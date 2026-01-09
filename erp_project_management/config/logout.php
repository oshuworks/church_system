<?php
// logout.php - destroy admin session
if (session_status() == PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/auth_helpers.php';
// delete remember token if exists
if (!empty($_COOKIE['remember_token'])){
    delete_remember_token($_COOKIE['remember_token']);
    setcookie('remember_token', '', time()-3600, '/');
}
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();
$BASE_URL = '/church_system-main_m';
header("Location: {$BASE_URL}/admin_login.php");
exit;
?>