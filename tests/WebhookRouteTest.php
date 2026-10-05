<?php

namespace FLAIRUK\Square\Tests;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;

class WebhookRouteTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('square.webhooks.path', null);
    }

    #[Test]
    public function the_webhook_route_is_opt_in(): void
    {
        $this->assertFalse(Route::has('square.webhook'));

        $this->post('/square/webhook', [], ['X-Square-HmacSha256-Signature' => 'x'])->assertNotFound();
    }

    #[Test]
    public function the_middleware_alias_is_always_registered(): void
    {
        $this->assertArrayHasKey('square.webhook', $this->app['router']->getMiddleware());
    }
}
