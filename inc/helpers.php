<?php
if (!defined('APP_GITHUB_URL')) { define('APP_GITHUB_URL', 'https://github.com/Wiwaltill/power-planner/'); }
if (!defined('APP_VERSION')) { define('APP_VERSION', '1.4.0'); }
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
function user_project(int $projectId, int $userId, bool $includeDeleted = false): ?array {
    $deletedSql = $includeDeleted ? '' : ' AND p.deleted_at IS NULL';
    $stmt = db()->prepare('SELECT p.*, u.name AS owner_name, u.email AS owner_email, CASE WHEN p.user_id = ? THEN 1 ELSE 0 END AS is_owner, COALESCE(ps.permission, CASE WHEN p.user_id = ? THEN \'manage\' ELSE NULL END) AS permission FROM projects p JOIN users u ON u.id = p.user_id LEFT JOIN project_shares ps ON ps.project_id = p.id AND ps.user_id = ? WHERE p.id = ? ' . $deletedSql . ' AND (p.user_id = ? OR ps.user_id IS NOT NULL) LIMIT 1');
    $stmt->execute([$userId, $userId, $userId, $projectId, $userId]);
    return $stmt->fetch() ?: null;
}
function project_can_edit(array $project): bool {
    return (int)($project['is_owner'] ?? 0) === 1 || in_array(($project['permission'] ?? ''), ['edit','manage'], true);
}
function project_can_manage(array $project): bool {
    return (int)($project['is_owner'] ?? 0) === 1 || ($project['permission'] ?? '') === 'manage';
}
function require_project_access(int $projectId, int $userId, string $level = 'view'): array {
    $project = user_project($projectId, $userId);
    if (!$project) json_response(['error' => 'Projekt nicht gefunden.'], 404);
    if ($level === 'edit' && !project_can_edit($project)) json_response(['error' => 'Nur Leserechte für dieses Projekt.'], 403);
    if ($level === 'manage' && !project_can_manage($project)) json_response(['error' => 'Keine Verwaltungsrechte für dieses Projekt.'], 403);
    return $project;
}
function log_project_activity(int $projectId, int $userId, string $action, string $details = ''): void {
    try {
        ensure_schema();
        $stmt = db()->prepare('INSERT INTO project_activity (project_id, user_id, action, details) VALUES (?, ?, ?, ?)');
        $stmt->execute([$projectId, $userId, $action, $details]);
    } catch (Throwable $e) {}
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


function app_version_clean(string $version): string {
    return ltrim(trim($version), "vV \t\n\r\0\x0B");
}
function app_version_is_newer(string $remote, string $local): bool {
    return version_compare(app_version_clean($remote), app_version_clean($local), '>');
}
function github_repo_from_url(string $url): string {
    $path = trim((string)parse_url($url, PHP_URL_PATH), '/');
    $parts = explode('/', $path);
    if (count($parts) >= 2) return $parts[0] . '/' . $parts[1];
    return 'Wiwaltill/power-planner';
}
function http_get_json_cached(string $url, int $timeout = 10): ?array {
    $headers = [
        'User-Agent: Power-Planner-Updater/' . APP_VERSION,
        'Accept: application/vnd.github+json'
    ];
    $body = false;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false || $code < 200 || $code >= 300) return null;
    } elseif (ini_get('allow_url_fopen')) {
        $context = stream_context_create(['http' => [
            'method' => 'GET',
            'timeout' => $timeout,
            'header' => implode("\r\n", $headers) . "\r\n",
            'ignore_errors' => true,
        ]]);
        $body = @file_get_contents($url, false, $context);
        if ($body === false) return null;
    } else {
        return null;
    }
    $data = json_decode((string)$body, true);
    return is_array($data) ? $data : null;
}
function latest_release_cached(bool $force = false): ?array {
    try {
        ensure_schema();
        $cacheRaw = setting_get('github_latest_release_cache', '');
        if (!$force && $cacheRaw !== '') {
            $cache = json_decode($cacheRaw, true);
            if (is_array($cache) && !empty($cache['checked_at']) && is_array($cache['release'] ?? null)) {
                $age = time() - (int)$cache['checked_at'];
                $cachedRelease = $cache['release'];
                $cachedTag = (string)($cachedRelease['tag_name'] ?? '');

                // Wenn der Cache bereits ein Update zeigt, kann er länger genutzt werden.
                if ($cachedTag !== '' && app_version_is_newer($cachedTag, APP_VERSION) && $age < 3600) {
                    return $cachedRelease;
                }

                // Wenn der Cache "kein Update" sagt, nur sehr kurz cachen.
                // So bleibt der Footer mit der Updater-Seite synchron und hängt nicht 24h auf "Aktuell" fest.
                if ($cachedTag !== '' && !app_version_is_newer($cachedTag, APP_VERSION) && $age < 60) {
                    return $cachedRelease;
                }
            }
        }
        $repo = github_repo_from_url(APP_GITHUB_URL);
        $release = http_get_json_cached('https://api.github.com/repos/' . $repo . '/releases/latest');
        if (!$release || empty($release['tag_name'])) return null;
        setting_set('github_latest_release_cache', json_encode(['checked_at' => time(), 'release' => $release], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return $release;
    } catch (Throwable $e) {
        return null;
    }
}
function available_update_info(): ?array {
    $release = latest_release_cached(false);
    if (!$release || empty($release['tag_name'])) return null;
    if (!app_version_is_newer((string)$release['tag_name'], APP_VERSION)) return null;
    return $release;
}
