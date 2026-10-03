<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use App\Services\Feed\RecommendationService;
use App\Services\Social\InterestService;
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

        app(InterestService::class)->bump($me->id, $tech->id, 50);
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
}
