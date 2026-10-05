<?php

namespace FLAIRUK\Square\Tests;

use FLAIRUK\Square\Events\WebhookReceived;
use FLAIRUK\Square\Facades\Square;
use FLAIRUK\Square\Webhooks\WebhookEvent;
use FLAIRUK\Square\Webhooks\WebhookSignature;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Square\Types\PaymentCreatedEvent;
use Square\Utils\WebhooksHelper;

class WebhookTest extends TestCase
{
    protected function paymentCreated(string $eventId = 'evt-1'): string
    {
        return json_encode([
            'merchant_id' => 'M1',
            'type' => 'payment.created',
            'event_id' => $eventId,
            'created_at' => '2026-10-05T10:00:00.000Z',
            'data' => [
                'type' => 'payment',
                'id' => 'P1',
                'object' => ['payment' => [
                    'id' => 'P1',
                    'amount_money' => ['amount' => 1250, 'currency' => 'GBP'],
                    'status' => 'APPROVED',
                ]],
            ],
        ]);
    }

    protected function postWebhook(string $body, ?string $signature, string $uri = '/square/webhook')
    {
        $headers = ['CONTENT_TYPE' => 'application/json'];

        if ($signature !== null) {
            $headers['HTTP_X_SQUARE_HMACSHA256_SIGNATURE'] = $signature;
        }

        return $this->call('POST', $uri, [], [], [], $headers, $body);
    }

    protected function assertNoSquareEvents(): void
    {
        Event::assertNotDispatched(WebhookReceived::class);
        Event::assertNotDispatched('square.payment.created');
    }

    protected function sign(string $body, string $url = self::WEBHOOK_URL, string $key = self::WEBHOOK_KEY): string
    {
        return base64_encode(hash_hmac('sha256', $url.$body, $key, true));
    }

    #[Test]
    public function the_signature_matches_squares_documented_scheme_and_the_sdk_helper(): void
    {
        $body = $this->paymentCreated();
        $signature = Square::webhookSignature()->sign($body);

        $this->assertSame($this->sign($body), $signature);
        $this->assertTrue(WebhooksHelper::verifySignature($body, $signature, self::WEBHOOK_KEY, self::WEBHOOK_URL));
        $this->assertTrue(Square::webhookSignature()->verify($body, $signature));
        $this->assertFalse(Square::webhookSignature()->verify($body.' ', $signature));
        $this->assertFalse(Square::webhookSignature()->verify($body, $signature, 'https://other.example.com/hook'));
        $this->assertFalse(Square::webhookSignature()->verify($body, null));
        $this->assertFalse(Square::webhookSignature()->verify('', $this->sign('')));
    }

    #[Test]
    public function a_valid_webhook_dispatches_the_generic_and_typed_events(): void
    {
        Event::fake();
        $body = $this->paymentCreated();

        $this->postWebhook($body, $this->sign($body))->assertOk();

        Event::assertDispatched(WebhookReceived::class, fn (WebhookReceived $received) => $received->event->type === 'payment.created'
            && $received->event->eventId === 'evt-1'
            && $received->event->merchantId === 'M1');

        Event::assertDispatched('square.payment.created', fn (string $name, array $payload) => $payload[0] instanceof WebhookEvent
            && $payload[0]->object('amount_money.amount') === 1250);
    }

    #[Test]
    public function listeners_receive_the_webhook_event(): void
    {
        $received = [];
        Event::listen('square.payment.created', function (WebhookEvent $event) use (&$received) {
            $received[] = $event;
        });

        $body = $this->paymentCreated();
        $this->postWebhook($body, $this->sign($body))->assertOk();

        $this->assertCount(1, $received);
        $this->assertSame('payment', $received[0]->objectType());
        $this->assertSame('P1', $received[0]->objectId());
        $this->assertSame('APPROVED', $received[0]->object('status'));
        $this->assertSame('2026-10-05T10:00:00.000Z', $received[0]->createdAt);
        $this->assertSame(PaymentCreatedEvent::class, $received[0]->sdkEventClass());
        $this->assertSame('P1', $received[0]->toSdkEvent()->getData()->getObject()->getPayment()->getId());
    }

