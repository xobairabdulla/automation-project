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
        Schema::create('limit_extension_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('message_extra')->default(0);
            $table->unsignedInteger('comment_extra')->default(0);
            $table->unsignedInteger('ai_extra')->default(0);
            $table->decimal('price', 10, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('stripe_price_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('limit_extension_packages');
    }
};
