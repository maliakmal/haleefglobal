<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/config.php';

use Haleef\Admin\AdminAuth;
use Haleef\Storage\PrivateUploadStorage;
use Haleef\Support\Database;

AdminAuth::requireUser();
$statement=Database::connection()->prepare('SELECT * FROM payment_evidence WHERE id=? LIMIT 1');
$statement->execute([(int)($_GET['evidence_id']??0)]);
$evidence=$statement->fetch();
if(!$evidence){http_response_code(404);exit('File not found.');}
$path=(new PrivateUploadStorage())->absolutePath($evidence['storage_path']);
if(!is_file($path)){http_response_code(404);exit('File not found.');}
header('Content-Type: '.$evidence['mime_type']);
header('Content-Length: '.filesize($path));
header('Content-Disposition: attachment; filename="'.str_replace(['"',"\r","\n"],'',$evidence['original_name']).'"');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;

