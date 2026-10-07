<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Service;

use Useyona\EInvoice\RequestOptions;

/**
 * The key's organisation (`/a/v1/organizations`): its profile and readiness. Read-only for an API key.
 *
 * @phpstan-import-type GetOrganizationData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type GetOrganizationReadinessData from \Useyona\EInvoice\Generated\Types
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
}
