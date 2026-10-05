<?php

namespace FLAIRUK\Square\Tests;

use FLAIRUK\Square\Facades\Square;
use FLAIRUK\Square\SquareServiceProvider;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected const SANDBOX = 'https://connect.squareupsandbox.com';

    protected const WEBHOOK_KEY = 'test-signature-key';

    protected const WEBHOOK_URL = 'https://shop.example.com/square/webhook';

    protected function getPackageProviders($app): array
    {
        return [SquareServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['Square' => Square::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('square.access_token', 'EAAA-test-token');
        $app['config']->set('square.environment', 'sandbox');
        $app['config']->set('square.location_id', 'L123');
        $app['config']->set('square.application_id', 'sandbox-sq0idb-app');
        $app['config']->set('square.application_secret', 'sandbox-sq0csb-secret');
        $app['config']->set('square.currency', 'GBP');
        $app['config']->set('square.retries', 0);
        $app['config']->set('square.webhooks.signature_key', self::WEBHOOK_KEY);
        $app['config']->set('square.webhooks.url', self::WEBHOOK_URL);
        $app['config']->set('square.webhooks.path', 'square/webhook');
    }

    protected function fakeSquare(array $responses = []): void
    {
        Http::preventStrayRequests();
        Http::fake($responses);
    }

    /**
     * @return array<string, mixed>
     */
    protected function location(string $id = 'L123', string $name = 'High Street'): array
    {
        return ['id' => $id, 'name' => $name, 'status' => 'ACTIVE', 'currency' => 'GBP'];
    }
}
