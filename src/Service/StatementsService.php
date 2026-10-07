<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Service;

use Useyona\EInvoice\RequestOptions;

/**
 * Monthly statements (`/b/v1/statements`).
 *
 * @phpstan-import-type GetBillingStatementQuery from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type GetBillingStatementData from \Useyona\EInvoice\Generated\Types
 */
final class StatementsService extends BaseService
{
    /**
     * One UTC month (`YYYY-MM`) of the key's own mode's ledger (`billing.statement.read`).
     *
     * @param GetBillingStatementQuery|null $query
     *
     * @return GetBillingStatementData
     */
    public function get(string $period, ?array $query = null, ?RequestOptions $options = null): array
    {
        return $this->call('GET', '/b/v1/statements/' . self::seg($period), ['query' => $query, 'options' => $options]);
    }
}
