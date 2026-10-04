<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use App\Services\Feed\RecommendationService;
use App\Services\Feed\TasteService;
use Tests\TestCase;

class FeedTest extends TestCase
{
    public function test_latest_feed_is_newest_first_and_paginates(): void
    {
        Post::factory()->create(['content' => 'Eski post', 'published_at' => now()->subDay()]);
        Post::factory()->create(['content' => 'Yangi post', 'published_at' => now()]);

        $this->get('/?tab=latest')->assertOk()->assertSeeInOrder(['Yangi post', 'Eski post']);

        Post::factory()->count(25)->create();
        $html = $this->get('/feed?tab=latest')->assertOk()->getContent();
        $this->assertStringContainsString('data-next="http', $html);
    }

    public function test_following_feed_contains_only_followed_and_own_posts(): void
    {
        $me = User::factory()->create();
        $friend = User::factory()->create();
        $me->following()->attach($friend->id, ['created_at' => now()]);
        Post::factory()->for($friend)->create(['content' => 'Do‘stim posti']);
        Post::factory()->for($me)->create(['content' => 'Mening postim']);
        Post::factory()->create(['content' => 'Begona post']);

        $this->actingAs($me)->get('/?tab=following')
            ->assertSee('Do‘stim posti')->assertSee('Mening postim')->assertDontSee('Begona post');
    }

    public function test_for_you_ranks_by_interest_and_excludes_own_posts(): void
    {
        $this->seedCategories();
        [$tech, $life] = [Category::query()->where('slug', 'texnologiya')->first(), Category::query()->where('slug', 'hayot')->first()];
        $me = User::factory()->create();

        $techPost = Post::factory()->create(['category_id' => $tech->id, 'published_at' => now()->subHour()]);
        $lifePost = Post::factory()->create(['category_id' => $life->id, 'published_at' => now()->subHour()]);
        $own = Post::factory()->for($me)->create();

        // Foydalanuvchi texnologiya postlarini saqlab, o‘qib yurgan — did shundan o‘rganiladi.
        $taste = app(TasteService::class);
        $liked = Post::factory()->create(['category_id' => $tech->id]);
        foreach (range(1, 3) as $_) {
            $taste->expose($me->id, $liked);
            $taste->engage($me->id, $liked, 'save');
        }
        $ids = app(RecommendationService::class)->rank($me);

        $this->assertNotContains($own->id, $ids);
        $this->assertLessThan(array_search($lifePost->id, $ids), array_search($techPost->id, $ids));

        $this->actingAs($me)->get('/')->assertOk();
    }

    public function test_guest_for_you_shows_trending(): void
    {
        Post::factory()->create(['content' => 'Mashhur post', 'score' => 10]);
        $this->get('/')->assertOk()->assertSee('Mashhur post');
    }

    public function test_feed_has_no_n_plus_one_queries(): void
    {
        Post::factory()->count(20)->create();
        $viewer = User::factory()->create();

        \DB::enableQueryLog();
        $this->actingAs($viewer)->get('/?tab=latest')->assertOk();
        $count = count(\DB::getQueryLog());

        $this->assertLessThan(30, $count, "Lenta {$count} ta so‘rov bajardi");
    }

    public function test_ignored_topics_sink_and_not_interested_hides_post(): void
    {
        $this->seedCategories();
        [$tech, $life] = [Category::query()->where('slug', 'texnologiya')->first(), Category::query()->where('slug', 'hayot')->first()];
        $me = User::factory()->create();
        $taste = app(TasteService::class);

        // Hayot postlari ko‘p ko‘rsatildi, lekin foydalanuvchi ularga hech javob bermadi.
        $shown = Post::factory()->create(['category_id' => $life->id]);
        foreach (range(1, 12) as $_) {
            $taste->expose($me->id, $shown);
        }
        $this->assertLessThan(1.0, $taste->profile($me->id)['category'][$life->id]);

        $lifePost = Post::factory()->create(['category_id' => $life->id, 'published_at' => now()->subHour()]);
        $techPost = Post::factory()->create(['category_id' => $tech->id, 'published_at' => now()->subHour()]);
        $ids = app(RecommendationService::class)->rank($me);
        $this->assertLessThan(array_search($lifePost->id, $ids), array_search($techPost->id, $ids));

        // "Qiziq emas" — post umuman chiqmaydi.
        $this->actingAs($me)->postJson("/api/v1/posts/{$techPost->id}/not-interested")->assertOk()->assertJsonPath('data.dismissed', true);
        $this->assertNotContains($techPost->id, app(RecommendationService::class)->rank($me));
        $this->assertLessThan(1.0, $taste->profile($me->id)['category'][$tech->id]);
    }

    public function test_feed_dwell_and_opening_a_post_teach_the_profile(): void
    {
        $this->seedCategories();
        $tech = Category::query()->where('slug', 'texnologiya')->first();
        $me = User::factory()->create();
        $post = Post::factory()->create(['category_id' => $tech->id]);

        $this->actingAs($me)->postJson('/api/v1/views', ['post_ids' => [$post->id], 'dwell' => [$post->id => 15]])->assertOk();
        $this->assertDatabaseHas('user_affinities', ['user_id' => $me->id, 'kind' => 'category', 'target_id' => $tech->id]);
        $afterDwell = app(TasteService::class)->profile($me->id)['category'][$tech->id];
        $this->assertGreaterThan(1.0, $afterDwell);

        $this->actingAs($me)->get("/posts/{$post->id}")->assertOk();
        $this->assertGreaterThan($afterDwell, app(TasteService::class)->profile($me->id)['category'][$tech->id]);

        // O‘z postini o‘qish profilga ta'sir qilmaydi.
        $own = Post::factory()->for($me)->create(['category_id' => $tech->id]);
        $before = \DB::table('user_affinities')->where('user_id', $me->id)->sum('score');
        $this->actingAs($me)->get("/posts/{$own->id}")->assertOk();
        $this->assertEquals($before, \DB::table('user_affinities')->where('user_id', $me->id)->sum('score'));
    }

    public function test_following_an_author_boosts_their_posts(): void
    {
        $me = User::factory()->create();
        $author = User::factory()->create();
        $this->actingAs($me)->postJson("/api/v1/users/{$author->id}/follow")->assertOk();

        $this->assertGreaterThan(1.0, app(TasteService::class)->profile($me->id)['author'][$author->id]);
    }
}
