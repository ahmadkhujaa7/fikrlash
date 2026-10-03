<?php

namespace Database\Factories;

use App\Enums\ReportReason;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'reportable_type' => 'post',
            'reportable_id' => Post::factory(),
            'reason' => fake()->randomElement(ReportReason::cases()),
            'description' => fake()->boolean(40) ? 'Bu post qoidalarga zid deb o‘ylayman.' : null,
        ];
    }
}
