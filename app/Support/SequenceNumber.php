<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class SequenceNumber
{
    /**
     * Generate a clean, always-correctly-formatted sequential reference
     * number like "PO-2026-0001" / "SHP-2026-0001". Counts existing rows
     * for the current year on $table (matched via $column LIKE "{prefix}-{year}-%")
     * and picks the next free suffix, retrying past any gaps so the format
     * never degrades into a random collision suffix.
     */
    public static function generate(string $table, string $column, string $prefix, int $padLength = 4): string
    {
        $year = now()->year;
        $base = "{$prefix}-{$year}-";

        $existing = DB::table($table)
            ->where($column, 'like', $base . '%')
            ->pluck($column);

        $maxSeq = 0;
        foreach ($existing as $value) {
            $tail = substr((string) $value, strlen($base));
            if (preg_match('/^(\d+)/', $tail, $m)) {
                $maxSeq = max($maxSeq, (int) $m[1]);
            }
        }

        $next = $maxSeq + 1;
        $candidate = $base . str_pad((string) $next, $padLength, '0', STR_PAD_LEFT);

        while (DB::table($table)->where($column, $candidate)->exists()) {
            $next++;
            $candidate = $base . str_pad((string) $next, $padLength, '0', STR_PAD_LEFT);
        }

        return $candidate;
    }
}
