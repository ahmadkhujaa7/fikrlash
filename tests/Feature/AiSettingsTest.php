<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Filament\Pages\AiSettings;
use App\Models\Post;
use App\Models\Setting;
use App\Models\User;
use App\Services\Ai\AiConfig;
use App\Services\Ai\AiManager;
use App\Services\Ai\Providers\ClaudeProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class AiSettingsTest extends TestCase
{
    public function test_env_config_is_used_until_admin_overrides_it(): void
    {
        config(['ai.provider' => 'fake', 'ai.providers.claude.api_key' => 'sk-ant-env-key-1234']);

        $this->assertSame('fake', AiConfig::provider());
        $this->assertSame('env', AiConfig::keySource('claude'));
        $this->assertSame('sk-ant-env-key-1234', AiConfig::providerConfig('claude')['api_key']);
        $this->assertSame(70, AiConfig::thresholds()['toxicity_review']);
    }

    public function test_admin_saves_provider_key_model_and_thresholds(): void
    {
        config(['ai.provider' => 'fake', 'ai.providers.claude.api_key' => null]);
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/ai-settings')->assertOk()->assertSee('AI ulanishi va sozlamalari');

        Livewire::actingAs($admin)->test(AiSettings::class)
            ->fillForm(['provider' => 'claude', 'claude_api_key' => '', 'claude_model' => 'claude-haiku-4-5'])
            ->call('save')
            ->assertHasFormErrors(['claude_api_key' => 'required']);

        Livewire::actingAs($admin)->test(AiSettings::class)
            ->fillForm([
                'provider' => 'claude',
                'claude_api_key' => 'sk-ant-admin-secret-9876',
                'claude_model' => 'claude-sonnet-5-5',
                'toxicity_review' => 55,
                'spam_review' => 90,
                'daily_limit' => 1200,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('claude', AiConfig::provider());
        $this->assertSame('admin', AiConfig::keySource('claude'));
        $this->assertSame('sk-ant-admin-secret-9876', AiConfig::providerConfig('claude')['api_key']);
        $this->assertSame('claude-sonnet-5-5', AiConfig::providerConfig('claude')['model']);
        $this->assertSame(['toxicity_review' => 55, 'spam_review' => 90, 'auto_category_min_quality' => 30], AiConfig::thresholds());
        $this->assertSame(1200, AiConfig::dailyLimit());
        $this->assertInstanceOf(ClaudeProvider::class, app(AiManager::class)->provider());

        // Kalit bazada ochiq holda saqlanmaydi va audit log'ga yozilmaydi.
        $this->assertStringNotContainsString('admin-secret', (string) json_encode(Setting::query()->pluck('value')));
        $this->assertStringNotContainsString('admin-secret', (string) DB::table('audit_logs')->pluck('new_values')->implode(' '));

        // Bo‘sh kalit maydoni — saqlangan kalit o‘zgarmaydi; "o‘chirish" — .env ga qaytadi.
        Livewire::actingAs($admin)->test(AiSettings::class)->fillForm(['claude_api_key' => ''])->call('save')->assertHasNoFormErrors();
        $this->assertSame('sk-ant-admin-secret-9876', AiConfig::storedKey('claude'));

        config(['ai.providers.claude.api_key' => 'sk-ant-env-key-1234']);
        Livewire::actingAs($admin)->test(AiSettings::class)->fillForm(['claude_forget_key' => true])->call('save')->assertHasNoFormErrors();
        $this->assertNull(AiConfig::storedKey('claude'));
        $this->assertSame('env', AiConfig::keySource('claude'));
    }

    public function test_admin_threshold_is_used_by_moderation(): void
    {
        Setting::write('ai_toxicity_review', 30); // bitta haqorat so‘zi (40 ball) ham yetadi
        $user = User::factory()->create();

        $this->actingAs($user)->post('/posts', ['content' => 'Bu fikr juda ahmoq, menimcha.']);

        $this->assertSame(PostStatus::PendingModeration, Post::query()->firstOrFail()->status);
    }

    public function test_connection_check_uses_unsaved_form_values(): void
    {
        config(['ai.providers.claude.api_key' => null]);
        Http::fake([
            'api.anthropic.com/*' => Http::sequence()
                ->push(['error' => ['message' => 'invalid x-api-key']], 401)
                ->push([
                    'model' => 'claude-haiku-4-5',
                    'content' => [['type' => 'tool_use', 'name' => 'record_post_analysis', 'input' => [
                        'topic' => 'Sinov', 'category' => null, 'sentiment' => 'neutral', 'quality_score' => 40, 'toxicity_score' => 0,
                        'spam_score' => 0, 'educational_score' => 10, 'engagement_score' => 20, 'summary' => 'Sinov', 'keywords' => [],
                    ]]],
                    'usage' => ['input_tokens' => 300, 'output_tokens' => 60],
                ]),
        ]);
        $admin = $this->admin();

        Livewire::actingAs($admin)->test(AiSettings::class)
            ->fillForm(['provider' => 'claude', 'claude_api_key' => 'sk-ant-wrong'])
            ->call('testConnection')
            ->assertNotified('Ulanish ishlamadi');

        Livewire::actingAs($admin)->test(AiSettings::class)
            ->fillForm(['provider' => 'claude', 'claude_api_key' => 'sk-ant-right'])
            ->call('testConnection')
            ->assertNotified('Ulanish ishlayapti');

        Http::assertSent(fn ($request) => $request->hasHeader('x-api-key', 'sk-ant-right'));
        // Tekshirish hech narsani saqlamaydi.
        $this->assertNull(AiConfig::storedKey('claude'));
    }

    public function test_settings_page_is_admin_only(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/ai-settings')->assertForbidden();
    }
}
