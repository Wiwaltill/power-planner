<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/helpers.php';
$user = require_login();
ensure_schema();
$pdo = db();
$projectId = (int)($_GET['project_id'] ?? 0);
if ($projectId <= 0 || !user_project($projectId, (int)$user['id'])) json_response(['error' => 'Projekt nicht gefunden.'], 404);
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $pdo->prepare('SELECT id, project_id, name, amp_limit FROM circuits WHERE project_id = ? ORDER BY id');
    $stmt->execute([$projectId]);
    json_response($stmt->fetchAll());
}

$data = request_json();

if ($method === 'POST') {
    $name = trim((string)($data['name'] ?? ''));
    $ampLimit = (float)($data['amp_limit'] ?? 16);
    if ($name === '') json_response(['error' => 'Name fehlt.'], 422);
    $stmt = $pdo->prepare('INSERT INTO circuits (project_id, name, amp_limit) VALUES (?, ?, ?)');
    $stmt->execute([$projectId, $name, $ampLimit > 0 ? $ampLimit : 16]);
    json_response(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
}

if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) json_response(['error' => 'ID fehlt.'], 422);
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM circuits WHERE project_id = ?');
    $stmt->execute([$projectId]);
    if ((int)$stmt->fetchColumn() <= 1) json_response(['error' => 'Der letzte Stromkreis kann nicht gelöscht werden.'], 422);
    $stmt = $pdo->prepare('DELETE FROM circuits WHERE id = ? AND project_id = ?');
    $stmt->execute([$id, $projectId]);
    json_response(['ok' => true]);
}

json_response(['error' => 'Methode nicht erlaubt.'], 405);
