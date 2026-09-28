<?php

// Central-context suite (RefreshDatabase). Covers the deploy-time operational
// commands: environment validation and the post-deploy health check.

test('the health check passes when backing services are reachable', function () {
    $this->artisan('app:health-check')
        ->assertExitCode(0);
});

test('the health check reports json', function () {
    $this->artisan('app:health-check --json')->assertExitCode(0);
});

test('environment validation passes for a well-formed environment', function () {
    $this->artisan('app:validate-env')
        ->assertExitCode(0);
});

test('environment validation fails when a required key is missing', function () {
    config(['app.key' => null]);

    $this->artisan('app:validate-env')
        ->assertExitCode(1);
});

test('environment validation enforces production-safe settings', function () {
    // Pretend we are booting a production release with an unsafe config.
    $this->app['env'] = 'production';
    config([
        'app.debug' => true,          // must be false in production
        'app.url' => 'http://insecure.example.com', // must be https
        'session.driver' => 'array',  // must be persistent
        'cache.default' => 'array',   // must be shared
        'queue.default' => 'sync',    // must not be sync
    ]);

    $this->artisan('app:validate-env')
        ->assertExitCode(1);
});

test('environment validation accepts a production-safe config', function () {
    $this->app['env'] = 'production';
    config([
        'app.debug' => false,
        'app.url' => 'https://example.com',
        'session.driver' => 'database',
        'cache.default' => 'redis',
        'queue.default' => 'database',
        'queue.connections.database.connection' => 'pgsql',
    ]);

    $this->artisan('app:validate-env')
        ->assertExitCode(0);
});
