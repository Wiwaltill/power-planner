<?php
require_once __DIR__ . '/inc/auth.php'; require_once __DIR__ . '/inc/helpers.php';
require_login(); $pageTitle = 'Geräte'; $activePage = 'devices'; $pageScript = 'assets/js/devices.js'; require __DIR__ . '/inc/header.php';
?>
<main class="container py-4 flex-grow-1">
  <div class="row g-4">
    <div class="col-lg-4"><div class="card p-4 sticky-lg-top planner-form-card"><h1 class="h3">Gerät verwalten</h1><form id="deviceForm" class="row g-3">
      <input type="hidden" id="deviceId">
      <div class="col-12"><label class="form-label">Name</label><input class="form-control" id="name" required></div>
      <div class="col-12"><label class="form-label">Marke</label><input class="form-control" id="brand"></div>
      <div class="col-12"><label class="form-label">Kategorie</label><input class="form-control" id="category"></div>
      <div class="col-md-6"><label class="form-label">Leistung W</label><input type="number" class="form-control" id="power" min="0" required></div>
      <div class="col-md-6"><label class="form-label">Spannung V</label><input type="number" class="form-control" id="voltage" value="230" min="1"></div>
      <div class="col-12"><label class="form-label">Anschluss</label><input class="form-control" id="connector"></div>
      <div class="col-12"><label class="form-label">Notizen</label><textarea class="form-control" id="notes" rows="2"></textarea></div>
      <div class="col-12 d-flex gap-2"><button class="btn btn-primary flex-fill">Speichern</button><button class="btn btn-outline-secondary" id="resetForm" type="button">Neu</button></div>
    </form><hr><div class="d-flex gap-2"><button class="btn btn-outline-secondary btn-sm flex-fill" id="exportDevices" type="button">Export</button><button class="btn btn-outline-success btn-sm flex-fill" id="importDevices" type="button">Import</button><input class="d-none" id="importDevicesFile" type="file" accept=".json,application/json"></div></div></div>
    <div class="col-lg-8"><div class="card p-4"><h2 class="h4 mb-3">Meine Geräte</h2><div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Gerät</th><th>Marke</th><th>Leistung</th><th>Strom</th><th>Anschluss</th><th></th></tr></thead><tbody id="deviceRows"></tbody></table></div></div></div>
  </div>
</main>
<?php require __DIR__ . '/inc/footer.php'; ?>
