<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Service;

use Useyona\EInvoice\Http\HttpClient;

/** Billing (`/b/v1`), grouped as the API tags it. */
final class BillingService
{
    /** The billing account and balance. */
    public readonly BillingAccountsService $accounts;
    /** Payments (read-only). */
    public readonly PaymentsService $payments;
    /** The sandbox ledger and allowance. */
    public readonly SandboxService $sandbox;
    /** Monthly statements. */
    public readonly StatementsService $statements;
    /** Subscriptions (read-only). */
    public readonly SubscriptionsService $subscriptions;
    /** The credit ledger and usage. */
    public readonly TransactionsService $transactions;

    public function __construct(HttpClient $http)
    {
        $this->accounts = new BillingAccountsService($http);
        $this->payments = new PaymentsService($http);
        $this->sandbox = new SandboxService($http);
        $this->statements = new StatementsService($http);
        $this->subscriptions = new SubscriptionsService($http);
        $this->transactions = new TransactionsService($http);
    }
}
