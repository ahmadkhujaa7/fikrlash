<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
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
            ->assertJsonStructure(['data' => ['posts', 'users', 'tags', 'categories']]);
    }
}
