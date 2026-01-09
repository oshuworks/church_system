<?php
$activePage = 'resources';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>iServe Resource Allocation</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
  <link href="css/navbar.css" rel="stylesheet">
  <link href="css/resource_page/resources.css" rel="stylesheet">
  <style>
    /* Notification Badge in Navbar */
    #notification-count {
      position: absolute;
      top: -5px;
      right: -5px;
      font-size: 0.65rem;
      padding: 2px 6px;
      border-radius: 10px;
      min-width: 18px;
      text-align: center;
      font-weight: 700;
    }

    /* Navbar dropdown notification items */
    #navbar-notifications-list .notification-item {
      white-space: normal;
      padding: 10px 12px;
      border-radius: 4px;
    }

    #navbar-notifications-list .notification-item:hover {
      background-color: #f8f9fa;
    }

    .floating-action-btn {
      position: fixed;
      bottom: 2rem;
      right: 2rem;
      width: 56px;
      height: 56px;
      border-radius: 50%;
      background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
      color: white;
      border: none;
      cursor: pointer;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
      transition: all 0.3s ease;
      z-index: 1000;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
    }
    .floating-action-btn:hover {
      transform: scale(1.1);
      box-shadow: 0 6px 20px rgba(0, 0, 0, 0.25);
    }
    .floating-action-btn:active {
      transform: scale(0.95);
    }
  </style>
