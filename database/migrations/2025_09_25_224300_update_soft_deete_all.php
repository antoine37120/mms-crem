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
        // Ajouter deleted_at aux fonds
        Schema::table('fonds', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Ajouter deleted_at aux corpuses
        Schema::table('corpuses', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Ajouter deleted_at aux collections
        Schema::table('collections', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Ajouter deleted_at aux item_types
        Schema::table('item_types', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Ajouter deleted_at aux items
        Schema::table('items', function (Blueprint $table) {
            $table->softDeletes();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fonds', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('corpuses', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('collections', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('item_types', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('items', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

    }
};
