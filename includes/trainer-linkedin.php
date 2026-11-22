<?php
declare(strict_types=1);

use Haleef\Training\TrainerProfiles;

$trainerLinkedIn = TrainerProfiles::linkedin($trainerSocialProfile['linkedin_url'] ?? null);
if ($trainerLinkedIn):
    require_once __DIR__ . '/learning-icons.php';
?>
<a class="learning-trainer-social" href="<?= e($trainerLinkedIn) ?>" target="_blank" rel="noopener noreferrer" aria-label="View <?= e($trainerSocialProfile['name']) ?> on LinkedIn (opens in a new tab)"><?= learning_icon('linkedin') ?><span>LinkedIn profile</span><?= learning_icon('external') ?></a>
<?php endif; ?>
