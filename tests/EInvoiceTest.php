<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Tests;

use PHPUnit\Framework\TestCase;
use Useyona\EInvoice\BinaryResponse;
use Useyona\EInvoice\EInvoice;
use Useyona\EInvoice\Exception\ConfigException;
use Useyona\EInvoice\ExcludedOperations;
use Useyona\EInvoice\Http\HttpClient;
use Useyona\EInvoice\Page;
use Useyona\EInvoice\Paginate;
use Useyona\EInvoice\RequestOptions;
use Useyona\EInvoice\Tests\Support\FakeHttpClient;
use Useyona\EInvoice\Tests\Support\Responses;

/** The port of einvoice-js tests/einvoice.test.ts (the client and `paginate`). */
final class EInvoiceTest extends TestCase
{
    public function testNeedsOnlyTheKeyAndModeAndHostFollowFromIt(): void
    {
        $yona = new EInvoice(['api_key' => Responses::TEST_KEY]);
        self::assertSame('sandbox', $yona->mode());
        self::assertSame('https://gp.useyona.com', $yona->baseUrl());
        self::assertInstanceOf(HttpClient::class, $yona->http);
        self::assertSame('live', (new EInvoice(['api_key' => Responses::LIVE_KEY]))->mode());
        self::assertSame('http://localhost:3000', (new EInvoice(['api_key' => Responses::LIVE_KEY, 'base_url' => 'http://localhost:3000']))->baseUrl());
        $this->expectException(ConfigException::class);
        new EInvoice(['api_key' => Responses::TEST_KEY, 'assert_mode' => 'live']);
    }

    public function testRefusesAnUnknownOption(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageMatches("/Unknown option 'apiKey'/");
        new EInvoice(['apiKey' => Responses::TEST_KEY]); // @phpstan-ignore argument.type
    }

    public function testHasNoUserRoleInvitationApiKeyOrOrganisationManagementServices(): void
    {
        $yona = new EInvoice(['api_key' => Responses::TEST_KEY]);
        foreach (['users', 'roles', 'invitations', 'apiKeys', 'organizations', 'collection'] as $gone) {
            self::assertFalse(property_exists($yona, $gone), $gone);
        }
        self::assertGreaterThan(0, count(ExcludedOperations::OPERATIONS));
        self::assertFalse(class_exists('Useyona\EInvoice\Yona'));
    }

    public function testUnwrapsDataAndPagesIntoDataAndPagination(): void
    {
        $pagination = ['total' => 1, 'page' => 1, 'pageSize' => 20, 'totalPages' => 1, 'hasNext' => false, 'hasPrevious' => false];
        $fake = new FakeHttpClient([
            Responses::ok([['id' => 'b1']], ['pagination' => $pagination]),
            Responses::raw(200, null),
            Responses::ok(['id' => 'inv']),
        ]);
        $yona = Responses::client($fake);
        $page = $yona->buyers->list(['page' => 1]);
        self::assertSame([['id' => 'b1']], $page->data);
        self::assertSame($pagination, $page->pagination);
        self::assertSame('r1', 'r1');
        self::assertSame('req-ok', $page->requestId);
        self::assertSame('https://gp.useyona.com/i/v1/buyers?page=1', (string) $fake->last()->getUri());
        $empty = $yona->items->list();
        self::assertSame([], $empty->data);
        self::assertSame(0, $empty->pagination['total']);
        self::assertSame(['id' => 'inv'], $yona->invoices->get('inv'));
    }

    public function testReceivedAndIssuedHistoryKeepDataAndMetaPagination(): void
    {
        $pagination = ['total' => 0, 'page' => 1, 'pageSize' => 20, 'totalPages' => 0, 'hasNext' => false, 'hasPrevious' => false];
        $fake = new FakeHttpClient([Responses::ok(['items' => []], ['pagination' => $pagination])]);
        $yona = Responses::client($fake);
        $res = $yona->inboundInvoices->list(['tier' => 'history']);
        self::assertSame(['items' => []], $res->data);
        self::assertSame($pagination, $res->pagination);
        self::assertSame('tier=history', $fake->last()->getUri()->getQuery());
        $history = $yona->issuedHistory->list();
        self::assertSame($pagination, $history->pagination);
        self::assertSame('/i/v1/issued-history', $fake->last()->getUri()->getPath());
    }

