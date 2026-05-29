<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/helpers.php';
$user = require_login();
if (($user['role'] ?? '') !== 'admin') { header('Location: profile'); exit; }
ensure_schema();
$message = $_SESSION['flash_message'] ?? '';
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_error']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'upload_logo') {
        try {
            if (empty($_FILES['company_logo']['name'])) {
                throw new RuntimeException('Bitte ein Logo auswählen.');
            }
            $path = upload_company_logo($_FILES['company_logo']);
            setting_set('company_logo', $path);
            $message = 'Firmenlogo wurde gespeichert.';
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
    if ($action === 'delete_logo') {
        $currentLogo = setting_get('company_logo');
        if ($currentLogo) @unlink(__DIR__ . '/' . $currentLogo);
        setting_set('company_logo', '');
        $message = 'Firmenlogo wurde entfernt.';
    }
}
$currentLogo = setting_get('company_logo');
$pageTitle = 'Einstellungen';
$activePage = 'settings';
$pageScript = 'assets/js/settings.js';
require __DIR__ . '/inc/header.php';
?>
<main class="container py-4 flex-grow-1">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h1 class="h3 mb-1">Einstellungen</h1>
      <div class="small-muted">Marken, Kategorien, Anschlüsse und Firmenlogo verwalten.</div>
    </div>
  </div>
  <?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
  <div class="row g-4">
    <div class="col-12">
      <div class="card p-4 border-primary border-2">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-center">
          <div>
            <h2 class="h4 mb-1"><i class="bi bi-shield-check me-2"></i>Backup</h2>
            <p class="small text-muted mb-0">Alle Projekte, Geräte, Nutzer, Einstellungen und Uploads als ZIP sichern oder wiederherstellen.</p>
          </div>
          <div class="d-flex flex-column flex-sm-row gap-2">
            <a class="btn btn-success" href="<?= e(app_url('backup')) ?>"><i class="bi bi-download me-1"></i>Backup herunterladen</a>
            <button class="btn btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#restoreBackupModal"><i class="bi bi-upload me-1"></i>Backup wiederherstellen</button>
          </div>
        </div>
      </div>
    </div>
    <div class="col-12">
      <div class="card p-4">
        <h2 class="h4">Firmenlogo</h2>
        <p class="small text-muted mb-3">Dieses Logo wird zentral gespeichert und im PDF-Export angezeigt.</p>
        <?php if ($currentLogo): ?>
          <div class="mb-3">
            <img src="<?= e(app_url($currentLogo)) ?>" alt="Firmenlogo" style="max-height:32px;max-width:260px" class="border rounded p-2 bg-white">
          </div>
        <?php else: ?>
          <p class="text-muted">Noch kein Firmenlogo hinterlegt.</p>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data" class="row g-3 align-items-end">
          <input type="hidden" name="action" value="upload_logo">
          <div class="col-lg-8"><label class="form-label">Logo hochladen</label><input type="file" name="company_logo" class="form-control" accept="image/png,image/jpeg,image/gif,image/webp" required></div>
          <div class="col-lg-4"><button class="btn btn-primary w-100">Logo speichern</button></div>
        </form>
        <?php if ($currentLogo): ?>
          <form method="post" class="mt-3">
            <input type="hidden" name="action" value="delete_logo">
            <button class="btn btn-outline-danger btn-sm" type="submit">Logo entfernen</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card p-4">
        <h2 class="h4">Marken</h2>
        <form class="input-group mb-3" data-form="brands">
          <input class="form-control" placeholder="Neue Marke" required>
          <button class="btn btn-primary">Hinzufügen</button>
        </form>
        <div id="brandRows" class="list-group"></div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card p-4">
        <h2 class="h4">Kategorien</h2>
        <form class="input-group mb-3" data-form="categories">
          <input class="form-control" placeholder="Neue Kategorie" required>
          <button class="btn btn-primary">Hinzufügen</button>
        </form>
        <div id="categoryRows" class="list-group"></div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card p-4">
        <h2 class="h4">Anschlüsse</h2>
        <form class="input-group mb-3" data-form="connectors">
          <input class="form-control" placeholder="Neuer Anschluss" required>
          <button class="btn btn-primary">Hinzufügen</button>
        </form>
        <div id="connectorRows" class="list-group"></div>
      </div>
    </div>
  </div>
</main>

<div class="modal fade" id="restoreBackupModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="post" action="<?= e(app_url('backup')) ?>" enctype="multipart/form-data">
        <div class="modal-header">
          <h5 class="modal-title">Backup wiederherstellen</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-warning small mb-3">Die aktuellen Daten werden durch das Backup ersetzt.</div>
          <label class="form-label">Backup-ZIP auswählen</label>
          <input type="file" name="backup_file" class="form-control" accept=".zip,application/zip" required>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Abbrechen</button>
          <button type="submit" class="btn btn-danger">Wiederherstellen</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/inc/footer.php'; ?>
