<?php

use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_posts_search
            ON posts
            USING gin ((lower(title || ' ' || body)) gin_trgm_ops)
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_posts_search');
    }
};
