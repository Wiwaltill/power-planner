<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/helpers.php';
$user = require_login();
if (($user['role'] ?? '') !== 'admin') { http_response_code(403); die('Zugriff verweigert'); }
ensure_schema();

$message = $_SESSION['flash_message'] ?? '';
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_error']);

function updater_http_get(string $url, array $headers = [], int $timeout = 20): string {
    $defaultHeaders = [
        'User-Agent: PowerPlanner-Updater/1.3.5',
        'Accept: application/vnd.github+json, application/json, text/html;q=0.8',
        'X-GitHub-Api-Version: 2022-11-28'
    ];
    $headers = array_values(array_unique(array_merge($defaultHeaders, $headers)));

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body !== false && $status >= 200 && $status < 300) return $body;
        throw new RuntimeException('GitHub konnte nicht abgefragt werden. HTTP-Status: ' . ($status ?: 'unbekannt') . ($err ? ' / ' . $err : ''));
    }

    if (!ini_get('allow_url_fopen')) {
        throw new RuntimeException('GitHub konnte nicht abgefragt werden: cURL fehlt und allow_url_fopen ist deaktiviert. Bitte PHP-cURL aktivieren.');
    }

    $ctx = stream_context_create(['http' => [
        'method' => 'GET',
        'header' => implode("\r\n", $headers) . "\r\n",
        'timeout' => $timeout,
        'ignore_errors' => true,
    ]]);
    $body = @file_get_contents($url, false, $ctx);
    $status = 0;
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $h) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', $h, $m)) { $status = (int)$m[1]; break; }
        }
    }
    if ($body !== false && $status >= 200 && $status < 300) return $body;
    throw new RuntimeException('GitHub konnte nicht abgefragt werden. HTTP-Status: ' . ($status ?: 'unbekannt'));
}


function updater_http_download(string $url, string $target, int $timeout = 120): void {
    $headers = [
        'User-Agent: PowerPlanner-Updater/1.3.5',
        'Accept: application/octet-stream, application/zip, application/x-zip-compressed, */*'
    ];

    if (function_exists('curl_init')) {
        $fp = fopen($target, 'wb');
        if (!$fp) throw new RuntimeException('Temporäre Update-Datei konnte nicht erstellt werden.');
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_USERAGENT => 'PowerPlanner-Updater/1.3.5',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $ok = curl_exec($ch);
        $err = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $type = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        fclose($fp);
        clearstatcache(true, $target);
        if ($ok && $status >= 200 && $status < 300 && is_file($target) && filesize($target) > 1000) {
            return;
        }
        @unlink($target);
        throw new RuntimeException('Release-ZIP konnte nicht geladen werden. HTTP-Status: ' . ($status ?: 'unbekannt') . ($type ? ' / Content-Type: ' . $type : '') . ($err ? ' / ' . $err : ''));
    }

    if (!ini_get('allow_url_fopen')) {
        throw new RuntimeException('Release-ZIP konnte nicht geladen werden: cURL fehlt und allow_url_fopen ist deaktiviert. Bitte PHP-cURL aktivieren.');
    }
    $ctx = stream_context_create(['http' => [
        'method' => 'GET',
        'header' => implode("\r\n", $headers) . "\r\n",
        'timeout' => $timeout,
        'ignore_errors' => true,
        'follow_location' => 1,
        'max_redirects' => 10,
    ]]);
    $data = @file_get_contents($url, false, $ctx);
    $status = 0;
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $h) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', $h, $m)) { $status = (int)$m[1]; }
        }
    }
    if ($data === false || strlen($data) < 1000 || ($status && ($status < 200 || $status >= 300))) {
        throw new RuntimeException('Release-ZIP konnte nicht geladen werden. HTTP-Status: ' . ($status ?: 'unbekannt'));
    }
    file_put_contents($target, $data);
}

