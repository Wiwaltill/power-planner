<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/helpers.php';
$user = require_login();
ensure_schema();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'restore_project') {
        $projectId = (int)($_POST['project_id'] ?? 0);
        $project = user_project($projectId, (int)$user['id'], true);
        if ($project && (int)($project['is_owner'] ?? 0) === 1 && !empty($project['deleted_at'])) {
            db()->prepare('UPDATE projects SET deleted_at = NULL, deleted_by = NULL WHERE id = ? AND user_id = ?')->execute([$projectId, (int)$user['id']]);
            header('Location: trash?project_restored=1'); exit;
        }
        header('Location: trash?project_error=1'); exit;
    }

    if ($action === 'purge_project') {
        $projectId = (int)($_POST['project_id'] ?? 0);
        $confirm = (string)($_POST['confirm_project_name'] ?? '');
        $project = user_project($projectId, (int)$user['id'], true);
        if ($project && (int)($project['is_owner'] ?? 0) === 1 && !empty($project['deleted_at']) && hash_equals((string)$project['name'], $confirm)) {
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $pdo->prepare('DELETE FROM plan_items WHERE project_id = ?')->execute([$projectId]);
                $pdo->prepare('DELETE FROM circuits WHERE project_id = ?')->execute([$projectId]);
                $pdo->prepare('DELETE FROM project_shares WHERE project_id = ?')->execute([$projectId]);
                $pdo->prepare('DELETE FROM project_tag_map WHERE project_id = ?')->execute([$projectId]);
                $pdo->prepare('DELETE FROM projects WHERE id = ? AND user_id = ?')->execute([$projectId, (int)$user['id']]);
                $pdo->commit();
                header('Location: trash?project_purged=1'); exit;
            } catch (Throwable $e) {
                $pdo->rollBack();
            }
        }
        header('Location: trash?project_error=1'); exit;
    }

    if ($action === 'restore_device') {
        $deviceId = (int)($_POST['device_id'] ?? 0);
        if ($deviceId > 0) {
            db()->prepare('UPDATE devices SET deleted_at = NULL WHERE id = ? AND deleted_at IS NOT NULL')->execute([$deviceId]);
            header('Location: trash?device_restored=1'); exit;
        }
        header('Location: trash?device_error=1'); exit;
    }

    if ($action === 'purge_device') {
        $deviceId = (int)($_POST['device_id'] ?? 0);
        if ($deviceId > 0) {
            db()->prepare('DELETE FROM devices WHERE id = ? AND deleted_at IS NOT NULL')->execute([$deviceId]);
            header('Location: trash?device_purged=1'); exit;
        }
        header('Location: trash?device_error=1'); exit;
    }
}

$projectTrashStmt = db()->prepare('SELECT p.*, COUNT(DISTINCT c.id) circuits, COUNT(DISTINCT i.id) items FROM projects p LEFT JOIN circuits c ON c.project_id = p.id LEFT JOIN plan_items i ON i.project_id = p.id WHERE p.deleted_at IS NOT NULL AND p.user_id = ? GROUP BY p.id ORDER BY p.deleted_at DESC');
$projectTrashStmt->execute([(int)$user['id']]);
$trashProjects = $projectTrashStmt->fetchAll();

$deviceTrashStmt = db()->query('SELECT id, name, brand, category, power_w, voltage_v, connector, deleted_at FROM devices WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC, brand, name');
$trashDevices = $deviceTrashStmt->fetchAll();