    public function testDownloadPdfAsksForThePdfAndReturnsTheBinary(): void
    {
        $fake = new FakeHttpClient([Responses::raw(200, '%PDF-1.7', ['content-type' => 'application/pdf'])]);
        $yona = Responses::client($fake);
        $file = $yona->output->downloadPdf('inv');
        self::assertInstanceOf(BinaryResponse::class, $file);
        self::assertSame('%PDF-1.7', $file->data);
        self::assertSame('application/pdf', $file->contentType);
        $req = $fake->last();
        self::assertSame('GET', $req->getMethod());
        self::assertSame('/i/v1/invoices/inv/download', $req->getUri()->getPath());
        self::assertSame('format=pdf', $req->getUri()->getQuery());
        self::assertSame('application/pdf', $req->getHeaderLine('Accept'));
        self::assertTrue($req->hasHeader('Idempotency-Key'));
    }

    public function testBothDownloadsSendAnIdempotencyKeySoARetriedChargedDownloadIsNotChargedTwice(): void
    {
        $fake = new FakeHttpClient([Responses::ok([])]);
        $yona = Responses::client($fake);
        $yona->output->getDownloadLink('inv');
        $yona->output->downloadPdf('inv', new RequestOptions(idempotencyKey: 'download-inv-1'));
        self::assertSame('/i/v1/invoices/inv/download', $fake->requests[0]->getUri()->getPath());
        self::assertMatchesRegularExpression(HttpClient::IDEMPOTENCY_KEY_PATTERN, $fake->requests[0]->getHeaderLine('Idempotency-Key'));
        self::assertSame('download-inv-1', $fake->requests[1]->getHeaderLine('Idempotency-Key'));
    }

    public function testEncodesPathSegments(): void
    {
        $fake = new FakeHttpClient([Responses::ok([])]);
        Responses::client($fake)->reference->lookupTaxId('12345678/0001');
        self::assertSame('/i/v1/invoices/lookup/tax-id/12345678%2F0001', $fake->last()->getUri()->getPath());
    }

    public function testWebhooksEndpointsTestSendsAnEmptyBodyByDefault(): void
    {
        $fake = new FakeHttpClient([Responses::ok([])]);
        Responses::client($fake)->webhooks->endpoints->test('ep');
        self::assertSame('{}', (string) $fake->last()->getBody());
        self::assertSame('application/json', $fake->last()->getHeaderLine('Content-Type'));
    }

    public function testTaxConnectionGetKeepsTheOptionsOnlyCallOf01AndTakesTheR151Query(): void
    {
        $fake = new FakeHttpClient([Responses::ok(['state' => 'connected'])]);
        $yona = Responses::client($fake);
        $options = new RequestOptions(headers: ['X-Probe' => 'p']);
        $yona->taxConnection->get($options);
        self::assertSame('', $fake->requests[0]->getUri()->getQuery());
        self::assertSame('p', $fake->requests[0]->getHeaderLine('X-Probe'));
        $yona->taxConnection->get(['identifier' => 'true'], $options);
        self::assertSame('identifier=true', $fake->requests[1]->getUri()->getQuery());
        self::assertSame('p', $fake->requests[1]->getHeaderLine('X-Probe'));
        $yona->taxConnection->get(['fresh' => 'true']);
        self::assertSame('fresh=true', $fake->requests[2]->getUri()->getQuery());
        self::assertSame(['state' => 'connected'], $yona->taxConnection->get());
        self::assertSame('/i/v1/tax-connection', $fake->requests[3]->getUri()->getPath());
        self::assertSame('', $fake->requests[3]->getUri()->getQuery());
    }

