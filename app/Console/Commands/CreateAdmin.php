<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateAdmin extends Command
{
    protected $signature = 'fikrlash:create-admin {phone? : +998XXXXXXXXX} {--username=} {--name=}';

    protected $description = 'Administrator yaratadi yoki mavjud foydalanuvchini admin qiladi';

    public function handle(): int
    {
        $phone = PhoneNumber::normalize($this->argument('phone') ?? text('Telefon raqam', '+998 90 123 45 67', required: true));
        if (! $phone) {
            $this->error('Telefon raqam noto‘g‘ri.');

            return self::FAILURE;
        }

        $user = User::query()->where('phone', $phone)->first();
        if ($user) {
            $user->forceFill(['role' => UserRole::Admin])->save();
            $this->info("@{$user->username} endi administrator.");

            return self::SUCCESS;
        }

        $username = $this->option('username') ?? text('Username', default: 'admin', required: true);
        $name = $this->option('name') ?? text('Ism', default: 'Administrator', required: true);
        $secret = password('Parol (kamida 12 belgi)', required: true);

        $validator = Validator::make(['password' => $secret], ['password' => [Password::min(12)->letters()->numbers()]]);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        $user = new User;
        $user->forceFill([
            'name' => $name, 'username' => $username, 'phone' => $phone, 'password' => $secret,
            'role' => UserRole::Admin, 'phone_verified_at' => now(),
        ])->save();

        $this->info("Administrator yaratildi: @{$user->username}");

        return self::SUCCESS;
    }
}
