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
        Schema::create('scanned_files', function (Blueprint $table) {
            $table->id();
            $table->string('file_path')->index();
            $table->string('disk');
            $table->string('file_name');
            $table->bigInteger('size');
            $table->string('status');
            $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->timestamp('last_scanned_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scanned_files');
    }
};
