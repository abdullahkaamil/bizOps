<?php

declare(strict_types=1);

namespace App\Domain\Documents\Support;

use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Settings\TenantSettings;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Everything a generator needs to produce a document: the source record, the
 * acting user, and the tenant's settings (branding + localization).
 */
class DocumentContext
{
    public function __construct(
        public DocumentType $type,
        public Model $related,
        public ?User $actor,
        public TenantSettings $settings,
    ) {}
}
