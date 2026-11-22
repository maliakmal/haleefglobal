<?php
declare(strict_types=1);

$whatsappNumber = trim(\Haleef\Support\Env::fromFile('WHATSAPP_NUMBER') ?? '');
if (!preg_match('/^[1-9][0-9]{6,14}$/D', $whatsappNumber)) return;
require_once __DIR__ . '/learning-icons.php';
?>
<a class="whatsapp-contact" href="https://wa.me/<?= e($whatsappNumber) ?>" target="_blank" rel="noopener noreferrer" aria-label="Talk to us on WhatsApp (opens in a new tab)">
    <?= learning_icon('whatsapp') ?>
    <span class="whatsapp-contact-label" aria-hidden="true">Talk to us on WhatsApp</span>
</a>
