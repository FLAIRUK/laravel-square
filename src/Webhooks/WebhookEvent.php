<?php

namespace FLAIRUK\Square\Webhooks;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * A verified Square webhook notification.
 */
final readonly class WebhookEvent
{
    /**
     * @param  array<string, mixed>  $data  the notification's "data" object
     * @param  array<string, mixed>  $payload  the whole decoded body
     */
    public function __construct(
        public string $type,
        public ?string $eventId,
        public ?string $merchantId,
        public ?string $createdAt,
        public array $data,
        public array $payload,
        public string $body,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload, string $body): self
    {
        return new self(
            type: (string) $payload['type'],
            eventId: isset($payload['event_id']) ? (string) $payload['event_id'] : null,
            merchantId: isset($payload['merchant_id']) ? (string) $payload['merchant_id'] : null,
            createdAt: isset($payload['created_at']) ? (string) $payload['created_at'] : null,
            data: is_array($payload['data'] ?? null) ? $payload['data'] : [],
            payload: $payload,
            body: $body,
        );
    }

    /**
     * The affected object's type and ID, e.g. "payment" and the payment ID.
     */
    public function objectType(): ?string
    {
        return $this->data['type'] ?? null;
    }

    public function objectId(): ?string
    {
        return $this->data['id'] ?? null;
    }

    /**
     * The affected object, e.g. the payment for payment.created, or one of its
     * fields in dot notation: $event->object('amount_money.amount').
     */
    public function object(?string $key = null, mixed $default = null): mixed
    {
        $object = $this->data['object'] ?? [];
        $type = $this->objectType();

        if ($type !== null && is_array($object[$type] ?? null)) {
            $object = $object[$type];   // {"type": "payment", "object": {"payment": {...}}} -> the payment
        }

        return $key === null ? $object : Arr::get($object, $key, $default);
    }

    /**
     * The Laravel event name dispatched for this notification, e.g. "square.payment.created".
     */
    public function eventName(): string
    {
        return 'square.'.$this->type;
    }

    /**
     * The SDK's typed event class for this notification, if it has one
     * (payment.created -> \Square\Types\PaymentCreatedEvent).
     */
    public function sdkEventClass(): ?string
    {
        $class = 'Square\\Types\\'.Str::studly(str_replace('.', '_', $this->type)).'Event';

        return class_exists($class) ? $class : null;
    }

    /**
     * The notification hydrated into the SDK's typed event, or null if the SDK has no class for it.
     */
    public function toSdkEvent(): ?object
    {
        $class = $this->sdkEventClass();

        return $class === null ? null : $class::fromJson($this->body);
    }
}