    public function testListsSubOrganisationsAndTheirReadinessAsPagesJoiningAListOfIds(): void
    {
        $pagination = ['total' => 2, 'page' => 1, 'pageSize' => 100, 'totalPages' => 1, 'hasNext' => false, 'hasPrevious' => false];
        $fake = new FakeHttpClient([Responses::ok([['id' => 'c1'], ['id' => 'c2']], ['pagination' => $pagination, 'requestId' => 'r1'])]);
        $yona = Responses::client($fake);
        $page = $yona->organization->listSubOrganizations(['search' => 'acme', 'limit' => 50]);
        self::assertSame([['id' => 'c1'], ['id' => 'c2']], $page->data);
        self::assertSame($pagination, $page->pagination);
        self::assertSame('r1', $page->requestId);
        self::assertSame('GET', $fake->requests[0]->getMethod());
        self::assertSame('/a/v1/organizations/me/children', $fake->requests[0]->getUri()->getPath());
        self::assertSame('search=acme&limit=50', $fake->requests[0]->getUri()->getQuery());
        $yona->organization->listSubOrganizations(['ids' => ['c1', 'c2']]);
        self::assertSame('ids=c1%2Cc2', $fake->requests[1]->getUri()->getQuery());
        $yona->organization->listSubOrganizations(['ids' => 'c1,c2']);
        self::assertSame('ids=c1%2Cc2', $fake->requests[2]->getUri()->getQuery());
        $yona->organization->listSubOrganizations();
        self::assertSame('', $fake->requests[3]->getUri()->getQuery());
        $yona->organization->listSubOrganizationsReadiness(['ids' => ['c1'], 'page' => 2]);
        self::assertSame('GET', $fake->requests[4]->getMethod());
        self::assertSame('/a/v1/organizations/me/children/readiness', $fake->requests[4]->getUri()->getPath());
        self::assertSame('ids=c1&page=2', $fake->requests[4]->getUri()->getQuery());
    }

    public function testAListOfIdsReachesTheApiAsOneCommaSeparatedValueNotARepeatedKey(): void
    {
        $fake = new FakeHttpClient([Responses::ok([['organizationId' => 'a', 'state' => 'connected', 'submissionsAllowed' => true]])]);
        $connections = Responses::client($fake)->taxConnection->listForSubOrganizations(['ids' => ['a', 'b']]);
        self::assertSame([['organizationId' => 'a', 'state' => 'connected', 'submissionsAllowed' => true]], $connections);
        self::assertSame('/i/v1/tax-connection/children', $fake->last()->getUri()->getPath());
        $pairs = explode('&', $fake->last()->getUri()->getQuery());
        self::assertSame(['ids=a%2Cb'], $pairs);
        self::assertSame('a,b', rawurldecode(substr($pairs[0], 4)));
        // A plain string passes through.
        Responses::client($fake)->taxConnection->listForSubOrganizations(['ids' => 'a,b']);
        self::assertSame('ids=a%2Cb', $fake->last()->getUri()->getQuery());
    }

    public function testCreateSubOrganizationPostsTheBodyWithAnIdempotencyKeySoARetryIsSafe(): void
    {
        $created = ['id' => 'c9', 'type' => 'secondary', 'status' => 'pending_verification'];
        $answer = Responses::json(201, ['meta' => ['statusCode' => 201, 'success' => true, 'requestId' => 'req-ok'], 'data' => $created]);
        $fake = new FakeHttpClient([$answer]);
        $yona = Responses::client($fake, ['api_key' => Responses::LIVE_KEY]);
        $body = ['businessName' => 'Acme Retail Ltd', 'email' => 'accounts@acme.example', 'externalReference' => 'crm-0042'];
        self::assertSame($created, $yona->organization->createSubOrganization($body));
        self::assertSame('POST', $fake->last()->getMethod());
        self::assertSame('/a/v1/organizations', $fake->last()->getUri()->getPath());
        self::assertSame($body, json_decode((string) $fake->last()->getBody(), true));
        // The route reads an Idempotency-Key since managed onboarding: the same key answers the business already made.
        self::assertMatchesRegularExpression(HttpClient::IDEMPOTENCY_KEY_PATTERN, $fake->last()->getHeaderLine('Idempotency-Key'));
        $yona->organization->createSubOrganization($body, new RequestOptions(idempotencyKey: 'create-crm-0042'));
        self::assertSame('create-crm-0042', $fake->last()->getHeaderLine('Idempotency-Key'));

        // Retried: a 503 is followed by a second request that carries the same key.
        $failing = new FakeHttpClient([Responses::refusal(503, 'SYS001'), $answer]);
        self::assertSame($created, Responses::client($failing, ['api_key' => Responses::LIVE_KEY])->organization->createSubOrganization($body));
        self::assertSame(2, $failing->calls());
        self::assertSame($failing->requests[0]->getHeaderLine('Idempotency-Key'), $failing->requests[1]->getHeaderLine('Idempotency-Key'));
    }

