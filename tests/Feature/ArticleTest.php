<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Jobs\AnalyzePostJob;
use App\Models\MediaUpload;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArticleTest extends TestCase
{
    private function upload(User $user, int $w = 1600, int $h = 900): array
    {
        return $this->actingAs($user)
            ->postJson('/compose/images', ['image' => UploadedFile::fake()->image('rasm.jpg', $w, $h)])
            ->assertCreated()
            ->json();
    }

    private function article(array $blocks, string $title = 'Ta’limda yangi yondashuv', array $extra = []): array
    {
        return ['type' => 'article', 'title' => $title, 'blocks' => json_encode($blocks)] + $extra;
    }

    public function test_image_upload_is_reencoded_and_owned_by_author(): void
    {
        $user = User::factory()->create();
        $data = $this->upload($user, 2400, 1200);

        $this->assertStringEndsWith('.webp', $data['path']);
        Storage::disk('public')->assertExists($data['path']);
        $this->assertSame(1600, $data['width']); // post_max_width gacha kichraytiriladi
        $this->assertSame(800, $data['height']);
        $this->assertDatabaseHas('media_uploads', ['user_id' => $user->id, 'path' => $data['path'], 'post_id' => null]);

        $this->actingAs($user)->postJson('/compose/images', ['image' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])->assertUnprocessable();
        auth()->logout();
        $this->postJson('/compose/images', ['image' => UploadedFile::fake()->image('a.jpg')])->assertUnauthorized();
    }

    public function test_user_publishes_article_with_headings_images_and_lists(): void
    {
        Bus::fake([AnalyzePostJob::class]);
        $user = User::factory()->create();
        $img = $this->upload($user);

        $this->actingAs($user)->post('/posts', $this->article([
            ['type' => 'p', 'text' => 'Kirish: **muhim** fikr va *kursiv* so‘z #talim'],
            ['type' => 'h', 'text' => 'Birinchi bo‘lim'],
            ['type' => 'image', 'path' => $img['path'], 'caption' => 'Sinfxona', 'w' => 9999],
            ['type' => 'p', 'text' => '   '], // bo‘sh — tashlanadi
            ['type' => 'quote', 'text' => 'Savol bergan — bilimga yetadi.'],
            ['type' => 'list', 'ordered' => true, 'items' => ['Birinchi', '', 'Ikkinchi']],
            ['type' => 'hr'],
            ['type' => 'hr'], // ketma-ket — bittasi qoladi
            ['type' => 'p', 'text' => 'Xulosa @nobody'],
            ['type' => 'hr'], // oxirida — tashlanadi
        ], extra: ['tags' => 'maktab']))->assertRedirect();

        $post = Post::query()->with('tags')->firstOrFail();
        $this->assertTrue($post->isArticle());
        $this->assertSame(PostStatus::Published, $post->status);
        $this->assertSame('Ta’limda yangi yondashuv', $post->title);
        $this->assertSame(['p', 'h', 'image', 'quote', 'list', 'hr', 'p'], array_column($post->blocks, 'type'));
        $this->assertSame(['Birinchi', 'Ikkinchi'], $post->blocks[4]['items']);
        $this->assertSame(1600, $post->blocks[2]['w']); // o‘lcham DB'dan, mijozdan emas
        $this->assertSame($img['path'], $post->image_path); // birinchi rasm — muqova
        $this->assertStringStartsWith('Ta’limda yangi yondashuv', $post->content);
        $this->assertStringContainsString('Kirish: muhim fikr va kursiv so‘z', $post->content); // belgilar olib tashlangan
        $this->assertEqualsCanonicalizing(['maktab', 'talim'], $post->tags->pluck('slug')->all());
        $this->assertDatabaseHas('media_uploads', ['path' => $img['path'], 'post_id' => $post->id]);

        // Sahifa: sarlavha, bo‘lim, rasm (o‘lchami bilan), iqtibos, raqamli ro‘yxat; formatlash xavfsiz.
        $html = $this->get("/posts/{$post->id}")->assertOk()->getContent();
        $this->assertStringContainsString('<h1 class="article-title mt-3">Ta’limda yangi yondashuv</h1>', $html);
        $this->assertStringContainsString('<h2 id="bolim-1">Birinchi bo‘lim</h2>', $html);
        $this->assertStringContainsString('<strong>muhim</strong>', $html);
        $this->assertStringContainsString('<em>kursiv</em>', $html);
        $this->assertStringContainsString('width="1600" height="900"', $html);
        $this->assertStringContainsString('<figcaption>Sinfxona</figcaption>', $html);
        $this->assertStringContainsString('<blockquote>', $html);
        $this->assertStringContainsString('<ol>', $html);
        $this->assertStringContainsString('"@type":"Article"', $html);

        // Lentada: sarlavha va o‘qish vaqti bilan kartochka.
        $this->actingAs(User::factory()->create())->get('/')->assertSee('Ta’limda yangi yondashuv')->assertSee('daqiqalik o‘qish');

        // API: bloklar rasm URL'i bilan.
        $this->getJson("/api/v1/posts/{$post->id}")->assertOk()
            ->assertJsonPath('data.type', 'article')
            ->assertJsonPath('data.title', 'Ta’limda yangi yondashuv')
            ->assertJsonPath('data.blocks.2.type', 'image')
            ->assertJsonPath('data.blocks.2.width', 1600);
    }

    public function test_html_in_article_is_escaped(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/posts', $this->article([
            ['type' => 'p', 'text' => '<script>alert(1)</script> **<b>qalin</b>**'],
            ['type' => 'h', 'text' => '<img src=x onerror=alert(1)>'],
        ], '<i>Sarlavha</i>'))->assertRedirect();

        $html = $this->get('/posts/'.Post::query()->value('id'))->getContent();
        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringNotContainsString('<i>Sarlavha</i>', $html);
        $this->assertStringContainsString('<strong>&lt;b&gt;qalin&lt;/b&gt;</strong>', $html);
    }

    public function test_article_validation(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $user = User::factory()->create();
        $other = User::factory()->create();
        $foreign = $this->upload($other);

        $this->actingAs($user)->post('/posts', $this->article([['type' => 'p', 'text' => 'Matn']], 'ab'))->assertSessionHasErrors('title');
        $this->actingAs($user)->post('/posts', $this->article([['type' => 'p', 'text' => '  ']]))->assertSessionHasErrors('blocks');
        $this->actingAs($user)->post('/posts', $this->article([['type' => 'video', 'src' => 'x']]))->assertSessionHasErrors('blocks');
        $this->actingAs($user)->post('/posts', ['type' => 'article', 'title' => 'Sarlavha', 'blocks' => '{buzilgan'])->assertSessionHasErrors('blocks');
        // Boshqa odamning rasmi yoki mavjud bo‘lmagan fayl — mumkin emas.
        $this->actingAs($user)->post('/posts', $this->article([['type' => 'image', 'path' => $foreign['path']]]))->assertSessionHasErrors('blocks');
        $this->actingAs($user)->post('/posts', $this->article([['type' => 'image', 'path' => '../../.env']]))->assertSessionHasErrors('blocks');
        // Juda uzun paragraf.
        $this->actingAs($user)->post('/posts', $this->article([['type' => 'p', 'text' => str_repeat('a', 6001)]]))->assertSessionHasErrors('blocks');

        $this->assertDatabaseCount('posts', 0);
    }

    public function test_draft_then_edit_removes_dropped_images_and_publishes(): void
    {
        Bus::fake([AnalyzePostJob::class]);
        $user = User::factory()->create();
        $a = $this->upload($user);
        $b = $this->upload($user);

        $this->actingAs($user)->post('/posts', $this->article([
            ['type' => 'image', 'path' => $a['path']],
            ['type' => 'p', 'text' => 'Birinchi qoralama'],
            ['type' => 'image', 'path' => $b['path']],
        ], extra: ['draft' => 1]))->assertRedirect(route('posts.drafts'));

        $post = Post::query()->firstOrFail();
        $this->assertSame(PostStatus::Draft, $post->status);

        // Tahrir: birinchi rasm olib tashlandi, yangi rasm qo‘shildi — muqova o‘zgaradi, eski fayl o‘chadi.
        $c = $this->upload($user);
        $this->actingAs($user)->put("/posts/{$post->id}", $this->article([
            ['type' => 'p', 'text' => 'Yangilangan matn'],
            ['type' => 'image', 'path' => $b['path']],
            ['type' => 'image', 'path' => $c['path']],
        ], 'Yangi sarlavha', ['publish' => 1, 'type' => 'post']))->assertRedirect();

        $post->refresh();
        $this->assertTrue($post->isArticle()); // turi o‘zgarmaydi
        $this->assertSame(PostStatus::Published, $post->status);
        $this->assertSame('Yangi sarlavha', $post->title);
        $this->assertSame($b['path'], $post->image_path);
        Storage::disk('public')->assertMissing($a['path']);
        Storage::disk('public')->assertExists($b['path']);
        $this->assertDatabaseMissing('media_uploads', ['path' => $a['path']]);
        $this->assertDatabaseHas('media_uploads', ['path' => $c['path'], 'post_id' => $post->id]);

        // Boshqa postga biriktirilgan rasmni yangi maqolada ishlatib bo‘lmaydi.
        $this->actingAs($user)->post('/posts', $this->article([['type' => 'image', 'path' => $b['path']]]))->assertSessionHasErrors('blocks');
    }

    public function test_unattached_uploads_are_pruned(): void
    {
        $user = User::factory()->create();
        $old = $this->upload($user);
        $fresh = $this->upload($user);
        MediaUpload::query()->where('path', $old['path'])->update(['created_at' => now()->subDays(8)]);

        $this->artisan('fikrlash:prune')->assertSuccessful();

        Storage::disk('public')->assertMissing($old['path']);
        Storage::disk('public')->assertExists($fresh['path']);
        $this->assertDatabaseMissing('media_uploads', ['path' => $old['path']]);
    }

    public function test_compose_page_offers_article_editor(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/compose?type=article')->assertOk()
            ->assertSee('articleEditor(', false)
            ->assertSee('Maqola');
        $this->actingAs($user)->get('/compose')->assertOk()
            ->assertSee('composer(', false)
            ->assertSee('/compose?type=article', false);
    }
}
