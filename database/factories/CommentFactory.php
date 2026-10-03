<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    public const SAMPLES = [
        'Juda to‘g‘ri fikr, qo‘shilaman!', 'Qiziq, lekin men boshqacha o‘ylayman.', 'Rahmat, foydali bo‘ldi.',
        'Bu haqda batafsilroq yozsangiz yaxshi bo‘lardi.', 'Menda ham xuddi shunday tajriba bo‘lgan.',
        'Manba bormi? O‘qib ko‘rmoqchiman.', 'Zo‘r g‘oya 👍', 'Savolingizga javob: menimcha Python.',
    ];

    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'user_id' => User::factory(),
            'content' => fake()->randomElement(self::SAMPLES),
        ];
    }
}
