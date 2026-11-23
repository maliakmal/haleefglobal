<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Autoload.php';

\Haleef\Support\Env::load(dirname(__DIR__) . '/.env');
date_default_timezone_set(\Haleef\Support\Env::get('APP_TIMEZONE', 'Asia/Dubai') ?: 'Asia/Dubai');

