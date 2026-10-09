<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Kampaniya havolasi: fikrlash.uz/r/<kod>. Masalan "Tg1" — Telegram kanal adminiga beriladi,
 * shu havola orqali necha kishi kirgani va ro‘yxatdan o‘tgani hisoblanadi.
 */
class MarketingLink extends Model
{
    use SoftDeletes;

    public const CHANNELS = [
        'telegram' => 'Telegram',
        'instagram' => 'Instagram',
        'facebook' => 'Facebook',
        'youtube' => 'YouTube',
        'tiktok' => 'TikTok',
        'blogger' => 'Bloger',
        'ads' => 'Pullik reklama (target)',
        'partner' => 'Hamkor sayt',
        'offline' => 'Offline / QR',
        'other' => 'Boshqa',
    ];

    public const CHANNEL_COLORS = [
        'telegram' => 'info', 'instagram' => 'danger', 'facebook' => 'primary', 'youtube' => 'danger', 'tiktok' => 'gray',
        'blogger' => 'warning', 'ads' => 'success', 'partner' => 'primary', 'offline' => 'gray', 'other' => 'gray',
    ];

    public const TARGETS = [
        '/register' => 'Ro‘yxatdan o‘tish sahifasi',
        '/' => 'Bosh sahifa (lenta)',
    ];

    protected $fillable = ['name', 'code', 'channel', 'target', 'partner', 'contact', 'cost', 'welcome', 'notes', 'is_active', 'expires_at', 'created_by'];

    protected function casts(): array
    {
        return [
            'cost' => 'decimal:2',
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
            'last_click_at' => 'datetime',
        ];
    }

    public function visits(): HasMany
    {
        return $this->hasMany(MarketingVisit::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'acquisition_link_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Ulashish uchun to‘liq havola: https://fikrlash.uz/r/tg1 */
    public function url(): string
    {
        return route('marketing.go', $this->code);
    }

    /** Hozir bosishlarni hisoblaydimi (faol va muddati o‘tmagan). */
    public function isLive(): bool
    {
        return $this->is_active && ! $this->trashed() && (! $this->expires_at || $this->expires_at->isFuture());
    }

    /** Faqat o‘z saytimizdagi yo‘l: tashqi saytga yo‘naltirib bo‘lmaydi. */
    public function targetPath(): string
    {
        $raw = trim((string) $this->target);
        if ($raw === '' || ! str_starts_with($raw, '/') || str_starts_with($raw, '//') || preg_match('#[\s\\\\]|://#', $raw)) {
            return '/register';
        }

        return $raw;
    }

    public function channelLabel(): string
    {
        return self::CHANNELS[$this->channel] ?? $this->channel;
    }

    /** Ro‘yxatdan o‘tganlar / noyob tashrifchilar, foizda. */
    public function conversion(): ?float
    {
        return $this->visitors_count ? round($this->signups_count / $this->visitors_count * 100, 1) : null;
    }

    /** Bitta ro‘yxatdan o‘tish narxi (xarajat kiritilgan bo‘lsa). */
    public function costPerSignup(): ?float
    {
        return $this->cost !== null && $this->signups_count ? round((float) $this->cost / $this->signups_count, 2) : null;
    }
}
