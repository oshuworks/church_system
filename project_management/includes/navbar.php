<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
  <div class="container-fluid">
    <a class="navbar-brand d-flex align-items-center" href="index.php">
      <img src="/church_system-main/project_management/includes/iServe transparent Icon.png" alt="iServe logo" height="36" class="me-2 rounded-1 bg-white p-1">
      <span class="fw-bold">iServe</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" href="/church_system-main/project_management/index.php"><i class="fas fa-project-diagram me-1"></i>Projects</a></li>
        <li class="nav-item"><a class="nav-link" href="/church_system-main/erp_project_management/resources.php"><i class="fas fa-users me-1"></i>ERP Resources</a></li>
        <li class="nav-item"><a class="nav-link" href="/church_system-main/budget_cost_tracking/budget_cost_tracking/budget.php"><i class="fas fa-dollar-sign me-1"></i>Budget Cost</a></li>
        <li class="nav-item"><a class="nav-link" href="/church_system-main/resource_allocation/resources.php"><i class="fas fa-tasks me-1"></i>Resource Allocation</a></li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="notificationsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fas fa-bell"></i>
            <span class="badge bg-dark" id="notification-count" style="display: none;">0</span>
          </a>
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationsDropdown" id="notifications-list">
            <li><h6 class="dropdown-header">Notifications</h6></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-muted" href="#" id="no-notifications">No new notifications</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-center" href="#" onclick="markAllNotificationsRead()">Mark all as read</a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>