$pageTitle = 'Papierkorb';
$activePage = 'trash';
require __DIR__ . '/inc/header.php';
?>
<main class="container py-4 flex-grow-1">
  <div class="d-flex flex-wrap gap-3 justify-content-between align-items-start mb-4">
    <div>
      <h1 class="h3 mb-1"><i class="bi bi-trash3 me-2"></i>Papierkorb</h1>
      <p class="text-muted mb-0">Gelöschte Projekte und Geräte zentral wiederherstellen oder endgültig löschen.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a class="btn btn-outline-primary" href="<?= e(app_url('projects')) ?>"><i class="bi bi-folder2-open me-1"></i>Projekte</a>
      <a class="btn btn-outline-primary" href="<?= e(app_url('devices')) ?>"><i class="bi bi-lightning-charge me-1"></i>Geräte</a>
    </div>
  </div>

  <?php if (isset($_GET['project_restored'])): ?><div class="alert alert-success">Projekt wurde wiederhergestellt.</div><?php endif; ?>
  <?php if (isset($_GET['project_purged'])): ?><div class="alert alert-success">Projekt wurde endgültig gelöscht.</div><?php endif; ?>
  <?php if (isset($_GET['project_error'])): ?><div class="alert alert-danger">Projekt-Aktion konnte nicht ausgeführt werden.</div><?php endif; ?>
  <?php if (isset($_GET['device_restored'])): ?><div class="alert alert-success">Gerät wurde wiederhergestellt.</div><?php endif; ?>
  <?php if (isset($_GET['device_purged'])): ?><div class="alert alert-success">Gerät wurde endgültig gelöscht.</div><?php endif; ?>
  <?php if (isset($_GET['device_error'])): ?><div class="alert alert-danger">Geräte-Aktion konnte nicht ausgeführt werden.</div><?php endif; ?>

  <div class="row g-4">
    <div class="col-12">
      <div class="card p-4">
        <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
          <h2 class="h4 mb-0"><i class="bi bi-folder-x me-2"></i>Gelöschte Projekte</h2>
          <span class="badge text-bg-secondary"><?= count($trashProjects) ?></span>
        </div>
        <?php if (!$trashProjects): ?>
          <p class="text-muted mb-0">Keine gelöschten Projekte.</p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table align-middle">
              <thead><tr><th>Projekt</th><th>Gelöscht am</th><th>Inhalt</th><th class="text-end">Aktion</th></tr></thead>
              <tbody>
              <?php foreach ($trashProjects as $p): ?>
                <tr>
                  <td class="fw-semibold"><?= e($p['name']) ?></td>
                  <td><?= e($p['deleted_at']) ?></td>
                  <td><?= (int)$p['circuits'] ?> Stromkreis(e), <?= (int)$p['items'] ?> Position(en)</td>
                  <td class="text-end">
                    <form method="post" class="d-inline"><input type="hidden" name="action" value="restore_project"><input type="hidden" name="project_id" value="<?= (int)$p['id'] ?>"><button class="btn btn-sm btn-outline-success"><i class="bi bi-arrow-counterclockwise me-1"></i>Wiederherstellen</button></form>
                    <button class="btn btn-sm btn-outline-danger" type="button" data-bs-toggle="modal" data-bs-target="#purgeProjectModal<?= (int)$p['id'] ?>"><i class="bi bi-trash3 me-1"></i>Endgültig löschen</button>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="col-12">
      <div class="card p-4">
        <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
          <h2 class="h4 mb-0"><i class="bi bi-lightning-charge me-2"></i>Gelöschte Geräte</h2>
          <span class="badge text-bg-secondary"><?= count($trashDevices) ?></span>
        </div>
        <?php if (!$trashDevices): ?>
          <p class="text-muted mb-0">Keine gelöschten Geräte.</p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table align-middle">
              <thead><tr><th>Gerät</th><th>Leistung</th><th>Anschluss</th><th>Gelöscht am</th><th class="text-end">Aktion</th></tr></thead>
              <tbody>
              <?php foreach ($trashDevices as $d): ?>
                <tr>
                  <td><strong><?= e($d['name']) ?></strong><div class="small text-muted"><?= e($d['brand'] ?: '-') ?> · <?= e($d['category'] ?: '-') ?></div></td>
                  <td><?= number_format((float)$d['power_w'], 0, ',', '.') ?> W</td>
                  <td><?= e($d['connector'] ?: '-') ?></td>
                  <td><?= e($d['deleted_at']) ?></td>
                  <td class="text-end">
                    <form method="post" class="d-inline"><input type="hidden" name="action" value="restore_device"><input type="hidden" name="device_id" value="<?= (int)$d['id'] ?>"><button class="btn btn-sm btn-outline-success"><i class="bi bi-arrow-counterclockwise me-1"></i>Wiederherstellen</button></form>
                    <button class="btn btn-sm btn-outline-danger" type="button" data-bs-toggle="modal" data-bs-target="#purgeDeviceModal<?= (int)$d['id'] ?>"><i class="bi bi-trash3 me-1"></i>Endgültig löschen</button>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php foreach ($trashProjects as $p): ?>
    <div class="modal fade" id="purgeProjectModal<?= (int)$p['id'] ?>" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="post">
        <input type="hidden" name="action" value="purge_project"><input type="hidden" name="project_id" value="<?= (int)$p['id'] ?>">
        <div class="modal-header"><h5 class="modal-title">Projekt endgültig löschen</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button></div>
        <div class="modal-body"><p>Dieses Projekt wird unwiderruflich gelöscht. Bitte Projektnamen eingeben:</p><p class="fw-semibold"><?= e($p['name']) ?></p><input class="form-control" name="confirm_project_name" required></div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Abbrechen</button><button class="btn btn-danger">Endgültig löschen</button></div>
      </form></div></div>
    </div>
  <?php endforeach; ?>

  <?php foreach ($trashDevices as $d): ?>
    <div class="modal fade" id="purgeDeviceModal<?= (int)$d['id'] ?>" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="post">
        <input type="hidden" name="action" value="purge_device"><input type="hidden" name="device_id" value="<?= (int)$d['id'] ?>">
        <div class="modal-header"><h5 class="modal-title">Gerät endgültig löschen</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button></div>
        <div class="modal-body"><p>Dieses Gerät wird unwiderruflich gelöscht.</p><p class="fw-semibold mb-0"><?= e($d['name']) ?></p></div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Abbrechen</button><button class="btn btn-danger">Endgültig löschen</button></div>
      </form></div></div>
    </div>
  <?php endforeach; ?>
</main>
<?php require __DIR__ . '/inc/footer.php'; ?>
