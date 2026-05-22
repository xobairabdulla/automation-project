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
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('monthly_price', 10, 2)->default(0);
            $table->decimal('yearly_price', 10, 2)->default(0);
            $table->unsignedInteger('message_reply_limit')->default(0);
            $table->unsignedInteger('comment_reply_limit')->default(0);
            $table->unsignedInteger('ai_reply_limit')->default(0);
            $table->unsignedInteger('connected_page_limit')->default(0);
            $table->unsignedInteger('team_member_limit')->default(0);
            $table->unsignedInteger('knowledge_base_limit')->default(0);
            $table->json('features_json')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
