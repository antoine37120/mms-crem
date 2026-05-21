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
        Schema::table('items', function (Blueprint $table) {
            // Supprimer la contrainte unique existante sur la colonne 'code'
            $table->dropUnique(['code']);

            // Ajouter une contrainte unique composite sur code + file_extension
            $table->unique(['code', 'file_extension'], 'items_code_file_extension_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            // Supprimer la contrainte unique composite
            $table->dropUnique('items_code_file_extension_unique');

            // Remettre la contrainte unique sur la colonne 'code' seule
            $table->unique('code');
        });
    }
};
