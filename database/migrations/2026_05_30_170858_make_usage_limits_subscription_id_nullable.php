<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usage_limits', function (Blueprint $table) {
            $table->unsignedBigInteger('subscription_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('usage_limits', function (Blueprint $table) {
            $table->unsignedBigInteger('subscription_id')->nullable(false)->change();
        });
    }
};
