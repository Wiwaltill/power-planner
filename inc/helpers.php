<?php
if (!defined('APP_GITHUB_URL')) { define('APP_GITHUB_URL', 'https://github.com/Wiwaltill/power-planner/'); }
if (!defined('APP_VERSION')) { define('APP_VERSION', '1.1.0'); }
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
    $stmt = db()->prepare('SELECT * FROM projects WHERE id = ? AND user_id = ?');
    $stmt->execute([$projectId, $userId]);
    return $stmt->fetch() ?: null;
}
