<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/helpers.php';
$user = require_login();
ensure_schema();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';

    if ($action === 'delete') {
        $projectId = (int)($_POST['project_id'] ?? 0);
        $confirm = (string)($_POST['confirm_project_name'] ?? '');
        $project = user_project($projectId, (int)$user['id']);

        if ($project && (int)($project['is_owner'] ?? 0) === 1 && hash_equals((string)$project['name'], $confirm)) {
            try {
                db()->prepare('UPDATE projects SET deleted_at = NOW(), deleted_by = ? WHERE id = ? AND user_id = ?')->execute([(int)$user['id'], $projectId, (int)$user['id']]);
                log_project_activity($projectId, (int)$user['id'], 'Projekt in Papierkorb verschoben');
                header('Location: projects?deleted=1'); exit;
            } catch (Throwable $e) {
                header('Location: projects?delete_error=1'); exit;
            }
        }

        header('Location: projects?delete_error=1'); exit;
    }

    if ($action === 'restore') {
        $projectId = (int)($_POST['project_id'] ?? 0);
        $project = user_project($projectId, (int)$user['id'], true);
        if ($project && (int)($project['is_owner'] ?? 0) === 1) {
            db()->prepare('UPDATE projects SET deleted_at = NULL, deleted_by = NULL WHERE id = ? AND user_id = ?')->execute([$projectId, (int)$user['id']]);
            log_project_activity($projectId, (int)$user['id'], 'Projekt wiederhergestellt');
            header('Location: projects?restored=1'); exit;
        }
        header('Location: projects?delete_error=1'); exit;
    }

    if ($action === 'purge') {
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
                $pdo->prepare('DELETE FROM project_activity WHERE project_id = ?')->execute([$projectId]);
                $pdo->prepare('DELETE FROM projects WHERE id = ? AND user_id = ?')->execute([$projectId, (int)$user['id']]);
                $pdo->commit();
                header('Location: projects?purged=1'); exit;
            } catch (Throwable $e) {
                $pdo->rollBack();
            }
        }
        header('Location: projects?delete_error=1'); exit;
    }

    $name = trim($_POST['name'] ?? '');
    if ($name) {
        $stmt = db()->prepare('INSERT INTO projects (user_id, name, client, technician) VALUES (?, ?, ?, ?)');
        $stmt->execute([(int)$user['id'], $name, trim($_POST['client'] ?? ''), trim($_POST['technician'] ?? '')]);
        $projectId = (int)db()->lastInsertId();
        db()->prepare('INSERT INTO circuits (project_id, name, amp_limit) VALUES (?, ?, 16.00)')->execute([$projectId, 'Standard-Stromkreis']);
        log_project_activity($projectId, (int)$user['id'], 'Projekt erstellt');
        header('Location: project?id=' . $projectId); exit;
    }
}

