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
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('facebook_page_id');
            $table->unsignedBigInteger('customer_id');
            $table->string('channel')->default('facebook_messenger');
            $table->string('status')->default('open');
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->boolean('human_takeover')->default(false);
            $table->timestamp('last_customer_message_at')->nullable();
            $table->timestamp('last_reply_at')->nullable();
            $table->timestamps();

            $table->unique(['facebook_page_id', 'customer_id']);
            $table->index('tenant_id');
            $table->index('customer_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
