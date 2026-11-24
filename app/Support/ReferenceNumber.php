<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
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

    /**
     * The number typed in a "from / to" box: "255", "0255" or "SMP-0255" all mean 255.
     */
    public static function parse(mixed $input): ?int
    {
        if (! is_scalar($input) || ! preg_match('/(\d+)\s*$/', trim((string) $input), $m)) {
            return null;
        }

        return (int) $m[1];
    }

    /** "SMP-0255" for display. */
    public static function format(string $type, int $number): string
    {
        return sprintf('%s-%04d', config("rice.prefixes.{$type}"), $number);
    }

    /**
     * Limit a query to reference numbers from / to (inclusive), comparing the
     * numeric part so SMP-0999 < SMP-1000 < SMP-10000.
     */
    public static function whereBetween(Builder $query, string $column, ?int $from, ?int $to): void
    {
        if ($from === null && $to === null) {
            return;
        }

        $column = $query->getModel()->qualifyColumn($column);
        $number = match ($query->getConnection()->getDriverName()) {
            'sqlite' => "CAST(substr({$column}, instr({$column}, '-') + 1) AS INTEGER)",
            'pgsql' => "CAST(split_part({$column}, '-', 2) AS INTEGER)",
            default => "CAST(SUBSTRING_INDEX({$column}, '-', -1) AS UNSIGNED)",
        };

        $query->when($from !== null, fn (Builder $q) => $q->whereRaw("{$number} >= ?", [$from]))
            ->when($to !== null, fn (Builder $q) => $q->whereRaw("{$number} <= ?", [$to]));
    }

    /** "SMP-0255 – SMP-0290", "from SMP-0255", "up to SMP-0290" or "All", for printed lists. */
    public static function rangeLabel(string $type, ?int $from, ?int $to): string
    {
        return match (true) {
            $from !== null && $to !== null => self::format($type, $from).' – '.self::format($type, $to),
            $from !== null => 'from '.self::format($type, $from),
            $to !== null => 'up to '.self::format($type, $to),
            default => 'All',
        };
    }

    /** Series with a configured start (e.g. samples from 255) never go below it. */
    private static function floor(string $type, int $value): int
    {
        return max($value, (int) config("rice.number_start.{$type}", 1) - 1);
    }
}
