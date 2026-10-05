<?php

namespace FLAIRUK\Square\Tests;

use FLAIRUK\Square\ClientFactory;
use FLAIRUK\Square\Exceptions\ConfigurationException;
use FLAIRUK\Square\Square;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;

class CardFormTest extends TestCase
{
    #[Test]
    public function it_renders_the_web_payments_card_field(): void
    {
        $html = Blade::render('<form method="POST"><x-square::card-form class="mb-4" /></form>');

        $this->assertStringContainsString('<script src="https://sandbox.web.squarecdn.com/v1/square.js"></script>', $html);
        $this->assertStringContainsString('<div id="square-card"></div>', $html);
        $this->assertStringContainsString('<input type="hidden" name="source_id" id="square-card-token">', $html);
        $this->assertStringContainsString('class="square-card-form mb-4"', $html);
        $this->assertStringContainsString("window.Square.payments('sandbox-sq0idb-app', 'L123')", $html);
        $this->assertStringContainsString('card.tokenize(null ?? undefined)', $html);
    }

    #[Test]
    public function it_accepts_overrides_and_verification_details(): void
    {
        $html = Blade::render(
            '<x-square::card-form name="nonce" id="checkout-card" application-id="sq0idp-live" location-id="L9" :verification="$verification" />',
            ['verification' => ['amount' => '1.00', 'currencyCode' => 'GBP', 'intent' => 'CHARGE']],
        );

        $this->assertStringContainsString('name="nonce"', $html);
        $this->assertStringContainsString('<div id="checkout-card"></div>', $html);
        $this->assertStringContainsString("window.Square.payments('sq0idp-live', 'L9')", $html);
        $this->assertStringContainsString('\u0022intent\u0022:\u0022CHARGE\u0022', $html);
    }

    #[Test]
    public function production_uses_the_production_script(): void
    {
        config(['square.environment' => 'production']);
        $this->app->forgetInstance(Square::class);
        $this->app->forgetInstance(ClientFactory::class);

        $this->assertStringContainsString('https://web.squarecdn.com/v1/square.js', Blade::render('<x-square::card-form />'));
    }

    #[Test]
    public function it_needs_an_application_id(): void
    {
        config(['square.application_id' => null]);
        $this->app->forgetInstance(Square::class);

        try {
            Blade::render('<x-square::card-form />');
            $this->fail('Expected an exception.');
        } catch (\Throwable $e) {
            $this->assertInstanceOf(ConfigurationException::class, $e->getPrevious() ?? $e);
            $this->assertStringContainsString('SQUARE_APPLICATION_ID', $e->getMessage());
        }
    }
}
