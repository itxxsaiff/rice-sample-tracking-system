<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Generates sequential, human-friendly reference numbers such as SMP-0001.
 * The counter row is locked for the duration of the surrounding transaction,
 * and numbers already taken (e.g. typed manually) are skipped.
 */
class ReferenceNumber
{
    /**
     * @param  class-string<Model>  $model
     */
    public static function next(string $type, string $model, string $column): string
    {
        $prefix = config("rice.prefixes.{$type}");

        return DB::transaction(function () use ($type, $prefix, $model, $column) {
            DB::table('reference_sequences')->insertOrIgnore(['key' => $type, 'value' => 0]);
            $value = self::floor($type, (int) DB::table('reference_sequences')->where('key', $type)->lockForUpdate()->value('value'));

            do {
                $value++;
                $candidate = sprintf('%s-%04d', $prefix, $value);
            } while ($model::query()->where($column, $candidate)->exists());

            DB::table('reference_sequences')->where('key', $type)->update(['value' => $value]);

            return $candidate;
        });
    }

    /** A preview of the next number for form placeholders (not reserved). */
    public static function peek(string $type): string
    {
        $value = self::floor($type, (int) DB::table('reference_sequences')->where('key', $type)->value('value'));

        return sprintf('%s-%04d', config("rice.prefixes.{$type}"), $value + 1);
    }

    /** Series with a configured start (e.g. samples from 255) never go below it. */
    private static function floor(string $type, int $value): int
    {
        return max($value, (int) config("rice.number_start.{$type}", 1) - 1);
    }
}
