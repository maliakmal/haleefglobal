<?php
declare(strict_types=1);

namespace Haleef\Payments;

use Haleef\Support\Database;
use RuntimeException;

final class PaymentMethodRegistry
{
    /** @var array<string, class-string<PaymentMethodInterface>> */
    private const ADAPTERS = [
        'bank_transfer' => BankTransferPaymentMethod::class,
    ];

    public function enabledForBrand(int $brandId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, code, label, config_json FROM payment_methods WHERE brand_id = ? AND enabled = 1 ORDER BY display_order, id'
        );
        $statement->execute([$brandId]);

        return array_map(function (array $row): array {
            $row['configuration'] = $this->decodeConfiguration($row['config_json'] ?? null);
            $row['adapter'] = $this->adapter((string) $row['code']);
            unset($row['config_json']);
            return $row;
        }, $statement->fetchAll());
    }

    public function enabledMethod(int $brandId, string $code): array
    {
        return $this->methodForBrand($brandId, $code, true);
    }

    public function configuredMethod(int $brandId, string $code): array
    {
        return $this->methodForBrand($brandId, $code, false);
    }

    private function methodForBrand(int $brandId, string $code, bool $requireEnabled): array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, code, label, config_json FROM payment_methods WHERE brand_id = ? AND code = ?' . ($requireEnabled ? ' AND enabled = 1' : '') . ' LIMIT 1'
        );
        $statement->execute([$brandId, $code]);
        $row = $statement->fetch();

        if (!$row) {
            throw new RuntimeException('The selected payment method is not currently available.');
        }

        $row['configuration'] = $this->decodeConfiguration($row['config_json'] ?? null);
        $row['adapter'] = $this->adapter((string) $row['code']);
        unset($row['config_json']);
        return $row;
    }

    public function adapter(string $code): PaymentMethodInterface
    {
        $class = self::ADAPTERS[$code] ?? null;
        if (!$class) {
            throw new RuntimeException("No payment adapter is registered for {$code}.");
        }
        return new $class();
    }

    private function decodeConfiguration(mixed $json): array
    {
        if (!is_string($json) || $json === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }
}

