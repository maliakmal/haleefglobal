<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/config.php';

use Haleef\Admin\AdminAuth;
use Haleef\Storage\LocalPublicAssetStorage;
use Haleef\Support\{Csrf, Database, Flash};
use Haleef\Training\{Editorial, TrainerProfiles};

$adminUser = AdminAuth::requireUser();
$kind = $editorKind ?? '';
$table = ['trainings'=>'trainings','editions'=>'training_editions','trainers'=>'trainers'][$kind] ?? null;
if (!$table) { http_response_code(404); exit; }
$base = "/admin/$kind";
$id = $editorId ?? 0;
$pdo = Database::connection();
$brand = $pdo->query("SELECT * FROM brands WHERE code='haleef-learning'")->fetch();
$brandId = (int) $brand['id'];
$scope = match ($kind) {
    'trainings' => " AND brand_id=$brandId",
    'editions' => " AND training_id IN (SELECT id FROM trainings WHERE brand_id=$brandId)",
    default => '',
};
$row = [];
if ($id) {
    $q = $pdo->prepare("SELECT * FROM $table WHERE id=?$scope");
    $q->execute([$id]);
    $row = $q->fetch();
    if (!$row) { http_response_code(404); require dirname(__DIR__) . '/not-found.php'; exit; }
}
$editing = $id > 0 || ($editorNew ?? false);
$programmeOptions = $kind === 'editions' ? $pdo->query("SELECT id,title FROM trainings WHERE brand_id=$brandId ORDER BY title")->fetchAll(PDO::FETCH_KEY_PAIR) : [];
$trainerOptions = $kind === 'editions' ? $pdo->query('SELECT id,name FROM trainers ORDER BY name')->fetchAll(PDO::FETCH_KEY_PAIR) : [];
$selected = [];
if ($id && $kind === 'editions') {
    $q = $pdo->prepare('SELECT trainer_id FROM training_trainers WHERE edition_id=?');
    $q->execute([$id]);
    $selected = array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
}
$assignmentVersion = TrainerProfiles::assignmentVersion($selected);
$currencies = ['AED','USD','EUR','GBP','SAR','QAR','OMR','BHD','KWD','CHF','CAD','AUD','INR','SGD','PKR','ZAR','JPY','HKD','NZD','EGP','JOD'];
if (!empty($row['currency'])) $currencies[] = $row['currency'];
$currencies = array_combine(array_unique($currencies), array_unique($currencies));
$zones = DateTimeZone::listIdentifiers();
if (!empty($row['timezone'])) $zones[] = $row['timezone'];
$zones = array_combine(array_unique($zones), array_unique($zones));
$features = json_decode((string) ($row['features'] ?? ''), true) ?: [];
$error = '';
if ($editing && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $paths = [];
    $p = static fn(string $key): string => trim(is_string($_POST[$key] ?? null) ? $_POST[$key] : '');
    try {
        Csrf::verify($_POST['_csrf'] ?? null);
        $choose = static function (string $key, array $values) use ($p): string {
            $value = $p($key);
            if (!in_array($value, $values, true)) throw new RuntimeException("Choose a valid " . str_replace('_', ' ', $key) . '.');
            return $value;
        };
        $pdo->beginTransaction();
        if ($kind === 'trainings') {
            $title = $p('title');
            $data = ['brand_id'=>$brandId,'title'=>$title,'status'=>$choose('status',['draft','published','archived']),'cpd_available'=>(int)isset($_POST['cpd_available'])];
            foreach (['short_description','description','audience','learning_outcomes','key_notes'] as $field) $data[$field] = $p($field);
        } elseif ($kind === 'trainers') {
            $title = $p('name');
            $data = ['name'=>$title,'headline'=>$p('headline'),'biography'=>$p('biography'),'specialties'=>$p('specialties'),'is_published'=>(int)isset($_POST['is_published'])];
            if (array_key_exists('linkedin_url', $_POST)) {
                $linkedin = $p('linkedin_url');
                if ($linkedin !== (string)($row['linkedin_url'] ?? '')) {
                    $profileUrl = TrainerProfiles::linkedin($linkedin);
                    if ($linkedin !== '' && !$profileUrl) throw new RuntimeException('Enter an HTTPS LinkedIn profile URL, for example https://www.linkedin.com/in/your-name.');
                    $data['linkedin_url'] = $profileUrl;
                }
            }
        } else {
            $trainingId = (int) $p('training_id');
            if (!isset($programmeOptions[$trainingId])) throw new RuntimeException('Choose a programme.');
            if ($row && (int) $row['training_id'] !== $trainingId) throw new RuntimeException('An existing edition cannot be moved to another programme.');
            $zone = $choose('timezone', array_values($zones));
            $tz = new DateTimeZone($zone);
            $dates = [];
            foreach (['starts_at','ends_at'] as $field) {
                $d = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $p($field), $tz);
                if (!$d || $d->format('Y-m-d\TH:i') !== $p($field)) throw new RuntimeException('Enter valid local dates and times for the selected timezone.');
                $dates[$field] = $d;
            }
            if ($dates['ends_at'] <= $dates['starts_at']) throw new RuntimeException('End time must follow start time.');
            if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $p('price'))) throw new RuntimeException('Enter a valid price.');
            if ($p('capacity') !== '' && !filter_var($p('capacity'), FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>1000000]])) throw new RuntimeException('Enter a valid capacity.');
            if ($p('cpd_points') !== '' && !preg_match('/^\d{1,6}(?:\.\d{1,2})?$/D', $p('cpd_points'))) throw new RuntimeException('Enter valid CPD points.');
            $zoom = $p('zoom_url');
            if ($zoom !== '') {
                $host = strtolower((string) parse_url($zoom, PHP_URL_HOST));
                if (strlen($zoom) > 1000 || !filter_var($zoom, FILTER_VALIDATE_URL) || parse_url($zoom, PHP_URL_SCHEME) !== 'https' || !preg_match('/(?:^|\.)(?:zoom\.us|zoomgov\.com)$/D', $host) || preg_match('~/(?:s|start)(?:/|$)~i', (string) parse_url($zoom, PHP_URL_PATH))) throw new RuntimeException('Use an HTTPS Zoom attendee link, not a host/start link.');
            }
            $title = $programmeOptions[$trainingId] . ' ' . $dates['starts_at']->format('Y-m-d');
            $features = array_values(array_intersect(array_keys(Editorial::FEATURES), (array) ($_POST['features'] ?? [])));
            if (isset($_POST['trainer_ids']) || $p('trainer_assignment_version') !== '') {
                if ($p('trainer_assignment_version') === '') throw new RuntimeException('The trainer selection was incomplete. Reopen the edition and try again.');
                $postedTrainers = $_POST['trainer_ids'] ?? [];
                if (!is_array($postedTrainers)) throw new RuntimeException('Choose trainers from the list.');
                $selected = [];
                foreach ($postedTrainers as $trainerId) {
                    if (!is_string($trainerId) || !ctype_digit($trainerId) || !isset($trainerOptions[(int)$trainerId])) {
                        throw new RuntimeException('One of the selected trainers is no longer available. Reopen the edition and try again.');
                    }
                    $selected[(int)$trainerId] = (int)$trainerId;
                }
            }
            $data = ['training_id'=>$trainingId,'timezone'=>$zone,'starts_at'=>$dates['starts_at']->format('Y-m-d H:i:s'),'ends_at'=>$dates['ends_at']->format('Y-m-d H:i:s'),
                'delivery_mode'=>$choose('delivery_mode',['online','in_person','hybrid']),'publication_status'=>$choose('publication_status',['draft','published']),
                'registration_status'=>$choose('registration_status',['closed','open','waitlist']),'currency'=>$choose('currency',array_values($currencies)),
                'venue_name'=>$p('venue_name'),'venue_address'=>$p('venue_address'),'price'=>$p('price'),'capacity'=>$p('capacity') ?: null,
                'cpd_points'=>$p('cpd_points') !== '' ? $p('cpd_points') : null,'zoom_url'=>$zoom ?: null,'features'=>json_encode($features, JSON_THROW_ON_ERROR)];
        }
        if ($title === '') throw new RuntimeException('Enter a title or name.');
        $data['slug'] = Editorial::slug($pdo, $kind, $p('slug'), $title, $id, $brandId);
        $image = Editorial::upload($pdo, $_FILES['header_image'] ?? [], $brand, (int) $adminUser['id'], "$title — image", $kind === 'trainers' ? 'trainer_profile' : 'other', $paths);
        if ($image || isset($_POST['remove_image'])) $data['image_path'] = $image['url'] ?? null;
        $agenda = $kind === 'editions' ? Editorial::upload($pdo, $_FILES['agenda'] ?? [], $brand, (int) $adminUser['id'], "$title — agenda", 'agenda', $paths) : null;
        if ($agenda || ($kind === 'editions' && isset($_POST['remove_agenda']))) $data['agenda_asset_id'] = $agenda['id'] ?? null;
        $keys = array_keys($data);
        if ($id) {
            $q = $pdo->prepare("UPDATE $table SET " . implode(',', array_map(static fn($key) => "$key=?", $keys)) . " WHERE id=?$scope");
            $q->execute([...array_values($data), $id]);
            $saved = $id;
        } else {
            $q = $pdo->prepare("INSERT INTO $table (" . implode(',', $keys) . ') VALUES (' . implode(',', array_fill(0,count($keys),'?')) . ')');
            $q->execute(array_values($data));
            $saved = (int) $pdo->lastInsertId();
        }
        foreach ([$image, $agenda] as $asset) {
            if (!$asset || $kind === 'trainers') continue;
            $pdo->prepare('UPDATE media_assets SET training_id=?, edition_id=? WHERE id=?')
                ->execute([$kind === 'trainings' ? $saved : $trainingId, $kind === 'editions' ? $saved : null, $asset['id']]);
        }
        if ($kind === 'editions' && $p('trainer_assignment_version') !== '') {
            TrainerProfiles::updateAssignments($pdo,$saved,$selected,$p('trainer_assignment_version'));
        }
        $pdo->commit();
        Flash::set('success', 'Saved. Preview the page or continue editing.');
        header("Location: $base/$saved/edit", true, 303);
        exit;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        foreach ($paths as $path) (new LocalPublicAssetStorage())->delete($brand['code'], $path);
        if ($ex instanceof PDOException) error_log($ex->getMessage());
        $error = $ex instanceof PDOException ? 'Could not save. Check field lengths and that the slug is unique.' : $ex->getMessage();
    }
}
$labels = ['trainings'=>'Programme','editions'=>'Edition','trainers'=>'Trainer'];
$label = $labels[$kind];
$adminPageTitle = $editing ? ($id ? "Edit $label" : "New $label") : ucfirst($kind);
require __DIR__ . '/_header.php';
$preview = static fn(int $key): string => "/admin/preview/$kind/$key";
if (!$editing):
    $rows = $pdo->query("SELECT * FROM $table WHERE 1=1$scope ORDER BY id DESC")->fetchAll();
