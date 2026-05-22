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
        Schema::create('usage_limits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('message_reply_limit')->default(0);
            $table->unsignedInteger('message_reply_used')->default(0);
            $table->unsignedInteger('comment_reply_limit')->default(0);
            $table->unsignedInteger('comment_reply_used')->default(0);
            $table->unsignedInteger('ai_reply_limit')->default(0);
            $table->unsignedInteger('ai_reply_used')->default(0);
            $table->unsignedInteger('connected_page_limit')->default(0);
            $table->unsignedInteger('team_member_limit')->default(0);
            $table->unsignedInteger('knowledge_base_limit')->default(0);
            $table->timestamp('reset_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usage_limits');
    }
};
