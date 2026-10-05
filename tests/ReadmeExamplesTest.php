<?php

namespace FLAIRUK\Square\Tests;

use FLAIRUK\Square\Facades\Square;
use FLAIRUK\Square\Support\Money;
use FLAIRUK\Square\Webhooks\WebhookEvent;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Square\Customers\Requests\CreateCustomerRequest;
use Square\Refunds\Requests\RefundPaymentRequest;
use Square\Types\PaymentUpdatedEvent;

/**
 * The examples in README.md, run against faked responses.
 */
class ReadmeExamplesTest extends TestCase
{
    #[Test]
    public function every_square_api_examples(): void
    {
        $this->fakeSquare([
            '*/v2/customers*' => fn (Request $request) => $request->method() === 'POST'
                ? Http::response(['customer' => ['id' => 'C3', 'given_name' => 'Jane']])
                : Http::response(['customers' => [['id' => 'C1'], ['id' => 'C2']]]),
            '*/v2/refunds' => Http::response(['refund' => ['id' => 'R1', 'status' => 'PENDING', 'amount_money' => ['amount' => 500, 'currency' => 'GBP']]]),
            '*/v2/payments*' => Http::response(['payments' => [['id' => 'P1', 'amount_money' => ['amount' => 1250, 'currency' => 'GBP']]]]),
        ]);

        $customer = Square::customers()->create(new CreateCustomerRequest([
            'idempotencyKey' => Square::idempotencyKey(),
            'givenName' => 'Jane',
            'emailAddress' => 'jane@example.com',
        ]))->getCustomer();
        $this->assertSame('C3', $customer->getId());

        $ids = [];
        foreach (Square::customers()->list() as $listed) {
            $ids[] = $listed->getId();
        }
        $this->assertSame(['C1', 'C2'], $ids);

        $refund = Square::refunds()->refundPayment(new RefundPaymentRequest([
            'idempotencyKey' => Square::idempotencyKey('refund', 'P1'),
            'amountMoney' => Square::money('5.00', 'GBP'),
            'paymentId' => 'P1',
        ]))->getRefund();
        $this->assertSame('R1', $refund->getId());

        $payments = iterator_to_array(Square::forMerchant('EAAA-seller')->payments()->list(), false);
        $this->assertSame('12.50', Money::toDecimal($payments[0]->getAmountMoney()));

        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/v2/payments')
            && $request->header('Authorization') === ['Bearer EAAA-seller']);
    }

    #[Test]
    public function webhook_testing_example(): void
    {
        $received = null;
        Event::listen('square.payment.updated', function (WebhookEvent $event) use (&$received) {
            $received = $event;
        });

        $body = json_encode(['type' => 'payment.updated', 'event_id' => 'evt-1', 'merchant_id' => 'M1', 'data' => [
            'type' => 'payment', 'id' => 'P1', 'object' => ['payment' => ['id' => 'P1', 'status' => 'COMPLETED']],
        ]]);

        $this->call('POST', '/square/webhook', server: [
            'HTTP_X_SQUARE_HMACSHA256_SIGNATURE' => Square::webhookSignature()->sign($body),
        ], content: $body)->assertOk();

        $this->assertSame('COMPLETED', $received->object('status'));
        $this->assertInstanceOf(PaymentUpdatedEvent::class, $received->toSdkEvent());
        $this->assertTrue(Square::webhookSignature()->verify($body, Square::webhookSignature()->sign($body)));
    }
}
