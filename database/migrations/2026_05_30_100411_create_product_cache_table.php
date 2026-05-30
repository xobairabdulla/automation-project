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
        Schema::create('product_cache', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('source_id');
            $table->string('external_product_id')->nullable();
            $table->string('product_name');
            $table->string('sku')->nullable();
            $table->string('category')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->string('product_url')->nullable();
            $table->json('image_urls')->nullable();
            $table->text('description')->nullable();
            $table->string('stock_status')->nullable();
            $table->json('keywords')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('source_id')->references('id')->on('product_sources')->cascadeOnDelete();
            $table->index(['user_id', 'source_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_cache');
    }
};
