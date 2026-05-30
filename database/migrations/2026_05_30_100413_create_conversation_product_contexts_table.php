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
        Schema::create('conversation_product_contexts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('page_id');
            $table->string('facebook_user_id');
            $table->enum('channel_type', ['message', 'comment'])->default('message');
            $table->string('last_product_name')->nullable();
            $table->string('last_product_url')->nullable();
            $table->json('last_product_data')->nullable();
            $table->json('last_sent_images')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['user_id', 'page_id', 'facebook_user_id'], 'cpc_user_page_fb_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversation_product_contexts');
    }
};