?>
<section class="admin-panel"><div class="panel-heading"><h2><?= e($adminPageTitle) ?></h2><a href="<?= e($base) ?>/new">Create <?= e(strtolower($label)) ?></a></div>
<div class="admin-table-wrap"><table><thead><tr><th>Name / date</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($rows as $item): ?><tr><td><strong><?= e($item['title'] ?? $item['name'] ?? ($programmeOptions[$item['training_id']] . ' · ' . $item['starts_at'])) ?></strong><small><?= e($item['slug']) ?></small></td><td><?= e($item['status'] ?? (isset($item['publication_status']) ? $item['publication_status'] . ' / ' . $item['registration_status'] : ($item['is_published'] ? 'published' : 'draft'))) ?></td><td><a href="<?= e($base . '/' . $item['id'] . '/edit') ?>">Edit</a> · <a href="<?= e($preview((int)$item['id'])) ?>" target="_blank" rel="noopener">Preview</a></td></tr><?php endforeach; ?>
</tbody></table></div><?php if (!$rows): ?><p>No records yet.</p><?php endif; ?></section>
<?php else:
$value = static fn(string $key, string $default = ''): string => (string) ($_POST[$key] ?? $row[$key] ?? $default);
$input = static function (string $key, string $label, string $default = '', string $type = 'text', string $attr = '') use ($value): void {
    $val = $value($key,$default);
    if ($type === 'datetime-local') $val = str_replace(' ','T',substr($val,0,16));
    ?><label><?= e($label) ?><input name="<?= e($key) ?>" type="<?= e($type) ?>" value="<?= e($val) ?>" <?= $attr ?>></label><?php
};
$area = static function (string $key, string $label) use ($value): void { ?><label><?= e($label) ?><textarea name="<?= e($key) ?>" rows="5"><?= e($value($key)) ?></textarea></label><?php };
$select = static function (string $key, string $label, array $options, string $default) use ($value): void { ?><label><?= e($label) ?><select required name="<?= e($key) ?>"><?php foreach ($options as $k=>$v): ?><option value="<?= e((string)$k) ?>" <?= $value($key,$default)===(string)$k?'selected':'' ?>><?= e((string)$v) ?></option><?php endforeach; ?></select></label><?php };
$check = static function (string $key, string $label) use ($row): void { ?><label class="check-label"><input type="checkbox" name="<?= e($key) ?>" <?= (($_SERVER['REQUEST_METHOD']==='POST' ? isset($_POST[$key]) : !empty($row[$key])))?'checked':'' ?>><?= e($label) ?></label><?php };
?>
<section class="admin-panel admin-form-panel"><div class="panel-heading"><a href="<?= e($base) ?>">Back to list</a><?php if ($id): ?><div><?php if ($kind === 'editions'): ?><a href="/admin/editions/<?= $id ?>/faqs">Manage edition FAQs</a> · <?php endif; ?><a href="<?= e($preview($id)) ?>" target="_blank" rel="noopener">Preview saved version</a></div><?php endif; ?></div>
<?php if ($error): ?><div class="admin-alert admin-alert-error"><?= e($error) ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data"><?= Csrf::field() ?>
<?php if ($kind === 'trainings'):
    $input('title','Title','','text','required maxlength="255"');
    $area('short_description','Short description (plain text)');
    foreach (['description'=>'Overview','audience'=>'Who should attend','learning_outcomes'=>'Learning outcomes','key_notes'=>'Key notes'] as $key=>$name) $area($key,$name . ' (Markdown)');
    $select('status','Publication',['draft'=>'Draft','published'=>'Published','archived'=>'Archived'],'draft');
    $check('cpd_available','CPD available');
