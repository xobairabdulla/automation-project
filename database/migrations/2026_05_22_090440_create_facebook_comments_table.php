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
        Schema::create('facebook_comments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('facebook_page_id');
            $table->unsignedBigInteger('facebook_post_id');
            $table->string('external_comment_id')->index();
            $table->string('commenter_id')->nullable();
            $table->string('commenter_name')->nullable();
            $table->text('comment_text')->nullable();
            $table->string('status')->default('new');
            $table->timestamp('created_time')->nullable();
            $table->timestamps();

            $table->unique(['facebook_page_id', 'external_comment_id']);
            $table->index('tenant_id');
            $table->index('facebook_post_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facebook_comments');
    }
};