    #[Test]
    public function an_invalid_signature_is_rejected(): void
    {
        Event::fake();
        $body = $this->paymentCreated();

        $this->postWebhook($body, $this->sign($body, key: 'wrong-key'))->assertForbidden();
        $this->postWebhook($body, $this->sign($body, url: 'https://attacker.example.com/square/webhook'))->assertForbidden();
        $this->postWebhook(str_replace('1250', '1', $body), $this->sign($body))->assertForbidden();

        $this->assertNoSquareEvents();
    }

    #[Test]
    public function a_missing_signature_is_rejected(): void
    {
        Event::fake();

        $this->postWebhook($this->paymentCreated(), null)->assertForbidden();
        $this->postWebhook($this->paymentCreated(), '')->assertForbidden();

        $this->assertNoSquareEvents();
    }

    #[Test]
    public function a_missing_signature_key_is_a_server_error_not_a_pass(): void
    {
        Event::fake();
        config(['square.webhooks.signature_key' => null]);
        $this->app->forgetInstance(\FLAIRUK\Square\Square::class);
        $body = $this->paymentCreated();

        $this->postWebhook($body, $this->sign($body))->assertStatus(500);

        $this->assertNoSquareEvents();
    }

    #[Test]
    public function without_a_configured_url_the_request_url_is_signed(): void
    {
        Event::fake();
        config(['square.webhooks.url' => null]);
        $this->app->forgetInstance(\FLAIRUK\Square\Square::class);
        $body = $this->paymentCreated();

        $this->postWebhook($body, $this->sign($body, url: 'http://localhost/square/webhook'))->assertOk();

        Event::assertDispatched(WebhookReceived::class);
    }

    #[Test]
    public function repeated_events_are_acknowledged_but_dispatched_once(): void
    {
        Event::fake();
        $body = $this->paymentCreated('evt-repeat');

        $this->postWebhook($body, $this->sign($body))->assertOk();
        $this->postWebhook($body, $this->sign($body))->assertOk();

        Event::assertDispatchedTimes(WebhookReceived::class, 1);
    }

    #[Test]
    public function deduplication_can_be_disabled(): void
    {
        Event::fake();
        config(['square.webhooks.deduplicate' => false]);
        $body = $this->paymentCreated('evt-again');

        $this->postWebhook($body, $this->sign($body))->assertOk();
        $this->postWebhook($body, $this->sign($body))->assertOk();

        Event::assertDispatchedTimes(WebhookReceived::class, 2);
    }

    #[Test]
    public function a_failed_listener_lets_square_retry(): void
    {
        $body = $this->paymentCreated('evt-fails');
        $attempts = 0;
        Event::listen('square.payment.created', function () use (&$attempts) {
            if (++$attempts === 1) {
                throw new \RuntimeException('Database down');
            }
        });

        $this->withoutExceptionHandling();

        try {
            $this->postWebhook($body, $this->sign($body));
        } catch (\RuntimeException) {
        }

        $this->postWebhook($body, $this->sign($body))->assertOk();
        $this->assertSame(2, $attempts);
    }

    #[Test]
    public function a_signed_body_that_is_not_an_event_is_a_bad_request(): void
    {
        Event::fake();
        $body = '{"hello":"world"}';

        $this->postWebhook($body, $this->sign($body))->assertStatus(400);

        $this->assertNoSquareEvents();
    }

    #[Test]
    public function the_middleware_can_guard_your_own_route(): void
    {
        Route::post('my/square/hook', fn () => 'handled')->middleware('square.webhook');
        $body = $this->paymentCreated();

        $this->postWebhook($body, $this->sign($body), '/my/square/hook')->assertOk()->assertSee('handled');
        $this->postWebhook($body, 'bad', '/my/square/hook')->assertForbidden();
    }

    #[Test]
    public function the_route_is_named(): void
    {
        $this->assertSame('http://localhost/square/webhook', route('square.webhook'));
        $this->assertTrue(Route::has('square.webhook'));
    }

    #[Test]
    public function the_signature_class_is_bound(): void
    {
        $this->assertInstanceOf(WebhookSignature::class, $this->app->make(WebhookSignature::class));
    }
}
