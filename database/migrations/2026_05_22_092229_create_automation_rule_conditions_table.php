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
        Schema::create('automation_rule_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_rule_id')->constrained()->cascadeOnDelete();
            $table->enum('condition_type', ['keyword_contains', 'exact_match', 'starts_with', 'outside_business_hours', 'first_message']);
            $table->json('keywords_json')->nullable();
            $table->boolean('case_sensitive')->default(false);
            $table->timestamps();

            $table->index('automation_rule_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('automation_rule_conditions');
    }
};
