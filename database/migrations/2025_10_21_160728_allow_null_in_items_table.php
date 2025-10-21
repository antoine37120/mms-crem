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
            // Autoriser les valeurs NULL pour les champs spécifiés
            $table->string('file_path')->nullable()->change();
            $table->integer('file_size')->nullable()->change();
            $table->string('file_extension')->nullable()->change();
            $table->string('file_type')->nullable()->change();
            $table->unsignedBigInteger('uploaded_by')->nullable()->change();
            $table->date('upload_date')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            // Revenir à la configuration précédente
            $table->string('file_path')->nullable(false)->change();
            $table->integer('file_size')->nullable(false)->change();
            $table->string('file_extension')->nullable(false)->change();
            $table->string('file_type')->nullable(false)->change();
            $table->unsignedBigInteger('uploaded_by')->nullable(false)->change();
            $table->date('upload_date')->nullable(false)->change();
        });
    }
};
