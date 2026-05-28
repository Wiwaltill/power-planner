<?php
require_once __DIR__ . '/inc/auth.php';
header('Location: ' . (current_user() ? 'projects' : 'login'));
exit;