elseif ($kind === 'trainers'):
    $input('name','Name','','text','required maxlength="190"'); $input('headline','Headline','','text','maxlength="255"');
    $input('specialties','Specialties (comma separated)','','text','maxlength="500"');
    $input('linkedin_url','LinkedIn profile URL (optional)','','url','maxlength="500" placeholder="https://www.linkedin.com/in/your-name"');
    $area('biography','Biography (Markdown)'); $check('is_published','Publish public profile');
else:
    if ($id): ?><input type="hidden" name="training_id" value="<?= (int)$row['training_id'] ?>"><p>Programme: <?= e($programmeOptions[$row['training_id']]) ?></p><?php
    else: $select('training_id','Programme',[''=>'Choose a programme'] + $programmeOptions,''); endif;
    ?><div class="admin-form-row"><?php $input('starts_at','Starts','','datetime-local','required'); $input('ends_at','Ends','','datetime-local','required'); ?></div><?php
    $select('timezone','Timezone',$zones,'Asia/Dubai');
    $select('delivery_mode','Format',['online'=>'Online','in_person'=>'In person','hybrid'=>'Hybrid'],'online');
    $input('venue_name','Venue name','','text','maxlength="255"'); $area('venue_address','Venue address');
    ?><div class="admin-form-row"><?php $input('price','Price','0','number','min="0" step="0.01" required'); $select('currency','Currency',$currencies,'AED'); ?></div>
    <div class="admin-form-row"><?php $input('capacity','Capacity','','number','min="1"'); $input('cpd_points','CPD points','','number','min="0" step="0.01"'); ?></div><?php
    $select('publication_status','Page visibility',['draft'=>'Draft','published'=>'Published'],'draft');
    $select('registration_status','Registration',['closed'=>'Closed','open'=>'Open','waitlist'=>'Waitlist'],'closed');
    $input('zoom_url','Zoom attendee join link (paid bookings only)','','url','maxlength="1000" autocomplete="off"');
    ?><fieldset><legend>Edition trainers</legend><p class="admin-help">Select all trainers for this edition. Only published trainer profiles appear on the public page.</p><?php foreach ($trainerOptions as $tid=>$name): ?><label class="check-label"><input type="checkbox" name="trainer_ids[]" value="<?= (int)$tid ?>" <?= in_array((int)$tid,$selected,true)?'checked':'' ?>><?= e($name) ?></label><?php endforeach; ?><a href="/admin/trainers/new">Create trainer profile</a></fieldset>
    <fieldset><legend>Highlights</legend><?php foreach (Editorial::FEATURES as $key=>$feature): ?><label class="check-label"><input type="checkbox" name="features[]" value="<?= e($key) ?>" <?= in_array($key,$features,true)?'checked':'' ?>><?= e($feature[1]) ?></label><?php endforeach; ?></fieldset>
    <label>Agenda PDF (public resource, email-gated on the edition page)<input type="file" name="agenda" accept="application/pdf"></label>
    <?php if (!empty($row['agenda_asset_id'])): ?><p>Agenda attached.</p><?php $check('remove_agenda','Detach agenda (existing resource links remain valid)'); endif;
endif;
$input('slug','URL slug (leave blank to generate)','','text','maxlength="190" pattern="[a-z0-9]+(-[a-z0-9]+)*"');
?>
<label><?= $kind === 'trainers' ? 'Portrait' : ($kind === 'editions' ? 'Header override (defaults to programme image)' : 'Programme header') ?><input type="file" name="header_image" accept="image/jpeg,image/png,image/webp"></label>
<?php if (!empty($row['image_path'])): ?><img class="editor-image" src="<?= e($row['image_path']) ?>" alt="Current image"><?php $check('remove_image','Remove current image'); endif; ?>
<p class="admin-help">Markdown supports headings, lists, emphasis and links. Uploaded files are public resources; use approved material only.</p>
<?php if ($kind === 'editions'): ?><input type="hidden" name="trainer_assignment_version" value="<?= e(is_string($_POST['trainer_assignment_version'] ?? null) ? $_POST['trainer_assignment_version'] : $assignmentVersion) ?>"><?php endif; ?>
<button type="submit">Save <?= e(strtolower($label)) ?></button></form></section>
<?php endif; require __DIR__ . '/_footer.php'; ?>
