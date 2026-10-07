<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Service;

use Useyona\EInvoice\RequestOptions;

/**
 * Webhook endpoints (`/n/v1/webhook-endpoints`). An API key may read and test them; creating,
 * changing, deleting and rotating an endpoint's secret are done in the dashboard.
 *
 * @phpstan-import-type ListWebhookEndpointsItem from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type GetWebhookEndpointData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type TestWebhookEndpointBody from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type TestWebhookEndpointData from \Useyona\EInvoice\Generated\Types
 */
final class WebhookEndpointsService extends BaseService
{
    /**
     * The endpoints of the key's organisation and mode; never a secret (`webhook.endpoint.read`).
     *
     * @return list<ListWebhookEndpointsItem>
     */
    public function list(?RequestOptions $options = null): array
    {
        return $this->call('GET', '/n/v1/webhook-endpoints', ['options' => $options]);
    }

    /**
     * One endpoint, never its secret (`webhook.endpoint.read`).
     *
     * @return GetWebhookEndpointData
     */
    public function get(string $id, ?RequestOptions $options = null): array
    {
        return $this->call('GET', '/n/v1/webhook-endpoints/' . self::seg($id), ['options' => $options]);
    }

    /**
     * Sends one test delivery (`"test": true`; never charged; `webhook.endpoint.test`). An empty
     * `$params` is sent as `{}`.
     *
     * @param TestWebhookEndpointBody|array{} $params
     *
     * @return TestWebhookEndpointData
     */
    public function test(string $id, array $params = [], ?RequestOptions $options = null): array
    {
        return $this->call('POST', '/n/v1/webhook-endpoints/' . self::seg($id) . '/test', ['body' => $params, 'options' => $options]);
    }
}
