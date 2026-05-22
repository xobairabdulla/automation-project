<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->index('owner_id');
            $table->index('status');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('status');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('action');
            $table->index('entity_type');
            $table->index('created_at');
        });

        Schema::table('facebook_pages', function (Blueprint $table) {
            $table->index('tenant_id');
            $table->index('user_id');
            $table->index('is_connected');
        });

        Schema::table('facebook_accounts', function (Blueprint $table) {
            $table->index('tenant_id');
            $table->index('user_id');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['owner_id']);
            $table->dropIndex(['status']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['status']);
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['action']);
            $table->dropIndex(['entity_type']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('facebook_pages', function (Blueprint $table) {
            $table->dropIndex(['tenant_id']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['is_connected']);
        });

        Schema::table('facebook_accounts', function (Blueprint $table) {
            $table->dropIndex(['tenant_id']);
            $table->dropIndex(['user_id']);
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['notifiable_type', 'notifiable_id', 'read_at']);
        });
    }
};
