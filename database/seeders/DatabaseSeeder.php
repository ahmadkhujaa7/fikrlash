<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CategorySeeder::class);
        $this->seedAdmin();

        if (app()->environment(['local', 'testing'])) {
            $this->call(DemoSeeder::class);
        }
    }

    /**
     * Admin ADMIN_PHONE va ADMIN_PASSWORD (.env) dan olinadi.
     * Productionda ular berilmasa — admin yaratilmaydi (php artisan fikrlash:create-admin ishlating).
     */
    private function seedAdmin(): void
    {
        $phone = PhoneNumber::normalize(config('fikrlash.admin.phone'));
        $password = config('fikrlash.admin.password');

        if (! $phone || ! $password) {
            if (! app()->environment(['local', 'testing'])) {
                $this->command?->warn('ADMIN_PHONE/ADMIN_PASSWORD berilmagan — admin yaratilmadi.');

                return;
            }
            [$phone, $password] = ['+998900000001', 'admin12345'];
        }

        $admin = User::query()->firstOrNew(['phone' => $phone]);
        $admin->forceFill([
            'name' => 'Administrator',
            'username' => $admin->username ?? 'admin',
            'password' => $password,
            'role' => UserRole::Admin,
            'phone_verified_at' => now(),
            'last_active_at' => now(),
        ])->save();

        $this->command?->info("Admin: {$phone} / ".(config('fikrlash.admin.password') ? '(.env dagi parol)' : $password));
    }
}
