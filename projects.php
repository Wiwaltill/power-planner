<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/helpers.php';
$user = require_login();
ensure_schema();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';

    if ($action === 'duplicate') {
        $projectId = (int)($_POST['project_id'] ?? 0);
        try {
            $newId = duplicate_project($projectId, (int)$user['id']);
            header('Location: project?id=' . $newId . '&duplicated=1'); exit;
        } catch (Throwable $e) {
            header('Location: projects?duplicate_error=1'); exit;
        }
    }

    if ($action === 'archive') {
        $projectId = (int)($_POST['project_id'] ?? 0);
        $project = user_project($projectId, (int)$user['id']);
        if ($project && project_can_manage($project)) {
            db()->prepare('UPDATE projects SET archived_at = NOW() WHERE id = ?')->execute([$projectId]);
            header('Location: projects?archived=1'); exit;
        }
        header('Location: projects?archive_error=1'); exit;
    }

    if ($action === 'unarchive') {
        $projectId = (int)($_POST['project_id'] ?? 0);
        $project = user_project($projectId, (int)$user['id'], true);
        if ($project && project_can_manage_archived($project)) {
            db()->prepare('UPDATE projects SET archived_at = NULL WHERE id = ?')->execute([$projectId]);
            header('Location: projects?unarchived=1'); exit;
        }
        header('Location: projects?archive_error=1'); exit;
    }

    if ($action === 'leave_share') {
        $projectId = (int)($_POST['project_id'] ?? 0);
        $project = user_project($projectId, (int)$user['id'], true);
        if ($project && (int)($project['is_owner'] ?? 0) !== 1) {
            db()->prepare('DELETE FROM project_shares WHERE project_id = ? AND user_id = ?')->execute([$projectId, (int)$user['id']]);
            header('Location: projects?share_removed=1'); exit;
        }
        header('Location: projects?share_remove_error=1'); exit;
    }

    if ($action === 'delete') {
        $projectId = (int)($_POST['project_id'] ?? 0);
        $confirm = (string)($_POST['confirm_project_name'] ?? '');
        $project = user_project($projectId, (int)$user['id']);

        if ($project && (int)($project['is_owner'] ?? 0) === 1 && hash_equals((string)$project['name'], $confirm)) {
            try {
                db()->prepare('UPDATE projects SET deleted_at = NOW(), deleted_by = ? WHERE id = ? AND user_id = ?')->execute([(int)$user['id'], $projectId, (int)$user['id']]);
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
        $status = in_array(($_POST['status'] ?? 'planning'), array_keys(project_status_options()), true) ? $_POST['status'] : 'planning';
        $stmt = db()->prepare('INSERT INTO projects (user_id, name, client, technician, status) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([(int)$user['id'], $name, trim($_POST['client'] ?? ''), trim($_POST['technician'] ?? ''), $status]);
        $projectId = (int)db()->lastInsertId();
        set_project_tags($projectId, array_map('intval', $_POST['tag_ids'] ?? []));
        db()->prepare('INSERT INTO circuits (project_id, name, amp_limit) VALUES (?, ?, 16.00)')->execute([$projectId, 'Standard-Stromkreis']);
        header('Location: project?id=' . $projectId); exit;
    }
}

$stmt = db()->prepare('SELECT p.*, u.name AS owner_name, u.email AS owner_email, CASE WHEN p.user_id = ? THEN 1 ELSE 0 END AS is_owner, COALESCE(ps.permission, CASE WHEN p.user_id = ? THEN \'manage\' ELSE ps.permission END) AS permission, COUNT(DISTINCT c.id) circuits, COUNT(DISTINCT i.id) items FROM projects p JOIN users u ON u.id = p.user_id LEFT JOIN project_shares ps ON ps.project_id = p.id AND ps.user_id = ? LEFT JOIN circuits c ON c.project_id = p.id LEFT JOIN plan_items i ON i.project_id = p.id WHERE p.deleted_at IS NULL AND p.archived_at IS NULL AND (p.user_id = ? OR ps.user_id = ?) GROUP BY p.id ORDER BY p.updated_at DESC');
$stmt->execute([(int)$user['id'], (int)$user['id'], (int)$user['id'], (int)$user['id'], (int)$user['id']]);
$projects = $stmt->fetchAll();
$trashStmt = db()->prepare('SELECT p.*, COUNT(DISTINCT c.id) circuits, COUNT(DISTINCT i.id) items FROM projects p LEFT JOIN circuits c ON c.project_id = p.id LEFT JOIN plan_items i ON i.project_id = p.id WHERE p.deleted_at IS NOT NULL AND p.user_id = ? GROUP BY p.id ORDER BY p.deleted_at DESC');
$trashStmt->execute([(int)$user['id']]);
$trashProjects = $trashStmt->fetchAll();
$archiveStmt = db()->prepare('SELECT p.*, u.name AS owner_name, u.email AS owner_email, CASE WHEN p.user_id = ? THEN 1 ELSE 0 END AS is_owner, COALESCE(ps.permission, CASE WHEN p.user_id = ? THEN \'manage\' ELSE ps.permission END) AS permission, COUNT(DISTINCT c.id) circuits, COUNT(DISTINCT i.id) items FROM projects p JOIN users u ON u.id = p.user_id LEFT JOIN project_shares ps ON ps.project_id = p.id AND ps.user_id = ? LEFT JOIN circuits c ON c.project_id = p.id LEFT JOIN plan_items i ON i.project_id = p.id WHERE p.deleted_at IS NULL AND p.archived_at IS NOT NULL AND (p.user_id = ? OR ps.user_id = ?) GROUP BY p.id ORDER BY p.archived_at DESC');
$archiveStmt->execute([(int)$user['id'], (int)$user['id'], (int)$user['id'], (int)$user['id'], (int)$user['id']]);
$archivedProjects = $archiveStmt->fetchAll();

$allTags = all_project_tags();
$allProjectIds = array_map(fn($p) => (int)$p['id'], array_merge($projects, $archivedProjects, $trashProjects));
$projectTags = project_tags_grouped($allProjectIds);
$dashboardStats = [
    'active' => count($projects),
    'archived' => count($archivedProjects),
    'public' => 0,
    'devices' => 0,
    'users' => 0,
];
try {
    $stmt = db()->prepare('SELECT COUNT(*) FROM projects p LEFT JOIN project_shares ps ON ps.project_id = p.id AND ps.user_id = ? WHERE p.deleted_at IS NULL AND p.archived_at IS NULL AND p.public_share_enabled = 1 AND (p.user_id = ? OR ps.user_id = ?)');
    $stmt->execute([(int)$user['id'], (int)$user['id'], (int)$user['id']]);
    $dashboardStats['public'] = (int)$stmt->fetchColumn();
    $dashboardStats['devices'] = (int)db()->query('SELECT COUNT(*) FROM devices WHERE deleted_at IS NULL')->fetchColumn();
    $dashboardStats['users'] = (($user['role'] ?? '') === 'admin') ? (int)db()->query('SELECT COUNT(*) FROM users WHERE active = 1')->fetchColumn() : null;
} catch (Throwable $e) {}

$pageTitle = 'Projekte';
$activePage = 'projects';
require __DIR__ . '/inc/header.php';
?>
<main class="container py-4 flex-grow-1">

  <div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="card p-3"><div class="small text-muted">Aktive Projekte</div><div class="h3 mb-0"><?= (int)$dashboardStats['active'] ?></div></div></div>
    <div class="col-6 col-lg-3"><div class="card p-3"><div class="small text-muted">Archivierte Projekte</div><div class="h3 mb-0"><?= (int)$dashboardStats['archived'] ?></div></div></div>
    <div class="col-6 col-lg-3"><div class="card p-3"><div class="small text-muted">Öffentliche Freigaben</div><div class="h3 mb-0"><?= (int)$dashboardStats['public'] ?></div></div></div>
    <div class="col-6 col-lg-3"><div class="card p-3"><div class="small text-muted">Geräte</div><div class="h3 mb-0"><?= (int)$dashboardStats['devices'] ?></div></div></div>
  </div>
  <?php if (isset($_GET['deleted'])): ?><div class="alert alert-success">Projekt wurde in den Papierkorb verschoben.</div><?php endif; ?>
  <?php if (isset($_GET['restored'])): ?><div class="alert alert-success">Projekt wurde wiederhergestellt.</div><?php endif; ?>
  <?php if (isset($_GET['purged'])): ?><div class="alert alert-success">Projekt wurde endgültig gelöscht.</div><?php endif; ?>
  <?php if (isset($_GET['delete_error'])): ?><div class="alert alert-danger">Projekt konnte nicht gelöscht werden. Bitte prüfe die Bestätigung.</div><?php endif; ?>
  <?php if (isset($_GET['archived'])): ?><div class="alert alert-success">Projekt wurde archiviert.</div><?php endif; ?>
  <?php if (isset($_GET['unarchived'])): ?><div class="alert alert-success">Projekt wurde reaktiviert.</div><?php endif; ?>
  <?php if (isset($_GET['duplicate_error'])): ?><div class="alert alert-danger">Projekt konnte nicht dupliziert werden.</div><?php endif; ?>
  <?php if (isset($_GET['share_removed'])): ?><div class="alert alert-success">Freigabe wurde entfernt. Das geteilte Projekt wird nicht mehr angezeigt.</div><?php endif; ?>
  <?php if (isset($_GET['share_remove_error'])): ?><div class="alert alert-danger">Freigabe konnte nicht entfernt werden.</div><?php endif; ?>
  <?php if (isset($_GET['import_error'])): ?><div class="alert alert-danger">Projekt konnte nicht importiert werden. Bitte prüfe die JSON-Datei.</div><?php endif; ?>
  <div class="row g-4">
    <div class="col-lg-4">
      <div class="card p-4 planner-form-card">
        <h1 class="h3">Neues Projekt</h1>
        <form method="post" class="row g-3">
          <div class="col-12"><label class="form-label">Projektname</label><input class="form-control" name="name" required></div>
          <div class="col-12"><label class="form-label">Kunde</label><input class="form-control" name="client"></div>
          <div class="col-12"><label class="form-label">Techniker</label><input class="form-control" name="technician" value="<?= e($user['name']) ?>"></div>
          <div class="col-12"><label class="form-label">Status</label><select class="form-select" name="status"><?php foreach (project_status_options() as $key => $label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?></select></div>
          <div class="col-12">
            <label class="form-label">Tags</label>
            <?php if ($allTags): ?>
              <div class="tag-choice-group">
                <?php foreach ($allTags as $tag): $tagColor = $tag['color'] ?: 'secondary'; ?>
                  <input class="btn-check" type="checkbox" name="tag_ids[]" value="<?= (int)$tag['id'] ?>" id="newProjectTag<?= (int)$tag['id'] ?>" autocomplete="off">
                  <label class="btn btn-sm btn-outline-<?= e($tagColor) ?> tag-choice" for="newProjectTag<?= (int)$tag['id'] ?>">
                    <span class="badge rounded-pill text-bg-<?= e($tagColor) ?> me-1">&nbsp;</span><?= e($tag['name']) ?>
                  </label>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="form-text">Noch keine Tags angelegt.</div>
            <?php endif; ?>
          </div>
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
        <input class="form-control mb-3" id="projectSearch" placeholder="Projekte suchen nach Name, Kunde, Techniker, Besitzer oder Tags...">
        <?php if (!$projects): ?>
          <p class="text-muted mb-0">Noch keine Projekte vorhanden.</p>
        <?php else: ?>
          <div class="list-group list-group-flush">
            <?php foreach ($projects as $p): ?>
              <?php $tags = $projectTags[(int)$p['id']] ?? []; $searchText = strtolower(($p['name'] ?? '') . ' ' . ($p['client'] ?? '') . ' ' . ($p['technician'] ?? '') . ' ' . ($p['owner_name'] ?? '') . ' ' . implode(' ', array_map(fn($t) => $t['name'], $tags))); ?>
              <div class="list-group-item d-flex flex-wrap gap-3 justify-content-between align-items-center project-list-row" data-search="<?= e($searchText) ?>">
                <a class="text-decoration-none text-reset flex-grow-1" href="<?= e(app_url('project?id=' . (int)$p['id'])) ?>">
                  <div class="d-flex flex-wrap gap-2 align-items-center">
                    <strong><?= e($p['name']) ?></strong>
                    <span class="badge text-bg-<?= e(project_status_badge($p['status'] ?? 'planning')) ?>"><?= e(project_status_label($p['status'] ?? 'planning')) ?></span>
                    <?php foreach (($projectTags[(int)$p['id']] ?? []) as $tag): ?><span class="badge text-bg-<?= e($tag['color'] ?: 'secondary') ?>"><?= e($tag['name']) ?></span><?php endforeach; ?>
                    <?php if ((int)$p['is_owner'] !== 1): ?><span class="badge text-bg-info">geteilt</span><span class="badge text-bg-secondary"><?= e(project_permission_label($p['permission'] ?? 'view')) ?></span><?php endif; ?>
                  </div>
                  <span class="small-muted d-block"><?= e($p['client'] ?: 'Kein Kunde') ?> · <?= (int)$p['circuits'] ?> Stromkreis(e) · <?= (int)$p['items'] ?> Position(en)</span>
                  <?php if ((int)$p['is_owner'] !== 1): ?><span class="small text-muted d-block">Besitzer: <?= e($p['owner_name']) ?> &lt;<?= e($p['owner_email']) ?>&gt;</span><?php endif; ?>
                </a>
                <div class="d-flex gap-2 flex-wrap justify-content-end">
                  <a class="btn btn-sm btn-outline-primary" href="<?= e(app_url('project?id=' . (int)$p['id'])) ?>">Öffnen</a>
                  <form method="post" class="d-inline"><input type="hidden" name="action" value="duplicate"><input type="hidden" name="project_id" value="<?= (int)$p['id'] ?>"><button class="btn btn-sm btn-outline-secondary">Duplizieren</button></form>
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
        <h2 class="h4 mb-3"><i class="bi bi-archive me-2"></i>Archiv</h2>
        <?php if (!$archivedProjects): ?>
          <p class="text-muted mb-0">Keine archivierten Projekte.</p>
        <?php else: ?>
          <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Projekt</th><th>Archiviert am</th><th>Inhalt</th><th class="text-end">Aktion</th></tr></thead><tbody>
          <?php foreach ($archivedProjects as $p): ?>
            <tr>
              <td class="fw-semibold"><?= e($p['name']) ?></td>
              <td><?= e($p['archived_at']) ?></td>
              <td><?= (int)$p['circuits'] ?> Stromkreis(e), <?= (int)$p['items'] ?> Position(en)</td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary" href="<?= e(app_url('project?id=' . (int)$p['id'])) ?>">Öffnen</a>
                <?php if (project_can_manage_archived($p)): ?>
                  <form method="post" class="d-inline"><input type="hidden" name="action" value="unarchive"><input type="hidden" name="project_id" value="<?= (int)$p['id'] ?>"><button class="btn btn-sm btn-outline-success">Reaktivieren</button></form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody></table></div>
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

<script>
document.addEventListener('DOMContentLoaded', function(){
  const input = document.getElementById('projectSearch');
  if (!input) return;
  const normalize = value => String(value || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  input.addEventListener('input', function(){
    const q = normalize(this.value.trim());
    document.querySelectorAll('.project-list-row').forEach(row => {
      const haystack = normalize((row.dataset.search || '') + ' ' + row.innerText);
      row.classList.toggle('d-none', !!q && !haystack.includes(q));
    });
  });
});
</script>

<?php require __DIR__ . '/inc/footer.php'; ?>
