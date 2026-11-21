<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/config.php';

use Haleef\Admin\AdminAuth;
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        Csrf::verify($_POST['_csrf'] ?? null);
        $action = (string) ($_POST['action'] ?? 'upload');

        if ($action === 'archive') {
            $repository->archive((int) ($_POST['asset_id'] ?? 0));
            Flash::set('success', 'Resource archived. Existing campaign links still work.');
            header('Location: /admin/resources');
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

$brands = $repository->brands();
$assets = $repository->all();
$adminPageTitle = 'Resources';
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
                    <?php foreach (['agenda'=>'Agenda','brochure'=>'Brochure','trainer_profile'=>'Trainer profile','course_material'=>'Course material','other'=>'Other public resource'] as $value=>$label): ?><option value="<?= e($value) ?>" <?= ($_POST['asset_type'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label>Resource title<input name="title" value="<?= e((string) ($_POST['title'] ?? '')) ?>" maxlength="255" required></label>
            <label>File<input type="file" name="resource_file" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp" required></label>
            <label>Replace an earlier resource <small class="label-note">Optional; old campaign links remain valid.</small>
                <select name="replace_asset_id">
                    <option value="">No replacement</option>
                    <?php foreach ($assets as $asset): ?><?php if ($asset['is_published']): ?><option value="<?= (int) $asset['id'] ?>"><?= e($asset['brand_name'] . ' — ' . $asset['title']) ?></option><?php endif; ?><?php endforeach; ?>
                </select>
            </label>
            <button type="submit">Upload resource</button>
        </form>
    </article>
    <aside class="admin-panel resource-guidance">
        <span>How links work</span>
        <h2>Branded and durable</h2>
        <p>Global files use <strong>resources.haleefglobal.com</strong>. Learning files use <strong>resources.haleeflearning.com</strong>.</p>
        <p>Archiving removes a file from the active admin list without deleting it, so links already sent through Brevo continue working.</p>
        <p>Allowed files: PDF, JPG, PNG and WebP. Maximum size: <?= (int) \Haleef\Support\Env::int('MAX_PUBLIC_ASSET_MB', 15) ?> MB.</p>
    </aside>
</section>

<section class="admin-panel">
    <div class="panel-heading"><div><span>Resource library</span><h2>Uploaded files</h2></div></div>
    <?php if (!$assets): ?><div class="admin-empty">No public resources have been uploaded yet.</div><?php else: ?>
    <div class="admin-table-wrap"><table><thead><tr><th>Resource</th><th>Brand</th><th>Category</th><th>Public URL</th><th>Size</th><th>Status</th><th></th></tr></thead><tbody>
    <?php foreach ($assets as $asset): ?><tr>
        <td><strong><?= e($asset['title']) ?></strong><small><?= e($asset['original_name']) ?></small><small><?= e(date('d M Y H:i', strtotime($asset['created_at']))) ?></small></td>
        <td><?= e($asset['brand_name']) ?></td>
        <td><?= e(str_replace('_', ' ', $asset['asset_type'])) ?></td>
        <td class="resource-url"><a href="<?= e((string) $asset['public_url']) ?>" target="_blank" rel="noopener">Open file</a><input readonly value="<?= e((string) $asset['public_url']) ?>" aria-label="Public URL"><button type="button" class="copy-button" data-copy="<?= e((string) $asset['public_url']) ?>">Copy</button></td>
        <td><?= e(number_format(((int) $asset['size_bytes']) / 1048576, 2)) ?> MB</td>
        <td><span class="status <?= $asset['is_published'] ? 'status-published' : '' ?>"><?= $asset['is_published'] ? 'Active' : 'Archived' ?></span></td>
        <td><?php if ($asset['is_published']): ?><form method="post" class="inline-form" onsubmit="return confirm('Archive this resource? Existing public links will continue to work.');"><?= Csrf::field() ?><input type="hidden" name="action" value="archive"><input type="hidden" name="asset_id" value="<?= (int) $asset['id'] ?>"><button type="submit" class="text-button">Archive</button></form><?php endif; ?></td>
    </tr><?php endforeach; ?>
    </tbody></table></div>
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
