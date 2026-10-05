<?php

namespace FLAIRUK\Square;

use FLAIRUK\Square\Exceptions\ConfigurationException;
use Square\OAuth\OAuthClient;
use Square\OAuth\Requests\ObtainTokenRequest;
use Square\OAuth\Requests\RevokeTokenRequest;
use Square\Types\ObtainTokenResponse;
use Square\Types\RevokeTokenResponse;

/**
 * Square OAuth for apps that act for other sellers: build the authorization
 * URL, exchange the code, refresh and revoke tokens. Uses SQUARE_APPLICATION_ID
 * and SQUARE_APPLICATION_SECRET (not needed for the PKCE flow).
 *
 * Use the seller's token with Square::forMerchant($accessToken).
 *
 * @see https://developer.squareup.com/docs/oauth-api/overview
 */
class OAuth
{
    /**
     * @param  array<string, mixed>  $config  the "square" config array
     */
    public function __construct(
        protected readonly ClientFactory $factory,
        protected readonly array $config,
    ) {}

    /**
     * The URL to send the seller to, to authorize your application.
     *
     * @param  list<string>  $scopes  permissions, e.g. ['MERCHANT_PROFILE_READ', 'PAYMENTS_WRITE']
     * @param  string  $state  a random CSRF token; store it and compare it on the callback
     * @param  string|null  $codeChallenge  for the PKCE flow, the challenge from pkce()
     * @param  string|null  $locale  e.g. "en-US", "fr-CA"
     */
    public function authorizeUrl(
        array $scopes,
        string $state,
        ?string $redirectUri = null,
        ?string $codeChallenge = null,
        ?string $locale = null,
        bool $session = false,
    ): string {
        $query = array_filter([
            'client_id' => $this->applicationId(),
            'scope' => implode(' ', $scopes),
            'session' => $session ? 'true' : 'false',
            'state' => $state,
            'redirect_uri' => $redirectUri ?? $this->config['oauth']['redirect_uri'] ?? null,
            'code_challenge' => $codeChallenge,
            'locale' => $locale,
        ], fn ($value) => $value !== null && $value !== '');

        return $this->factory->environment()->baseUrl().'/oauth2/authorize?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Exchange the authorization code from the callback for an access and refresh token.
     *
     * Pass $codeVerifier for the PKCE flow; otherwise the application secret is sent.
     */
    public function exchangeCode(string $code, ?string $redirectUri = null, ?string $codeVerifier = null): ObtainTokenResponse
    {
        return $this->client()->obtainToken(new ObtainTokenRequest(array_filter([
            'clientId' => $this->applicationId(),
            'clientSecret' => $codeVerifier === null ? $this->applicationSecret() : null,
            'code' => $code,
            'redirectUri' => $redirectUri ?? $this->config['oauth']['redirect_uri'] ?? null,
            'codeVerifier' => $codeVerifier,
            'grantType' => 'authorization_code',
        ], fn ($value) => $value !== null)));
    }

    /**
     * Get a new access token with a refresh token. Code-flow apps send the
     * application secret; pass $pkce = true for tokens obtained with PKCE.
     */
    public function refresh(string $refreshToken, bool $pkce = false): ObtainTokenResponse
    {
        return $this->client()->obtainToken(new ObtainTokenRequest(array_filter([
            'clientId' => $this->applicationId(),
            'clientSecret' => $pkce ? null : $this->applicationSecret(),
            'refreshToken' => $refreshToken,
            'grantType' => 'refresh_token',
        ], fn ($value) => $value !== null)));
    }

    /**
     * Revoke a seller's access to your application, by access token or merchant ID.
     * Square revokes all of the seller's tokens for the application.
     */
    public function revoke(?string $accessToken = null, ?string $merchantId = null): RevokeTokenResponse
    {
        if ($accessToken === null && $merchantId === null) {
            throw new \InvalidArgumentException('Pass an access token or a merchant ID to revoke.');
        }

        return $this->client(clientAuthorization: true)->revokeToken(new RevokeTokenRequest(array_filter([
            'clientId' => $this->applicationId(),
            'accessToken' => $accessToken,
            'merchantId' => $merchantId,
        ], fn ($value) => $value !== null)));
    }

    /**
     * A PKCE code verifier and its S256 challenge. Keep the verifier (e.g. in the
     * session), send the challenge to authorizeUrl(), and the verifier to exchangeCode().
     *
     * @return array{verifier: string, challenge: string}
     */
    public static function pkce(): array
    {
        $verifier = rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');

        return ['verifier' => $verifier, 'challenge' => static::challenge($verifier)];
    }

    /**
     * The S256 code challenge for a verifier: base64url(sha256(verifier)), without padding.
     */
    public static function challenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    /**
     * The SDK's OAuth client. Token requests need no bearer token; revoke needs
     * "Authorization: Client {application secret}".
     */
    public function client(bool $clientAuthorization = false): OAuthClient
    {
        $authorization = $clientAuthorization ? 'Client '.$this->applicationSecret() : '';

        return $this->factory->make('', ['Authorization' => $authorization])->oAuth;
    }

    protected function applicationId(): string
    {
        return filled($this->config['application_id'] ?? null)
            ? (string) $this->config['application_id']
            : throw new ConfigurationException('No Square application ID: set SQUARE_APPLICATION_ID.');
    }

    protected function applicationSecret(): string
    {
        return filled($this->config['application_secret'] ?? null)
            ? (string) $this->config['application_secret']
            : throw new ConfigurationException('No Square application secret: set SQUARE_APPLICATION_SECRET.');
    }
}
