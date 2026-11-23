<?php
declare(strict_types=1);

namespace Haleef\Storage;

use PDO;
use RuntimeException;

final class MediaAssetRepository
{
    public const PAGE_SIZE = 50;
    public const CATEGORIES = ['agenda'=>'Agenda','brochure'=>'Brochure','trainer_profile'=>'Trainer profile','course_material'=>'Course material','other'=>'Other public resource'];
    public const FILE_TYPES = ['application/pdf'=>'PDF','image/jpeg'=>'JPG','image/png'=>'PNG','image/webp'=>'WebP'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function brands(): array
    {
        return $this->pdo->query('SELECT id, code, name, resource_domain FROM brands ORDER BY name')->fetchAll();
    }

    public function replacements(): array
    {
        return $this->pdo->query(
            'SELECT ma.id, ma.title, b.name AS brand_name
             FROM media_assets ma INNER JOIN brands b ON b.id = ma.brand_id
             WHERE ma.is_published = 1 ORDER BY ma.created_at DESC, ma.id DESC'
        )->fetchAll();
    }

    private function listingCriteria(string $search, string $category, string $type): array
    {
        $conditions = [];
        $params = [];
        if ($search !== '') {
            $conditions[] = "ma.title LIKE :search ESCAPE '!'";
            $params['search'] = '%' . strtr($search, ['!'=>'!!','%'=>'!%','_'=>'!_']) . '%';
        }
        if ($category !== '') {
            $conditions[] = 'ma.asset_type = :category';
            $params['category'] = $category;
        }
        if ($type !== '') {
            $conditions[] = 'ma.mime_type = :type';
            $params['type'] = $type;
        }
        $from = ' FROM media_assets ma INNER JOIN brands b ON b.id = ma.brand_id'
            . ($conditions ? ' WHERE ' . implode(' AND ', $conditions) : '');
        return [$from,$params];
    }

    public function export(string $search, string $category, string $type): \PDOStatement
    {
        [$from,$params] = $this->listingCriteria($search,$category,$type);
        $q = $this->pdo->prepare("SELECT ma.title,b.name,ma.asset_type,ma.mime_type,ma.public_url,ma.size_bytes,
            IF(ma.is_published,'Published','Archived'),ma.created_at".$from.' ORDER BY ma.created_at DESC,ma.id DESC');
        $q->execute($params);
        return $q;
    }

    public function listing(string $search, string $category, string $type, int $page): array
    {
        [$from,$params] = $this->listingCriteria($search,$category,$type);
        $count = $this->pdo->prepare('SELECT COUNT(*)' . $from);
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $last = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = min(max(1, $page), $last);
        $statement = $this->pdo->prepare(
            'SELECT ma.*, b.name AS brand_name' . $from . ' ORDER BY ma.created_at DESC, ma.id DESC LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $name => $value) $statement->bindValue(':' . $name, $value, PDO::PARAM_STR);
        $statement->bindValue(':limit', self::PAGE_SIZE, PDO::PARAM_INT);
        $statement->bindValue(':offset', ($page - 1) * self::PAGE_SIZE, PDO::PARAM_INT);
        $statement->execute();
        return ['rows'=>$statement->fetchAll(), 'total'=>$total, 'page'=>$page, 'last'=>$last];
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
