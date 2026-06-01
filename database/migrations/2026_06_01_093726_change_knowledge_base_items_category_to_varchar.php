<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Convert enum to varchar so new categories can be added without future migrations
        DB::statement("ALTER TABLE knowledge_base_items MODIFY COLUMN category VARCHAR(50) NOT NULL DEFAULT 'faq'");
    }

    public function down(): void
    {
        $enumValues = "'faq','product','pricing','delivery','refund','contact','business_hours','restricted_topic','other','payment','order','complaint','support','instruction'";
        DB::statement("ALTER TABLE knowledge_base_items MODIFY COLUMN category ENUM({$enumValues}) NOT NULL DEFAULT 'faq'");
    }
};
