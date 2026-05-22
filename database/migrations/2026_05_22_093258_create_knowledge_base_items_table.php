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
        Schema::create('knowledge_base_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('knowledge_base_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->enum('category', ['faq', 'product', 'pricing', 'delivery', 'refund', 'contact', 'business_hours', 'restricted_topic', 'other'])->default('faq');
            $table->text('content');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->index(['knowledge_base_id', 'status']);
            $table->index('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('knowledge_base_items');
    }
};
