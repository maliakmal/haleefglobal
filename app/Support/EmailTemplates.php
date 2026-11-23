<?php
declare(strict_types=1);

namespace Haleef\Support;

use Haleef\Training\Markdown;
use PDO;
use RuntimeException;

final class EmailTemplates
{
    public const LABELS = [
        'agenda_request'=>'Agenda request',
        'registration_contact'=>'Registration — booking contact',
        'registration_delegate'=>'Registration — delegate',
        'contact_staff'=>'Contact enquiry — staff notification',
    ];
    private const AGENDA = ['training_title'=>'Leadership essentials','edition_date'=>'25 Nov 2026','agenda_url'=>'https://example.test/agenda.pdf'];
    private const REGISTRATION = [
        'first_name'=>'Sam','training_title'=>'Leadership essentials','schedule'=>'25 Nov 2026 · 09:00–17:00',
        'mode'=>'In person','booking_reference'=>'HL-2026-000001','registration_reference'=>'HLR-2026-000001',
    ];
    private const INVOICE = ['invoice_number'=>'INV-HL-2026-000001','amount'=>'AED 250.00','payment_due'=>'24 Nov 2026, 17:00 GST','payment_reference'=>'HL-2026-000001'];
    private const CONTACT = [
        'name'=>'Sam Example','email'=>'sam@example.test','company'=>'Example Events','type'=>'Event consultation',
        'location'=>'Dubai','message'=>"We’re planning a team event.\nCan we discuss options?",'enquiry_url'=>'https://example.test/admin/contact-enquiries/1',
    ];
    private const LINKS = ['agenda_url','enquiry_url'];

    public static function samples(string $key): array
    {
        return match ($key) {
            'agenda_request'=>self::AGENDA,
            'registration_contact'=>self::REGISTRATION+self::INVOICE,
            'registration_delegate'=>self::REGISTRATION,
            'contact_staff'=>self::CONTACT,
            default=>throw new RuntimeException('Unknown email template.'),
        };
    }

    public static function load(PDO $pdo, string $key): array
    {
        self::samples($key);
        $q = $pdo->prepare('SELECT subject,body,updated_at FROM learning_email_templates WHERE template_key=?');
        $q->execute([$key]);
        return $q->fetch() ?: throw new RuntimeException('Email template is missing.');
    }

    public static function validate(string $key, string $subject, string $body): void
    {
        $known = self::samples($key);
        if ($subject === '' || strlen($subject)>200 || preg_match('/[\r\n]/', $subject) || $body === ''
            || strlen($body)>8000 || !preg_match('//u', $subject . $body)) {
            throw new RuntimeException('Enter a one-line subject and a message of up to 8,000 bytes.');
        }
        foreach ([$subject,$body] as $source) {
            $remaining = preg_replace_callback('/\{\{([a-z_]+)\}\}/', static function (array $m) use ($known): string {
                if (!array_key_exists($m[1], $known)) throw new RuntimeException('Unknown placeholder: ' . $m[0]);
                return '';
            }, $source);
            if (str_contains($remaining,'{{') || str_contains($remaining,'}}')) throw new RuntimeException('Use the exact placeholder names shown.');
        }
        foreach (self::LINKS as $link) {
            if (str_contains($subject,'{{'.$link.'}}')) throw new RuntimeException('Put URL placeholders in the message body.');
        }
        if (preg_match('/\]\([^\n)]*\{\{/', $body)) throw new RuntimeException('Insert URL placeholders as standalone text, outside Markdown links.');
        $required = match ($key) {'agenda_request'=>'agenda_url','contact_staff'=>'message',default=>'registration_reference'};
        if (!str_contains($body,'{{'.$required.'}}')) throw new RuntimeException('Keep {{'.$required.'}} in the message.');
    }

    public static function sendCopy(PDO $pdo, string $key, array $values): array
    {
        $t = self::load($pdo,$key);
        return self::render($key,$t['subject'],$t['body'],$values);
    }

    public static function render(string $key, string $subject, string $body, array $values): array
    {
        $escape = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $subjectTokens = $plainTokens = $htmlTokens = [];
        $markers = [];
        foreach (self::samples($key) as $token=>$sample) {
            $value = (string)($values[$token] ?? '');
            $marker = 'HAL' . bin2hex(random_bytes(10)) . 'END';
            $placeholder = '{{'.$token.'}}';
            $markers[$placeholder] = $marker;
            $subjectTokens[$placeholder] = preg_replace('/[\x00-\x1f\x7f]+/', ' ', $value);
            $plainTokens[$marker] = $value;
            $htmlTokens[$marker] = nl2br($escape($value), false);
            if (in_array($token,self::LINKS,true) && filter_var($value,FILTER_VALIDATE_URL)
                && in_array(parse_url($value,PHP_URL_SCHEME),['https','http'],true)) {
                $htmlTokens[$marker] = '<a href="'.$escape($value).'">'.$escape($value).'</a>';
            }
        }
        // Parse only the editor's Markdown. Replace data in HTML text nodes, never in attributes.
        $fragment = Markdown::render(strtr($body,$markers));
        $nodes = preg_split('/(<[^>]*>)/', $fragment, -1, PREG_SPLIT_DELIM_CAPTURE);
        $insideLink = false;
        foreach ($nodes as $i=>$node) {
            if ($i % 2 === 0) {
                // A URL placeholder inside an existing anchor should remain text, not a nested link.
                $nodes[$i] = strtr($node,$insideLink ? array_map('strip_tags',$htmlTokens) : $htmlTokens);
            } elseif (str_starts_with($node,'<a ')) {
                $insideLink = true;
            } elseif ($node === '</a>') {
                $insideLink = false;
            }
        }
        $plain = preg_replace_callback('~<a [^>]*href="([^"]+)"[^>]*>(.*?)</a>~s', static fn(array $m): string => $m[2].' ('.$m[1].')', $fragment);
        $plain = str_replace(['<li>','</li>','<br>','</p>','</h3>','</h4>','</h5>','</h6>','</ul>','</ol>'],
            ['- ',"\n","\n","\n\n","\n\n","\n\n","\n\n","\n\n","\n","\n"],$plain);
        $title = mb_substr(strtr($subject,$subjectTokens), 0, 236);
        return [
            'subject'=>$title,
            'text'=>trim(strtr(html_entity_decode(strip_tags($plain),ENT_QUOTES | ENT_HTML5,'UTF-8'),$plainTokens)),
            'html'=>'<!doctype html><html lang="en"><meta charset="utf-8"><body style="margin:0;background:#f4f2fa;color:#231b35;font-family:Arial,sans-serif;font-size:16px;line-height:1.55"><div style="max-width:580px;margin:20px auto;background:#fff;padding:24px"><div style="border-bottom:2px solid #493178;padding-bottom:12px;margin-bottom:16px;color:#493178;font-weight:bold">Haleef</div>'.implode('',$nodes).'</div></body></html>',
        ];
    }
}
