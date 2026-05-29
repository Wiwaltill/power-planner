<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/helpers.php';
$user = require_login();
ensure_schema();
$pdo = db();
$projectId = (int)($_GET['project_id'] ?? 0);
$method = $_SERVER['REQUEST_METHOD'];
if ($projectId <= 0) json_response(['error' => 'Projekt fehlt.'], 422);
$project = require_project_access($projectId, (int)$user['id'], $method === 'GET' ? 'view' : 'edit');

if ($method === 'GET') {
    $stmt = $pdo->prepare('SELECT i.*, c.name AS circuit_name FROM plan_items i JOIN circuits c ON c.id = i.circuit_id WHERE i.project_id = ? ORDER BY i.id');
    $stmt->execute([$projectId]);
    json_response($stmt->fetchAll());
}

$data = request_json();

if ($method === 'POST') {
    $deviceId = (int)($data['device_id'] ?? 0);
    $device = null;
    if ($deviceId > 0) {
        $stmt = $pdo->prepare('SELECT id, name, brand, category, power_w, voltage_v FROM devices WHERE id = ?');
        $stmt->execute([$deviceId]);
        $device = $stmt->fetch();
        if (!$device) json_response(['error' => 'Gerät nicht gefunden.'], 404);
    }
    $name = $device['name'] ?? trim((string)($data['name'] ?? ''));
    if ($name === '') json_response(['error' => 'Gerät fehlt.'], 422);

    $circuitId = (int)($data['circuit_id'] ?? 0);
    $stmt = $pdo->prepare('SELECT id FROM circuits WHERE id = ? AND project_id = ?');
    $stmt->execute([$circuitId, $projectId]);
    if (!$stmt->fetch()) json_response(['error' => 'Stromkreis fehlt.'], 422);

    $phase = in_array(($data['phase'] ?? 'L1'), ['L1', 'L2', 'L3'], true) ? $data['phase'] : 'L1';
    $stmt = $pdo->prepare('INSERT INTO plan_items (project_id, circuit_id, device_id, name, brand, category, quantity, phase, power_w, voltage_v, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $projectId,
        $circuitId,
        $deviceId > 0 ? $deviceId : null,
        $name,
        $device['brand'] ?? trim((string)($data['brand'] ?? '')),
        $device['category'] ?? trim((string)($data['category'] ?? '')),
        max(1, (int)($data['quantity'] ?? 1)),
        $phase,
        max(0, (int)($device['power_w'] ?? ($data['power_w'] ?? 0))),
        max(1, (int)($data['voltage_v'] ?? ($device['voltage_v'] ?? 230))),
        trim((string)($data['remarks'] ?? ''))
    ]);
    json_response(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
}

if ($method === 'PATCH') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) json_response(['error' => 'ID fehlt.'], 422);
    $fields = [];
    $values = [];
    $allowed = ['circuit_id', 'quantity', 'phase', 'voltage_v', 'remarks'];
    foreach ($allowed as $field) {
        if (!array_key_exists($field, $data)) continue;
        $value = $data[$field];
        if ($field === 'circuit_id') {
            $value = (int)$value;
            $stmt = $pdo->prepare('SELECT id FROM circuits WHERE id = ? AND project_id = ?');
            $stmt->execute([$value, $projectId]);
            if (!$stmt->fetch()) json_response(['error' => 'Stromkreis fehlt.'], 422);
        }
        if ($field === 'quantity') $value = max(1, (int)$value);
        if ($field === 'voltage_v') $value = max(1, (int)$value);
        if ($field === 'phase') $value = in_array($value, ['L1', 'L2', 'L3'], true) ? $value : 'L1';
        $fields[] = "$field = ?";
        $values[] = $value;
    }
    if (!$fields) json_response(['ok' => true]);
    $values[] = $id;
    $values[] = $projectId;
    $stmt = $pdo->prepare('UPDATE plan_items SET ' . implode(', ', $fields) . ' WHERE id = ? AND project_id = ?');
    $stmt->execute($values);
    json_response(['ok' => true]);
}

if ($method === 'DELETE') {
    if (isset($_GET['all'])) {
        $pdo->prepare('DELETE FROM plan_items WHERE project_id = ?')->execute([$projectId]);
        json_response(['ok' => true]);
    }
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) json_response(['error' => 'ID fehlt.'], 422);
    $pdo->prepare('DELETE FROM plan_items WHERE id = ? AND project_id = ?')->execute([$id, $projectId]);
    json_response(['ok' => true]);
}

json_response(['error' => 'Methode nicht erlaubt.'], 405);
