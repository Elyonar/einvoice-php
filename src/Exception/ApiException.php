<?php

declare(strict_types=1);

namespace Useyona\EInvoice\Exception;

/**
 * The API refused the request. Every refusal carries the v2 envelope
 * `{ meta: { statusCode, errorCode, message, errors, requestId }, data: null }`.
 *
 * ```php
 * try {
 *     $client->invoices->get('0192f3a0-0000-7000-8000-000000000000');
 * } catch (ApiException $e) {
 *     $e->status;    // 404
 *     $e->errorCode; // 'RES001'
 *     $e->requestId; // quote this to support
 * }
 * ```
 */
class ApiException extends EInvoiceException
{
    /**
     * `meta.errors`, each `{ field, message }`.
     *
     * @var list<ApiErrorDetail>
     */
    public readonly array $errors;

    /**
     * @param int                  $status     HTTP status
     * @param string               $message    `meta.message`, or `HTTP <status>` when the body was not the envelope
     * @param string|null          $errorCode  `meta.errorCode`: `AUTH…`, `VAL…`, `RES…`, `BIZ…` or `SYS…`. Branch on this, never on the message.
     * @param list<ApiErrorDetail> $errors     `meta.errors`, unpacked
     * @param string|null          $requestId  `meta.requestId`, or the `x-request-id` header. Quote it in a support request.
     * @param int|null             $retryAfter seconds from the `Retry-After` header, when the API sent one
     * @param mixed                $body       the parsed response body, for anything the fields above do not carry
     */
    public function __construct(
        /** HTTP status. */
        public readonly int $status,
        string $message,
        /** `meta.errorCode` (e.g. `VAL001`, `RES001`, `AUTH019`, `BIZ001`, `SYS005`). */
        public readonly ?string $errorCode = null,
        array $errors = [],
        /** `meta.requestId`, falling back to the `x-request-id` header. */
        public readonly ?string $requestId = null,
        /** Seconds the API asked the caller to wait (`Retry-After`), when present. */
        public readonly ?int $retryAfter = null,
        /** The parsed response body. */
        public readonly mixed $body = null,
    ) {
        parent::__construct($message, $status);
        $this->errors = $errors;
    }

    /** Alias of {@see $status}, kept from 0.7. */
    public function getStatusCode(): int
    {
        return $this->status;
    }

    /**
     * Builds the exception class matching an HTTP status (einvoice-js's `apiErrorFor`).
     *
     * @param list<ApiErrorDetail> $errors
     *
     * @internal
     */
    public static function forStatus(
        int $status,
        string $message,
        ?string $errorCode = null,
        array $errors = [],
        ?string $requestId = null,
        ?int $retryAfter = null,
        mixed $body = null,
    ): self {
        $class = match (true) {
            $status === 400, $status === 422 => ValidationException::class,
            $status === 401 => AuthenticationException::class,
            $status === 402 => InsufficientCreditsException::class,
            $status === 403 => PermissionException::class,
            $status === 404 => NotFoundException::class,
            $status === 409 => ConflictException::class,
            $status === 429 => RateLimitException::class,
            $status >= 500 => ServerException::class,
            default => self::class,
        };

        return new $class($status, $message, $errorCode, $errors, $requestId, $retryAfter, $body);
    }
}
