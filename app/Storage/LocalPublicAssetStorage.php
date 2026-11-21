<?php
declare(strict_types=1);

namespace Haleef\Storage;

use Haleef\Support\Env;
use RuntimeException;

final class LocalPublicAssetStorage implements PublicAssetStorageInterface
{
    private const ALLOWED_MIME_TYPES = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    private const TYPE_DIRECTORIES = [
        'agenda' => 'agendas',
        'brochure' => 'brochures',
        'trainer_profile' => 'trainer-profiles',
        'course_material' => 'course-materials',
        'other' => 'other',
    ];

    public function store(array $file, string $brandCode, string $assetType, string $title): array
    {
        $this->assertBrandCode($brandCode);
        $directoryName = self::TYPE_DIRECTORIES[$assetType] ?? null;
        if ($directoryName === null) {
            throw new RuntimeException('Please select a valid resource category.');
        }

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            throw new RuntimeException('Please choose a valid PDF or image to upload.');
        }

        $maxBytes = Env::int('MAX_PUBLIC_ASSET_MB', 15) * 1024 * 1024;
        $size = (int) ($file['size'] ?? 0);
        if ($size < 1 || $size > $maxBytes) {
            throw new RuntimeException('The resource must be smaller than the configured upload limit.');
        }

        $temporaryPath = (string) $file['tmp_name'];
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
        $extension = self::ALLOWED_MIME_TYPES[$mime] ?? null;
        if ($extension === null) {
            throw new RuntimeException('Resources must be PDF, JPG, PNG or WebP files.');
        }

        $slug = $this->slug($title !== '' ? $title : pathinfo((string) ($file['name'] ?? 'resource'), PATHINFO_FILENAME));
        $relativePath = sprintf(
            '%s/%s/%s-%s.%s',
            $directoryName,
            date('Y/m'),
            $slug,
            bin2hex(random_bytes(6)),
            $extension
        );
        $destination = $this->brandRoot($brandCode) . '/' . $relativePath;
        $directory = dirname($destination);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to prepare public resource storage.');
        }
        if (!move_uploaded_file($temporaryPath, $destination)) {
            throw new RuntimeException('Unable to store the uploaded resource.');
        }
        chmod($destination, 0644);

        return [
            'original_name' => basename((string) ($file['name'] ?? 'resource.' . $extension)),
            'storage_path' => $relativePath,
            'mime_type' => $mime,
            'size_bytes' => $size,
            'sha256' => hash_file('sha256', $destination),
        ];
    }

    public function delete(string $brandCode, string $relativePath): void
    {
        $this->assertBrandCode($brandCode);
        if ($relativePath === '' || str_contains($relativePath, '..') || str_starts_with($relativePath, '/')) {
            throw new RuntimeException('Invalid public resource path.');
        }

        $path = $this->brandRoot($brandCode) . '/' . $relativePath;
        if (is_file($path)) {
            unlink($path);
        }
    }

    private function brandRoot(string $brandCode): string
    {
        $root = Env::get('PUBLIC_STORAGE_PATH', 'storage/public-resources') ?: 'storage/public-resources';
        if (!str_starts_with($root, '/')) {
            $root = dirname(__DIR__, 2) . '/' . $root;
        }
        return rtrim($root, '/') . '/' . $brandCode;
    }

    private function assertBrandCode(string $brandCode): void
    {
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $brandCode)) {
            throw new RuntimeException('Invalid resource brand.');
        }
    }

    private function slug(string $value): string
    {
        $value = trim($value);
        if (function_exists('transliterator_transliterate')) {
            $value = (string) transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $value);
        } else {
            $value = strtolower($value);
        }
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');
        return substr($value !== '' ? $value : 'resource', 0, 120);
    }
}
