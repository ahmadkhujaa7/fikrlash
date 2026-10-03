<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('type', 40);
            $table->nullableMorphs('subject');
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('reportable');
            $table->string('reason', 32);
            $table->string('description', 1000)->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('resolution_note', 500)->nullable();
            $table->timestamps();

            // Bitta foydalanuvchi bitta kontentni bir marta report qiladi.
            $table->unique(['user_id', 'reportable_type', 'reportable_id']);
        });

        Schema::create('post_ai_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('status', 16)->default('completed');
            $table->string('provider', 30);
            $table->string('model', 80)->nullable();
            $table->char('content_hash', 64)->nullable();
            $table->string('topic', 120)->nullable();
            $table->string('category', 60)->nullable();
            $table->string('sentiment', 16)->nullable();
            $table->unsignedTinyInteger('quality_score')->nullable();
            $table->unsignedTinyInteger('toxicity_score')->nullable();
            $table->unsignedTinyInteger('spam_score')->nullable();
            $table->unsignedTinyInteger('educational_score')->nullable();
            $table->unsignedTinyInteger('engagement_score')->nullable();
            $table->string('summary', 500)->nullable();
            $table->json('keywords')->nullable();
            $table->json('raw_response')->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('latency_ms')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamps();

            $table->index(['post_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 60);
            $table->string('target_type', 120)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['target_type', 'target_id']);
            $table->index(['action', 'created_at']);
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 80)->primary();
            $table->json('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['settings', 'audit_logs', 'post_ai_analyses', 'reports', 'notifications'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
