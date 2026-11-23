<?php
declare(strict_types=1);

namespace Haleef\Support;

use PDO;
use RuntimeException;
use Throwable;

final class ContactEnquiries
{
    public const STATUS = ['new'=>'New','in_progress'=>'In progress','closed'=>'Closed'];
    public const MAIL_STATUS = ['queued'=>'Queued','sent'=>'Sent to Brevo','failed'=>'Failed'];

    public static function submit(PDO $pdo, array $input, string $recipient): int
    {
        $data = ContactEmail::fields($input);
        $pdo->beginTransaction();
        try {
            $pdo->prepare('INSERT INTO contact_enquiries (name,email,company,enquiry_type,location,message) VALUES (?,?,?,?,?,?)')
                ->execute([$data['name'],$data['email'],$data['company'],$data['type'],$data['location'],$data['message']]);
            $id = (int)$pdo->lastInsertId();
            $data['enquiry_url'] = rtrim(Env::get('APP_URL','https://www.haleefglobal.com') ?: 'https://www.haleefglobal.com','/') . '/admin/contact-enquiries/' . $id;
            $copy = EmailTemplates::sendCopy($pdo,'contact_staff',$data);
            $pdo->prepare('INSERT INTO contact_email_jobs (enquiry_id,recipient,subject,body,html_body) VALUES (?,?,?,?,?)')
                ->execute([$id,$recipient,$copy['subject'],$copy['text'],$copy['html']]);
            $pdo->commit();
            return $id;
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $ex;
        }
    }

    public static function process(PDO $pdo, ?callable $transport = null): int
    {
        if ((int)$pdo->query("SELECT GET_LOCK('haleef_contact_email_worker',0)")->fetchColumn() !== 1) return 0;
        try {
            $jobs = $pdo->query("SELECT j.*,e.email FROM contact_email_jobs j JOIN contact_enquiries e ON e.id=j.enquiry_id
                WHERE j.status='queued' AND j.available_at<=NOW() ORDER BY j.available_at,j.enquiry_id LIMIT 10")->fetchAll();
            foreach ($jobs as $job) {
                $attempt = (int)$job['attempts'] + 1;
                try {
                    $provider = ($transport ?? [self::class,'send'])($job);
                } catch (RuntimeException $error) {
                    $delay = [2,10,30,90,240][min(4,$attempt-1)];
                    $pdo->prepare("UPDATE contact_email_jobs SET status=?,attempts=?,last_error=?,available_at=DATE_ADD(NOW(), INTERVAL $delay MINUTE) WHERE enquiry_id=?")
                        ->execute([$attempt>=5?'failed':'queued',$attempt,substr($error->getMessage(),0,255),$job['enquiry_id']]);
                    continue;
                }
                $pdo->prepare("UPDATE contact_email_jobs SET status='sent',attempts=?,provider_id=?,sent_at=NOW(),last_error=NULL WHERE enquiry_id=?")
                    ->execute([$attempt,$provider,$job['enquiry_id']]);
            }
            return count($jobs);
        } finally {
            $pdo->query("SELECT RELEASE_LOCK('haleef_contact_email_worker')");
        }
    }

    public static function payload(array $job, string $sender): array
    {
        return [
            'sender'=>['name'=>'Haleef Website','email'=>$sender],'to'=>[['email'=>$job['recipient']]],
            'replyTo'=>['email'=>$job['email']],'subject'=>$job['subject'],
            'textContent'=>$job['body'],'htmlContent'=>$job['html_body'],'tags'=>['haleef-contact'],
        ];
    }

    private static function send(array $job): string
    {
        [$key,$sender] = ContactEmail::sender();
        return ContactEmail::deliver($key,self::payload($job,$sender));
    }
}
