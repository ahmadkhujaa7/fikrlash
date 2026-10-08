<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mualliflar monetizatsiyasi (maqolalar uchun).
 *  - users.monetized_at — admin "Muallif" deb tasdiqlagan vaqt; shundan keyin yozilgan maqolalar daromad keltiradi.
 *  - author_applications — muallif bo‘lish so‘rovlari (yuborilgan paytdagi ko‘rsatkichlar bilan).
 *  - author_earnings — har bir maqola uchun kunlik ko‘rishlar va hisoblangan summa (o‘sha paytdagi narxda).
 *  - author_payouts — pul yechish so‘rovlari.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('monetized_at')->nullable()->after('verified_at');
        });

        Schema::create('author_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 16)->default('pending'); // pending | approved | rejected | revoked
            $table->text('message')->nullable();
            $table->unsignedInteger('followers')->default(0);
            $table->unsignedBigInteger('article_views')->default(0);
            $table->unsignedInteger('articles')->default(0);
            $table->text('admin_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('author_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('views')->default(0);
            $table->decimal('amount', 14, 2)->default(0);
            $table->timestamps();

            $table->unique(['post_id', 'date']);
            $table->index(['user_id', 'date']);
        });

        Schema::create('author_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('status', 16)->default('pending'); // pending | paid | rejected | cancelled
            $table->string('method', 24)->default('card');
            $table->text('account');             // shifrlangan: karta raqami
            $table->string('holder', 120)->nullable();
            $table->text('admin_note')->nullable();
            $table->string('reference', 120)->nullable(); // to‘lov kvitansiyasi / tranzaksiya raqami
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('author_payouts');
        Schema::dropIfExists('author_earnings');
        Schema::dropIfExists('author_applications');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('monetized_at'));
    }
};
