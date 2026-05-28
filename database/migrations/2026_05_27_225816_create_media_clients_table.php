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
        Schema::create('media_clients', function (Blueprint $table) {
            $table->id();
            $table->string('app_id')->unique();
            $table->string('name');
            $table->text('encrypted_secret');
            $table->text('encrypted_secret_previous')->nullable();
            $table->timestamp('previous_expires_at')->nullable();
            $table->json('allowed_origins');
            $table->integer('token_ttl')->default(3600);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_clients');
    }
};
