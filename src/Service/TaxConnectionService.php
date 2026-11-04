<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Service;

use Useyona\EInvoice\RequestOptions;

/**
 * The tax connection (`/i/v1/tax-connection`): the organisation's link to the tax authority.
 *
 * @phpstan-import-type GetTaxConnectionQuery from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type GetTaxConnectionData from \Useyona\EInvoice\Generated\Types
 */
final class TaxConnectionService extends BaseService
{
    /**
     * The connection's state in the key's mode, its key and the next step (`tax_connection.read`).
     * `['identifier' => 'true']` also resolves which number the tax authority holds for the
     * organisation (`einvoicingIdentifier`, or `identifierNextAction` when it holds none);
     * `['fresh' => 'true']` asks the authority again. Both are rationed: pass them only when that
     * answer is needed.
     *
     * The options-only call of 0.1, `get($options)`, still works.
     *
     * @param GetTaxConnectionQuery|RequestOptions|null $query
     *
     * @return GetTaxConnectionData
     */
    public function get(array|RequestOptions|null $query = null, ?RequestOptions $options = null): array
    {
        if ($query instanceof RequestOptions) {
            [$query, $options] = [null, $options ?? $query];
        }

        return $this->call('GET', '/i/v1/tax-connection', ['query' => $query, 'options' => $options]);
    }
}
