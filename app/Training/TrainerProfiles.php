<?php
declare(strict_types=1);

namespace Haleef\Training;

use PDO;
use RuntimeException;

final class TrainerProfiles
{
    public static function linkedin(?string $url): ?string
    {
        $url = trim((string)$url);
        if (strlen($url) > 500 || !filter_var($url, FILTER_VALIDATE_URL)) return null;
        $parts = parse_url($url);
        if (!$parts || strtolower($parts['scheme'] ?? '') !== 'https'
            || !preg_match('/^(?:[a-z]{2,3}\.)?linkedin\.com$/iD', $parts['host'] ?? '')
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])
            || !preg_match('~^/(?:in|pub)/[^/]+(?:/[^/]+)*/?$~D', $parts['path'] ?? '')) return null;
        return 'https://' . strtolower($parts['host']) . $parts['path'];
    }

    public static function assignmentVersion(array $ids): string
    {
        sort($ids, SORT_NUMERIC);
        return hash('sha256', implode(',', $ids));
    }

    /** Call inside the edition save transaction, after obtaining its row lock. */
    public static function updateAssignments(PDO $pdo, int $editionId, array $selected, string $version): void
    {
        $q = $pdo->prepare('SELECT trainer_id,display_order FROM training_trainers WHERE edition_id=? FOR UPDATE');
        $q->execute([$editionId]);
        $existing = $q->fetchAll(PDO::FETCH_KEY_PAIR);
        if (!hash_equals(self::assignmentVersion(array_keys($existing)), $version)) {
            throw new RuntimeException('Trainer assignments changed while you were editing. Reopen the edition to review the latest list before saving.');
        }
        $order = $existing ? max($existing) + 1 : 0;
        $delete = $pdo->prepare('DELETE FROM training_trainers WHERE edition_id=? AND trainer_id=?');
        foreach (array_diff(array_keys($existing), $selected) as $id) $delete->execute([$editionId,$id]);
        $insert = $pdo->prepare('INSERT INTO training_trainers (edition_id,trainer_id,display_order) VALUES (?,?,?)');
        foreach (array_diff($selected,array_keys($existing)) as $id) $insert->execute([$editionId,$id,$order++]);
    }
}
