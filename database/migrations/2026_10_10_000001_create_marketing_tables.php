<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketing: kampaniya havolalari (fikrlash.uz/r/<kod>), har bir bosish, ro‘yxatdan o‘tishni boshlaganlar
 * va foydalanuvchi qayerdan kelgani (havola, do‘st taklifi, UTM, boshqa sayt, to‘g‘ridan-to‘g‘ri).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_links', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('code', 40)->unique();
            $table->string('channel', 20)->default('telegram');
            $table->string('target', 255)->default('/register');
            $table->string('partner', 120)->nullable();
            $table->string('contact', 120)->nullable();
            $table->decimal('cost', 14, 2)->nullable();
            $table->string('welcome', 200)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('clicks_count')->default(0);
            $table->unsignedInteger('visitors_count')->default(0);
            $table->unsignedInteger('starts_count')->default(0);
            $table->unsignedInteger('signups_count')->default(0);
            $table->timestamp('last_click_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('marketing_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketing_link_id')->constrained()->cascadeOnDelete();
            $table->char('visitor', 32);
            $table->boolean('is_unique')->default(false);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('device', 10)->nullable();
            $table->string('os', 12)->nullable();
            $table->string('browser', 16)->nullable();
            $table->string('app', 12)->nullable();
            $table->string('referrer', 120)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['marketing_link_id', 'created_at']);
            $table->index(['marketing_link_id', 'visitor']);
        });

        // Ro‘yxatdan o‘tishni boshlagan (SMS kod so‘ragan) tashrifchilar — har biri bir marta.
        Schema::create('marketing_starts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketing_link_id')->constrained()->cascadeOnDelete();
            $table->char('visitor', 32);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['marketing_link_id', 'visitor']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('acquisition_link_id')->nullable()->index();
            $table->string('acquisition_source', 20)->nullable()->index();
            $table->string('acquisition_detail', 120)->nullable();
            $table->unsignedBigInteger('referred_by')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['acquisition_link_id']);
            $table->dropIndex(['acquisition_source']);
            $table->dropIndex(['referred_by']);
            $table->dropColumn(['acquisition_link_id', 'acquisition_source', 'acquisition_detail', 'referred_by']);
        });
        Schema::dropIfExists('marketing_starts');
        Schema::dropIfExists('marketing_visits');
        Schema::dropIfExists('marketing_links');
    }
};
