<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Service;

use Useyona\EInvoice\RequestOptions;

/**
 * Delivery health (`/n/v1/webhook-health`): how the organisation's webhooks have been arriving.
 *
 * @phpstan-import-type GetWebhookHealthQuery from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type GetWebhookHealthData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type GetWebhookHealthDailyQuery from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type GetWebhookHealthDailyData from \Useyona\EInvoice\Generated\Types
 */
final class WebhookHealthService extends BaseService
{
    /**
     * Deliveries between `from` and `to` (31 days at most), counted for each endpoint and event
     * type (`webhook.delivery.read`).
     *
     * @param GetWebhookHealthQuery $query
     *
     * @return GetWebhookHealthData
     */
    public function get(array $query, ?RequestOptions $options = null): array
    {
        return $this->call('GET', '/n/v1/webhook-health', ['query' => $query, 'options' => $options]);
    }

    /**
     * Deliveries for each UTC day between `from` and `to`, 31 days at most; `endpointId` keeps one
     * endpoint (`webhook.delivery.read`).
     *
     * @param GetWebhookHealthDailyQuery $query
     *
     * @return GetWebhookHealthDailyData
     */
    public function getDaily(array $query, ?RequestOptions $options = null): array
    {
        return $this->call('GET', '/n/v1/webhook-health/daily', ['query' => $query, 'options' => $options]);
    }
}
