<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Offline sync groundwork (additive only):
 *  - uuid:    stable identifier, so records created offline on a phone can be
 *             referenced by other offline records before the server assigns ids.
 *  - version: incremented on every update, for detecting conflicting edits.
 * Existing rows are backfilled; nothing is dropped or rewritten.
 */
return new class extends Migration
{
    private const UUID_TABLES = ['mills', 'buyers', 'rice_varieties', 'sample_bundles', 'samples', 'sample_dispatches', 'loadings', 'loading_photos'];

    private const VERSION_TABLES = ['mills', 'buyers', 'rice_varieties', 'sample_bundles', 'samples', 'sample_dispatches', 'loadings'];

    public function up(): void
    {
        foreach (self::UUID_TABLES as $table) {
            if (! Schema::hasColumn($table, 'uuid')) {
                Schema::table($table, fn (Blueprint $t) => $t->uuid('uuid')->nullable()->after('id'));
            }

            DB::table($table)->whereNull('uuid')->orderBy('id')->select('id')
                ->chunkById(500, function ($rows) use ($table) {
                    foreach ($rows as $row) {
                        DB::table($table)->where('id', $row->id)->update(['uuid' => (string) Str::uuid()]);
                    }
                });

            Schema::table($table, fn (Blueprint $t) => $t->unique('uuid'));
        }

        foreach (self::VERSION_TABLES as $table) {
            if (! Schema::hasColumn($table, 'version')) {
                Schema::table($table, fn (Blueprint $t) => $t->unsignedInteger('version')->default(1));
            }
        }
    }

    public function down(): void
    {
        foreach (self::UUID_TABLES as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->dropUnique($table.'_uuid_unique');
                $t->dropColumn('uuid');
            });
        }
        foreach (self::VERSION_TABLES as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('version'));
        }
    }
};
