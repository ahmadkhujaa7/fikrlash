<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 1) Chat: rasm/video (message_attachments), joylashuv (messages.meta), post ulashish (messages.post_id).
 * 2) Bildirishnoma sozlamalari: users.notification_settings (turi bo‘yicha yoqish/o‘chirish).
 * 3) Admin e'lonlari: announcements + announcement_receipts (kim ro‘yxatda ko‘rdi, kim bosib ochdi).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('post_id')->nullable()->after('reply_to_id')->constrained()->nullOnDelete();
            $table->json('meta')->nullable()->after('voice_waveform');
        });

        Schema::create('message_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16); // image | video
            $table->string('path');
            $table->string('mime', 64);
            $table->unsignedInteger('size')->default(0);
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->unsignedSmallInteger('duration')->nullable(); // video, soniya
            $table->string('poster_path')->nullable(); // video uchun birinchi kadr
            $table->unsignedTinyInteger('position')->default(0);
            $table->timestamp('created_at')->nullable();

            $table->index(['message_id', 'position']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->json('notification_settings')->nullable()->after('messages_from');
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->text('body');
            $table->string('image_path')->nullable();
            $table->string('link_url', 500)->nullable();
            $table->string('link_label', 60)->nullable();
            $table->string('audience', 16)->default('all'); // all | users
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('recipients_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('announcement_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('seen_at')->nullable();   // bildirishnomalar sahifasida ko‘rindi
            $table->timestamp('opened_at')->nullable(); // bosib ochildi

            $table->unique(['announcement_id', 'user_id']);
            $table->index(['announcement_id', 'opened_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_receipts');
        Schema::dropIfExists('announcements');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('notification_settings'));
        Schema::dropIfExists('message_attachments');
        Schema::table('messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('post_id');
            $table->dropColumn('meta');
        });
    }
};
