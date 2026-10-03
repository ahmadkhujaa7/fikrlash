<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Denormalizatsiya qilingan hisoblagichlarni manba jadvallardan qayta hisoblaydi.
 * Hisoblagichlar atomik yangilanadi, lekin akkaunt o‘chirilishi kabi holatlardan keyin
 * eventual consistency'ni kafolatlash uchun har kecha ishga tushadi.
 */
class ReconcileCounters extends Command
{
    protected $signature = 'fikrlash:reconcile-counters';

    protected $description = 'Like, comment, save, follow hisoblagichlarini qayta hisoblaydi';

    public function handle(): int
    {
        DB::update(<<<'SQL'
            UPDATE posts SET
                likes_count = (SELECT COUNT(*) FROM post_likes pl JOIN users u ON u.id = pl.user_id WHERE pl.post_id = posts.id AND u.deleted_at IS NULL),
                saves_count = (SELECT COUNT(*) FROM saved_posts sp WHERE sp.post_id = posts.id),
                comments_count = (SELECT COUNT(*) FROM comments c WHERE c.post_id = posts.id AND c.deleted_at IS NULL AND c.status = 'published')
        SQL);

        if (DB::getDriverName() === 'mysql') {
            // MySQL o‘zi yangilanayotgan jadvalga subquery'da murojaat qilishga ruxsat bermaydi (1093) —
            // shuning uchun guruhlangan derived table bilan JOIN.
            DB::update(<<<'SQL'
                UPDATE comments c
                LEFT JOIN (SELECT comment_id, COUNT(*) AS n FROM comment_likes GROUP BY comment_id) l ON l.comment_id = c.id
                LEFT JOIN (SELECT parent_id, COUNT(*) AS n FROM comments WHERE parent_id IS NOT NULL AND deleted_at IS NULL AND status = 'published' GROUP BY parent_id) r ON r.parent_id = c.id
                SET c.likes_count = COALESCE(l.n, 0), c.replies_count = COALESCE(r.n, 0)
                WHERE c.parent_id IS NULL
            SQL);

            DB::update(<<<'SQL'
                UPDATE users u
                LEFT JOIN (SELECT f.following_id AS id, COUNT(*) AS n FROM follows f JOIN users x ON x.id = f.follower_id WHERE x.deleted_at IS NULL GROUP BY f.following_id) fr ON fr.id = u.id
                LEFT JOIN (SELECT f.follower_id AS id, COUNT(*) AS n FROM follows f JOIN users x ON x.id = f.following_id WHERE x.deleted_at IS NULL GROUP BY f.follower_id) fg ON fg.id = u.id
                SET u.followers_count = COALESCE(fr.n, 0), u.following_count = COALESCE(fg.n, 0)
            SQL);
        } else {
            DB::update(<<<'SQL'
                UPDATE comments SET
                    likes_count = (SELECT COUNT(*) FROM comment_likes cl WHERE cl.comment_id = comments.id),
                    replies_count = (SELECT COUNT(*) FROM comments r WHERE r.parent_id = comments.id AND r.deleted_at IS NULL AND r.status = 'published')
                WHERE parent_id IS NULL
            SQL);

            DB::update(<<<'SQL'
                UPDATE users SET
                    followers_count = (SELECT COUNT(*) FROM follows f JOIN users u ON u.id = f.follower_id WHERE f.following_id = users.id AND u.deleted_at IS NULL),
                    following_count = (SELECT COUNT(*) FROM follows f JOIN users u ON u.id = f.following_id WHERE f.follower_id = users.id AND u.deleted_at IS NULL)
            SQL);
        }

        $this->info('Hisoblagichlar qayta hisoblandi.');

        return self::SUCCESS;
    }
}
