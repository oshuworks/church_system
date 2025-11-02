<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
  <a class="navbar-brand" href="index.php">ERP Project Management</a>
  <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
    <span class="navbar-toggler-icon"></span>
  </button>
  <div class="collapse navbar-collapse" id="navbarNav">
    <ul class="navbar-nav ml-auto">
      <li class="nav-item"><a class="nav-link" href="index.php">Projects</a></li>
      <li class="nav-item"><a class="nav-link" href="resources.php">Resources</a></li>
      <li class="nav-item"><a class="nav-link" href="budget.php">Budget</a></li>
      <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" href="#" id="notificationsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
          <i class="fas fa-bell"></i>
          <span class="badge bg-danger" id="notification-count" style="display: none;">0</span>
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
</nav>