    public function testManagedOnboardingTheFiveStepsAParentsKeyTakesForABusinessEachOnItsOwnRoute(): void
    {
        $fake = new FakeHttpClient([Responses::ok([])]);
        $yona = Responses::client($fake, ['api_key' => Responses::LIVE_KEY]);
        $id = 'c9';
        $base = "/a/v1/organizations/me/children/{$id}";

        $yona->organization->getGroupTerms();
        self::assertSame('GET', $fake->requests[0]->getMethod());
        self::assertSame('/a/v1/organizations/me/group-terms', $fake->requests[0]->getUri()->getPath());

        $taxId = ['businessName' => 'Bola Stores Limited', 'taxNo' => '1234567890123'];
        $yona->organization->verifySubOrganizationTaxId($id, $taxId);
        self::assertSame('POST', $fake->requests[1]->getMethod());
        self::assertSame("{$base}/onboarding/tax-id", $fake->requests[1]->getUri()->getPath());
        self::assertSame($taxId, json_decode((string) $fake->requests[1]->getBody(), true));
        self::assertFalse($fake->requests[1]->hasHeader('Idempotency-Key'));

        $contact = ['phoneNumber' => '+2348031234567', 'contactEmail' => 'accounts@bolastores.example'];
        $yona->organization->setSubOrganizationContact($id, $contact);
        self::assertSame('PUT', $fake->requests[2]->getMethod());
        self::assertSame("{$base}/onboarding/contact", $fake->requests[2]->getUri()->getPath());
        self::assertSame($contact, json_decode((string) $fake->requests[2]->getBody(), true));

        $yona->organization->createSubOrganizationSetupLink($id, ['expiresInDays' => 14]);
        self::assertSame('POST', $fake->requests[3]->getMethod());
        self::assertSame("{$base}/setup-link", $fake->requests[3]->getUri()->getPath());
        self::assertSame(['expiresInDays' => 14], json_decode((string) $fake->requests[3]->getBody(), true));
        self::assertMatchesRegularExpression(HttpClient::IDEMPOTENCY_KEY_PATTERN, $fake->requests[3]->getHeaderLine('Idempotency-Key'));
        // Without a life the body is empty, and the link takes the default.
        $yona->organization->createSubOrganizationSetupLink($id);
        self::assertSame('POST', $fake->requests[4]->getMethod());
        self::assertSame("{$base}/setup-link", $fake->requests[4]->getUri()->getPath());
        self::assertSame('{}', (string) $fake->requests[4]->getBody());
        self::assertTrue($fake->requests[4]->hasHeader('Idempotency-Key'));
        // The caller's own key is the one sent.
        $yona->organization->createSubOrganizationSetupLink($id, [], new RequestOptions(idempotencyKey: 'link-crm-0042'));
        self::assertSame('link-crm-0042', $fake->requests[5]->getHeaderLine('Idempotency-Key'));

        $yona->organization->submitSubOrganization($id);
        self::assertSame('POST', $fake->requests[6]->getMethod());
        self::assertSame("{$base}/onboarding/submit", $fake->requests[6]->getUri()->getPath());
        self::assertSame('', (string) $fake->requests[6]->getBody());
        self::assertFalse($fake->requests[6]->hasHeader('Idempotency-Key'));

        $yona->organization->revokeSubOrganizationSetupLink($id);
        self::assertSame('DELETE', $fake->requests[7]->getMethod());
        self::assertSame("{$base}/setup-link", $fake->requests[7]->getUri()->getPath());
    }

