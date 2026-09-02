<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rend file_name nullable sur items, pour cohérence avec file_path
     * (nullable depuis 2025_10_21) : une fiche importée sans fichier
     * (import Telemeta) n'a ni file_path ni file_name.
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->string('file_name')
                ->nullable()
                ->comment('Nom original du fichier')
                ->change();
        });
    }

    public function down(): void
    {
        // Remise en NOT NULL impossible sans combler les NULL existants
        DB::table('items')->whereNull('file_name')->update(['file_name' => '']);

        Schema::table('items', function (Blueprint $table) {
            $table->string('file_name')
                ->comment('Nom original du fichier')
                ->change();
        });
    }
};
