<?php

declare(strict_types=1);

namespace Useyona\EInvoice;

use Useyona\EInvoice\Http\HttpClient;
use Useyona\EInvoice\Service\BillingService;
use Useyona\EInvoice\Service\BuyersService;
use Useyona\EInvoice\Service\InboundInvoicesService;
use Useyona\EInvoice\Service\InvoiceSettingsService;
use Useyona\EInvoice\Service\InvoicesService;
use Useyona\EInvoice\Service\IssuedHistoryService;
use Useyona\EInvoice\Service\ItemsService;
use Useyona\EInvoice\Service\OrganizationService;
use Useyona\EInvoice\Service\OutputService;
use Useyona\EInvoice\Service\ReferenceService;
use Useyona\EInvoice\Service\SellersService;
use Useyona\EInvoice\Service\ShareLinksService;
use Useyona\EInvoice\Service\SubmissionsService;
use Useyona\EInvoice\Service\TaxConnectionService;
use Useyona\EInvoice\Service\WebhooksService;

/**
 * The client of the Yona e-invoicing API. It covers what an API key may call: invoicing, buyers,
 * items, received invoices, billing reads and webhooks. Users, invitations, roles, API keys and
 * organisation management are done in the dashboard.
 *
 * The key decides the mode: `sk_test_…` is sandbox and `sk_live_…` is live, on the same host.
 *
 * ```php
 * use Useyona\EInvoice\EInvoice;
 *
 * $client = new EInvoice(['api_key' => getenv('YONA_API_KEY')]);
 * $client->mode(); // 'sandbox' | 'live'
 * $invoice = $client->invoices->create([…]);
 * ```
 *
 * @phpstan-import-type HttpClientConfig from HttpClient
 */
final class EInvoice
{
    /** Invoices: drafts, lifecycle, credit and debit notes, statistics. */
    public readonly InvoicesService $invoices;
    /** Submissions to the tax authority and their status. */
    public readonly SubmissionsService $submissions;
    /** The invoice PDF and sending it to the buyer. */
    public readonly OutputService $output;
    /** Revocable share links to an invoice. */
    public readonly ShareLinksService $shareLinks;
    /** Saved items (goods and services). */
    public readonly ItemsService $items;
    /** Code lists, HS codes, tax-id lookup and dry-run validation. */
    public readonly ReferenceService $reference;
    /** Buyers. */
    public readonly BuyersService $buyers;
    /** The organisation's seller (read-only). */
    public readonly SellersService $sellers;
    /** Invoices other businesses issued to the organisation. */
    public readonly InboundInvoicesService $inboundInvoices;
    /** Invoices the organisation issued outside Yona that the tax authority holds. */
    public readonly IssuedHistoryService $issuedHistory;
    /** Invoice settings (read-only). */
    public readonly InvoiceSettingsService $invoiceSettings;
    /** The connection to the tax authority (read-only). */
    public readonly TaxConnectionService $taxConnection;
    /** The key's organisation (read-only). */
    public readonly OrganizationService $organization;
    /** Billing reads: account, ledger, statements, subscriptions, payments, sandbox allowance. */
    public readonly BillingService $billing;
    /** Webhook endpoints (read, test), deliveries, events and the event catalogue. */
    public readonly WebhooksService $webhooks;
    /** The transport, for advanced use. */
    public readonly HttpClient $http;

    /**
     * Creates a client. Only `api_key` is required; see {@see HttpClient} for every option.
     * Throws `ConfigException` for a missing or malformed key, an unknown option, or when
     * `assert_mode` does not match the key.
     *
     * @param HttpClientConfig $config
     */
    public function __construct(array $config)
    {
        $this->http = new HttpClient($config);
        $this->invoices = new InvoicesService($this->http);
        $this->submissions = new SubmissionsService($this->http);
        $this->output = new OutputService($this->http);
        $this->shareLinks = new ShareLinksService($this->http);
        $this->items = new ItemsService($this->http);
        $this->reference = new ReferenceService($this->http);
        $this->buyers = new BuyersService($this->http);
        $this->sellers = new SellersService($this->http);
        $this->inboundInvoices = new InboundInvoicesService($this->http);
        $this->issuedHistory = new IssuedHistoryService($this->http);
        $this->invoiceSettings = new InvoiceSettingsService($this->http);
        $this->taxConnection = new TaxConnectionService($this->http);
        $this->organization = new OrganizationService($this->http);
        $this->billing = new BillingService($this->http);
        $this->webhooks = new WebhooksService($this->http);
    }

    /** `'sandbox'` for an `sk_test_` key, `'live'` for an `sk_live_` key. */
    public function mode(): string
    {
        return $this->http->mode;
    }

    /** The host requests go to. */
    public function baseUrl(): string
    {
        return $this->http->baseUrl;
    }

    /**
     * Iterates every item of a paged list, fetching page after page (see {@see Paginate::all()}).
     *
     * ```php
     * foreach (EInvoice::paginate(fn (array $q) => $client->buyers->list($q), ['limit' => 100]) as $buyer) { … }
     * ```
     *
     * @template T
     *
     * @param callable(array<string, mixed>): Page<T> $list
     * @param array<string, mixed>                    $query
     *
     * @return \Generator<int, T, mixed, void>
     */
    public static function paginate(callable $list, array $query = []): \Generator
    {
        return Paginate::all($list, $query);
    }
}
