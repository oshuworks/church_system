<?php
// admin_auth.php - handles admin login POST
if (session_status() == PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/admin.php';
require_once __DIR__ . '/auth_helpers.php';
$BASE_URL = '/church_system-main_m';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: {$BASE_URL}/admin_login.php");
    exit;
}
$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';
$ip = get_remote_ip();
// Check lockout
list($blocked, $secs) = is_ip_blocked($ip);
if ($blocked) {
    header("Location: {$BASE_URL}/admin_login.php?error=blocked&wait={$secs}");
    exit;
}
// Verify credentials
$ok = false;
if (!empty($ADMIN_PASS_HASH)) {
    if (password_verify($password, $ADMIN_PASS_HASH)) $ok = ($username === $ADMIN_USER);
} else {
    if ($username === $ADMIN_USER && hash_equals($ADMIN_PASS, $password)) $ok = true;
}
if ($ok) {
    // success: regenerate id and set session
    session_regenerate_id(true);
    $_SESSION['is_admin'] = true;
    $_SESSION['admin_user'] = $username;
    // clear failed attempts for this IP
    clear_failed_attempts($ip);
    // remember-me token
    if (!empty($_POST['remember'])){
        $token = generate_remember_token($username);
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie('remember_token', $token, [
            'expires' => time() + 30*24*3600,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
    header("Location: {$BASE_URL}/index.php");
    exit;
} else {
    // record failed attempt and redirect with error
    record_failed_attempt($ip);
    header("Location: {$BASE_URL}/admin_login.php?error=1");
    exit;
}
?>