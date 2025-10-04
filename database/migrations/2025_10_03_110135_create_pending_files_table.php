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
        Schema::create('pending_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade')
                ->comment('Utilisateur ayant uploadé');

            $table->string('original_name')
                ->comment('Nom original du fichier');

            $table->string('stored_name')
                ->comment('Nom de stockage temporaire');

            $table->string('file_path')
                ->comment('Chemin de stockage temporaire');

            $table->unsignedBigInteger('file_size')
                ->comment('Taille en octets');

            $table->string('file_type')
                ->comment('MIME type');

            $table->string('file_extension')
                ->comment('Extension extraite');

            $table->enum('upload_status', ['uploading', 'completed', 'failed'])
                ->default('uploading')
                ->comment('Statut de l\'upload');

            $table->string('suggested_code')
                ->nullable()
                ->comment('Cote suggérée si détectée');

            $table->timestamps();

            // Index pour optimiser les requêtes
            $table->index(['user_id', 'upload_status']);
            $table->index('upload_status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pending_files');
    }
};
