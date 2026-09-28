<?php

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;

// Ensure routes/console.php (which registers the schedule) is loaded.
beforeEach(function () {
    app(Kernel::class)->bootstrap();
});

test('tenant fan-out schedules take an overlap lock to prevent duplicate runs', function () {
    $events = collect(app(Schedule::class)->events());

    foreach (['tasks:notify-due-soon', 'quotations:expire', 'tenants:backup'] as $command) {
        $event = $events->first(fn (Event $e): bool => str_contains((string) $e->command, $command));

        expect($event)->not->toBeNull("schedule for {$command} should exist")
            ->and($event->withoutOverlapping)->toBeTrue("{$command} must be withoutOverlapping")
            ->and($event->onOneServer)->toBeTrue("{$command} must run onOneServer");
    }
});
