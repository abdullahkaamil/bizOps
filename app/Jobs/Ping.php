<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * Trivial queue smoke-test job: records that it ran.
 */
class Ping implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $token = 'pong') {}

    public function handle(): void
    {
        Cache::put('ping', $this->token, 60);
    }
}
