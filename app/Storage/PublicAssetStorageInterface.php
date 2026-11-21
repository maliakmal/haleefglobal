<?php
declare(strict_types=1);

namespace Haleef\Storage;

interface PublicAssetStorageInterface
{
    public function store(array $file, string $brandCode, string $assetType, string $title): array;

    public function delete(string $brandCode, string $relativePath): void;
}
