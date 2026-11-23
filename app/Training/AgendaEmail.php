<?php
declare(strict_types=1);

namespace Haleef\Training;

use Haleef\Support\{EmailTemplates, Env};
use PDO;
use RuntimeException;

final class AgendaEmail
{
    public function __construct(private readonly PDO $pdo) {}

    public static function configured(): bool
    {
        return Env::get('AGENDA_EMAIL_ENABLED') === '1'
            && (bool) Env::get('BREVO_API_KEY')
            && (bool) filter_var(Env::get('AGENDA_EMAIL_FROM'), FILTER_VALIDATE_EMAIL)
            && function_exists('curl_init');
    }

    public function eligible(?int $trainingId = null, ?int $editionId = null): array
    {
        $q = $this->pdo->prepare("SELECT e.id,e.slug,e.starts_at,e.ends_at,e.timezone,e.delivery_mode,e.venue_name,t.title,t.slug AS training_slug,a.id AS asset_id,a.public_url
            FROM training_editions e JOIN trainings t ON t.id=e.training_id JOIN brands b ON b.id=t.brand_id
            JOIN media_assets a ON a.id=e.agenda_asset_id AND a.edition_id=e.id AND a.brand_id=b.id
            WHERE b.code='haleef-learning' AND t.status='published' AND e.publication_status='published'
                AND a.asset_type='agenda' AND a.is_published=1 AND a.public_url LIKE 'https://%'
                AND (? IS NULL OR t.id=?) AND (? IS NULL OR e.id=?) ORDER BY e.starts_at DESC");
        $q->execute([$trainingId,$trainingId,$editionId,$editionId]);
        return $q->fetchAll();
    }

    public function template(): array
    {
        return $this->pdo->query("SELECT subject,body FROM learning_email_templates WHERE template_key='agenda_request'")->fetch()
            ?: throw new RuntimeException('Agenda email template is missing.');
    }

    public function saveTemplate(string $subject, string $body): void
    {
        $known = ['{{training_title}}','{{edition_date}}','{{agenda_url}}'];
        $combined = str_replace($known, '', $subject . $body);
        if ($subject === '' || strlen($subject)>200 || preg_match('/[\r\n]/', $subject) || str_contains($subject,'{{agenda_url}}')
            || strlen($body)>8000 || !str_contains($body,'{{agenda_url}}') || str_contains($combined,'{{') || str_contains($combined,'}}')) {
            throw new RuntimeException('Enter a subject and body using the supported placeholders. The body must contain {{agenda_url}}.');
        }
        $this->pdo->prepare("UPDATE learning_email_templates SET subject=?,body=? WHERE template_key='agenda_request'")->execute([$subject,$body]);
    }

    /** Returns whether this request is queued (or was recently queued). */
    public function request(array $agenda, string $email, string $source): bool
    {
        $sourceHash = hash_hmac('sha256', $source, Env::get('APP_KEY') ?: 'haleef-agenda');
        $mailEnabled = self::configured();
        if ($mailEnabled) {
            $lock = $this->pdo->prepare('SELECT GET_LOCK(?,3)');
            $lock->execute(['agenda-' . substr($sourceHash,0,48)]);
            if ((int) $lock->fetchColumn() !== 1) throw new RuntimeException('Please try again in a moment.');
        }
        try {
            $this->pdo->beginTransaction();
            $this->pdo->prepare('INSERT INTO agenda_interests (edition_id,asset_id,email) VALUES (?,?,?)
                ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id),last_requested_at=CURRENT_TIMESTAMP')
                ->execute([$agenda['id'],$agenda['asset_id'],$email]);
            $interestId = (int)$this->pdo->lastInsertId();
            if ($mailEnabled) {
                $q = $this->pdo->prepare("SELECT id FROM learning_agenda_email_jobs WHERE interest_id=?
                    AND (status='queued' OR (status='sent' AND sent_at>DATE_SUB(NOW(), INTERVAL 15 MINUTE))) LIMIT 1");
                $q->execute([$interestId]);
                if (!$q->fetchColumn()) {
                    $q = $this->pdo->prepare('SELECT COUNT(*) FROM learning_agenda_email_jobs WHERE source_hash=? AND created_at>DATE_SUB(NOW(), INTERVAL 1 HOUR)');
                    $q->execute([$sourceHash]);
                    if ((int)$q->fetchColumn() >= 12) throw new RuntimeException('Too many agenda requests. Please try again later.');
                    $copy = EmailTemplates::sendCopy($this->pdo,'agenda_request',[
                        'training_title'=>$agenda['title'],'edition_date'=>Editorial::date($agenda,'starts_at')->format('d M Y'),'agenda_url'=>$agenda['public_url'],
                    ]);
                    $this->pdo->prepare('INSERT INTO learning_agenda_email_jobs (interest_id,source_hash,subject,body,html_body) VALUES (?,?,?,?,?)')
                        ->execute([$interestId,$sourceHash,$copy['subject'],$copy['text'],$copy['html']]);
                }
            }
            $this->pdo->commit();
            return $mailEnabled;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        } finally {
            if ($mailEnabled) $this->pdo->prepare('SELECT RELEASE_LOCK(?)')->execute(['agenda-' . substr($sourceHash,0,48)]);
        }
    }

    public function process(callable $transport): int
    {
        if ((int)$this->pdo->query("SELECT GET_LOCK('haleef_agenda_email_worker',0)")->fetchColumn() !== 1) return 0;
        try {
            $jobs = $this->pdo->query("SELECT j.*,l.email FROM learning_agenda_email_jobs j JOIN agenda_interests l ON l.id=j.interest_id
                WHERE j.status='queued' AND j.available_at<=NOW() ORDER BY j.available_at,j.id LIMIT 10")->fetchAll();
            foreach ($jobs as $job) {
                $attempt = (int)$job['attempts'] + 1;
                try {
                    $providerId = $transport($job['email'],$job['subject'],$job['body'],$job['html_body'] ?? null);
                } catch (RuntimeException $error) {
                    $delay = [5,30,120,360,720][min(4,$attempt-1)];
                    $this->pdo->prepare("UPDATE learning_agenda_email_jobs SET status=?,attempts=?,last_error=?,
                        available_at=DATE_ADD(NOW(),INTERVAL $delay MINUTE) WHERE id=?")
                        ->execute([$attempt>=5?'failed':'queued',$attempt,substr($error->getMessage(),0,255),$job['id']]);
                    continue;
                }
                $this->pdo->prepare("UPDATE learning_agenda_email_jobs SET status='sent', attempts=?,provider_id=?,sent_at=NOW(),last_error=NULL WHERE id=?")
                    ->execute([$attempt,$providerId,$job['id']]);
            }
            return count($jobs);
        } finally {
            $this->pdo->query("SELECT RELEASE_LOCK('haleef_agenda_email_worker')");
        }
    }

    public static function sendBrevo(string $email, string $subject, string $body, ?string $html = null): string
    {
        $api = curl_init('https://api.brevo.com/v3/smtp/email');
        curl_setopt_array($api, [
            CURLOPT_POST=>true, CURLOPT_RETURNTRANSFER=>true, CURLOPT_CONNECTTIMEOUT=>5, CURLOPT_TIMEOUT=>15,
            CURLOPT_HTTPHEADER=>['accept: application/json','content-type: application/json','api-key: ' . Env::get('BREVO_API_KEY')],
            CURLOPT_POSTFIELDS=>json_encode(['sender'=>['email'=>Env::get('AGENDA_EMAIL_FROM'),'name'=>Env::get('AGENDA_EMAIL_FROM_NAME') ?: 'Haleef Learning'],
                'to'=>[['email'=>$email]],'subject'=>$subject,'textContent'=>$body,
                'htmlContent'=>$html ?? ('<html><body><p>'.nl2br(htmlspecialchars($body,ENT_QUOTES | ENT_SUBSTITUTE,'UTF-8')).'</p></body></html>'),
                'tags'=>['haleef-agenda']], JSON_THROW_ON_ERROR),
        ]);
        $body = curl_exec($api);
        $status = (int)curl_getinfo($api,CURLINFO_RESPONSE_CODE);
        if ($body === false) throw new RuntimeException('Could not connect to Brevo.');
        if ($status !== 201) throw new RuntimeException("Brevo returned HTTP $status.");
        $result = json_decode($body,true);
        return substr((string)($result['messageId'] ?? ''),0,255);
    }
}
