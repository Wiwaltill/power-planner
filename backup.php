<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/helpers.php';
$user = require_login();
if (($user['role'] ?? '') !== 'admin') { http_response_code(403); echo 'Zugriff verweigert'; exit; }
ensure_schema();
$pdo = db();

$backupTables = [
    'users',
    'device_brands',
    'device_categories',
    'device_connectors',
    'devices',
    'projects',
    'circuits',
    'plan_items',
    'app_settings',
];

function backup_table_exists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function export_backup(PDO $pdo, array $tables): void {
    if (!class_exists('ZipArchive')) {
        http_response_code(500);
        echo 'PHP ZipArchive ist nicht verfügbar.';
        exit;
    }

    $payload = [
        'app' => APP_NAME,
        'version' => APP_VERSION,
        'created_at' => date('c'),
        'tables' => [],
        'uploads' => [],
    ];

    foreach ($tables as $table) {
        if (!backup_table_exists($pdo, $table)) continue;
        $stmt = $pdo->query("SELECT * FROM `{$table}`");
        $payload['tables'][$table] = $stmt->fetchAll();
    }

    $tmp = tempnam(sys_get_temp_dir(), 'stromplaner_backup_');
    $zipPath = $tmp . '.zip';
    @unlink($tmp);
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        http_response_code(500);
        echo 'Backup-ZIP konnte nicht erstellt werden.';
        exit;
    }

    $zip->addFromString('backup.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    $uploadDir = __DIR__ . '/uploads';
    if (is_dir($uploadDir)) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploadDir, FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if (!$file->isFile()) continue;
            $relative = 'uploads/' . substr(str_replace('\\', '/', $file->getPathname()), strlen(str_replace('\\', '/', $uploadDir)) + 1);
            $zip->addFile($file->getPathname(), $relative);
        }
    }

    $zip->close();

    $filename = 'stromplaner-backup-' . date('Y-m-d-H-i-s') . '.zip';
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($zipPath));
    header('Cache-Control: no-store, no-cache, must-revalidate');
    readfile($zipPath);
    @unlink($zipPath);
    exit;
}

function restore_backup(PDO $pdo, array $tables, array $file): array {
    if (!class_exists('ZipArchive')) {
        return ['ok' => false, 'message' => 'PHP ZipArchive ist nicht verfügbar.'];
    }
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
        return ['ok' => false, 'message' => 'Bitte eine Backup-ZIP auswählen.'];
    }

    $zip = new ZipArchive();
    if ($zip->open($file['tmp_name']) !== true) {
        return ['ok' => false, 'message' => 'Backup-ZIP konnte nicht geöffnet werden.'];
    }

    $json = $zip->getFromName('backup.json');
    if ($json === false) {
        $zip->close();
        return ['ok' => false, 'message' => 'backup.json fehlt im Archiv.'];
    }
    $payload = json_decode($json, true);
    if (!is_array($payload) || empty($payload['tables']) || !is_array($payload['tables'])) {
        $zip->close();
        return ['ok' => false, 'message' => 'Ungültiges Backup-Format.'];
    }

    $pdo->beginTransaction();
    try {
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach (array_reverse($tables) as $table) {
            if (backup_table_exists($pdo, $table)) $pdo->exec("DELETE FROM `{$table}`");
        }

        foreach ($tables as $table) {
            if (empty($payload['tables'][$table]) || !is_array($payload['tables'][$table]) || !backup_table_exists($pdo, $table)) continue;
            foreach ($payload['tables'][$table] as $row) {
                if (!is_array($row) || !$row) continue;
                $cols = array_keys($row);
                $colSql = implode(',', array_map(fn($c) => '`' . str_replace('`', '``', $c) . '`', $cols));
                $placeholders = implode(',', array_fill(0, count($cols), '?'));
                $stmt = $pdo->prepare("INSERT INTO `{$table}` ({$colSql}) VALUES ({$placeholders})");
                $stmt->execute(array_values($row));
            }
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        try { $pdo->exec('SET FOREIGN_KEY_CHECKS=1'); } catch (Throwable $ignored) {}
        $zip->close();
        return ['ok' => false, 'message' => 'Wiederherstellung fehlgeschlagen: ' . $e->getMessage()];
    }

    $uploadDir = __DIR__ . '/uploads';
    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (!$name || strpos($name, 'uploads/') !== 0 || substr($name, -1) === '/') continue;
        $safeName = str_replace(['..', '\\'], ['', '/'], $name);
        $target = __DIR__ . '/' . $safeName;
        $targetDir = dirname($target);
        if (!is_dir($targetDir)) @mkdir($targetDir, 0755, true);
        copy('zip://' . $file['tmp_name'] . '#' . $name, $target);
    }
    $zip->close();

    return ['ok' => true, 'message' => 'Backup wurde wiederhergestellt. Bitte erneut anmelden, falls sich Nutzerdaten geändert haben.'];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    export_backup($pdo, $backupTables);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = restore_backup($pdo, $backupTables, $_FILES['backup_file'] ?? []);
    $_SESSION['flash_' . ($result['ok'] ? 'message' : 'error')] = $result['message'];
    header('Location: settings');
    exit;
}

http_response_code(405);
echo 'Methode nicht erlaubt';
