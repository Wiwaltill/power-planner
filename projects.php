<?php
require_once __DIR__ . '/inc/auth.php'; require_once __DIR__ . '/inc/helpers.php';
$user = require_login();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    if ($name) {
        $stmt = db()->prepare('INSERT INTO projects (user_id, name, client, technician, logo_url) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$user['id'], $name, trim($_POST['client'] ?? ''), trim($_POST['technician'] ?? ''), trim($_POST['logo_url'] ?? '')]);
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
  <div class="row g-4">
    <div class="col-lg-4"><div class="card p-4 sticky-lg-top planner-form-card"><h1 class="h3">Neues Projekt</h1><form method="post" class="row g-3">
      <div class="col-12"><label class="form-label">Projektname</label><input class="form-control" name="name" required></div>
      <div class="col-12"><label class="form-label">Kunde</label><input class="form-control" name="client"></div>
      <div class="col-12"><label class="form-label">Techniker</label><input class="form-control" name="technician" value="<?= e($user['name']) ?>"></div>
      <div class="col-12"><label class="form-label">Logo URL</label><input class="form-control" name="logo_url"></div>
      <div class="col-12"><button class="btn btn-primary w-100">Projekt erstellen</button></div>
    </form></div></div>
    <div class="col-lg-8"><div class="card p-4"><h2 class="h4 mb-3">Meine Projekte</h2>
      <?php if (!$projects): ?><p class="text-muted">Noch keine Projekte vorhanden.</p><?php endif; ?>
      <div class="list-group list-group-flush">
        <?php foreach ($projects as $p): ?><a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" href="project?id=<?= (int)$p['id'] ?>">
          <span><strong><?= e($p['name']) ?></strong><span class="small-muted d-block"><?= e($p['client'] ?: 'Kein Kunde') ?> · <?= (int)$p['circuits'] ?> Stromkreis(e) · <?= (int)$p['items'] ?> Position(en)</span></span>
          <span class="badge text-bg-light border">Öffnen</span>
        </a><?php endforeach; ?>
      </div>
    </div></div>
  </div>
</main>
<?php require __DIR__ . '/inc/footer.php'; ?>
