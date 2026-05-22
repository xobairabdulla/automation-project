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
        Schema::create('automation_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('facebook_page_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('channel', ['message', 'comment', 'both'])->default('both');
            $table->unsignedSmallInteger('priority')->default(10);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['facebook_page_id', 'status', 'priority']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('automation_rules');
    }
};
