<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ReportStatus: string implements HasLabel
{
    case Pending = 'pending';
    case Reviewing = 'reviewing';
    case Resolved = 'resolved';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Kutilmoqda',
            self::Reviewing => 'Ko‘rib chiqilmoqda',
            self::Resolved => 'Hal qilindi',
            self::Rejected => 'Rad etildi',
        };
    }

    /** @return array<string, string> value => label */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])->all();
    }
}
