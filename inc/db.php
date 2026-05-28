<?php
$configFile = __DIR__ . '/../config/config.php';
if (!file_exists($configFile)) {
    if (basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'install.php') {
        header('Location: install');
        exit;
    }
} else {
    require_once $configFile;
}

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER')) {
            throw new RuntimeException('Die Anwendung ist noch nicht installiert.');
        }
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}
