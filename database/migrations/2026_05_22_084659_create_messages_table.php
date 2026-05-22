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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedBigInteger('facebook_page_id');
            $table->unsignedBigInteger('customer_id');
            $table->string('direction');
            $table->string('sender_type');
            $table->text('message_text')->nullable();
            $table->string('external_message_id')->nullable();
            $table->string('status')->default('received');
            $table->json('metadata_json')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('conversation_id');
            $table->index(['direction', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
