<?php
declare(strict_types=1);

namespace Haleef\Payments;

interface PaymentMethodInterface
{
    public function code(): string;

    public function supportsOnlineCheckout(): bool;

    /** @return array<string, string> */
    public function customerInstructions(array $configuration, array $order): array;
}

