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
        Schema::create('webhook_event_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_event_id')->constrained('webhook_events')->cascadeOnDelete();
            $table->string('status', 30)->index();
            $table->string('message');
            $table->json('metadata_json')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_event_logs');
    }
};
