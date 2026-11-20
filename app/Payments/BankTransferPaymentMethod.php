<?php
declare(strict_types=1);

namespace Haleef\Payments;

final class BankTransferPaymentMethod implements PaymentMethodInterface
{
    public function code(): string
    {
        return 'bank_transfer';
    }

    public function supportsOnlineCheckout(): bool
    {
        return false;
    }

    public function customerInstructions(array $configuration, array $order): array
    {
        return [
            'account_name' => trim((string) ($configuration['account_name'] ?? '')),
            'bank_name' => trim((string) ($configuration['bank_name'] ?? '')),
            'iban' => trim((string) ($configuration['iban'] ?? '')),
            'swift' => trim((string) ($configuration['swift'] ?? '')),
            'reference' => (string) ($order['order_number'] ?? ''),
            'instructions' => trim((string) ($configuration['instructions'] ?? 'Use your order number as the payment reference.')),
        ];
    }
}

