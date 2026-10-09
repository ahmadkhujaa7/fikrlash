<?php

namespace Tests\Feature;

use App\Models\MarketingLink;
use App\Models\MarketingVisit;
use App\Models\Notification;
use App\Models\Setting;
use App\Models\User;
use App\Services\Marketing\MarketingService;
use Tests\TestCase;

class MarketingTest extends TestCase
{
    private array $form = [
        'name' => 'Aziz Karimov',
        'username' => 'aziz_k',
        'phone' => '90 123 45 67',
        'password' => 'parol1234',
        'password_confirmation' => 'parol1234',
        'terms' => '1',
    ];

    private const PHONE_UA = 'Mozilla/5.0 (Linux; Android 14; SM-A546E) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Mobile Safari/537.36 Telegram-Android/11.2.1';

    private function link(array $attrs = []): MarketingLink
    {
        return MarketingLink::query()->create($attrs + ['name' => 'Tg1', 'code' => 'tg1', 'channel' => 'telegram', 'target' => '/register']);
    }

    /** Ro‘yxatdan o‘tish (2 bosqich) — cookie'lar bilan, haqiqiy foydalanuvchi kabi. */
    private function register(array $cookies, array $form = []): User
    {
        $form += $this->form;
        $this->fresh($cookies);
        $this->withHeader('User-Agent', self::PHONE_UA)->post('/register', $form)->assertRedirect(route('register.verify'));
        $code = $this->sms()->lastCodeFor('+998'.preg_replace('/\D/', '', $form['phone']));
        $this->post('/register/verify', ['code' => $code])->assertRedirect(route('home'));

        return User::query()->where('username', $form['username'])->firstOrFail();
    }

    /** Yangi "brauzer": faqat berilgan cookie'lar, tizimdan chiqilgan. */
    private function fresh(array $cookies = []): static
    {
        if (auth()->check()) {
            auth()->logout();
        }
        $this->defaultCookies = [];
        $this->unencryptedCookies = [];

        return $this->withCookies($cookies);
    }

    /** Javobdagi cookie qiymatlari (shifrlanmagan holda) — keyingi so‘rovga uzatish uchun. */
    private function cookiesFrom($response): array
    {
        return collect($response->headers->getCookies())->mapWithKeys(fn ($c) => [$c->getName() => $c->getValue()])
            ->only([MarketingService::COOKIE_VISITOR, MarketingService::COOKIE_REF, MarketingService::COOKIE_INVITE, MarketingService::COOKIE_UTM, MarketingService::COOKIE_SOURCE])
            ->map(fn ($v) => decrypt($v, false))
            ->map(fn ($v) => str_contains($v, '|') ? substr($v, strpos($v, '|') + 1) : $v)
            ->all();
    }

    public function test_campaign_link_counts_clicks_and_redirects(): void
    {
        $link = $this->link();

        $first = $this->withHeader('User-Agent', self::PHONE_UA)->get('/r/tg1')->assertRedirect('/register');
        $cookies = $this->cookiesFrom($first);
        $this->assertArrayHasKey(MarketingService::COOKIE_REF, $cookies);

        // O‘sha odam yana bosdi — bosish +1, noyob tashrifchi o‘zgarmaydi.
        $this->fresh($cookies)->withHeader('User-Agent', self::PHONE_UA)->get('/r/TG1')->assertRedirect('/register');
        // Telegram havola ko‘rinishini yasovchi bot — hisoblanmaydi.
        $this->withHeader('User-Agent', 'TelegramBot (like TwitterBot)')->get('/r/tg1')->assertRedirect('/register');

        $link->refresh();
        $this->assertSame(2, $link->clicks_count);
        $this->assertSame(1, $link->visitors_count);
        $visit = MarketingVisit::query()->first();
        $this->assertSame(['mobile', 'android', 'chrome', 'telegram'], [$visit->device, $visit->os, $visit->browser, $visit->app]);

        // Noma'lum kod — bosh sahifaga; o‘chirilgan havola — yo‘naltiradi, lekin hisoblamaydi.
        $this->get('/r/yoq')->assertRedirect(route('home'));
        $link->update(['is_active' => false]);
        $this->withHeader('User-Agent', self::PHONE_UA)->get('/r/tg1')->assertRedirect('/register');
        $this->assertSame(2, $link->fresh()->clicks_count);

        // Tashqi saytga yo‘naltirib bo‘lmaydi.
        $evil = $this->link(['name' => 'X', 'code' => 'x', 'target' => '//evil.example']);
        $this->get('/r/x')->assertRedirect('/register');
        $this->assertSame('/register', $evil->targetPath());
    }

    public function test_signup_through_link_is_attributed(): void
    {
        $link = $this->link(['welcome' => 'Tg1 kanali obunachilariga xush kelibsiz!']);
        $cookies = $this->cookiesFrom($this->withHeader('User-Agent', self::PHONE_UA)->get('/r/tg1'));

        $this->fresh($cookies)->get('/register')->assertOk()->assertSee('Tg1 kanali obunachilariga xush kelibsiz!');
        $user = $this->register($cookies);

        $this->assertSame('link', $user->acquisition_source);
        $this->assertSame('tg1', $user->acquisition_detail);
        $this->assertTrue($user->acquisitionLink->is($link));
        $link->refresh();
        $this->assertSame([1, 1, 1], [$link->visitors_count, $link->starts_count, $link->signups_count]);

        $funnel = app(MarketingService::class)->funnel(now()->subDay(), $link);
        $this->assertSame(1, $funnel['signups']);
        $this->assertSame(100.0, $funnel['conversion']);
    }

