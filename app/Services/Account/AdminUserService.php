<?php

namespace App\Services\Account;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\Media\ImageService;
use App\Services\Moderation\ModerationService;
use App\Services\Security\SessionService;
use App\Services\Social\AuditLogger;
use App\Support\PhoneNumber;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admin foydalanuvchining istalgan ma'lumotini o‘zgartiradi.
 * Har bir o‘zgarish audit log'ga "eski → yangi" ko‘rinishida yoziladi (parol qiymati yozilmaydi).
 * Holat o‘zgarishi ModerationService orqali o‘tadi (bloklashda sessiyalar va kontent ham yopiladi).
 */
class AdminUserService
{
    /** Audit log'da kuzatiladigan maydonlar. */
    private const TRACKED = ['name', 'username', 'email', 'phone', 'bio', 'gender', 'birth_date', 'avatar_path', 'role', 'admin_note'];

    public function __construct(
        private AuditLogger $audit,
        private ModerationService $moderation,
        private VerificationService $verification,
        private SessionService $sessions,
        private ImageService $images,
    ) {}

    public function create(array $data, User $admin): User
    {
        $verified = (bool) ($data['is_verified'] ?? false);
        $status = $this->status($data['status'] ?? UserStatus::Active);
        $until = $data['suspended_until'] ?? null;
        unset($data['is_verified'], $data['status'], $data['suspended_until'], $data['password_confirmation']);

        $user = DB::transaction(function () use ($data) {
            $user = new User;
            // role/status fillable emas — admin ataylab belgilaydi.
            $user->forceFill([...$data, 'phone' => PhoneNumber::normalize($data['phone']), 'phone_verified_at' => now()])->save();

            return $user;
        });

        $this->audit->log('user.created_by_admin', $user, [], $user->only(['name', 'username', 'phone', 'role']), $admin);
        $this->applyStatus($user, $status, $until, $admin);
        if ($verified) {
            $this->verification->verify($user, $admin);
        }

        return $user;
    }

    public function update(User $user, array $data, User $admin): User
    {
        $isSelf = $admin->is($user);
        $verified = array_key_exists('is_verified', $data) ? (bool) $data['is_verified'] : null;
        $status = array_key_exists('status', $data) && ! $isSelf ? $this->status($data['status']) : null;
        $until = $data['suspended_until'] ?? null;
        $password = filled($data['password'] ?? null) ? $data['password'] : null;
        unset($data['is_verified'], $data['status'], $data['suspended_until'], $data['password'], $data['password_confirmation']);

        if ($isSelf) {
            unset($data['role']); // admin o‘z rolini tushirib qo‘ya olmaydi
        }
        if (isset($data['phone'])) {
            $data['phone'] = PhoneNumber::normalize($data['phone']) ?? $user->phone;
        }
        $this->guardLastAdmin($user, $data['role'] ?? null);

        $before = $this->snapshot($user);
        $oldAvatar = $user->avatar_path;

        DB::transaction(function () use ($user, $data, $password) {
            $user->forceFill($data);
            if ($password) {
                $user->password = $password;
            }
            $user->save();
        });

        if ($oldAvatar && $oldAvatar !== $user->avatar_path) {
            $this->images->delete($oldAvatar);
        }

        $after = $this->snapshot($user->fresh());
        $changed = array_keys(array_diff_assoc(array_map('strval', $after), array_map('strval', $before)));
        if ($changed || $password) {
            $this->audit->log(
                'user.updated_by_admin',
                $user,
                array_intersect_key($before, array_flip($changed)),
                array_intersect_key($after, array_flip($changed)) + ($password ? ['password_changed' => true] : []),
                $admin,
            );
        }

        // Parol admin tomonidan almashtirilsa — eski qurilmalardagi kirishlar yopiladi.
        if ($password && ! $isSelf) {
            $this->sessions->terminateAll($user, $admin);
        }

        if ($status !== null) {
            $this->applyStatus($user, $status, $until, $admin);
        }
        if ($verified !== null) {
            $verified ? $this->verification->verify($user, $admin) : $this->verification->unverify($user, $admin);
        }

        return $user->fresh();
    }

    private function applyStatus(User $user, UserStatus $status, mixed $until, User $admin): void
    {
        if ($user->status === $status && $status !== UserStatus::Suspended) {
            return;
        }

        match ($status) {
            UserStatus::Blocked => $this->moderation->blockUser($user, $admin),
            UserStatus::Suspended => $this->moderation->suspendUser($user, $until ? Carbon::parse($until) : now()->addDays(3), $admin),
            UserStatus::Active => $user->status === UserStatus::Active ? null : $this->moderation->activateUser($user, $admin),
            UserStatus::Deactivated => tap($user->forceFill(['status' => UserStatus::Deactivated])->save(),
                fn () => $this->audit->log('user.deactivated_by_admin', $user, [], [], $admin)),
        };
    }

    private function guardLastAdmin(User $user, mixed $newRole): void
    {
        $newRole = $newRole instanceof UserRole ? $newRole : ($newRole ? UserRole::from($newRole) : null);
        if ($user->isAdmin() && $newRole && $newRole !== UserRole::Admin
            && User::query()->where('role', UserRole::Admin)->count() <= 1) {
            throw ValidationException::withMessages(['data.role' => 'Bu yagona admin — avval boshqa adminni tayinlang.']);
        }
    }

    private function status(mixed $value): UserStatus
    {
        return $value instanceof UserStatus ? $value : UserStatus::from($value);
    }

    /** @return array<string, string|null> */
    private function snapshot(User $user): array
    {
        return collect(self::TRACKED)->mapWithKeys(function ($key) use ($user) {
            $value = $user->getAttribute($key);

            return [$key => match (true) {
                $value instanceof \BackedEnum => $value->value,
                $value instanceof \DateTimeInterface => $value->format('Y-m-d'),
                $key === 'admin_note' => $value ? '(izoh #'.substr(md5((string) $value), 0, 6).')' : null, // izoh matni audit'ga ochiq yozilmaydi
                default => $value,
            }];
        })->all();
    }
}
