<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usage_limits', function (Blueprint $table) {
            $table->unsignedInteger('image_send_limit')->default(0)->after('ai_reply_used');
            $table->unsignedInteger('image_send_used')->default(0)->after('image_send_limit');
        });

        Schema::table('limit_extension_packages', function (Blueprint $table) {
            $table->string('description')->nullable()->after('name');
            $table->unsignedInteger('image_send_extra')->default(0)->after('ai_extra');
        });
    }

    public function down(): void
    {
        Schema::table('usage_limits', function (Blueprint $table) {
            $table->dropColumn(['image_send_limit', 'image_send_used']);
        });

        Schema::table('limit_extension_packages', function (Blueprint $table) {
            $table->dropColumn(['image_send_extra', 'description']);
        });
    }
};
