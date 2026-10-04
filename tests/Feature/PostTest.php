<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Jobs\AnalyzePostJob;
use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use App\Services\Social\TagService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PostTest extends TestCase
{
    public function test_user_creates_post_with_tags_and_image(): void
    {
        Bus::fake([AnalyzePostJob::class]);
        $this->seedCategories();
        $user = User::factory()->create();
        $category = Category::query()->where('slug', 'dasturlash')->first();

        $this->actingAs($user)->post('/posts', [
            'content' => "Laravel haqida fikr #PHP\nikkinchi qator",
            'category_id' => $category->id,
            'tags' => 'laravel, #backend',
            'image' => UploadedFile::fake()->image('rasm.jpg', 1200, 800),
        ])->assertRedirect();

        $post = Post::query()->with('tags')->firstOrFail();
        $this->assertSame(PostStatus::Published, $post->status);
        $this->assertNotNull($post->published_at);
        $this->assertEqualsCanonicalizing(['laravel', 'backend', 'php'], $post->tags->pluck('slug')->all());
        $this->assertStringEndsWith('.webp', $post->image_path);
        Storage::disk('public')->assertExists($post->image_path);
        $this->assertSame('laravel haqida fikr #php ikkinchi qator', $post->search_text);
        Bus::assertDispatched(AnalyzePostJob::class);
    }

    public function test_post_validation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/posts', ['content' => '   '])->assertSessionHasErrors('content');
        $this->actingAs($user)->post('/posts', ['content' => str_repeat('a', 5001)])->assertSessionHasErrors('content');
        $this->actingAs($user)->post('/posts', ['content' => 'ok', 'image' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])->assertSessionHasErrors('image');
        $this->actingAs($user)->post('/posts', ['content' => 'ok', 'image' => UploadedFile::fake()->image('big.jpg')->size(6000)])->assertSessionHasErrors('image');
        $this->assertDatabaseCount('posts', 0);
    }

    public function test_exactly_5000_characters_is_allowed(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/posts', ['content' => str_repeat('o‘', 2500)])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('posts', 1);
    }

    public function test_guest_cannot_create_post(): void
    {
        $this->post('/posts', ['content' => 'salom'])->assertRedirect(route('login'));
    }

    public function test_suspended_user_cannot_post(): void
    {
        $user = User::factory()->suspended()->create();
        $this->actingAs($user)->post('/posts', ['content' => 'salom'])->assertForbidden();
    }

    public function test_only_owner_can_edit_and_delete(): void
    {
        $post = Post::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($other)->put("/posts/{$post->id}", ['content' => 'buzildi'])->assertForbidden();
        $this->actingAs($other)->delete("/posts/{$post->id}")->assertForbidden();

        $this->actingAs($post->user)->put("/posts/{$post->id}", ['content' => 'Yangilangan matn'])->assertRedirect();
        $this->assertSame('Yangilangan matn', $post->fresh()->content);
        $this->assertNotNull($post->fresh()->edited_at);

        $this->actingAs($post->user)->delete("/posts/{$post->id}")->assertRedirect();
        $this->assertSoftDeleted($post);
        $this->get("/posts/{$post->id}")->assertNotFound();
    }

    public function test_drafts_are_private_and_can_be_published(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/posts', ['content' => 'Qoralama fikr', 'draft' => '1'])->assertRedirect(route('posts.drafts'));
        $post = Post::query()->firstOrFail();
        $this->assertSame(PostStatus::Draft, $post->status);

        $this->get('/?tab=latest')->assertDontSee('Qoralama fikr');
        $this->actingAs(User::factory()->create())->get("/posts/{$post->id}")->assertForbidden();

        $this->actingAs($user)->put("/posts/{$post->id}", ['content' => 'Qoralama fikr', 'publish' => '1']);
        $this->assertSame(PostStatus::Published, $post->fresh()->status);
        $this->assertNotNull($post->fresh()->published_at);
    }

    public function test_followers_only_post_visibility(): void
    {
        $author = User::factory()->create();
        $follower = User::factory()->create();
        $stranger = User::factory()->create();
        $follower->following()->attach($author->id, ['created_at' => now()]);
        $post = Post::factory()->followersOnly()->for($author)->create(['content' => 'Faqat yaqinlarim uchun']);

        $this->get("/posts/{$post->id}")->assertForbidden();
        $this->actingAs($stranger)->get("/posts/{$post->id}")->assertForbidden();
        $this->actingAs($follower)->get("/posts/{$post->id}")->assertOk()->assertSee('Faqat yaqinlarim uchun');
        $this->actingAs($stranger)->get('/?tab=latest')->assertDontSee('Faqat yaqinlarim uchun');
        $this->actingAs($follower)->get('/?tab=latest')->assertSee('Faqat yaqinlarim uchun');
    }

    public function test_hidden_posts_and_blocked_authors_are_not_shown(): void
    {
        Post::factory()->hidden()->create(['content' => 'Yashirin post']);
        Post::factory()->for(User::factory()->blocked())->create(['content' => 'Bloklangan muallif']);
        Post::factory()->create(['content' => 'Oddiy post']);

        $this->get('/?tab=latest')->assertSee('Oddiy post')->assertDontSee('Yashirin post')->assertDontSee('Bloklangan muallif');
    }

    public function test_post_page_has_seo_meta_and_escapes_content(): void
    {
        $post = Post::factory()->create(['content' => '<script>alert("x")</script> Salom']);

        $this->get("/posts/{$post->id}")
            ->assertOk()
            ->assertSee('<meta property="og:type" content="article">', false)
            ->assertSee('application/ld+json', false)
            ->assertDontSee('<script>alert("x")</script>', false);
    }

    public function test_composer_is_text_and_image_only_and_edit_keeps_ai_topic(): void
    {
        $this->seedCategories();
        $user = User::factory()->create();

        $html = $this->actingAs($user)->get('/compose')->assertOk()->getContent();
        $this->assertStringContainsString('name="content"', $html);
        $this->assertStringContainsString('name="image"', $html);
        $this->assertStringNotContainsString('name="category_id"', $html);
        $this->assertStringNotContainsString('name="tags"', $html);
        $this->assertStringNotContainsString('name="visibility"', $html);

        // Tahrirlashda faqat matn yuboriladi — AI aniqlagan mavzu va teglar saqlanib qoladi.
        $category = Category::query()->where('slug', 'dasturlash')->first();
        $post = Post::factory()->for($user)->create(['category_id' => $category->id, 'content' => 'Eski matn #laravel']);
        app(TagService::class)->syncForPost($post, ['php']);

        $this->actingAs($user)->put("/posts/{$post->id}", ['content' => 'Yangi matn #laravel'])->assertRedirect();

        $post->refresh();
        $this->assertSame('Yangi matn #laravel', $post->content);
        $this->assertSame($category->id, $post->category_id);
        $slugs = $post->tags()->pluck('slug')->all();
        $this->assertContains('laravel', $slugs);
        $this->assertContains('php', $slugs);
        $this->assertLessThanOrEqual(4, count($slugs));
    }
}
