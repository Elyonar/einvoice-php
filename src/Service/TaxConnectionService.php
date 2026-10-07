<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Service;

use Useyona\EInvoice\RequestOptions;

/**
 * The tax connection (`/i/v1/tax-connection`): the organisation's link to the tax authority.
 *
 * @phpstan-import-type GetTaxConnectionData from \Useyona\EInvoice\Generated\Types
 */
final class TaxConnectionService extends BaseService
{
    /**
     * The connection's state in the key's mode, its key and the next step (`tax_connection.read`).
     *
     * @return GetTaxConnectionData
     */
    public function get(?RequestOptions $options = null): array
    {
        return $this->call('GET', '/i/v1/tax-connection', ['options' => $options]);
    }
}
