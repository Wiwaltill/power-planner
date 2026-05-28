<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/helpers.php';
$admin = require_admin();
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create') {
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = (string)($_POST['password'] ?? '');
            $role = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
            if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
                throw new RuntimeException('Bitte Name, gültige E-Mail und ein Passwort mit mindestens 8 Zeichen eingeben.');
            }
            $stmt = db()->prepare('INSERT INTO users (name, email, password_hash, role, active) VALUES (?, ?, ?, ?, 1)');
            $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
            $success = 'Nutzer wurde angelegt.';
        }
        if ($action === 'update') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $role = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
            $active = isset($_POST['active']) ? 1 : 0;
            if ($id === (int)$admin['id'] && $active === 0) throw new RuntimeException('Du kannst deinen eigenen Admin-Zugang nicht deaktivieren.');
            if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Bitte Name und gültige E-Mail eingeben.');
            $stmt = db()->prepare('UPDATE users SET name = ?, email = ?, role = ?, active = ? WHERE id = ?');
            $stmt->execute([$name, $email, $role, $active, $id]);
            if (!empty($_POST['password'])) {
                if (strlen((string)$_POST['password']) < 8) throw new RuntimeException('Das neue Passwort muss mindestens 8 Zeichen haben.');
                $stmt = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                $stmt->execute([password_hash((string)$_POST['password'], PASSWORD_DEFAULT), $id]);
            }
            $success = 'Nutzer wurde aktualisiert.';
        }
        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id === (int)$admin['id']) throw new RuntimeException('Du kannst deinen eigenen Admin-Zugang nicht löschen.');
            $stmt = db()->prepare('DELETE FROM users WHERE id = ?');
            $stmt->execute([$id]);
            $success = 'Nutzer wurde gelöscht.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
$users = db()->query('SELECT id, name, email, role, active, created_at FROM users ORDER BY created_at DESC')->fetchAll();
$pageTitle = 'Nutzerverwaltung'; $activePage = 'users'; require __DIR__ . '/inc/header.php';
?>
<main class="container py-4 flex-grow-1">
  <div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h3 mb-0">Nutzerverwaltung</h1></div>
  <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
  <div class="card p-3 mb-4"><h2 class="h5">Neuen Nutzer anlegen</h2><form method="post" class="row g-3"><input type="hidden" name="action" value="create"><div class="col-md-3"><label class="form-label">Name</label><input class="form-control" name="name" required></div><div class="col-md-3"><label class="form-label">E-Mail</label><input class="form-control" type="email" name="email" required></div><div class="col-md-3"><label class="form-label">Passwort</label><input class="form-control" type="password" name="password" minlength="8" required></div><div class="col-md-2"><label class="form-label">Rolle</label><select class="form-select" name="role"><option value="user">Nutzer</option><option value="admin">Admin</option></select></div><div class="col-md-1 d-flex align-items-end"><button class="btn btn-primary w-100">+</button></div></form></div>
  <div class="card p-3"><h2 class="h5">Bestehende Nutzer</h2><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Name</th><th>E-Mail</th><th>Rolle</th><th>Aktiv</th><th>Neues Passwort</th><th></th></tr></thead><tbody><?php foreach ($users as $u): ?><tr><form method="post"><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><td><input class="form-control" name="name" value="<?= e($u['name']) ?>"></td><td><input class="form-control" type="email" name="email" value="<?= e($u['email']) ?>"></td><td><select class="form-select" name="role"><option value="user" <?= $u['role']==='user'?'selected':'' ?>>Nutzer</option><option value="admin" <?= $u['role']==='admin'?'selected':'' ?>>Admin</option></select></td><td class="text-center"><input class="form-check-input" type="checkbox" name="active" <?= (int)$u['active'] ? 'checked' : '' ?>></td><td><input class="form-control" type="password" name="password" placeholder="leer lassen"></td><td class="text-end"><button class="btn btn-sm btn-outline-primary">Speichern</button></form><?php if ((int)$u['id'] !== (int)$admin['id']): ?><form method="post" class="d-inline" data-confirm="Nutzer wirklich löschen?" data-confirm-title="Nutzer löschen" data-confirm-button="Löschen"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><button class="btn btn-sm btn-outline-danger">Löschen</button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></div>
</main>
<?php require __DIR__ . '/inc/footer.php'; ?>
