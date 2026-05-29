<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/db.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) die('Invalid project');

$stmt = $pdo->prepare("SELECT * FROM projects WHERE id=?");
$stmt->execute([$id]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$project) die('Project not found');

$stmt = $pdo->prepare("SELECT * FROM circuits WHERE project_id=?");
$stmt->execute([$id]);
$circuits = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT * FROM plan_items WHERE project_id=?");
$stmt->execute([$id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$data = [
  'exported_at' => date('c'),
  'project' => $project,
  'circuits' => $circuits,
  'plan_items' => $items
];

header('Content-Type: application/json');
header('Content-Disposition: attachment; filename="'.preg_replace('/[^a-zA-Z0-9_-]/','_',$project['name']).'.json"');
echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
