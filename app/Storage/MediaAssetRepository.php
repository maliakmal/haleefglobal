<?php
declare(strict_types=1);

namespace Haleef\Storage;

use PDO;
use RuntimeException;

final class MediaAssetRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function brands(): array
    {
        return $this->pdo->query('SELECT id, code, name, resource_domain FROM brands ORDER BY name')->fetchAll();
    }

    public function all(): array
    {
        return $this->pdo->query(
            'SELECT ma.*, b.code AS brand_code, b.name AS brand_name, au.name AS creator_name
             FROM media_assets ma
             INNER JOIN brands b ON b.id = ma.brand_id
             LEFT JOIN admin_users au ON au.id = ma.created_by
             ORDER BY ma.created_at DESC'
        )->fetchAll();
    }

    public function find(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT ma.*, b.code AS brand_code, b.name AS brand_name, b.resource_domain
             FROM media_assets ma
             INNER JOIN brands b ON b.id = ma.brand_id
             WHERE ma.id = ? LIMIT 1'
        );
        $statement->execute([$id]);
        $asset = $statement->fetch();
        return $asset ?: null;
    }

    public function create(array $data): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO media_assets
             (brand_id, title, asset_type, visibility, storage_disk, storage_path, public_url, original_name, mime_type, size_bytes, sha256, is_published, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $data['brand_id'],
            $data['title'],
            $data['asset_type'],
            'public',
            'public_local',
            $data['storage_path'],
            $data['public_url'],
            $data['original_name'],
            $data['mime_type'],
            $data['size_bytes'],
            $data['sha256'],
            1,
            $data['created_by'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function archive(int $id): void
    {
        $statement = $this->pdo->prepare('UPDATE media_assets SET is_published = 0, archived_at = NOW() WHERE id = ?');
        $statement->execute([$id]);
        if ($statement->rowCount() < 1) {
            throw new RuntimeException('Resource not found or already archived.');
        }
    }

    public function markReplaced(int $oldId, int $newId): void
    {
        $statement = $this->pdo->prepare('UPDATE media_assets SET is_published = 0, archived_at = NOW(), replaced_by_id = ? WHERE id = ?');
        $statement->execute([$newId, $oldId]);
    }
}
