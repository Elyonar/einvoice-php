<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Service;

use Useyona\EInvoice\RequestOptions;

/**
 * The plan catalogue (`/b/v1/plans`).
 *
 * @phpstan-import-type GetPlanRecommendationData from \Useyona\EInvoice\Generated\Types
 */
final class PlansService extends BaseService
{
    /**
     * The plan recommended for the organisation from its usage (`billing.subscription.read`);
     * `null` when there is no recommendation.
     *
     * @return GetPlanRecommendationData
     */
    public function getRecommendation(?RequestOptions $options = null): ?array
    {
        return $this->call('GET', '/b/v1/plans/recommendation', ['options' => $options]);
    }
}
