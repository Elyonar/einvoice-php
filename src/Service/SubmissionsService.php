<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Service;

use Useyona\EInvoice\RequestOptions;

/**
 * Submissions (`/i/v1/invoices/…`): reporting invoices to the tax authority and following them.
 *
 * @phpstan-import-type SubmitInvoiceQuery from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type SubmitInvoiceData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type IssueInvoiceData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type CreateAndSubmitInvoiceBody from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type CreateAndSubmitInvoiceData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type BatchSubmitInvoicesBody from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type BatchSubmitInvoicesData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type RetryInvoiceSubmissionData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type RenumberInvoiceData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type QueryInvoiceStatusData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type GetInvoiceStatusData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type RecordInvoicePaymentBody from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type RecordInvoicePaymentData from \Useyona\EInvoice\Generated\Types
 * @phpstan-import-type GetInvoiceAuthorityCopyData from \Useyona\EInvoice\Generated\Types
 */
final class SubmissionsService extends BaseService
{
    /**
     * Queues a submission of a finalised invoice (`invoice.submit`; 202, or 200 `replayed: true`
     * while one is in flight). `['finalise' => 'true']` finalises a draft first. Sends an `Idempotency-Key`.
     *
     * @param SubmitInvoiceQuery|null $query
     *
     * @return SubmitInvoiceData
     */
    public function submit(string $id, ?array $query = null, ?RequestOptions $options = null): array
    {
        return $this->call('POST', '/i/v1/invoices/' . self::seg($id) . '/submit', ['query' => $query, 'idempotent' => true, 'options' => $options]);
    }

    /**
     * Issues a draft: finalised and submitted when the organisation reports automatically (202),
     * otherwise finalised and sent to the buyer (200, `notReportedReason`). Sends an `Idempotency-Key`.
     *
     * @return IssueInvoiceData
     */
    public function issue(string $id, ?RequestOptions $options = null): array
    {
        return $this->call('POST', '/i/v1/invoices/' . self::seg($id) . '/issue', ['idempotent' => true, 'options' => $options]);
    }

    /**
     * Creates an invoice and submits it (`invoice.create` + `invoice.submit`). When the submit is
     * refused the draft is kept and `submitRefusal` says why. Sends an `Idempotency-Key`.
     *
     * @param CreateAndSubmitInvoiceBody $params
     *
     * @return CreateAndSubmitInvoiceData
     */
    public function createAndSubmit(array $params, ?RequestOptions $options = null): array
    {
        return $this->call('POST', '/i/v1/invoices/create-and-submit', ['body' => $params, 'idempotent' => true, 'options' => $options]);
    }

    /**
     * Submits 1–100 invoices, each independently (`invoice.submit`). Not retried by the SDK.
     *
     * @param BatchSubmitInvoicesBody $params
     *
     * @return BatchSubmitInvoicesData
     */
    public function batchSubmit(array $params, ?RequestOptions $options = null): array
    {
        return $this->call('POST', '/i/v1/invoices/batch-submit', ['body' => $params, 'options' => $options]);
    }

    /**
     * Resumes a submission that is on hold, with its existing charge hold (`invoice.retry`; 202).
     *
     * @return RetryInvoiceSubmissionData
     */
    public function retry(string $id, ?RequestOptions $options = null): array
    {
        return $this->call('POST', '/i/v1/invoices/' . self::seg($id) . '/retry', ['options' => $options]);
    }

    /**
     * Gives an invoice whose submission is on hold because the tax authority already holds its number
     * the next number of its series and queues a new submission (`invoice.submit`). Sends an
     * `Idempotency-Key`.
     *
     * @return RenumberInvoiceData
     */
    public function renumber(string $id, ?RequestOptions $options = null): array
    {
        return $this->call('POST', '/i/v1/invoices/' . self::seg($id) . '/renumber', ['idempotent' => true, 'options' => $options]);
    }

    /**
     * Asks the tax authority now for the invoice's state (`invoice.query_status`). Sends an `Idempotency-Key`.
     *
     * @return QueryInvoiceStatusData
     */
    public function queryStatus(string $id, ?RequestOptions $options = null): array
    {
        return $this->call('POST', '/i/v1/invoices/' . self::seg($id) . '/query-status', ['idempotent' => true, 'options' => $options]);
    }

    /**
     * The submission status as last recorded (`invoice.read`).
     *
     * @return GetInvoiceStatusData
     */
    public function getStatus(string $id, ?RequestOptions $options = null): array
    {
        return $this->call('GET', '/i/v1/invoices/' . self::seg($id) . '/status', ['options' => $options]);
    }

    /**
     * Records a payment against an issued invoice (`invoice.record_payment`). Charged once per
     * status report relayed to the tax authority; a no-op change is free. Not retried by the SDK.
     *
     * @param RecordInvoicePaymentBody $params
     *
     * @return RecordInvoicePaymentData
     */
    public function recordPayment(string $id, array $params, ?RequestOptions $options = null): array
    {
        return $this->call('PATCH', '/i/v1/invoices/' . self::seg($id) . '/payment-status', ['body' => $params, 'options' => $options]);
    }

    /**
     * The tax authority's own decrypted copy of a registered invoice (`invoice.download_authority_copy`).
     *
     * @return GetInvoiceAuthorityCopyData
     */
    public function getAuthorityCopy(string $id, ?RequestOptions $options = null): array
    {
        return $this->call('GET', '/i/v1/invoices/' . self::seg($id) . '/authority-download', ['options' => $options]);
    }
}
