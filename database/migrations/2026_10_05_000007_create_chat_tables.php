<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shaxsiy xabarlar (ikki kishi o‘rtasidagi suhbat):
 *  - conversations             — har bir juftlik uchun bitta suhbat (pair_key: "kichikId:kattaId");
 *  - conversation_participants — kim qayergacha o‘qigan, suhbatni tozalaganmi, bloklaganmi;
 *  - messages                  — matn yoki ovozli xabar; tahrirlash va o‘chirish izi bilan;
 *  - message_reactions         — har bir odam bitta xabarga bitta reaksiya.
 * users.messages_from — kim menga yoza oladi: everyone | following | nobody.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('messages_from', 16)->default('everyone')->after('status');
        });

        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->string('pair_key', 64)->unique();
            $table->unsignedBigInteger('last_message_id')->nullable();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('last_read_message_id')->default(0);
            // "Suhbatni tozalash": shu id gacha bo‘lgan xabarlar bu ishtirokchiga ko‘rinmaydi.
            $table->unsignedBigInteger('cleared_message_id')->default(0);
            // Bu ishtirokchi suhbatdoshini bloklagan — undan xabar qabul qilmaydi.
            $table->timestamp('blocked_at')->nullable();
            $table->timestamps();

            $table->unique(['conversation_id', 'user_id']);
            $table->index(['user_id', 'conversation_id']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reply_to_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->string('type', 16)->default('text');
            $table->text('body')->nullable();
            $table->string('voice_path')->nullable();
            $table->string('voice_mime', 32)->nullable();
            $table->unsignedSmallInteger('voice_duration')->nullable(); // soniya
            $table->json('voice_waveform')->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'id']);
            $table->index(['conversation_id', 'updated_at']);
        });

        Schema::create('message_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('emoji', 16);
            $table->timestamp('created_at')->nullable();

            $table->unique(['message_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_reactions');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversation_participants');
        Schema::dropIfExists('conversations');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('messages_from');
        });
    }
};
