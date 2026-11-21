<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Service;

use Useyona\EInvoice\Page;
use Useyona\EInvoice\RequestOptions;

/**
 * The key's organisation (`/a/v1/organizations`): its profile and readiness, its
 * sub-organisations, and managed onboarding, by which a parent's key takes each business it
 * creates from created to waiting for approval. The profile is read-only for an API key.
 *
 * `ids` is a list of ids or the API's comma-separated string; a list reaches the API as one
 * comma-separated value. A sub-organisation takes its parent's country, so `country` is not part of
 * `CreateSubOrganizationParams`; `groupOnboarding` is the signed-in person's field (a key that
 * holds `organization.children.onboard` makes a managed business without it).
 *
 * @phpstan-import-type GetOrganizationData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type GetOrganizationReadinessData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type ListChildOrganizationsItem from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type ListChildOrganizationsReadinessItem from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type CreateOrganizationData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type GetGroupTermsData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type VerifyChildOrganizationTaxIdBody from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type VerifyChildOrganizationTaxIdData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type SetChildOrganizationContactBody from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type SetChildOrganizationContactData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type CreateChildOrganizationSetupLinkBody from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type CreateChildOrganizationSetupLinkData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type RevokeChildOrganizationSetupLinkData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type SubmitChildOrganizationOnboardingData from \Useyona\EInvoice\Generated\Types
 *
 * @phpstan-type ListSubOrganizationsQuery array{
 *     page?: int,
 *     limit?: int,
 *     search?: string,
 *     ids?: string|list<string>
 * }
 * @phpstan-type ListSubOrganizationsReadinessQuery array{
 *     page?: int,
 *     limit?: int,
 *     search?: string,
 *     ids?: string|list<string>
 * }
 * @phpstan-type CreateSubOrganizationParams array{
 *     businessName: string,
 *     email: string,
 *     phoneNumber?: string,
 *     website?: string,
 *     intent?: 'core'|'access'|'both',
 *     externalReference?: string
 * }
 */
final class OrganizationService extends BaseService
{
    /**
     * The organisation's profile; `$orgId` must be the key's own organisation (404 otherwise; `organization.read`).
     *
     * @return GetOrganizationData
     */
    public function get(string $orgId, ?RequestOptions $options = null): array
    {
        return $this->call('GET', '/a/v1/organizations/' . self::seg($orgId), ['options' => $options]);
    }

    /**
     * The onboarding checklist, the live-access status and the next step (`organization.read`).
     *
     * @return GetOrganizationReadinessData
     */
    public function getReadiness(?RequestOptions $options = null): array
    {
        return $this->call('GET', '/a/v1/organizations/me/readiness', ['options' => $options]);
    }

    /**
     * A page of the organisation's sub-organisations, newest first, with the total in `pagination`
     * (`organization.children.read`; sandbox and live). `search` keeps the ones whose name holds it;
     * `ids` keeps only the ones named (at most 100). Without `limit` a page holds 100, the most.
     * No key preset includes `organization.children.read`: it is added to a key by name, in the
     * dashboard's Create API Key dialog. 409 `BIZ014` when the key's organisation is itself a
     * sub-organisation.
     *
     * @param ListSubOrganizationsQuery|null $query
     *
     * @return Page<ListChildOrganizationsItem>
     */
    public function listSubOrganizations(?array $query = null, ?RequestOptions $options = null): Page
    {
        return $this->page('GET', '/a/v1/organizations/me/children', ['query' => self::withJoinedIds($query), 'options' => $options]);
    }

    /**
     * The same page as `listSubOrganizations`, with each sub-organisation's onboarding checklist,
     * live status and next step: which of a parent's businesses still have something to do
     * (`organization.children.read`; sandbox and live). Whether each is connected to the tax
     * authority is `taxConnection->listForSubOrganizations` with the same ids.
     *
     * @param ListSubOrganizationsReadinessQuery|null $query
     *
     * @return Page<ListChildOrganizationsReadinessItem>
     */
    public function listSubOrganizationsReadiness(?array $query = null, ?RequestOptions $options = null): Page
    {
        return $this->page('GET', '/a/v1/organizations/me/children/readiness', ['query' => self::withJoinedIds($query), 'options' => $options]);
    }

    /**
     * Creates a sub-organisation of the key's organisation (`organization.children.create`, which no
     * key preset includes; a live key only: a sandbox key is refused with 403 `BIZ003`). It is
     * recorded under the parent's longest-standing Owner and starts as `pending_verification`
     * until its own onboarding is completed. An `Idempotency-Key` is sent (yours from
     * `$options->idempotencyKey`, or one the SDK makes), so a retried call answers the organisation
     * already made, with `replayed: true`; the same key with other details is 409 `BIZ107`.
     * `externalReference` is your own id for the business (1 to 64 of letters, digits, `.`, `_`, `:`,
     * `-`): unique among the parent's sub-organisations, never changed, and the same reference with
     * the same name also answers the business already made. A name is unique under its parent (409
     * `RES002`). 402 `BIZ016` when the plan allows no more organisations; at most 60 a minute for
     * each key.
     *
     * With a key that also holds `organization.children.onboard`, in an organisation that has
     * accepted the managed onboarding terms, the business is created for managed onboarding
     * (`onboardingManaged: true`, `origin: 'parent_key'`): the same key then takes it through
     * `verifySubOrganizationTaxId`, `setSubOrganizationContact`, `createSubOrganizationSetupLink`
     * and `submitSubOrganization`.
     *
     * @param CreateSubOrganizationParams $params
     *
     * @return CreateOrganizationData
     */
    public function createSubOrganization(array $params, ?RequestOptions $options = null): array
    {
        return $this->call('POST', '/a/v1/organizations', ['body' => $params, 'idempotent' => true, 'options' => $options]);
    }

