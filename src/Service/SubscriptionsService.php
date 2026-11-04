<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Service;

use Useyona\EInvoice\Page;
use Useyona\EInvoice\RequestOptions;

/**
 * Subscriptions (`/b/v1/subscriptions`): read-only for an API key.
 *
 * @phpstan-import-type GetActiveSubscriptionData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type ListSubscriptionsQuery from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type ListSubscriptionsItem from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type GetSubscriptionData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type ListSubscriptionRenewalsQuery from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type ListSubscriptionRenewalsItem from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type PreviewSubscriptionPlanChangeQuery from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type PreviewSubscriptionPlanChangeData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type GetDealOfferData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type GetSubOrgSlotQuoteData from \Useyona\EInvoice\Generated\Types
 */
final class SubscriptionsService extends BaseService
{
    /**
     * The active or past-due subscription and its allocation window (`billing.subscription.read`).
     *
     * @return GetActiveSubscriptionData
     */
    public function getActive(?RequestOptions $options = null): array
    {
        return $this->call('GET', '/b/v1/subscriptions/active', ['options' => $options]);
    }

    /**
     * Every subscription, newest first (`billing.subscription.read`).
     *
     * @param ListSubscriptionsQuery|null $query
     *
     * @return Page<ListSubscriptionsItem>
     */
    public function list(?array $query = null, ?RequestOptions $options = null): Page
    {
        return $this->page('GET', '/b/v1/subscriptions/history', ['query' => $query, 'options' => $options]);
    }

    /**
     * One subscription (`billing.subscription.read`).
     *
     * @return GetSubscriptionData
     */
    public function get(string $id, ?RequestOptions $options = null): array
    {
        return $this->call('GET', '/b/v1/subscriptions/' . self::seg($id), ['options' => $options]);
    }

    /**
     * The billing periods and how each was charged (`billing.subscription.read`).
     *
     * @param ListSubscriptionRenewalsQuery|null $query
     *
     * @return Page<ListSubscriptionRenewalsItem>
     */
    public function listRenewals(?array $query = null, ?RequestOptions $options = null): Page
    {
        return $this->page('GET', '/b/v1/subscriptions/renewals', ['query' => $query, 'options' => $options]);
    }

    /**
     * What a plan change would cost now and at period end; writes nothing (`billing.subscription.read`).
     *
     * @param PreviewSubscriptionPlanChangeQuery $query
     *
     * @return PreviewSubscriptionPlanChangeData
     */
    public function previewPlanChange(string $id, array $query, ?RequestOptions $options = null): array
    {
        return $this->call('GET', '/b/v1/subscriptions/' . self::seg($id) . '/change-plan/preview', ['query' => $query, 'options' => $options]);
    }

    /**
     * The deal offered to the organisation, if any (`billing.subscription.read`); `null` when there
     * is none.
     *
     * @return GetDealOfferData
     */
    public function getDealOffer(?RequestOptions $options = null): ?array
    {
        return $this->call('GET', '/b/v1/deals/offer', ['options' => $options]);
    }

    /**
     * What one more sub-organisation slot would cost; writes nothing (`billing.subscription.read`).
     *
     * @return GetSubOrgSlotQuoteData
     */
    public function quoteSubOrgSlot(?RequestOptions $options = null): array
    {
        return $this->call('GET', '/b/v1/sub-org-slots/quote', ['options' => $options]);
    }
}
