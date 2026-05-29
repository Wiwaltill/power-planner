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
function import_csv_devices(string $path): int {
    $pdo = db(); $user = current_user(); $count = 0;
    $fh = fopen($path, 'r'); if (!$fh) return 0;
    $header = fgetcsv($fh, 0, ';');
    if (!$header || count($header) < 2) { rewind($fh); $header = fgetcsv($fh, 0, ','); }
    if (!$header) return 0;
    $norm = array_map(fn($h) => strtolower(trim((string)$h)), $header);
    $sep = in_array('name', $norm, true) || in_array('gerät', $norm, true) ? null : null;
    while (($row = fgetcsv($fh, 0, ';')) !== false) {
        if (count($row) < 2 && isset($row[0])) { $row = str_getcsv($row[0], ','); }
        $assoc = [];
        foreach ($norm as $i => $key) $assoc[$key] = $row[$i] ?? '';
        $name = $assoc['name'] ?? $assoc['gerät'] ?? $assoc['geraet'] ?? $row[0] ?? '';
        $data = [
            'name' => $name,
            'brand' => $assoc['brand'] ?? $assoc['marke'] ?? '',
            'category' => $assoc['category'] ?? $assoc['kategorie'] ?? '',
            'power_w' => $assoc['power_w'] ?? $assoc['leistung'] ?? $assoc['watt'] ?? 0,
            'voltage_v' => $assoc['voltage_v'] ?? $assoc['spannung'] ?? 230,
            'connector' => $assoc['connector'] ?? $assoc['anschluss'] ?? '',
            'notes' => $assoc['notes'] ?? $assoc['notizen'] ?? '',
        ];
        $d = normalize_device($data);
        if ($d['name'] === '') continue;
        $stmt = $pdo->prepare('INSERT INTO devices (user_id, name, brand, category, power_w, voltage_v, connector, notes, deleted_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL)');
        $stmt->execute([(int)$user['id'], $d['name'], $d['brand'], $d['category'], $d['power_w'], $d['voltage_v'], $d['connector'], $d['notes']]);
        $count++;
    }
    fclose($fh);
    return $count;
}

if ($method === 'GET') {
    $trash = isset($_GET['trash']);
    $where = $trash ? 'deleted_at IS NOT NULL' : 'deleted_at IS NULL';
    $stmt = $pdo->prepare("SELECT id, user_id, name, brand, category, power_w, voltage_v, connector, notes, deleted_at FROM devices WHERE {$where} ORDER BY brand, name");
    $stmt->execute();
    $devices = $stmt->fetchAll();
    if (isset($_GET['export'])) {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="devices-export.json"');
        echo json_encode(['devices' => $devices], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    json_response($devices);
}

if ($method === 'POST' && isset($_GET['csv_import'])) {
    if (empty($_FILES['csv_file']['tmp_name'])) json_response(['error' => 'CSV-Datei fehlt.'], 422);
    $count = import_csv_devices($_FILES['csv_file']['tmp_name']);
    json_response(['imported' => $count]);
}

$data = request_json();

if ($method === 'POST' && isset($_GET['restore'])) {
    $id = (int)($data['id'] ?? $_GET['id'] ?? 0);
    if ($id <= 0) json_response(['error' => 'ID fehlt.'], 422);
    $pdo->prepare('UPDATE devices SET deleted_at = NULL WHERE id = ?')->execute([$id]);
    json_response(['ok' => true]);
}

if ($method === 'POST' && isset($_GET['import'])) {
    $items = isset($data[0]) ? $data : ($data['devices'] ?? []);
    if (!is_array($items)) json_response(['error' => 'Import-Datei ist ungültig.'], 422);
    $count = 0;
    foreach ($items as $item) {
        if (!is_array($item)) continue;
        $d = normalize_device($item);
        if ($d['name'] === '') continue;
        $stmt = $pdo->prepare('INSERT INTO devices (user_id, name, brand, category, power_w, voltage_v, connector, notes, deleted_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL)');
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
        $stmt = $pdo->prepare('UPDATE devices SET name = ?, brand = ?, category = ?, power_w = ?, voltage_v = ?, connector = ?, notes = ?, deleted_at = NULL WHERE id = ?');
        $stmt->execute([$d['name'], $d['brand'], $d['category'], $d['power_w'], $d['voltage_v'], $d['connector'], $d['notes'], $id]);
        if ($stmt->rowCount() === 0) {
            $check = $pdo->prepare('SELECT id FROM devices WHERE id = ?');
            $check->execute([$id]);
            if (!$check->fetch()) json_response(['error' => 'Gerät nicht gefunden.'], 404);
        }
    } else {
        $stmt = $pdo->prepare('INSERT INTO devices (user_id, name, brand, category, power_w, voltage_v, connector, notes, deleted_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL)');
        $stmt->execute([(int)$user['id'], $d['name'], $d['brand'], $d['category'], $d['power_w'], $d['voltage_v'], $d['connector'], $d['notes']]);
        $id = (int)$pdo->lastInsertId();
    }
    json_response(['ok' => true, 'id' => $id]);
}

if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) json_response(['error' => 'ID fehlt.'], 422);
    if (isset($_GET['purge'])) {
        $stmt = $pdo->prepare('DELETE FROM devices WHERE id = ? AND deleted_at IS NOT NULL');
    } else {
        $stmt = $pdo->prepare('UPDATE devices SET deleted_at = NOW() WHERE id = ?');
    }
    $stmt->execute([$id]);
    json_response(['ok' => true]);
}

json_response(['error' => 'Methode nicht erlaubt.'], 405);