    public function test_ref_parameter_utm_and_referrer_sources(): void
    {
        $link = $this->link(['code' => 'insta1', 'channel' => 'instagram']);

        // ?ref=kod istalgan sahifada ham ishlaydi.
        $cookies = $this->cookiesFrom($this->withHeader('User-Agent', self::PHONE_UA)->get('/?ref=insta1'));
        $this->assertSame(1, $link->fresh()->clicks_count);
        $this->assertSame('link', $this->register($cookies)->acquisition_source);

        // UTM belgilari bilan kelgan.
        $cookies = $this->cookiesFrom($this->fresh()->withHeader('User-Agent', self::PHONE_UA)->get('/login?utm_source=instagram&utm_medium=paid&utm_campaign=register_a'));
        $user = $this->register($cookies, ['username' => 'utm_user', 'phone' => '90 222 33 44']);
        $this->assertSame(['utm', 'instagram / register_a'], [$user->acquisition_source, $user->acquisition_detail]);

        // Boshqa saytdan (t.me) o‘tgan.
        $cookies = $this->cookiesFrom($this->fresh()->withHeaders(['User-Agent' => self::PHONE_UA, 'Referer' => 'https://t.me/some_channel/12'])->get('/login'));
        $user = $this->register($cookies, ['username' => 'tg_user', 'phone' => '90 333 44 55']);
        $this->assertSame(['referrer', 't.me'], [$user->acquisition_source, $user->acquisition_detail]);

        // Hech narsasiz.
        $user = $this->register([], ['username' => 'direct_user', 'phone' => '90 444 55 66']);
        $this->assertSame('direct', $user->acquisition_source);

        $sources = app(MarketingService::class)->sources(now()->subDay());
        $this->assertEquals(['direct' => 1, 'link' => 1, 'referrer' => 1, 'utm' => 1], $sources->sortKeys()->all());
    }

    public function test_friend_invite(): void
    {
        $laylo = User::factory()->create(['username' => 'laylo']);

        $this->actingAs($laylo)->get('/invite')->assertOk()->assertSee(route('invite', 'laylo'));
        auth()->logout();

        $cookies = $this->cookiesFrom($this->withHeader('User-Agent', self::PHONE_UA)->get('/i/laylo')->assertRedirect(route('register')));
        $this->fresh($cookies)->get('/register')->assertSee('sizni Fikrlash’ga taklif qildi', false);

        $user = $this->register($cookies);
        $this->assertSame(['invite', '@laylo'], [$user->acquisition_source, $user->acquisition_detail]);
        $this->assertTrue($user->referrer->is($laylo));
        $this->assertSame(1, $laylo->invitees()->count());
        $this->assertStringContainsString('taklifingiz bilan', (string) Notification::query()->where('user_id', $laylo->id)->latest('id')->first()->data['message']);

        // Takliflar o‘chirilsa — havola shunchaki ro‘yxatdan o‘tishga olib boradi, sahifa yopiladi.
        Setting::write('marketing_invites_enabled', false);
        $this->fresh()->get('/i/laylo')->assertRedirect(route('register'));
        $this->assertEmpty(collect($this->get('/i/laylo')->headers->getCookies())->filter(fn ($c) => $c->getName() === MarketingService::COOKIE_INVITE && $c->getExpiresTime() > time()));
        $this->actingAs($laylo)->get('/invite')->assertNotFound();
    }

    public function test_old_cookies_expire_after_attribution_window(): void
    {
        $link = $this->link();
        Setting::write('marketing_attribution_days', 7);
        $cookies = [MarketingService::COOKIE_REF => json_encode(['l' => $link->id, 'v' => null, 't' => now()->subDays(8)->timestamp])];

        $this->assertSame('direct', $this->register($cookies)->acquisition_source);
        $this->assertSame(0, $link->fresh()->signups_count);
    }

    public function test_tracking_scripts_and_signup_event(): void
    {
        Setting::write('marketing_meta_pixel', '123456789012345');
        Setting::write('marketing_ga4', 'G-ABC123XYZ');
        Setting::write('marketing_yandex_metrika', '98765432');

        $page = $this->get('/login')->assertOk();
        $page->assertSee("fbq('init', '123456789012345')", false)->assertSee('G-ABC123XYZ', false)->assertSee('ym(98765432', false);
        $csp = $page->headers->get('Content-Security-Policy');
        foreach (['connect.facebook.net', 'www.googletagmanager.com', 'mc.yandex.ru'] as $host) {
            $this->assertStringContainsString($host, $csp);
        }
        $page->assertDontSee('CompleteRegistration', false);

        $this->register([]);
        $this->get('/')->assertSee("fbq('track', 'CompleteRegistration')", false)->assertSee("'sign_up'", false)->assertSee("reachGoal', 'signup'", false);
        $this->get('/')->assertDontSee('CompleteRegistration', false); // faqat bir marta

        // Admin panelda skriptlar yo‘q.
        $this->actingAs($this->admin())->get('/admin')->assertDontSee('connect.facebook.net', false);
    }
}
