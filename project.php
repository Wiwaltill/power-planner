<?php
require_once __DIR__ . '/inc/auth.php'; require_once __DIR__ . '/inc/helpers.php';
$user = require_login();
$projectId = (int)($_GET['id'] ?? 0);
$project = user_project($projectId, (int)$user['id']);
if (!$project) { header('Location: projects'); exit; }
$pageTitle = $project['name'] . ' · Planung'; $activePage = 'projects'; $pageScript = 'assets/js/app.js'; require __DIR__ . '/inc/header.php';
?>
<script>window.APP_PROJECT_ID = <?= (int)$project['id'] ?>;</script>
<main class="container py-4 flex-grow-1">
  <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-4">
    <div><h1 class="h3 mb-1"><?= e($project['name']) ?></h1><div class="small-muted"><?= e($project['client'] ?: 'Kein Kunde') ?> · <?= e($project['technician'] ?: 'Kein Techniker') ?></div></div>
    <a href="<?= e(app_url('projects')) ?>" class="btn btn-outline-secondary">Zur Projektliste</a>
  </div>
  <div class="card p-3 p-md-4 mb-4">
    <div class="row g-3 align-items-end">
      <div class="col-md-4"><label class="form-label">Aktiver Stromkreis</label><select class="form-select" id="activeCircuitSelect"></select></div>
      <div class="col-md-5"><label class="form-label">Neuen Stromkreis anlegen</label><div class="input-group"><input class="form-control" id="newCircuitName" placeholder="z. B. Fronttruss"><button class="btn btn-outline-primary" id="addCircuit" type="button">Anlegen</button></div></div>
      <div class="col-md-3"><button class="btn btn-outline-danger w-100" id="deleteCircuit" type="button">Stromkreis löschen</button></div>
    </div>
  </div>
  <div class="row g-4">
    <div class="col-lg-4"><div class="card p-4 sticky-lg-top planner-form-card">
      <h2 class="h4">Gerät hinzufügen</h2>
      <form id="loadForm" class="row g-3">
        <div class="col-12"><label class="form-label">Gerät</label><div class="dropdown w-100 device-dropdown"><button class="btn btn-outline-secondary dropdown-toggle w-100 text-start" type="button" id="deviceDropdownButton" data-bs-toggle="dropdown" data-bs-auto-close="outside"><span id="deviceDropdownLabel">Gerät suchen oder auswählen...</span></button><div class="dropdown-menu w-100 p-2 device-dropdown-menu"><input type="search" class="form-control form-control-sm mb-2" id="deviceSearch" placeholder="Gerät filtern..."><div id="deviceOptions" class="device-options list-group list-group-flush"></div></div></div><input type="hidden" id="deviceSelect" required></div>
        <div class="col-12"><label class="form-label">Kategorie-Filter</label><select class="form-select" id="categoryFilter"><option value="">Alle Kategorien</option></select></div>
        <div class="col-md-4"><label class="form-label">Anzahl</label><input type="number" class="form-control" id="quantity" min="1" value="1" required></div>
        <div class="col-md-4"><label class="form-label">Phase</label><select class="form-select" id="phase"><option>L1</option><option>L2</option><option>L3</option></select></div>
        <div class="col-md-4"><label class="form-label">Spannung</label><input type="number" class="form-control" id="voltage" value="230" min="1"></div>
        <div class="col-12"><label class="form-label">Stromkreis</label><select class="form-select" id="circuitSelect"></select></div>
        <div class="col-12"><label class="form-label">Bemerkungen</label><textarea class="form-control" id="remarks" rows="2"></textarea></div>
        <div class="col-12"><button class="btn btn-primary w-100">Zum Plan hinzufügen</button></div>
      </form>
      <hr><div class="d-flex gap-2 flex-wrap">
        <button class="btn btn-outline-primary btn-sm flex-fill" id="exportPdf" type="button">PDF exportieren</button>
        <button class="btn btn-outline-secondary btn-sm flex-fill" id="exportCsv" type="button">CSV exportieren</button>
        <button class="btn btn-outline-warning btn-sm flex-fill" id="autoDistribute" type="button">Automatisch verteilen</button>
        <button class="btn btn-outline-danger btn-sm flex-fill" id="clearPlan" type="button">Plan leeren</button>
      </div>
    </div></div>
    <div class="col-lg-8"><div class="d-flex justify-content-between align-items-end mb-3"><div><h2 class="h4 mb-0">Phasenplaner</h2><div class="small-muted">Geräte im aktiven Stromkreis per Drag & Drop zwischen L1 bis L3 verschieben.</div></div></div><div class="row g-3" id="phaseBoards"></div></div>
  </div>
  <div class="card p-4 mt-4"><h2 class="h4 mb-3">Aktueller Stromplan</h2><div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Gerät</th><th>Marke</th><th>Anzahl</th><th>Stromkreis</th><th>Phase</th><th>Leistung</th><th>Strom</th><th>Bemerkungen</th><th></th></tr></thead><tbody id="planRows"></tbody></table></div></div>
  <section id="printArea" class="print-area"><div class="print-header"><div><h1>Stromplan Übersicht</h1><p><?= e($project['name']) ?> · <?= e($project['client']) ?></p></div><div class="print-meta"><?= date('d.m.Y') ?></div></div><div id="printSummary"></div><div id="printPhaseTables"></div><table class="print-table"><thead><tr><th>Gerät</th><th>Marke</th><th>Anzahl</th><th>Stromkreis</th><th>Phase</th><th>Leistung</th><th>Strom</th><th>Bemerkung</th></tr></thead><tbody id="printRows"></tbody></table></section>
</main>
<?php require __DIR__ . '/inc/footer.php'; ?>