function updater_github_latest(): array {
    $url = 'https://api.github.com/repos/Wiwaltill/power-planner/releases/latest';
    $json = updater_http_get($url, [], 20);
    $data = json_decode($json, true);
    if (!is_array($data) || empty($data['tag_name'])) {
        $msg = is_array($data) && !empty($data['message']) ? ': ' . $data['message'] : '';
        throw new RuntimeException('Ungültige GitHub-Antwort' . $msg . '.');
    }
    return $data;
}
function updater_version_clean(string $v): string { return ltrim(trim($v), 'vV'); }
function updater_is_newer(string $remote, string $local): bool {
    return version_compare(updater_version_clean($remote), updater_version_clean($local), '>');
}
function updater_download_url(array $release): string {
    foreach (($release['assets'] ?? []) as $asset) {
        if (!empty($asset['browser_download_url']) && preg_match('/\.zip$/i', $asset['name'] ?? '')) {
            return $asset['browser_download_url'];
        }
    }
    if (!empty($release['zipball_url'])) return $release['zipball_url'];
    throw new RuntimeException('Kein ZIP-Download im GitHub Release gefunden.');
}
function updater_starts_with(string $haystack, string $needle): bool { return $needle === '' || substr($haystack, 0, strlen($needle)) === $needle; }
function updater_copy_dir(string $source, string $target, array $skip): void {
    $source = rtrim($source, '/');
    $target = rtrim($target, '/');
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($it as $item) {
        $rel = str_replace('\\', '/', substr($item->getPathname(), strlen($source) + 1));
        foreach ($skip as $prefix) {
            if ($rel === $prefix || updater_starts_with($rel, rtrim($prefix, '/') . '/')) continue 2;
        }
        $dest = $target . '/' . $rel;
        if ($item->isDir()) {
            if (!is_dir($dest)) mkdir($dest, 0755, true);
        } else {
            if (!is_dir(dirname($dest))) mkdir(dirname($dest), 0755, true);
            copy($item->getPathname(), $dest);
        }
    }
}
function updater_zip_current(string $file): void {
    $zip = new ZipArchive();
    if ($zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Update-Backup konnte nicht erstellt werden.');
    $root = __DIR__;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $item) {
        $rel = str_replace('\\', '/', substr($item->getPathname(), strlen($root) + 1));
        if (updater_starts_with($rel, 'backups/') || updater_starts_with($rel, 'uploads/') || $rel === 'config/config.php') continue;
        if ($item->isFile()) $zip->addFile($item->getPathname(), $rel);
    }
    $zip->close();
}
function updater_apply(): string {
    if (!class_exists('ZipArchive')) throw new RuntimeException('PHP ZipArchive ist nicht verfügbar.');
    $release = updater_github_latest();
    if (!updater_is_newer($release['tag_name'], APP_VERSION)) return 'Es ist bereits die aktuelle Version installiert.';
    $url = updater_download_url($release);
    $tmp = sys_get_temp_dir() . '/power-planner-update-' . time();
    if (!is_dir($tmp)) mkdir($tmp, 0755, true);
    $zipFile = $tmp . '/release.zip';
    updater_http_download($url, $zipFile, 120);
    $backupDir = __DIR__ . '/backups';
    if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);
    updater_zip_current($backupDir . '/pre-update-' . date('Ymd-His') . '.zip');
    $zip = new ZipArchive();
    if ($zip->open($zipFile) !== true) throw new RuntimeException('Release-ZIP konnte nicht geöffnet werden.');
    $extract = $tmp . '/extract';
    mkdir($extract, 0755, true);
    $zip->extractTo($extract);
    $zip->close();
    $entries = array_values(array_filter(glob($extract . '/*'), 'is_dir'));
    $source = count($entries) === 1 ? $entries[0] : $extract;
    updater_copy_dir($source, __DIR__, ['config/config.php', 'uploads', 'backups', 'README.md', 'readme.md']);
    return 'Update auf ' . $release['tag_name'] . ' wurde installiert.';
}

$latest = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (($_POST['action'] ?? '') === 'apply_update') {
            $_SESSION['flash_message'] = updater_apply();
            header('Location: updater'); exit;
        }
    } catch (Throwable $e) { $error = $e->getMessage(); }
}
try { $latest = updater_github_latest(); } catch (Throwable $e) { $error = $error ?: $e->getMessage(); }

$pageTitle = 'Updater';
$activePage = 'settings';
require __DIR__ . '/inc/header.php';
?>
<main class="container py-4 flex-grow-1">
  <div class="mb-4">
    <h1 class="h3 mb-1"><i class="bi bi-cloud-arrow-down me-2"></i>Updater</h1>
    <div class="small-muted">System über GitHub Releases aktualisieren.</div>
  </div>
  <?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
  <div class="card p-4">
    <div class="row g-3 align-items-center">
      <div class="col-md-4"><strong>Installierte Version</strong><br><span class="badge text-bg-secondary">v<?= e(APP_VERSION) ?></span></div>
      <div class="col-md-4"><strong>Neuester GitHub Release</strong><br><?php if ($latest): ?><span class="badge text-bg-primary"><?= e($latest['tag_name']) ?></span><?php else: ?><span class="text-muted">nicht abrufbar</span><?php endif; ?></div>
      <div class="col-md-4 text-md-end">
        <?php if ($latest && updater_is_newer($latest['tag_name'], APP_VERSION)): ?>
          <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#updateModal"><i class="bi bi-cloud-download me-1"></i>Update installieren</button>
        <?php else: ?>
          <button class="btn btn-outline-secondary" disabled>Kein Update verfügbar</button>
        <?php endif; ?>
      </div>
    </div>
    <?php if ($latest && !empty($latest['html_url'])): ?><hr><a href="<?= e($latest['html_url']) ?>" target="_blank" rel="noopener">Release auf GitHub ansehen</a><?php endif; ?>
  </div>
</main>
<div class="modal fade" id="updateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="action" value="apply_update">
        <div class="modal-header"><h5 class="modal-title">Update installieren?</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="alert alert-warning small">Vor dem Update wird automatisch ein Datei-Backup unter <code>/backups</code> erstellt. <code>config/config.php</code>, Uploads und lokale Backups werden nicht überschrieben.</div>
          <p class="mb-0">Update von <strong>v<?= e(APP_VERSION) ?></strong> auf <strong><?= e($latest['tag_name'] ?? '') ?></strong> installieren?</p>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Abbrechen</button><button class="btn btn-success">Update installieren</button></div>
      </form>
    </div>
  </div>
</div>
<?php require __DIR__ . '/inc/footer.php'; ?>
