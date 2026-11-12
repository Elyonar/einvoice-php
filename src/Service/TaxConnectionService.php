<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Service;

use Useyona\EInvoice\RequestOptions;

/**
 * The tax connection (`/i/v1/tax-connection`): the organisation's link to the tax authority.
 *
 * @phpstan-import-type GetTaxConnectionQuery from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type GetTaxConnectionData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type ListChildTaxConnectionsItem from \Useyona\EInvoice\Generated\Types
 *
 * @phpstan-type SubOrganizationTaxConnectionsQuery array{ids: string|list<string>}
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
     * `state` can be `awaiting_authority_listing`: the business asked to connect, and the tax
     * authority does not list it under any of its numbers yet. Yona asks the authority again every 6
     * hours (`['fresh' => 'true']` asks now) and carries on by itself once it is listed; `listing`
     * says since when, when it was last asked and is asked next, and what the last answer was (`null`
     * in every other state). `connectWaits` says whether connecting now would wait like this instead
     * of being refused. Invoices issued meanwhile are held and reported once the connection completes.
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

    /**
     * For a parent organisation: the state of each named sub-organisation's connection to the tax
     * authority in the key's mode, and whether a submission would pass (`tax_connection.read`).
     * `ids` are the ids `organization->listSubOrganizations` answers, as a list or a comma-separated
     * string: at most 100, each once (400 `VAL001` otherwise). An id that is not one of the
     * organisation's sub-organisations is left out of the answer. Only the state is shown, never the
     * sub-organisation's tax id or key.
     *
     * @param SubOrganizationTaxConnectionsQuery $query
     *
     * @return list<ListChildTaxConnectionsItem>
     */
    public function listForSubOrganizations(array $query, ?RequestOptions $options = null): array
    {
        return $this->call('GET', '/i/v1/tax-connection/children', ['query' => self::withJoinedIds($query), 'options' => $options]);
    }
}
