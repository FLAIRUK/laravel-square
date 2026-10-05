<?php

namespace FLAIRUK\Square;

use FLAIRUK\Square\Exceptions\ConfigurationException;
use Square\Environments;

enum Environment: string
{
    case Sandbox = 'sandbox';
    case Production = 'production';

    public static function fromConfig(?string $value): self
    {
        return self::tryFrom(strtolower(trim((string) $value)))
            ?? throw new ConfigurationException("SQUARE_ENVIRONMENT must be \"sandbox\" or \"production\", \"{$value}\" given.");
    }

    /**
     * The Square API base URL, e.g. https://connect.squareupsandbox.com.
     */
    public function baseUrl(): string
    {
        return match ($this) {
            self::Sandbox => Environments::Sandbox->value,
            self::Production => Environments::Production->value,
        };
    }

    /**
     * The Web Payments SDK script for this environment.
     */
    public function webPaymentsScriptUrl(): string
    {
        return match ($this) {
            self::Sandbox => 'https://sandbox.web.squarecdn.com/v1/square.js',
            self::Production => 'https://web.squarecdn.com/v1/square.js',
        };
    }

    public function isSandbox(): bool
    {
        return $this === self::Sandbox;
    }
}
