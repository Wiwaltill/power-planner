<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['project_file'])) {
    $data = json_decode(file_get_contents($_FILES['project_file']['tmp_name']), true);
    if (!$data || empty($data['project'])) die('Invalid file');

    $p = $data['project'];
    $stmt = $pdo->prepare("INSERT INTO projects (user_id,name,client,technician) VALUES (?,?,?,?)");
    $stmt->execute([$_SESSION['user_id'], $p['name'].' (Import)', $p['client'] ?? '', $p['technician'] ?? '']);
    $newProjectId = $pdo->lastInsertId();

    $circuitMap = [];
    foreach (($data['circuits'] ?? []) as $c) {
        $stmt = $pdo->prepare("INSERT INTO circuits (project_id,name,amp_limit) VALUES (?,?,?)");
        $stmt->execute([$newProjectId, $c['name'], $c['amp_limit'] ?? 16]);
        $circuitMap[$c['id']] = $pdo->lastInsertId();
    }

    foreach (($data['plan_items'] ?? []) as $i) {
        $stmt = $pdo->prepare("INSERT INTO plan_items (project_id,circuit_id,device_id,name,brand,category,quantity,phase,power_w,voltage_v,remarks)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $newProjectId,
            $circuitMap[$i['circuit_id']] ?? array_values($circuitMap)[0],
            null,
            $i['name'],$i['brand'],$i['category'],$i['quantity'],
            $i['phase'],$i['power_w'],$i['voltage_v'],$i['remarks']
        ]);
    }
    header('Location: projects.php');
    exit;
}
?>
<form method="post" enctype="multipart/form-data">
<input type="file" name="project_file" required>
<button type="submit">Projekt importieren</button>
</form>
