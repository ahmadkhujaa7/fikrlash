<?php

namespace App\Services\Moderation;

use App\Enums\CommentStatus;
use App\Enums\NotificationType;
use App\Enums\PostStatus;
use App\Enums\ReportStatus;
use App\Enums\UserStatus;
use App\Models\Comment;
use App\Models\Post;
use App\Models\PostAiAnalysis;
use App\Models\Report;
use App\Models\Setting;
use App\Models\User;
use App\Services\Social\AuditLogger;
use App\Services\Social\NotificationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Barcha moderatsiya qarorlari shu yerda — har biri audit log'ga yoziladi.
 * AI va reportlar faqat "signal": post tekshiruvga yuboriladi, foydalanuvchi avtomatik jazolanmaydi.
 */
class ModerationService
{
    public function __construct(private AuditLogger $audit, private NotificationService $notifications) {}

    public function hidePost(Post $post, ?User $admin = null, ?string $reason = null): void
    {
        $this->changePostStatus($post, PostStatus::Hidden, 'post.hidden', $admin, $reason);
        $this->notifications->notify($post->user, NotificationType::PostModerated, null, $post, [
            'status' => PostStatus::Hidden->value,
            'reason' => $reason,
        ]);
    }

    public function publishPost(Post $post, ?User $admin = null): void
    {
        $this->changePostStatus($post, PostStatus::Published, 'post.published', $admin);
        $post->published_at ??= now();
        $post->save();
    }

    public function deletePost(Post $post, ?User $admin = null, ?string $reason = null): void
    {
        $this->audit->log('post.deleted', $post, ['status' => $post->status], ['reason' => $reason], $admin);
        $post->delete();
    }

    public function restorePost(Post $post, ?User $admin = null): void
    {
        $post->restore();
        $this->audit->log('post.restored', $post, [], [], $admin);
    }

    public function hideComment(Comment $comment, ?User $admin = null): void
    {
        $old = $comment->status;
        $comment->update(['status' => CommentStatus::Hidden]);
        $this->audit->log('comment.hidden', $comment, ['status' => $old], ['status' => CommentStatus::Hidden], $admin);
    }

    public function suspendUser(User $user, ?Carbon $until, ?User $admin = null, ?string $reason = null): void
    {
        $old = ['status' => $user->status, 'suspended_until' => $user->suspended_until?->toIso8601String()];
        $user->forceFill(['status' => UserStatus::Suspended, 'suspended_until' => $until])->save();
        $this->audit->log('user.suspended', $user, $old, ['status' => UserStatus::Suspended, 'suspended_until' => $until?->toIso8601String(), 'reason' => $reason], $admin);
        $this->notifications->notify($user, NotificationType::System, null, null, [
            'message' => $until
                ? 'Akkauntingiz '.$until->format('d.m.Y H:i').' gacha cheklandi.'.($reason ? " Sabab: {$reason}" : '')
                : 'Akkauntingiz vaqtincha cheklandi.'.($reason ? " Sabab: {$reason}" : ''),
        ]);
    }

    public function blockUser(User $user, ?User $admin = null, ?string $reason = null): void
    {
        $old = ['status' => $user->status];
        DB::transaction(function () use ($user) {
            $user->forceFill(['status' => UserStatus::Blocked, 'suspended_until' => null])->save();
            $user->tokens()->delete();
            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
            }
        });
        $this->audit->log('user.blocked', $user, $old, ['status' => UserStatus::Blocked, 'reason' => $reason], $admin);
    }

    public function activateUser(User $user, ?User $admin = null): void
    {
        $old = ['status' => $user->status];
        $user->forceFill(['status' => UserStatus::Active, 'suspended_until' => null])->save();
        $this->audit->log('user.activated', $user, $old, ['status' => UserStatus::Active], $admin);
    }

    public function resolveReport(Report $report, ReportStatus $status, ?User $admin = null, ?string $note = null): void
    {
        $old = ['status' => $report->status];
        $report->forceFill([
            'status' => $status,
            'reviewed_by' => $admin?->id,
            'reviewed_at' => now(),
            'resolution_note' => $note,
        ])->save();
        $this->audit->log('report.'.$status->value, $report, $old, ['status' => $status, 'note' => $note], $admin);
    }

    /** Ko‘p foydalanuvchi report qilgan post avtomatik tekshiruvga yuboriladi. */
    public function evaluateReports(Report $report): void
    {
        $target = $report->reportable;
        if (! $target instanceof Post || ! $target->isPublished()) {
            return;
        }

        $distinct = Report::query()
            ->where('reportable_type', $target->getMorphClass())
            ->where('reportable_id', $target->id)
            ->where('status', ReportStatus::Pending)
            ->count();

        if ($distinct >= config('fikrlash.moderation.auto_review_reports')) {
            $this->sendToReview($target, 'reports', ['reports' => $distinct]);
        }
    }

    /** AI natijasi xavfli bo‘lsa — post admin tekshiruviga yuboriladi. */
    public function evaluateAiAnalysis(Post $post, PostAiAnalysis $analysis): bool
    {
        if (! Setting::read('ai_auto_moderation') || ! $post->isPublished()) {
            return false;
        }

        $thresholds = config('ai.thresholds');
        $risky = ($analysis->toxicity_score ?? 0) >= $thresholds['toxicity_review']
            || ($analysis->spam_score ?? 0) >= $thresholds['spam_review'];

        if ($risky) {
            $this->sendToReview($post, 'ai', [
                'toxicity' => $analysis->toxicity_score,
                'spam' => $analysis->spam_score,
            ]);
        }

        return $risky;
    }

    private function sendToReview(Post $post, string $source, array $context): void
    {
        $this->changePostStatus($post, PostStatus::PendingModeration, 'post.sent_to_review', null, $source, $context);
        $this->notifications->notify($post->user, NotificationType::PostModerated, null, $post, [
            'status' => PostStatus::PendingModeration->value,
        ]);
    }

    private function changePostStatus(Post $post, PostStatus $status, string $action, ?User $admin, ?string $reason = null, array $context = []): void
    {
        $old = $post->status;
        $post->status = $status;
        $post->save();

        $this->audit->log($action, $post, ['status' => $old], ['status' => $status, 'reason' => $reason] + $context, $admin);
    }
}
