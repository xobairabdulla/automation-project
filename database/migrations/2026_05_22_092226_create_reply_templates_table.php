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
        Schema::create('reply_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('facebook_page_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('channel', ['message', 'comment', 'both'])->default('both');
            $table->string('language', 10)->default('en');
            $table->text('body');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['facebook_page_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reply_templates');
    }
};
