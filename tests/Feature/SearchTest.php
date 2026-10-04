<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use App\Services\Social\TagService;
use Tests\TestCase;

class SearchTest extends TestCase
{
    public function test_finds_posts_regardless_of_apostrophe_variant(): void
    {
        Post::factory()->create(['content' => 'Kitob o‘qish odati haqida']);
        Post::factory()->create(['content' => 'Boshqa mavzu']);

        $this->get('/search?q='.urlencode("o'qish"))->assertOk()->assertSee('Kitob o‘qish odati haqida')->assertDontSee('Boshqa mavzu');
        $this->get('/search?q='.urlencode('oʻqish'))->assertSee('Kitob o‘qish odati haqida');
    }

    public function test_finds_users_and_hides_blocked(): void
    {
        User::factory()->create(['username' => 'dilnoza_k', 'name' => 'Dilnoza Karimova']);
        User::factory()->blocked()->create(['username' => 'dilnoza_blok']);

        $this->get('/search?q=dilnoza&type=users')->assertSee('@dilnoza_k')->assertDontSee('dilnoza_blok');
    }

    public function test_hashtag_query_redirects_to_tag_page(): void
    {
        $this->get('/search?q='.urlencode('#Biznes'))->assertRedirect(route('tags.show', 'biznes'));
    }

    public function test_like_wildcards_are_escaped(): void
    {
        Post::factory()->create(['content' => 'oddiy matn']);
        $this->get('/search?q='.urlencode('%_%'))->assertOk()->assertDontSee('oddiy matn');
    }

    public function test_api_search_format(): void
    {
        Post::factory()->create(['content' => 'Startap g‘oyasi']);
        $this->getJson('/api/v1/search?q=startap')->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.posts')
            ->assertJsonStructure(['data' => ['posts', 'users', 'tags']])
            ->assertJsonMissingPath('data.categories');
    }

    public function test_live_search_returns_results_fragment_while_typing(): void
    {
        Post::factory()->create(['content' => 'Kitob o‘qish odati haqida']);
        User::factory()->create(['username' => 'kitobxon', 'name' => 'Kitobxon Ali']);

        $html = $this->get('/search/live?q=kito', ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->getContent();
        $this->assertStringContainsString('Kitob o‘qish odati haqida', $html);
        $this->assertStringContainsString('@kitobxon', $html);
        $this->assertStringNotContainsString('<html', $html);

        // Bitta harf — hali qidirilmaydi; "#teg" jonli rejimda yo‘naltirilmaydi.
        $this->get('/search/live?q=k')->assertOk()->assertSee('Nimani qidiramiz?');
        $this->get('/search/live?q='.urlencode('#kitob'))->assertOk();
    }

    public function test_quick_suggestions_list_people_tags_and_posts(): void
    {
        $post = Post::factory()->create(['content' => 'Laravel bilan API yozish #laravel']);
        app(TagService::class)->syncForPost($post);
        User::factory()->create(['username' => 'laravelchi', 'name' => 'Laravel Usta']);

        $this->get('/search/suggest?q=larav')->assertOk()
            ->assertSee('@laravelchi')->assertSee('#laravel')->assertSee('Laravel bilan API yozish')
            ->assertSee('bo‘yicha barcha natijalar', false);

        $this->assertSame('', trim($this->get('/search/suggest?q=l')->assertOk()->getContent()));
    }

    public function test_topics_are_internal_and_never_shown_to_users(): void
    {
        $this->seedCategories();
        $category = Category::query()->where('slug', 'texnologiya')->first();
        $post = Post::factory()->create(['category_id' => $category->id, 'content' => 'Telefonlar haqida fikr']);

        $this->get('/categories')->assertNotFound();
        $this->get('/c/texnologiya')->assertNotFound();
        $this->get("/posts/{$post->id}")->assertOk()->assertDontSee($category->name);
        $this->get('/search?q=texnologiya')->assertOk()->assertDontSee('/c/texnologiya');
        $this->getJson("/api/v1/posts/{$post->id}")->assertOk()->assertJsonMissingPath('data.category');
        $this->getJson('/api/v1/categories')->assertNotFound();
        $this->get('/sitemap.xml')->assertOk()->assertDontSee('/c/');
    }
}
