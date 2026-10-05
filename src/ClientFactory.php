<?php

namespace FLAIRUK\Square;

use FLAIRUK\Square\Exceptions\ConfigurationException;
use FLAIRUK\Square\Http\LaravelHttpClient;
use Illuminate\Http\Client\Factory;
use Square\SquareClient;

/**
 * Builds Square SDK clients from the package config.
 */
class ClientFactory
{
    /**
     * @param  array<string, mixed>  $config  the "square" config array
     */
    public function __construct(
        protected readonly Factory $http,
        protected readonly array $config,
    ) {}

    /**
     * A client authenticated with the given access token (defaults to SQUARE_ACCESS_TOKEN).
     *
     * @param  array<string, string>  $headers  extra headers sent with every request
     */
    public function make(?string $accessToken = null, array $headers = []): SquareClient
    {
        $accessToken ??= $this->config['access_token'] ?? null;

        if (blank($accessToken) && ! array_key_exists('Authorization', $headers)) {
            throw new ConfigurationException('No Square access token: set SQUARE_ACCESS_TOKEN, or use Square::forMerchant($token).');
        }

        return new SquareClient(
            token: (string) $accessToken,
            version: filled($this->config['version'] ?? null) ? (string) $this->config['version'] : null,
            options: [
                'baseUrl' => $this->environment()->baseUrl(),
                'client' => new LaravelHttpClient($this->http, (int) ($this->config['timeout'] ?? 30)),
                'maxRetries' => (int) ($this->config['retries'] ?? 2),
                'headers' => $headers,
            ],
        );
    }

    public function environment(): Environment
    {
        return Environment::fromConfig($this->config['environment'] ?? 'sandbox');
    }
}
