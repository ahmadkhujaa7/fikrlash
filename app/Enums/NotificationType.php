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
    case Announcement = 'announcement';
    case Monetization = 'monetization';

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
            self::Announcement => 'E’lon',
            self::Monetization => 'Monetizatsiya',
        };
    }

    /** Foydalanuvchi sozlamalarda o‘chira oladigan turlar (tizim, moderatsiya va e'lonlar doim keladi). */
    public static function optional(): array
    {
        return [self::Followed, self::PostLiked, self::PostCommented, self::CommentReplied, self::Mentioned];
    }

    public function settingLabel(): string
    {
        return match ($this) {
            self::Followed => 'Yangi obunachilar',
            self::PostLiked => 'Fikrlarimni yoqtirishganda',
            self::PostCommented => 'Fikrlarimga izoh qoldirishganda',
            self::CommentReplied => 'Izohlarimga javob berishganda',
            self::Mentioned => 'Meni @eslatishganda',
            default => $this->getLabel(),
        };
    }

    /** @return array<string, string> value => label */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])->all();
    }
}
