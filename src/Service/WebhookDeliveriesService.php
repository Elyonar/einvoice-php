<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Service;

use Useyona\EInvoice\Page;
use Useyona\EInvoice\RequestOptions;

/**
 * Webhook deliveries (`/n/v1/webhook-deliveries`): one per event × endpoint, with its attempts.
 *
 * @phpstan-import-type ListWebhookDeliveriesQuery from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type ListWebhookDeliveriesItem from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type GetWebhookDeliveryData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type RedeliverWebhookDeliveryData from \Useyona\EInvoice\Generated\Types
 */
final class WebhookDeliveriesService extends BaseService
{
    /**
     * Lists deliveries by endpoint, status or type (`webhook.delivery.read`).
     *
     * @param ListWebhookDeliveriesQuery|null $query
     *
     * @return Page<ListWebhookDeliveriesItem>
     */
    public function list(?array $query = null, ?RequestOptions $options = null): Page
    {
        return $this->page('GET', '/n/v1/webhook-deliveries', ['query' => $query, 'options' => $options]);
    }

    /**
     * One delivery and its attempt log (`webhook.delivery.read`).
     *
     * @return GetWebhookDeliveryData
     */
    public function get(string $id, ?RequestOptions $options = null): array
    {
        return $this->call('GET', '/n/v1/webhook-deliveries/' . self::seg($id), ['options' => $options]);
    }

    /**
     * One more attempt with the stored bytes (`webhook.delivery.redeliver`; 202).
     *
     * @return RedeliverWebhookDeliveryData
     */
    public function redeliver(string $id, ?RequestOptions $options = null): array
    {
        return $this->call('POST', '/n/v1/webhook-deliveries/' . self::seg($id) . '/redeliver', ['options' => $options]);
    }
}
