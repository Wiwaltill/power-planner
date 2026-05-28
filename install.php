<?php
$configPath = __DIR__ . '/config/config.php';
$lockPath = __DIR__ . '/config/installed.lock';
if (file_exists($configPath) && file_exists($lockPath)) {
    header('Location: login');
    exit;
}
$error = '';
$success = false;
function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = trim($_POST['db_host'] ?? 'localhost');
    $dbName = trim($_POST['db_name'] ?? '');
    $dbUser = trim($_POST['db_user'] ?? '');
    $dbPass = (string)($_POST['db_pass'] ?? '');
    $appName = trim($_POST['app_name'] ?? 'Stromplaner');
    $githubUrl = trim($_POST['github_url'] ?? 'https://github.com/');
    $adminName = trim($_POST['admin_name'] ?? '');
    $adminEmail = trim($_POST['admin_email'] ?? '');
    $adminPass = (string)($_POST['admin_password'] ?? '');
    if (!$dbHost || !$dbName || !$dbUser || !$adminName || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL) || strlen($adminPass) < 8) {
        $error = 'Bitte alle Pflichtfelder ausfüllen. Das Admin-Passwort muss mindestens 8 Zeichen haben.';
    } else {
        try {
            $pdo = new PDO('mysql:host=' . $dbHost . ';dbname=' . $dbName . ';charset=utf8mb4', $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $sql = file_get_contents(__DIR__ . '/database/install.sql');
            $pdo->exec($sql);
            $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role, active) VALUES (?, ?, ?, "admin", 1)');
            $stmt->execute([$adminName, $adminEmail, password_hash($adminPass, PASSWORD_DEFAULT)]);
            if (!is_dir(__DIR__ . '/config')) mkdir(__DIR__ . '/config', 0755, true);
            $config = "<?php\n";
            $config .= "define('DB_HOST', " . var_export($dbHost, true) . ");\n";
            $config .= "define('DB_NAME', " . var_export($dbName, true) . ");\n";
            $config .= "define('DB_USER', " . var_export($dbUser, true) . ");\n";
            $config .= "define('DB_PASS', " . var_export($dbPass, true) . ");\n";
            $config .= "define('APP_NAME', " . var_export($appName ?: 'Stromplaner', true) . ");\n";
            $config .= "define('GITHUB_URL', " . var_export($githubUrl ?: 'https://github.com/', true) . ");\n";
            file_put_contents($configPath, $config);
            file_put_contents($lockPath, date('c'));
            $success = true;
        } catch (Throwable $e) {
            $error = 'Installation fehlgeschlagen: ' . $e->getMessage();
        }
    }
}
?>
<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Stromplaner installieren</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><main class="container py-5"><div class="row justify-content-center"><div class="col-lg-7"><div class="card shadow-sm"><div class="card-body p-4"><h1 class="h3 mb-3">Stromplaner installieren</h1><?php if ($success): ?><div class="alert alert-success">Installation abgeschlossen. Der erste Admin wurde angelegt.</div><a class="btn btn-primary" href="login">Zum Login</a><?php else: ?><?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?><form method="post" class="row g-3"><h2 class="h5">Datenbank</h2><div class="col-md-6"><label class="form-label">Host</label><input class="form-control" name="db_host" value="<?= h($_POST['db_host'] ?? 'localhost') ?>" required></div><div class="col-md-6"><label class="form-label">Datenbankname</label><input class="form-control" name="db_name" value="<?= h($_POST['db_name'] ?? '') ?>" required></div><div class="col-md-6"><label class="form-label">Datenbank-User</label><input class="form-control" name="db_user" value="<?= h($_POST['db_user'] ?? '') ?>" required></div><div class="col-md-6"><label class="form-label">Datenbank-Passwort</label><input class="form-control" type="password" name="db_pass"></div><hr><h2 class="h5">System</h2><div class="col-md-6"><label class="form-label">App-Name</label><input class="form-control" name="app_name" value="<?= h($_POST['app_name'] ?? 'Stromplaner') ?>"></div><div class="col-md-6"><label class="form-label">GitHub-Link</label><input class="form-control" name="github_url" value="<?= h($_POST['github_url'] ?? 'https://github.com/') ?>"></div><hr><h2 class="h5">Erster Admin</h2><div class="col-md-6"><label class="form-label">Name</label><input class="form-control" name="admin_name" value="<?= h($_POST['admin_name'] ?? '') ?>" required></div><div class="col-md-6"><label class="form-label">E-Mail</label><input class="form-control" type="email" name="admin_email" value="<?= h($_POST['admin_email'] ?? '') ?>" required></div><div class="col-12"><label class="form-label">Passwort</label><input class="form-control" type="password" name="admin_password" minlength="8" required></div><div class="col-12"><button class="btn btn-primary w-100">Installieren</button></div></form><?php endif; ?></div></div></div></div></main></body></html>
