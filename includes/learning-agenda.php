<?php
declare(strict_types=1);

use Haleef\Support\Csrf;
use Haleef\Training\{AgendaEmail, Editorial};

$agendaNotice = $_SESSION['agenda_notice'] ?? null;
if (($agendaNotice['path'] ?? null) !== $agendaReturn) $agendaNotice = null;
if ($agendaNotice) unset($_SESSION['agenda_notice']);
if (empty($agendaOptions) && empty($agendaInPreview) && !$agendaNotice) return;
require_once __DIR__ . '/learning-schedule.php';
?>
<section id="agenda" class="container learning-band" aria-labelledby="agenda-heading" data-reveal>
<div class="learning-agenda">
    <div><span class="eyebrow">Programme guide</span><h2 id="agenda-heading">Request the agenda</h2>
        <p><?= AgendaEmail::configured() ? 'Enter your email and we’ll send you the full agenda link.' : 'Enter your email to get the full agenda PDF.' ?></p>
        <a href="/privacy">Privacy policy</a></div>
    <?php if (!empty($agendaInPreview)): ?><p>Download form disabled in preview.</p>
    <?php else: ?><div>
    <?php if ($agendaNotice): ?><p role="<?= $agendaNotice['ok'] ? 'status' : 'alert' ?>"<?= $agendaNotice['ok'] ? ' data-analytics-result="agenda_request"' : '' ?>><?= e($agendaNotice['message']) ?></p>
    <?php if (!empty($agendaNotice['download'])): ?><p><a class="learning-action" href="<?= e($agendaNotice['download']) ?>">Download the PDF</a></p><?php endif; endif; ?>
    <?php if ($agendaOptions): ?><form method="post" action="/training/agenda-request"><?= Csrf::field() ?>
        <input type="hidden" name="return_path" value="<?= e($agendaReturn) ?>">
        <?php if (count($agendaOptions) === 1): ?><input type="hidden" name="edition_id" value="<?= (int)$agendaOptions[0]['id'] ?>">
        <p class="learning-agenda-edition"><?= e($agendaOptions[0]['title']) ?></p>
        <?php learning_schedule($agendaOptions[0]); ?>
        <?php else: ?><label for="agenda-edition">Course edition</label><select id="agenda-edition" name="edition_id" required><option value="">Select an edition</option>
        <?php foreach ($agendaOptions as $option): ?><option value="<?= (int)$option['id'] ?>"><?= e($option['title'] . ' · ' . Editorial::dateRange($option)) ?></option><?php endforeach; ?></select><?php endif; ?>
        <label for="agenda-email">Email address</label><input id="agenda-email" name="email" type="email" autocomplete="email" maxlength="254" required>
        <label class="honeypot" aria-hidden="true">Website<input name="website" tabindex="-1" autocomplete="off"></label>
        <button class="learning-action" type="submit"><?= AgendaEmail::configured() ? 'Email me the agenda' : 'Get the agenda' ?></button>
    </form><?php endif; ?></div><?php endif; ?>
</div></section>
