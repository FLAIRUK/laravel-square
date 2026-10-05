<?php

namespace FLAIRUK\Square\Tests;

use FLAIRUK\Square\Facades\Square;
use FLAIRUK\Square\Support\IdempotencyKey;
use PHPUnit\Framework\Attributes\Test;

class IdempotencyKeyTest extends TestCase
{
    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    #[Test]
    public function generated_keys_are_random_v4_uuids(): void
    {
        $key = IdempotencyKey::generate();

        $this->assertMatchesRegularExpression(self::UUID, $key);
        $this->assertSame('4', $key[14]);
        $this->assertNotSame($key, IdempotencyKey::generate());
        $this->assertLessThanOrEqual(45, strlen($key));
    }

    #[Test]
    public function derived_keys_are_stable_and_distinct(): void
    {
        $key = IdempotencyKey::for('order', 42, 'payment');

        $this->assertMatchesRegularExpression(self::UUID, $key);
        $this->assertSame('8', $key[14]);
        $this->assertSame($key, IdempotencyKey::for('order', '42', 'payment'));
        $this->assertNotSame($key, IdempotencyKey::for('order', 43, 'payment'));
        $this->assertNotSame(IdempotencyKey::for('ab', 'c'), IdempotencyKey::for('a', 'bc'));
    }

    #[Test]
    public function the_facade_generates_or_derives(): void
    {
        $this->assertMatchesRegularExpression(self::UUID, Square::idempotencyKey());
        $this->assertSame(IdempotencyKey::for('order', 42), Square::idempotencyKey('order', 42));
    }

    #[Test]
    public function at_least_one_part_is_required(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        IdempotencyKey::for();
    }
}
