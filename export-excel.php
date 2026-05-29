<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/helpers.php';
$user = require_login();
$projectId = (int)($_GET['id'] ?? 0);
$project = user_project($projectId, (int)$user['id']);
if (!$project) { http_response_code(404); echo 'Projekt nicht gefunden.'; exit; }

$stmt = db()->prepare('SELECT pi.*, c.name AS circuit_name FROM plan_items pi LEFT JOIN circuits c ON c.id = pi.circuit_id WHERE pi.project_id = ? ORDER BY c.name ASC, FIELD(pi.phase, "L1", "L2", "L3"), pi.name ASC');
$stmt->execute([$projectId]);
$items = $stmt->fetchAll();

function excel_num($value, int $decimals = 2): string {
    return number_format((float)$value, $decimals, ',', '');
}
function excel_watts(array $item): float {
    return (float)($item['power_w'] ?? 0) * (float)($item['quantity'] ?? 1);
}
function excel_amps(array $item): float {
    $voltage = max(1, (float)($item['voltage_v'] ?? 230));
    return excel_watts($item) / $voltage;
}

$totals = [
    'L1' => ['w' => 0.0, 'a' => 0.0],
    'L2' => ['w' => 0.0, 'a' => 0.0],
    'L3' => ['w' => 0.0, 'a' => 0.0],
];
foreach ($items as $item) {
    $phase = $item['phase'] ?? 'L1';
    if (!isset($totals[$phase])) { $totals[$phase] = ['w' => 0.0, 'a' => 0.0]; }
    $totals[$phase]['w'] += excel_watts($item);
    $totals[$phase]['a'] += excel_amps($item);
}

$filenameBase = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $project['name'] ?: 'stromplan');
$filenameBase = trim($filenameBase, '-') ?: 'stromplan';
$filename = $filenameBase . '-stromplan.xls';

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');
echo "\xEF\xBB\xBF";
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: Arial, sans-serif; font-size: 11pt; }
    h1 { font-size: 18pt; margin-bottom: 4px; }
    h2 { font-size: 13pt; margin-top: 20px; }
    table { border-collapse: collapse; width: 100%; margin-bottom: 18px; }
    th, td { border: 1px solid #999; padding: 6px; vertical-align: top; }
    th { background: #eeeeee; font-weight: bold; }
    .meta td { border: none; padding: 3px 6px 3px 0; }
  </style>
</head>
<body>
  <h1>Stromplan Export</h1>
  <table class="meta">
    <tr><td><strong>Projekt:</strong></td><td><?= e($project['name']) ?></td></tr>
    <tr><td><strong>Kunde:</strong></td><td><?= e($project['client'] ?? '') ?></td></tr>
    <tr><td><strong>Techniker:</strong></td><td><?= e($project['technician'] ?? '') ?></td></tr>
    <tr><td><strong>Export:</strong></td><td><?= date('d.m.Y H:i') ?></td></tr>
  </table>

  <h2>Phasenübersicht</h2>
  <table>
    <thead><tr><th>Phase</th><th>Leistung gesamt</th><th>Strom gesamt</th></tr></thead>
    <tbody>
      <?php foreach (['L1','L2','L3'] as $phase): ?>
        <tr>
          <td><?= e($phase) ?></td>
          <td><?= excel_num($totals[$phase]['w'], 0) ?> W</td>
          <td><?= excel_num($totals[$phase]['a'], 2) ?> A</td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <h2>Geräte</h2>
  <table>
    <thead>
      <tr>
        <th>Gerät</th>
        <th>Marke</th>
        <th>Kategorie</th>
        <th>Anzahl</th>
        <th>Stromkreis</th>
        <th>Phase</th>
        <th>Leistung</th>
        <th>Spannung</th>
        <th>Strom</th>
        <th>Bemerkung</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$items): ?>
        <tr><td colspan="10">Keine Geräte im Plan.</td></tr>
      <?php else: foreach ($items as $item): ?>
        <tr>
          <td><?= e($item['name']) ?></td>
          <td><?= e($item['brand'] ?? '') ?></td>
          <td><?= e($item['category'] ?? '') ?></td>
          <td><?= (int)$item['quantity'] ?></td>
          <td><?= e($item['circuit_name'] ?? '') ?></td>
          <td><?= e($item['phase']) ?></td>
          <td><?= excel_num(excel_watts($item), 0) ?> W</td>
          <td><?= (int)$item['voltage_v'] ?> V</td>
          <td><?= excel_num(excel_amps($item), 2) ?> A</td>
          <td><?= e($item['remarks'] ?? '') ?></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</body>
</html>
