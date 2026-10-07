<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Service;

use Useyona\EInvoice\Page;
use Useyona\EInvoice\RequestOptions;

/**
 * Payments (`/b/v1/payments`): read-only for an API key (purchases are made in the dashboard).
 *
 * @phpstan-import-type ListPaymentsQuery from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type ListPaymentsItem from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type GetPaymentData from \Useyona\EInvoice\Generated\Types
 */
final class PaymentsService extends BaseService
{
    /**
     * Lists payments, newest first (`billing.payment.read`).
     *
     * @param ListPaymentsQuery|null $query
     *
     * @return Page<ListPaymentsItem>
     */
    public function list(?array $query = null, ?RequestOptions $options = null): Page
    {
        return $this->page('GET', '/b/v1/payments/history', ['query' => $query, 'options' => $options]);
    }

    /**
     * One payment (`billing.payment.read`).
     *
     * @return GetPaymentData
     */
    public function get(string $id, ?RequestOptions $options = null): array
    {
        return $this->call('GET', '/b/v1/payments/' . self::seg($id), ['options' => $options]);
    }
}
