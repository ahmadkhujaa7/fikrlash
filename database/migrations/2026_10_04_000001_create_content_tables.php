<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->string('slug', 60)->unique();
            $table->string('description', 255)->nullable();
            $table->string('icon', 40)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('slug', 50)->unique();
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->text('content');
            // Qidiruv uchun normallashtirilgan matn (apostrof variantlari birlashtirilgan, kichik harf).
            $table->text('search_text')->nullable();
            $table->char('content_hash', 64)->nullable()->index();
            $table->string('image_path')->nullable();
            $table->string('status', 24)->default('published');
            $table->string('visibility', 16)->default('public');
            $table->unsignedInteger('likes_count')->default(0);
            $table->unsignedInteger('comments_count')->default(0);
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('saves_count')->default(0);
            // Global "hot" ball — scheduler yangilab turadi (trending va For You nomzodlari uchun).
            $table->double('score')->default(0);
            $table->unsignedTinyInteger('ai_score')->nullable();
            $table->string('ai_category', 60)->nullable();
            $table->string('ai_sentiment', 16)->nullable();
            $table->string('ai_topic', 120)->nullable();
            $table->string('ai_summary', 500)->nullable();
            $table->boolean('ai_flagged')->default(false);
            $table->timestamp('ai_analyzed_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
            $table->index(['user_id', 'status', 'published_at']);
            $table->index(['category_id', 'status', 'published_at']);
            $table->index(['status', 'score']);
            $table->index('created_at');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE posts ADD FULLTEXT INDEX posts_search_text_fulltext (search_text)');
        }

        Schema::create('post_tag', function (Blueprint $table) {
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['post_id', 'tag_id']);
            $table->index('tag_id');
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Faqat bir daraja: javob doim yuqori darajadagi commentga bog‘lanadi.
            $table->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();
            $table->foreignId('reply_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('content');
            $table->unsignedInteger('likes_count')->default(0);
            $table->unsignedInteger('replies_count')->default(0);
            $table->string('status', 20)->default('published');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['post_id', 'parent_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('post_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['user_id', 'post_id']);
            $table->index(['post_id', 'created_at']);
        });

        Schema::create('comment_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['user_id', 'comment_id']);
            $table->index('comment_id');
        });

        Schema::create('saved_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['user_id', 'post_id']);
            $table->index('post_id');
        });

        Schema::create('follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('follower_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('following_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['follower_id', 'following_id']);
            $table->index(['following_id', 'created_at']);
        });

        // Tavsiya tizimi uchun foydalanuvchi qiziqishlari (kategoriya bo‘yicha vazn).
        Schema::create('user_interests', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->double('weight')->default(0);
            $table->timestamp('followed_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->primary(['user_id', 'category_id']);
        });

        // Tizimga kirgan foydalanuvchi qaysi postni ko‘rgani va qancha o‘qigani (90 kun saqlanadi).
        Schema::create('post_views', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('read_seconds')->default(0);
            $table->timestamp('first_viewed_at')->useCurrent();
            $table->timestamp('last_viewed_at')->useCurrent();
            $table->primary(['user_id', 'post_id']);
            $table->index('last_viewed_at');
        });
    }

    public function down(): void
    {
        foreach (['post_views', 'user_interests', 'follows', 'saved_posts', 'comment_likes', 'post_likes', 'comments', 'post_tag', 'posts', 'tags', 'categories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
