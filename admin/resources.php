<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/config.php';

use Haleef\Admin\{AdminAuth, Lists};
use Haleef\Storage\LocalPublicAssetStorage;
use Haleef\Storage\MediaAssetRepository;
use Haleef\Support\Csrf;
use Haleef\Support\Database;
use Haleef\Support\Flash;

$adminUser = AdminAuth::requireUser();
$pdo = Database::connection();
$repository = new MediaAssetRepository($pdo);
$storage = new LocalPublicAssetStorage();
$error = null;
$queryText = static fn(string $key): string => is_string($_GET[$key] ?? null) ? trim($_GET[$key]) : '';
$search = mb_substr($queryText('q'),0,255);
$category = array_key_exists($queryText('category'),MediaAssetRepository::CATEGORIES) ? $queryText('category') : '';
$fileType = array_key_exists($queryText('type'),MediaAssetRepository::FILE_TYPES) ? $queryText('type') : '';
$requestedPage = filter_var($queryText('page'), FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]) ?: 1;
$listUrl = static function (int $page) use ($search,$category,$fileType): string {
    $query = array_filter(['q'=>$search,'category'=>$category,'type'=>$fileType,'page'=>$page > 1 ? $page : ''], static fn($value) => $value !== '');
    return '/admin/resources' . ($query ? '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '') . '#resources-list';
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        Csrf::verify($_POST['_csrf'] ?? null);
        $action = (string) ($_POST['action'] ?? 'upload');

        if ($action === 'archive') {
            $repository->archive((int) ($_POST['asset_id'] ?? 0));
            Flash::set('success', 'Resource archived. Existing campaign links still work.');
            header('Location: ' . $listUrl($requestedPage), true, 303);
            exit;
        }

        if ($action !== 'upload') {
            throw new RuntimeException('Invalid resource action.');
        }

        $title = trim((string) ($_POST['title'] ?? ''));
        $brandId = (int) ($_POST['brand_id'] ?? 0);
        $assetType = (string) ($_POST['asset_type'] ?? '');
        $replaceId = (int) ($_POST['replace_asset_id'] ?? 0);
        if ($title === '') {
            throw new RuntimeException('Please enter a resource title.');
        }

        $brands = $repository->brands();
        $brand = null;
        foreach ($brands as $candidate) {
            if ((int) $candidate['id'] === $brandId) {
                $brand = $candidate;
                break;
            }
        }
        if ($brand === null || trim((string) $brand['resource_domain']) === '') {
            throw new RuntimeException('Please select a brand with a configured resource domain.');
        }

        $replacedAsset = null;
        if ($replaceId > 0) {
            $replacedAsset = $repository->find($replaceId);
            if ($replacedAsset === null || !(bool) $replacedAsset['is_published'] || (int) $replacedAsset['brand_id'] !== $brandId) {
                throw new RuntimeException('A replacement must use the same brand as the original resource.');
            }
        }

        $stored = $storage->store($_FILES['resource_file'] ?? [], (string) $brand['code'], $assetType, $title);
        $publicUrl = rtrim((string) $brand['resource_domain'], '/') . '/' . implode('/', array_map('rawurlencode', explode('/', $stored['storage_path'])));

        try {
            $pdo->beginTransaction();
            $assetId = $repository->create([
                'brand_id' => $brandId,
                'title' => $title,
                'asset_type' => $assetType,
                'storage_path' => $stored['storage_path'],
                'public_url' => $publicUrl,
                'original_name' => $stored['original_name'],
                'mime_type' => $stored['mime_type'],
                'size_bytes' => $stored['size_bytes'],
                'sha256' => $stored['sha256'],
                'created_by' => (int) $adminUser['id'],
            ]);
            if ($replacedAsset !== null) {
                $repository->markReplaced((int) $replacedAsset['id'], $assetId);
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $storage->delete((string) $brand['code'], $stored['storage_path']);
            throw $exception;
        }

        Flash::set('success', $replacedAsset === null ? 'Resource uploaded and ready to share.' : 'Replacement uploaded. The original link remains available for earlier campaigns.');
        header('Location: /admin/resources');
        exit;
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

if (Lists::exporting()) {
    Lists::csv('resources',['Title','Brand','Category','MIME type','Public URL','Bytes','Status','Created'],
        Lists::rows($repository->export($search,$category,$fileType)));
}
$brands = $repository->brands();
$replacements = $repository->replacements();
$listing = $repository->listing($search,$category,$fileType,$requestedPage);
$assets = $listing['rows'];
$page = $listing['page'];
$total = $listing['total'];
$last = $listing['last'];
$adminPageTitle = 'Resources';
$adminStyles = '/assets/css/admin-resources.css';
require __DIR__ . '/_header.php';
?>
<?php if ($error): ?><div class="admin-alert admin-alert-error"><?= e($error) ?></div><?php endif; ?>
<section class="admin-two-column resource-layout">
    <article class="admin-panel admin-form-panel">
        <span>Campaign assets</span>
        <h2>Upload a public resource</h2>
        <p class="admin-help">Use this for agendas, brochures and approved public images. Payment evidence and attendee files remain private.</p>
        <form method="post" enctype="multipart/form-data">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="upload">
            <label>Brand
                <select name="brand_id" required>
                    <option value="">Select a brand</option>
                    <?php foreach ($brands as $brand): ?><option value="<?= (int) $brand['id'] ?>" <?= (int) ($_POST['brand_id'] ?? 0) === (int) $brand['id'] ? 'selected' : '' ?>><?= e($brand['name']) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label>Category
                <select name="asset_type" required>
                    <?php foreach (MediaAssetRepository::CATEGORIES as $value=>$label): ?><option value="<?= e($value) ?>" <?= ($_POST['asset_type'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label>Resource title<input name="title" value="<?= e((string) ($_POST['title'] ?? '')) ?>" maxlength="255" required></label>
            <label>File<input type="file" name="resource_file" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp" required></label>
            <label>Replace an earlier resource <small class="label-note">Optional; old campaign links remain valid.</small>
                <select name="replace_asset_id">
                    <option value="">No replacement</option>
                    <?php foreach ($replacements as $asset): ?><option value="<?= (int) $asset['id'] ?>" <?= (int)($_POST['replace_asset_id'] ?? 0) === (int)$asset['id'] ? 'selected' : '' ?>><?= e($asset['brand_name'] . ' — ' . $asset['title']) ?></option><?php endforeach; ?>
                </select>
            </label>
            <button type="submit">Upload resource</button>
        </form>
    </article>
    <aside class="admin-panel resource-guidance">
        <span>How links work</span>
        <h2>Branded and durable</h2>
        <p>Global files use <strong>resources.haleefglobal.com</strong>. Learning files use <strong>resources.haleeflearning.com</strong>.</p>
        <p>Archived files remain listed as archived; links already shared continue working.</p>
        <p>Allowed files: PDF, JPG, PNG and WebP. Maximum size: <?= (int) \Haleef\Support\Env::int('MAX_PUBLIC_ASSET_MB', 15) ?> MB.</p>
    </aside>
</section>

<section class="admin-panel" id="resources-list">
    <div class="panel-heading"><div><span>Resource library</span><h2>Uploaded files</h2></div><a href="<?= e('/admin/resources?'.http_build_query(['format'=>'csv','q'=>$search,'category'=>$category,'type'=>$fileType])) ?>">Download CSV</a></div>
    <form class="admin-form-panel resource-filters" action="/admin/resources#resources-list" method="get" role="search" aria-label="Search uploaded files">
        <label>Title<input name="q" type="search" value="<?= e($search) ?>" maxlength="255" placeholder="Search by full or partial title"></label>
        <label>Category<select name="category"><option value="">All categories</option>
            <?php foreach (MediaAssetRepository::CATEGORIES as $value=>$label): ?><option value="<?= e($value) ?>" <?= $category === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
        </select></label>
        <label>File type<select name="type"><option value="">All file types</option>
            <?php foreach (MediaAssetRepository::FILE_TYPES as $value=>$label): ?><option value="<?= e($value) ?>" <?= $fileType === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
        </select></label>
        <div class="resource-filter-actions"><button type="submit">Filter</button><?php if ($search !== '' || $category !== '' || $fileType !== ''): ?><a href="/admin/resources#resources-list">Clear filters</a><?php endif; ?></div>
    </form>
    <?php if (!$assets): ?><div class="admin-empty"><?= $search !== '' || $category !== '' || $fileType !== '' ? 'No files match these filters.' : 'No public resources have been uploaded yet.' ?></div><?php else: ?>
    <p class="resource-count">Showing <?= ($page - 1) * MediaAssetRepository::PAGE_SIZE + 1 ?>–<?= ($page - 1) * MediaAssetRepository::PAGE_SIZE + count($assets) ?> of <?= $total ?> files</p>
    <div class="admin-table-wrap"><table><thead><tr><th>Resource</th><th>Brand</th><th>Category</th><th>Public URL</th><th>Size</th><th>Status</th><th></th></tr></thead><tbody>
    <?php foreach ($assets as $asset): $mime = $asset['mime_type']; ?><tr data-resource-row>
        <td><div class="resource-file"><span class="resource-file-icon <?= $mime === 'application/pdf' ? 'resource-file-pdf' : '' ?>">
            <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                <?php if (str_starts_with($mime,'image/')): ?><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="9" r="1.5"/><path d="m3 17 4-4 3 3 4-4 7 7"/><?php else: ?><path d="M5 3h9l5 5v13H5zM14 3v5h5M8 12h8m-8 4h8"/><?php endif; ?>
            </svg><?= e(MediaAssetRepository::FILE_TYPES[$mime] ?? 'File') ?></span><div><strong><?= e($asset['title']) ?></strong><small><?= e($asset['original_name']) ?></small><small><?= e(date('d M Y H:i', strtotime($asset['created_at']))) ?></small></div></div></td>
        <td><?= e($asset['brand_name']) ?></td>
        <td><?= e(MediaAssetRepository::CATEGORIES[$asset['asset_type']] ?? $asset['asset_type']) ?></td>
        <td class="resource-url"><a href="<?= e((string) $asset['public_url']) ?>" target="_blank" rel="noopener">Open file</a><input readonly value="<?= e((string) $asset['public_url']) ?>" aria-label="Public URL"><button type="button" class="copy-button" data-copy="<?= e((string) $asset['public_url']) ?>">Copy</button></td>
        <td><?= e(number_format(((int) $asset['size_bytes']) / 1048576, 2)) ?> MB</td>
        <td><span class="status <?= $asset['is_published'] ? 'status-published' : '' ?>"><?= $asset['is_published'] ? 'Active' : 'Archived' ?></span></td>
        <td><?php if ($asset['is_published']): ?><form method="post" class="inline-form" onsubmit="return confirm('Archive this resource? Existing public links will continue to work.');"><?= Csrf::field() ?><input type="hidden" name="action" value="archive"><input type="hidden" name="asset_id" value="<?= (int) $asset['id'] ?>"><button type="submit" class="text-button">Archive</button></form><?php endif; ?></td>
    </tr><?php endforeach; ?>
    </tbody></table></div>
    <?php if ($last > 1): ?><nav class="resource-pagination" aria-label="Resources pagination">
        <?php if ($page > 1): ?><a href="<?= e($listUrl($page - 1)) ?>" rel="prev">Previous</a><?php endif; ?>
        <span aria-current="page">Page <?= $page ?> of <?= $last ?></span>
        <?php if ($page < $last): ?><a href="<?= e($listUrl($page + 1)) ?>" rel="next">Next</a><?php endif; ?>
    </nav><?php endif; ?>
    <?php endif; ?>
</section>
<script>
document.querySelectorAll('[data-copy]').forEach(function (button) {
    button.addEventListener('click', function () {
        navigator.clipboard.writeText(button.dataset.copy).then(function () {
            var original = button.textContent;
            button.textContent = 'Copied';
            setTimeout(function () { button.textContent = original; }, 1400);
        });
    });
});
</script>
<?php require __DIR__ . '/_footer.php'; ?>
