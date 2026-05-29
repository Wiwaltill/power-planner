<?php
require_once __DIR__ . '/inc/auth.php'; require_once __DIR__ . '/inc/helpers.php';
$user = require_login();
$projectId = (int)($_GET['id'] ?? 0);
$project = user_project($projectId, (int)$user['id']);
if (!$project) { header('Location: projects'); exit; }
$isOwner = (int)($project['is_owner'] ?? 0) === 1;
$isArchived = project_is_archived($project);
$canEdit = project_can_edit($project);
$canManage = project_can_manage($project);
$canOwner = project_is_owner($project);
$shareMessage = '';
$shareError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $manageActions = ['share_project','unshare_project','update_share_permission','enable_public_share','disable_public_share','regenerate_public_share','update_public_share','clear_public_share_expiry','update_project_meta'];
    $ownerActions = ['transfer_owner'];
    if (in_array($action, $manageActions, true) && !$canManage) { $shareError = 'Keine Verwaltungsrechte für dieses Projekt.'; $action = ''; }
    if (in_array($action, $ownerActions, true) && !$canOwner) { $shareError = 'Nur der Besitzer darf diese Aktion ausführen.'; $action = ''; }

    if ($action === 'update_project_meta') {
        $name = trim((string)($_POST['name'] ?? $project['name']));
        $client = trim((string)($_POST['client'] ?? ''));
        $technician = trim((string)($_POST['technician'] ?? ''));
        $status = in_array(($_POST['status'] ?? 'planning'), array_keys(project_status_options()), true) ? $_POST['status'] : 'planning';
        if ($name === '') {
            $shareError = 'Projektname darf nicht leer sein.';
        } else {
            db()->prepare('UPDATE projects SET name = ?, client = ?, technician = ?, status = ? WHERE id = ?')->execute([$name, $client, $technician, $status, $projectId]);
            set_project_tags($projectId, array_map('intval', $_POST['tag_ids'] ?? []));
            $project = user_project($projectId, (int)$user['id']);
            $shareMessage = 'Projektdaten wurden gespeichert.';
        }
    }

    if ($action === 'share_project') {
        $shareUserId = (int)($_POST['share_user_id'] ?? 0);
        if ($shareUserId > 0 && $shareUserId !== (int)$user['id']) {
            try {
                $permission = in_array(($_POST['permission'] ?? 'view'), ['view','edit','manage'], true) ? $_POST['permission'] : 'view';
                $stmt = db()->prepare('INSERT INTO project_shares (project_id, user_id, permission) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE permission = VALUES(permission)');
                $stmt->execute([$projectId, $shareUserId, $permission]);
                $shareMessage = 'Projekt wurde geteilt.';
            } catch (Throwable $e) {
                $shareError = 'Projekt konnte nicht geteilt werden.';
            }
        } else {
            $shareError = 'Bitte einen gültigen Nutzer auswählen.';
        }
    }
    if ($action === 'unshare_project') {
        $shareId = (int)($_POST['share_id'] ?? 0);
        db()->prepare('DELETE FROM project_shares WHERE id = ? AND project_id = ?')->execute([$shareId, $projectId]);
        $shareMessage = 'Freigabe wurde entfernt.';
    }
    if ($action === 'update_share_permission') {
        $shareId = (int)($_POST['share_id'] ?? 0);
        $permission = in_array(($_POST['permission'] ?? 'view'), ['view','edit','manage'], true) ? $_POST['permission'] : 'view';
        db()->prepare('UPDATE project_shares SET permission = ? WHERE id = ? AND project_id = ?')->execute([$permission, $shareId, $projectId]);
        $shareMessage = 'Freigaberecht wurde geändert.';
    }
    if ($action === 'transfer_owner') {
        $newOwnerId = (int)($_POST['new_owner_id'] ?? 0);
        if ($newOwnerId > 0 && $newOwnerId !== (int)$user['id']) {
            $stmt = db()->prepare('SELECT id FROM users WHERE id = ? AND active = 1 LIMIT 1');
            $stmt->execute([$newOwnerId]);
            if ((int)$stmt->fetchColumn() === $newOwnerId) {
                $pdo = db();
                $pdo->beginTransaction();
                try {
                    $pdo->prepare('UPDATE projects SET user_id = ? WHERE id = ?')->execute([$newOwnerId, $projectId]);
                    $pdo->prepare('DELETE FROM project_shares WHERE project_id = ? AND user_id = ?')->execute([$projectId, $newOwnerId]);
                    $pdo->prepare('INSERT INTO project_shares (project_id, user_id, permission) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE permission = VALUES(permission)')->execute([$projectId, (int)$user['id'], 'manage']);
                    $pdo->commit();
                    header('Location: project?id=' . $projectId); exit;
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) { $pdo->rollBack(); }
                    $shareError = 'Besitzer konnte nicht übertragen werden.';
                }
            } else {
                $shareError = 'Bitte einen gültigen neuen Besitzer auswählen.';
            }
        } else {
            $shareError = 'Bitte einen anderen Nutzer als neuen Besitzer auswählen.';
        }
    }
    if ($action === 'enable_public_share') {
        $token = $project['public_share_token'] ?? '';
        if (!$token) { $token = generate_share_token(); }
        db()->prepare('UPDATE projects SET public_share_token = ?, public_share_enabled = 1 WHERE id = ?')->execute([$token, $projectId]);
        $project = user_project($projectId, (int)$user['id']);
        $shareMessage = 'Web-Freigabe wurde aktiviert.';
    }
    if ($action === 'disable_public_share') {
        db()->prepare('UPDATE projects SET public_share_enabled = 0 WHERE id = ?')->execute([$projectId]);
        $project = user_project($projectId, (int)$user['id']);
        $shareMessage = 'Web-Freigabe wurde deaktiviert.';
    }
    if ($action === 'regenerate_public_share') {
        $token = generate_share_token();
        db()->prepare('UPDATE projects SET public_share_token = ?, public_share_enabled = 1 WHERE id = ?')->execute([$token, $projectId]);
        $project = user_project($projectId, (int)$user['id']);
        $shareMessage = 'Web-Link wurde neu erstellt.';
    }
    if ($action === 'update_public_share') {
        $expires = trim((string)($_POST['public_share_expires_at'] ?? ''));
        $expiresValue = $expires !== '' ? str_replace('T', ' ', $expires) . ':00' : null;
        $password = (string)($_POST['public_share_password'] ?? '');
        $clearPassword = !empty($_POST['clear_public_share_password']);
        if ($password !== '') {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            db()->prepare('UPDATE projects SET public_share_expires_at = ?, public_share_password_hash = ? WHERE id = ?')->execute([$expiresValue, $hash, $projectId]);
        } elseif ($clearPassword) {
            db()->prepare('UPDATE projects SET public_share_expires_at = ?, public_share_password_hash = NULL WHERE id = ?')->execute([$expiresValue, $projectId]);
        } else {
            db()->prepare('UPDATE projects SET public_share_expires_at = ? WHERE id = ?')->execute([$expiresValue, $projectId]);
        }
        $project = user_project($projectId, (int)$user['id']);
        $shareMessage = 'Web-Freigabe wurde aktualisiert.';
    }
    if ($action === 'clear_public_share_expiry') {
        db()->prepare('UPDATE projects SET public_share_expires_at = NULL WHERE id = ?')->execute([$projectId]);
        $project = user_project($projectId, (int)$user['id']);
        $shareMessage = 'Ablaufdatum wurde entfernt.';
    }
}
$shareUsers = [];
$availableShareUsers = [];
if ($canManage) {
    $stmt = db()->prepare('SELECT ps.id AS share_id, ps.permission, u.id, u.name, u.email FROM project_shares ps JOIN users u ON u.id = ps.user_id WHERE ps.project_id = ? ORDER BY u.name, u.email');
    $stmt->execute([$projectId]);
    $shareUsers = $stmt->fetchAll();
    $stmt = db()->prepare('SELECT u.id, u.name, u.email FROM users u WHERE u.id <> ? AND u.id <> ? AND u.active = 1 AND NOT EXISTS (SELECT 1 FROM project_shares ps WHERE ps.project_id = ? AND ps.user_id = u.id) ORDER BY u.name, u.email');
    $stmt->execute([(int)$user['id'], (int)$project['user_id'], $projectId]);
    $availableShareUsers = $stmt->fetchAll();
}
$allTags = all_project_tags();
$currentProjectTags = project_tags($projectId);
$currentProjectTagIds = array_map(fn($t) => (int)$t['id'], $currentProjectTags);
$companyLogo = setting_get('company_logo');
$pageTitle = $project['name'] . ' · Planung'; $activePage = 'projects'; $pageScript = 'assets/js/app.js'; require __DIR__ . '/inc/header.php';
?>
<script>window.APP_PROJECT_ID = <?= (int)$project['id'] ?>; window.APP_CAN_EDIT = <?= $canEdit ? 'true' : 'false' ?>; window.APP_PROJECT_ARCHIVED = <?= $isArchived ? 'true' : 'false' ?>;</script>
<main class="container py-4 flex-grow-1">
  <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-4">
    <div><h1 class="h3 mb-1"><?= e($project['name']) ?></h1><div class="small-muted"><?= e($project['client'] ?: 'Kein Kunde') ?> · <?= e($project['technician'] ?: 'Kein Techniker') ?></div><div class="mt-2"><span class="badge text-bg-<?= e(project_status_badge($project['status'] ?? 'planning')) ?>"><?= e(project_status_label($project['status'] ?? 'planning')) ?></span><?php foreach ($currentProjectTags as $tag): ?> <span class="badge text-bg-<?= e($tag['color'] ?: 'secondary') ?>"><?= e($tag['name']) ?></span><?php endforeach; ?></div></div>
    <a href="<?= e(app_url('projects')) ?>" class="btn btn-outline-secondary">Zur Projektliste</a>
  </div>
  <?php if (!$isOwner): ?><div class="alert alert-info">Dieses Projekt wurde von <?= e($project['owner_name'] ?? '') ?> mit dir geteilt. Berechtigung: <?= e(project_permission_label($project['permission'] ?? 'view')) ?>.</div><?php endif; ?>
  <?php if ($isArchived): ?><div class="alert alert-warning"><i class="bi bi-archive me-2"></i>Dieses Projekt ist archiviert und schreibgeschützt. Reaktiviere es in der Projektübersicht, um Änderungen vorzunehmen.</div><?php elseif (!$canEdit): ?><div class="alert alert-warning">Du hast nur Leserechte. Änderungen sind gesperrt.</div><?php endif; ?>
  <?php if ($shareMessage): ?><div class="alert alert-success"><?= e($shareMessage) ?></div><?php endif; ?>
  <?php if ($shareError): ?><div class="alert alert-danger"><?= e($shareError) ?></div><?php endif; ?>
  <?php if (isset($_GET['imported'])): ?><div class="alert alert-success">Projekt wurde erfolgreich importiert.</div><?php endif; ?>
  <?php if (isset($_GET['duplicated'])): ?><div class="alert alert-success">Projekt wurde dupliziert.</div><?php endif; ?>

  <?php if ($canManage): ?>
  <div class="card p-4 mb-4">
    <h2 class="h4 mb-3"><i class="bi bi-sliders me-2"></i>Projektdaten</h2>
    <form method="post" class="row g-3">
      <input type="hidden" name="action" value="update_project_meta">
      <div class="col-md-6"><label class="form-label">Projektname</label><input class="form-control" name="name" value="<?= e($project['name']) ?>" required <?= $isArchived ? 'disabled' : '' ?>></div>
      <div class="col-md-3"><label class="form-label">Kunde</label><input class="form-control" name="client" value="<?= e($project['client'] ?? '') ?>" <?= $isArchived ? 'disabled' : '' ?>></div>
      <div class="col-md-3"><label class="form-label">Techniker</label><input class="form-control" name="technician" value="<?= e($project['technician'] ?? '') ?>" <?= $isArchived ? 'disabled' : '' ?>></div>
      <div class="col-md-4"><label class="form-label">Status</label><select class="form-select" name="status" <?= $isArchived ? 'disabled' : '' ?>><?php foreach (project_status_options() as $key => $label): ?><option value="<?= e($key) ?>" <?= ($project['status'] ?? 'planning') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-6"><label class="form-label">Tags</label><select class="form-select" name="tag_ids[]" multiple size="3" <?= $isArchived ? 'disabled' : '' ?>><?php foreach ($allTags as $tag): ?><option value="<?= (int)$tag['id'] ?>" <?= in_array((int)$tag['id'], $currentProjectTagIds, true) ? 'selected' : '' ?>><?= e($tag['name']) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100" <?= $isArchived ? 'disabled' : '' ?>>Speichern</button></div>
    </form>
  </div>
  <?php endif; ?>
  <div class="card p-3 p-md-4 mb-4">
    <div class="row g-3 align-items-end">
      <div class="col-md-4"><label class="form-label">Aktiver Stromkreis</label><select class="form-select" id="activeCircuitSelect"></select></div>
      <div class="col-md-5"><label class="form-label">Neuen Stromkreis anlegen</label><div class="input-group"><input class="form-control" id="newCircuitName" placeholder="z. B. Fronttruss"><button class="btn btn-outline-primary" id="addCircuit" type="button" <?= !$canEdit ? 'disabled' : '' ?>>Anlegen</button></div></div>
      <div class="col-md-3"><button class="btn btn-outline-danger w-100" id="deleteCircuit" type="button" <?= !$canEdit ? 'disabled' : '' ?>>Stromkreis löschen</button></div>
    </div>
  </div>
  <div class="row g-4">
    <div class="col-lg-4"><div class="card p-4 sticky-lg-top planner-form-card">
      <h2 class="h4">Gerät hinzufügen</h2>
      <form id="loadForm" class="row g-3">
        <div class="col-12"><label class="form-label">Gerät</label><div class="dropdown w-100 device-dropdown"><button class="btn btn-outline-secondary dropdown-toggle w-100 text-start" type="button" id="deviceDropdownButton" data-bs-toggle="dropdown" data-bs-auto-close="outside"><span id="deviceDropdownLabel">Gerät suchen oder auswählen...</span></button><div class="dropdown-menu w-100 p-2 device-dropdown-menu"><input type="search" class="form-control form-control-sm mb-2" id="deviceSearch" placeholder="Gerät filtern..."><div id="deviceOptions" class="device-options list-group list-group-flush"></div></div></div><input type="hidden" id="deviceSelect" required></div>
        <div class="col-12"><label class="form-label">Kategorie-Filter</label><select class="form-select" id="categoryFilter"><option value="">Alle Kategorien</option></select></div>
        <div class="col-md-4"><label class="form-label">Anzahl</label><input type="number" class="form-control" id="quantity" min="1" value="1" required></div>
        <div class="col-md-4"><label class="form-label">Phase</label><select class="form-select" id="phase"><option>L1</option><option>L2</option><option>L3</option></select></div>
        <div class="col-md-4"><label class="form-label">Spannung</label><input type="number" class="form-control" id="voltage" value="230" min="1"></div>
        <div class="col-12"><label class="form-label">Stromkreis</label><select class="form-select" id="circuitSelect"></select></div>
        <div class="col-12"><label class="form-label">Bemerkungen</label><textarea class="form-control" id="remarks" rows="2"></textarea></div>
        <div class="col-12"><button class="btn btn-primary w-100" <?= !$canEdit ? 'disabled' : '' ?>>Zum Plan hinzufügen</button></div>
      </form>
      <hr><div class="d-flex gap-2 flex-wrap">
        <button class="btn btn-outline-primary btn-sm flex-fill" id="exportPdf" type="button">PDF exportieren</button>
        <a class="btn btn-outline-success btn-sm flex-fill" id="exportExcel" href="<?= e(app_url('export-excel?id=' . (int)$project['id'])) ?>">Excel exportieren</a>
        <a class="btn btn-outline-dark btn-sm flex-fill" href="<?= e(app_url('project-export?id=' . (int)$project['id'])) ?>"><i class="bi bi-download me-1"></i>Projekt exportieren</a>
        <?php if ($canManage && !empty($project['public_share_enabled']) && !empty($project['public_share_token'])): ?>
          <button class="btn btn-outline-info btn-sm flex-fill copy-public-link" type="button" data-link="<?= e(app_full_url('public-project?token=' . urlencode($project['public_share_token']))) ?>"><i class="bi bi-clipboard me-1"></i>Web-Link kopieren</button>
        <?php endif; ?>
        <button class="btn btn-outline-secondary btn-sm flex-fill" id="exportCsv" type="button">CSV exportieren</button>
        <button class="btn btn-outline-warning btn-sm flex-fill" id="autoDistribute" type="button" <?= !$canEdit ? 'disabled' : '' ?>>Automatisch verteilen</button>
        <button class="btn btn-outline-danger btn-sm flex-fill" id="clearPlan" type="button" <?= !$canEdit ? 'disabled' : '' ?>>Plan leeren</button>
      </div>
    </div></div>
    <div class="col-lg-8"><div class="d-flex justify-content-between align-items-end mb-3"><div><h2 class="h4 mb-0">Phasenplaner</h2><div class="small-muted">Geräte im aktiven Stromkreis per Drag & Drop zwischen L1 bis L3 verschieben.</div></div></div><div class="row g-3" id="phaseBoards"></div></div>
  </div>
  <div class="card p-4 mt-4"><h2 class="h4 mb-3">Aktueller Stromplan</h2><div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Gerät</th><th>Marke</th><th>Anzahl</th><th>Stromkreis</th><th>Phase</th><th>Leistung</th><th>Strom</th><th>Bemerkungen</th><th></th></tr></thead><tbody id="planRows"></tbody></table></div></div>
  <?php if ($canManage): ?>
  <div class="card p-4 mt-4">
    <h2 class="h4 mb-3"><i class="bi bi-globe2 me-2"></i>Web-URL teilen</h2>
    <p class="text-muted">Erstellt einen öffentlichen Nur-Lese-Link. Der Link funktioniert ohne Anmeldung.</p>
    <?php if (!empty($project['public_share_enabled']) && !empty($project['public_share_token'])): ?>
      <label class="form-label">Öffentlicher Link</label>
      <div class="input-group mb-3">
        <input class="form-control" id="publicShareUrl" readonly value="<?= e(app_full_url('public-project?token=' . urlencode($project['public_share_token']))) ?>" onclick="this.select()">
        <button class="btn btn-outline-primary copy-public-link" type="button" data-link="<?= e(app_full_url('public-project?token=' . urlencode($project['public_share_token']))) ?>"><i class="bi bi-clipboard me-1"></i>Kopieren</button>
      </div>
      <form method="post" class="row g-3 mb-3">
        <input type="hidden" name="action" value="update_public_share">
        <div class="col-md-4">
          <label class="form-label">Optional gültig bis</label>
          <input class="form-control" type="datetime-local" name="public_share_expires_at" value="<?= !empty($project['public_share_expires_at']) ? e(str_replace(' ', 'T', substr($project['public_share_expires_at'],0,16))) : '' ?>">
        </div>
        <div class="col-md-4"><label class="form-label">Optionales Passwort</label><input class="form-control" type="password" name="public_share_password" placeholder="leer lassen = unverändert"></div>
        <div class="col-md-2 d-flex align-items-end"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="clear_public_share_password" id="clearSharePassword"><label class="form-check-label small" for="clearSharePassword">Passwort entfernen</label></div></div>
        <div class="col-md-2 d-flex align-items-end"><button class="btn btn-outline-primary w-100">Speichern</button></div>
        <div class="col-12 small text-muted">Status: <?= !empty($project['public_share_password_hash']) ? 'Passwortschutz aktiv' : 'Kein Passwortschutz' ?><?= !empty($project['public_share_expires_at']) ? ' · gültig bis ' . e($project['public_share_expires_at']) : ' · kein Ablaufdatum' ?></div>
      </form>
      <div class="d-flex gap-2 flex-wrap">
        <?php if (!empty($project['public_share_expires_at'])): ?>
        <form method="post" data-confirm="Das Ablaufdatum wird entfernt. Der Web-Link bleibt aktiv, bis er manuell deaktiviert wird." data-confirm-title="Ablaufdatum entfernen" data-confirm-button="Entfernen">
          <input type="hidden" name="action" value="clear_public_share_expiry">
          <button class="btn btn-outline-warning">Ablaufdatum entfernen</button>
        </form>
        <?php endif; ?>
        <form method="post"><input type="hidden" name="action" value="disable_public_share"><button class="btn btn-outline-danger">Web-Link deaktivieren</button></form>
        <form method="post" data-confirm="Der bisherige Web-Link wird ungültig. Neuen Link erstellen?" data-confirm-title="Web-Link erneuern" data-confirm-button="Neu erstellen"><input type="hidden" name="action" value="regenerate_public_share"><button class="btn btn-outline-secondary">Link neu erstellen</button></form>
      </div>
    <?php else: ?>
      <form method="post"><input type="hidden" name="action" value="enable_public_share"><button class="btn btn-info">Web-Link aktivieren</button></form>
    <?php endif; ?>
  </div>
  <div class="card p-4 mt-4">
    <h2 class="h4 mb-3"><i class="bi bi-share me-2"></i>Projekt teilen</h2>
    <form method="post" class="row g-3 align-items-end mb-3">
      <input type="hidden" name="action" value="share_project">
      <div class="col-md-6">
        <label class="form-label">Nutzer auswählen</label>
        <select class="form-select" name="share_user_id" required>
          <option value="">Bitte wählen...</option>
          <?php foreach ($availableShareUsers as $shareUser): ?>
            <option value="<?= (int)$shareUser['id'] ?>"><?= e($shareUser['name']) ?> &lt;<?= e($shareUser['email']) ?>&gt;</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3"><label class="form-label">Recht</label><select class="form-select" name="permission"><option value="view">Nur ansehen</option><option value="edit">Bearbeiten</option><option value="manage">Verwalten</option></select></div>
      <div class="col-md-3"><button class="btn btn-primary w-100" <?= empty($availableShareUsers) ? 'disabled' : '' ?>>Teilen</button></div>
    </form>
    <?php if (!$shareUsers): ?>
      <p class="text-muted mb-0">Dieses Projekt ist aktuell mit keinem Nutzer geteilt.</p>
    <?php else: ?>
      <div class="list-group">
        <?php foreach ($shareUsers as $shareUser): ?>
          <div class="list-group-item d-flex flex-column flex-md-row justify-content-between gap-2 align-items-md-center">
            <div><strong><?= e($shareUser['name']) ?></strong><div class="small text-muted"><?= e($shareUser['email']) ?></div></div>
            <div class="d-flex gap-2 align-items-center flex-wrap">
              <form method="post" class="d-flex gap-2 align-items-center">
                <input type="hidden" name="action" value="update_share_permission">
                <input type="hidden" name="share_id" value="<?= (int)$shareUser['share_id'] ?>">
                <select class="form-select form-select-sm" name="permission" onchange="this.form.submit()">
                  <option value="view" <?= ($shareUser['permission'] ?? 'view') === 'view' ? 'selected' : '' ?>>Nur ansehen</option>
                  <option value="edit" <?= ($shareUser['permission'] ?? 'view') === 'edit' ? 'selected' : '' ?>>Bearbeiten</option>
                  <option value="manage" <?= ($shareUser['permission'] ?? 'view') === 'manage' ? 'selected' : '' ?>>Verwalten</option>
                </select>
              </form>
              <form method="post" data-confirm="Freigabe für diesen Nutzer entfernen?" data-confirm-title="Freigabe entfernen" data-confirm-button="Entfernen">
                <input type="hidden" name="action" value="unshare_project">
                <input type="hidden" name="share_id" value="<?= (int)$shareUser['share_id'] ?>">
                <button class="btn btn-sm btn-outline-danger">Freigabe entfernen</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if ($canOwner): ?>
      <hr>
      <h3 class="h5 mb-3"><i class="bi bi-person-check me-2"></i>Besitzer übertragen</h3>
      <p class="text-muted small">Nur der aktuelle Besitzer kann den Besitz übertragen. Du bleibst anschließend mit Verwaltungsrechten im Projekt.</p>
      <form method="post" class="row g-3 align-items-end" data-confirm="Besitz dieses Projekts wirklich übertragen?" data-confirm-title="Besitzer übertragen" data-confirm-button="Übertragen">
        <input type="hidden" name="action" value="transfer_owner">
        <div class="col-md-8"><label class="form-label">Neuer Besitzer</label><select class="form-select" name="new_owner_id" required><option value="">Bitte wählen...</option><?php foreach (array_merge($shareUsers, $availableShareUsers) as $ownerCandidate): ?><option value="<?= (int)$ownerCandidate['id'] ?>"><?= e($ownerCandidate['name']) ?> &lt;<?= e($ownerCandidate['email']) ?>&gt;</option><?php endforeach; ?></select></div>
        <div class="col-md-4"><button class="btn btn-outline-warning w-100">Besitz übertragen</button></div>
      </form>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <section id="printArea" class="print-area"><div class="print-header"><div class="print-title-wrap"><?php if ($companyLogo): ?><img class="print-logo" src="<?= e(app_url($companyLogo)) ?>" alt="Firmenlogo"><?php endif; ?><div><h1>Stromplan Übersicht</h1><p><?= e($project['name']) ?> · <?= e($project['client']) ?></p></div></div><div class="print-meta"><?= date('d.m.Y') ?></div>

      <?php if (!empty($project['public_share_enabled']) && !empty($project['public_share_token'])): ?>
        <div class="print-qr"><img src="<?= e(qr_png_url(app_full_url('public-project?token=' . urlencode($project['public_share_token'])), 120)) ?>" alt="QR-Code"><div>Web-Share</div></div>
      <?php endif; ?>
