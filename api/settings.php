<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/helpers.php';
$user = require_login();
ensure_schema();
$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];
$type = $_GET['type'] ?? 'all';
$tables = ['brands' => 'device_brands', 'categories' => 'device_categories', 'connectors' => 'device_connectors', 'tags' => 'project_tags'];

function list_values(PDO $pdo, string $table): array {
    $select = $table === 'project_tags' ? 'id, name, color' : 'id, name';
    $stmt = $pdo->prepare("SELECT {$select} FROM {$table} ORDER BY name");
    $stmt->execute();
    return $stmt->fetchAll();
}

if ($method === 'GET') {
    json_response([
        'brands' => list_values($pdo, 'device_brands'),
        'categories' => list_values($pdo, 'device_categories'),
        'connectors' => list_values($pdo, 'device_connectors'),
        'tags' => list_values($pdo, 'project_tags'),
    ]);
}

if (!isset($tables[$type])) json_response(['error' => 'Ungültiger Einstellungstyp.'], 422);
$table = $tables[$type];
$data = request_json();

if ($method === 'POST') {
    $name = trim((string)($data['name'] ?? ''));
    if ($name === '') json_response(['error' => 'Name fehlt.'], 422);
    try {
        if ($table === 'project_tags') {
            $color = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($data['color'] ?? 'secondary')) ?: 'secondary';
            $stmt = $pdo->prepare("INSERT INTO {$table} (name, color) VALUES (?, ?)");
            $stmt->execute([$name, $color]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO {$table} (user_id, name) VALUES (?, ?)");
            $stmt->execute([(int)$user['id'], $name]);
        }
        json_response(['ok' => true, 'id' => (int)$pdo->lastInsertId(), 'name' => $name]);
    } catch (PDOException $e) {
        json_response(['error' => 'Eintrag existiert bereits.'], 422);
    }
}

if ($method === 'PATCH') {
    $id = (int)($_GET['id'] ?? 0);
    $name = trim((string)($data['name'] ?? ''));
    if ($id <= 0 || $name === '') json_response(['error' => 'ID oder Name fehlt.'], 422);
    try {
        if ($table === 'project_tags') {
            $color = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($data['color'] ?? 'secondary')) ?: 'secondary';
            $stmt = $pdo->prepare("UPDATE {$table} SET name = ?, color = ? WHERE id = ?");
            $stmt->execute([$name, $color, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE {$table} SET name = ? WHERE id = ?");
            $stmt->execute([$name, $id]);
        }
        json_response(['ok' => true]);
    } catch (PDOException $e) {
        json_response(['error' => 'Eintrag existiert bereits.'], 422);
    }
}

if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) json_response(['error' => 'ID fehlt.'], 422);
    $stmt = $pdo->prepare("DELETE FROM {$table} WHERE id = ?");
    $stmt->execute([$id]);
    json_response(['ok' => true]);
}

json_response(['error' => 'Methode nicht erlaubt.'], 405);