    /**
     * Where the organisation stands with the managed onboarding terms, which let it onboard its
     * businesses (`organization.children.read`; sandbox and live). `accepted` is true only for the
     * current version. An Owner or Admin accepts them in the Yona app; an API key cannot.
     *
     * @return GetGroupTermsData
     */
    public function getGroupTerms(?RequestOptions $options = null): array
    {
        return $this->call('GET', '/a/v1/organizations/me/group-terms', ['options' => $options]);
    }

    /**
     * Managed onboarding, step 2: verifies the Tax ID of a business this key created
     * (`organization.children.onboard`, in no preset; a live key only). The registry's name for the
     * number is compared with `businessName`. The same number, already verified, answers the stored
     * result with `replayed: true` and makes no second lookup. Not retried: the route takes no
     * `Idempotency-Key`.
     *
     * 404 `RES001` for any business the key may not onboard: not the organisation's, one its own
     * people set up, or one Yona has approved. 409 `BIZ004` with `errors[0].reason`
     * `group_terms_not_accepted` or `in_review`; 422 `BIZ007` the name does not match; 409 `BIZ009`
     * the Tax ID is verified for another organisation; 429 `BIZ002` the day's checks are used up.
     *
     * @param VerifyChildOrganizationTaxIdBody $params
     *
     * @return VerifyChildOrganizationTaxIdData
     */
    public function verifySubOrganizationTaxId(string $subOrganizationId, array $params, ?RequestOptions $options = null): array
    {
        return $this->call('POST', '/a/v1/organizations/me/children/' . self::seg($subOrganizationId) . '/onboarding/tax-id', ['body' => $params, 'options' => $options]);
    }

    /**
     * Managed onboarding, step 3: sets the contact details of a business this key is onboarding
     * (`organization.children.onboard`). `phoneNumber` is the business owner's own phone: the setup
     * link's codes go to it and nowhere else. The same values again change nothing and never undo the
     * phone check; a changed number clears it and stops the working setup link. 409 `RES002`
     * (`errors[0].phoneNumber`) when another business being onboarded uses the number.
     *
     * @param SetChildOrganizationContactBody $params
     *
     * @return SetChildOrganizationContactData
     */
    public function setSubOrganizationContact(string $subOrganizationId, array $params, ?RequestOptions $options = null): array
    {
        return $this->call('PUT', '/a/v1/organizations/me/children/' . self::seg($subOrganizationId) . '/onboarding/contact', ['body' => $params, 'options' => $options]);
    }

    /**
     * Managed onboarding, step 4: creates the setup link the business owner finishes on
     * (`organization.children.onboard`). `url` is answered ONCE: Yona keeps only a fingerprint of it,
     * so store it or send it straight away. A link works for 14 days unless `expiresInDays` says
     * otherwise (30 at most). A business has one working link: a new one stops the one before it.
     * An empty `$params` is sent as `{}`, and the link takes the default life.
     *
     * An `Idempotency-Key` is sent (yours from `$options->idempotencyKey`, or one the SDK makes),
     * so a retried call answers the same link with `url: null` and `replayed: true`, and makes no
     * second link. This is also the one call the key may make for a business Yona has approved,
     * when its owner lost the link before finishing.
     *
     * @param CreateChildOrganizationSetupLinkBody|array{} $params
     *
     * @return CreateChildOrganizationSetupLinkData
     */
    public function createSubOrganizationSetupLink(string $subOrganizationId, array $params = [], ?RequestOptions $options = null): array
    {
        return $this->call('POST', '/a/v1/organizations/me/children/' . self::seg($subOrganizationId) . '/setup-link', ['body' => $params, 'idempotent' => true, 'options' => $options]);
    }

    /**
     * Withdraws the working setup link of a business at once (`organization.children.onboard`).
     * `revoked` is false, and nothing changes, when there was no working link.
     *
     * @return RevokeChildOrganizationSetupLinkData
     */
    public function revokeSubOrganizationSetupLink(string $subOrganizationId, ?RequestOptions $options = null): array
    {
        return $this->call('DELETE', '/a/v1/organizations/me/children/' . self::seg($subOrganizationId) . '/setup-link', ['options' => $options]);
    }

    /**
     * Managed onboarding, step 5: asks Yona to review a business this key is onboarding
     * (`organization.children.onboard`). It can be called before the owner has verified the phone:
     * `state` is then `awaiting_phone_verification`, and the business goes to review by itself when
     * the owner enters the code on the setup link. With the phone verified, `state` is `in_review`.
     * A business already submitted answers where it stands, with `replayed: true`. 409 `BIZ010`
     * (`errors[].missing`: `tin`, `contact_info`) when an earlier step is missing. Yona approves; a
     * key never does. No body is sent. Not retried: the route takes no `Idempotency-Key`.
     *
     * @return SubmitChildOrganizationOnboardingData
     */
    public function submitSubOrganization(string $subOrganizationId, ?RequestOptions $options = null): array
    {
        return $this->call('POST', '/a/v1/organizations/me/children/' . self::seg($subOrganizationId) . '/onboarding/submit', ['options' => $options]);
    }
}
