<?php

namespace FLAIRUK\Square\Facades;

use Illuminate\Support\Facades\Facade;
use Square\SquareClient;

/**
 * @method static \Square\SquareClient client()
 * @method static \FLAIRUK\Square\Square forMerchant(string $accessToken)
 * @method static \FLAIRUK\Square\Environment environment()
 * @method static string|null locationId()
 * @method static string|null applicationId()
 * @method static string currency()
 * @method static \Square\Types\Money money(int|float|string $amount, ?string $currency = null)
 * @method static string idempotencyKey(string|int ...$parts)
 * @method static \FLAIRUK\Square\OAuth oauth()
 * @method static \FLAIRUK\Square\Webhooks\WebhookSignature webhookSignature()
 * @method static \Square\V1Transactions\V1TransactionsClient v1Transactions()
 * @method static \Square\ApplePay\ApplePayClient applePay()
 * @method static \Square\BankAccounts\BankAccountsClient bankAccounts()
 * @method static \Square\Bookings\BookingsClient bookings()
 * @method static \Square\Cards\CardsClient cards()
 * @method static \Square\Catalog\CatalogClient catalog()
 * @method static \Square\Channels\ChannelsClient channels()
 * @method static \Square\Customers\CustomersClient customers()
 * @method static \Square\Devices\DevicesClient devices()
 * @method static \Square\Disputes\DisputesClient disputes()
 * @method static \Square\Employees\EmployeesClient employees()
 * @method static \Square\Events\EventsClient events()
 * @method static \Square\GiftCards\GiftCardsClient giftCards()
 * @method static \Square\Inventory\InventoryClient inventory()
 * @method static \Square\Invoices\InvoicesClient invoices()
 * @method static \Square\Labor\LaborClient labor()
 * @method static \Square\Locations\LocationsClient locations()
 * @method static \Square\Loyalty\LoyaltyClient loyalty()
 * @method static \Square\Merchants\MerchantsClient merchants()
 * @method static \Square\Checkout\CheckoutClient checkout()
 * @method static \Square\Orders\OrdersClient orders()
 * @method static \Square\Payments\PaymentsClient payments()
 * @method static \Square\Payouts\PayoutsClient payouts()
 * @method static \Square\Refunds\RefundsClient refunds()
 * @method static \Square\Sites\SitesClient sites()
 * @method static \Square\Snippets\SnippetsClient snippets()
 * @method static \Square\Subscriptions\SubscriptionsClient subscriptions()
 * @method static \Square\TeamMembers\TeamMembersClient teamMembers()
 * @method static \Square\Team\TeamClient team()
 * @method static \Square\Terminal\TerminalClient terminal()
 * @method static \Square\TransferOrders\TransferOrdersClient transferOrders()
 * @method static \Square\Vendors\VendorsClient vendors()
 * @method static \Square\Reporting\ReportingClient reporting()
 * @method static \Square\CashDrawers\CashDrawersClient cashDrawers()
 * @method static \Square\Webhooks\WebhooksClient webhooks()
 *
 * @see \FLAIRUK\Square\Square
 * @see SquareClient
 */
class Square extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \FLAIRUK\Square\Square::class;
    }
}
