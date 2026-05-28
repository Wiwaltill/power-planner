<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/helpers.php';
if (current_user()) { header('Location: projects'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = db()->prepare('SELECT * FROM users WHERE email = ? AND active = 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        login_user((int)$user['id']);
        header('Location: projects'); exit;
    }
    $error = 'E-Mail oder Passwort ist falsch.';
}
$pageTitle = 'Login'; $activePage = 'login'; require __DIR__ . '/inc/header.php';
?>
<main class="container py-5 flex-grow-1"><div class="row justify-content-center"><div class="col-md-5"><div class="card p-4">
<h1 class="h3 mb-3">Login</h1>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<form method="post" class="row g-3">
  <div class="col-12"><label class="form-label">E-Mail</label><input class="form-control" type="email" name="email" required></div>
  <div class="col-12"><label class="form-label">Passwort</label><input class="form-control" type="password" name="password" required></div>
  <div class="col-12"><button class="btn btn-primary w-100">Einloggen</button></div>
</form>
<p class="small-muted mt-3 mb-0">Zugänge werden durch einen Administrator angelegt.</p>
</div></div></div></main>
<?php require __DIR__ . '/inc/footer.php'; ?>
