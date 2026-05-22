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
        Schema::create('daily_usage_stats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->date('date');
            $table->unsignedInteger('messages_received')->default(0);
            $table->unsignedInteger('comments_received')->default(0);
            $table->unsignedInteger('message_replies_sent')->default(0);
            $table->unsignedInteger('comment_replies_sent')->default(0);
            $table->unsignedInteger('ai_replies_sent')->default(0);
            $table->unsignedInteger('manual_replies_sent')->default(0);
            $table->unsignedInteger('human_handovers')->default(0);
            $table->unsignedInteger('failed_replies')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'date']);
            $table->index(['tenant_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_usage_stats');
    }
};
