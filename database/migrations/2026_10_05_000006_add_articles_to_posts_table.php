<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Maqolalar: sarlavha + bloklar (paragraf, kichik sarlavha, rasm, iqtibos, ro‘yxat, ajratgich).
 * `content` — bloklardan yig‘ilgan oddiy matn (qidiruv, AI, teglar va eslatmalar shu orqali ishlaydi).
 * `media_uploads` — maqola ichiga yuklangan rasmlar: kimga tegishli va qaysi postga biriktirilgani.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('type', 16)->default('post')->after('category_id');
            $table->string('title', 200)->nullable()->after('type');
            $table->json('blocks')->nullable()->after('content');
            $table->index(['type', 'status', 'published_at']);
        });

        // Uzun maqola TEXT (64 KB) ga sig‘masligi mumkin. SQLite'da cheklov yo‘q.
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            Schema::table('posts', function (Blueprint $table) {
                $table->mediumText('content')->change();
                $table->mediumText('search_text')->nullable()->change();
            });
        }

        Schema::create('media_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->nullable()->constrained()->nullOnDelete();
            $table->string('path')->unique();
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['post_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_uploads');

        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex(['type', 'status', 'published_at']);
            $table->dropColumn(['type', 'title', 'blocks']);
        });
    }
};
