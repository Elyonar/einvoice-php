<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Service;

use Useyona\EInvoice\RequestOptions;

/**
 * The partner account (`/b/v1/partner`): an Integrated Partner's tier, seats and their quotes.
 * Read-only for an API key.
 *
 * @phpstan-import-type GetPartnerData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type GetPartnerOfferData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type GetPartnerSeatQuoteQuery from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type GetPartnerSeatQuoteData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type GetPartnerBasketQuoteQuery from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type GetPartnerBasketQuoteData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type GetPartnerPlanChangeQuoteQuery from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type GetPartnerPlanChangeQuoteData from \Useyona\EInvoice\Generated\Types
 */
final class PartnerService extends BaseService
{
    /**
     * The partner account: tier, seats, capacity and renewal (`billing.subscription.read`).
     *
     * @return GetPartnerData
     */
    public function get(?RequestOptions $options = null): array
    {
        return $this->call('GET', '/b/v1/partner', ['options' => $options]);
    }

    /**
     * The partner tier the organisation may buy (`billing.subscription.read`); `null` when there is
     * no offer.
     *
     * @return GetPartnerOfferData
     */
    public function getOffer(?RequestOptions $options = null): ?array
    {
        return $this->call('GET', '/b/v1/partner/offer', ['options' => $options]);
    }

    /**
     * What one business seat on `plan` would cost; writes nothing (`billing.subscription.read`).
     *
     * @param GetPartnerSeatQuoteQuery $query
     *
     * @return GetPartnerSeatQuoteData
     */
    public function quoteSeat(array $query, ?RequestOptions $options = null): array
    {
        return $this->call('GET', '/b/v1/partner/seats/quote', ['query' => $query, 'options' => $options]);
    }

    /**
     * What seats for several businesses would cost together; writes nothing (`billing.subscription.read`).
     *
     * @param GetPartnerBasketQuoteQuery $query
     *
     * @return GetPartnerBasketQuoteData
     */
    public function quoteBasket(array $query, ?RequestOptions $options = null): array
    {
        return $this->call('GET', '/b/v1/partner/seats/basket/quote', ['query' => $query, 'options' => $options]);
    }

    /**
     * What moving one business to `plan` would cost; writes nothing (`billing.subscription.read`).
     *
     * @param GetPartnerPlanChangeQuoteQuery $query
     *
     * @return GetPartnerPlanChangeQuoteData
     */
    public function quotePlanChange(string $organizationId, array $query, ?RequestOptions $options = null): array
    {
        return $this->call('GET', '/b/v1/partner/businesses/' . self::seg($organizationId) . '/change-plan/quote', ['query' => $query, 'options' => $options]);
    }
}
