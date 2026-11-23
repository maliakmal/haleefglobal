<?php
declare(strict_types=1);

namespace Haleef\Orders;

use Dompdf\Dompdf;
use Dompdf\Options;
use RuntimeException;

final class InvoicePdf
{
    public static function render(array $invoice): string
    {
        $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
        if (!is_file($autoload)) throw new RuntimeException('Invoice PDF dependencies are missing.');
        require_once $autoload;
        $logo = base64_encode((string)file_get_contents(__DIR__ . '/invoice-logo.png'));
        ob_start();
        try {
            require __DIR__ . '/invoice-template.php';
            $html = (string)ob_get_contents();
        } finally {
            ob_end_clean();
        }
        $pdf = new Dompdf(new Options([
            'isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false,
            'defaultFont' => 'DejaVu Sans',
        ]));
        $pdf->setPaper('A4');
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->render();
        $bytes = $pdf->output();
        if (!is_string($bytes) || !str_starts_with($bytes, '%PDF-') || strlen($bytes) > 2 * 1024 * 1024) {
            throw new RuntimeException('Invoice PDF could not be created.');
        }
        return $bytes;
    }
}