    public function testABusinessIdIsOnePathSegmentHoweverItIsSpelt(): void
    {
        $fake = new FakeHttpClient([Responses::ok([])]);
        $organization = Responses::client($fake, ['api_key' => Responses::LIVE_KEY])->organization;
        $organization->submitSubOrganization('../../me');
        $organization->verifySubOrganizationTaxId('a/b', ['businessName' => 'Bola Stores Limited', 'taxNo' => '1234567890123']);
        $organization->setSubOrganizationContact('a/b', ['phoneNumber' => '+2348031234567']);
        $organization->createSubOrganizationSetupLink('a/b');
        $organization->revokeSubOrganizationSetupLink('a/b');
        self::assertSame([
            'POST /a/v1/organizations/me/children/..%2F..%2Fme/onboarding/submit',
            'POST /a/v1/organizations/me/children/a%2Fb/onboarding/tax-id',
            'PUT /a/v1/organizations/me/children/a%2Fb/onboarding/contact',
            'POST /a/v1/organizations/me/children/a%2Fb/setup-link',
            'DELETE /a/v1/organizations/me/children/a%2Fb/setup-link',
        ], array_map(fn ($req): string => $req->getMethod() . ' ' . $req->getUri()->getPath(), $fake->requests));
    }

    public function testTheReadsOpenedToApiKeysSince022CreditCostsAddOnsCollectionAndWebhookHealth(): void
    {
        $fake = new FakeHttpClient([Responses::ok([])]);
        $yona = Responses::client($fake);
        $window = ['from' => '2026-11-01T00:00:00.000Z', 'to' => '2026-11-20T00:00:00.000Z'];
        $yona->billing->accounts->getCreditCosts();
        $yona->billing->subscriptions->listAddOns();
        $yona->billing->subscriptions->quoteAddOn('sub_org_slot', ['slots' => 5]);
        $yona->collections->getSummary($window);
        $yona->collections->getDailySeries($window);
        $yona->webhooks->health->get($window);
        $yona->webhooks->health->getDaily([...$window, 'endpointId' => 'e1']);
        self::assertSame([
            'GET /b/v1/billing-accounts/me/credit-costs',
            'GET /b/v1/subscription/add-ons',
            'GET /b/v1/subscription/add-ons/sub_org_slot/quote',
            'GET /b/v1/collection/summary',
            'GET /b/v1/collection/summary/daily',
            'GET /n/v1/webhook-health',
            'GET /n/v1/webhook-health/daily',
        ], array_map(fn ($req): string => $req->getMethod() . ' ' . $req->getUri()->getPath(), $fake->requests));
        self::assertSame('slots=5', $fake->requests[2]->getUri()->getQuery());
        parse_str($fake->requests[3]->getUri()->getQuery(), $summaryQuery);
        self::assertSame($window, $summaryQuery);
        parse_str($fake->requests[6]->getUri()->getQuery(), $dailyQuery);
        self::assertSame([...$window, 'endpointId' => 'e1'], $dailyQuery);
        foreach ($fake->requests as $req) {
            self::assertFalse($req->hasHeader('Idempotency-Key'));
        }
        // The add-on's kind is one path segment.
        $yona->billing->subscriptions->quoteAddOn('a/b', ['slots' => 1]);
        self::assertSame('/b/v1/subscription/add-ons/a%2Fb/quote', $fake->last()->getUri()->getPath());
    }

    public function testBillingPartnerGetKeepsTheOptionsOnlyCallOf02AndTakesThePageOfBusinesses(): void
    {
        $partner = ['tier' => 'growth', 'businessesPage' => ['page' => 2, 'limit' => 50, 'total' => 120]];
        $fake = new FakeHttpClient([Responses::ok($partner)]);
        $yona = Responses::client($fake);
        $options = new RequestOptions(headers: ['X-Probe' => 'p']);
        $yona->billing->partner->get($options);
        self::assertSame('/b/v1/partner', $fake->requests[0]->getUri()->getPath());
        self::assertSame('', $fake->requests[0]->getUri()->getQuery());
        self::assertSame('p', $fake->requests[0]->getHeaderLine('X-Probe'));
        $yona->billing->partner->get(['page' => 2, 'limit' => 50], $options);
        self::assertSame('page=2&limit=50', $fake->requests[1]->getUri()->getQuery());
        self::assertSame('p', $fake->requests[1]->getHeaderLine('X-Probe'));
        self::assertSame($partner, $yona->billing->partner->get());
        self::assertSame('', $fake->requests[2]->getUri()->getQuery());
        // The named argument of 0.2 still binds.
        $yona->billing->partner->get(options: $options);
        self::assertSame('', $fake->requests[3]->getUri()->getQuery());
        self::assertSame('p', $fake->requests[3]->getHeaderLine('X-Probe'));
    }

