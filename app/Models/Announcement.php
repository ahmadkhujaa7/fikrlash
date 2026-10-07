<?php

namespace App\Models;

use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Admin e'loni: foydalanuvchilarning bildirishnomalar ro‘yxatida chiqadi, bosilganda to‘liq ochiladi.
 * Bildirishnoma qatorlari e'longa havola qiladi — admin tahrirlasa, hammada yangilanadi;
 * o‘chirsa — hammadan o‘chadi.
 *
 * @property int $id
 * @property string $title
 * @property string $body
 */
class Announcement extends Model
{
    public const AUDIENCE_ALL = 'all';

    public const AUDIENCE_USERS = 'users';

    protected $fillable = ['title', 'body', 'image_path', 'link_url', 'link_label', 'audience', 'created_by'];

    protected $attributes = ['audience' => 'all', 'recipients_count' => 0];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'recipients_count' => 'integer'];
    }

    protected static function booted(): void
    {
        // O‘chirilganda: foydalanuvchilardagi bildirishnomalar va rasm ham o‘chadi.
        static::deleting(function (Announcement $announcement) {
            Notification::query()
                ->where('type', NotificationType::Announcement->value)
                ->where('subject_type', 'announcement')
                ->where('subject_id', $announcement->id)
                ->delete();
        });
        // Rasm almashtirilsa — eskisi o‘chiriladi.
        static::updated(function (Announcement $announcement) {
            $old = $announcement->getOriginal('image_path');
            if ($announcement->wasChanged('image_path') && $old) {
                Storage::disk(config('fikrlash.media.disk'))->delete($old);
            }
        });
        static::deleted(function (Announcement $announcement) {
            if ($announcement->image_path) {
                Storage::disk(config('fikrlash.media.disk'))->delete($announcement->image_path);
            }
        });
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(AnnouncementReceipt::class);
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk(config('fikrlash.media.disk'))->url($this->image_path) : null;
    }
}
