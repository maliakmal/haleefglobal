<?php
declare(strict_types=1);

use Haleef\Training\Editorial;

require_once __DIR__ . '/learning-icons.php';

function learning_schedule(array $edition): void
{
    $mode = $edition['delivery_mode'];
    $venue = trim((string)($edition['venue_name'] ?? ''));
    $location = match ($mode) {
        'online' => 'Live online',
        'hybrid' => 'Hybrid' . ($venue !== '' ? ' · ' . $venue : ''),
        default => $venue !== '' ? $venue : 'In person',
    };
    $dates = Editorial::dateRange($edition);
    $times = Editorial::dailyTimeRange($edition);
    $multiDay = Editorial::date($edition,'starts_at')->format('Y-m-d') !== Editorial::date($edition,'ends_at')->format('Y-m-d');
    ?>
    <div class="learning-schedule" role="group" aria-label="Course schedule">
        <div class="learning-schedule-dates"><?= learning_icon('calendar') ?><strong><?= e($dates) ?></strong></div>
        <div class="learning-schedule-logistics">
            <span class="learning-schedule-format"><?= learning_icon($mode === 'in_person' ? 'pin' : 'video') ?><?= e($location) ?></span>
            <span class="learning-schedule-time"><?= learning_icon('clock') ?><?= $multiDay ? 'Daily ' : '' ?><?= e($times) ?></span>
        </div>
    </div>
    <?php
}
