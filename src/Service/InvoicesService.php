<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Service;

use Useyona\EInvoice\Page;
use Useyona\EInvoice\RequestOptions;

/**
 * Invoices (`/i/v1/invoices`): drafts, their lifecycle, credit and debit notes, and the
 * organisation's invoice statistics. Submission to the tax authority is {@see SubmissionsService}.
 *
 * @phpstan-import-type CreateInvoiceBody from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type CreateInvoiceData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type ListInvoicesQuery from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type ListInvoicesItem from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type GetInvoiceData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type UpdateInvoiceBody from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type UpdateInvoiceData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type DeleteInvoiceData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type FinaliseInvoiceData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type ReopenInvoiceData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type ReviseInvoiceData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type CancelInvoiceBody from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type CancelInvoiceData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type IssueCreditNoteBody from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type IssueCreditNoteData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type IssueDebitNoteBody from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type IssueDebitNoteData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type GetInvoiceOverviewQuery from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type GetInvoiceOverviewData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type GetInvoiceStatisticsQuery from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type GetInvoiceStatisticsData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type GetInvoiceListSummaryQuery from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type GetInvoiceListSummaryData from \Useyona\EInvoice\Generated\Types
 */
final class InvoicesService extends BaseService
{
    /**
     * Creates a draft invoice (`invoice.create`; charged as one invoice creation). The number is
     * allocated from the series when `invoiceNumber` is omitted. Sends an `Idempotency-Key`
     * (generated unless `$options->idempotencyKey` is given), so a retry is never charged twice.
     *
     * @param CreateInvoiceBody $params
     *
     * @return CreateInvoiceData
     */
    public function create(array $params, ?RequestOptions $options = null): array
    {
        return $this->call('POST', '/i/v1/invoices', ['body' => $params, 'idempotent' => true, 'options' => $options]);
    }

    /**
     * Lists invoices, newest first, without `lineItems` (`invoice.read`).
     *
     * @param ListInvoicesQuery|null $query
     *
     * @return Page<ListInvoicesItem>
     */
    public function list(?array $query = null, ?RequestOptions $options = null): Page
    {
        return $this->page('GET', '/i/v1/invoices', ['query' => $query, 'options' => $options]);
    }

    /**
     * Gets one invoice with its lines (`invoice.read`).
     *
     * @return GetInvoiceData
     */
    public function get(string $id, ?RequestOptions $options = null): array
    {
        return $this->call('GET', '/i/v1/invoices/' . self::seg($id), ['options' => $options]);
    }

    /**
     * Updates a draft (`invoice.update_draft`).
     *
     * @param UpdateInvoiceBody $params
     *
     * @return UpdateInvoiceData
     */
    public function update(string $id, array $params, ?RequestOptions $options = null): array
    {
        return $this->call('PATCH', '/i/v1/invoices/' . self::seg($id), ['body' => $params, 'options' => $options]);
    }

    /**
     * Deletes a draft permanently; its number is never reused (`invoice.delete_draft`).
     *
     * @return DeleteInvoiceData
     */
    public function delete(string $id, ?RequestOptions $options = null): array
    {
        return $this->call('DELETE', '/i/v1/invoices/' . self::seg($id), ['options' => $options]);
    }

    /**
     * Finalises a draft: freezes the seller and buyer onto it; 422 `VAL001` when the jurisdiction's
     * checks refuse it (`invoice.finalise`).
     *
     * @return FinaliseInvoiceData
     */
    public function finalise(string $id, ?RequestOptions $options = null): array
    {
        return $this->call('POST', '/i/v1/invoices/' . self::seg($id) . '/finalise', ['options' => $options]);
    }

    /**
     * Reopens a finalised invoice as a draft: allowed while its submission was rejected, or when it
     * was never submitted (`invoice.reopen`).
     *
     * @return ReopenInvoiceData
     */
    public function reopen(string $id, ?RequestOptions $options = null): array
    {
        return $this->call('POST', '/i/v1/invoices/' . self::seg($id) . '/reopen', ['options' => $options]);
    }

    /**
     * Revises an issued invoice that was never reported and is unpaid: back to a draft with the same
     * number and the next version (`invoice.reopen`).
     *
     * @return ReviseInvoiceData
     */
    public function revise(string $id, ?RequestOptions $options = null): array
    {
        return $this->call('POST', '/i/v1/invoices/' . self::seg($id) . '/revise', ['options' => $options]);
    }

    /**
     * Cancels an invoice (`invoice.cancel`; 202): voided locally when the tax authority does not hold
     * it, otherwise by a credit note. A draft is 409 `BIZ201` (delete it instead). Sends an
     * `Idempotency-Key`.
     *
     * @param CancelInvoiceBody $params
     *
     * @return CancelInvoiceData
     */
    public function cancel(string $id, array $params, ?RequestOptions $options = null): array
    {
        return $this->call('POST', '/i/v1/invoices/' . self::seg($id) . '/cancel', ['body' => $params, 'idempotent' => true, 'options' => $options]);
    }

    /**
     * Issues a credit note against a registered invoice (`invoice.cancel`). With `preview: true`
     * nothing is written or charged. Sends an `Idempotency-Key`.
     *
     * @param IssueCreditNoteBody $params
     *
     * @return IssueCreditNoteData
     */
    public function issueCreditNote(string $id, array $params, ?RequestOptions $options = null): array
    {
        return $this->call('POST', '/i/v1/invoices/' . self::seg($id) . '/credit-notes', ['body' => $params, 'idempotent' => true, 'options' => $options]);
    }

    /**
     * Issues a debit note (added lines) against a registered invoice and submits it
     * (`invoice.create` + `invoice.submit`). With `preview: true` nothing is written. Sends an
     * `Idempotency-Key`.
     *
     * @param IssueDebitNoteBody $params
     *
     * @return IssueDebitNoteData
     */
    public function issueDebitNote(string $id, array $params, ?RequestOptions $options = null): array
    {
        return $this->call('POST', '/i/v1/invoices/' . self::seg($id) . '/debit-notes', ['body' => $params, 'idempotent' => true, 'options' => $options]);
    }

    /**
     * The dashboard overview: KPIs, trend, alerts and readiness (`invoice.stats.read`).
     *
     * @param GetInvoiceOverviewQuery|null $query
     *
     * @return GetInvoiceOverviewData
     */
    public function getOverview(?array $query = null, ?RequestOptions $options = null): array
    {
        return $this->call('GET', '/i/v1/invoices/overview', ['query' => $query, 'options' => $options]);
    }

    /**
     * Invoice statistics for a period (`invoice.stats.read`).
     *
     * @param GetInvoiceStatisticsQuery|null $query
     *
     * @return GetInvoiceStatisticsData
     */
    public function getStatistics(?array $query = null, ?RequestOptions $options = null): array
    {
        return $this->call('GET', '/i/v1/invoices/statistics', ['query' => $query, 'options' => $options]);
    }

    /**
     * The counts behind the invoice list's summary cards (`invoice.stats.read`).
     *
     * @param GetInvoiceListSummaryQuery|null $query
     *
     * @return GetInvoiceListSummaryData
     */
    public function getSummary(?array $query = null, ?RequestOptions $options = null): array
    {
        return $this->call('GET', '/i/v1/invoices/summary', ['query' => $query, 'options' => $options]);
    }
}
