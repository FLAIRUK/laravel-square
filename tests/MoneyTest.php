<?php

namespace FLAIRUK\Square\Tests;

use FLAIRUK\Square\Facades\Square;
use FLAIRUK\Square\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Square\Types\Money as SquareMoney;

class MoneyTest extends TestCase
{
    public static function conversions(): array
    {
        return [
            ['12.34', 'GBP', 1234],
            ['19.99', 'USD', 1999],
            [19.99, 'USD', 1999],
            [0.1 + 0.2, 'EUR', 30],
            ['12', 'GBP', 1200],
            [12, 'gbp', 1200],
            ['12.5', 'GBP', 1250],
            ['12.', 'GBP', 1200],
            ['.5', 'GBP', 50],
            ['0', 'GBP', 0],
            ['-4.20', 'GBP', -420],
            ['1.500', 'GBP', 150],
            ['1500', 'JPY', 1500],
            ['1500.00', 'JPY', 1500],
            ['1.234', 'KWD', 1234],
            [' 7.00 ', 'AUD', 700],
        ];
    }

    #[Test]
    #[DataProvider('conversions')]
    public function decimal_amounts_become_minor_units(int|float|string $amount, string $currency, int $minor): void
    {
        $this->assertSame($minor, Money::toMinor($amount, $currency));
    }

    #[Test]
    public function minor_units_become_decimal_strings(): void
    {
        $this->assertSame('12.34', Money::fromMinor(1234, 'GBP'));
        $this->assertSame('0.05', Money::fromMinor(5, 'USD'));
        $this->assertSame('0.00', Money::fromMinor(0, 'USD'));
        $this->assertSame('-4.20', Money::fromMinor(-420, 'GBP'));
        $this->assertSame('1500', Money::fromMinor(1500, 'JPY'));
        $this->assertSame('1.234', Money::fromMinor(1234, 'KWD'));
    }

    #[Test]
    public function it_knows_currency_precision(): void
    {
        $this->assertSame(2, Money::decimals('GBP'));
        $this->assertSame(0, Money::decimals('jpy'));
        $this->assertSame(3, Money::decimals('BHD'));
    }

    #[Test]
    public function it_builds_square_money_objects(): void
    {
        $money = Money::make('9.99', 'usd');

        $this->assertInstanceOf(SquareMoney::class, $money);
        $this->assertSame(999, $money->getAmount());
        $this->assertSame('USD', $money->getCurrency());
        $this->assertSame(500, Money::ofMinor(500, 'GBP')->getAmount());
        $this->assertSame('9.99', Money::toDecimal($money));
        $this->assertSame('12.50', Money::toDecimal(['amount' => 1250, 'currency' => 'GBP']));
    }

    #[Test]
    public function the_facade_uses_the_default_currency(): void
    {
        $this->assertSame('GBP', Square::money('3.50')->getCurrency());
        $this->assertSame(350, Square::money('3.50')->getAmount());
        $this->assertSame('JPY', Square::money(350, 'JPY')->getCurrency());
        $this->assertSame(350, Square::money(350, 'JPY')->getAmount());
    }

    public static function invalid(): array
    {
        return [['abc', 'GBP'], ['', 'GBP'], ['1,000', 'GBP'], ['1.234', 'GBP'], ['1.5', 'JPY'], ['1e3', 'GBP'], ['-', 'GBP'], ['1', 'POUNDS'], ['99999999999999999999', 'GBP']];
    }

    #[Test]
    #[DataProvider('invalid')]
    public function it_rejects_invalid_amounts(string $amount, string $currency): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::toMinor($amount, $currency);
    }
}
