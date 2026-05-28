<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_clients', function (Blueprint $table) {
            $table->boolean('can_access_not_public')->default(true)->after('token_ttl');
        });
    }

    public function down(): void
    {
        Schema::table('media_clients', function (Blueprint $table) {
            $table->dropColumn('can_access_not_public');
        });
    }
};
