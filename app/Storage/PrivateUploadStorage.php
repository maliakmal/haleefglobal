<?php
declare(strict_types=1);

namespace Haleef\Storage;

use Haleef\Support\Env;
use RuntimeException;

final class PrivateUploadStorage
{
    private const ALLOWED_MIME_TYPES = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function storeEvidence(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            throw new RuntimeException('Please attach a valid payment confirmation file.');
        }

        $maxBytes = Env::int('MAX_PAYMENT_EVIDENCE_MB', 10) * 1024 * 1024;
        $size = (int) ($file['size'] ?? 0);
        if ($size < 1 || $size > $maxBytes) {
            throw new RuntimeException('Payment evidence must be smaller than the configured upload limit.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
        $extension = self::ALLOWED_MIME_TYPES[$mime] ?? null;
        if (!$extension) {
            throw new RuntimeException('Payment evidence must be a PDF, JPG, PNG or WebP file.');
        }

        $relative = 'payment-evidence/' . date('Y/m') . '/' . bin2hex(random_bytes(20)) . '.' . $extension;
        $root = Env::get('PRIVATE_STORAGE_PATH', 'storage/private') ?: 'storage/private';
        if (!str_starts_with($root, '/')) {
            $root = dirname(__DIR__, 2) . '/' . $root;
        }
        $destination = rtrim($root, '/') . '/' . $relative;
        $directory = dirname($destination);
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to prepare private file storage.');
        }
        if (!move_uploaded_file((string) $file['tmp_name'], $destination)) {
            throw new RuntimeException('Unable to store the payment confirmation file.');
        }

        return [
            'original_name' => basename((string) ($file['name'] ?? 'payment-evidence.' . $extension)),
            'storage_path' => $relative,
            'mime_type' => $mime,
            'size_bytes' => $size,
            'sha256' => hash_file('sha256', $destination),
        ];
    }

    public function absolutePath(string $relative): string
    {
        if (str_contains($relative, '..') || str_starts_with($relative, '/')) {
            throw new RuntimeException('Invalid private file path.');
        }
        $root = Env::get('PRIVATE_STORAGE_PATH', 'storage/private') ?: 'storage/private';
        if (!str_starts_with($root, '/')) {
            $root = dirname(__DIR__, 2) . '/' . $root;
        }
        return rtrim($root, '/') . '/' . $relative;
    }
}

