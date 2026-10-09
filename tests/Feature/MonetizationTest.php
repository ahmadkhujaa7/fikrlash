<?php

namespace Tests\Feature;

use App\Filament\Pages\MonetizationSettings;
use App\Filament\Resources\AuthorApplications\Pages\ManageAuthorApplications;
use App\Filament\Resources\AuthorPayouts\Pages\ManageAuthorPayouts;
use App\Filament\Resources\Authors\Pages\ManageAuthors;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\AuthorApplication;
use App\Models\AuthorEarning;
use App\Models\AuthorPayout;
use App\Models\Post;
use App\Models\Setting;
use App\Models\User;
use App\Services\Monetization\MonetizationService;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class MonetizationTest extends TestCase
{
    private function settings(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::write('monetization_'.$key, $value);
        }
    }

    private function article(User $author, array $attrs = []): Post
    {
        return Post::factory()->for($author)->create(['type' => Post::TYPE_ARTICLE, 'title' => 'Maqola '.fake()->word()] + $attrs);
    }

    /** Ro‘yxatdan o‘tgan o‘quvchi maqolani ochdi va o‘qidi. */
    private function viewArticle(Post $post, User $reader, int $seconds, string $at): void
    {
        DB::table('post_views')->insert(['user_id' => $reader->id, 'post_id' => $post->id, 'read_seconds' => $seconds, 'first_viewed_at' => $at, 'last_viewed_at' => $at]);
    }

    public function test_author_applies_when_thresholds_are_met(): void
    {
        $this->settings(['min_followers' => 2, 'min_views' => 100, 'min_articles' => 2]);
        $author = User::factory()->create(['followers_count' => 1]);
        $this->article($author, ['views_count' => 80]);
        Post::factory()->for($author)->create(['views_count' => 5000]); // oddiy fikr — hisoblanmaydi

        $this->actingAs($author)->get('/monetization')->assertOk()->assertSee('Talablar')->assertSee('1 / 2');
        $this->actingAs($author)->post('/monetization/apply')->assertRedirect()->assertSessionHas('toast', 'Hali talablarga yetmagansiz.');
        $this->assertDatabaseCount('author_applications', 0);

        $author->forceFill(['followers_count' => 2])->save();
        $this->article($author, ['views_count' => 30]);
        $this->actingAs($author)->post('/monetization/apply', ['message' => 'Tarix haqida yozaman'])->assertSessionHas('toast');
        $this->assertDatabaseHas('author_applications', ['user_id' => $author->id, 'status' => 'pending', 'followers' => 2, 'article_views' => 110, 'articles' => 2]);

        // Ikkinchi so‘rov — yo‘q.
        $this->actingAs($author)->post('/monetization/apply')->assertSessionHas('toast', 'So‘rovingiz ko‘rib chiqilmoqda.');
        $this->actingAs($author)->get('/monetization')->assertSee('ko‘rib chiqilmoqda');
    }

    public function test_admin_approves_and_only_new_articles_earn(): void
    {
        $this->settings(['min_followers' => 0, 'min_views' => 0, 'min_articles' => 0, 'rate_views' => 1000, 'rate_amount' => 5000, 'min_read_seconds' => 15, 'min_payout' => 10]);
        $admin = $this->admin();
        $author = User::factory()->create(['name' => 'Dilnoza Karimova']);
        $readers = User::factory()->count(4)->create();
        $old = $this->article($author, ['published_at' => now()->subDays(10)]);

        $this->travelTo(now()->subDays(2));
        $application = app(MonetizationService::class)->apply($author);
        Livewire::actingAs($admin)->test(ManageAuthorApplications::class)
            ->assertSee('Dilnoza Karimova')
            ->callTableAction('approve', $application, ['note' => 'Xush kelibsiz'])
            ->assertHasNoTableActionErrors();
        $this->travelBack();

        $author->refresh();
        $this->assertTrue($author->isMonetized());
        $this->assertSame('approved', $application->fresh()->status);
        $this->assertDatabaseHas('notifications', ['user_id' => $author->id, 'type' => 'monetization']);

        // Profilda "Muallif" belgisi.
        $this->get('/@'.$author->username)->assertSee('Muallif — maqolalari monetizatsiya qilingan', false);

        $new = $this->article($author, ['published_at' => now()->subDay()]);
        $at = now()->subHours(3)->toDateTimeString();
        $this->viewArticle($new, $readers[0], 40, $at);   // ✓
        $this->viewArticle($new, $readers[1], 20, $at);   // ✓
        $this->viewArticle($new, $readers[2], 3, $at);    // ✗ juda qisqa o‘qilgan
        $this->viewArticle($new, $author, 100, $at);      // ✗ o‘zi
        $this->viewArticle($old, $readers[3], 60, $at);   // ✗ monetizatsiyadan oldingi maqola
        $this->viewArticle($new, $readers[3], 60, now()->subMinutes(5)->toDateTimeString()); // hali kechikish oynasida

        $this->assertSame(2, app(MonetizationService::class)->accrue());
        $this->assertSame(0, app(MonetizationService::class)->accrue()); // ikki marta hisoblanmaydi
        $this->assertSame('10.00', AuthorEarning::query()->sole()->amount); // 2 × 5000/1000

        // Narx o‘zgardi — keyingi ko‘rishlar yangi narxda, eskilari o‘zgarmaydi.
        $this->settings(['rate_amount' => 10000]);
        $this->travel(40)->minutes();
        $this->assertSame(1, app(MonetizationService::class)->accrue());
        $this->assertEquals(20.0, (float) AuthorEarning::query()->sum('amount'));

        $this->actingAs($author)->get('/monetization')->assertOk()
            ->assertSee('Muallif paneli')->assertSee('20 so‘m')->assertSee('Monetizatsiyadan oldin');
    }

    public function test_payout_request_and_admin_processing(): void
    {
        $this->settings(['min_payout' => 50000]);
        $admin = $this->admin();
        $author = User::factory()->create(['monetized_at' => now()->subMonth()]);
        $post = $this->article($author);
        AuthorEarning::query()->create(['user_id' => $author->id, 'post_id' => $post->id, 'date' => today()->subDay(), 'views' => 20000, 'amount' => 100000]);

        $this->actingAs($author)->post('/monetization/payouts', ['amount' => 20000, 'account' => '8600 1234 5678 9012', 'holder' => 'Dilnoza K'])
            ->assertSessionHasErrors(['amount' => 'Eng kam yechish summasi — 50 000 so‘m.']);
        $this->actingAs($author)->post('/monetization/payouts', ['amount' => 150000, 'account' => '8600123456789012', 'holder' => 'Dilnoza K'])
            ->assertSessionHasErrors('amount');
        $this->actingAs($author)->post('/monetization/payouts', ['amount' => 60000, 'account' => '12', 'holder' => 'Dilnoza K'])
            ->assertSessionHasErrors('account');

        $this->actingAs($author)->post('/monetization/payouts', ['amount' => 60000, 'account' => '8600 1234 5678 9012', 'holder' => 'Dilnoza K'])->assertSessionHasNoErrors();
        $payout = AuthorPayout::query()->sole();
        $this->assertSame('8600123456789012', $payout->account);
        $this->assertNotSame('8600123456789012', DB::table('author_payouts')->value('account')); // bazada shifrlangan
        $this->assertSame(40000.0, app(MonetizationService::class)->balance($author)['balance']);

        // Kutilayotgan so‘rov bor — yana so‘rab bo‘lmaydi.
        $this->actingAs($author)->post('/monetization/payouts', ['amount' => 50000, 'account' => '8600123456789012', 'holder' => 'D'])->assertSessionHasErrors('amount');

        Livewire::actingAs($admin)->test(ManageAuthorPayouts::class)
            ->assertSee('8600 •••• •••• 9012')
            ->callTableAction('paid', $payout, ['reference' => 'CHK-778'])
            ->assertHasNoTableActionErrors();
        $this->assertSame('paid', $payout->fresh()->status);
        $this->assertSame(['earned' => 100000.0, 'paid' => 60000.0, 'pending' => 0.0, 'held' => 60000.0, 'balance' => 40000.0], app(MonetizationService::class)->balance($author));

        // Rad etilgan so‘rov summasi balansga qaytadi.
        $this->settings(['min_payout' => 1000]);
        $second = app(MonetizationService::class)->requestPayout($author, 40000, '8600123456789012', 'D');
        $this->assertSame(0.0, app(MonetizationService::class)->balance($author)['balance']);
        app(MonetizationService::class)->rejectPayout($second, $admin, 'Karta egasi mos emas');
        $this->assertSame(40000.0, app(MonetizationService::class)->balance($author)['balance']);
        $this->assertDatabaseHas('notifications', ['user_id' => $author->id, 'type' => 'monetization']);
    }

    public function test_admin_pages_and_settings(): void
    {
        $admin = $this->admin();
        $author = User::factory()->create(['monetized_at' => now()]);

        foreach (['/admin/monetization-settings', '/admin/author-applications', '/admin/author-payouts', '/admin/authors'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }

        Livewire::actingAs($admin)->test(MonetizationSettings::class)
            ->fillForm(['monetization_min_followers' => 250, 'monetization_rate_amount' => 7000, 'monetization_rate_views' => 1000, 'monetization_min_payout' => 200000])
            ->call('save');
        $s = app(MonetizationService::class)->settings();
        $this->assertSame(250, $s['min_followers']);
        $this->assertSame(7000.0, $s['rate_amount']);
        $this->assertSame(200000.0, $s['min_payout']);

        Livewire::actingAs($admin)->test(ManageAuthors::class)
            ->assertSee($author->name)
            ->callTableAction('revoke', $author, ['reason' => 'Qoidabuzarlik'])
            ->assertHasNoTableActionErrors();
        $this->assertFalse($author->fresh()->isMonetized());
        $this->assertSame(0, AuthorApplication::query()->where('status', 'approved')->count());
    }

    public function test_admin_grants_and_revokes_authorship_from_users_page(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['name' => 'Jasur Aliyev']);

        // Jadvaldagi amal: talablarsiz muallif qilish.
        Livewire::actingAs($admin)->test(ListUsers::class)
            ->callTableAction('grantAuthor', $user, ['note' => 'Sifatli maqolalar uchun'])
            ->assertHasNoTableActionErrors();
        $user->refresh();
        $this->assertTrue($user->isMonetized());
        $this->assertSame(AuthorApplication::APPROVED, $user->authorApplications()->value('status'));
        $this->assertStringContainsString('Sifatli maqolalar uchun', (string) $user->notifications()->latest('id')->first()->data['message']);
        $this->actingAs($admin)->get('/admin/users/'.$user->id)->assertOk()->assertSee('Mualliflikni to‘xtatish');

        // Tahrirlash formasidagi tugma bilan o‘chirish va qayta yoqish.
        Livewire::actingAs($admin)->test(EditUser::class, ['record' => $user->getRouteKey()])
            ->assertFormSet(['is_author' => true])
            ->fillForm(['is_author' => false])->call('save')->assertHasNoFormErrors();
        $this->assertFalse($user->fresh()->isMonetized());
        $this->assertSame(AuthorApplication::REVOKED, $user->authorApplications()->value('status'));

        Livewire::actingAs($admin)->test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm(['is_author' => true])->call('save')->assertHasNoFormErrors();
        $this->assertTrue($user->fresh()->isMonetized());

        Livewire::actingAs($admin)->test(ListUsers::class)
            ->filterTable('author', true)->assertCanSeeTableRecords([$user])->assertCanNotSeeTableRecords([$admin]);
    }

    public function test_revoked_author_with_balance_can_apply_again(): void
    {
        $this->settings(['min_followers' => 0, 'min_views' => 0, 'min_articles' => 0]);
        $admin = $this->admin();
        $author = User::factory()->create(['monetized_at' => now()->subMonth()]);
        $this->article($author, ['published_at' => now()->subWeeks(2)]);
        AuthorEarning::query()->create(['user_id' => $author->id, 'post_id' => Post::query()->value('id'), 'date' => today(), 'views' => 10, 'amount' => 50]);
        $author->authorApplications()->create(['status' => AuthorApplication::APPROVED]);

        app(MonetizationService::class)->revoke($author, $admin, 'Takroriy kontent');
        $this->assertStringContainsString('qayta so‘rov yuborishingiz mumkin', (string) $author->notifications()->latest('id')->first()->data['message']);

        // Balansi bor — panel ochiladi, unda sabab va "Qayta so‘rov yuborish" tugmasi.
        $this->actingAs($author)->get('/monetization')->assertOk()
            ->assertSee('Monetizatsiya to‘xtatilgan')->assertSee('Takroriy kontent')->assertSee('Qayta so‘rov yuborish');
        $this->actingAs($author)->get('/monetization?apply=1')->assertOk()
            ->assertSee('Muallif bo‘lish uchun so‘rov')->assertSee('Balans va to‘lovlar');

        $this->actingAs($author)->post('/monetization/apply', ['message' => 'Endi faqat original yozaman'])->assertSessionHas('toast');
        $this->assertSame(AuthorApplication::PENDING, $author->authorApplications()->latest('id')->value('status'));
        $this->actingAs($author)->get('/monetization')->assertSee('Qayta so‘rovingiz ko‘rib chiqilmoqda')->assertDontSee('Qayta so‘rov yuborish');

        // Admin tasdiqlaydi — muallif qaytadan monetizatsiyada.
        app(MonetizationService::class)->approve($author->authorApplications()->latest('id')->first(), $admin);
        $this->assertTrue($author->fresh()->isMonetized());
    }
}
