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
        Schema::table('customers', function (Blueprint $table) {
            $table->string('facebook_first_name')->nullable()->after('name');
            $table->string('facebook_last_name')->nullable()->after('facebook_first_name');
            $table->string('facebook_locale', 20)->nullable()->after('facebook_last_name');
            $table->smallInteger('facebook_timezone')->nullable()->after('facebook_locale');
            $table->timestamp('profile_synced_at')->nullable()->after('facebook_timezone');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'facebook_first_name',
                'facebook_last_name',
                'facebook_locale',
                'facebook_timezone',
                'profile_synced_at',
            ]);
        });
    }
};
