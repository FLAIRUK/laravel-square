<?php

namespace FLAIRUK\Square\Support;

use InvalidArgumentException;
use Square\Types\Money as SquareMoney;

/**
 * Converts between decimal amounts and the minor units (cents, pence; yen for
 * JPY) that Square's Money objects use. Conversions use string arithmetic,
 * so there is no floating-point rounding: "19.99" is always 1999.
 */
final class Money
{
    /**
     * Currencies whose minor unit is not 1/100, per ISO 4217.
     *
     * @var array<string, int>
     */
    private const DECIMALS = [
        'BIF' => 0, 'CLP' => 0, 'DJF' => 0, 'GNF' => 0, 'ISK' => 0, 'JPY' => 0, 'KMF' => 0, 'KRW' => 0,
        'PYG' => 0, 'RWF' => 0, 'UGX' => 0, 'UYI' => 0, 'VND' => 0, 'VUV' => 0, 'XAF' => 0, 'XOF' => 0, 'XPF' => 0,
        'BHD' => 3, 'IQD' => 3, 'JOD' => 3, 'KWD' => 3, 'LYD' => 3, 'OMR' => 3, 'TND' => 3,
        'CLF' => 4, 'UYW' => 4,
    ];

    /**
     * The number of decimal places in the currency's minor unit (2 for GBP, 0 for JPY).
     */
    public static function decimals(string $currency): int
    {
        return self::DECIMALS[self::currency($currency)] ?? 2;
    }

    /**
     * A decimal amount in minor units: toMinor('12.34', 'GBP') === 1234.
     *
     * Ints and numeric strings are exact. Floats are formatted to the currency's
     * precision first, so pass strings when the amount comes from user input.
     *
     * @throws InvalidArgumentException if the amount is not numeric or has more decimals than the currency
     */
    public static function toMinor(int|float|string $amount, string $currency): int
    {
        $decimals = self::decimals($currency);

        if (is_float($amount)) {
            $amount = number_format($amount, $decimals, '.', '');
        }

        $amount = trim((string) $amount);

        if (! preg_match('/^(-)?(\d*)(?:\.(\d*))?$/', $amount, $parts) || ($parts[2] === '' && ($parts[3] ?? '') === '')) {
            throw new InvalidArgumentException("\"{$amount}\" is not a valid amount.");
        }

        [, $sign, $whole] = $parts;
        $fraction = rtrim($parts[3] ?? '', '0');

        if (strlen($fraction) > $decimals) {
            throw new InvalidArgumentException("\"{$amount}\" has more than {$decimals} decimal places for ".self::currency($currency).'.');
        }

        $minor = ltrim($whole.str_pad($fraction, $decimals, '0'), '0');

        if (strlen($minor) > 18) {
            throw new InvalidArgumentException("\"{$amount}\" is too large.");
        }

        return (int) ($sign.($minor === '' ? '0' : $minor));
    }

    /**
     * Minor units as a decimal string: fromMinor(1234, 'GBP') === '12.34'.
     */
    public static function fromMinor(int $amount, string $currency): string
    {
        $decimals = self::decimals($currency);
        $digits = str_pad((string) abs($amount), $decimals + 1, '0', STR_PAD_LEFT);
        $sign = $amount < 0 ? '-' : '';

        if ($decimals === 0) {
            return $sign.$digits;
        }

        return $sign.substr($digits, 0, -$decimals).'.'.substr($digits, -$decimals);
    }

    /**
     * A Square Money object from a decimal amount: make('12.34', 'GBP') has amount 1234.
     */
    public static function make(int|float|string $amount, string $currency): SquareMoney
    {
        return self::ofMinor(self::toMinor($amount, $currency), $currency);
    }

    /**
     * A Square Money object from an amount already in minor units.
     */
    public static function ofMinor(int $amount, string $currency): SquareMoney
    {
        return new SquareMoney(['amount' => $amount, 'currency' => self::currency($currency)]);
    }

    /**
     * A Square Money object (or the array form in a webhook payload) as a decimal string.
     *
     * @param  SquareMoney|array{amount?: int|null, currency?: string|null}  $money
     */
    public static function toDecimal(SquareMoney|array $money): string
    {
        $amount = $money instanceof SquareMoney ? $money->getAmount() : ($money['amount'] ?? null);
        $currency = $money instanceof SquareMoney ? $money->getCurrency() : ($money['currency'] ?? null);

        if ($amount === null || blank($currency)) {
            throw new InvalidArgumentException('The Money object needs an amount and a currency.');
        }

        return self::fromMinor((int) $amount, $currency);
    }

    private static function currency(string $currency): string
    {
        $currency = strtoupper(trim($currency));

        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException("\"{$currency}\" is not an ISO 4217 currency code.");
        }

        return $currency;
    }
}
