<?php

namespace FLAIRUK\Square\Tests;

use FLAIRUK\Square\ClientFactory;
use FLAIRUK\Square\Environment;
use FLAIRUK\Square\Exceptions\ConfigurationException;
use FLAIRUK\Square\Facades\Square;
use FLAIRUK\Square\Square as SquareManager;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Square\Exceptions\SquareApiException;
use Square\Exceptions\SquareException as SdkException;
use Square\Payments\PaymentsClient;
use Square\Payments\Requests\CreatePaymentRequest;
use Square\SquareClient;

class ClientTest extends TestCase
{
    #[Test]
    public function the_client_and_manager_are_singletons_behind_the_facade(): void
    {
        $this->assertSame($this->app->make(SquareManager::class), $this->app->make('square'));
        $this->assertSame($this->app->make(SquareClient::class), $this->app->make(SquareClient::class));
        $this->assertSame($this->app->make(SquareClient::class), Square::client());
        $this->assertInstanceOf(PaymentsClient::class, Square::payments());
        $this->assertSame(Square::client()->payments, Square::payments());
    }

    #[Test]
    public function requests_go_to_the_sandbox_with_the_token_and_version(): void
    {
        $this->fakeSquare(['connect.squareupsandbox.com/v2/locations' => Http::response(['locations' => [$this->location()]])]);

        $locations = Square::locations()->list()->getLocations();

        $this->assertSame('High Street', $locations[0]->getName());
        Http::assertSent(fn (Request $request) => $request->url() === self::SANDBOX.'/v2/locations'
            && $request->method() === 'GET'
            && $request->header('Authorization') === ['Bearer EAAA-test-token']
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $request->header('Square-Version')[0]) === 1);
    }

    #[Test]
    public function production_and_a_pinned_version_are_configurable(): void
    {
        config(['square.environment' => 'production', 'square.version' => '2025-01-23']);
        $this->app->forgetInstance(SquareManager::class);
        $this->app->forgetInstance(SquareClient::class);
        $this->app->forgetInstance(ClientFactory::class);

        $this->fakeSquare(['connect.squareup.com/v2/locations' => Http::response(['locations' => []])]);

        Square::locations()->list();

        $this->assertSame(Environment::Production, Square::environment());
        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://connect.squareup.com/')
            && $request->header('Square-Version') === ['2025-01-23']);
    }

    #[Test]
    public function json_bodies_are_sent_through_laravels_http_client(): void
    {
        $this->fakeSquare(['*/v2/payments' => Http::response(['payment' => ['id' => 'P1', 'status' => 'COMPLETED']])]);

        $response = Square::payments()->create(new CreatePaymentRequest([
            'sourceId' => 'cnon:card-nonce-ok',
            'idempotencyKey' => 'key-1',
            'amountMoney' => Square::money('12.50'),
            'locationId' => Square::locationId(),
        ]));

        $this->assertSame('P1', $response->getPayment()->getId());
        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request['source_id'] === 'cnon:card-nonce-ok'
            && $request['idempotency_key'] === 'key-1'
            && $request['amount_money'] === ['amount' => 1250, 'currency' => 'GBP']
            && $request['location_id'] === 'L123'
            && $request->hasHeader('Content-Type', 'application/json'));
    }

    #[Test]
    public function api_errors_are_the_sdks_exceptions(): void
    {
        $this->fakeSquare(['*' => Http::response(['errors' => [[
            'category' => 'AUTHENTICATION_ERROR', 'code' => 'UNAUTHORIZED', 'detail' => 'This request could not be authorized.',
        ]]], 401)]);

        try {
            Square::locations()->list();
            $this->fail('Expected an exception.');
        } catch (SquareApiException $e) {
            $this->assertSame(401, $e->getStatusCode());
            $this->assertSame('UNAUTHORIZED', $e->getErrors()[0]->getCode());
        }
    }

    #[Test]
    public function connection_failures_become_sdk_exceptions(): void
    {
        Http::fake(fn () => throw new ConnectionException('Could not resolve host'));

        $this->expectException(SdkException::class);
        $this->expectExceptionMessage('Could not resolve host');

        Square::locations()->list();
    }

    #[Test]
    public function server_errors_are_retried_by_the_sdk(): void
    {
        config(['square.retries' => 1]);
        $this->app->forgetInstance(SquareManager::class);
        $this->app->forgetInstance(ClientFactory::class);

        $this->fakeSquare(['*' => Http::sequence()
            ->push(['errors' => []], 503)
            ->push(['locations' => [$this->location()]])]);

        $this->assertCount(1, Square::locations()->list()->getLocations());
        Http::assertSentCount(2);
    }

    #[Test]
    public function for_merchant_uses_another_token(): void
    {
        $this->fakeSquare(['*' => Http::response(['locations' => []])]);

        Square::forMerchant('EAAA-seller-token')->locations()->list();

        Http::assertSent(fn (Request $request) => $request->header('Authorization') === ['Bearer EAAA-seller-token']);
        $this->assertNotSame(Square::client(), Square::forMerchant('EAAA-seller-token')->client());
    }

    #[Test]
    public function a_missing_token_is_a_configuration_error(): void
    {
        config(['square.access_token' => null]);
        $this->app->forgetInstance(SquareManager::class);
        $this->app->forgetInstance(ClientFactory::class);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('SQUARE_ACCESS_TOKEN');

        Square::client();
    }

    #[Test]
    public function an_unknown_environment_is_a_configuration_error(): void
    {
        config(['square.environment' => 'staging']);
        $this->app->forgetInstance(SquareManager::class);
        $this->app->forgetInstance(ClientFactory::class);

        $this->expectException(ConfigurationException::class);

        Square::environment();
    }

    #[Test]
    public function config_values_are_exposed(): void
    {
        $this->assertSame('L123', Square::locationId());
        $this->assertSame('sandbox-sq0idb-app', Square::applicationId());
        $this->assertSame('GBP', Square::currency());
        $this->assertSame(Environment::Sandbox, Square::environment());
        $this->assertSame('https://sandbox.web.squarecdn.com/v1/square.js', Square::environment()->webPaymentsScriptUrl());
    }

    #[Test]
    public function unknown_methods_and_private_sdk_properties_are_rejected(): void
    {
        $this->expectException(\BadMethodCallException::class);

        Square::options();
    }
}
