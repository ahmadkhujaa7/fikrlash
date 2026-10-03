<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    private const FIRST = ['Aziz', 'Dilnoza', 'Jasur', 'Malika', 'Sardor', 'Nodira', 'Bekzod', 'Gulnora', 'Otabek', 'Shahnoza',
        'Javohir', 'Madina', 'Sherzod', 'Kamola', 'Rustam', 'Zarina', 'Ulug‘bek', 'Feruza', 'Doniyor', 'Laylo', 'Bobur', 'Sevara'];

    private const LAST = ['Karimov', 'Rahimova', 'Tursunov', 'Yusupova', 'Aliyev', 'Nazarova', 'Ergashev', 'Qodirova', 'Ismoilov', 'Saidova'];

    public function definition(): array
    {
        $first = fake()->randomElement(self::FIRST);
        $last = fake()->randomElement(self::LAST);
        $last = str_ends_with($first, 'a') && ! str_ends_with($last, 'a') ? $last.'a' : $last;

        return [
            'name' => "{$first} {$last}",
            'username' => Str::of(Str::ascii($first))->lower()->replaceMatches('/[^a-z]/', '')
                ->append('_'.fake()->unique()->numberBetween(10, 99999))->toString(),
            'phone' => '+99890'.fake()->unique()->numerify('#######'),
            'password' => static::$password ??= Hash::make('password'),
            'bio' => fake()->boolean(60) ? fake()->randomElement([
                'Dasturchi. Kitob o‘qishni yaxshi ko‘raman.', 'Talaba, fizika va falsafa qiziqtiradi.',
                'Startaplar va mahsulot dizayni haqida yozaman.', 'Ona, o‘qituvchi, sayohatchi.',
                'Har kuni bitta yangi g‘oya.', 'Marketing va brending bo‘yicha mutaxassis.',
            ]) : null,
            'phone_verified_at' => now(),
            'last_active_at' => now()->subHours(fake()->numberBetween(0, 200)),
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => UserRole::Admin]);
    }

    public function blocked(): static
    {
        return $this->state(fn () => ['status' => UserStatus::Blocked]);
    }

    public function suspended(?\DateTimeInterface $until = null): static
    {
        return $this->state(fn () => ['status' => UserStatus::Suspended, 'suspended_until' => $until ?? now()->addDay()]);
    }
}
