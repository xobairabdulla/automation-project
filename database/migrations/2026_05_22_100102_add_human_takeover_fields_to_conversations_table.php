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
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('human_takeover_reason')->nullable()->after('human_takeover');
            $table->unsignedBigInteger('human_takeover_by')->nullable()->after('human_takeover_reason');
            $table->timestamp('human_takeover_at')->nullable()->after('human_takeover_by');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['human_takeover_reason', 'human_takeover_by', 'human_takeover_at']);
        });
    }
};
