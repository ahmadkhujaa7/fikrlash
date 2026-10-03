<?php

namespace Database\Seeders;

use App\Enums\NotificationType;
use App\Enums\ReportReason;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Post;
use App\Models\PostAiAnalysis;
use App\Models\User;
use App\Services\Feed\ScoreCalculator;
use App\Services\Social\CommentService;
use App\Services\Social\FollowService;
use App\Services\Social\InteractionService;
use App\Services\Social\NotificationService;
use App\Services\Social\ReportService;
use App\Services\Social\TagService;
use Database\Factories\CommentFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Event;

/**
 * Development uchun demo ma'lumotlar: 40 foydalanuvchi, 150 post, izohlar, like, obunalar,
 * bildirishnomalar, reportlar va test AI tahlillari. Faqat local/testing muhitda ishlatiladi.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // Demo ma'lumot yaratishda AI job va queued listenerlar ishga tushmaydi.
        Event::fake();

        $categories = Category::all();
        $users = User::factory(40)->create();
        $admin = User::query()->where('role', 'admin')->first();
        $everyone = $users->when($admin, fn ($c) => $c->push($admin));

        $follows = app(FollowService::class);
        foreach ($users as $user) {
            $users->random(rand(3, 12))->each(fn (User $target) => $user->is($target) ?: $follows->follow($user, $target));
        }

        $tags = app(TagService::class);
        $posts = collect();
        for ($i = 0; $i < 150; $i++) {
            $post = Post::factory()->for($users->random())->create([
                'category_id' => $categories->random()->id,
                'visibility' => rand(1, 10) === 1 ? 'followers' : 'public',
            ]);
            $tags->syncForPost($post);
            $posts->push($post);
        }

        $interactions = app(InteractionService::class);
        $comments = app(CommentService::class);
        foreach ($posts as $post) {
            $everyone->random(rand(0, 15))->each(fn (User $u) => $interactions->likePost($u, $post));
            $everyone->random(rand(0, 4))->each(fn (User $u) => $interactions->savePost($u, $post));
            Post::query()->whereKey($post->id)->update(['views_count' => $post->likes_count * rand(3, 12) + rand(0, 40)]);

            for ($c = 0; $c < rand(0, 5); $c++) {
                $comment = $comments->create($everyone->random(), $post, fake()->randomElement(CommentFactory::SAMPLES));
                if (rand(0, 2) === 0) {
                    $comments->create($everyone->random(), $post, 'Javob: '.fake()->randomElement(CommentFactory::SAMPLES), $comment->id);
                }
            }

            $this->fakeAnalysis($post->fresh());
        }

        $scores = app(ScoreCalculator::class);
        Post::query()->get()->each(fn (Post $p) => $p->forceFill(['score' => $scores->hot($p)])->saveQuietly());

        $notifications = app(NotificationService::class);
        foreach ($everyone->take(10) as $user) {
            $notifications->notify($user, NotificationType::System, null, null, ['message' => 'Fikrlash.uz ga xush kelibsiz!']);
            $notifications->notify($user, NotificationType::Followed, $users->random(), $users->random());
        }

        $reports = app(ReportService::class);
        $posts->random(6)->each(function (Post $post) use ($users, $reports) {
            $reporter = $users->firstWhere(fn (User $u) => $u->id !== $post->user_id);
            rescue(fn () => $reports->create($reporter, $post, ReportReason::Spam, 'Demo report'), report: false);
        });

        $this->command?->info('Demo: '.User::count().' foydalanuvchi, '.Post::count().' post, '.Comment::count().' izoh.');
    }

    private function fakeAnalysis(Post $post): void
    {
        PostAiAnalysis::query()->create([
            'post_id' => $post->id,
            'provider' => 'fake',
            'model' => 'demo-seed',
            'content_hash' => $post->content_hash,
            'topic' => $post->category?->name,
            'category' => $post->category?->slug,
            'sentiment' => fake()->randomElement(['positive', 'neutral', 'mixed']),
            'quality_score' => $q = rand(35, 95),
            'toxicity_score' => rand(0, 10),
            'spam_score' => rand(0, 15),
            'educational_score' => rand(10, 90),
            'engagement_score' => rand(30, 90),
            'summary' => $post->excerpt(140),
            'keywords' => ['demo'],
            'input_tokens' => rand(80, 300),
            'output_tokens' => rand(60, 120),
            'latency_ms' => rand(300, 1500),
        ]);

        $post->forceFill(['ai_score' => $q, 'ai_topic' => $post->category?->name, 'ai_summary' => $post->excerpt(140), 'ai_analyzed_at' => now()])->saveQuietly();
    }
}
