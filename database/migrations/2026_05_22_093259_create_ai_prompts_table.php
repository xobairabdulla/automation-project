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
        Schema::create('ai_prompts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('facebook_page_id')->constrained()->cascadeOnDelete();
            $table->string('preferred_language', 10)->default('en');
            $table->enum('tone', ['friendly', 'professional', 'casual'])->default('friendly');
            $table->enum('reply_length', ['short', 'medium', 'long'])->default('short');
            $table->boolean('use_emoji')->default(false);
            $table->enum('unknown_answer_action', ['human_handover', 'send_default'])->default('human_handover');
            $table->text('restricted_instructions')->nullable();
            $table->timestamps();

            $table->unique('facebook_page_id');
            $table->index('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_prompts');
    }
};
