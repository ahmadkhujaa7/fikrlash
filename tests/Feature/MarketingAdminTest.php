<?php

namespace Tests\Feature;

use App\Filament\Pages\MarketingDashboard;
use App\Filament\Pages\MarketingSettings;
use App\Filament\Resources\MarketingLinks\Pages\CreateMarketingLink;
use App\Filament\Resources\MarketingLinks\Pages\ListMarketingLinks;
use App\Filament\Resources\MarketingLinks\Pages\ViewMarketingLink;
use App\Filament\Resources\MarketingLinks\Widgets\LinkStats;
use App\Filament\Widgets\Marketing\MarketingChannelsChart;
use App\Filament\Widgets\Marketing\MarketingFunnelStats;
use App\Filament\Widgets\Marketing\MarketingSourcesChart;
use App\Filament\Widgets\Marketing\TopMarketingLinks;
use App\Models\MarketingLink;
use App\Models\MarketingVisit;
use App\Models\Setting;
use App\Models\User;
use App\Support\TrackingIds;
use Livewire\Livewire;
use Tests\TestCase;

class MarketingAdminTest extends TestCase
{
    public function test_admin_creates_campaign_link_from_form(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/marketing-links/create')->assertOk()->assertSee('Yangi reklama havolasi');

        // Nomi yozilganda kod avtomatik taklif qilinadi.
        Livewire::actingAs($admin)->test(CreateMarketingLink::class)
            ->fillForm(['name' => 'Tg1 — @kitoblar'])
            ->assertSchemaStateSet(['code' => 'tg1-kitoblar']);

        Livewire::actingAs($admin)->test(CreateMarketingLink::class)
            ->fillForm(['name' => 'Tg1', 'code' => 'TG 1!', 'channel' => 'telegram', 'target' => '//evil.example'])
            ->call('create')
            ->assertHasFormErrors(['code' => 'regex', 'target' => 'regex']);

        Livewire::actingAs($admin)->test(CreateMarketingLink::class)
            ->fillForm(['name' => 'Tg1', 'code' => 'tg1', 'channel' => 'telegram', 'target' => '/register', 'partner' => '@kitoblar', 'cost' => 300000,
                'welcome' => 'Kitoblar kanali o‘quvchilari, xush kelibsiz!'])
            ->call('create')
            ->assertHasNoFormErrors();

        $link = MarketingLink::query()->where('code', 'tg1')->firstOrFail();
        $this->assertSame($admin->id, $link->created_by);
        $this->assertTrue($link->is_active);
        $this->assertSame('300000.00', $link->cost);

        // Kod band (arxivdagisi ham) — ikkinchi marta yaratib bo‘lmaydi.
        $link->delete();
        Livewire::actingAs($admin)->test(CreateMarketingLink::class)
            ->fillForm(['name' => 'Yana', 'code' => 'tg1', 'channel' => 'telegram', 'target' => '/register'])
            ->call('create')
            ->assertHasFormErrors(['code' => 'unique']);
    }

    public function test_list_view_and_qr_code(): void
    {
        $admin = $this->admin();
        $link = $this->seedLink();

        $this->actingAs($admin)->get('/admin/marketing-links')->assertOk()->assertSee('Tg1')->assertSee('/r/tg1');
        $this->actingAs($admin)->get('/admin/marketing-links/'.$link->id)->assertOk()
            ->assertSee('Ulashiladigan havola')->assertSee('Telegram')->assertSee('Android — 5 (100%)');
        Livewire::actingAs($admin)->test(LinkStats::class, ['record' => $link])
            ->assertSee('Ro‘yxatdan o‘tdi')->assertSee('40%')->assertSee('50 000');

        Livewire::actingAs($admin)->test(ListMarketingLinks::class)->assertCanSeeTableRecords([$link]);
        Livewire::actingAs($admin)->test(ViewMarketingLink::class, ['record' => $link->id])->assertOk();

        $png = $this->actingAs($admin)->get(route('marketing.qr', [$link, 'png']));
        $png->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertStringStartsWith("\x89PNG", $png->getContent());

        $this->actingAs($admin)->get(route('marketing.qr', [$link, 'svg', 'download' => 1]))->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="fikrlash-qr-tg1.svg"');

        // Oddiy foydalanuvchi va mehmon QR ololmaydi.
        $this->actingAs(User::factory()->create())->get(route('marketing.qr', [$link, 'png']))->assertForbidden();
        auth()->logout();
        $this->get(route('marketing.qr', [$link, 'png']))->assertRedirect(route('login'));
    }

