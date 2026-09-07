<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !empty($_POST['website'] ?? '')) {
    header('Location: ../contact.php?error=1'); exit;
}
$name = trim((string)($_POST['name'] ?? ''));
$email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
$message = trim((string)($_POST['message'] ?? ''));
if ($name === '' || !$email || $message === '') { header('Location: ../contact.php?error=1'); exit; }
$body = "New Haleef website enquiry\n\nName: {$name}\nEmail: {$email}\nCompany: " . trim((string)($_POST['company'] ?? '')) . "\nType: " . trim((string)($_POST['type'] ?? '')) . "\nLocation: " . trim((string)($_POST['location'] ?? '')) . "\n\nBrief:\n{$message}";
$headers = "From: Haleef Website <" . SITE_EMAIL . ">\r\nReply-To: {$email}\r\nContent-Type: text/plain; charset=UTF-8";
$ok = @mail(SITE_EMAIL, 'New website enquiry from ' . $name, $body, $headers);
header('Location: ../contact.php?' . ($ok ? 'sent=1' : 'error=1')); exit;
