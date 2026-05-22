<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ai_reply_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('facebook_page_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('conversation_id')->nullable();
            $table->unsignedBigInteger('facebook_comment_id')->nullable();
            $table->text('prompt');
            $table->text('response')->nullable();
            $table->string('model')->nullable();
            $table->unsignedInteger('tokens_used')->default(0);
            $table->enum('status', ['success', 'failed', 'unknown_answer'])->default('success');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['facebook_page_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_reply_logs');
    }
};
