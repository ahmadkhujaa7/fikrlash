<?php

namespace App\Services\Account;

use App\Enums\UserStatus;
use App\Models\Comment;
use App\Models\MediaUpload;
use App\Models\Post;
use App\Models\User;
use App\Services\Media\ImageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AccountService
{
    public function __construct(private ImageService $images) {}

    /** @param array{name?: string, username?: string, bio?: string|null, email?: string|null, gender?: string|null, birth_date?: string|null} $data */
    public function updateProfile(User $user, array $data): User
    {
        $user->fill($data)->save();

        return $user;
    }

    public function updateAvatar(User $user, UploadedFile $file): User
    {
        $old = $user->avatar_path;
        $user->forceFill(['avatar_path' => $this->images->storeAvatar($file)])->save();
        $this->images->delete($old);

        return $user;
    }

    public function removeAvatar(User $user): void
    {
        $this->images->delete($user->avatar_path);
        $user->forceFill(['avatar_path' => null])->save();
    }

    public function changePassword(User $user, string $password): void
    {
        $user->forceFill(['password' => $password])->save();
        $user->tokens()->delete();
        Log::channel('security')->info('Parol o‘zgartirildi', ['user_id' => $user->id]);
    }

    /** Vaqtincha o‘chirish: profil va postlar yashiriladi, qayta kirganda tiklanadi. */
    public function deactivate(User $user): void
    {
        $user->forceFill(['status' => UserStatus::Deactivated])->save();
        $user->tokens()->delete();
    }

    public function reactivateIfNeeded(User $user): void
    {
        if ($user->status === UserStatus::Deactivated) {
            $user->forceFill(['status' => UserStatus::Active])->save();
        }
    }

    /**
     * Akkauntni o‘chirish. Darhol: kontent yashiriladi, tokenlar va sessiyalar bekor qilinadi.
     * Grace period (config) tugagach `accounts:purge-deleted` hammasini butunlay o‘chiradi.
     */
    public function delete(User $user): void
    {
        DB::transaction(function () use ($user) {
            Post::query()->where('user_id', $user->id)->update(['deleted_at' => now()]);
            Comment::query()->where('user_id', $user->id)->update(['deleted_at' => now()]);
            $user->tokens()->delete();

            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
            }

            $user->delete();
        });

        Log::channel('security')->info('Akkaunt o‘chirildi (grace period boshlandi)', ['user_id' => $user->id]);
    }

    /** Grace period tugagan akkauntni butunlay o‘chirish (FK cascade orqali barcha bog‘liq ma'lumotlar). */
    public function purge(User $user): void
    {
        Post::withTrashed()->where('user_id', $user->id)->whereNotNull('image_path')
            ->pluck('image_path')->each(fn ($path) => $this->images->delete($path));
        MediaUpload::query()->where('user_id', $user->id)->pluck('path')->each(fn ($path) => $this->images->delete($path));
        $this->images->delete($user->avatar_path);

        $user->forceDelete();
    }

    /** Foydalanuvchi ma'lumotlarini eksport qilish (GDPR uslubida) — JSON. */
    public function export(User $user): array
    {
        return [
            'profile' => $user->only(['name', 'username', 'phone', 'email', 'bio', 'gender', 'birth_date', 'created_at']),
            'posts' => $user->posts()->withTrashed()->get(['id', 'content', 'status', 'visibility', 'created_at'])->toArray(),
            'comments' => $user->comments()->get(['id', 'post_id', 'content', 'created_at'])->toArray(),
            'following' => $user->following()->pluck('username')->all(),
            'saved_posts' => $user->savedPosts()->pluck('posts.id')->all(),
            'exported_at' => now()->toIso8601String(),
        ];
    }
}
