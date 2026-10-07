<?php

declare(strict_types=1);

namespace Useyona\EInvoice;

/**
 * The webhook event catalogue as of 0.8.0 (the API's `GET /n/v1/webhook-event-types` is
 * authoritative; new types may arrive, so any other string must be accepted too).
 *
 * The body of a delivery (`WebhookEvent`) is serialised once and every attempt sends the same bytes:
 *
 * @phpstan-type WebhookEvent array{
 *     id: string,
 *     type: string,
 *     version: int,
 *     createdAt: string,
 *     mode: 'sandbox'|'live',
 *     organizationId: string,
 *     data: array<string, mixed>,
 *     test?: true
 * }
 */
final class WebhookEventType
{
    public const INVOICE_CREATED = 'invoice.created';
    public const INVOICE_UPDATED = 'invoice.updated';
    public const INVOICE_REOPENED = 'invoice.reopened';
    public const INVOICE_DELETED = 'invoice.deleted';
    public const INVOICE_FINALISED = 'invoice.finalised';
    public const INVOICE_SUBMITTED = 'invoice.submitted';
    public const INVOICE_SIGNED = 'invoice.signed';
    public const INVOICE_TRANSMITTED = 'invoice.transmitted';
    public const INVOICE_ACCEPTED = 'invoice.accepted';
    public const INVOICE_REJECTED = 'invoice.rejected';
    public const INVOICE_PARKED = 'invoice.parked';
    public const INVOICE_RETRY_SCHEDULED = 'invoice.retry_scheduled';
    public const INVOICE_CANCELLATION_REQUESTED = 'invoice.cancellation_requested';
    public const INVOICE_CANCELLATION_REVERTED = 'invoice.cancellation_reverted';
    public const INVOICE_CANCELLED = 'invoice.cancelled';
    public const INVOICE_SENT_TO_BUYER = 'invoice.sent_to_buyer';
    public const INVOICE_PAYMENT_STATUS_UPDATED = 'invoice.payment_status.updated';
    public const INVOICE_PAYMENT_STATUS_REPORT_REFUSED = 'invoice.payment_status.report_refused';
    public const INVOICE_BUYER_DELIVERY_FAILED = 'invoice.buyer_delivery_failed';
    public const INVOICE_RECEIVED = 'invoice.received';
    public const BUYER_CREATED = 'buyer.created';
    public const BUYER_UPDATED = 'buyer.updated';
    public const BUYER_DELETED = 'buyer.deleted';
    public const BUYER_TAX_ID_VERIFIED = 'buyer.tax_id.verified';
    public const BUYER_TAX_ID_VERIFICATION_FAILED = 'buyer.tax_id.verification_failed';
    public const BUYER_TAX_ID_VERIFICATION_UNAVAILABLE = 'buyer.tax_id.verification_unavailable';
    public const SELLER_CREATED = 'seller.created';
    public const SELLER_UPDATED = 'seller.updated';
    public const SELLER_DELETED = 'seller.deleted';
    public const SELLER_TAX_ID_VERIFIED = 'seller.tax_id.verified';
    public const SELLER_TAX_ID_VERIFICATION_FAILED = 'seller.tax_id.verification_failed';
    public const SELLER_TAX_ID_VERIFICATION_UNAVAILABLE = 'seller.tax_id.verification_unavailable';
    public const TAX_CONNECTION_ACTION_REQUIRED = 'tax_connection.action_required';
    public const TAX_CONNECTION_CONNECTED = 'tax_connection.connected';
    public const TAX_CONNECTION_SUSPENDED = 'tax_connection.suspended';
    public const TAX_CONNECTION_DISCONNECTED = 'tax_connection.disconnected';
    public const TAX_CONNECTION_KEY_INSTALLED = 'tax_connection.key_installed';
    public const TAX_CONNECTION_KEY_EXPIRING = 'tax_connection.key_expiring';
    public const TAX_CONNECTION_KEY_EXPIRED = 'tax_connection.key_expired';
    public const ORGANIZATION_UPDATED = 'organization.updated';
    public const ORGANIZATION_TAX_ID_VERIFIED = 'organization.tax_id.verified';
    public const ORGANIZATION_TAX_ID_VERIFICATION_FAILED = 'organization.tax_id.verification_failed';
    public const ORGANIZATION_TAX_ID_VERIFICATION_UNAVAILABLE = 'organization.tax_id.verification_unavailable';
    public const ORGANIZATION_PHONE_VERIFIED = 'organization.phone.verified';
    public const ORGANIZATION_LOGO_CHANGED = 'organization.logo.changed';
    public const ORGANIZATION_ONBOARDING_COMPLETED = 'organization.onboarding_completed';
    public const ORGANIZATION_OWNERSHIP_TRANSFERRED = 'organization.ownership_transferred';
    public const ORGANIZATION_LIVE_ACCESS_REQUESTED = 'organization.live_access.requested';
    public const ORGANIZATION_LIVE_ACCESS_APPROVED = 'organization.live_access.approved';
    public const ORGANIZATION_LIVE_ACCESS_REJECTED = 'organization.live_access.rejected';
    public const ORGANIZATION_LIVE_ACCESS_REVOKED = 'organization.live_access.revoked';
    public const ORGANIZATION_CLOSED = 'organization.closed';
    public const ORGANIZATION_CHILD_CREATED = 'organization.child.created';
    public const MEMBER_JOINED = 'member.joined';
    public const MEMBER_ROLE_CHANGED = 'member.role_changed';
    public const MEMBER_SUSPENDED = 'member.suspended';
    public const MEMBER_RESTORED = 'member.restored';
    public const MEMBER_REMOVED = 'member.removed';
    public const INVITATION_CREATED = 'invitation.created';
    public const INVITATION_RESENT = 'invitation.resent';
    public const INVITATION_WITHDRAWN = 'invitation.withdrawn';
    public const INVITATION_ACCEPTED = 'invitation.accepted';
    public const INVITATION_EXPIRED = 'invitation.expired';
    public const INVITATION_LOCKED = 'invitation.locked';
    public const ROLE_CREATED = 'role.created';
    public const ROLE_UPDATED = 'role.updated';
    public const ROLE_DELETED = 'role.deleted';
    public const API_KEY_CREATED = 'api_key.created';
    public const API_KEY_ROTATED = 'api_key.rotated';
    public const API_KEY_UPDATED = 'api_key.updated';
    public const API_KEY_REVOKED = 'api_key.revoked';
    public const API_KEY_EXPIRED = 'api_key.expired';
    public const API_KEY_FLAGGED = 'api_key.flagged';
    public const API_KEY_EXPIRING = 'api_key.expiring';
    public const BILLING_CREDITS_GRANTED = 'billing.credits.granted';
    public const BILLING_CREDITS_EXPIRED = 'billing.credits.expired';
    public const BILLING_CREDITS_ADJUSTED = 'billing.credits.adjusted';
    public const BILLING_CHARGE_SETTLED = 'billing.charge.settled';
    public const BILLING_CHARGE_REFUSED = 'billing.charge.refused';
    public const BILLING_CHARGE_REFUNDED = 'billing.charge.refunded';
    public const BILLING_HOLD_PLACED = 'billing.hold.placed';
    public const BILLING_HOLD_CAPTURED = 'billing.hold.captured';
    public const BILLING_HOLD_RELEASED = 'billing.hold.released';
    public const BILLING_CREDITS_TRANSFERRED = 'billing.credits.transferred';
    public const BILLING_POOL_DRAWN = 'billing.pool.drawn';
    public const BILLING_POOL_RETURNED = 'billing.pool.returned';
    public const BILLING_POOL_POLICY_UPDATED = 'billing.pool_policy.updated';
    public const BILLING_BALANCE_LOW = 'billing.balance.low';
    public const BILLING_BALANCE_RECOVERED = 'billing.balance.recovered';
    public const BILLING_LOW_BALANCE_THRESHOLD_UPDATED = 'billing.low_balance_threshold.updated';
    public const BILLING_DEBT_RECORDED = 'billing.debt.recorded';
    public const BILLING_DEBT_SETTLED = 'billing.debt.settled';
    public const BILLING_PAYMENT_INITIATED = 'billing.payment.initiated';
    public const BILLING_PAYMENT_SUCCEEDED = 'billing.payment.succeeded';
    public const BILLING_PAYMENT_FAILED = 'billing.payment.failed';
    public const BILLING_PAYMENT_EXPIRED = 'billing.payment.expired';
    public const BILLING_PAYMENT_REFUNDED = 'billing.payment.refunded';
    public const BILLING_PAYMENT_DISPUTED = 'billing.payment.disputed';
    public const BILLING_PAYMENT_DISPUTE_RESOLVED = 'billing.payment.dispute_resolved';
    public const BILLING_SUBSCRIPTION_FREE_ASSIGNED = 'billing.subscription.free_assigned';
    public const BILLING_SUBSCRIPTION_ACTIVATED = 'billing.subscription.activated';
    public const BILLING_SUBSCRIPTION_RENEWED = 'billing.subscription.renewed';
    public const BILLING_SUBSCRIPTION_RENEWAL_DUE = 'billing.subscription.renewal_due';
    public const BILLING_SUBSCRIPTION_CANCEL_SCHEDULED = 'billing.subscription.cancel_scheduled';
    public const BILLING_SUBSCRIPTION_RESUMED = 'billing.subscription.resumed';
    public const BILLING_SUBSCRIPTION_CANCELLED = 'billing.subscription.cancelled';
    public const BILLING_SUBSCRIPTION_EXPIRED = 'billing.subscription.expired';
    public const BILLING_SUBSCRIPTION_RENEWAL_FAILED = 'billing.subscription.renewal_failed';
    public const BILLING_SUBSCRIPTION_PLAN_CHANGED = 'billing.subscription.plan_changed';
    public const BILLING_SUBSCRIPTION_PLAN_CHANGE_SCHEDULED = 'billing.subscription.plan_change_scheduled';
    public const BILLING_SUBSCRIPTION_PLAN_CHANGE_CANCELLED = 'billing.subscription.plan_change_cancelled';
    public const BILLING_SUBSCRIPTION_TRIAL_ENDING = 'billing.subscription.trial_ending';
    public const BILLING_STATEMENT_READY = 'billing.statement.ready';
    public const COLLECTION_PAYMENT_RECEIVED = 'collection.payment.received';
    public const COLLECTION_PAYMENT_RECONCILED = 'collection.payment.reconciled';
    public const COLLECTION_PAYMENT_APPLIED = 'collection.payment.applied';
    public const COLLECTION_SETTLEMENT_UPDATED = 'collection.settlement.updated';
    public const WEBHOOK_ENDPOINT_PINGED = 'webhook_endpoint.pinged';
    public const WEBHOOK_ENDPOINT_CREATED = 'webhook_endpoint.created';
    public const WEBHOOK_ENDPOINT_UPDATED = 'webhook_endpoint.updated';
    public const WEBHOOK_ENDPOINT_DELETED = 'webhook_endpoint.deleted';
    public const WEBHOOK_ENDPOINT_SECRET_ROTATED = 'webhook_endpoint.secret_rotated';
    public const WEBHOOK_ENDPOINT_FAILING = 'webhook_endpoint.failing';
    public const WEBHOOK_ENDPOINT_DISABLED = 'webhook_endpoint.disabled';
    public const WEBHOOK_ENDPOINT_ENABLED = 'webhook_endpoint.enabled';
    public const WEBHOOK_ENDPOINT_RECOVERED = 'webhook_endpoint.recovered';
    public const WEBHOOK_ENDPOINT_FLAGGED = 'webhook_endpoint.flagged';

    private function __construct()
    {
    }
}
