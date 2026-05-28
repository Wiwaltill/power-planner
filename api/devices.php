<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/helpers.php';
$user = require_login();
ensure_schema();
$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];

function normalize_device(array $data): array {
    return [
        'name' => trim((string)($data['name'] ?? '')),
        'brand' => trim((string)($data['brand'] ?? '')),
        'category' => trim((string)($data['category'] ?? '')),
        'power_w' => max(0, (int)($data['power_w'] ?? $data['power'] ?? 0)),
        'voltage_v' => max(1, (int)($data['voltage_v'] ?? $data['voltage'] ?? 230)),
        'connector' => trim((string)($data['connector'] ?? '')),
        'notes' => trim((string)($data['notes'] ?? '')),
    ];
}

if ($method === 'GET') {
    $stmt = $pdo->prepare('SELECT id, user_id, name, brand, category, power_w, voltage_v, connector, notes FROM devices WHERE user_id = ? ORDER BY brand, name');
    $stmt->execute([(int)$user['id']]);
    $devices = $stmt->fetchAll();
    if (isset($_GET['export'])) {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="devices-export.json"');
        echo json_encode(['devices' => $devices], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    json_response($devices);
}

$data = request_json();

if ($method === 'POST' && isset($_GET['import'])) {
    $items = isset($data[0]) ? $data : ($data['devices'] ?? []);
    if (!is_array($items)) json_response(['error' => 'Import-Datei ist ungültig.'], 422);
    $count = 0;
    foreach ($items as $item) {
        if (!is_array($item)) continue;
        $d = normalize_device($item);
        if ($d['name'] === '') continue;
        $stmt = $pdo->prepare('INSERT INTO devices (user_id, name, brand, category, power_w, voltage_v, connector, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([(int)$user['id'], $d['name'], $d['brand'], $d['category'], $d['power_w'], $d['voltage_v'], $d['connector'], $d['notes']]);
        $count++;
    }
    json_response(['imported' => $count]);
}

if ($method === 'POST') {
    $id = max(0, (int)($data['id'] ?? 0));
    $d = normalize_device($data);
    if ($d['name'] === '') json_response(['error' => 'Name fehlt.'], 422);

    if ($id > 0) {
        $stmt = $pdo->prepare('UPDATE devices SET name = ?, brand = ?, category = ?, power_w = ?, voltage_v = ?, connector = ?, notes = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([$d['name'], $d['brand'], $d['category'], $d['power_w'], $d['voltage_v'], $d['connector'], $d['notes'], $id, (int)$user['id']]);
        if ($stmt->rowCount() === 0) {
            $check = $pdo->prepare('SELECT id FROM devices WHERE id = ? AND user_id = ?');
            $check->execute([$id, (int)$user['id']]);
            if (!$check->fetch()) json_response(['error' => 'Gerät nicht gefunden.'], 404);
        }
    } else {
        $stmt = $pdo->prepare('INSERT INTO devices (user_id, name, brand, category, power_w, voltage_v, connector, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([(int)$user['id'], $d['name'], $d['brand'], $d['category'], $d['power_w'], $d['voltage_v'], $d['connector'], $d['notes']]);
        $id = (int)$pdo->lastInsertId();
    }
    json_response(['ok' => true, 'id' => $id]);
}

if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) json_response(['error' => 'ID fehlt.'], 422);
    $stmt = $pdo->prepare('DELETE FROM devices WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, (int)$user['id']]);
    json_response(['ok' => true]);
}

json_response(['error' => 'Methode nicht erlaubt.'], 405);
