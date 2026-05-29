<?php
if (!defined('APP_GITHUB_URL')) { define('APP_GITHUB_URL', 'https://github.com/Wiwaltill/power-planner/'); }
if (!defined('APP_VERSION')) { define('APP_VERSION', '1.3.3'); }
function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function json_response($data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function request_json(): array {
    $data = json_decode(file_get_contents('php://input'), true);
    return is_array($data) ? $data : [];
}
function user_project(int $projectId, int $userId): ?array {
    $stmt = db()->prepare('SELECT p.*, u.name AS owner_name, u.email AS owner_email, CASE WHEN p.user_id = ? THEN 1 ELSE 0 END AS is_owner FROM projects p JOIN users u ON u.id = p.user_id LEFT JOIN project_shares ps ON ps.project_id = p.id AND ps.user_id = ? WHERE p.id = ? AND (p.user_id = ? OR ps.user_id IS NOT NULL) LIMIT 1');
    $stmt->execute([$userId, $userId, $projectId, $userId]);
    return $stmt->fetch() ?: null;
}
function user_owns_project(int $projectId, int $userId): bool {
    $stmt = db()->prepare('SELECT COUNT(*) FROM projects WHERE id = ? AND user_id = ?');
    $stmt->execute([$projectId, $userId]);
    return (int)$stmt->fetchColumn() > 0;
}
function setting_get(string $key, string $default = ''): string {
    try {
        ensure_schema();
        $stmt = db()->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $value === false ? $default : (string)$value;
    } catch (Throwable $e) {
        return $default;
    }
}
function setting_set(string $key, string $value): void {
    $stmt = db()->prepare('INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    $stmt->execute([$key, $value]);
}
function upload_company_logo(array $file): string {
    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Keine Datei hochgeladen.');
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Logo konnte nicht hochgeladen werden.');
    }
    if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
        throw new RuntimeException('Das Logo darf maximal 2 MB groß sein.');
    }
    $info = @getimagesize($file['tmp_name']);
    if (!$info || empty($info['mime'])) {
        throw new RuntimeException('Bitte eine Bilddatei hochladen.');
    }
    $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    if (!isset($allowed[$info['mime']])) {
        throw new RuntimeException('Erlaubt sind PNG, JPG, GIF und WebP.');
    }
    $uploadDir = __DIR__ . '/../uploads';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $filename = 'company-logo.' . $allowed[$info['mime']];
    $target = $uploadDir . '/' . $filename;
    foreach (glob($uploadDir . '/company-logo.*') ?: [] as $old) {
        if ($old !== $target) @unlink($old);
    }
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new RuntimeException('Logo konnte nicht gespeichert werden.');
    }
    return 'uploads/' . $filename;
}

function generate_share_token(): string {
    return bin2hex(random_bytes(24));
}
function public_project_by_token(string $token): ?array {
    if ($token === '') return null;
    ensure_schema();
    $stmt = db()->prepare('SELECT p.*, u.name AS owner_name, u.email AS owner_email FROM projects p JOIN users u ON u.id = p.user_id WHERE p.public_share_enabled = 1 AND p.public_share_token = ? LIMIT 1');
    $stmt->execute([$token]);
    return $stmt->fetch() ?: null;
}
