<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum NotificationType: string implements HasLabel
{
    case Followed = 'followed';
    case PostLiked = 'post_liked';
    case PostCommented = 'post_commented';
    case CommentReplied = 'comment_replied';
    case Mentioned = 'mentioned';
    case PostModerated = 'post_moderated';
    case System = 'system';

    public function getLabel(): string
    {
        return match ($this) {
            self::Followed => 'Obuna bo‘ldi',
            self::PostLiked => 'Postni yoqtirdi',
            self::PostCommented => 'Izoh qoldirdi',
            self::CommentReplied => 'Izohga javob berdi',
            self::Mentioned => 'Sizni eslatdi',
            self::PostModerated => 'Post moderatsiyasi',
            self::System => 'Tizim xabari',
        };
    }

    /** @return array<string, string> value => label */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])->all();
    }
}
