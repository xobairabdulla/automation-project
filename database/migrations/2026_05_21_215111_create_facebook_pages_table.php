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
        Schema::create('facebook_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('facebook_account_id')->constrained('facebook_accounts')->cascadeOnDelete();
            $table->string('page_id')->index();
            $table->string('page_name');
            $table->text('page_access_token_encrypted');
            $table->timestamp('token_expires_at')->nullable();
            $table->boolean('is_connected')->default(false);
            $table->boolean('automation_enabled')->default(false);
            $table->boolean('message_automation_enabled')->default(false);
            $table->boolean('comment_automation_enabled')->default(false);
            $table->timestamp('last_webhook_received_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'page_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facebook_pages');
    }
};
