<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Muallifning pul yechish so‘rovi. Karta raqami bazada shifrlangan holda saqlanadi. */
class AuthorPayout extends Model
{
    public const PENDING = 'pending';

    public const PAID = 'paid';

    public const REJECTED = 'rejected';

    public const CANCELLED = 'cancelled';

    /** Balansdan "band" qilingan holatlar. */
    public const HELD = [self::PENDING, self::PAID];

    public const LABELS = [
        self::PENDING => 'Kutilmoqda',
        self::PAID => 'To‘landi',
        self::REJECTED => 'Rad etildi',
        self::CANCELLED => 'Bekor qilindi',
    ];

    public const COLORS = [
        self::PENDING => 'warning',
        self::PAID => 'success',
        self::REJECTED => 'danger',
        self::CANCELLED => 'gray',
    ];

    protected $fillable = ['user_id', 'amount', 'status', 'method', 'account', 'holder', 'admin_note', 'reference', 'processed_by', 'processed_at'];

    protected $hidden = ['account'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'account' => 'encrypted', 'processed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by')->withTrashed();
    }

    /** "8600 •••• •••• 1234" */
    public function maskedAccount(): string
    {
        $digits = preg_replace('/\D+/', '', (string) $this->account) ?? '';

        return strlen($digits) >= 8 ? substr($digits, 0, 4).' •••• •••• '.substr($digits, -4) : '••••';
    }

    public function label(): string
    {
        return self::LABELS[$this->status] ?? $this->status;
    }
}
