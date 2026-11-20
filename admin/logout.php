<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/config.php';
\Haleef\Admin\AdminAuth::logout();
header('Location: /admin/login');
exit;

