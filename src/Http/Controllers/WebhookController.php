<?php

namespace FLAIRUK\Square\Http\Controllers;

use FLAIRUK\Square\Events\WebhookReceived;
use FLAIRUK\Square\Webhooks\WebhookEvent;
use Illuminate\Contracts\Cache\Factory as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Receives verified Square webhooks (see VerifyWebhookSignature) and dispatches
 * WebhookReceived plus a "square.{type}" event, e.g. "square.payment.updated".
 */
class WebhookController
{
    public function __invoke(Request $request, Dispatcher $events, Cache $cache, Config $config): Response
    {
        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload) || ! is_string($payload['type'] ?? null) || $payload['type'] === '') {
            return new Response('Invalid Square webhook payload.', 400);
        }

        $event = WebhookEvent::fromPayload($payload, $request->getContent());

        $deduplicate = $config->get('square.webhooks.deduplicate') && $event->eventId !== null;
        $store = $cache->store($config->get('square.webhooks.cache_store'));
        $cacheKey = 'square:webhook:'.$event->eventId;

        if ($deduplicate && $store->has($cacheKey)) {
            return new Response('Already processed.', 200);
        }

        $events->dispatch(new WebhookReceived($event));
        $events->dispatch($event->eventName(), [$event]);

        if ($deduplicate) {
            $store->put($cacheKey, true, (int) $config->get('square.webhooks.deduplicate_hours', 48) * 3600);
        }

        return new Response('OK', 200);
    }
}
