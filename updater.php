<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/helpers.php';
$user = require_login();
if (($user['role'] ?? '') !== 'admin') { http_response_code(403); die('Zugriff verweigert'); }
ensure_schema();

$message = $_SESSION['flash_message'] ?? '';
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_error']);

function updater_github_latest(): array {
    $url = 'https://api.github.com/repos/Wiwaltill/power-planner/releases/latest';
    $ctx = stream_context_create(['http' => [
        'method' => 'GET',
        'header' => "User-Agent: PowerPlanner-Updater\r\nAccept: application/vnd.github+json\r\n",
        'timeout' => 15,
    ]]);
    $json = @file_get_contents($url, false, $ctx);
    if ($json === false) throw new RuntimeException('GitHub Release konnte nicht abgefragt werden.');
    $data = json_decode($json, true);
    if (!is_array($data) || empty($data['tag_name'])) throw new RuntimeException('Ungültige GitHub-Antwort.');
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
    $ctx = stream_context_create(['http' => ['header' => "User-Agent: PowerPlanner-Updater\r\n", 'timeout' => 60]]);
    $data = @file_get_contents($url, false, $ctx);
    if ($data === false || strlen($data) < 1000) throw new RuntimeException('Release-ZIP konnte nicht geladen werden.');
    file_put_contents($zipFile, $data);
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
