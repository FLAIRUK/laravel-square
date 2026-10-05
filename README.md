<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="art/logo-dark.svg">
    <img src="art/logo-light.svg" alt="Square for Laravel" width="420">
  </picture>
</p>

<h2 align="center">
  <a href="https://www.php.net/" target="_blank"><img src="https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=flat&logo=php&logoColor=white" alt="PHP 8.2+"></a>&nbsp;
  <a href="https://laravel.com/docs/" target="_blank"><img src="https://img.shields.io/badge/Laravel-12%20%7C%2013-FF2D20?style=flat&logo=laravel&logoColor=white" alt="Laravel 12 or 13"></a>&nbsp;
  <a href="https://github.com/FLAIRUK/laravel-square/actions/workflows/tests.yml" target="_blank"><img src="https://img.shields.io/badge/Lint-%E2%9C%93-2EA043?style=flat&logo=githubactions&logoColor=white" alt="Lint"></a>&nbsp;
  <a href="https://github.com/FLAIRUK/laravel-square/actions/workflows/tests.yml" target="_blank"><img src="https://img.shields.io/badge/Tests-%E2%9C%93-2EA043?style=flat&logo=githubactions&logoColor=white" alt="Tests"></a>&nbsp;
  <a href="https://packagist.org/packages/flairuk/laravel-square" target="_blank"><img src="https://img.shields.io/packagist/dt/flairuk/laravel-square?style=flat&logo=packagist&logoColor=white&label=Downloads&color=F28D1A" alt="Downloads on Packagist"></a>&nbsp;
  <a href="https://github.com/FLAIRUK/laravel-square/blob/master/LICENSE.md" target="_blank"><img src="https://img.shields.io/github/license/FLAIRUK/laravel-square?style=flat&label=License&color=3DA639" alt="MIT licence"></a>&nbsp;
  <a href="https://developer.squareup.com/reference/square" target="_blank"><img src="https://img.shields.io/badge/Client-Square%20API-3E4348?style=flat" alt="Square API"></a>&nbsp;
  <br>&nbsp;
</h2>

