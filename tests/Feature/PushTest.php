<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\DeviceToken;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PushTest extends TestCase
{
    private function enableFcm(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);
        config([
            'fikrlash.push.credentials' => json_encode([
                'type' => 'service_account',
                'project_id' => 'fikrlash-test',
                'client_email' => 'push@fikrlash-test.iam.gserviceaccount.com',
                'private_key' => $pem,
                'token_uri' => 'https://oauth2.googleapis.com/token',
            ]),
            'fikrlash.push.dispatch' => 'queue', // testda navbat — sync
        ]);

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.test', 'expires_in' => 3600]),
            'fcm.googleapis.com/v1/projects/fikrlash-test/messages:send' => function (Request $request) {
                return $request['message']['token'] === 'eskirgan-token-0000000000000'
                    ? Http::response(['error' => ['status' => 'NOT_FOUND', 'message' => 'Requested entity was not found.', 'details' => [['errorCode' => 'UNREGISTERED']]]], 404)
                    : Http::response(['name' => 'projects/fikrlash-test/messages/1']);
            },
        ]);
    }

    public function test_app_registers_and_forgets_device_token(): void
    {
        [$aziz, $laylo] = User::factory()->count(2)->create();
        $token = str_repeat('a', 40);

        $this->postJson('/devices', ['token' => $token, 'platform' => 'android'])->assertUnauthorized();
        $this->actingAs($aziz)->postJson('/devices', ['token' => $token, 'platform' => 'windows'])->assertStatus(422);

        $this->actingAs($aziz)->postJson('/devices', ['token' => $token, 'platform' => 'android', 'app_version' => '1.0.0'])
            ->assertOk()->assertCookie('fk_device');
        $this->assertDatabaseHas('device_tokens', ['user_id' => $aziz->id, 'platform' => 'android']);

        // Shu telefonda boshqa akkaunt bilan kirildi — token unga o‘tadi (ikki kishiga bitta telefon emas).
        $this->actingAs($laylo)->postJson('/api/v1/devices', ['token' => $token, 'platform' => 'android'])->assertOk();
        $this->assertSame($laylo->id, DeviceToken::query()->sole()->user_id);

        $this->actingAs($laylo)->deleteJson('/api/v1/devices', ['token' => $token])->assertOk()->assertJsonPath('deleted', 1);
        $this->assertDatabaseCount('device_tokens', 0);
    }

    public function test_logout_inside_app_stops_pushes_to_that_phone(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->postJson('/devices', ['token' => str_repeat('b', 40), 'platform' => 'android']);
        $cookie = $response->getCookie('fk_device');

        $this->actingAs($user)->withCookie('fk_device', $cookie->getValue())->post('/logout')->assertRedirect();
        $this->assertDatabaseCount('device_tokens', 0);
    }

    public function test_likes_messages_and_bad_tokens(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->enableFcm();
        [$me, $aziz] = User::factory()->count(2)->create();
        $aziz->forceFill(['name' => 'Aziz Karimov'])->save();
        DeviceToken::query()->create(['user_id' => $me->id, 'token' => 'yaxshi-token-00000000000000', 'platform' => 'android']);
        DeviceToken::query()->create(['user_id' => $me->id, 'token' => 'eskirgan-token-0000000000000', 'platform' => 'android']);
        $post = Post::factory()->for($me)->create(['content' => 'Kitob o‘qish haqida fikrim']);

        // Layk — telefonga "Aziz Karimov: fikringizni yoqtirdi".
        $this->actingAs($aziz)->postJson("/api/v1/posts/{$post->id}/like")->assertOk();

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'messages:send')
            && $r['message']['token'] === 'yaxshi-token-00000000000000'
            && $r['message']['notification']['title'] === 'Aziz Karimov'
            && str_starts_with($r['message']['notification']['body'], 'fikringizni yoqtirdi')
            && $r['message']['data']['url'] === route('posts.show', $post)
            && $r['message']['android']['notification']['channel_id'] === 'activity'
            && $r->hasHeader('Authorization', 'Bearer ya29.test'));
        // Eskirgan token o‘chirildi.
        $this->assertSame(['yaxshi-token-00000000000000'], DeviceToken::query()->pluck('token')->all());

        // Chat xabari — "Xabarlar" kanali, suhbat sahifasi ochiladi.
        $this->actingAs($aziz)->get("/messages/with/{$me->username}");
        $conversation = Conversation::query()->where('pair_key', Conversation::pairKey($me->id, $aziz->id))->firstOrFail();
        $this->actingAs($aziz)->postJson("/messages/{$conversation->id}", ['body' => 'Salom! Ertaga uchrashamizmi?'])->assertCreated();

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'messages:send')
            && $r['message']['notification']['body'] === 'Salom! Ertaga uchrashamizmi?'
            && $r['message']['data']['url'] === route('messages.show', $conversation)
            && $r['message']['android']['notification']['channel_id'] === 'messages'
            && $r['message']['android']['notification']['tag'] === 'chat-'.$conversation->id);

        // O‘chirilgan tur — push ham yo‘q.
        $me->forceFill(['notification_settings' => ['muted' => ['followed']]])->save();
        $sends = fn () => collect(Http::recorded())->filter(fn ($pair) => str_contains($pair[0]->url(), 'messages:send'))->count();
        $before = $sends();
        $this->actingAs($aziz)->postJson("/api/v1/users/{$me->id}/follow")->assertOk();
        $this->assertSame($before, $sends());
    }

    public function test_nothing_is_sent_without_firebase(): void
    {
        Http::fake();
        config(['fikrlash.push.credentials' => '']);
        [$me, $aziz] = User::factory()->count(2)->create();
        DeviceToken::query()->create(['user_id' => $me->id, 'token' => str_repeat('c', 40), 'platform' => 'android']);

        $this->actingAs($aziz)->postJson("/api/v1/users/{$me->id}/follow")->assertOk();
        Http::assertNothingSent();
    }

    public function test_app_links_files_for_android_and_ios(): void
    {
        $this->get('/.well-known/assetlinks.json')->assertOk()
            ->assertJsonPath('0.target.package_name', 'uz.fikrlash.app')
            ->assertJsonPath('0.relation.0', 'delegate_permission/common.handle_all_urls')
            ->assertJsonCount(2, '0.target.sha256_cert_fingerprints');

        $this->get('/.well-known/apple-app-site-association')->assertNotFound(); // Apple akkaunti hali yo‘q
        config(['fikrlash.mobile.ios_app_id' => 'ABCDE12345.uz.fikrlash.app']);
        $this->get('/.well-known/apple-app-site-association')->assertOk()->assertJsonPath('applinks.details.0.appIDs.0', 'ABCDE12345.uz.fikrlash.app');
    }

    public function test_site_knows_when_it_runs_inside_the_app(): void
    {
        $this->get('/login', ['User-Agent' => 'Mozilla/5.0 (Linux; Android 15) Chrome/140 Mobile FikrlashApp/1.0.0 (android; push)'])
            ->assertOk()->assertSee('class="in-app"', false);
        $this->get('/login')->assertOk()->assertDontSee('class="in-app"', false);
    }
}
