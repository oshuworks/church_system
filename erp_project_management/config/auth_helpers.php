<?php
// includes/auth_helpers.php
// Helper functions for admin auth: rate-limiting, remember-me tokens, and password management

// Path helpers
$CFG_DIR = __DIR__; // auth_helpers.php is already in config/ directory
$LOGIN_ATTEMPTS_FILE = $CFG_DIR . '/login_attempts.json';
$REMEMBER_FILE = $CFG_DIR . '/remember_tokens.json';
$ADMIN_FILE = $CFG_DIR . '/admin.php';

function _read_json_file($path) {
    if (!file_exists($path)) return [];
    $txt = @file_get_contents($path);
    $data = @json_decode($txt, true);
    return is_array($data) ? $data : [];
}
function _write_json_file($path, $data) {
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
}

// --- Rate limiting (by IP) ---
function get_remote_ip(){
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function record_failed_attempt($ip){
    global $LOGIN_ATTEMPTS_FILE;
    $data = _read_json_file($LOGIN_ATTEMPTS_FILE);
    $now = time();
    if (!isset($data[$ip])) $data[$ip] = [];
    $data[$ip][] = $now;
    // keep only last 100 attempts
    if (count($data[$ip]) > 100) $data[$ip] = array_slice($data[$ip], -100);
    _write_json_file($LOGIN_ATTEMPTS_FILE, $data);
}

function clear_failed_attempts($ip){
    global $LOGIN_ATTEMPTS_FILE;
    $data = _read_json_file($LOGIN_ATTEMPTS_FILE);
    if (isset($data[$ip])) unset($data[$ip]);
    _write_json_file($LOGIN_ATTEMPTS_FILE, $data);
}

function is_ip_blocked($ip, $threshold = 5, $windowSeconds = 900, $lockoutSeconds = 900){
    global $LOGIN_ATTEMPTS_FILE;
    $data = _read_json_file($LOGIN_ATTEMPTS_FILE);
    if (empty($data[$ip])) return [false, 0];
    $now = time();
    // remove old timestamps
    $attempts = array_filter($data[$ip], function($t) use ($now, $windowSeconds){ return ($now - $t) <= $windowSeconds; });
    $count = count($attempts);
    if ($count >= $threshold) {
        $last = max($attempts);
        $blockedUntil = $last + $lockoutSeconds;
        if ($now <= $blockedUntil) return [true, $blockedUntil - $now];
    }
    return [false, 0];
}

// --- Remember-me tokens ---
function generate_remember_token($username, $days = 30){
    global $REMEMBER_FILE;
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $data = _read_json_file($REMEMBER_FILE);
    $data[$hash] = ['user' => $username, 'expiry' => time() + ($days*24*3600)];
    _write_json_file($REMEMBER_FILE, $data);
    return $token;
}

function verify_remember_token($token){
    global $REMEMBER_FILE;
    if (empty($token)) return false;
    $hash = hash('sha256', $token);
    $data = _read_json_file($REMEMBER_FILE);
    if (empty($data[$hash])) return false;
    $rec = $data[$hash];
    if (time() > ($rec['expiry'] ?? 0)) { unset($data[$hash]); _write_json_file($REMEMBER_FILE,$data); return false; }
    return $rec['user'];
}

function delete_remember_token($token){
    global $REMEMBER_FILE;
    if (empty($token)) return;
    $hash = hash('sha256', $token);
    $data = _read_json_file($REMEMBER_FILE);
    if (isset($data[$hash])) { unset($data[$hash]); _write_json_file($REMEMBER_FILE,$data); }
}

// Auto-login from remember cookie (call early in includes)
function auto_login_from_remember(){
    // If headers already sent we cannot safely start a session; abort gracefully
    if (session_status() == PHP_SESSION_NONE) {
        if (!headers_sent()) session_start();
        else return false;
    }
    if (!empty($_SESSION['is_admin'])) return true;
    if (empty($_COOKIE['remember_token'])) return false;
    $token = $_COOKIE['remember_token'];
    $user = verify_remember_token($token);
    if ($user) {
        // verify user matches config (extra safety)
        require_once __DIR__ . '/admin.php';
        if ($user === ($ADMIN_USER ?? '')){
            session_regenerate_id(true);
            $_SESSION['is_admin'] = true;
            $_SESSION['admin_user'] = $user;
            return true;
        }
    }
    return false;
}

// --- Admin password helpers ---
function verify_admin_password($password){
    require __DIR__ . '/admin.php';
    if (!empty($ADMIN_PASS_HASH)) {
        return password_verify($password, $ADMIN_PASS_HASH) && ($ADMIN_USER ?? '') !== '';
    }
    // fallback to plain password
    return (isset($ADMIN_PASS) && hash_equals($ADMIN_PASS, $password));
}

function set_admin_password_hash($newHash){
    // Overwrite config/admin.php with the $ADMIN_USER and $ADMIN_PASS_HASH set. Keep $ADMIN_USER.
    require __DIR__ . '/admin.php';
    $user = addslashes($ADMIN_USER ?? 'admin');
    $hash = addslashes($newHash);
    $content = "<?php\n// Admin credentials - updated by UI\n\$ADMIN_USER = '{$user}';\n\$ADMIN_PASS = ''; // cleared - using hash\n\$ADMIN_PASS_HASH = '{$hash}';\n?>\n";
    file_put_contents(__DIR__ . '/admin.php', $content, LOCK_EX);
}

?>