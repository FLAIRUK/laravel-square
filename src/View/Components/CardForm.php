<?php

namespace FLAIRUK\Square\View\Components;

use FLAIRUK\Square\Exceptions\ConfigurationException;
use FLAIRUK\Square\Square;
use Illuminate\View\Component;

/**
 * <x-square::card-form />: the Web Payments SDK card field.
 *
 * Put it inside your <form>. On submit it tokenizes the card, puts the token
 * in a hidden input (default name "source_id") and submits the form; use the
 * token as the sourceId of a CreatePaymentRequest.
 *
 * @see https://developer.squareup.com/docs/web-payments/take-card-payment
 */
class CardForm extends Component
{
    public string $applicationId;

    public string $locationId;

    public string $scriptUrl;

    /**
     * @param  array<string, mixed>|null  $verification  verification details passed to card.tokenize()
     *                                                   (amount, currencyCode, intent, billingContact, ...)
     */
    public function __construct(
        Square $square,
        ?string $applicationId = null,
        ?string $locationId = null,
        public string $name = 'source_id',
        public string $id = 'square-card',
        public ?array $verification = null,
    ) {
        $this->applicationId = $applicationId
            ?? $square->applicationId()
            ?? throw new ConfigurationException('The Square card form needs an application ID: set SQUARE_APPLICATION_ID.');

        $this->locationId = $locationId
            ?? $square->locationId()
            ?? throw new ConfigurationException('The Square card form needs a location ID: set SQUARE_LOCATION_ID.');

        $this->scriptUrl = $square->environment()->webPaymentsScriptUrl();
    }

    public function render(): string
    {
        return 'square::components.card-form';
    }
}
