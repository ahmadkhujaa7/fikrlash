<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('username', 30)->unique();
            // E.164 formatida saqlanadi: +998901234567
            $table->string('phone', 20)->unique();
            $table->string('email')->nullable()->unique();
            $table->string('password');
            $table->string('avatar_path')->nullable();
            $table->string('bio', 300)->nullable();
            $table->string('gender', 16)->nullable();
            $table->date('birth_date')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamp('suspended_until')->nullable();
            $table->string('role', 20)->default('user')->index();
            $table->unsignedInteger('followers_count')->default(0);
            $table->unsignedInteger('following_count')->default(0);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('last_active_at')->nullable()->index();
            $table->rememberToken();
            $table->timestamps();
            // Account deletion: soft delete + grace period, keyin scheduler to‘liq o‘chiradi.
            $table->softDeletes()->index();

            $table->index('created_at');
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        // SMS OTP kodlari. Kod hech qachon ochiq holda saqlanmaydi.
        Schema::create('phone_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20);
            $table->string('purpose', 20);
            $table->string('code_hash', 64);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['phone', 'purpose', 'created_at']);
            $table->index('expires_at');
        });

        // API tokenlar (Laravel Sanctum sxemasi; token SHA-256 hash ko‘rinishida saqlanadi).
        Schema::create('users_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users_tokens');
        Schema::dropIfExists('phone_verifications');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
    }
};
