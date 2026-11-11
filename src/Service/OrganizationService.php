<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Service;

use Useyona\EInvoice\Page;
use Useyona\EInvoice\RequestOptions;

/**
 * The key's organisation (`/a/v1/organizations`): its profile and readiness, and its
 * sub-organisations. The profile is read-only for an API key.
 *
 * `ids` is a list of ids or the API's comma-separated string; a list reaches the API as one
 * comma-separated value. A sub-organisation takes its parent's country, so `country` is not part of
 * `CreateSubOrganizationParams`.
 *
 * @phpstan-import-type GetOrganizationData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type GetOrganizationReadinessData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type ListChildOrganizationsItem from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type ListChildOrganizationsReadinessItem from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type CreateOrganizationData from \Useyona\EInvoice\Generated\Types
 *
 * @phpstan-type ListSubOrganizationsQuery array{
 *     page?: int,
 *     limit?: int,
 *     search?: string,
 *     ids?: string|list<string>
 * }
 * @phpstan-type ListSubOrganizationsReadinessQuery array{
 *     page?: int,
 *     limit?: int,
 *     search?: string,
 *     ids?: string|list<string>
 * }
 * @phpstan-type CreateSubOrganizationParams array{
 *     businessName: string,
 *     email: string,
 *     phoneNumber?: string,
 *     website?: string
 * }
 */
final class OrganizationService extends BaseService
{
    /**
     * The organisation's profile; `$orgId` must be the key's own organisation (404 otherwise; `organization.read`).
     *
     * @return GetOrganizationData
     */
    public function get(string $orgId, ?RequestOptions $options = null): array
    {
        return $this->call('GET', '/a/v1/organizations/' . self::seg($orgId), ['options' => $options]);
    }

    /**
     * The onboarding checklist, the live-access status and the next step (`organization.read`).
     *
     * @return GetOrganizationReadinessData
     */
    public function getReadiness(?RequestOptions $options = null): array
    {
        return $this->call('GET', '/a/v1/organizations/me/readiness', ['options' => $options]);
    }

    /**
     * A page of the organisation's sub-organisations, newest first, with the total in `pagination`
     * (`organization.children.read`; sandbox and live). `search` keeps the ones whose name holds it;
     * `ids` keeps only the ones named (at most 100). Without `limit` a page holds 100, the most.
     * No key preset includes `organization.children.read`: it is added to a key by name, in the
     * dashboard's Create API Key dialog. 409 `BIZ014` when the key's organisation is itself a
     * sub-organisation.
     *
     * @param ListSubOrganizationsQuery|null $query
     *
     * @return Page<ListChildOrganizationsItem>
     */
    public function listSubOrganizations(?array $query = null, ?RequestOptions $options = null): Page
    {
        return $this->page('GET', '/a/v1/organizations/me/children', ['query' => self::withJoinedIds($query), 'options' => $options]);
    }

    /**
     * The same page as `listSubOrganizations`, with each sub-organisation's onboarding checklist,
     * live status and next step: which of a parent's businesses still have something to do
     * (`organization.children.read`; sandbox and live). Whether each is connected to the tax
     * authority is `taxConnection->listForSubOrganizations` with the same ids.
     *
     * @param ListSubOrganizationsReadinessQuery|null $query
     *
     * @return Page<ListChildOrganizationsReadinessItem>
     */
    public function listSubOrganizationsReadiness(?array $query = null, ?RequestOptions $options = null): Page
    {
        return $this->page('GET', '/a/v1/organizations/me/children/readiness', ['query' => self::withJoinedIds($query), 'options' => $options]);
    }

    /**
     * Creates a sub-organisation of the key's organisation (`organization.children.create`, which no
     * key preset includes; a live key only: a sandbox key is refused with 403 `BIZ003`). It is
     * recorded under the parent's longest-standing Owner and starts as `pending_verification`
     * until its own onboarding is completed. No `Idempotency-Key` is sent and the call is not
     * retried: a name is unique under its parent, so a repeated request answers 409 `RES002`, and
     * the organisation is found with `listSubOrganizations(['search' => …])`. 402 `BIZ016` when the
     * plan allows no more organisations; at most 60 a minute for each key.
     *
     * @param CreateSubOrganizationParams $params
     *
     * @return CreateOrganizationData
     */
    public function createSubOrganization(array $params, ?RequestOptions $options = null): array
    {
        return $this->call('POST', '/a/v1/organizations', ['body' => $params, 'options' => $options]);
    }
}