</div><div id="printSummary"></div><div id="printPhaseTables"></div><table class="print-table"><thead><tr><th>Gerät</th><th>Marke</th><th>Anzahl</th><th>Stromkreis</th><th>Phase</th><th>Leistung</th><th>Strom</th><th>Bemerkung</th></tr></thead><tbody id="printRows"></tbody></table></section>
</main>
<script>
document.addEventListener('click', async function (event) {
  const button = event.target.closest('.copy-public-link');
  if (!button) return;
  const link = button.dataset.link || document.getElementById('publicShareUrl')?.value || '';
  if (!link) return;
  try {
    await navigator.clipboard.writeText(link);
    if (window.AppUI && typeof window.AppUI.toast === 'function') {
      window.AppUI.toast('Web-Link wurde in die Zwischenablage kopiert.', 'success');
    } else {
      button.textContent = 'Kopiert';
      setTimeout(() => { button.innerHTML = '<i class="bi bi-clipboard me-1"></i>Kopieren'; }, 1600);
    }
  } catch (e) {
    const input = document.getElementById('publicShareUrl');
    if (input) { input.focus(); input.select(); }
    if (window.AppUI && typeof window.AppUI.toast === 'function') {
      window.AppUI.toast('Kopieren nicht möglich. Bitte den Link manuell markieren.', 'warning');
    }
  }
});
</script>

<?php require __DIR__ . '/inc/footer.php'; ?>
