<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
  <?php $BASE_URL = '/church_system-main/church_system-main'; ?> 
  <div class="container-fluid">
    <a class="navbar-brand d-flex align-items-center" href="<?php echo $BASE_URL; ?>/index.php">
      <img src="includes/iServe transparent Icon.png" alt="iServe logo" height="36" class="me-2 p-1">
      <span class="fw-bold">iServe</span>
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        
        <li class="nav-item">
          <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>" 
             href="<?php echo $BASE_URL; ?>/index.php">
            <i class="fas fa-project-diagram me-1"></i>Projects
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'resource.php') ? 'active' : ''; ?>" 
             href="<?php echo $BASE_URL; ?>/resource_allocation/resources.php">
            <i class="fas fa-users me-1"></i>Resources
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'budget.php') ? 'active' : ''; ?>" 
             href="<?php echo $BASE_URL; ?>/budget_cost_tracking/budget.php">
            <i class="fas fa-dollar-sign me-1"></i>Budget
          </a>
        </li>

        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="notificationsDropdown" role="button" data-bs-toggle="dropdown">
            <i class="fas fa-bell"></i>
            <span class="badge bg-danger" id="notification-count" style="display: none;">0</span>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><h6 class="dropdown-header">Notifications</h6></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-muted" href="#">No new notifications</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-center" href="#" onclick="markAllNotificationsRead()">Mark all as read</a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>
<script>window.BASE_URL = '<?php echo $BASE_URL; ?>';</script>