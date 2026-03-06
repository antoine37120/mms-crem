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
        Schema::table('corpuses', function (Blueprint $table) {
            // Supprimer la colonne fond_id existante
            if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
                $table->dropForeign(['fond_id']);
                $table->dropColumn('fond_id');
            }
        });

        // Créer une table pivot pour la relation many-to-many
        Schema::create('corpus_fond', function (Blueprint $table) {
            $table->id();
            $table->foreignId('corpus_id')->constrained()->onDelete('cascade');
            $table->foreignId('fond_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            // Index pour les performances
            $table->unique(['corpus_id', 'fond_id']);
        });
    }

    public function down(): void
    {
        // Supprimer la table pivot
        Schema::dropIfExists('corpus_fond');

        // Recréer la colonne fond_id
        Schema::table('corpuses', function (Blueprint $table) {
            $table->foreignId('fond_id')->constrained()->onDelete('cascade');
        });
    }
};
