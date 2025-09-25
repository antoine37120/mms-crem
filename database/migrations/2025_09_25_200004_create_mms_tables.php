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
        // Table fonds
        Schema::create('fonds', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique()->comment('Ex: CNRSMH_Arnaud');
            $table->string('title')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->index(['code']);
            $table->index(['created_by']);
        });

        // Table corpuses
        Schema::create('corpuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fond_id')->constrained('fonds')->onDelete('cascade');
            $table->string('code')->unique()->comment('Ex: CNRSMH_Arnaud_001');
            $table->string('title')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->index(['fond_id']);
            $table->index(['code']);
            $table->index(['created_by']);
        });

        // Table collections
        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('corpus_id')->constrained('corpuses')->onDelete('cascade');
            $table->string('code')->unique()->comment('Ex: CNRSMH_I_2011_001');
            $table->string('title')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->index(['corpus_id']);
            $table->index(['code']);
            $table->index(['created_by']);
        });

        // Table item_types
        Schema::create('item_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Traduction, Transcription, Livret, etc.');
            $table->string('suffix')->comment('_TRA, _TRS, _livret, etc.');
            $table->text('description')->nullable();
            $table->boolean('requires_language')->default(false)->comment('Pour TRA/TRS');
            $table->json('allowed_extensions')->nullable()->comment('["pdf", "txt", "docx"]');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->index(['name']);
            $table->index(['suffix']);
            $table->index(['is_active']);
            $table->index(['created_by']);
        });

        // Table items
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('itemable_type')->comment('Polymorphique: Fond, Corpus, Collection, Item');
            $table->unsignedBigInteger('itemable_id')->comment('ID de l\'entité parente');
            $table->foreignId('item_type_id')->nullable()->constrained('item_types')->onDelete('set null')->comment('NULL pour items principaux, requis pour secondaires');
            $table->string('code')->unique()->comment('Ex: CNRSMH_I_2011_001_001_001_TRA_en');
            $table->string('title')->nullable();
            $table->string('language_code', 10)->nullable()->comment('fr, en, etc.');
            $table->string('file_path')->comment('Chemin du fichier');
            $table->string('file_name')->comment('Nom original du fichier');
            $table->unsignedBigInteger('file_size')->comment('Taille en bytes');
            $table->string('file_type')->comment('MIME type');
            $table->string('file_extension', 10)->comment('wav, mp4, pdf, etc.');
            $table->unsignedInteger('duration')->nullable()->comment('Durée en secondes pour audio/vidéo');
            $table->date('upload_date')->comment('Date de dépôt');
            $table->foreignId('uploaded_by')->constrained('users')->onDelete('cascade')->comment('Déposant');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            // Index polymorphique
            $table->index(['itemable_type', 'itemable_id']);
            $table->index(['item_type_id']);
            $table->index(['code']);
            $table->index(['file_type']);
            $table->index(['file_extension']);
            $table->index(['upload_date']);
            $table->index(['uploaded_by']);
            $table->index(['created_by']);
            $table->index(['language_code']);

            // Index composites pour les requêtes fréquentes
            $table->index(['itemable_type', 'itemable_id', 'item_type_id']);
            $table->index(['file_type', 'upload_date']);
            $table->index(['uploaded_by', 'upload_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
        Schema::dropIfExists('item_types');
        Schema::dropIfExists('collections');
        Schema::dropIfExists('corpuses');
        Schema::dropIfExists('fonds');
    }

};
