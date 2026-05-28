<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/helpers.php';
if (current_user()) { header('Location: projects'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
        $error = 'Bitte Name, gültige E-Mail und ein Passwort mit mindestens 6 Zeichen eingeben.';
    } else {
        try {
            $stmt = db()->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
            $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
            login_user((int)db()->lastInsertId());
            header('Location: projects'); exit;
        } catch (PDOException $e) {
            $error = 'Diese E-Mail ist bereits registriert.';
        }
    }
}
$pageTitle = 'Registrieren'; $activePage = 'register'; require __DIR__ . '/inc/header.php';
?>
<main class="container py-5 flex-grow-1"><div class="row justify-content-center"><div class="col-md-5"><div class="card p-4">
<h1 class="h3 mb-3">Registrieren</h1>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<form method="post" class="row g-3">
  <div class="col-12"><label class="form-label">Name</label><input class="form-control" name="name" required></div>
  <div class="col-12"><label class="form-label">E-Mail</label><input class="form-control" type="email" name="email" required></div>
  <div class="col-12"><label class="form-label">Passwort</label><input class="form-control" type="password" name="password" minlength="6" required></div>
  <div class="col-12"><button class="btn btn-primary w-100">Konto erstellen</button></div>
</form>
<p class="small-muted mt-3 mb-0">Schon registriert? <a href="login">Einloggen</a></p>
</div></div></div></main>
<?php require __DIR__ . '/inc/footer.php'; ?>
