<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Filament\Resources\Announcements\Pages\CreateAnnouncement;
use App\Filament\Resources\Announcements\Pages\EditAnnouncement;
use App\Filament\Resources\Announcements\Pages\ViewAnnouncement;
use App\Filament\Resources\Announcements\RelationManagers\ReceiptsRelationManager;
use App\Filament\Resources\Announcements\Widgets\AnnouncementStats;
use App\Models\Announcement;
use App\Models\Notification;
use App\Models\User;
use App\Services\Social\AnnouncementService;
use Livewire\Livewire;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    public function test_admin_sends_announcement_and_sees_who_saw_and_opened_it(): void
    {
        $admin = $this->admin();
        [$aziz, $laylo, $sardor] = User::factory()->count(3)->create();
        $blocked = User::factory()->create(['status' => UserStatus::Blocked]);

        Livewire::actingAs($admin)->test(CreateAnnouncement::class)
            ->fillForm(['title' => 'Chatda yangi imkoniyatlar', 'body' => "Endi rasm, video va joylashuv yuborish mumkin.\nBatafsil: fikrlash.uz", 'audience' => 'all'])
            ->call('create')
            ->assertHasNoFormErrors();

        $announcement = Announcement::query()->sole();
        $this->assertSame(4, $announcement->recipients_count); // admin + 3 faol; bloklangan — yo‘q
        $this->assertNotNull($announcement->sent_at);
        $this->assertSame(1, $aziz->notifications()->unread()->count());
        $this->assertSame(0, $blocked->notifications()->count());

        // Ro‘yxatda: sarlavha va qisqa matn; sahifa ochilishi — "ko‘rdi".
        $this->actingAs($aziz)->get('/notifications')->assertOk()
            ->assertSee('Chatda yangi imkoniyatlar')->assertSee('Batafsil o‘qish')->assertSee('href="https://fikrlash.uz"', false);
        $this->actingAs($laylo)->get('/notifications')->assertOk();

        // Bosib ochish — "ochdi"; qabul qilmagan foydalanuvchi uchun — 404.
        $this->actingAs($aziz)->postJson("/announcements/{$announcement->id}/open")->assertOk();
        $this->actingAs($aziz)->postJson("/announcements/{$announcement->id}/open")->assertOk(); // takror — o‘zgarmaydi
        $this->actingAs($blocked)->postJson("/announcements/{$announcement->id}/open")->assertStatus(403);
        $this->actingAs(User::factory()->create())->postJson("/announcements/{$announcement->id}/open")->assertNotFound();

        $stats = app(AnnouncementService::class)->stats($announcement);
        $this->assertSame(['recipients' => 4, 'seen' => 2, 'opened' => 1, 'seen_rate' => 50.0, 'open_rate' => 25.0], $stats);

        // Admin: statistika, ko‘rganlar ro‘yxati.
        $this->actingAs($admin)->get("/admin/announcements/{$announcement->id}")->assertOk();
        $this->actingAs($admin)->get('/admin/announcements')->assertOk()->assertSee('Chatda yangi imkoniyatlar');
        Livewire::actingAs($admin)->test(AnnouncementStats::class, ['record' => $announcement])
            ->assertSee('Ro‘yxatda ko‘rdi')->assertSee('50%')->assertSee('Bosib ochdi')->assertSee('25%');
        Livewire::actingAs($admin)->test(ReceiptsRelationManager::class, ['ownerRecord' => $announcement, 'pageClass' => ViewAnnouncement::class])
            ->assertSee($aziz->name)->assertSee($laylo->name)->assertDontSee($sardor->name);

        // Tahrirlash — hammada yangilanadi.
        Livewire::actingAs($admin)->test(EditAnnouncement::class, ['record' => $announcement->getRouteKey()])
            ->fillForm(['title' => 'Chatda rasm va video'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->actingAs($sardor)->get('/notifications')->assertSee('Chatda rasm va video')->assertDontSee('Chatda yangi imkoniyatlar');

        // O‘chirish — hammadan o‘chadi.
        $announcement->delete();
        $this->assertSame(0, Notification::query()->where('type', 'announcement')->count());
        $this->assertDatabaseCount('announcement_receipts', 0);
        $this->actingAs($aziz)->get('/notifications')->assertOk()->assertDontSee('Chatda rasm va video');
    }

    public function test_announcement_to_selected_users_only(): void
    {
        $admin = $this->admin();
        [$aziz, $laylo] = User::factory()->count(2)->create();

        Livewire::actingAs($admin)->test(CreateAnnouncement::class)
            ->fillForm(['title' => 'Faqat siz uchun', 'body' => 'Sinov', 'audience' => 'users', 'user_ids' => [$aziz->id]])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(1, Announcement::query()->sole()->recipients_count);
        $this->assertSame(1, $aziz->notifications()->count());
        $this->assertSame(0, $laylo->notifications()->count());

        // API ham e'lon mazmunini beradi.
        $this->actingAs($aziz)->getJson('/api/v1/notifications')->assertOk()
            ->assertJsonPath('data.0.type', 'announcement')
            ->assertJsonPath('data.0.announcement.title', 'Faqat siz uchun');
    }
}
