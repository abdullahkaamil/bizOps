<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A per-type (and optionally per-year) document number counter. Lives in the
 * tenant database. Incremented only through App\Domain\Documents\NextDocumentNumberService
 * under a row lock so numbers are never duplicated.
 *
 * @property string $type
 * @property int|null $year
 * @property string $prefix
 * @property int $current_number
 * @property int $padding
 */
class DocumentSequence extends Model
{
    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'current_number' => 'integer',
            'padding' => 'integer',
        ];
    }
}
