<?php

namespace Tests\Feature;

use App\Enums\MessagePrivacy;
use App\Enums\PostVisibility;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChatMediaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function open(User $me, User $other): Conversation
    {
        $this->actingAs($me)->get("/messages/with/{$other->username}");

        return Conversation::query()->where('pair_key', Conversation::pairKey($me->id, $other->id))->firstOrFail();
    }

    private function video(): UploadedFile
    {
        // MP4 imzosi: 4 bayt o‘lcham + "ftyp" + brend.
        return UploadedFile::fake()->createWithContent('klip.mp4', "\x00\x00\x00\x18ftypisom".str_repeat("\0", 2000));
    }

    public function test_album_with_images_and_video_and_caption(): void
    {
        [$aziz, $laylo, $begona] = User::factory()->count(3)->create();
        $conversation = $this->open($aziz, $laylo);

        $message = $this->actingAs($aziz)->post("/messages/{$conversation->id}", [
            'body' => 'Sayohatdan rasmlar fikrlash.uz',
            'files' => [UploadedFile::fake()->image('a.jpg', 3000, 2000), UploadedFile::fake()->image('b.png', 800, 600), $this->video()],
            'posters' => [2 => UploadedFile::fake()->image('poster.jpg', 640, 360)],
            'durations' => [2 => 12],
            'dims' => [2 => '1280x720'],
        ], ['Accept' => 'application/json'])->assertCreated()->json('message');

        $this->assertSame('media', $message['type']);
        $this->assertCount(3, $message['media']);
        $this->assertSame(['image', 'image', 'video'], array_column($message['media'], 'kind'));
        $this->assertSame(2048, $message['media'][0]['w']); // katta rasm kichraytiriladi
        $this->assertSame(12, $message['media'][2]['duration']);
        $this->assertNotNull($message['media'][2]['poster']);
        $this->assertStringContainsString('href="https://fikrlash.uz"', $message['html']);

        // Faqat ishtirokchilar ko‘radi; rasm WebP'ga aylantirilgan, video o‘zgarmagan.
        $this->actingAs($laylo)->get($message['media'][0]['url'])->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->actingAs($laylo)->get($message['media'][2]['url'])->assertOk()->assertHeader('Content-Type', 'video/mp4');
        $this->actingAs($laylo)->get($message['media'][2]['poster'])->assertOk();
        $this->actingAs($begona)->get($message['media'][0]['url'])->assertForbidden();

        // Ro‘yxatdagi qisqa ko‘rinish.
        $this->actingAs($laylo)->get('/messages')->assertSee('2 ta rasm, video');

        // Izohni tahrirlash mumkin; o‘chirilganda fayllar ham yo‘qoladi.
        $this->actingAs($aziz)->patchJson("/messages/{$conversation->id}/{$message['id']}", ['body' => ''])->assertOk()->assertJsonPath('message.html', null);
        $paths = MessageAttachment::query()->pluck('path')->all();
        $this->actingAs($aziz)->deleteJson("/messages/{$conversation->id}/{$message['id']}")->assertOk();
        foreach ($paths as $path) {
            Storage::disk('local')->assertMissing($path);
        }
        $this->assertDatabaseCount('message_attachments', 0);
    }

    public function test_bad_files_are_rejected(): void
    {
        [$aziz, $laylo] = User::factory()->count(2)->create();
        $conversation = $this->open($aziz, $laylo);

        $this->actingAs($aziz)->post("/messages/{$conversation->id}", [
            'files' => [UploadedFile::fake()->createWithContent('virus.mp4', 'MZ'.str_repeat('x', 500))],
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $this->actingAs($aziz)->post("/messages/{$conversation->id}", [
            'files' => array_fill(0, 11, UploadedFile::fake()->image('x.jpg')),
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $this->assertDatabaseCount('messages', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_location_message(): void
    {
        [$aziz, $laylo] = User::factory()->count(2)->create();
        $conversation = $this->open($aziz, $laylo);

        $this->actingAs($aziz)->postJson("/messages/{$conversation->id}", ['lat' => 41.311081, 'lng' => 69.240562, 'acc' => 18.4])
            ->assertCreated()
            ->assertJsonPath('message.type', 'location')
            ->assertJsonPath('message.location', ['lat' => 41.311081, 'lng' => 69.240562, 'acc' => 18]);

        $this->actingAs($aziz)->postJson("/messages/{$conversation->id}", ['lat' => 120, 'lng' => 69])->assertStatus(422);
        $this->actingAs($laylo)->get('/messages')->assertSee('Joylashuv');
    }

    public function test_post_is_shared_to_several_people(): void
    {
        [$aziz, $laylo, $sardor, $yopiq] = User::factory()->count(4)->create();
        $yopiq->forceFill(['messages_from' => MessagePrivacy::Nobody])->save();
        $post = Post::factory()->for($sardor)->create(['content' => 'Ulashishga arziydigan fikr']);

        $this->actingAs($aziz)->postJson('/messages/share', [
            'post_id' => $post->id, 'user_ids' => [$laylo->id, $sardor->id, $yopiq->id], 'body' => 'Buni o‘qing!',
        ])->assertOk()->assertJsonPath('sent', 2)->assertJsonCount(1, 'failed');

        $message = Message::query()->where('type', 'post')->firstOrFail();
        $conversation = $message->conversation;
        $data = $this->actingAs($laylo)->getJson("/messages/{$conversation->id}/poll?after=0")->json('messages.0');
        $this->assertSame('post', $data['type']);
        $this->assertTrue($data['post']['available']);
        $this->assertSame('Ulashishga arziydigan fikr', $data['post']['text']);
        $this->assertSame($sardor->username, $data['post']['author']['username']);
        $this->assertStringContainsString('Buni o‘qing!', $data['html']);

        // Faqat obunachilar uchun bo‘lgan post: obunachi bo‘lmagan odam ko‘rmaydi.
        $private = Post::factory()->for($sardor)->create(['visibility' => PostVisibility::Followers]);
        $this->actingAs($sardor)->postJson('/messages/share', ['post_id' => $private->id, 'user_ids' => [$laylo->id]])->assertOk();
        $last = $this->actingAs($laylo)->getJson('/messages/'.Conversation::query()->where('pair_key', Conversation::pairKey($sardor->id, $laylo->id))->value('id').'/poll?after=0')->json('messages');
        $this->assertFalse(end($last)['post']['available']);

        // Ko‘ra olmaydigan postni ulashib bo‘lmaydi.
        $this->actingAs($aziz)->postJson('/messages/share', ['post_id' => $private->id, 'user_ids' => [$laylo->id]])->assertStatus(422);
    }

    public function test_recipients_list_shows_recent_people_and_search(): void
    {
        [$aziz, $laylo] = User::factory()->count(2)->create();
        $sardor = User::factory()->create(['name' => 'Sardor Toshmatov', 'username' => 'sardor_t']);
        $conversation = $this->open($aziz, $laylo);
        $this->actingAs($aziz)->postJson("/messages/{$conversation->id}", ['body' => 'Salom']);

        $this->actingAs($aziz)->getJson('/messages/recipients')->assertOk()
            ->assertJsonPath('users.0.username', $laylo->username)->assertJsonPath('users.0.can', true);
        $this->actingAs($aziz)->getJson('/messages/recipients?q=sardor')->assertJsonPath('users.0.username', 'sardor_t');
    }
}
