<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PostStatus: string implements HasLabel
{
    case Published = 'published';
    case Draft = 'draft';
    case Hidden = 'hidden';
    case PendingModeration = 'pending_moderation';

    public function getLabel(): string
    {
        return match ($this) {
            self::Published => 'Chop etilgan',
            self::Draft => 'Qoralama',
            self::Hidden => 'Yashirilgan',
            self::PendingModeration => 'Tekshiruvda',
        };
    }

    /** @return array<string, string> value => label */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])->all();
    }
}
