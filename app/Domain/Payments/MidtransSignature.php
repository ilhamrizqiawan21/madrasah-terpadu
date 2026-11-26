<?php

declare(strict_types=1);

namespace App\Domain\Payments;

/**
 * Verifies the `signature_key` Midtrans attaches to every HTTP notification.
 *
 * Midtrans signs as SHA512(order_id + status_code + gross_amount + server_key).
 * `gross_amount` must be hashed exactly as received (e.g. "150000.00"); never
 * reformat it first, or valid notifications will be rejected.
 *
 * @see https://docs.midtrans.com/docs/https-notification-webhooks
 */
final readonly class MidtransSignature
{
    public function __construct(private string $serverKey)
    {
        if ($serverKey === '') {
            throw new \InvalidArgumentException('Midtrans server key is not configured.');
        }
    }

    public function expected(string $orderId, string $statusCode, string $grossAmount): string
    {
        return hash('sha512', $orderId.$statusCode.$grossAmount.$this->serverKey);
    }

    /**
     * Uses a constant-time comparison so the check leaks no timing information.
     *
     * @param  array<string, mixed>  $payload  Raw notification body.
     */
    public function isValid(array $payload): bool
    {
        $fields = [];

        foreach (['order_id', 'status_code', 'gross_amount', 'signature_key'] as $field) {
            $value = $payload[$field] ?? null;

            if (! is_string($value) || $value === '') {
                return false;
            }

            $fields[$field] = $value;
        }

        return hash_equals(
            $this->expected($fields['order_id'], $fields['status_code'], $fields['gross_amount']),
            strtolower($fields['signature_key']),
        );
    }
}
