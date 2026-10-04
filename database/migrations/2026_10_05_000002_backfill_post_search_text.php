<?php

use App\Support\TextNormalizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Qidiruv matni bo‘sh qolgan postlar uchun to‘ldirish.
 * Seeder model hodisalarisiz ishlagani sababli demo postlarda search_text yozilmagan edi —
 * natijada ular qidiruvda topilmasdi. Faqat bo‘sh qiymatlar to‘ldiriladi, hech narsa o‘chirilmaydi.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('posts')->whereNull('search_text')->orderBy('id')->select(['id', 'content'])
            ->chunkById(500, function ($posts) {
                foreach ($posts as $post) {
                    DB::table('posts')->where('id', $post->id)->update([
                        'search_text' => TextNormalizer::forSearch((string) $post->content),
                        'content_hash' => hash('sha256', TextNormalizer::forHash((string) $post->content)),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Ma'lumotni to‘ldirish — qaytarish shart emas.
    }
};
