<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/helpers.php';
$user = require_login();
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'update_email') {
            $email = trim($_POST['email'] ?? '');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Bitte eine gültige E-Mail-Adresse eingeben.');
            }
            $stmt = db()->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
            $stmt->execute([$email, (int)$user['id']]);
            if ($stmt->fetch()) {
                throw new RuntimeException('Diese E-Mail-Adresse wird bereits verwendet.');
            }
            $stmt = db()->prepare('UPDATE users SET email = ? WHERE id = ?');
            $stmt->execute([$email, (int)$user['id']]);
            $success = 'E-Mail-Adresse wurde aktualisiert.';
            $user['email'] = $email;
        }
        if ($action === 'update_password') {
            $currentPassword = (string)($_POST['current_password'] ?? '');
            $newPassword = (string)($_POST['new_password'] ?? '');
            $newPasswordRepeat = (string)($_POST['new_password_repeat'] ?? '');
            if (strlen($newPassword) < 8) {
                throw new RuntimeException('Das neue Passwort muss mindestens 8 Zeichen haben.');
            }
            if ($newPassword !== $newPasswordRepeat) {
                throw new RuntimeException('Die neuen Passwörter stimmen nicht überein.');
            }
            $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([(int)$user['id']]);
            $hash = (string)$stmt->fetchColumn();
            if (!$hash || !password_verify($currentPassword, $hash)) {
                throw new RuntimeException('Das aktuelle Passwort ist falsch.');
            }
            $stmt = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), (int)$user['id']]);
            $success = 'Passwort wurde aktualisiert.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
$pageTitle = 'Profil';
$activePage = 'profile';
require __DIR__ . '/inc/header.php';
?>
<main class="container py-4 flex-grow-1">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h1 class="h3 mb-1">Mein Profil</h1>
      <div class="small-muted">E-Mail-Adresse und Passwort für deinen Zugang ändern.</div>
    </div>
  </div>
  <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
  <div class="row g-4">
    <div class="col-lg-6">
      <div class="card p-4 h-100">
        <h2 class="h5 mb-3">E-Mail-Adresse ändern</h2>
        <form method="post" class="row g-3">
          <input type="hidden" name="action" value="update_email">
          <div class="col-12">
            <label class="form-label">Name</label>
            <input class="form-control" value="<?= e($user['name']) ?>" disabled>
          </div>
          <div class="col-12">
            <label class="form-label">E-Mail-Adresse</label>
            <input class="form-control" type="email" name="email" value="<?= e($user['email']) ?>" required>
          </div>
          <div class="col-12">
            <button class="btn btn-primary">E-Mail speichern</button>
          </div>
        </form>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card p-4 h-100">
        <h2 class="h5 mb-3">Passwort ändern</h2>
        <form method="post" class="row g-3">
          <input type="hidden" name="action" value="update_password">
          <div class="col-12">
            <label class="form-label">Aktuelles Passwort</label>
            <input class="form-control" type="password" name="current_password" autocomplete="current-password" required>
          </div>
          <div class="col-12">
            <label class="form-label">Neues Passwort</label>
            <input class="form-control" type="password" name="new_password" minlength="8" autocomplete="new-password" required>
          </div>
          <div class="col-12">
            <label class="form-label">Neues Passwort wiederholen</label>
            <input class="form-control" type="password" name="new_password_repeat" minlength="8" autocomplete="new-password" required>
          </div>
          <div class="col-12">
            <button class="btn btn-primary">Passwort speichern</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</main>
<?php require __DIR__ . '/inc/footer.php'; ?>
