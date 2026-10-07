<?php

declare(strict_types=1);

namespace Useyona\EInvoice;

/**
 * Operations an API key may call that the SDK deliberately has no method for, each with its
 * reason. tests/ParityTest.php requires every API-key operation in
 * scripts/openapi-api-key-ops.json to be either an SDK method or listed here, and nothing here
 * to be missing from the snapshot.
 */
final class ExcludedOperations
{
    /** @var list<array{method: string, path: string, reason: string}> */
    public const OPERATIONS = [
        ['method' => 'POST', 'path' => '/i/v1/sellers', 'reason' => 'Always 409 BIZ205: the organisation is its only seller.'],
        ['method' => 'PATCH', 'path' => '/i/v1/sellers/{id}', 'reason' => 'Always 409 BIZ205: the seller is edited as the organisation profile in the dashboard.'],
        ['method' => 'DELETE', 'path' => '/i/v1/sellers/{id}', 'reason' => 'Always 409 BIZ205: the organisation is its only seller.'],
        ['method' => 'POST', 'path' => '/i/v1/sellers/{id}/verify-tax-number', 'reason' => 'Always 409 BIZ205: the TIN is verified in organisation settings.'],
        ['method' => 'GET', 'path' => '/i/v1/sellers/setup', 'reason' => 'Dashboard form metadata; the seller itself is sellers.get/list.'],
        ['method' => 'GET', 'path' => '/i/v1/buyers/setup', 'reason' => 'Dashboard form metadata; the code lists are reference.getResource.'],
        ['method' => 'GET', 'path' => '/i/v1/invoices/setup', 'reason' => 'Dashboard form metadata; the code lists are reference.getResource, the connection taxConnection.get.'],
        ['method' => 'GET', 'path' => '/b/v1/setup', 'reason' => 'Dashboard billing overview in one call; its parts are billing.accounts/subscriptions/sandbox.'],
    ];

    private function __construct()
    {
    }
}
