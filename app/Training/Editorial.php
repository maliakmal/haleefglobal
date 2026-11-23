<?php
declare(strict_types=1);

namespace Haleef\Training;

use Haleef\Storage\LocalPublicAssetStorage;
use Haleef\Storage\MediaAssetRepository;
use PDO;
use RuntimeException;

final class Editorial
{
    public const FEATURES = [
        'any-device' => ['devices', 'Any device', 'Join with your computer, tablet or phone.'],
        'interactive' => ['chat', 'Interactive sessions', 'Take part in discussions and ask questions live.'],
        'case-studies' => ['document', 'Practical case studies', 'Work through examples drawn from real scenarios.'],
        'materials' => ['document', 'Course materials', 'Receive supporting materials for the programme.'],
        'certificate' => ['award', 'Certificate of attendance', 'Receive a certificate of attendance.'],
    ];

    public static function slug(PDO $pdo, string $kind, string $input, string $fallback, int $id, int $brandId): string
    {
        $table = ['trainings'=>'trainings','editions'=>'training_editions','trainers'=>'trainers'][$kind];
        $auto = $input === '';
        $ascii = function_exists('iconv') ? @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $fallback) : $fallback;
        $base = $auto ? rtrim(substr(trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($ascii ?: $fallback)), '-'), 0, 175), '-') : $input;
        $base = $base !== '' ? $base : rtrim($kind, 's');
        if (strlen($base) > 190 || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $base)) {
            throw new RuntimeException('Use lowercase letters, numbers and single hyphens in the URL slug.');
        }
        $query = $pdo->prepare("SELECT id FROM $table WHERE slug = ? AND id <> ?" . ($kind === 'trainings' ? ' AND brand_id = ?' : ''));
        for ($i = 1; ; $i++) {
            $slug = $i === 1 ? $base : "$base-$i";
            $query->execute($kind === 'trainings' ? [$slug, $id, $brandId] : [$slug, $id]);
            if (!$query->fetchColumn()) return $slug;
            if (!$auto) throw new RuntimeException('That URL slug is already in use.');
        }
    }

    public static function upload(PDO $pdo, array $file, array $brand, int $adminId, string $title, string $type, array &$storedPaths): ?array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
        if (($file['error'] ?? null) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            throw new RuntimeException('Upload failed. Check the file size and try again.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $allowed = $type === 'agenda' ? ['application/pdf'] : ['image/jpeg','image/png','image/webp'];
        if (!in_array($mime, $allowed, true)) throw new RuntimeException($type === 'agenda' ? 'An agenda must be a PDF.' : 'Choose a JPG, PNG or WebP image.');
        $stored = (new LocalPublicAssetStorage())->store($file, $brand['code'], $type, $title);
        $storedPaths[] = $stored['storage_path'];
        $url = rtrim($brand['resource_domain'], '/') . '/' . implode('/', array_map('rawurlencode', explode('/', $stored['storage_path'])));
        $id = (new MediaAssetRepository($pdo))->create($stored + [
            'brand_id'=>$brand['id'], 'title'=>$title, 'asset_type'=>$type, 'public_url'=>$url, 'created_by'=>$adminId,
        ]);
        return ['id'=>$id,'url'=>$url];
    }

    public static function date(array $edition, string $field): \DateTimeImmutable
    {
        try { $zone = new \DateTimeZone($edition['timezone']); }
        catch (\Exception) { $zone = new \DateTimeZone(date_default_timezone_get()); }
        return new \DateTimeImmutable($edition[$field], $zone);
    }

    public static function ended(array $edition): bool
    {
        return self::date($edition, 'ends_at')->getTimestamp() < time();
    }

    public static function dateRange(array $edition): string
    {
        $start = self::date($edition,'starts_at')->format('d M Y');
        $end = self::date($edition,'ends_at')->format('d M Y');
        return $start === $end ? $start : $start . ' – ' . $end;
    }

    public static function dailyTimeRange(array $edition): string
    {
        return self::date($edition,'starts_at')->format('H:i') . ' – '
            . self::date($edition,'ends_at')->format('H:i') . ' ' . self::timezoneLabel($edition);
    }

    public static function timezoneLabel(array $edition): string
    {
        $label = static function (\DateTimeImmutable $date): string {
            $zone = $date->getTimezone()->getName();
            if ($date->getOffset() === 0 && in_array($zone, ['UTC','Etc/UTC','GMT','Etc/GMT','Etc/GMT+0','Etc/GMT-0','Etc/GMT0'], true)) return 'UTC';
            if ($zone === 'Asia/Dubai' && $date->getOffset() === 14400) return 'GST';
            $abbreviation = $date->format('T');
            if (preg_match('/^[A-Z]{2,6}$/D', $abbreviation)) return $abbreviation;
            return $date->getOffset() === 0 ? 'UTC' : 'UTC' . $date->format('P');
        };
        $start = $label(self::date($edition, 'starts_at'));
        $end = $label(self::date($edition, 'ends_at'));
        return $start === $end ? $start : $start . ' / ' . $end;
    }
}
