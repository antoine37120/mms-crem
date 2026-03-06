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
        // Supprimer la colonne corpus_id existante
        Schema::table('collections', function (Blueprint $table) {
            if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
                $table->dropForeign(['corpus_id']);
                $table->dropColumn('corpus_id');
            }
        });

        // Créer la table pivot
        Schema::create('collection_corpus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->constrained()->onDelete('cascade');
            $table->foreignId('corpus_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Supprimer la table pivot
        Schema::dropIfExists('collection_corpus');

        // Recréer la colonne corpus_id
        Schema::table('collections', function (Blueprint $table) {
            $table->foreignId('corpus_id')->nullable()->constrained()->onDelete('cascade');
        });
    }
};
