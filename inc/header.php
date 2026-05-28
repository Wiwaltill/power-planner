<?php
$pageTitle = $pageTitle ?? 'Stromplaner';
$activePage = $activePage ?? '';
function navActive(string $page, string $activePage): string {
    return $page === $activePage ? ' active' : '';
}
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container">
    <a class="navbar-brand" href="./">⚡ Stromplaner</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link<?= navActive('planner', $activePage) ?>" href="./">Planung</a></li>
        <li class="nav-item"><a class="nav-link<?= navActive('devices', $activePage) ?>" href="devices">Geräte</a></li>
      </ul>
    </div>
  </div>
</nav>
