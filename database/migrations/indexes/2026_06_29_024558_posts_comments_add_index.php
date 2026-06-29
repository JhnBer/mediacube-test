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
        DB::statement("
            CREATE INDEX IF NOT EXISTS posts_published_at_id_idx
            ON posts
            USING btree (published_at DESC, id DESC)
        ");

        DB::statement("
            CREATE INDEX IF NOT EXISTS comments_post_id_idx
            ON comments
            USING btree (post_id, id)
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DROP INDEX IF EXISTS posts_published_at_id_idx");
        DB::statement("DROP INDEX IF EXISTS comments_post_id_idx");
    }
};
