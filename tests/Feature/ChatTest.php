<?php

namespace Tests\Feature;

use App\Enums\MessagePrivacy;
use App\Enums\UserStatus;
use App\Models\Conversation;
use App\Models\Follow;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChatTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function open(User $me, User $other): Conversation
    {
        $this->actingAs($me)->get("/messages/with/{$other->username}")->assertRedirect();

        return Conversation::query()->where('pair_key', Conversation::pairKey($me->id, $other->id))->firstOrFail();
    }

    private function voiceFile(): UploadedFile
    {
        // Haqiqiy WebM imzosi (EBML) bilan boshlanadigan kichik fayl.
        return UploadedFile::fake()->createWithContent('voice.webm', "\x1A\x45\xDF\xA3".str_repeat("\0", 400));
    }

    public function test_two_users_exchange_text_messages(): void
    {
        [$aziz, $laylo] = User::factory()->count(2)->create();
        $conversation = $this->open($aziz, $laylo);

        $sent = $this->actingAs($aziz)->postJson("/messages/{$conversation->id}", ['body' => "Salom! <b>qalaysan</b>\nhttps://fikrlash.uz"])
            ->assertCreated()
            ->assertJsonPath('message.mine', true)
            ->assertJsonPath('message.type', 'text')
            ->json('message');
        $this->assertStringContainsString('&lt;b&gt;qalaysan&lt;/b&gt;<br>', $sent['html']); // HTML escape qilinadi
        $this->assertStringContainsString('class="link"', $sent['html']);

        // Laylo: o‘qilmagan belgi, ro‘yxat va suhbat.
        $this->actingAs($laylo)->getJson('/badges')->assertJson(['messages' => 1]);
        $this->actingAs($laylo)->get('/messages')->assertOk()->assertSee($aziz->name)->assertSee('Salom!');
        $this->actingAs($laylo)->get("/messages/{$conversation->id}")->assertOk()->assertSee('messageThread(', false);
        $this->actingAs($laylo)->getJson('/badges')->assertJson(['messages' => 0]); // ochilganda o‘qilgan bo‘ldi

        // Javob (reply) va poll: Aziz yangi xabarni va o‘qilganini ko‘radi.
        $reply = $this->actingAs($laylo)->postJson("/messages/{$conversation->id}", ['body' => 'Yaxshi, rahmat', 'reply_to_id' => $sent['id']])
            ->assertCreated()->json('message');

        $poll = $this->actingAs($aziz)->getJson("/messages/{$conversation->id}/poll?after={$sent['id']}")->assertOk()->json();
        $this->assertCount(1, $poll['messages']);
        $this->assertSame($reply['id'], $poll['messages'][0]['id']);
        $this->assertFalse($poll['messages'][0]['mine']);
        $this->assertSame($sent['id'], $poll['messages'][0]['reply']['id']);
        $this->assertSame('Siz', $poll['messages'][0]['reply']['name']);
        $this->assertNull($poll['messages'][0]['body']); // birovning xom matni berilmaydi
        $this->assertGreaterThanOrEqual($sent['id'], $poll['peerRead']);
    }

    public function test_only_author_can_edit_and_delete_and_changes_reach_the_other_side(): void
    {
        [$aziz, $laylo] = User::factory()->count(2)->create();
        $conversation = $this->open($aziz, $laylo);
        $id = $this->actingAs($aziz)->postJson("/messages/{$conversation->id}", ['body' => 'Ertaga uchrashamiz'])->json('message.id');
        $since = now()->subSeconds(5)->toIso8601String();

        $this->actingAs($laylo)->patchJson("/messages/{$conversation->id}/{$id}", ['body' => 'buzish'])->assertStatus(422);
        $this->actingAs($aziz)->patchJson("/messages/{$conversation->id}/{$id}", ['body' => 'Indinga uchrashamiz'])
            ->assertOk()->assertJsonPath('message.edited', true)->assertJsonPath('message.body', 'Indinga uchrashamiz');

        $updated = $this->actingAs($laylo)->getJson("/messages/{$conversation->id}/poll?after={$id}&since=".urlencode($since))->json('updated');
        $this->assertSame('Indinga uchrashamiz', strip_tags($updated[0]['html']));
        $this->assertTrue($updated[0]['edited']);

        $this->actingAs($laylo)->deleteJson("/messages/{$conversation->id}/{$id}")->assertStatus(422);
        $this->actingAs($aziz)->deleteJson("/messages/{$conversation->id}/{$id}")->assertOk()->assertJsonPath('message.removed', true);
        $this->assertNull(Message::query()->find($id)->body);
    }

    public function test_reactions_toggle_and_replace(): void
    {
        [$aziz, $laylo] = User::factory()->count(2)->create();
        $conversation = $this->open($aziz, $laylo);
        $id = $this->actingAs($aziz)->postJson("/messages/{$conversation->id}", ['body' => 'Yangi maqolam chiqdi'])->json('message.id');
        $url = "/messages/{$conversation->id}/{$id}/react";

        $this->actingAs($laylo)->postJson($url, ['emoji' => '❤️'])->assertOk()
            ->assertJsonPath('message.reactions.0', ['emoji' => '❤️', 'count' => 1, 'mine' => true]);
        $this->actingAs($aziz)->postJson($url, ['emoji' => '❤️'])->assertJsonPath('message.reactions.0.count', 2);
        // Almashtirish: Laylo 🔥 ga o‘zgartirdi.
        $this->actingAs($laylo)->postJson($url, ['emoji' => '🔥'])->assertJsonCount(2, 'message.reactions');
        // Qayta bosish — olib tashlash.
        $this->actingAs($laylo)->postJson($url, ['emoji' => '🔥'])
            ->assertJsonCount(1, 'message.reactions')->assertJsonPath('message.reactions.0.mine', false);
        // Ro‘yxatda yo‘q emoji — rad etiladi.
        $this->actingAs($laylo)->postJson($url, ['emoji' => '💩'])->assertStatus(422);
    }

    public function test_voice_message_is_private_to_participants(): void
    {
        [$aziz, $laylo, $begona] = User::factory()->count(3)->create();
        $conversation = $this->open($aziz, $laylo);

        $message = $this->actingAs($aziz)->post("/messages/{$conversation->id}", [
            'voice' => $this->voiceFile(), 'duration' => 7, 'waveform' => json_encode([10, 250, -5, 60]),
        ], ['Accept' => 'application/json'])->assertCreated()->json('message');

        $this->assertSame('voice', $message['type']);
        $this->assertSame(7, $message['voice']['duration']);
        $this->assertSame([10, 100, 0, 60], $message['voice']['waveform']);
        $stored = Message::query()->findOrFail($message['id']);
        $this->assertStringEndsWith('.webm', $stored->voice_path);
        Storage::disk('local')->assertExists($stored->voice_path);

        $this->actingAs($laylo)->get($message['voice']['url'])->assertOk()->assertHeader('Content-Type', 'audio/webm');
        $this->actingAs($begona)->get($message['voice']['url'])->assertForbidden();
        $this->actingAs($begona)->get("/messages/{$conversation->id}")->assertForbidden();

        // Noma'lum format rad etiladi; o‘chirilganda fayl ham yo‘qoladi.
        $this->actingAs($aziz)->post("/messages/{$conversation->id}", ['voice' => UploadedFile::fake()->createWithContent('x.webm', str_repeat('A', 100)), 'duration' => 3], ['Accept' => 'application/json'])
            ->assertStatus(422);
        $this->actingAs($aziz)->deleteJson("/messages/{$conversation->id}/{$message['id']}")->assertOk();
        Storage::disk('local')->assertMissing($stored->voice_path);
    }

    public function test_privacy_settings_and_blocking(): void
    {
        [$aziz, $laylo, $sardor] = User::factory()->count(3)->create();

        // "Hech kim" — yangi suhbat ochib bo‘lmaydi.
        $laylo->forceFill(['messages_from' => MessagePrivacy::Nobody])->save();
        $this->actingAs($aziz)->from("/@{$laylo->username}")->get("/messages/with/{$laylo->username}")
            ->assertRedirect("/@{$laylo->username}")->assertSessionHas('toast');
        $this->assertDatabaseCount('conversations', 0);

        // "Faqat men obuna bo‘lganlar" — Laylo Azizga obuna bo‘lsa, Aziz yoza oladi.
        $laylo->forceFill(['messages_from' => MessagePrivacy::Following])->save();
        $this->actingAs($sardor)->get("/messages/with/{$laylo->username}")->assertRedirect();
        $this->assertDatabaseCount('conversations', 0);
        Follow::query()->create(['follower_id' => $laylo->id, 'following_id' => $aziz->id]);
        $conversation = $this->open($aziz, $laylo);
        $this->actingAs($aziz)->postJson("/messages/{$conversation->id}", ['body' => 'Salom'])->assertCreated();

        // Laylo bloklaydi — Aziz yoza olmaydi; blokdan chiqarsa — yana yoza oladi.
        $this->actingAs($laylo)->post("/messages/{$conversation->id}/block")->assertRedirect();
        $this->actingAs($aziz)->postJson("/messages/{$conversation->id}", ['body' => 'Javob ber'])->assertStatus(422)
            ->assertJsonPath('message', 'Bu foydalanuvchi sizdan xabar qabul qilmaydi.');
        $this->actingAs($laylo)->delete("/messages/{$conversation->id}/block");
        $this->actingAs($aziz)->postJson("/messages/{$conversation->id}", ['body' => 'Rahmat'])->assertCreated();

        // Bloklangan akkauntga yozib bo‘lmaydi; o‘ziga ham.
        $laylo->forceFill(['status' => UserStatus::Blocked])->save();
        $this->actingAs($aziz)->postJson("/messages/{$conversation->id}", ['body' => 'Hey'])->assertStatus(422);
        $this->actingAs($aziz)->get("/messages/with/{$aziz->username}")->assertRedirect();
        $this->assertDatabaseMissing('conversations', ['pair_key' => Conversation::pairKey($aziz->id, $aziz->id)]);

        // Sozlamalar sahifasidan o‘zgartirish.
        $this->actingAs($sardor)->put('/settings/messaging', ['messages_from' => 'following'])->assertRedirect();
        $this->assertSame(MessagePrivacy::Following, $sardor->fresh()->messages_from);
    }

    public function test_clear_hides_old_messages_and_history_pages(): void
    {
        config(['fikrlash.chat.page_size' => 3]);
        [$aziz, $laylo] = User::factory()->count(2)->create();
        $conversation = $this->open($aziz, $laylo);
        $ids = [];
        foreach (range(1, 5) as $i) {
            $ids[] = $this->actingAs($i % 2 ? $aziz : $laylo)->postJson("/messages/{$conversation->id}", ['body' => "Xabar {$i}"])->json('message.id');
        }

        $history = $this->actingAs($aziz)->getJson("/messages/{$conversation->id}/history?before={$ids[3]}")->assertOk()->json();
        $this->assertSame([$ids[0], $ids[1], $ids[2]], array_column($history['messages'], 'id'));
        $this->assertFalse($history['hasMore']);

        $this->actingAs($aziz)->post("/messages/{$conversation->id}/clear")->assertRedirect('/messages');
        $this->actingAs($aziz)->get('/messages')->assertDontSee('Xabar 5');
        $this->assertSame([], $this->actingAs($aziz)->getJson("/messages/{$conversation->id}/poll?after=0")->json('messages'));
        // Suhbatdoshda hammasi joyida.
        $this->assertCount(5, $this->actingAs($laylo)->getJson("/messages/{$conversation->id}/poll?after=0")->json('messages'));

        // Yangi xabar kelsa — suhbat yana ro‘yxatda.
        $this->actingAs($laylo)->postJson("/messages/{$conversation->id}", ['body' => 'Yangi gap']);
        $this->actingAs($aziz)->get('/messages')->assertSee('Yangi gap');
    }

    public function test_typing_indicator_and_profile_button(): void
    {
        [$aziz, $laylo] = User::factory()->count(2)->create();
        $conversation = $this->open($aziz, $laylo);

        $this->actingAs($laylo)->post("/messages/{$conversation->id}/typing")->assertNoContent();
        $this->actingAs($aziz)->getJson("/messages/{$conversation->id}/poll?after=0")->assertJsonPath('typing', true);

        $this->actingAs($aziz)->get("/@{$laylo->username}")->assertSee("/messages/with/{$laylo->username}", false);
        $this->actingAs($aziz)->get("/@{$aziz->username}")->assertDontSee('/messages/with/', false);
    }
}
