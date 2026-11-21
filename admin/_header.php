<?php
declare(strict_types=1);

$adminPageTitle = $adminPageTitle ?? 'Dashboard';
$adminUser = $adminUser ?? \Haleef\Admin\AdminAuth::requireUser();
$adminFlash = \Haleef\Support\Flash::pull();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e($adminPageTitle) ?> | Haleef Learning Console</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=Manrope:wght@500;600;700&display=swap" rel="stylesheet"><link rel="stylesheet" href="/assets/css/admin.css"></head><body><div class="admin-shell"><aside class="admin-sidebar"><a class="admin-brand" href="/admin"><img src="/assets/images/haleef-logo.webp" alt="Haleef"><span>Learning Console</span></a><nav><a href="/admin">Dashboard</a><a href="/admin/trainings">Trainings</a><a href="/admin/editions">Editions</a><a href="/admin/orders">Orders & registrations</a><a href="/admin/payment-methods">Payment methods</a><a href="/admin/resources">Resources</a><span class="nav-coming">Attendance <small>Coming next</small></span></nav><div class="admin-account"><strong><?= e($adminUser['name']) ?></strong><span><?= e($adminUser['role']) ?></span><a href="/admin/logout">Sign out</a></div></aside><main class="admin-main"><header class="admin-topbar"><div><span>Haleef Learning</span><h1><?= e($adminPageTitle) ?></h1></div><a href="/training/courses" target="_blank" rel="noopener">View public calendar</a></header><?php foreach ($adminFlash as $flash): ?><div class="admin-alert admin-alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endforeach; ?>

