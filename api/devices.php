<?php
require_once __DIR__ . '/../inc/auth.php'; require_once __DIR__ . '/../inc/helpers.php';
$user = require_login(); $pdo = db(); $method = $_SERVER['REQUEST_METHOD'];
if ($method === 'GET') {
    $stmt = $pdo->prepare('SELECT * FROM devices WHERE user_id=? ORDER BY brand, name'); $stmt->execute([$user['id']]);
    $devices = $stmt->fetchAll();
    if (isset($_GET['export'])) {
        header('Content-Type: application/json; charset=utf-8'); header('Content-Disposition: attachment; filename="devices-export.json"');
        echo json_encode($devices, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); exit;
    }
    json_response($devices);
}
$data = request_json();
if ($method === 'POST' && isset($_GET['import'])) {
    $items = isset($data[0]) ? $data : ($data['devices'] ?? []); $count = 0;
    foreach ($items as $d) {
        if (empty($d['name'])) continue;
        $stmt = $pdo->prepare('INSERT INTO devices (user_id,name,brand,category,power_w,voltage_v,connector,notes) VALUES (?,?,?,?,?,?,?,?)');
        $stmt->execute([$user['id'], trim($d['name']), $d['brand'] ?? '', $d['category'] ?? '', (int)($d['power_w'] ?? 0), (int)($d['voltage_v'] ?? 230), $d['connector'] ?? '', $d['notes'] ?? '']); $count++;
    }
    json_response(['imported'=>$count]);
}
if ($method === 'POST') {
    $id = (int)($data['id'] ?? 0);
    $payload = [trim($data['name'] ?? ''), $data['brand'] ?? '', $data['category'] ?? '', (int)($data['power_w'] ?? 0), (int)($data['voltage_v'] ?? 230), $data['connector'] ?? '', $data['notes'] ?? ''];
    if (!$payload[0]) json_response(['error'=>'Name fehlt.'], 422);
    if ($id) {
        $stmt = $pdo->prepare('UPDATE devices SET name=?, brand=?, category=?, power_w=?, voltage_v=?, connector=?, notes=? WHERE id=? AND user_id=?');
        $params = array_merge($payload, [$id, $user['id']]);
        $stmt->execute($params);
    } else {
        $stmt = $pdo->prepare('INSERT INTO devices (user_id,name,brand,category,power_w,voltage_v,connector,notes) VALUES (?,?,?,?,?,?,?,?)');
        $params = array_merge([$user['id']], $payload);
        $stmt->execute($params); $id = (int)$pdo->lastInsertId();
    }
    json_response(['id'=>$id]);
}
if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0); $stmt = $pdo->prepare('DELETE FROM devices WHERE id=? AND user_id=?'); $stmt->execute([$id, $user['id']]);
    json_response(['ok'=>true]);
}
json_response(['error'=>'Methode nicht erlaubt.'], 405);
