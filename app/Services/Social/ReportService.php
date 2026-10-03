<?php

namespace App\Services\Social;

use App\Enums\ReportReason;
use App\Events\PostReported;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class ReportService
{
    public function create(User $reporter, Model $target, ReportReason $reason, ?string $description = null): Report
    {
        $owner = match (true) {
            $target instanceof Post, $target instanceof Comment => $target->user_id,
            $target instanceof User => $target->id,
            default => throw ValidationException::withMessages(['type' => 'Bu turdagi kontentni xabar qilib bo‘lmaydi.']),
        };

        if ($owner === $reporter->id) {
            throw ValidationException::withMessages(['type' => 'O‘z kontentingizni xabar qila olmaysiz.']);
        }

        $report = new Report([
            'reportable_type' => $target->getMorphClass(),
            'reportable_id' => $target->getKey(),
            'reason' => $reason,
            'description' => $description,
        ]);
        $report->user()->associate($reporter);

        try {
            $report->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['type' => 'Siz bu kontentni allaqachon xabar qilgansiz. Rahmat!']);
        }

        PostReported::dispatch($report);

        return $report;
    }
}