**Square for Laravel** — The official [Square PHP SDK](https://github.com/square/square-php-sdk), configured for Laravel 12 and 13, with the parts the SDK leaves to you.

- **The real SDK, configured.** `Square::payments()`, `Square::customers()` and every other Square API, set up from `.env` for sandbox or production. Nothing is re-implemented, so new Square APIs arrive with SDK updates.
- **Testable.** The SDK sends its requests through Laravel's HTTP client, so `Http::fake()` works in your tests.
- **Webhooks.** Signature-verifying middleware and an opt-in route that turns each notification into a Laravel event, with duplicates filtered out.
- **Safe payments.** Idempotency keys, including deterministic ones, so a retried job never charges twice. Money helpers convert `"12.50"` to minor units without floating-point errors.
- **Multi-merchant.** An OAuth helper (code and PKCE flows) and `Square::forMerchant($token)`.
- **Card form.** A Blade component for the Web Payments SDK card field.

<p align="center">
  📦&nbsp;<a href="#-installation">Installation</a> ·
  🚀&nbsp;<a href="#-usage">Usage</a> ·
  🔔&nbsp;<a href="#-webhooks">Webhooks</a> ·
  💳&nbsp;<a href="#-card-form">Card form</a> ·
  🔑&nbsp;<a href="#-oauth">OAuth</a> ·
  🔌&nbsp;<a href="#-testing-your-integration">Testing your integration</a>
</p>

<br><br>

## 📦 Installation

```bash
composer require flairuk/laravel-square
php artisan square:install
```

`square:install` publishes `config/square.php` and adds any of these keys that are missing to `.env` and `.env.example`, empty. Fill them in from the [Developer Console](https://developer.squareup.com/apps):

```dotenv
SQUARE_ACCESS_TOKEN=EAAA...
SQUARE_ENVIRONMENT=sandbox          # or production
SQUARE_LOCATION_ID=L...             # default location
SQUARE_APPLICATION_ID=sandbox-sq0idb-...
SQUARE_WEBHOOK_SIGNATURE_KEY=       # only for webhooks
SQUARE_WEBHOOK_URL=                 # only for webhooks
```

Then check the connection. It lists the locations the token can access:

```bash
php artisan square:status
```

Optional settings: `SQUARE_VERSION` pins the `Square-Version` header (by default, the version the installed SDK was built for), `SQUARE_CURRENCY` sets the default currency for `Square::money()` (default `USD`), and `SQUARE_APPLICATION_SECRET` and `SQUARE_OAUTH_REDIRECT_URI` are used by OAuth.

> The SDK's major version changes often, as Square releases new API versions. This package supports `square/square` 45 to 47. The SDK retries connection errors, 408, 429 and 5xx responses itself, so always send an idempotency key with writes.

<br><br>

## 🚀 Usage

```php
use FLAIRUK\Square\Facades\Square;
```

You can also type-hint `FLAIRUK\Square\Square`, or `Square\SquareClient` for the SDK client itself. Both are singletons.

### Taking a payment

```php
use Square\Payments\Requests\CreatePaymentRequest;

$response = Square::payments()->create(new CreatePaymentRequest([
    'sourceId' => $request->input('source_id'),         // the token from the card form
    'idempotencyKey' => Square::idempotencyKey('order', $order->id),
    'amountMoney' => Square::money('12.50', 'GBP'),     // 1250 pence
    'locationId' => Square::locationId(),
]));

$response->getPayment()->getId();
$response->getPayment()->getStatus();   // "COMPLETED"
```

### Every Square API

Each API client on the SDK's `SquareClient` is a method on the facade, returning that client:

```php
use Square\Customers\Requests\CreateCustomerRequest;
use Square\Refunds\Requests\RefundPaymentRequest;

Square::locations()->list()->getLocations();

Square::customers()->create(new CreateCustomerRequest([
    'idempotencyKey' => Square::idempotencyKey(),
    'givenName' => 'Jane',
    'emailAddress' => 'jane@example.com',
]));

foreach (Square::customers()->list() as $customer) {   // pages are fetched as you go
    // ...
}

Square::refunds()->refundPayment(new RefundPaymentRequest([
    'idempotencyKey' => Square::idempotencyKey('refund', $payment->id),
    'amountMoney' => Square::money('5.00', 'GBP'),
    'paymentId' => $payment->id,
]));

Square::client();   // the Square\SquareClient
```

`orders()`, `catalog()`, `inventory()`, `invoices()`, `subscriptions()`, `giftCards()`, `loyalty()`, `bookings()`, `terminal()`, `teamMembers()` and the rest work the same way. See the [SDK reference](https://github.com/square/square-php-sdk/blob/master/reference.md) for their methods. The SDK's own OAuth client is `Square::client()->oAuth`, because `Square::oauth()` is this package's [OAuth helper](#-oauth).

### Idempotency keys

```php
Square::idempotencyKey();                         // a random UUID
Square::idempotencyKey('order', 42, 'payment');   // the same key every time for these parts
```

Square returns the original result when it sees a key again, so a deterministic key makes a retried job or a double-clicked button safe. Keys are 36 characters, within Square's 45-character limit. The same methods are on `FLAIRUK\Square\Support\IdempotencyKey` as `generate()` and `for(...)`.

### Money

Square amounts are integers in the currency's smallest unit: pence, cents, or whole yen.

```php
use FLAIRUK\Square\Support\Money;

Square::money('12.50');            // Square\Types\Money: 1250 in SQUARE_CURRENCY
Square::money(1500, 'JPY');        // 1500 yen (JPY has no minor unit)

Money::toMinor('19.99', 'USD');    // 1999
Money::fromMinor(1999, 'USD');     // "19.99"
Money::toDecimal($payment->getAmountMoney());   // "12.50"
Money::ofMinor(1250, 'GBP');       // a Square\Types\Money from minor units
Money::decimals('KWD');            // 3
```

Amounts are converted as strings, so `"19.99"` is always `1999`. An amount with more decimals than the currency allows, such as `"1.234"` GBP, throws an `InvalidArgumentException` instead of being rounded.

### Other sellers

```php
Square::forMerchant($seller->square_access_token)->payments()->list();
```

### Errors

API errors are the SDK's own exceptions:

```php
use Square\Exceptions\SquareApiException;
use Square\Exceptions\SquareException;

try {
    Square::payments()->create($request);
} catch (SquareApiException $e) {
    $e->getStatusCode();                // 400, 401, 402 ...
    $e->getErrors()[0]->getCode();      // "CARD_DECLINED", "UNAUTHORIZED" ...
    $e->getErrors()[0]->getDetail();
} catch (SquareException $e) {
    // Square could not be reached, or the response could not be read
}
```

This package's exceptions extend `FLAIRUK\Square\Exceptions\SquareException`. `ConfigurationException` means a setting is missing, such as `SQUARE_ACCESS_TOKEN`.

<br><br>

## 🔔 Webhooks

Square signs each notification with an HMAC-SHA256 of the notification URL followed by the raw body. Add a subscription in the Developer Console, then set:

```dotenv
SQUARE_WEBHOOK_SIGNATURE_KEY=...                        # from the subscription
SQUARE_WEBHOOK_URL=https://example.com/square/webhook   # exactly as entered in the subscription
SQUARE_WEBHOOK_PATH=square/webhook                      # registers the package's route
```

The route (`POST /square/webhook`, named `square.webhook`) has no session or CSRF middleware. It verifies the signature, answers `403` if it is missing or wrong, and dispatches two events for each notification:

```php
use FLAIRUK\Square\Events\WebhookReceived;
use FLAIRUK\Square\Webhooks\WebhookEvent;
use Illuminate\Support\Facades\Event;

// Every notification
Event::listen(function (WebhookReceived $received) {
    $received->event->type;          // "payment.updated"
});

// One type, as "square.{type}"
Event::listen('square.payment.updated', function (WebhookEvent $event) {
    $event->eventId;                     // Square's event_id
    $event->merchantId;
    $event->createdAt;
    $event->objectId();                  // the payment ID
    $event->object('status');            // "COMPLETED"
    $event->object('amount_money.amount');
    $event->data;                        // the notification's "data"
    $event->payload;                     // the whole notification
    $event->toSdkEvent();                // Square\Types\PaymentUpdatedEvent, if the SDK has the class
});
```

Square can send a notification more than once. The route remembers each `event_id` for 48 hours in your default cache (set `SQUARE_CACHE_STORE` to use another), and acknowledges repeats without dispatching again. If a listener throws, the event is not remembered, so Square's retry is processed. Turn this off with `square.webhooks.deduplicate`. Square expects a fast `2xx`, so do slow work in a queued listener.

To handle webhooks in your own controller, use the middleware instead:

```php
Route::post('hooks/square', SquareWebhookController::class)->middleware('square.webhook');
```

The middleware checks the signature against `SQUARE_WEBHOOK_URL`. If that isn't set, it uses the URL the request arrived at, which may not match behind a proxy or load balancer. You can also check a signature yourself:

```php
Square::webhookSignature()->verify($request->getContent(), $request->header('x-square-hmacsha256-signature'));
```

<br><br>

## 💳 Card form

`<x-square::card-form />` renders the [Web Payments SDK](https://developer.squareup.com/docs/web-payments/overview) card field for `SQUARE_ENVIRONMENT`, using `SQUARE_APPLICATION_ID` and `SQUARE_LOCATION_ID`. Put it inside your form:

```blade
<form method="POST" action="{{ route('checkout.pay') }}">
    @csrf
    <x-square::card-form />
    <button type="submit">Pay £12.50</button>
</form>
```

When the form is submitted, the component tokenizes the card, puts the token in a hidden `source_id` input and submits the form. Card errors are shown under the field. Use the token as `sourceId` when [taking the payment](#taking-a-payment).

Every attribute is optional:

```blade
<x-square::card-form
    name="source_id"
    id="square-card"
    application-id="sandbox-sq0idb-..."
    location-id="L..."
    :verification="['amount' => '12.50', 'currencyCode' => 'GBP', 'intent' => 'CHARGE', 'customerInitiated' => true, 'sellerKeyedIn' => false]"
    class="mb-4"
/>
```

`verification` is passed to `card.tokenize()` as-is, for [buyer verification](https://developer.squareup.com/docs/web-payments/take-card-payment) (SCA). If your site has a Content Security Policy, allow Square's script and frame hosts.

<br><br>

## 🔑 OAuth

To act for other sellers, set `SQUARE_APPLICATION_ID`, `SQUARE_APPLICATION_SECRET` and `SQUARE_OAUTH_REDIRECT_URI` (the redirect URL on your application's OAuth page), then:

```php
// Redirect the seller to Square
$state = Str::random(40);
session(['square_state' => $state]);

return redirect(Square::oauth()->authorizeUrl(['MERCHANT_PROFILE_READ', 'PAYMENTS_WRITE'], $state));

// In the callback
abort_unless(hash_equals(session()->pull('square_state', ''), (string) $request->query('state')), 403);

$token = Square::oauth()->exchangeCode($request->query('code'));
$token->getAccessToken();
$token->getRefreshToken();
$token->getMerchantId();
$token->getExpiresAt();   // access tokens last 30 days: refresh them before then

Square::oauth()->refresh($refreshToken);
Square::oauth()->revoke(merchantId: $merchantId);
```

For the PKCE flow (no application secret, e.g. for a mobile or single-page app's back end):

```php
use FLAIRUK\Square\OAuth;

['verifier' => $verifier, 'challenge' => $challenge] = OAuth::pkce();

Square::oauth()->authorizeUrl($scopes, $state, codeChallenge: $challenge);
Square::oauth()->exchangeCode($code, codeVerifier: $verifier);
Square::oauth()->refresh($refreshToken, pkce: true);
```

Store tokens encrypted (for example with the `encrypted` cast), and use them with `Square::forMerchant($accessToken)`.

<br><br>

## 🔌 Testing your integration

The SDK sends its requests through Laravel's HTTP client, so `Http::fake()` works in your own tests:

```php
Http::fake([
    'connect.squareupsandbox.com/v2/payments' => Http::response([
        'payment' => ['id' => 'P1', 'status' => 'COMPLETED'],
    ]),
]);
```

To test a webhook listener, set `SQUARE_WEBHOOK_SIGNATURE_KEY` and `SQUARE_WEBHOOK_URL` for your tests (in `phpunit.xml` or `.env.testing`) and sign the body with them:

```php
$body = json_encode(['type' => 'payment.updated', 'event_id' => 'evt-1', 'data' => [...]]);

$this->call('POST', '/square/webhook', server: [
    'HTTP_X_SQUARE_HMACSHA256_SIGNATURE' => Square::webhookSignature()->sign($body),
], content: $body)->assertOk();
```

In Square's sandbox, use the [test card numbers](https://developer.squareup.com/docs/devtools/sandbox/payments), or the `cnon:card-nonce-ok` source ID to skip the card form.

<br><br>

## 🧪 Testing

```bash
composer test
```

<br><br>

## 🔒 Security

If you discover a security issue, please email ijeffrouk@gmail.com instead of using the issue tracker.

<br><br>

## 🙌 Credits

- [Phil Graham](https://github.com/ijeffro)
- [FLAIR](https://github.com/flairuk)
- [All Contributors](../../contributors)

<br><br>

## 📄 License

MIT. See [LICENSE](LICENSE.md).
