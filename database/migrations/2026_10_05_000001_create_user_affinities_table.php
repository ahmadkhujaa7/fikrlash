<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Foydalanuvchi "didi" (taste profile) — tavsiya algoritmining xotirasi.
 *
 * Har bir xususiyat (kategoriya, teg, muallif) uchun ikki son saqlanadi:
 *   exposures — foydalanuvchiga shu xususiyatli post necha marta ko‘rsatildi;
 *   score     — u bunga qanchalik javob berdi (ochdi, o‘qidi, like, izoh, saqlash).
 * Nisbat (score / exposures) qiziqishni ko‘rsatadi: ko‘p ko‘rsatilib, javob olmagan narsa pastga tushadi.
 * Foydalanuvchi hech narsani qo‘lda tanlamaydi — profil faqat xatti-harakatdan quriladi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_affinities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10); // category | tag | author
            $table->unsignedBigInteger('target_id');
            $table->float('score')->default(0);
            $table->float('exposures')->default(0);
            $table->timestamp('updated_at')->nullable();
            $table->unique(['user_id', 'kind', 'target_id']);
            $table->index(['kind', 'target_id']);
        });

        Schema::table('post_views', function (Blueprint $table) {
            // "Qiziq emas" bosilgan post — lentada boshqa ko‘rinmaydi.
            $table->timestamp('dismissed_at')->nullable();
        });

        // Eski kategoriya qiziqishlari yangi jadvalga ko‘chiriladi (user_interests jadvali o‘chirilmaydi).
        if (Schema::hasTable('user_interests')) {
            DB::table('user_interests')->orderBy('user_id')->chunk(500, function ($rows) {
                DB::table('user_affinities')->insertOrIgnore($rows->map(fn ($r) => [
                    'user_id' => $r->user_id,
                    'kind' => 'category',
                    'target_id' => $r->category_id,
                    'score' => (float) $r->weight,
                    'exposures' => 0,
                    'updated_at' => now(),
                ])->all());
            });
        }
    }

    public function down(): void
    {
        Schema::table('post_views', fn (Blueprint $table) => $table->dropColumn('dismissed_at'));
        Schema::dropIfExists('user_affinities');
    }
};
