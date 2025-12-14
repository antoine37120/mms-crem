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
        Schema::table('pending_files', function (Blueprint $table) {
            $table->string('client_signature')->nullable()->after('suggested_code')->comment('MD5 signature calculated by client');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pending_files', function (Blueprint $table) {
            $table->dropColumn('client_signature');
        });
    }
};
