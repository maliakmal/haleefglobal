<?php
declare(strict_types=1);

namespace Haleef\Orders;

use DateTimeImmutable;
use Haleef\Support\Env;
use Haleef\Training\Editorial;
use PDO;
use RuntimeException;

final class Invoices
{
    public static function enabled(): bool
    {
        return Env::get('BANK_TRANSFER_INVOICES_ENABLED') === '1';
    }

    public static function issuer(): array
    {
        $issuer = [
            'name'=>trim(Env::get('INVOICE_ISSUER_NAME') ?? ''),
            'address'=>trim(Env::get('INVOICE_ISSUER_ADDRESS') ?? ''),
            'license'=>trim(Env::get('INVOICE_ISSUER_LICENSE') ?? ''),
            'email'=>trim(Env::get('INVOICE_REPLY_TO', 'team@haleefglobal.com') ?? ''),
        ];
        if ($issuer['name'] === '' || $issuer['address'] === '' || !filter_var($issuer['email'], FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Invoice issuer name, address and reply-to must be configured.');
        }
        return $issuer;
    }

    public static function billingAddress(mixed $input): string
    {
        $value = is_string($input) ? trim(str_replace(["\r\n", "\r"], "\n", $input)) : '';
        if (!preg_match('//u', $value) || mb_strlen($value) > 500 || substr_count($value, "\n") > 6 || preg_match('/[\x00-\x09\x0B-\x1F\x7F]/', $value)) {
            throw new RuntimeException('Enter a billing address of up to 500 characters and seven lines.');
        }
        return $value;
    }

    public static function issue(PDO $pdo, array $order, array $edition, array $contact, string $address, DateTimeImmutable $issued, DateTimeImmutable $due, int $unitCents): array
    {
        $method = $order['payment_method'];
        $bank = $method['adapter']->customerInstructions($method['configuration'], $order);
        foreach (['account_name','bank_name','iban'] as $field) {
            if (empty($bank[$field])) throw new RuntimeException('Bank transfer details are incomplete.');
        }
        $start = Editorial::date($edition, 'starts_at');
        $end = Editorial::date($edition, 'ends_at');
        $mode = ['online'=>'Live online','in_person'=>'In person','hybrid'=>'Hybrid'][$edition['delivery_mode']];
        if ($edition['delivery_mode'] !== 'online' && !empty($edition['venue_name'])) {
            $mode .= ' | ' . $edition['venue_name'];
        }
        $data = [
            'number'=>'INV-' . $order['order_number'],
            'order'=>$order['order_number'],
            'training'=>$edition['training_title'],
            'edition'=>$start->format('d M Y') . ($start->format('Y-m-d') === $end->format('Y-m-d') ? '' : ' - ' . $end->format('d M Y')) . ' | ' . $mode,
            'mode'=>$mode,
            'schedule'=>$start->format('d M Y, H:i') . ' - ' . $end->format('d M Y, H:i') . ' ' . Editorial::timezoneLabel($edition),
            'issued'=>$issued->format('d M Y'),
            'due'=>$due->format('d M Y, H:i T'),
            'name'=>$contact['name'],'company'=>$contact['company'],'email'=>$contact['email'],'phone'=>$contact['phone'],'address'=>$address,
            'currency'=>$order['currency'],'quantity'=>$order['quantity'],'unit_cents'=>$unitCents,'total_cents'=>$unitCents * $order['quantity'],
            'issuer'=>self::issuer(),'bank'=>$bank,
        ];
        $pdf = InvoicePdf::render($data);
        $insert = $pdo->prepare('INSERT INTO order_invoices (order_id,invoice_number,payment_snapshot,pdf) VALUES (?,?,?,?)');
        $insert->bindValue(1, $order['id'], PDO::PARAM_INT);
        $insert->bindValue(2, $data['number']);
        $insert->bindValue(3, json_encode($bank, JSON_THROW_ON_ERROR));
        $insert->bindValue(4, $pdf, PDO::PARAM_LOB);
        $insert->execute();
        return $data;
    }

    public static function download(PDO $pdo, int $orderId): never
    {
        $statement = $pdo->prepare('SELECT invoice_number,pdf FROM order_invoices WHERE order_id=?');
        $statement->execute([$orderId]);
        $invoice = $statement->fetch();
        if (!$invoice || !preg_match('/^INV-HL-[0-9]{4}-[0-9]+$/D', $invoice['invoice_number'])) {
            http_response_code(404);
            exit;
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $invoice['invoice_number'] . '.pdf"');
        header('X-Content-Type-Options: nosniff');
        header('Content-Length: ' . strlen($invoice['pdf']));
        echo $invoice['pdf'];
        exit;
    }

    public static function privateHeaders(): void
    {
        header('Cache-Control: private, no-store');
        header('Referrer-Policy: no-referrer');
        header('X-Robots-Tag: noindex, nofollow, noarchive');
    }

    public static function grant(array $order): void
    {
        $access = array_filter($_SESSION['invoice_access'] ?? [], static fn(array $grant): bool => $grant['expires'] >= time());
        $access[$order['order_number']] = ['order_id'=>$order['id'],'expires'=>time() + 86400];
        $_SESSION['invoice_access'] = array_slice($access, -20, null, true);
    }

    public static function sessionOrderId(string $orderNumber): ?int
    {
        $grant = $_SESSION['invoice_access'][$orderNumber] ?? null;
        return $grant && $grant['expires'] >= time() ? (int)$grant['order_id'] : null;
    }
}
