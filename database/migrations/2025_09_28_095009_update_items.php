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
            $table->string('code_prefix')->default('')->after('code');
            $table->string('code_suffix')->default('')->after('code_prefix');

            $table->index('code_prefix');
            $table->index('code_suffix');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

        Schema::table('items', function (Blueprint $table) {
            $table->dropIndex(['code_prefix']);
            $table->dropIndex(['code_suffix']);
            $table->dropColumn('code_prefix');
            $table->dropColumn('code_suffix');
        });
    }
};
