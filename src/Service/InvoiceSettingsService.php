<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Service;

use Useyona\EInvoice\RequestOptions;

/**
 * Invoice settings (`/i/v1/invoice-settings`): read-only for an API key.
 *
 * @phpstan-import-type GetInvoiceSettingsData from \Useyona\EInvoice\Generated\Types
 */
final class InvoiceSettingsService extends BaseService
{
    /**
     * The organisation's invoice settings in the key's mode (`invoice.read`).
     *
     * @return GetInvoiceSettingsData
     */
    public function get(?RequestOptions $options = null): array
    {
        return $this->call('GET', '/i/v1/invoice-settings', ['options' => $options]);
    }
}
