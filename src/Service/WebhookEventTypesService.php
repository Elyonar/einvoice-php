<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Service;

use Useyona\EInvoice\RequestOptions;

/**
 * The webhook event catalogue (`/n/v1/webhook-event-types`).
 *
 * @phpstan-import-type ListWebhookEventTypesItem from \Useyona\EInvoice\Generated\Types
 */
final class WebhookEventTypesService extends BaseService
{
    /**
     * Every event type, with whether this key may subscribe to it.
     *
     * @return list<ListWebhookEventTypesItem>
     */
    public function list(?RequestOptions $options = null): array
    {
        return $this->call('GET', '/n/v1/webhook-event-types', ['options' => $options]);
    }
}
