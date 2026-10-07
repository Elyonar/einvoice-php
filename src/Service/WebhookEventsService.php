<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Service;

use Useyona\EInvoice\Page;
use Useyona\EInvoice\RequestOptions;

/**
 * Logged webhook events (`/n/v1/webhook-events`, last 30 days).
 *
 * @phpstan-import-type ListWebhookEventsQuery from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type ListWebhookEventsItem from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type GetWebhookEventData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type RedeliverWebhookEventBody from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type RedeliverWebhookEventData from \Useyona\EInvoice\Generated\Types
 */
final class WebhookEventsService extends BaseService
{
    /**
     * Lists events of the key's mode plus organisation-scoped ones (`webhook.delivery.read`).
     *
     * @param ListWebhookEventsQuery|null $query
     *
     * @return Page<ListWebhookEventsItem>
     */
    public function list(?array $query = null, ?RequestOptions $options = null): Page
    {
        return $this->page('GET', '/n/v1/webhook-events', ['query' => $query, 'options' => $options]);
    }

    /**
     * One event with its public `data` (`webhook.delivery.read`).
     *
     * @return GetWebhookEventData
     */
    public function get(string $id, ?RequestOptions $options = null): array
    {
        return $this->call('GET', '/n/v1/webhook-events/' . self::seg($id), ['options' => $options]);
    }

    /**
     * Replays an event to one endpoint (`webhook.delivery.redeliver`; 202).
     *
     * @param RedeliverWebhookEventBody $params
     *
     * @return RedeliverWebhookEventData
     */
    public function redeliver(string $id, array $params, ?RequestOptions $options = null): array
    {
        return $this->call('POST', '/n/v1/webhook-events/' . self::seg($id) . '/redeliver', ['body' => $params, 'options' => $options]);
    }
}
