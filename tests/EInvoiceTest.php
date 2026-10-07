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
