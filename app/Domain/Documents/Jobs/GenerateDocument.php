<?php

declare(strict_types=1);

namespace App\Domain\Documents\Jobs;

use App\Domain\Documents\DocumentService;
use App\Domain\Documents\Enums\DocumentType;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Generates a document off the request cycle (for large PDFs). Runs inside the
 * correct tenant via stancl's QueueTenancyBootstrapper.
 *
 * @template TModel of Model
 */
class GenerateDocument implements ShouldQueue
{
    use Queueable;

    /**
     * @param  class-string<Model>  $relatedType
     */
    public function __construct(
        public string $type,
        public string $relatedType,
        public int $relatedId,
        public ?int $actorId = null,
    ) {}

    public function handle(DocumentService $service): void
    {
        $related = $this->relatedType::find($this->relatedId);

        if ($related === null) {
            return;
        }

        $actor = $this->actorId !== null ? User::find($this->actorId) : null;

        $service->generate(DocumentType::from($this->type), $related, $actor);
    }
}