    public function testReferenceGetResourceTypesTheStatesAndLgaLists(): void
    {
        $fake = new FakeHttpClient([
            Responses::ok(['type' => 'lgas', 'version' => 1, 'items' => [['code' => 'NG-LA-IKE', 'name' => 'Ikeja', 'stateCode' => 'NG-LA'], ['code' => 'NG-OG-IFO', 'name' => 'Ifo', 'stateCode' => 'NG-OG']], 'fetchedAt' => '2026-11-10T00:00:00Z', 'stale' => false]),
            Responses::ok(['type' => 'states', 'version' => 1, 'items' => [['code' => 'NG-LA', 'name' => 'Lagos']], 'fetchedAt' => '2026-11-10T00:00:00Z', 'stale' => false]),
        ]);
        $yona = Responses::client($fake);
        $lgas = $yona->reference->getResource('lgas');
        $inLagos = array_map(fn (array $lga): string => $lga['code'], array_values(array_filter($lgas['items'], fn (array $lga): bool => $lga['stateCode'] === 'NG-LA')));
        self::assertSame(['NG-LA-IKE'], $inLagos);
        self::assertSame('/i/v1/invoices/resources/lgas', $fake->requests[0]->getUri()->getPath());
        $states = $yona->reference->getResource('states');
        self::assertSame('/i/v1/invoices/resources/states', $fake->requests[1]->getUri()->getPath());
        self::assertSame(['Lagos'], array_map(fn (array $state): string => $state['name'], $states['items']));
    }

    public function testHeldInvoicesTheListFilterAndTheIssueAnswerPassThrough(): void
    {
        $pagination = ['total' => 1, 'page' => 1, 'pageSize' => 20, 'totalPages' => 1, 'hasNext' => false, 'hasPrevious' => false];
        $fake = new FakeHttpClient([
            Responses::ok([['id' => 'inv-1', 'reporting' => ['state' => 'held', 'reason' => null]]], ['pagination' => $pagination]),
            Responses::ok(['held' => 3]),
            Responses::ok(['reporting' => 'held', 'notReportedReason' => 'awaiting_connection']),
        ]);
        $yona = Responses::client($fake);
        $page = $yona->invoices->list(['reporting' => 'held']);
        self::assertSame('/i/v1/invoices', $fake->requests[0]->getUri()->getPath());
        self::assertSame('reporting=held', $fake->requests[0]->getUri()->getQuery());
        self::assertSame(['held'], array_map(fn (array $invoice): string => $invoice['reporting']['state'], $page->data));
        self::assertSame(['held' => 3], $yona->invoices->getSummary(['reporting' => 'held']));
        self::assertSame('reporting=held', $fake->requests[1]->getUri()->getQuery());
        $issued = $yona->submissions->issue('inv-1');
        self::assertSame('held', $issued['reporting']);
        self::assertSame('awaiting_connection', $issued['notReportedReason']);
    }

    public function testTheTaxConnectionCanBeWaitingForTheAuthorityToListTheBusiness(): void
    {
        $listing = ['since' => '2026-11-12T09:00:00Z', 'checkedAt' => null, 'nextCheckAt' => null, 'answer' => 'unknown'];
        $fake = new FakeHttpClient([Responses::ok(['state' => 'awaiting_authority_listing', 'submissionsAllowed' => false, 'connectWaits' => true, 'listing' => $listing])]);
        $connection = Responses::client($fake)->taxConnection->get();
        self::assertSame('awaiting_authority_listing', $connection['state']);
        self::assertTrue($connection['connectWaits']);
        self::assertSame($listing, $connection['listing']);
    }

    public function testHasNoPaymentsServiceMethodsUntilPaymentsLaunchesInProduction(): void
    {
        $yona = new EInvoice(['api_key' => Responses::TEST_KEY]);
        foreach (['bankAccount', 'transfers'] as $gone) {
            self::assertFalse(property_exists($yona, $gone), $gone);
        }
        $paths = array_filter(array_column(ExcludedOperations::OPERATIONS, 'path'), fn (string $p): bool => str_starts_with($p, '/p/'));
        self::assertCount(5, $paths);
    }

