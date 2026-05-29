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
    $manageActions = ['share_project','unshare_project','update_share_permission','enable_public_share','disable_public_share','regenerate_public_share','update_public_share','clear_public_share_expiry','update_project_meta','archive_project','unarchive_project'];
    $ownerActions = ['transfer_owner','delete_project'];
    if ($action === 'leave_shared_project') {
        if (!$isOwner) {
            db()->prepare('DELETE FROM project_shares WHERE project_id = ? AND user_id = ?')->execute([$projectId, (int)$user['id']]);
            header('Location: projects?share_removed=1'); exit;
        }
        $shareError = 'Besitzer können ihre eigene Projektfreigabe nicht entfernen.';
        $action = '';
    }
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
    if ($action === 'archive_project') {
        if ($canManage && !$isArchived) {
            db()->prepare('UPDATE projects SET archived_at = NOW() WHERE id = ?')->execute([$projectId]);
            header('Location: projects?archived=1'); exit;
        }
        $shareError = 'Projekt konnte nicht archiviert werden.';
    }
    if ($action === 'unarchive_project') {
        if ($canManage && $isArchived) {
            db()->prepare('UPDATE projects SET archived_at = NULL WHERE id = ?')->execute([$projectId]);
            header('Location: project?id=' . $projectId . '&unarchived=1'); exit;
        }
        $shareError = 'Projekt konnte nicht reaktiviert werden.';
    }
    if ($action === 'delete_project') {
        $confirm = (string)($_POST['confirm_project_name'] ?? '');
        if ($canOwner && hash_equals((string)$project['name'], $confirm)) {
            db()->prepare('UPDATE projects SET deleted_at = NOW(), deleted_by = ? WHERE id = ? AND user_id = ?')->execute([(int)$user['id'], $projectId, (int)$user['id']]);
            header('Location: projects?deleted=1'); exit;
        }
        $shareError = 'Projekt konnte nicht gelöscht werden. Bitte Projektnamen exakt eingeben.';
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
<main class="container py-4 flex-grow-1 project-workspace">
  <section class="project-hero card p-3 p-md-4 mb-4">
    <div class="d-flex flex-column flex-lg-row gap-3 justify-content-between align-items-lg-start">
      <div>
        <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
          <span class="badge text-bg-<?= e(project_status_badge($project['status'] ?? 'planning')) ?>"><?= e(project_status_label($project['status'] ?? 'planning')) ?></span>
          <?php foreach ($currentProjectTags as $tag): ?><span class="badge text-bg-<?= e($tag['color'] ?: 'secondary') ?>"><?= e($tag['name']) ?></span><?php endforeach; ?>
          <?php if ($isArchived): ?><span class="badge text-bg-warning"><i class="bi bi-archive me-1"></i>Archiviert</span><?php endif; ?>
        </div>
        <h1 class="h2 mb-2"><?= e($project['name']) ?></h1>
        <div class="small-muted d-flex flex-wrap gap-3">
          <span><i class="bi bi-building me-1"></i><?= e($project['client'] ?: 'Kein Kunde') ?></span>
          <span><i class="bi bi-person-workspace me-1"></i><?= e($project['technician'] ?: 'Kein Techniker') ?></span>
          <span><i class="bi bi-shield-check me-1"></i><?= e(project_permission_label($project['permission'] ?? 'view')) ?></span>
        </div>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <button class="btn btn-primary" id="exportPdf" type="button"><i class="bi bi-filetype-pdf me-1"></i>PDF</button>
        <a class="btn btn-outline-success" id="exportExcel" href="<?= e(app_url('export-excel?id=' . (int)$project['id'])) ?>"><i class="bi bi-file-earmark-excel me-1"></i>Excel</a>
        <a href="<?= e(app_url('projects')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Projektliste</a>
      </div>
    </div>
  </section>

  <?php if (!$isOwner): ?><div class="alert alert-info">Dieses Projekt wurde von <?= e($project['owner_name'] ?? '') ?> mit dir geteilt. Berechtigung: <?= e(project_permission_label($project['permission'] ?? 'view')) ?>.</div><?php endif; ?>
  <?php if ($isArchived): ?><div class="alert alert-warning"><i class="bi bi-archive me-2"></i>Dieses Projekt ist archiviert und schreibgeschützt. Reaktiviere es in den Einstellungen, um Änderungen vorzunehmen.</div><?php elseif (!$canEdit): ?><div class="alert alert-warning">Du hast nur Leserechte. Änderungen sind gesperrt.</div><?php endif; ?>
  <?php if ($shareMessage): ?><div class="alert alert-success"><?= e($shareMessage) ?></div><?php endif; ?>
  <?php if ($shareError): ?><div class="alert alert-danger"><?= e($shareError) ?></div><?php endif; ?>
  <?php if (isset($_GET['imported'])): ?><div class="alert alert-success">Projekt wurde erfolgreich importiert.</div><?php endif; ?>
  <?php if (isset($_GET['duplicated'])): ?><div class="alert alert-success">Projekt wurde dupliziert.</div><?php endif; ?>
  <?php if (isset($_GET['unarchived'])): ?><div class="alert alert-success">Projekt wurde reaktiviert.</div><?php endif; ?>

  <ul class="nav nav-tabs project-tabs mb-4 overflow-auto flex-nowrap" id="projectTabs" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-overview" type="button"><i class="bi bi-speedometer2 me-1"></i>Übersicht</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-power" type="button"><i class="bi bi-lightning-charge me-1"></i>Stromplanung</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-devices" type="button"><i class="bi bi-hdd-rack me-1"></i>Geräte</button></li>
    <?php if ($canManage): ?><li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-shares" type="button"><i class="bi bi-people me-1"></i>Freigaben</button></li><?php endif; ?>
    <?php if ($canManage): ?><li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-web" type="button"><i class="bi bi-globe2 me-1"></i>Web-Freigabe</button></li><?php endif; ?>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-exports" type="button"><i class="bi bi-box-arrow-down me-1"></i>Exporte</button></li>
    <?php if ($canManage): ?><li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-settings" type="button"><i class="bi bi-gear me-1"></i>Einstellungen</button></li><?php endif; ?>
    <?php if (!$isOwner): ?><li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-my-share" type="button"><i class="bi bi-person-x me-1"></i>Meine Freigabe</button></li><?php endif; ?>
  </ul>

  <div class="tab-content">
    <section class="tab-pane fade show active" id="tab-overview">
      <div class="row g-4">
        <div class="col-lg-7"><div class="card p-4 h-100"><h2 class="h4 mb-3"><i class="bi bi-card-checklist me-2"></i>Zusammenfassung</h2><dl class="row mb-0 project-meta-list"><dt class="col-sm-4">Projektname</dt><dd class="col-sm-8"><?= e($project['name']) ?></dd><dt class="col-sm-4">Kunde</dt><dd class="col-sm-8"><?= e($project['client'] ?: 'Nicht angegeben') ?></dd><dt class="col-sm-4">Techniker</dt><dd class="col-sm-8"><?= e($project['technician'] ?: 'Nicht angegeben') ?></dd><dt class="col-sm-4">Status</dt><dd class="col-sm-8"><span class="badge text-bg-<?= e(project_status_badge($project['status'] ?? 'planning')) ?>"><?= e(project_status_label($project['status'] ?? 'planning')) ?></span></dd><dt class="col-sm-4">Tags</dt><dd class="col-sm-8"><?php if ($currentProjectTags): foreach ($currentProjectTags as $tag): ?><span class="badge text-bg-<?= e($tag['color'] ?: 'secondary') ?> me-1"><?= e($tag['name']) ?></span><?php endforeach; else: ?><span class="text-muted">Keine Tags</span><?php endif; ?></dd></dl></div></div>
        <div class="col-lg-5"><div class="card p-4 h-100"><h2 class="h4 mb-3"><i class="bi bi-bar-chart-line me-2"></i>Projektkennzahlen</h2><div class="row g-3"><div class="col-12 col-sm-6"><div class="metric-card"><span>Gesamtleistung</span><strong id="metricWatts">–</strong></div></div><div class="col-12 col-sm-6"><div class="metric-card"><span>Stromkreise genutzt</span><strong id="metricCircuits">–</strong></div></div></div></div></div>
      </div>
    </section>

    <section class="tab-pane fade" id="tab-power">
      <div class="card p-3 p-md-4 mb-4"><div class="row g-3 align-items-end"><div class="col-md-4"><label class="form-label">Aktiver Stromkreis</label><select class="form-select" id="activeCircuitSelect"></select></div><div class="col-md-5"><label class="form-label">Neuen Stromkreis anlegen</label><div class="input-group"><input class="form-control" id="newCircuitName" placeholder="z. B. Fronttruss"><button class="btn btn-outline-primary" id="addCircuit" type="button" <?= !$canEdit ? 'disabled' : '' ?>>Anlegen</button></div></div><div class="col-md-3"><button class="btn btn-outline-danger w-100" id="deleteCircuit" type="button" <?= !$canEdit ? 'disabled' : '' ?>>Stromkreis löschen</button></div></div></div>
      <div class="d-flex gap-2 flex-wrap mb-3"><button class="btn btn-outline-warning" id="autoDistribute" type="button" <?= !$canEdit ? 'disabled' : '' ?>><i class="bi bi-magic me-1"></i>Automatisch verteilen</button><button class="btn btn-outline-danger" id="clearPlan" type="button" <?= !$canEdit ? 'disabled' : '' ?>><i class="bi bi-trash3 me-1"></i>Plan leeren</button><button class="btn btn-outline-secondary" id="exportCsv" type="button"><i class="bi bi-filetype-csv me-1"></i>CSV exportieren</button></div>
      <div class="row g-4"><div class="col-lg-4"><div class="card p-4 sticky-lg-top planner-form-card"><h2 class="h4"><i class="bi bi-plus-circle me-2"></i>Gerät hinzufügen</h2><form id="loadForm" class="row g-3"><div class="col-12"><label class="form-label">Gerät</label><div class="dropdown w-100 device-dropdown"><button class="btn btn-outline-secondary dropdown-toggle w-100 text-start" type="button" id="deviceDropdownButton" data-bs-toggle="dropdown" data-bs-auto-close="outside"><span id="deviceDropdownLabel">Gerät suchen oder auswählen...</span></button><div class="dropdown-menu w-100 p-2 device-dropdown-menu"><input type="search" class="form-control form-control-sm mb-2" id="deviceSearch" placeholder="Gerät filtern..."><div id="deviceOptions" class="device-options list-group list-group-flush"></div></div></div><input type="hidden" id="deviceSelect" required></div><div class="col-12"><label class="form-label">Kategorie-Filter</label><select class="form-select" id="categoryFilter"><option value="">Alle Kategorien</option></select></div><div class="col-md-4"><label class="form-label">Anzahl</label><input type="number" class="form-control" id="quantity" min="1" value="1" required></div><div class="col-md-4"><label class="form-label">Phase</label><select class="form-select" id="phase"><option>L1</option><option>L2</option><option>L3</option></select></div><div class="col-md-4"><label class="form-label">Spannung</label><input type="number" class="form-control" id="voltage" value="230" min="1"></div><div class="col-12"><label class="form-label">Stromkreis</label><select class="form-select" id="circuitSelect"></select></div><div class="col-12"><label class="form-label">Bemerkungen</label><textarea class="form-control" id="remarks" rows="2"></textarea></div><div class="col-12"><button class="btn btn-primary w-100" <?= !$canEdit ? 'disabled' : '' ?>>Zum Plan hinzufügen</button></div></form></div></div><div class="col-lg-8"><div class="d-flex justify-content-between align-items-end mb-3"><div><h2 class="h4 mb-0">Phasenübersicht</h2><div class="small-muted">Geräte im aktiven Stromkreis per Drag & Drop zwischen L1 bis L3 verschieben.</div></div></div><div class="row g-3" id="phaseBoards"></div></div></div>
      <div class="card p-4 mt-4"><h2 class="h4 mb-3"><i class="bi bi-table me-2"></i>Lastberechnung</h2><div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Gerät</th><th>Marke</th><th>Anzahl</th><th>Stromkreis</th><th>Phase</th><th>Leistung</th><th>Strom</th><th>Bemerkungen</th><th></th></tr></thead><tbody id="planRows"></tbody></table></div></div>
    </section>

    <section class="tab-pane fade" id="tab-devices"><div class="card p-4"><div class="d-flex justify-content-between gap-2 flex-wrap align-items-center mb-3"><div><h2 class="h4 mb-0"><i class="bi bi-hdd-rack me-2"></i>Projektgeräte</h2><div class="small-muted">Alle im Projekt verwendeten Geräte inklusive Leistung, Phase und Stromkreis.</div></div></div><div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Gerät</th><th>Kategorie</th><th>Stromkreis</th><th>Phase</th><th>Anzahl</th><th>Leistung</th><th>Info</th></tr></thead><tbody id="projectDeviceRows"></tbody></table></div></div></section>

    <?php if ($canManage): ?><section class="tab-pane fade" id="tab-shares"><div class="card p-4"><h2 class="h4 mb-3"><i class="bi bi-share me-2"></i>Projekt mit Nutzern teilen</h2><form method="post" class="row g-3 align-items-end mb-3"><input type="hidden" name="action" value="share_project"><div class="col-md-6"><label class="form-label">Nutzer auswählen</label><select class="form-select" name="share_user_id" required><option value="">Bitte wählen...</option><?php foreach ($availableShareUsers as $shareUser): ?><option value="<?= (int)$shareUser['id'] ?>"><?= e($shareUser['name']) ?> &lt;<?= e($shareUser['email']) ?>&gt;</option><?php endforeach; ?></select></div><div class="col-md-3"><label class="form-label">Recht</label><select class="form-select" name="permission"><option value="view">Nur ansehen</option><option value="edit">Bearbeiten</option><option value="manage">Verwalten</option></select></div><div class="col-md-3"><button class="btn btn-primary w-100" <?= empty($availableShareUsers) ? 'disabled' : '' ?>>Teilen</button></div></form><?php if (!$shareUsers): ?><p class="text-muted mb-0">Dieses Projekt ist aktuell mit keinem Nutzer geteilt.</p><?php else: ?><div class="list-group"><?php foreach ($shareUsers as $shareUser): ?><div class="list-group-item d-flex flex-column flex-md-row justify-content-between gap-2 align-items-md-center"><div><strong><?= e($shareUser['name']) ?></strong><div class="small text-muted"><?= e($shareUser['email']) ?></div></div><div class="d-flex gap-2 align-items-center flex-wrap"><form method="post" class="d-flex gap-2 align-items-center"><input type="hidden" name="action" value="update_share_permission"><input type="hidden" name="share_id" value="<?= (int)$shareUser['share_id'] ?>"><select class="form-select form-select-sm" name="permission" onchange="this.form.submit()"><option value="view" <?= ($shareUser['permission'] ?? 'view') === 'view' ? 'selected' : '' ?>>Nur ansehen</option><option value="edit" <?= ($shareUser['permission'] ?? 'view') === 'edit' ? 'selected' : '' ?>>Bearbeiten</option><option value="manage" <?= ($shareUser['permission'] ?? 'view') === 'manage' ? 'selected' : '' ?>>Verwalten</option></select></form><form method="post" data-confirm="Freigabe für diesen Nutzer entfernen?" data-confirm-title="Freigabe entfernen" data-confirm-button="Entfernen"><input type="hidden" name="action" value="unshare_project"><input type="hidden" name="share_id" value="<?= (int)$shareUser['share_id'] ?>"><button class="btn btn-sm btn-outline-danger">Freigabe entfernen</button></form></div></div><?php endforeach; ?></div><?php endif; ?><?php if ($canOwner): ?><hr><h3 class="h5 mb-3"><i class="bi bi-person-check me-2"></i>Besitzer übertragen</h3><p class="text-muted small">Du bleibst anschließend mit Verwaltungsrechten im Projekt.</p><form method="post" class="row g-3 align-items-end" data-confirm="Besitz dieses Projekts wirklich übertragen?" data-confirm-title="Besitzer übertragen" data-confirm-button="Übertragen"><input type="hidden" name="action" value="transfer_owner"><div class="col-md-8"><label class="form-label">Neuer Besitzer</label><select class="form-select" name="new_owner_id" required><option value="">Bitte wählen...</option><?php foreach (array_merge($shareUsers, $availableShareUsers) as $ownerCandidate): ?><option value="<?= (int)$ownerCandidate['id'] ?>"><?= e($ownerCandidate['name']) ?> &lt;<?= e($ownerCandidate['email']) ?>&gt;</option><?php endforeach; ?></select></div><div class="col-md-4"><button class="btn btn-outline-warning w-100">Besitz übertragen</button></div></form><?php endif; ?></div></section><?php endif; ?>

    <?php if ($canManage): ?><section class="tab-pane fade" id="tab-web"><div class="card p-4"><h2 class="h4 mb-3"><i class="bi bi-globe2 me-2"></i>Web-Freigabe</h2><p class="text-muted">Öffentlicher Nur-Lese-Link mit optionalem Passwortschutz, Ablaufdatum und QR-Code.</p><?php if (!empty($project['public_share_enabled']) && !empty($project['public_share_token'])): ?><div class="row g-4"><div class="col-lg-8"><label class="form-label">Öffentlicher Link</label><div class="input-group mb-3"><input class="form-control" id="publicShareUrl" readonly value="<?= e(app_full_url('public-project?token=' . urlencode($project['public_share_token']))) ?>" onclick="this.select()"><button class="btn btn-outline-primary copy-public-link" type="button" data-link="<?= e(app_full_url('public-project?token=' . urlencode($project['public_share_token']))) ?>"><i class="bi bi-clipboard me-1"></i>Kopieren</button></div><form method="post" class="row g-3 mb-3"><input type="hidden" name="action" value="update_public_share"><div class="col-md-5"><label class="form-label">Gültig bis</label><input class="form-control" type="datetime-local" name="public_share_expires_at" value="<?= !empty($project['public_share_expires_at']) ? e(str_replace(' ', 'T', substr($project['public_share_expires_at'],0,16))) : '' ?>"></div><div class="col-md-5"><label class="form-label">Passwort</label><input class="form-control" type="password" name="public_share_password" placeholder="leer lassen = unverändert"></div><div class="col-md-2 d-flex align-items-end"><button class="btn btn-outline-primary w-100">Speichern</button></div><div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="clear_public_share_password" id="clearSharePassword"><label class="form-check-label" for="clearSharePassword">Passwort entfernen</label></div><div class="small text-muted mt-1">Status: <?= !empty($project['public_share_password_hash']) ? 'Passwortschutz aktiv' : 'Kein Passwortschutz' ?><?= !empty($project['public_share_expires_at']) ? ' · gültig bis ' . e($project['public_share_expires_at']) : ' · kein Ablaufdatum' ?></div></div></form><div class="d-flex gap-2 flex-wrap"><?php if (!empty($project['public_share_expires_at'])): ?><form method="post" data-confirm="Das Ablaufdatum wird entfernt. Der Web-Link bleibt aktiv, bis er manuell deaktiviert wird." data-confirm-title="Ablaufdatum entfernen" data-confirm-button="Entfernen"><input type="hidden" name="action" value="clear_public_share_expiry"><button class="btn btn-outline-warning">Ablaufdatum entfernen</button></form><?php endif; ?><form method="post"><input type="hidden" name="action" value="disable_public_share"><button class="btn btn-outline-danger">Web-Link deaktivieren</button></form><form method="post" data-confirm="Der bisherige Web-Link wird ungültig. Neuen Link erstellen?" data-confirm-title="Web-Link erneuern" data-confirm-button="Neu erstellen"><input type="hidden" name="action" value="regenerate_public_share"><button class="btn btn-outline-secondary">Link neu erstellen</button></form></div></div><div class="col-lg-4"><div class="qr-card text-center"><img src="<?= e(qr_png_url(app_full_url('public-project?token=' . urlencode($project['public_share_token'])), 220)) ?>" alt="QR-Code" class="img-fluid"><div class="small-muted mt-2">QR-Code zur Web-Freigabe</div></div></div></div><?php else: ?><form method="post"><input type="hidden" name="action" value="enable_public_share"><button class="btn btn-info"><i class="bi bi-globe2 me-1"></i>Web-Link aktivieren</button></form><?php endif; ?></div></section><?php endif; ?>

    <section class="tab-pane fade" id="tab-exports"><div class="row g-4"><div class="col-lg-6"><div class="card p-4 h-100"><h2 class="h4 mb-3"><i class="bi bi-box-arrow-down me-2"></i>Exportieren</h2><div class="d-grid gap-2"><button class="btn btn-outline-primary" type="button" onclick="document.getElementById('exportPdf').click()"><i class="bi bi-filetype-pdf me-1"></i>PDF exportieren</button><a class="btn btn-outline-success" href="<?= e(app_url('export-excel?id=' . (int)$project['id'])) ?>"><i class="bi bi-file-earmark-excel me-1"></i>Excel exportieren</a><a class="btn btn-outline-dark" href="<?= e(app_url('project-export?id=' . (int)$project['id'])) ?>"><i class="bi bi-download me-1"></i>Projekt exportieren</a><button class="btn btn-outline-secondary" type="button" onclick="document.getElementById('exportCsv').click()"><i class="bi bi-filetype-csv me-1"></i>CSV exportieren</button></div></div></div><div class="col-lg-6"><div class="card p-4 h-100"><h2 class="h4 mb-3"><i class="bi bi-box-arrow-in-up me-2"></i>Importieren</h2><?php if ($canManage): ?><form method="post" action="<?= e(app_url('project-import')) ?>" enctype="multipart/form-data" class="row g-3"><div class="col-12"><label class="form-label">Projektdatei importieren</label><input class="form-control" type="file" name="project_file" accept=".json,application/json" required></div><div class="col-12"><button class="btn btn-primary"><i class="bi bi-upload me-1"></i>Projekt importieren</button></div></form><?php else: ?><p class="text-muted mb-0">Import ist nur mit Verwaltungsrechten verfügbar.</p><?php endif; ?></div></div></div></section>

    <?php if ($canManage): ?><section class="tab-pane fade" id="tab-settings"><div class="row g-4"><div class="col-lg-8"><div class="card p-4"><h2 class="h4 mb-3"><i class="bi bi-sliders me-2"></i>Projektdaten</h2><form method="post" class="row g-3"><input type="hidden" name="action" value="update_project_meta"><div class="col-md-6"><label class="form-label">Projektname</label><input class="form-control" name="name" value="<?= e($project['name']) ?>" required <?= $isArchived ? 'disabled' : '' ?>></div><div class="col-md-3"><label class="form-label">Kunde</label><input class="form-control" name="client" value="<?= e($project['client'] ?? '') ?>" <?= $isArchived ? 'disabled' : '' ?>></div><div class="col-md-3"><label class="form-label">Techniker</label><input class="form-control" name="technician" value="<?= e($project['technician'] ?? '') ?>" <?= $isArchived ? 'disabled' : '' ?>></div><div class="col-md-4"><label class="form-label">Status</label><select class="form-select" name="status" <?= $isArchived ? 'disabled' : '' ?>><?php foreach (project_status_options() as $key => $label): ?><option value="<?= e($key) ?>" <?= ($project['status'] ?? 'planning') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div><div class="col-md-6"><label class="form-label">Tags</label><?php if ($allTags): ?><div class="tag-choice-group"><?php foreach ($allTags as $tag): $tagColor = $tag['color'] ?: 'secondary'; $tagId = (int)$tag['id']; ?><input class="btn-check" type="checkbox" name="tag_ids[]" value="<?= $tagId ?>" id="projectTag<?= $tagId ?>" autocomplete="off" <?= in_array($tagId, $currentProjectTagIds, true) ? 'checked' : '' ?> <?= $isArchived ? 'disabled' : '' ?>><label class="btn btn-sm btn-outline-<?= e($tagColor) ?> tag-choice" for="projectTag<?= $tagId ?>"><span class="badge rounded-pill text-bg-<?= e($tagColor) ?> me-1">&nbsp;</span><?= e($tag['name']) ?></label><?php endforeach; ?></div><?php else: ?><div class="form-text">Noch keine Tags angelegt.</div><?php endif; ?></div><div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100" <?= $isArchived ? 'disabled' : '' ?>>Speichern</button></div></form></div></div><div class="col-lg-4"><div class="card p-4 border-warning mb-4"><h2 class="h5 mb-3"><i class="bi bi-archive me-2"></i>Archiv</h2><?php if ($isArchived): ?><form method="post"><input type="hidden" name="action" value="unarchive_project"><button class="btn btn-outline-success w-100">Projekt reaktivieren</button></form><?php else: ?><form method="post" data-confirm="Projekt archivieren? Es wird schreibgeschützt und in den Archivbereich verschoben." data-confirm-title="Projekt archivieren" data-confirm-button="Archivieren"><input type="hidden" name="action" value="archive_project"><button class="btn btn-outline-warning w-100">Projekt archivieren</button></form><?php endif; ?></div><?php if ($canOwner): ?><div class="card p-4 border-danger"><h2 class="h5 mb-3"><i class="bi bi-trash3 me-2"></i>Löschen</h2><p class="small text-muted">Das Projekt wird in den Papierkorb verschoben. Zum Löschen bitte Projektnamen eingeben.</p><form method="post" data-confirm="Projekt wirklich in den Papierkorb verschieben?" data-confirm-title="Projekt löschen" data-confirm-button="Löschen"><input type="hidden" name="action" value="delete_project"><input class="form-control mb-2" name="confirm_project_name" placeholder="<?= e($project['name']) ?>" required><button class="btn btn-outline-danger w-100">Projekt löschen</button></form></div><?php endif; ?></div></div></section><?php endif; ?>

    <?php if (!$isOwner): ?><section class="tab-pane fade" id="tab-my-share"><div class="card p-4 border-danger"><h2 class="h4 mb-3"><i class="bi bi-person-x me-2"></i>Meine Freigabe</h2><p class="text-muted">Dieses Projekt wurde mit dir geteilt. Du kannst die Freigabe für dich entfernen; das Projekt verschwindet danach aus deiner Projektliste. Das Projekt selbst bleibt beim Besitzer unverändert erhalten.</p><dl class="row small mb-3"><dt class="col-sm-3">Besitzer</dt><dd class="col-sm-9"><?= e($project['owner_name'] ?? '') ?> &lt;<?= e($project['owner_email'] ?? '') ?>&gt;</dd><dt class="col-sm-3">Dein Recht</dt><dd class="col-sm-9"><?= e(project_permission_label($project['permission'] ?? 'view')) ?></dd></dl><form method="post" data-confirm="Diese Freigabe wird für dich entfernt. Das Projekt verschwindet aus deiner Projektliste." data-confirm-title="Freigabe entfernen" data-confirm-button="Entfernen"><input type="hidden" name="action" value="leave_shared_project"><button class="btn btn-outline-danger"><i class="bi bi-x-circle me-1"></i>Freigabe für mich entfernen</button></form></div></section><?php endif; ?>
  </div>

  <section id="printArea" class="print-area"><div class="print-header"><div class="print-title-wrap"><?php if ($companyLogo): ?><img class="print-logo" src="<?= e(app_url($companyLogo)) ?>" alt="Firmenlogo"><?php endif; ?><div><h1>Stromplan Übersicht</h1><p><?= e($project['name']) ?> · <?= e($project['client']) ?></p></div></div><div class="print-meta"><?= date('d.m.Y') ?></div><?php if (!empty($project['public_share_enabled']) && !empty($project['public_share_token'])): ?><div class="print-qr"><img src="<?= e(qr_png_url(app_full_url('public-project?token=' . urlencode($project['public_share_token'])), 120)) ?>" alt="QR-Code"><div>Web-Share</div></div><?php endif; ?></div><div id="printSummary"></div><div id="printPhaseTables"></div><table class="print-table"><thead><tr><th>Gerät</th><th>Marke</th><th>Anzahl</th><th>Stromkreis</th><th>Phase</th><th>Leistung</th><th>Strom</th><th>Bemerkung</th></tr></thead><tbody id="printRows"></tbody></table></section>
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
