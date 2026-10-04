<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kirishlar tarixi — admin kuzatuvi uchun: muvaffaqiyatli va muvaffaqiyatsiz kirishlar,
 * chiqishlar, ro‘yxatdan o‘tish, admin "foydalanuvchi sifatida kirishi".
 * 180 kundan eski yozuvlar har kuni tozalanadi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // login | failed | blocked | logout | register | impersonate | impersonate_end | password_reset
            $table->string('event', 24);
            $table->string('channel', 8)->default('web'); // web | api | admin
            $table->string('identifier', 64)->nullable(); // muvaffaqiyatsiz urinishda kiritilgan login
            $table->string('ip', 45)->nullable();
            $table->string('device', 80)->nullable(); // "Chrome · Windows"
            $table->string('user_agent', 300)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete(); // impersonatsiyada admin
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['user_id', 'created_at']);
            $table->index(['ip', 'created_at']);
            $table->index(['event', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_events');
    }
};
