<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';
$pageTitle = $pageTitle ?? APP_NAME;
$activePage = $activePage ?? '';
$user = current_user();
function nav_active(string $page, string $active): string { return $page === $active ? ' active' : ''; }
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container">
    <a class="navbar-brand" href="projects">⚡ <?= e(APP_NAME) ?></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav ms-auto">
        <?php if ($user): ?>
          <li class="nav-item"><a class="nav-link<?= nav_active('projects', $activePage) ?>" href="projects">Projekte</a></li>
          <li class="nav-item"><a class="nav-link<?= nav_active('devices', $activePage) ?>" href="devices">Geräte</a></li>
          <li class="nav-item"><span class="nav-link disabled"><?= e($user['name']) ?></span></li>
          <li class="nav-item"><a class="nav-link" href="logout">Logout</a></li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link<?= nav_active('login', $activePage) ?>" href="login">Login</a></li>
          <li class="nav-item"><a class="nav-link<?= nav_active('register', $activePage) ?>" href="register">Registrieren</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>
