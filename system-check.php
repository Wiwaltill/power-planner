<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/helpers.php';
$user = require_admin();
ensure_schema();
function check_row(string $label, bool $ok, string $info = ''): array { return ['label'=>$label,'ok'=>$ok,'info'=>$info]; }
$root = __DIR__;
$checks = [];
$checks[] = check_row('PHP-Version >= 8.0', version_compare(PHP_VERSION, '8.0.0', '>='), PHP_VERSION);
$checks[] = check_row('PDO MySQL verfügbar', extension_loaded('pdo_mysql'));
$checks[] = check_row('cURL verfügbar', function_exists('curl_init'), function_exists('curl_init') ? 'OK' : 'Für GitHub-Updater empfohlen');
$checks[] = check_row('ZipArchive verfügbar', class_exists('ZipArchive'), class_exists('ZipArchive') ? 'OK' : 'Für Backups/Updater erforderlich');
$checks[] = check_row('JSON verfügbar', function_exists('json_encode'));
$checks[] = check_row('Uploads beschreibbar', is_dir($root.'/uploads') ? is_writable($root.'/uploads') : is_writable($root), 'uploads/');
$checks[] = check_row('Backups beschreibbar', is_dir($root.'/backups') ? is_writable($root.'/backups') : is_writable($root), 'backups/');
$checks[] = check_row('Config vorhanden', is_file($root.'/config/config.php'), 'config/config.php');
$release = latest_release_cached(true);
if ($release && !empty($release['tag_name'])) {
    $latest = (string)$release['tag_name'];
    $checks[] = check_row('App-Version', true, 'Installiert: ' . APP_VERSION . ' · GitHub: ' . $latest . (app_version_is_newer($latest, APP_VERSION) ? ' · Update verfügbar' : ' · aktuell'));
} else {
    $checks[] = check_row('GitHub-Version prüfbar', false, 'Release konnte nicht abgefragt werden. Prüfe Serverzugriff auf api.github.com, PHP-cURL und ausgehende HTTPS-Verbindungen.');
}
try { db()->query('SELECT 1'); $dbOk = true; $dbInfo = 'Verbindung OK'; } catch (Throwable $e) { $dbOk = false; $dbInfo = $e->getMessage(); }
$checks[] = check_row('MySQL-Verbindung', $dbOk, $dbInfo);
try { $m = db()->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn(); $checks[] = check_row('Migrationssystem', true, $m . ' Migration(en) registriert'); } catch (Throwable $e) { $checks[] = check_row('Migrationssystem', false, $e->getMessage()); }
$pageTitle = 'Systemcheck';
$activePage = 'settings';
require __DIR__ . '/inc/header.php';
?>
<main class="container py-4 flex-grow-1">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div><h1 class="h3 mb-1">Systemcheck</h1><div class="small-muted">Prüft Server, Schreibrechte und Update-Voraussetzungen.</div></div>
    <a class="btn btn-outline-secondary" href="<?= e(app_url('settings')) ?>">Zurück</a>
  </div>
  <div class="card p-4"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Prüfung</th><th>Status</th><th>Info</th></tr></thead><tbody>
  <?php foreach ($checks as $check): ?>
    <tr><td><?= e($check['label']) ?></td><td><?= $check['ok'] ? '<span class="badge text-bg-success">OK</span>' : '<span class="badge text-bg-danger">Fehler</span>' ?></td><td class="text-muted"><?= e($check['info']) ?></td></tr>
  <?php endforeach; ?>
  </tbody></table></div></div>
</main>
<?php require __DIR__ . '/inc/footer.php'; ?>