</head>
<body style="background-color: #f0f4f8;">
  <?php include __DIR__ . '/includes/navbar.php'; ?>

  <main class="container my-4">
    <section class="row align-items-center mb-4">
      <div class="col-md-8">
        <h1 class="fw-bold text-primary mb-2">Resource Allocation</h1>
        <p class="text-muted mb-0">Monitor workforce, equipment, budgets, and alerts for every project.</p>
      </div>
      <div class="col-md-4 text-md-end mt-3 mt-md-0">
        <button class="btn btn-primary me-2" id="refresh-btn"><i class="fas fa-rotate me-1"></i>Refresh</button>
      </div>
    </section>

    <section class="row g-3 mb-4" id="summary-cards">
      <div class="col-md-3">
        <div class="card shadow-sm h-100">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <span class="text-uppercase text-muted small">Projects</span>
              <i class="fas fa-diagram-project text-primary"></i>
            </div>
            <h3 class="fw-bold mb-1" id="projects-total">--</h3>
            <p class="small text-muted mb-0"><span id="projects-active">--</span> active · <span id="projects-completed">--</span> completed</p>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card shadow-sm h-100">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <span class="text-uppercase text-muted small">Tasks</span>
              <i class="fas fa-tasks text-success"></i>
            </div>
            <h3 class="fw-bold mb-1" id="tasks-total">--</h3>
            <p class="small text-muted mb-0"><span id="tasks-in-progress">--</span> in progress · <span id="tasks-completed">--</span> done</p>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card shadow-sm h-100">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <span class="text-uppercase text-muted small">Budget</span>
              <i class="fas fa-wallet text-warning"></i>
            </div>
            <h3 class="fw-bold mb-1" id="budget-allocated">--</h3>
            <p class="small text-muted mb-0">Spent <span id="budget-spent">--</span></p>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card shadow-sm h-100">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <span class="text-uppercase text-muted small">Utilization</span>
              <i class="fas fa-chart-pie text-info"></i>
            </div>
            <h3 class="fw-bold mb-1" id="utilization-total">--</h3>
            <p class="small text-muted mb-0">
              <span class="badge bg-primary" id="utilization-under">0</span>
              <span class="badge bg-success" id="utilization-optimal">0</span>
              <span class="badge bg-warning text-dark" id="utilization-over">0</span>
              <span class="badge bg-danger" id="utilization-critical">0</span>
            </p>
          </div>
        </div>
      </div>
    </section>

    <section class="card shadow-sm mt-4">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div>
            <h5 class="mb-0">Utilization</h5>
            <small class="text-muted">Spot underutilized resources and relieve overloaded ones.</small>
          </div>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <h6 class="text-success">Underutilized (available)</h6>
            <div class="list-group small" id="underutilized-list">
              <div class="list-group-item text-muted">Loading...</div>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-2">
              <small class="text-muted" id="underutilized-count">0 items</small>
              <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-secondary" id="underutilized-prev" disabled>
                  <i class="fas fa-chevron-left"></i>
                </button>
                <button class="btn btn-outline-secondary" id="underutilized-next" disabled>
                  <i class="fas fa-chevron-right"></i>
                </button>
              </div>
            </div>
          </div>
          <div class="col-md-6 mb-3">
            <h6 class="text-danger">Overloaded / Critical</h6>
            <div class="list-group small" id="overloaded-list">
              <div class="list-group-item text-muted">Loading...</div>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-2">
              <small class="text-muted" id="overloaded-count">0 items</small>
              <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-secondary" id="overloaded-prev" disabled>
                  <i class="fas fa-chevron-left"></i>
                </button>
                <button class="btn btn-outline-secondary" id="overloaded-next" disabled>
                  <i class="fas fa-chevron-right"></i>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <br>

    <section class="card shadow-sm mb-4">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div>
            <h5 class="mb-0">Resource Assignments</h5>
            <small class="text-muted">Detailed view of all current resource allocations</small>
          </div>
          <div class="d-flex align-items-center">
            <button class="btn btn-outline-primary btn-sm me-2" id="assignments-refresh" title="Refresh Assignments">
              <i class="fas fa-sync-alt me-1"></i>Refresh
            </button>
            <select class="form-select form-select-sm d-inline-block w-auto me-2" id="assignment-filter">
              <option value="all">All Assignments</option>
              <option value="employee">Employees</option>
              <option value="equipment">Equipment</option>
            </select>
            <select class="form-select form-select-sm d-inline-block w-auto me-2" id="assignment-status">
              <option value="assigned">Assigned</option>
              <option value="completed">Completed</option>
              <option value="cancelled">Cancelled</option>
            </select>
            <button class="btn btn-outline-danger btn-sm me-2" id="delete-all-completed" style="display: none;" title="Delete All Completed">
              <i class="fas fa-trash-alt me-1"></i>Delete All Completed
            </button>
            <button class="btn btn-outline-danger btn-sm" id="delete-all-cancelled" style="display: none;" title="Delete All Cancelled">
              <i class="fas fa-trash-alt me-1"></i>Delete All Cancelled
            </button>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead>
              <tr>
                <th>Resource</th>
                <th>Type</th>
                <th>Project</th>
                <th>Task</th>
                <th class="text-center">Allocation</th>
                <th class="text-center">Hour</th>
                <th>Dates</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="assignments-list">
              <tr><td colspan="9" class="text-center text-muted py-4">Loading assignments...</td></tr>
            </tbody>
          </table>
        </div>
        <div class="d-flex justify-content-between align-items-center mt-2">
          <small class="text-muted" id="assignments-count">0 assignments</small>
          <div class="btn-group btn-group-sm">
            <button class="btn btn-outline-secondary" id="assignments-prev" disabled>
              <i class="fas fa-chevron-left"></i>
            </button>
            <button class="btn btn-outline-secondary" id="assignments-next" disabled>
              <i class="fas fa-chevron-right"></i>
            </button>
          </div>
        </div>
      </div>
    </section>

    

    <section class="card shadow-sm">
      <div class="card-body">
        <ul class="nav nav-pills mb-3" id="resource-tabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active resource-tab-btn" id="employees-tab" data-bs-toggle="pill" data-bs-target="#employees-pane" type="button" role="tab">
              <i class="fas fa-users me-2"></i>Employees
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link resource-tab-btn" id="equipment-tab" data-bs-toggle="pill" data-bs-target="#equipment-pane" type="button" role="tab">
              <i class="fas fa-tools me-2"></i>Equipment
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link resource-tab-btn" id="projects-tab" data-bs-toggle="pill" data-bs-target="#projects-pane" type="button" role="tab">
              <i class="fas fa-project-diagram me-2"></i>Projects
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link resource-tab-btn" id="tasks-tab" data-bs-toggle="pill" data-bs-target="#tasks-pane" type="button" role="tab">
              <i class="fas fa-tasks me-2"></i>Tasks
            </button>
          </li>
        </ul>

        <div class="tab-content">
          <div class="tab-pane fade show active" id="employees-pane" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h5 class="mb-0">Employees</h5>
              <input type="search" id="employee-search" class="form-control form-control-sm w-50" placeholder="Search name, role, department">
            </div>
            <div class="table-responsive">
              <table class="table table-hover align-middle">
                <thead>
                  <tr>
                    <th><i class="fas fa-user me-2"></i>Name</th>
                    <th><i class="fas fa-briefcase me-2"></i>Role</th>
                    <th><i class="fas fa-building me-2"></i>Department</th>
                    <th><i class="fas fa-circle me-2"></i>Status</th>
                    <th class="text-center"><i class="fas fa-tasks me-2"></i>Current Assignments</th>
                    <th class="text-center"><i class="fas fa-tachometer-alt me-2"></i>Utilization</th>
                  </tr>
                </thead>
                <tbody id="employees-body">
                  <tr><td colspan="6" class="text-center text-muted py-4">Loading employees...</td></tr>
                </tbody>
              </table>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-2">
              <small class="text-muted" id="employees-count">0 employees</small>
              <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-secondary" id="employees-prev" disabled>
                  <i class="fas fa-chevron-left"></i>
                </button>
                <button class="btn btn-outline-secondary" id="employees-next" disabled>
                  <i class="fas fa-chevron-right"></i>
                </button>
              </div>
            </div>
          </div>

          <div class="tab-pane fade" id="equipment-pane" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h5 class="mb-0">Equipment</h5>
              <input type="search" id="equipment-search" class="form-control form-control-sm w-50" placeholder="Search name, type, location">
            </div>
            <div class="table-responsive">
              <table class="table table-hover align-middle">
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th class="text-center">Current Assignments</th>
                    <th class="text-center">Utilization</th>
                  </tr>
                </thead>
                <tbody id="equipment-body">
                  <tr><td colspan="5" class="text-center text-muted py-4">Loading equipment...</td></tr>
                </tbody>
              </table>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-2">
              <small class="text-muted" id="equipment-count">0 equipment</small>
              <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-secondary" id="equipment-prev" disabled>
                  <i class="fas fa-chevron-left"></i>
                </button>
                <button class="btn btn-outline-secondary" id="equipment-next" disabled>
                  <i class="fas fa-chevron-right"></i>
                </button>
              </div>
            </div>
          </div>

          <div class="tab-pane fade" id="projects-pane" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h5 class="mb-0">Projects</h5>
            </div>
            <div class="table-responsive">
              <table class="table table-hover align-middle">
                <thead>
                  <tr>
                    <th>Project</th>
                    <th>Status</th>
                    <th>Priority</th>
                    <th>Dates</th>
                    <th class="text-center">Resources</th>
                  </tr>
                </thead>
                <tbody id="projects-body">
                  <tr><td colspan="5" class="text-center text-muted py-4">Loading projects...</td></tr>
                </tbody>
              </table>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-2">
              <small class="text-muted" id="projects-count">0 projects</small>
              <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-secondary" id="projects-prev" disabled>
                  <i class="fas fa-chevron-left"></i>
                </button>
                <button class="btn btn-outline-secondary" id="projects-next" disabled>
                  <i class="fas fa-chevron-right"></i>
                </button>
              </div>
            </div>
          </div>

          <div class="tab-pane fade" id="tasks-pane" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <div>
                <h5 class="mb-0">Tasks</h5>
                <span class="text-muted small">Cross-project task tracker</span>
              </div>
              <div class="d-flex align-items-center gap-2">
                <select class="form-select form-select-sm d-inline-block w-auto" id="tasks-project-filter">
                  <option value="0">All Projects</option>
                </select>
                <button class="btn btn-outline-secondary btn-sm" id="refresh-tasks-btn" title="Refresh Tasks">
                  <i class="fas fa-rotate"></i>
                </button>
              </div>
            </div>
            <div class="table-responsive">
              <table class="table table-hover align-middle">
                <thead>
                  <tr>
                    <th>Task</th>
                    <th>Project</th>
                    <th>Status</th>
                    <th>Priority</th>
                    <th>Dates</th>
                    <th>Progress</th>
                    <th class="text-center">Assigned Resources</th>
                  </tr>
                </thead>
                <tbody id="tasks-body">
                  <tr><td colspan="7" class="text-center text-muted py-4">Loading tasks...</td></tr>
                </tbody>
              </table>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-2">
              <small class="text-muted" id="tasks-count">0 tasks</small>
              <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-secondary" id="tasks-prev" disabled>
                  <i class="fas fa-chevron-left"></i>
                </button>
                <button class="btn btn-outline-secondary" id="tasks-next" disabled>
                  <i class="fas fa-chevron-right"></i>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </main>

  <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1080">
    <div id="toast-container"></div>
  </div>

  <!-- Floating Action Button -->
  <button type="button" class="floating-action-btn" data-bs-toggle="modal" data-bs-target="#assignResourceModal" title="Assign Resource">
    <i class="fas fa-plus"></i>
  </button>

  <!-- Assign Resource Modal -->
    <div class="modal fade" id="assignResourceModal" tabindex="-1" aria-labelledby="assignResourceLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title" id="assignResourceLabel"><i class="fas fa-plus-circle me-2"></i>Assign Resources to Tasks</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form id="assignment-form" class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Project</label>
              <select class="form-select" id="assign-project" required></select>
            </div>
            
            <div class="col-md-6">
              <label class="form-label">Assignment Type</label>
              <select class="form-select" id="assign-type" required>
                <option value="employee">Employee</option>
                <option value="equipment">Equipment</option>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label">Task</label>
              <select class="form-select" id="assign-task" required></select>
            </div>

            <div class="col-md-6">
              <label class="form-label">Resource</label>
              <select class="form-select" id="assign-resource" required></select>
            </div>

            <div class="col-md-4">
              <label class="form-label">Task Allocation %</label>
              <input type="number" class="form-control" id="assign-percentage" value="100" min="1" max="150" step="1" required>
            </div>

            <div class="col-md-4" id="hours-field-container">
              <label class="form-label" id="hours-label">Total Hours</label>
              <input type="number" class="form-control" id="assign-hours" value="10" min="1" step="0.01" required>
            </div>

            <div class="col-md-4">
              <label class="form-label">Start Date</label>
              <input type="date" class="form-control" id="assign-start" required>
            </div>

            <div class="col-md-12">
              <label class="form-label">End Date</label>
              <input type="date" class="form-control" id="assign-end" required>
            </div>

            <div class="col-12">
              <small class="text-muted" id="assign-availability-note">Select a project to load tasks and resources.</small>
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary" onclick="document.getElementById('assignment-form').dispatchEvent(new Event('submit'))">
            <i class="fas fa-plus-circle me-1"></i>Assign Resource
          </button>
        </div>
      </div>
    </div>
  </div>

  <?php include __DIR__ . '/includes/footer.php'; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Set BASE_URL for notification system
    window.BASE_URL = '/church_system-main_m';
  </script>
  <script src="js/main.js"></script>
  <script src="js/resources_page/resources.js"></script>
  <script>
    // Load notification count on page load
    document.addEventListener('DOMContentLoaded', function() {
      if (typeof updateNavbarNotificationCount === 'function') {
        updateNavbarNotificationCount();
      }
    });
  </script>
</body>
</html>

