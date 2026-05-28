<?php
require_once __DIR__ . '/inc/auth.php'; require_once __DIR__ . '/inc/helpers.php';
$user = require_login();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';

    if ($action === 'delete') {
        $projectId = (int)($_POST['project_id'] ?? 0);
        $confirm = (string)($_POST['confirm_project_name'] ?? '');
        $project = user_project($projectId, (int)$user['id']);

        if ($project && hash_equals((string)$project['name'], $confirm)) {
            $pdo = db();
            $pdo->beginTransaction();
            try {
                // plan_items and circuits are also removed by FK cascade, these explicit deletes
                // keep the delete stable on hosts where constraints were imported differently.
                $stmt = $pdo->prepare('DELETE FROM plan_items WHERE project_id = ?');
                $stmt->execute([$projectId]);
                $stmt = $pdo->prepare('DELETE FROM circuits WHERE project_id = ?');
                $stmt->execute([$projectId]);
                $stmt = $pdo->prepare('DELETE FROM projects WHERE id = ? AND user_id = ?');
                $stmt->execute([$projectId, $user['id']]);
                $pdo->commit();
                header('Location: projects?deleted=1'); exit;
            } catch (Throwable $e) {
                $pdo->rollBack();
                header('Location: projects?delete_error=1'); exit;
            }
        }

        header('Location: projects?delete_error=1'); exit;
    }

    $name = trim($_POST['name'] ?? '');
    if ($name) {
        $stmt = db()->prepare('INSERT INTO projects (user_id, name, client, technician, ) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$user['id'], $name, trim($_POST['client'] ?? ''), trim($_POST['technician'] ?? ''), trim($_POST[''] ?? '')]);
        $projectId = (int)db()->lastInsertId();
        db()->prepare('INSERT INTO circuits (project_id, name, amp_limit) VALUES (?, ?, 16.00)')->execute([$projectId, 'Standard-Stromkreis']);
        header('Location: project?id=' . $projectId); exit;
    }
}
$stmt = db()->prepare('SELECT p.*, COUNT(DISTINCT c.id) circuits, COUNT(DISTINCT i.id) items FROM projects p LEFT JOIN circuits c ON c.project_id=p.id LEFT JOIN plan_items i ON i.project_id=p.id WHERE p.user_id=? GROUP BY p.id ORDER BY p.updated_at DESC');
$stmt->execute([$user['id']]); $projects = $stmt->fetchAll();
$pageTitle = 'Projekte'; $activePage = 'projects'; require __DIR__ . '/inc/header.php';
?>
<main class="container py-4 flex-grow-1">
  <?php if (isset($_GET['deleted'])): ?><div class="alert alert-success">Projekt wurde gelöscht.</div><?php endif; ?>
  <?php if (isset($_GET['delete_error'])): ?><div class="alert alert-danger">Projekt konnte nicht gelöscht werden. Bitte prüfe die Bestätigung.</div><?php endif; ?>
  <div class="row g-4">
    <div class="col-lg-4"><div class="card p-4 sticky-lg-top planner-form-card"><h1 class="h3">Neues Projekt</h1><form method="post" class="row g-3">
      <div class="col-12"><label class="form-label">Projektname</label><input class="form-control" name="name" required></div>
      <div class="col-12"><label class="form-label">Kunde</label><input class="form-control" name="client"></div>
      <div class="col-12"><label class="form-label">Techniker</label><input class="form-control" name="technician" value="<?= e($user['name']) ?>"></div>
      <div class="col-12"><label class="form-label"></label><input class="form-control" name=""></div>
      <div class="col-12"><button class="btn btn-primary w-100">Projekt erstellen</button></div>
    </form></div></div>
    <div class="col-lg-8"><div class="card p-4"><h2 class="h4 mb-3">Meine Projekte</h2>
      <?php if (!$projects): ?><p class="text-muted">Noch keine Projekte vorhanden.</p><?php endif; ?>
      <div class="list-group list-group-flush">
        <?php foreach ($projects as $p): ?>
          <div class="list-group-item d-flex flex-wrap gap-3 justify-content-between align-items-center">
            <a class="text-decoration-none text-reset flex-grow-1" href="<?= e(app_url('project')) ?>?id=<?= (int)$p['id'] ?>">
              <strong><?= e($p['name']) ?></strong>
              <span class="small-muted d-block"><?= e($p['client'] ?: 'Kein Kunde') ?> · <?= (int)$p['circuits'] ?> Stromkreis(e) · <?= (int)$p['items'] ?> Position(en)</span>
            </a>
            <div class="d-flex gap-2">
              <a class="btn btn-sm btn-outline-primary" href="<?= e(app_url('project')) ?>?id=<?= (int)$p['id'] ?>">Öffnen</a>
              <button
                type="button"
                class="btn btn-sm btn-outline-danger"
                data-bs-toggle="modal"
                data-bs-target="#deleteProjectModal"
                data-project-id="<?= (int)$p['id'] ?>"
                data-project-name="<?= e($p['name']) ?>"
              >Löschen</button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div></div>
  </div>
</main>
<div class="modal fade" id="deleteProjectModal" tabindex="-1" aria-labelledby="deleteProjectModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form method="post" class="modal-content">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="project_id" id="deleteProjectId">
      <div class="modal-header">
        <h2 class="modal-title h5" id="deleteProjectModalLabel">Projekt löschen</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
      </div>
      <div class="modal-body">
        <p class="mb-2">Möchtest du dieses Projekt wirklich löschen?</p>
        <p class="fw-semibold mb-3" id="deleteProjectNameDisplay"></p>
        <div class="alert alert-warning small">Dabei werden auch alle Stromkreise und Planpositionen dieses Projekts gelöscht.</div>
        <label class="form-label">Zur Bestätigung bitte den Projektnamen eingeben</label>
        <input class="form-control" name="confirm_project_name" id="deleteProjectConfirm" autocomplete="off" required>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Abbrechen</button>
        <button type="submit" class="btn btn-danger" id="deleteProjectSubmit" disabled>Projekt endgültig löschen</button>
      </div>
    </form>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const modal = document.getElementById('deleteProjectModal');
  if (!modal) return;
  const idField = document.getElementById('deleteProjectId');
  const nameDisplay = document.getElementById('deleteProjectNameDisplay');
  const confirmField = document.getElementById('deleteProjectConfirm');
  const submitButton = document.getElementById('deleteProjectSubmit');
  let expectedName = '';

  modal.addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    expectedName = button.getAttribute('data-project-name') || '';
    idField.value = button.getAttribute('data-project-id') || '';
    nameDisplay.textContent = expectedName;
    confirmField.value = '';
    submitButton.disabled = true;
    setTimeout(function () { confirmField.focus(); }, 150);
  });

  confirmField.addEventListener('input', function () {
    submitButton.disabled = confirmField.value !== expectedName;
  });
});
</script>
<?php require __DIR__ . '/inc/footer.php'; ?>