$stmt = db()->prepare('SELECT p.*, u.name AS owner_name, u.email AS owner_email, CASE WHEN p.user_id = ? THEN 1 ELSE 0 END AS is_owner, COALESCE(ps.permission, CASE WHEN p.user_id = ? THEN \'manage\' ELSE ps.permission END) AS permission, COUNT(DISTINCT c.id) circuits, COUNT(DISTINCT i.id) items FROM projects p JOIN users u ON u.id = p.user_id LEFT JOIN project_shares ps ON ps.project_id = p.id AND ps.user_id = ? LEFT JOIN circuits c ON c.project_id = p.id LEFT JOIN plan_items i ON i.project_id = p.id WHERE p.deleted_at IS NULL AND (p.user_id = ? OR ps.user_id = ?) GROUP BY p.id ORDER BY p.updated_at DESC');
$stmt->execute([(int)$user['id'], (int)$user['id'], (int)$user['id'], (int)$user['id'], (int)$user['id']]);
$projects = $stmt->fetchAll();
$trashStmt = db()->prepare('SELECT p.*, COUNT(DISTINCT c.id) circuits, COUNT(DISTINCT i.id) items FROM projects p LEFT JOIN circuits c ON c.project_id = p.id LEFT JOIN plan_items i ON i.project_id = p.id WHERE p.deleted_at IS NOT NULL AND p.user_id = ? GROUP BY p.id ORDER BY p.deleted_at DESC');
$trashStmt->execute([(int)$user['id']]);
$trashProjects = $trashStmt->fetchAll();
$pageTitle = 'Projekte';
$activePage = 'projects';
require __DIR__ . '/inc/header.php';
?>
<main class="container py-4 flex-grow-1">
  <?php if (isset($_GET['deleted'])): ?><div class="alert alert-success">Projekt wurde in den Papierkorb verschoben.</div><?php endif; ?>
  <?php if (isset($_GET['restored'])): ?><div class="alert alert-success">Projekt wurde wiederhergestellt.</div><?php endif; ?>
  <?php if (isset($_GET['purged'])): ?><div class="alert alert-success">Projekt wurde endgültig gelöscht.</div><?php endif; ?>
  <?php if (isset($_GET['delete_error'])): ?><div class="alert alert-danger">Projekt konnte nicht gelöscht werden. Bitte prüfe die Bestätigung.</div><?php endif; ?>
  <?php if (isset($_GET['import_error'])): ?><div class="alert alert-danger">Projekt konnte nicht importiert werden. Bitte prüfe die JSON-Datei.</div><?php endif; ?>
  <div class="row g-4">
    <div class="col-lg-4">
      <div class="card p-4 sticky-lg-top planner-form-card">
        <h1 class="h3">Neues Projekt</h1>
        <form method="post" class="row g-3">
          <div class="col-12"><label class="form-label">Projektname</label><input class="form-control" name="name" required></div>
          <div class="col-12"><label class="form-label">Kunde</label><input class="form-control" name="client"></div>
          <div class="col-12"><label class="form-label">Techniker</label><input class="form-control" name="technician" value="<?= e($user['name']) ?>"></div>
          <div class="col-12"><button class="btn btn-primary w-100">Projekt erstellen</button></div>
        </form>
      </div>
      <div class="card p-4 mt-4">
        <h2 class="h5 mb-3"><i class="bi bi-upload me-2"></i>Projekt importieren</h2>
        <p class="text-muted small mb-3">Ein einzelnes Projekt aus einer Power-Planner-JSON-Datei lokal wiederherstellen.</p>
        <form method="post" action="<?= e(app_url('project-import')) ?>" enctype="multipart/form-data" class="row g-3">
          <div class="col-12"><input class="form-control" type="file" name="project_file" accept="application/json,.json" required></div>
          <div class="col-12"><button class="btn btn-outline-primary w-100" type="submit"><i class="bi bi-upload me-1"></i>Projekt importieren</button></div>
        </form>
      </div>
    </div>
    <div class="col-lg-8">
      <div class="card p-4">
        <h2 class="h4 mb-3">Meine Projekte</h2>
        <?php if (!$projects): ?>
          <p class="text-muted mb-0">Noch keine Projekte vorhanden.</p>
        <?php else: ?>
          <div class="list-group list-group-flush">
            <?php foreach ($projects as $p): ?>
              <div class="list-group-item d-flex flex-wrap gap-3 justify-content-between align-items-center">
                <a class="text-decoration-none text-reset flex-grow-1" href="<?= e(app_url('project?id=' . (int)$p['id'])) ?>">
                  <div class="d-flex flex-wrap gap-2 align-items-center">
                    <strong><?= e($p['name']) ?></strong>
                    <?php if ((int)$p['is_owner'] !== 1): ?><span class="badge text-bg-info">geteilt</span><span class="badge text-bg-secondary"><?= e($p['permission'] === 'edit' ? 'bearbeiten' : ($p['permission'] === 'manage' ? 'verwalten' : 'ansehen')) ?></span><?php endif; ?>
                  </div>
                  <span class="small-muted d-block"><?= e($p['client'] ?: 'Kein Kunde') ?> · <?= (int)$p['circuits'] ?> Stromkreis(e) · <?= (int)$p['items'] ?> Position(en)</span>
                  <?php if ((int)$p['is_owner'] !== 1): ?><span class="small text-muted d-block">Besitzer: <?= e($p['owner_name']) ?> &lt;<?= e($p['owner_email']) ?>&gt;</span><?php endif; ?>
                </a>
                <div class="d-flex gap-2">
                  <a class="btn btn-sm btn-outline-primary" href="<?= e(app_url('project?id=' . (int)$p['id'])) ?>">Öffnen</a>
                  <?php if ((int)$p['is_owner'] === 1): ?>
                    <button class="btn btn-sm btn-outline-danger" type="button" data-bs-toggle="modal" data-bs-target="#deleteProjectModal<?= (int)$p['id'] ?>">Löschen</button>
                  <?php endif; ?>
                </div>
              </div>
              <?php if ((int)$p['is_owner'] === 1): ?>
                <div class="modal fade" id="deleteProjectModal<?= (int)$p['id'] ?>" tabindex="-1" aria-hidden="true">
                  <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                      <form method="post">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="project_id" value="<?= (int)$p['id'] ?>">
                        <div class="modal-header">
                          <h5 class="modal-title">Projekt löschen</h5>
                          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                        </div>
                        <div class="modal-body">
                          <p>Zum Löschen bitte den Projektnamen eingeben:</p>
                          <p class="fw-semibold"><?= e($p['name']) ?></p>
                          <input class="form-control" name="confirm_project_name" required>
                        </div>
                        <div class="modal-footer">
                          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Abbrechen</button>
                          <button type="submit" class="btn btn-danger">Projekt löschen</button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    
      <div class="card p-4 mt-4">
        <h2 class="h4 mb-3"><i class="bi bi-trash me-2"></i>Papierkorb</h2>
        <?php if (!$trashProjects): ?>
          <p class="text-muted mb-0">Keine gelöschten Projekte.</p>
        <?php else: ?>
          <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Projekt</th><th>Gelöscht am</th><th>Inhalt</th><th class="text-end">Aktion</th></tr></thead><tbody>
          <?php foreach ($trashProjects as $p): ?>
            <tr>
              <td class="fw-semibold"><?= e($p['name']) ?></td>
              <td><?= e($p['deleted_at']) ?></td>
              <td><?= (int)$p['circuits'] ?> Stromkreis(e), <?= (int)$p['items'] ?> Position(en)</td>
              <td class="text-end">
                <form method="post" class="d-inline"><input type="hidden" name="action" value="restore"><input type="hidden" name="project_id" value="<?= (int)$p['id'] ?>"><button class="btn btn-sm btn-outline-success">Wiederherstellen</button></form>
                <button class="btn btn-sm btn-outline-danger" type="button" data-bs-toggle="modal" data-bs-target="#purgeProjectModal<?= (int)$p['id'] ?>">Endgültig löschen</button>
              </td>
            </tr>
            <div class="modal fade" id="purgeProjectModal<?= (int)$p['id'] ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="post"><input type="hidden" name="action" value="purge"><input type="hidden" name="project_id" value="<?= (int)$p['id'] ?>"><div class="modal-header"><h5 class="modal-title">Endgültig löschen</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><p>Dieses Projekt wird unwiderruflich gelöscht. Bitte Projektnamen eingeben:</p><p class="fw-semibold"><?= e($p['name']) ?></p><input class="form-control" name="confirm_project_name" required></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Abbrechen</button><button class="btn btn-danger">Endgültig löschen</button></div></form></div></div></div>
          <?php endforeach; ?>
          </tbody></table></div>
        <?php endif; ?>
      </div>

    </div>
  </div>
</main>
<?php require __DIR__ . '/inc/footer.php'; ?>
