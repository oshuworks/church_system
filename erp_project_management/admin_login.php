<?php
// admin_login.php - Admin-only login page
if (session_status() == PHP_SESSION_NONE) session_start();
$BASE_URL = '/church_system-main_m';
if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
    header("Location: {$BASE_URL}/index.php");
    exit;
}
$error = isset($_GET['error']) ? 'Invalid username or password' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login — iServe</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link rel="stylesheet" href="css/minimal.css" />
    <meta name="theme-color" content="#0b74de" />
</head>
<body>
  <div class="login-bg">
    <div class="login-card card p-4">
      <div class="login-brand">
        <img src="includes/iServe transparent Icon.png" alt="iServe logo" />
        <div>
          <div class="login-title">iServe Admin</div>
          <div class="login-sub">Sign in to manage projects and resources</div>
        </div>
      </div>
      <?php if ($error): ?>
        <div class="alert alert-danger small p-2 mb-3"><?=htmlspecialchars($error)?></div>
      <?php endif; ?>
      <form method="post" action="config/admin_auth.php" novalidate>
        <div class="mb-3">
          <label class="form-label">Username</label>
          <input name="username" class="form-control" required autofocus />
        </div>
        <div class="mb-3 position-relative">
          <label class="form-label">Password</label>
          <input id="password" name="password" type="password" class="form-control password-field" required />
        </div>
        <div class="mb-3 d-flex justify-content-start align-items-center">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="remember" id="rememberMe">
            <label class="form-check-label small" for="rememberMe">Remember me</label>
          </div>
        </div> 
        <div class="d-grid">
          <button class="btn btn-primary btn-lg">Sign in</button>
        </div>
      </form>
      <div class="login-footer">Default credentials: <strong>admin / admin123</strong> — change immediately in <code>config/admin.php</code></div>
    </div>
  </div>
  <?php include 'includes/footer.php'; ?>
</body> 
</html>