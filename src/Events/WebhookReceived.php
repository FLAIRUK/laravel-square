<?php

namespace FLAIRUK\Square\Events;

use FLAIRUK\Square\Webhooks\WebhookEvent;

/**
 * Dispatched for every verified Square webhook notification, before the
 * per-type "square.{type}" event.
 */
final readonly class WebhookReceived
{
    public function __construct(public WebhookEvent $event) {}
}
