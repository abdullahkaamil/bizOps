<?php

declare(strict_types=1);

namespace App\Domain\Documents;

use App\Models\DocumentSequence;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Generates the next document number for a type/year, e.g. JOB-2026-000001.
 * Concurrency-safe: the counter row is locked FOR UPDATE inside a transaction
 * so two simultaneous requests can never receive the same number.
 */
class NextDocumentNumberService
{
    public function next(string $type, string $prefix, ?int $year = null, int $padding = 6): string
    {
        $year ??= (int) now()->format('Y');

        return DB::transaction(function () use ($type, $prefix, $year, $padding): string {
            $sequence = $this->lock($type, $year);

            if ($sequence === null) {
                try {
                    DocumentSequence::create([
                        'type' => $type,
                        'year' => $year,
                        'prefix' => $prefix,
                        'current_number' => 0,
                        'padding' => $padding,
                    ]);
                } catch (QueryException) {
                    // Another transaction created the row first; fall through to re-lock.
                }

                $sequence = $this->lock($type, $year);
            }

            $sequence->increment('current_number');

            $number = str_pad((string) $sequence->current_number, $sequence->padding, '0', STR_PAD_LEFT);

            return "{$sequence->prefix}-{$year}-{$number}";
        });
    }

    protected function lock(string $type, int $year): ?DocumentSequence
    {
        return DocumentSequence::query()
            ->where('type', $type)
            ->where('year', $year)
            ->lockForUpdate()
            ->first();
    }
}
