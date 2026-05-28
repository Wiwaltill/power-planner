<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/helpers.php';
require_login();
$pageTitle = 'Einstellungen';
$activePage = 'settings';
$pageScript = 'assets/js/settings.js';
require __DIR__ . '/inc/header.php';
?>
<main class="container py-4 flex-grow-1">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h1 class="h3 mb-1">Einstellungen</h1>
      <div class="small-muted">Marken, Kategorien und Anschlüsse für die Geräteverwaltung pflegen.</div>
    </div>
  </div>
  <div class="row g-4">
    <div class="col-lg-6">
      <div class="card p-4">
        <h2 class="h4">Marken</h2>
        <form class="input-group mb-3" data-form="brands">
          <input class="form-control" placeholder="Neue Marke" required>
          <button class="btn btn-primary">Hinzufügen</button>
        </form>
        <div id="brandRows" class="list-group"></div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card p-4">
        <h2 class="h4">Kategorien</h2>
        <form class="input-group mb-3" data-form="categories">
          <input class="form-control" placeholder="Neue Kategorie" required>
          <button class="btn btn-primary">Hinzufügen</button>
        </form>
        <div id="categoryRows" class="list-group"></div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card p-4">
        <h2 class="h4">Anschlüsse</h2>
        <form class="input-group mb-3" data-form="connectors">
          <input class="form-control" placeholder="Neuer Anschluss" required>
          <button class="btn btn-primary">Hinzufügen</button>
        </form>
        <div id="connectorRows" class="list-group"></div>
      </div>
    </div>
  </div>
</main>

<?php require __DIR__ . '/inc/footer.php'; ?>
