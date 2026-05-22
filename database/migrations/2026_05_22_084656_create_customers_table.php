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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('facebook_page_id');
            $table->string('external_customer_id');
            $table->string('name')->nullable();
            $table->string('profile_picture_url')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->json('tags_json')->nullable();
            $table->timestamps();

            $table->unique(['facebook_page_id', 'external_customer_id']);
            $table->index('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
