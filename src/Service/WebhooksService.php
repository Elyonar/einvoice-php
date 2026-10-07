<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Service;

use Useyona\EInvoice\Http\HttpClient;

/** Webhooks (`/n/v1`), grouped as the API tags it. Verify deliveries with `Webhooks::verifyWebhook()`. */
final class WebhooksService
{
    /** Endpoints (read and test). */
    public readonly WebhookEndpointsService $endpoints;
    /** Deliveries and redelivery. */
    public readonly WebhookDeliveriesService $deliveries;
    /** Logged events and replay. */
    public readonly WebhookEventsService $events;
    /** The event catalogue. */
    public readonly WebhookEventTypesService $eventTypes;

    public function __construct(HttpClient $http)
    {
        $this->endpoints = new WebhookEndpointsService($http);
        $this->deliveries = new WebhookDeliveriesService($http);
        $this->events = new WebhookEventsService($http);
        $this->eventTypes = new WebhookEventTypesService($http);
    }
}
