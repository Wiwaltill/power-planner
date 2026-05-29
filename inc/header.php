<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';
$pageTitle = $pageTitle ?? APP_NAME;
$activePage = $activePage ?? '';
$user = current_user();
$companyLogo = setting_get('company_logo');
function nav_active(string $page, string $active): string { return $page === $active ? ' active' : ''; }
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($basePath === '') { $basePath = ''; }
function app_url(string $path = ''): string {
    global $basePath;
    return ($basePath === '' ? '' : $basePath) . '/' . ltrim($path, '/');
}
function app_full_url(string $path = ''): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . app_url($path);
}
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="<?= e(app_url('assets/css/style.css')) ?>" rel="stylesheet">
  <script>window.APP_BASE_PATH = <?= json_encode($basePath, JSON_UNESCAPED_SLASHES) ?>;</script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="d-flex flex-column min-vh-100">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center gap-2" href="<?= e(app_url('projects')) ?>"><?php if ($companyLogo): ?><img class="header-logo" src="<?= e(app_url($companyLogo)) ?>" alt="Firmenlogo"><?php endif; ?><span>⚡ <?= e(APP_NAME) ?></span></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav ms-auto">
        <?php if ($user): ?>
          <li class="nav-item"><a class="nav-link<?= nav_active('projects', $activePage) ?>" href="<?= e(app_url('projects')) ?>"><i class="bi bi-folder2-open me-1"></i>Projekte</a></li>
          <li class="nav-item"><a class="nav-link<?= nav_active('devices', $activePage) ?>" href="<?= e(app_url('devices')) ?>"><i class="bi bi-lightning-charge me-1"></i>Geräte</a></li>
          <?php if (($user['role'] ?? '') === 'admin'): ?>
            <li class="nav-item"><a class="nav-link<?= nav_active('settings', $activePage) ?>" href="<?= e(app_url('settings')) ?>"><i class="bi bi-gear me-1"></i>Einstellungen</a></li>
            <li class="nav-item"><a class="nav-link<?= nav_active('users', $activePage) ?>" href="<?= e(app_url('users')) ?>"><i class="bi bi-people me-1"></i>Nutzer</a></li>
          <?php endif; ?>
          <li class="nav-item"><a class="nav-link<?= nav_active('profile', $activePage) ?>" href="<?= e(app_url('profile')) ?>"><i class="bi bi-person-circle me-1"></i>Profil</a></li>
          <li class="nav-item"><span class="nav-link disabled"><?= e($user['name']) ?></span></li>
          <li class="nav-item"><a class="nav-link" href="<?= e(app_url('logout')) ?>"><i class="bi bi-box-arrow-right me-1"></i>Logout</a></li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link<?= nav_active('login', $activePage) ?>" href="<?= e(app_url('login')) ?>"><i class="bi bi-box-arrow-in-right me-1"></i>Login</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>
