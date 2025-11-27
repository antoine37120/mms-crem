<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->unsignedInteger('main_items_count')->default(0)->after('title');
            $table->unsignedInteger('secondary_items_count')->default(0)->after('main_items_count');
        });

        // Initialiser les compteurs pour les collections existantes
        $this->seedCounters();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->dropColumn(['main_items_count', 'secondary_items_count']);
        });
    } /**
     * Initialise les compteurs pour les données existantes
     */
    private function seedCounters(): void
    {
        DB::statement("
            UPDATE collections
            SET main_items_count = (
                SELECT COUNT(*) FROM items
                WHERE items.itemable_type = 'App\\\\Models\\\\Collection'
                AND items.itemable_id = collections.id
                AND (items.is_sub = 0 OR items.is_sub IS NULL)
                AND items.deleted_at IS NULL
            )
        ");

        DB::statement("
            UPDATE collections
            SET secondary_items_count = (
                SELECT COUNT(*) FROM items
                WHERE items.itemable_type = 'App\\\\Models\\\\Collection'
                AND items.itemable_id = collections.id
                AND items.is_sub = 1
                AND items.deleted_at IS NULL
            )
        ");
    }
};
