<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $itemTypes = DB::table('item_types')->get();

        foreach ($itemTypes as $itemType) {
            $value = $itemType->allowed_extensions;

            if ($value && ! str_starts_with($value, '[') && ! str_starts_with($value, '{')) {
                // Probablement du CSV, on convertit en tableau JSON
                $array = array_filter(array_map('trim', explode(',', $value)));
                DB::table('item_types')
                    ->where('id', $itemType->id)
                    ->update(['allowed_extensions' => json_encode(array_values($array))]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $itemTypes = DB::table('item_types')->get();

        foreach ($itemTypes as $itemType) {
            $value = $itemType->allowed_extensions;

            if ($value) {
                $decoded = json_decode($value, true);
                if (is_array($decoded)) {
                    DB::table('item_types')
                        ->where('id', $itemType->id)
                        ->update(['allowed_extensions' => implode(',', $decoded)]);
                }
            }
        }
    }
};
