<?php
if (session_status() == PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../includes/auth_helpers.php';
auto_login_from_remember();
$BASE_URL = $BASE_URL ?? '/church_system-main_m';
$loggedIn = $_SESSION['is_admin'] ?? false;
// Redirect unauthenticated users to login (allow login/auth/logout/setup)
$request = basename($_SERVER['PHP_SELF']);
if (!$loggedIn && !in_array($request, ['admin_login.php', 'admin_auth.php', 'logout.php', 'setup.php'])) {
    header("Location: {$BASE_URL}/admin_login.php");
    exit;
}
?>
<nav class="navbar navbar-expand-lg navbar-dark site-navbar shadow-sm">
  <?php $BASE_URL = '/church_system-main_m'; ?> 
  <div class="container-fluid">
    <a class="navbar-brand" href="<?php echo $BASE_URL; ?>/index.php">
      <i class="fas fa-church me-2"></i>iServe
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" href="<?php echo $BASE_URL; ?>/index.php"><i class="fas fa-project-diagram me-1"></i>Projects</a></li>
        <li class="nav-item"><a class="nav-link" href="<?php echo $BASE_URL; ?>/resource_allocation/resources.php"><i class="fas fa-users me-1"></i>Resources</a></li>
        <li class="nav-item"><a class="nav-link" href="<?php echo $BASE_URL; ?>/budget_cost_tracking/budget.php"><i class="fas fa-dollar-sign me-1"></i>Budget</a></li>
        <li class="nav-item"><a class="nav-link" href="<?php echo $BASE_URL; ?>/resource_allocation/resources.php"><i class="fas fa-tasks me-1"></i>Resource Allocation</a></li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="notificationsDropdown" role="button" data-bs-toggle="dropdown" onclick="loadNavbarNotifications()">
            <i class="fas fa-bell"></i>
            <span class="badge bg-danger" id="notification-count" style="display: none;">0</span>
          </a>
          <ul class="dropdown-menu dropdown-menu-end" style="min-width: 350px; max-height: 400px; overflow-y: auto;">
            <li><h6 class="dropdown-header d-flex justify-content-between align-items-center">
              Notifications
              <span class="badge bg-primary" id="navbar-unread-badge" style="display: none;">0</span>
            </h6></li>
            <li><hr class="dropdown-divider"></li>
            
            <!-- Notifications container -->
            <div id="navbar-notifications-list">
              <li class="text-center py-3"><i class="fas fa-spinner fa-spin"></i> Loading...</li>
            </div>
            
            <li><hr class="dropdown-divider"></li>
            <li class="d-flex gap-2 px-3">
              <a class="btn btn-sm btn-outline-primary flex-fill text-center" href="<?php echo $BASE_URL; ?>/notifications.php">
                <i class="fas fa-external-link-alt me-1"></i> View All
              </a>
              <button class="btn btn-sm btn-outline-secondary flex-fill" onclick="markAllNotificationsRead()">
                <i class="fas fa-check-double me-1"></i> Mark All
              </button>
            </li>
          </ul>
        </li>
        <?php if ($loggedIn): ?>
        <li class="nav-item">
          <a class="nav-link" href="<?php echo $BASE_URL; ?>/logout.php"><i class="fas fa-sign-out-alt me-1"></i> Logout</a>
        </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>
<script>
window.BASE_URL = '<?php echo $BASE_URL; ?>';

// Initialize navbar notification system
document.addEventListener('DOMContentLoaded', function() {
    // Load initial notification count
    if (typeof updateNavbarNotificationCount === 'function') {
        updateNavbarNotificationCount();
    }
    
    // Update count every 2 minutes
    setInterval(() => {
        if (typeof updateNavbarNotificationCount === 'function') {
            updateNavbarNotificationCount();
        }
    }, 120000);
});
</script>