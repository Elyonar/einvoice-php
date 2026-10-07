<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Exception;

/**
 * Base class of every exception the SDK throws.
 *
 * The hierarchy mirrors einvoice-js's `EInvoiceError` family with the PHP `Exception` suffix:
 * `EInvoiceError` → `EInvoiceException`, `EInvoiceApiError` → `ApiException`,
 * `EInvoiceValidationError` → `ValidationException`, `EInvoiceAuthenticationError` →
 * `AuthenticationException`, `EInvoiceInsufficientCreditsError` → `InsufficientCreditsException`,
 * `EInvoicePermissionError` → `PermissionException`, `EInvoiceNotFoundError` → `NotFoundException`,
 * `EInvoiceConflictError` → `ConflictException`, `EInvoiceRateLimitError` → `RateLimitException`,
 * `EInvoiceServerError` → `ServerException`, `EInvoiceTimeoutError` → `TimeoutException`,
 * `EInvoiceConnectionError` → `ConnectionException`, `EInvoiceConfigError` → `ConfigException`,
 * `EInvoiceWebhookError` → `WebhookException`.
 */
class EInvoiceException extends \RuntimeException
{
}
