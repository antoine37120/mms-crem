<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $defaultItem = config('mms.access.defaults.item', 'restricted');
        $defaultFond = config('mms.access.defaults.fond', 'full');
        $defaultCorpus = config('mms.access.defaults.corpus', 'full');
        $defaultCollection = config('mms.access.defaults.collection', 'restricted');

        Schema::table('items', function (Blueprint $table) use ($defaultItem) {
            $table->string('public_access', 16)->default($defaultItem)->after('md5');
        });
        Schema::table('fonds', function (Blueprint $table) use ($defaultFond) {
            $table->string('public_access', 16)->default($defaultFond)->after('title');
        });
        Schema::table('corpuses', function (Blueprint $table) use ($defaultCorpus) {
            $table->string('public_access', 16)->default($defaultCorpus)->after('title');
        });
        Schema::table('collections', function (Blueprint $table) use ($defaultCollection) {
            $table->string('public_access', 16)->default($defaultCollection)->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('items', fn (Blueprint $t) => $t->dropColumn('public_access'));
        Schema::table('fonds', fn (Blueprint $t) => $t->dropColumn('public_access'));
        Schema::table('corpuses', fn (Blueprint $t) => $t->dropColumn('public_access'));
        Schema::table('collections', fn (Blueprint $t) => $t->dropColumn('public_access'));
    }
};
