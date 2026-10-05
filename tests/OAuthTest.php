<?php

namespace FLAIRUK\Square\Tests;

use FLAIRUK\Square\ClientFactory;
use FLAIRUK\Square\Exceptions\ConfigurationException;
use FLAIRUK\Square\Facades\Square;
use FLAIRUK\Square\OAuth;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

class OAuthTest extends TestCase
{
    protected function tokenResponse(): array
    {
        return [
            'access_token' => 'EAAA-seller',
            'token_type' => 'bearer',
            'expires_at' => '2026-11-04T10:00:00Z',
            'merchant_id' => 'M1',
            'refresh_token' => 'EQAA-refresh',
        ];
    }

    #[Test]
    public function it_builds_the_authorize_url(): void
    {
        $url = Square::oauth()->authorizeUrl(['MERCHANT_PROFILE_READ', 'PAYMENTS_WRITE'], 'csrf-state', locale: 'en-US');

        $this->assertStringStartsWith(self::SANDBOX.'/oauth2/authorize?', $url);
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame([
            'client_id' => 'sandbox-sq0idb-app',
            'scope' => 'MERCHANT_PROFILE_READ PAYMENTS_WRITE',
            'session' => 'false',
            'state' => 'csrf-state',
            'locale' => 'en-US',
        ], $query);
    }

    #[Test]
    public function the_authorize_url_supports_pkce_and_a_redirect_uri(): void
    {
        config(['square.oauth.redirect_uri' => 'https://app.example.com/square/callback']);
        $this->app->forgetInstance(\FLAIRUK\Square\Square::class);

        $pkce = OAuth::pkce();
        $url = Square::oauth()->authorizeUrl(['PAYMENTS_READ'], 'state', codeChallenge: $pkce['challenge']);
        parse_str(parse_url($url, PHP_URL_QUERY), $query);

        $this->assertSame($pkce['challenge'], $query['code_challenge']);
        $this->assertSame('https://app.example.com/square/callback', $query['redirect_uri']);
    }

    #[Test]
    public function pkce_follows_rfc_7636(): void
    {
        // RFC 7636 appendix B test vector
        $this->assertSame('E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM', OAuth::challenge('dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk'));

        $pkce = OAuth::pkce();
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9\-_]{43,128}$/', $pkce['verifier']);
        $this->assertSame(OAuth::challenge($pkce['verifier']), $pkce['challenge']);
    }

    #[Test]
    public function it_exchanges_a_code_with_the_application_secret(): void
    {
        $this->fakeSquare(['*/oauth2/token' => Http::response($this->tokenResponse())]);

        $token = Square::oauth()->exchangeCode('sq0cgb-code');

        $this->assertSame('EAAA-seller', $token->getAccessToken());
        $this->assertSame('M1', $token->getMerchantId());
        Http::assertSent(fn (Request $request) => $request->url() === self::SANDBOX.'/oauth2/token'
            && $request->data() === [
                'client_id' => 'sandbox-sq0idb-app',
                'client_secret' => 'sandbox-sq0csb-secret',
                'code' => 'sq0cgb-code',
                'grant_type' => 'authorization_code',
            ]
            && ! $request->hasHeader('Authorization'));
    }

    #[Test]
    public function it_exchanges_a_code_with_a_pkce_verifier(): void
    {
        config(['square.application_secret' => null]);
        $this->app->forgetInstance(\FLAIRUK\Square\Square::class);
        $this->fakeSquare(['*/oauth2/token' => Http::response($this->tokenResponse())]);

        Square::oauth()->exchangeCode('sq0cgb-code', 'https://app.example.com/cb', 'the-verifier');

        Http::assertSent(fn (Request $request) => $request['code_verifier'] === 'the-verifier'
            && $request['redirect_uri'] === 'https://app.example.com/cb'
            && ! isset($request['client_secret']));
    }

    #[Test]
    public function it_refreshes_tokens(): void
    {
        $this->fakeSquare(['*/oauth2/token' => Http::response($this->tokenResponse())]);

        Square::oauth()->refresh('EQAA-refresh');
        Square::oauth()->refresh('EQAA-pkce', pkce: true);

        Http::assertSent(fn (Request $request) => $request['grant_type'] === 'refresh_token'
            && $request['refresh_token'] === 'EQAA-refresh'
            && $request['client_secret'] === 'sandbox-sq0csb-secret');
        Http::assertSent(fn (Request $request) => $request['refresh_token'] === 'EQAA-pkce'
            && ! isset($request['client_secret']));
    }

    #[Test]
    public function it_revokes_with_client_authorization(): void
    {
        $this->fakeSquare(['*/oauth2/revoke' => Http::response(['success' => true])]);

        $this->assertTrue(Square::oauth()->revoke(merchantId: 'M1')->getSuccess());

        Http::assertSent(fn (Request $request) => $request->header('Authorization') === ['Client sandbox-sq0csb-secret']
            && $request->data() === ['client_id' => 'sandbox-sq0idb-app', 'merchant_id' => 'M1']);
    }

    #[Test]
    public function oauth_works_without_an_access_token(): void
    {
        config(['square.access_token' => null]);
        $this->app->forgetInstance(\FLAIRUK\Square\Square::class);
        $this->app->forgetInstance(ClientFactory::class);
        $this->fakeSquare(['*/oauth2/token' => Http::response($this->tokenResponse())]);

        $this->assertSame('EAAA-seller', Square::oauth()->exchangeCode('code')->getAccessToken());
    }

    #[Test]
    public function a_missing_application_id_is_a_configuration_error(): void
    {
        config(['square.application_id' => null]);
        $this->app->forgetInstance(\FLAIRUK\Square\Square::class);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('SQUARE_APPLICATION_ID');

        Square::oauth()->authorizeUrl([], 'state');
    }
}
