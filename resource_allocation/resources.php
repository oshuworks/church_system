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
  <link href="css/resources.css" rel="stylesheet">
</head>
<body>
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

    <section class="card shadow-sm mb-4">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div>
            <h5 class="mb-0">Assign Resources to Tasks</h5>
            <small class="text-muted">Keep teams balanced across projects.</small>
          </div>
        </div>

        <form id="assignment-form" class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Project</label>
            <select class="form-select" id="assign-project" required></select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Task</label>
            <select class="form-select" id="assign-task" required></select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Assignment Type</label>
            <select class="form-select" id="assign-type" required>
              <option value="employee">Employee</option>
              <option value="equipment">Equipment</option>
              <option value="budget">Budget</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Resource</label>
            <select class="form-select" id="assign-resource" required></select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Allocation %</label>
            <input type="number" class="form-control" id="assign-percentage" value="100" min="1" max="150" step="1" required>
          </div>
          <div class="col-md-3">
            <label class="form-label">Allocated Hours</label>
            <input type="number" class="form-control" id="assign-hours" value="10" min="1" step="1" required>
          </div>
          <div class="col-md-3">
            <label class="form-label">Start Date</label>
            <input type="date" class="form-control" id="assign-start" required>
          </div>
          <div class="col-md-3">
            <label class="form-label">End Date</label>
            <input type="date" class="form-control" id="assign-end" required>
          </div>
          <div class="col-12">
            <label class="form-label">Notes (optional)</label>
            <textarea class="form-control" id="assign-notes" rows="2" placeholder="Add context for this allocation"></textarea>
          </div>
          <div class="col-12 d-flex justify-content-between align-items-center">
            <small class="text-muted" id="assign-availability-note">Select a project to load tasks and resources.</small>
            <button type="submit" class="btn btn-primary"><i class="fas fa-plus-circle me-1"></i>Assign Resource</button>
          </div>
        </form>
      </div>
    </section>

    
    <section class="card shadow-sm mt-4">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div>
            <h5 class="mb-0">Utilization & Balancing</h5>
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
          <div>
            <select class="form-select form-select-sm d-inline-block w-auto me-2" id="assignment-filter">
              <option value="all">All Assignments</option>
              <option value="employee">Employees</option>
              <option value="equipment">Equipment</option>
              <option value="budget">Budgets</option>
            </select>
            <select class="form-select form-select-sm d-inline-block w-auto me-2" id="assignment-status">
              <option value="assigned">Active</option>
              <option value="completed">Completed</option>
              <option value="cancelled">Cancelled</option>
            </select>
            <button class="btn btn-outline-primary btn-sm" id="assignments-refresh"><i class="fas fa-sync me-1"></i>Refresh</button>
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
                <th class="text-center">Hours</th>
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
      </div>
    </section>

    

    <section class="card shadow-sm">
      <div class="card-body">
        <ul class="nav nav-pills mb-3" id="resource-tabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="employees-tab" data-bs-toggle="pill" data-bs-target="#employees-pane" type="button" role="tab">Employees</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="equipment-tab" data-bs-toggle="pill" data-bs-target="#equipment-pane" type="button" role="tab">Equipment</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="projects-tab" data-bs-toggle="pill" data-bs-target="#projects-pane" type="button" role="tab">Projects</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="tasks-tab" data-bs-toggle="pill" data-bs-target="#tasks-pane" type="button" role="tab">Tasks</button>
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
                    <th>Name</th>
                    <th>Role</th>
                    <th>Department</th>
                    <th>Status</th>
                    <th class="text-center">Current Assignments</th>
                    <th class="text-center">Utilization</th>
                    <th class="text-end">Rate</th>
                  </tr>
                </thead>
                <tbody id="employees-body">
                  <tr><td colspan="7" class="text-center text-muted py-4">Loading employees...</td></tr>
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
                    <th>Location</th>
                    <th>Status</th>
                    <th class="text-center">Current Assignments</th>
                    <th class="text-center">Utilization</th>
                    <th class="text-end">Hourly Rate</th>
                  </tr>
                </thead>
                <tbody id="equipment-body">
                  <tr><td colspan="7" class="text-center text-muted py-4">Loading equipment...</td></tr>
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
              <span class="text-muted small">Budget vs. spend with average progress</span>
            </div>
            <div class="table-responsive">
              <table class="table table-hover align-middle">
                <thead>
                  <tr>
                    <th>Project</th>
                    <th>Status</th>
                    <th>Priority</th>
                    <th>Dates</th>
                    <th class="text-center">Progress</th>
                    <th class="text-center">Resources</th>
                    <th class="text-end">Budget</th>
                  </tr>
                </thead>
                <tbody id="projects-body">
                  <tr><td colspan="7" class="text-center text-muted py-4">Loading projects...</td></tr>
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
              <h5 class="mb-0">Tasks</h5>
              <span class="text-muted small">Cross-project task tracker</span>
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
                    <th class="text-center">Progress</th>
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

  <?php include __DIR__ . '/includes/footer.php'; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/resources.js"></script>
</body>
</html>

