<?php
declare(strict_types=1);

namespace Haleef\Orders;

use Haleef\Support\{EmailTemplates, Env};
use PDO;
use RuntimeException;

final class RegistrationEmail
{
    public static function sender(): string
    {
        return trim(Env::get('REGISTRATION_EMAIL_FROM') ?: (Env::get('AGENDA_EMAIL_FROM') ?? ''));
    }

    public static function configured(): bool
    {
        $key = trim(Env::get('BREVO_API_KEY') ?? '');
        return $key !== '' && !preg_match('/[\r\n]/', $key)
            && (bool)filter_var(self::sender(), FILTER_VALIDATE_EMAIL) && function_exists('curl_init');
    }

    public static function queue(PDO $pdo, int $orderId, array $invoice, array $delegates): void
    {
        $insert = $pdo->prepare('INSERT INTO order_registration_email_jobs (order_id,registration_id,recipient,subject,body,html_body,attach_invoice) VALUES (?,?,?,?,?,?,?)');
        $templates = [];
        foreach ($delegates as $i => $delegate) {
            $key = $i === 0 ? 'registration_contact' : 'registration_delegate';
            $t = $templates[$key] ??= EmailTemplates::load($pdo,$key);
            $copy = EmailTemplates::render($key,$t['subject'],$t['body'],[
                'first_name'=>$delegate['first_name'],'training_title'=>$invoice['training'],'schedule'=>$invoice['schedule'],
                'mode'=>$invoice['mode'],'booking_reference'=>$invoice['order'],'registration_reference'=>$delegate['registration_number'],
                'invoice_number'=>$invoice['number'],'amount'=>$invoice['currency'].' '.number_format($invoice['total_cents']/100,2,'.',','),
                'payment_due'=>$invoice['due'],'payment_reference'=>$invoice['bank']['reference'],
            ]);
            $insert->execute([$orderId,$delegate['registration_id'],$delegate['email'],$copy['subject'],$copy['text'],$copy['html'],(int)($i === 0)]);
        }
    }

    public static function process(PDO $pdo, callable $transport): int
    {
        if ((int)$pdo->query("SELECT GET_LOCK('haleef_registration_email_worker',0)")->fetchColumn() !== 1) return 0;
        try {
            // Do not send a payment request after payment or cancellation.
            $pdo->prepare("UPDATE order_registration_email_jobs j JOIN orders o ON o.id=j.order_id
                SET j.status='failed',j.last_error='Skipped: order no longer awaiting payment'
                WHERE j.status='queued' AND (o.status NOT IN ('pending_payment','payment_review','partially_paid') OR o.payment_due_at <= ?)")
                ->execute([date('Y-m-d H:i:s')]);
            $jobs = $pdo->query("SELECT j.*,i.invoice_number,IF(j.attach_invoice,i.pdf,NULL) AS pdf
                FROM order_registration_email_jobs j JOIN order_invoices i ON i.order_id=j.order_id
                WHERE j.status='queued' AND j.available_at<=NOW() ORDER BY j.available_at,j.id LIMIT 10")->fetchAll();
            foreach ($jobs as $job) {
                $attempt = (int)$job['attempts'] + 1;
                try {
                    $providerId = $transport($job);
                } catch (RuntimeException $error) {
                    $delay = [2,10,30,90,240][min(4,$attempt-1)];
                    $pdo->prepare("UPDATE order_registration_email_jobs SET status=?,attempts=?,last_error=?,
                        available_at=DATE_ADD(NOW(),INTERVAL $delay MINUTE) WHERE id=?")
                        ->execute([$attempt>=5?'failed':'queued',$attempt,substr($error->getMessage(),0,255),$job['id']]);
                    continue;
                }
                $pdo->prepare("UPDATE order_registration_email_jobs SET status='sent',attempts=?,provider_id=?,sent_at=NOW(),last_error=NULL WHERE id=?")
                    ->execute([$attempt,$providerId,$job['id']]);
            }
            return count($jobs);
        } finally {
            $pdo->query("SELECT RELEASE_LOCK('haleef_registration_email_worker')");
        }
    }

    public static function payload(array $job): array
    {
        $payload = [
            'sender'=>['email'=>self::sender(),'name'=>'Haleef Learning'],
            'replyTo'=>['email'=>Invoices::issuer()['email']],
            'to'=>[['email'=>$job['recipient']]],
            'subject'=>$job['subject'],'textContent'=>$job['body'],
            'htmlContent'=>$job['html_body'] ?? ('<html><body><p>' . nl2br(htmlspecialchars($job['body'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</p></body></html>'),
            'tags'=>['haleef-registration'],
        ];
        if ($job['attach_invoice']) {
            if (!is_string($job['pdf']) || !str_starts_with($job['pdf'],'%PDF-')) throw new RuntimeException('Invoice attachment unavailable.');
            $payload['attachment'] = [['name'=>$job['invoice_number'].'.pdf','content'=>base64_encode($job['pdf'])]];
        }
        return $payload;
    }

    public static function sendBrevo(array $job): string
    {
        $payload = self::payload($job);
        $api = curl_init('https://api.brevo.com/v3/smtp/email');
        if ($api === false) throw new RuntimeException('Could not initialize Brevo.');
        try {
            curl_setopt_array($api, [
                CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,
                CURLOPT_HTTPHEADER=>['accept: application/json','content-type: application/json','api-key: ' . trim(Env::get('BREVO_API_KEY') ?? '')],
                CURLOPT_POSTFIELDS=>json_encode($payload, JSON_THROW_ON_ERROR),
            ]);
            $body = curl_exec($api);
            $status = (int)curl_getinfo($api,CURLINFO_RESPONSE_CODE);
            if ($body === false || $status !== 201) throw new RuntimeException('Brevo delivery failed (HTTP ' . $status . ', cURL ' . curl_errno($api) . ').');
            return substr((string)(json_decode($body,true)['messageId'] ?? ''),0,255);
        } finally {
            curl_close($api);
        }
    }
}
