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
        Schema::create('automation_rule_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_rule_id')->constrained()->cascadeOnDelete();
            $table->enum('action_type', ['send_text', 'send_template', 'human_handover', 'mark_as_lead', 'do_nothing']);
            $table->text('reply_text')->nullable();
            $table->foreignId('reply_template_id')->nullable()->constrained()->nullOnDelete();
            $table->json('metadata_json')->nullable();
            $table->timestamps();

            $table->index('automation_rule_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('automation_rule_actions');
    }
};