    public function test_csv_export(): void
    {
        $this->seedLink();

        $csv = Livewire::actingAs($this->admin())->test(ListMarketingLinks::class)->callAction('export');
        $csv->assertFileDownloaded('fikrlash-reklama-havolalari-'.now()->format('Y-m-d').'.csv');
    }

    public function test_dashboard_widgets_follow_the_period_filter(): void
    {
        $admin = $this->admin();
        $link = $this->seedLink();

        $this->actingAs($admin)->get('/admin/marketing')->assertOk()->assertSee('Marketing');
        Livewire::actingAs($admin)->test(MarketingDashboard::class)->assertOk();

        Livewire::actingAs($admin)->test(MarketingFunnelStats::class, ['pageFilters' => ['period' => '30']])
            ->assertOk()->assertSee('Havola bosishlari')->assertSee('Do‘st taklifi bilan');
        Livewire::actingAs($admin)->test(MarketingSourcesChart::class, ['pageFilters' => ['period' => '30']])->assertOk();
        Livewire::actingAs($admin)->test(MarketingChannelsChart::class, ['pageFilters' => ['period' => '7']])->assertOk();
        Livewire::actingAs($admin)->test(TopMarketingLinks::class, ['pageFilters' => ['period' => '30']])
            ->assertCanSeeTableRecords([$link]);
    }

    public function test_settings_and_inviters_pages(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/marketing-settings')->assertOk()->assertSee('Meta (Facebook/Instagram) Pixel ID');

        Livewire::actingAs($admin)->test(MarketingSettings::class)
            ->fillForm(['marketing_meta_pixel' => 'abc', 'marketing_ga4' => 'UA-1'])
            ->call('save')
            ->assertHasFormErrors(['marketing_meta_pixel' => 'regex', 'marketing_ga4' => 'regex']);

        Livewire::actingAs($admin)->test(MarketingSettings::class)
            ->fillForm(['marketing_attribution_days' => 14, 'marketing_invites_enabled' => false, 'marketing_meta_pixel' => '1234567890', 'marketing_ga4' => 'g-abc123xyz'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(14, Setting::read('marketing_attribution_days'));
        $this->assertFalse(Setting::read('marketing_invites_enabled'));
        $this->assertSame(['pixel' => '1234567890', 'ga4' => 'G-ABC123XYZ', 'metrika' => null], TrackingIds::all());

        // Taklif qilganlar reytingi.
        $inviter = User::factory()->create(['name' => 'Laylo Taklifchi']);
        User::factory()->count(2)->create(['referred_by' => $inviter->id, 'acquisition_source' => 'invite']);
        $this->actingAs($admin)->get('/admin/inviters')->assertOk()->assertSee('Laylo Taklifchi');

        // Foydalanuvchi sahifasida manba ko‘rinadi.
        $invitee = User::query()->where('referred_by', $inviter->id)->first();
        $invitee->forceFill(['acquisition_detail' => '@'.$inviter->username])->save();
        $this->actingAs($admin)->get('/admin/users/'.$invitee->id)->assertOk()
            ->assertSee('Taklif: @'.$inviter->username)->assertSee('Laylo Taklifchi');
    }

    private function seedLink(): MarketingLink
    {
        $link = MarketingLink::query()->create(['name' => 'Tg1', 'code' => 'tg1', 'channel' => 'telegram', 'target' => '/register', 'cost' => 100000, 'is_active' => true]);

        foreach (range(1, 5) as $i) {
            (new MarketingVisit)->forceFill([
                'marketing_link_id' => $link->id, 'visitor' => str_pad((string) $i, 32, 'a'), 'is_unique' => true,
                'device' => 'mobile', 'os' => 'android', 'browser' => 'chrome', 'app' => 'telegram', 'created_at' => now()->subDays($i),
            ])->save();
        }
        User::factory()->count(2)->create(['acquisition_source' => 'link', 'acquisition_detail' => 'tg1', 'acquisition_link_id' => $link->id]);
        $link->forceFill(['clicks_count' => 5, 'visitors_count' => 5, 'signups_count' => 2, 'last_click_at' => now()->subDay()])->save();

        return $link;
    }
}
