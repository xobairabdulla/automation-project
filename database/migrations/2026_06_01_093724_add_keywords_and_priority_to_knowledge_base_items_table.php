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
        Schema::table('knowledge_base_items', function (Blueprint $table) {
            $table->json('keywords')->nullable()->after('content');
            $table->unsignedTinyInteger('priority')->default(0)->after('keywords');
        });
    }

    public function down(): void
    {
        Schema::table('knowledge_base_items', function (Blueprint $table) {
            $table->dropColumn(['keywords', 'priority']);
        });
    }
};
