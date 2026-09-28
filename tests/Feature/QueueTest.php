<?php

use App\Jobs\Ping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

test('a job can be queued', function () {
    Queue::fake();

    Ping::dispatch();

    Queue::assertPushed(Ping::class);
});

test('a queued job executes and records its effect', function () {
    Cache::forget('ping');

    Ping::dispatch('pong');

    expect(Cache::get('ping'))->toBe('pong');
});
