<?php
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/migrations.php';
require_once __DIR__ . '/inc/helpers.php';
ensure_schema();
$token = trim((string)($_GET['token'] ?? ''));
$project = public_project_by_token($token);
if (!$project) {
    http_response_code(404);
    $pageTitle = 'Projekt nicht gefunden'; $activePage = '';
    require __DIR__ . '/inc/header.php';
    echo '<main class="container py-5 flex-grow-1"><div class="alert alert-warning">Dieser öffentliche Projektlink ist ungültig oder wurde deaktiviert.</div></main>';
    require __DIR__ . '/inc/footer.php';
    exit;
}
$projectId = (int)$project['id'];
$stmt = db()->prepare('SELECT * FROM circuits WHERE project_id = ? ORDER BY id');
$stmt->execute([$projectId]);
$circuits = $stmt->fetchAll();
$stmt = db()->prepare('SELECT i.*, c.name AS circuit_name FROM plan_items i JOIN circuits c ON c.id = i.circuit_id WHERE i.project_id = ? ORDER BY c.id, FIELD(i.phase, "L1", "L2", "L3"), i.name');
$stmt->execute([$projectId]);
$items = $stmt->fetchAll();
$summary = [];
foreach ($circuits as $c) {
    $summary[(int)$c['id']] = ['name' => $c['name'], 'L1' => 0, 'L2' => 0, 'L3' => 0];
}
foreach ($items as $item) {
    $cid = (int)$item['circuit_id'];
    $phase = $item['phase'];
    if (isset($summary[$cid][$phase])) {
        $summary[$cid][$phase] += ((int)$item['power_w']) * ((int)$item['quantity']);
    }
}
$companyLogo = setting_get('company_logo');
$pageTitle = $project['name'] . ' · Öffentliche Ansicht'; $activePage = '';
require __DIR__ . '/inc/header.php';
?>
<main class="container py-4 flex-grow-1">
  <div class="d-flex flex-wrap gap-3 justify-content-between align-items-start mb-4">
    <div>
      <div class="badge text-bg-info mb-2"><i class="bi bi-globe2 me-1"></i>Öffentliche Projektansicht</div>
      <h1 class="h3 mb-1"><?= e($project['name']) ?></h1>
      <div class="text-muted"><?= e($project['client'] ?: 'Kein Kunde') ?> · <?= e($project['technician'] ?: 'Kein Techniker') ?></div>
    </div>
    <button class="btn btn-outline-primary" onclick="window.print()"><i class="bi bi-printer me-1"></i>Drucken / PDF</button>
  </div>

  <div class="card p-4 mb-4">
    <h2 class="h4 mb-3">Phasenübersicht je Stromkreis</h2>
    <div class="table-responsive">
      <table class="table table-bordered align-middle mb-0">
        <thead><tr><th>Stromkreis</th><th>L1</th><th>L2</th><th>L3</th></tr></thead>
        <tbody>
          <?php foreach ($summary as $row): ?>
            <tr>
              <td><strong><?= e($row['name']) ?></strong></td>
              <td><?= number_format($row['L1'], 0, ',', '.') ?> W</td>
              <td><?= number_format($row['L2'], 0, ',', '.') ?> W</td>
              <td><?= number_format($row['L3'], 0, ',', '.') ?> W</td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$summary): ?><tr><td colspan="4" class="text-muted">Keine Stromkreise vorhanden.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card p-4">
    <h2 class="h4 mb-3">Geräteliste</h2>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Gerät</th><th>Marke</th><th>Anzahl</th><th>Stromkreis</th><th>Phase</th><th>Leistung</th><th>Strom</th><th>Bemerkung</th></tr></thead>
        <tbody>
          <?php foreach ($items as $item):
            $totalW = (int)$item['power_w'] * (int)$item['quantity'];
            $voltage = max(1, (int)$item['voltage_v']);
            $amp = $totalW / $voltage;
          ?>
            <tr>
              <td><strong><?= e($item['name']) ?></strong></td>
              <td><?= e($item['brand']) ?></td>
              <td><?= (int)$item['quantity'] ?></td>
              <td><?= e($item['circuit_name']) ?></td>
              <td><?= e($item['phase']) ?></td>
              <td><?= number_format($totalW, 0, ',', '.') ?> W</td>
              <td><?= number_format($amp, 2, ',', '.') ?> A</td>
              <td><?= nl2br(e($item['remarks'])) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$items): ?><tr><td colspan="8" class="text-muted">Keine Geräte im Projekt vorhanden.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>
<?php require __DIR__ . '/inc/footer.php'; ?>
