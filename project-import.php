<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/helpers.php';
$user = require_login();
ensure_schema();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: projects');
    exit;
}

if (empty($_FILES['project_file']['tmp_name']) || ($_FILES['project_file']['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
    header('Location: projects?import_error=upload');
    exit;
}

$raw = file_get_contents($_FILES['project_file']['tmp_name']);
$data = json_decode($raw, true);
if (!is_array($data) || ($data['schema'] ?? '') !== 'power-planner-project' || empty($data['project']) || !is_array($data['project'])) {
    header('Location: projects?import_error=format');
    exit;
}

$pdo = db();
$pdo->beginTransaction();
try {
    $project = $data['project'];
    $name = trim((string)($project['name'] ?? 'Importiertes Projekt'));
    if ($name === '') $name = 'Importiertes Projekt';
    $name .= ' (Import)';

    $stmt = $pdo->prepare('INSERT INTO projects (user_id, name, client, technician, public_share_enabled) VALUES (?, ?, ?, ?, 0)');
    $stmt->execute([
        (int)$user['id'],
        $name,
        trim((string)($project['client'] ?? '')),
        trim((string)($project['technician'] ?? ($user['name'] ?? ''))),
    ]);
    $newProjectId = (int)$pdo->lastInsertId();

    $circuitMap = [];
    foreach (($data['circuits'] ?? []) as $circuit) {
        if (!is_array($circuit)) continue;
        $oldId = (int)($circuit['id'] ?? 0);
        $circuitName = trim((string)($circuit['name'] ?? 'Stromkreis')) ?: 'Stromkreis';
        $ampLimit = is_numeric($circuit['amp_limit'] ?? null) ? (float)$circuit['amp_limit'] : 16.00;
        $stmt = $pdo->prepare('INSERT INTO circuits (project_id, name, amp_limit) VALUES (?, ?, ?)');
        $stmt->execute([$newProjectId, $circuitName, $ampLimit]);
        if ($oldId > 0) $circuitMap[$oldId] = (int)$pdo->lastInsertId();
    }

    if (!$circuitMap) {
        $stmt = $pdo->prepare('INSERT INTO circuits (project_id, name, amp_limit) VALUES (?, ?, 16.00)');
        $stmt->execute([$newProjectId, 'Standard-Stromkreis']);
        $defaultCircuitId = (int)$pdo->lastInsertId();
    } else {
        $defaultCircuitId = (int)reset($circuitMap);
    }

    foreach (($data['plan_items'] ?? []) as $item) {
        if (!is_array($item)) continue;
        $oldCircuitId = (int)($item['circuit_id'] ?? 0);
        $newCircuitId = $circuitMap[$oldCircuitId] ?? $defaultCircuitId;
        $phase = strtoupper((string)($item['phase'] ?? 'L1'));
        if (!in_array($phase, ['L1', 'L2', 'L3'], true)) $phase = 'L1';
        $stmt = $pdo->prepare('INSERT INTO plan_items (project_id, circuit_id, device_id, name, brand, category, quantity, phase, power_w, voltage_v, remarks) VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $newProjectId,
            $newCircuitId,
            trim((string)($item['name'] ?? 'Gerät')) ?: 'Gerät',
            trim((string)($item['brand'] ?? '')),
            trim((string)($item['category'] ?? '')),
            max(1, (int)($item['quantity'] ?? 1)),
            $phase,
            max(0, (int)($item['power_w'] ?? 0)),
            max(1, (int)($item['voltage_v'] ?? 230)),
            (string)($item['remarks'] ?? ''),
        ]);
    }

    foreach (($data['shares'] ?? []) as $share) {
        if (!is_array($share) || empty($share['email'])) continue;
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND active = 1 LIMIT 1');
        $stmt->execute([(string)$share['email']]);
        $shareUserId = (int)$stmt->fetchColumn();
        if ($shareUserId > 0 && $shareUserId !== (int)$user['id']) {
            $stmt = $pdo->prepare('INSERT INTO project_shares (project_id, user_id, permission) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE permission = VALUES(permission)');
            $stmt->execute([$newProjectId, $shareUserId]);
        }
    }

    $pdo->commit();
    header('Location: project?id=' . $newProjectId . '&imported=1');
    exit;
} catch (Throwable $e) {
    $pdo->rollBack();
    header('Location: projects?import_error=1');
    exit;
}
