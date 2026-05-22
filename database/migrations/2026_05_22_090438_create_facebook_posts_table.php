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
        Schema::create('facebook_posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('facebook_page_id');
            $table->string('external_post_id')->index();
            $table->text('message')->nullable();
            $table->string('permalink_url')->nullable();
            $table->timestamp('created_time')->nullable();
            $table->timestamps();

            $table->unique(['facebook_page_id', 'external_post_id']);
            $table->index('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facebook_posts');
    }
};
