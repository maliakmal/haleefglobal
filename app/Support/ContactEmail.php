<?php
declare(strict_types=1);

namespace Haleef\Support;

use InvalidArgumentException;
use RuntimeException;

final class ContactEmail
{
    public static function send(array $input, string $recipient, ?callable $transport = null): void
    {
        $field = static function (string $name, int $limit) use ($input): string {
            $value = is_string($input[$name] ?? null) ? trim($input[$name]) : '';
            if (strlen($value) > $limit || !preg_match('//u', $value)) {
                throw new InvalidArgumentException('Invalid contact form field.');
            }
            return $value;
        };
        $name = $field('name', 200);
        $email = $field('email', 254);
        $message = $field('message', 12000);
        if ($name === '' || $message === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Name, email and message are required.');
        }
        $company = $field('company', 300);
        $type = $field('type', 200);
        $location = $field('location', 300);
        $apiKey = trim(Env::get('BREVO_API_KEY') ?? '');
        $sender = trim(Env::get('CONTACT_EMAIL_FROM') ?? '');
        if ($sender === '') $sender = trim(Env::get('AGENDA_EMAIL_FROM') ?? '');
        if ($apiKey === '' || preg_match('/[\r\n]/', $apiKey) || !filter_var($sender, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Brevo API key or verified contact sender is not configured.');
        }
        $payload = [
            'sender'=>['name'=>'Haleef Website','email'=>$sender],
            'to'=>[['email'=>$recipient]],
            'replyTo'=>['email'=>$email],
            'subject'=>'New Haleef website enquiry',
            'textContent'=>"New Haleef website enquiry\n\nName: {$name}\nEmail: {$email}\nCompany: {$company}\nType: {$type}\nLocation: {$location}\n\nBrief:\n{$message}",
            'tags'=>['haleef-contact'],
        ];
        ($transport ?? [self::class, 'deliver'])($apiKey, $payload);
    }

    private static function deliver(string $apiKey, array $payload): void
    {
        if (!function_exists('curl_init')) throw new RuntimeException('PHP cURL is not available.');
        $request = curl_init('https://api.brevo.com/v3/smtp/email');
        if ($request === false) throw new RuntimeException('Could not initialize the Brevo request.');
        try {
            curl_setopt_array($request, [
                CURLOPT_POST=>true,
                CURLOPT_RETURNTRANSFER=>true,
                CURLOPT_CONNECTTIMEOUT=>5,
                CURLOPT_TIMEOUT=>12,
                CURLOPT_HTTPHEADER=>['accept: application/json','content-type: application/json','api-key: ' . $apiKey],
                CURLOPT_POSTFIELDS=>json_encode($payload, JSON_THROW_ON_ERROR),
            ]);
            $response = curl_exec($request);
            $status = (int) curl_getinfo($request, CURLINFO_RESPONSE_CODE);
            if ($response === false) throw new RuntimeException('Brevo connection failed (cURL ' . curl_errno($request) . ').');
            if ($status !== 201) throw new RuntimeException('Brevo returned HTTP ' . $status . '.');
        } finally {
            curl_close($request);
        }
    }
}