    public function testNullableBillingAnswersComeBackAsNull(): void
    {
        $fake = new FakeHttpClient([Responses::ok(null)]);
        $billing = Responses::client($fake)->billing;
        self::assertNull($billing->plans->getRecommendation());
        self::assertNull($billing->usage->getInvoices());
        self::assertNull($billing->partner->getOffer());
        self::assertNull($billing->subscriptions->getDealOffer());
    }

    public function testCollectionsWritesCarryNoIdempotencyKeyAndPlainListsAreArrays(): void
    {
        $fake = new FakeHttpClient([Responses::ok([['code' => '058']])]);
        $collections = Responses::client($fake)->collections;
        self::assertSame([['code' => '058']], $collections->listBanks(['jurisdiction' => 'NG']));
        self::assertSame('jurisdiction=NG', $fake->last()->getUri()->getQuery());
        self::assertSame([['code' => '058']], $collections->quoteFee(['kind' => 'dynamic', 'amountMinor' => 150000]));
        self::assertSame('kind=dynamic&amountMinor=150000', $fake->last()->getUri()->getQuery());
        $collections->createAccount(['kind' => 'dynamic', 'reference' => 'r1', 'invoiceId' => 'inv']);
        $collections->deactivateAccount('ca/1', ['reason' => 'paid']);
        $collections->resolveException('pay1', ['resolution' => 'refund_recorded_externally']);
        foreach (array_slice($fake->requests, 2) as $req) {
            self::assertSame('POST', $req->getMethod());
            self::assertFalse($req->hasHeader('Idempotency-Key'));
        }
        self::assertSame('/b/v1/collection/accounts/ca%2F1/deactivate', $fake->requests[3]->getUri()->getPath());
    }

    public function testBillingAccountsGetStatsDefaultsToTheCallersOwnAccount(): void
    {
        $fake = new FakeHttpClient([Responses::ok([])]);
        Responses::client($fake)->billing->accounts->getStats();
        self::assertSame('/b/v1/billing-accounts/me/stats', $fake->last()->getUri()->getPath());
    }

    public function testABinaryRouteAnsweringJsonHandsTheJsonOverAsBytes(): void
    {
        $fake = new FakeHttpClient([Responses::ok(['downloadUrl' => 'u'])]);
        $file = Responses::client($fake)->issuedHistory->downloadPdf('h1');
        self::assertSame('application/json', $file->contentType);
        self::assertSame('{"downloadUrl":"u"}', $file->data);
        self::assertSame('req-ok', $file->requestId);
    }

    // ── paginate ──

    public function testPaginateWalksEveryPageUntilHasNextIsFalse(): void
    {
        $pages = [
            new Page([1, 2], [...Page::EMPTY_PAGINATION, 'hasNext' => true]),
            new Page([3], [...Page::EMPTY_PAGINATION, 'hasNext' => false]),
        ];
        $seen = [];
        $list = function (array $q) use (&$seen, $pages): Page {
            $seen[] = $q['page'];
            self::assertSame(2, $q['limit']);

            return $pages[$q['page'] - 1];
        };
        $out = iterator_to_array(EInvoice::paginate($list, ['limit' => 2]), false);
        self::assertSame([1, 2, 3], $out);
        self::assertSame([1, 2], $seen);
    }

    public function testPaginateStopsOnAnEmptyPageEvenIfHasNextIsTrue(): void
    {
        $list = fn (array $q): Page => new Page([], [...Page::EMPTY_PAGINATION, 'hasNext' => true]);
        self::assertSame([], iterator_to_array(Paginate::all($list), false));
    }

    public function testPaginateStartsAtTheRequestedPage(): void
    {
        $seen = [];
        $list = function (array $q) use (&$seen): Page {
            $seen[] = $q['page'];

            return new Page(['x'], [...Page::EMPTY_PAGINATION, 'hasNext' => $q['page'] < 4]);
        };
        self::assertSame(['x', 'x'], iterator_to_array(Paginate::all($list, ['page' => 3]), false));
        self::assertSame([3, 4], $seen);
    }
}
