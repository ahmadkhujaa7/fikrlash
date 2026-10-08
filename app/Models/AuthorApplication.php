<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Muallif (monetizatsiya) so‘rovi. Ko‘rsatkichlar — so‘rov yuborilgan paytdagi holat. */
class AuthorApplication extends Model
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const REVOKED = 'revoked';

    public const LABELS = [
        self::PENDING => 'Ko‘rib chiqilmoqda',
        self::APPROVED => 'Tasdiqlangan',
        self::REJECTED => 'Rad etilgan',
        self::REVOKED => 'To‘xtatilgan',
    ];

    public const COLORS = [
        self::PENDING => 'warning',
        self::APPROVED => 'success',
        self::REJECTED => 'danger',
        self::REVOKED => 'gray',
    ];

    protected $fillable = ['user_id', 'status', 'message', 'followers', 'article_views', 'articles', 'admin_note', 'reviewed_by', 'reviewed_at'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime', 'followers' => 'integer', 'article_views' => 'integer', 'articles' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }

    public function label(): string
    {
        return self::LABELS[$this->status] ?? $this->status;
    }
}
