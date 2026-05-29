<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/helpers.php';
$user = require_login();
ensure_schema();

$projectId = (int)($_GET['id'] ?? 0);
$project = user_project($projectId, (int)$user['id']);
if (!$project) {
    http_response_code(404);
    exit('Projekt nicht gefunden.');
}

$pdo = db();
$stmt = $pdo->prepare('SELECT id, name, client, technician, public_share_enabled, created_at, updated_at FROM projects WHERE id = ? LIMIT 1');
$stmt->execute([$projectId]);
$projectData = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare('SELECT id, name, amp_limit, created_at FROM circuits WHERE project_id = ? ORDER BY id ASC');
$stmt->execute([$projectId]);
$circuits = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare('SELECT id, circuit_id, device_id, name, brand, category, quantity, phase, power_w, voltage_v, remarks, created_at, updated_at FROM plan_items WHERE project_id = ? ORDER BY circuit_id ASC, id ASC');
$stmt->execute([$projectId]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare('SELECT u.email, u.name FROM project_shares ps JOIN users u ON u.id = ps.user_id WHERE ps.project_id = ? ORDER BY u.email ASC');
$stmt->execute([$projectId]);
$shares = $stmt->fetchAll(PDO::FETCH_ASSOC);

$export = [
    'schema' => 'power-planner-project',
    'schema_version' => 1,
    'app_version' => defined('APP_VERSION') ? APP_VERSION : '',
    'exported_at' => date('c'),
    'project' => $projectData,
    'circuits' => $circuits,
    'plan_items' => $items,
    'shares' => $shares,
];

$filenameBase = trim((string)($projectData['name'] ?? 'projekt')) ?: 'projekt';
$filenameBase = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $filenameBase);
$filenameBase = trim($filenameBase, '-') ?: 'projekt';
$filename = $filenameBase . '-projekt-export-' . date('Ymd-His') . '.json';

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');
echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
