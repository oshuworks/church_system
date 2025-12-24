
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resource Allocation Management - ERP System</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Custom CSS -->
    <link href="css/style.css" rel="stylesheet">
    
    <style>
        .utilization-card {
            transition: all 0.3s ease;
        }
        .utilization-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        .resource-progress {
            height: 8px;
        }
        .alert-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }
        .alert-critical { background-color: #dc3545; }
        .alert-high { background-color: #fd7e14; }
        .alert-medium { background-color: #ffc107; }
        .alert-low { background-color: #28a745; }
        .metric-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .balance-score {
            font-size: 2rem;
            font-weight: bold;
        }
        .resource-item {
            border-left: 4px solid #007bff;
            padding-left: 1rem;
            margin-bottom: 0.5rem;
        }
        .conflict-item {
            border-left: 4px solid #dc3545;
            background-color: #f8d7da;
            padding: 0.5rem;
            margin-bottom: 0.5rem;
            border-radius: 0.25rem;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container-fluid mt-4">
        <!-- Header Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h3 mb-0">Resource Allocation Management</h1>
                        <p class="text-muted mb-0">Manage and optimize resource distribution across projects</p>
                    </div>
                    <div>
                        <button class="btn btn-primary" onclick="openAssignModal()">
                            <i class="fas fa-plus me-2"></i>Assign Resource
                        </button>
                        <button class="btn btn-success ms-2" onclick="autoAssignResources()">
                            <i class="fas fa-magic me-2"></i>Auto Assign
                        </button>
                        <button class="btn btn-warning ms-2" onclick="balanceResources()">
                            <i class="fas fa-balance-scale me-2"></i>Balance Resources
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Key Metrics Dashboard -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card metric-card">
                    <div class="card-body text-center">
                        <div class="balance-score" id="balance-score">85%</div>
                        <p class="mb-0">Resource Balance Score</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-users me-2"></i>Active Resources</h5>
                        <h3 id="active-resources">24</h3>
                        <p class="card-text">Currently allocated</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-exclamation-triangle me-2"></i>Alerts</h5>
                        <h3 id="active-alerts">3</h3>
                        <p class="card-text">Need attention</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-chart-line me-2"></i>Avg Utilization</h5>
                        <h3 id="avg-utilization">72%</h3>
                        <p class="card-text">Across all resources</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs mb-4" id="mainTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="dashboard-tab" data-bs-toggle="tab" data-bs-target="#dashboard" type="button" role="tab">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="assignments-tab" data-bs-toggle="tab" data-bs-target="#assignments" type="button" role="tab">
                    <i class="fas fa-tasks me-2"></i>Assignments
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="monitoring-tab" data-bs-toggle="tab" data-bs-target="#monitoring" type="button" role="tab">
                    <i class="fas fa-chart-bar me-2"></i>Monitoring
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="balancing-tab" data-bs-toggle="tab" data-bs-target="#balancing" type="button" role="tab">
                    <i class="fas fa-balance-scale me-2"></i>Balancing
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="alerts-tab" data-bs-toggle="tab" data-bs-target="#alerts" type="button" role="tab">
                    <i class="fas fa-bell me-2"></i>Alerts
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="mainTabContent">
            <!-- Dashboard Tab -->
            <div class="tab-pane fade show active" id="dashboard" role="tabpanel">
                <div class="row">
                    <!-- Resource Utilization Chart -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Resource Utilization Overview</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="utilizationChart" height="100"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Resource Status</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="statusChart" height="150"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Assignments -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Recent Assignments</h5>
                                <button class="btn btn-sm btn-outline-primary" onclick="refreshAssignments()">
                                    <i class="fas fa-sync-alt me-1"></i>Refresh
                                </button>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover" id="recent-assignments-table">
                                        <thead>
                                            <tr>
                                                <th>Resource</th>
                                                <th>Type</th>
                                                <th>Task/Project</th>
                                                <th>Hours</th>
                                                <th>Utilization</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Populated by JavaScript -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Assignments Tab -->
            <div class="tab-pane fade" id="assignments" role="tabpanel">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">Resource Assignments</h5>
                                    <div>
                                        <select class="form-select form-select-sm d-inline-block w-auto me-2" id="filter-project">
                                            <option value="">All Projects</option>
                                        </select>
                                        <select class="form-select form-select-sm d-inline-block w-auto" id="filter-type">
                                            <option value="">All Types</option>
                                            <option value="employee">Employees</option>
                                            <option value="equipment">Equipment</option>
                                            <option value="budget">Budget</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover" id="assignments-table">
                                        <thead>
                                            <tr>
                                                <th>Resource Name</th>
                                                <th>Type</th>
                                                <th>Project</th>
                                                <th>Task</th>
                                                <th>Allocated Hours</th>
                                                <th>Actual Hours</th>
                                                <th>Start Date</th>
                                                <th>End Date</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Populated by JavaScript -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Monitoring Tab -->
            <div class="tab-pane fade" id="monitoring" role="tabpanel">
                <div class="row">
                    <!-- Resource Utilization Details -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Resource Utilization</h5>
                            </div>
                            <div class="card-body">
                                <div id="resource-utilization-list">
                                    <!-- Populated by JavaScript -->
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Workload Distribution -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Workload Distribution</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="workloadChart" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Capacity Analysis -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Capacity Analysis</h5>
                            </div>
                            <div class="card-body">
                                <div class="row" id="capacity-analysis">
                                    <!-- Populated by JavaScript -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Balancing Tab -->
            <div class="tab-pane fade" id="balancing" role="tabpanel">
                <div class="row">
                    <!-- Balance Recommendations -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Balance Recommendations</h5>
                                <button class="btn btn-sm btn-success" onclick="getRecommendations()">
                                    <i class="fas fa-sync-alt me-1"></i>Update
                                </button>
                            </div>
                            <div class="card-body">
                                <div id="recommendations-list">
                                    <!-- Populated by JavaScript -->
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Project Distribution -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Project Distribution</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="distributionChart" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Resource Conflicts -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Resource Conflicts</h5>
                            </div>
                            <div class="card-body">
                                <div id="conflicts-list">
                                    <!-- Populated by JavaScript -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Alerts Tab -->
            <div class="tab-pane fade" id="alerts" role="tabpanel">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Resource Alerts</h5>
                                <div>
                                    <select class="form-select form-select-sm d-inline-block w-auto me-2" id="alert-severity-filter">
                                        <option value="">All Severities</option>
                                        <option value="critical">Critical</option>
                                        <option value="high">High</option>
                                        <option value="medium">Medium</option>
                                        <option value="low">Low</option>
                                    </select>
                                    <button class="btn btn-sm btn-outline-primary" onclick="refreshAlerts()">
                                        <i class="fas fa-sync-alt me-1"></i>Refresh
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div id="alerts-list">
                                    <!-- Populated by JavaScript -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Assignment Modal -->
    <div class="modal fade" id="assignmentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Assign Resource to Task</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="assignment-form">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Project</label>
                                    <select class="form-select" id="project-select" required>
                                        <option value="">Select Project</option>
                                    </select>
                                    <div class="invalid-feedback" id="project-error"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Task</label>
                                    <select class="form-select" id="task-select" required>
                                        <option value="">Select Task</option>
                                    </select>
                                    <div class="invalid-feedback" id="task-error"></div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Resource Type</label>
                                    <select class="form-select" id="resource-type-select" required>
                                        <option value="">Select Type</option>
                                        <option value="employee">Employee</option>
                                        <option value="equipment">Equipment</option>
                                        <option value="budget">Budget</option>
                                    </select>
                                    <div class="invalid-feedback" id="resource-type-error"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Resource</label>
                                    <select class="form-select" id="resource-select" required>
                                        <option value="">Select Resource</option>
                                    </select>
                                    <div class="invalid-feedback" id="resource-error"></div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Allocated Hours</label>
                                    <input type="number" class="form-control" id="allocated-hours" step="0.1" min="0" required>
                                    <div class="invalid-feedback" id="allocated-hours-error"></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Allocation %</label>
                                    <input type="number" class="form-control" id="allocation-percentage" step="1" min="0" max="100" value="100">
                                    <div class="invalid-feedback" id="allocation-percentage-error"></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Start Date</label>
                                    <input type="date" class="form-control" id="start-date" required>
                                    <div class="invalid-feedback" id="start-date-error"></div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">End Date</label>
                                    <input type="date" class="form-control" id="end-date" required>
                                    <div class="invalid-feedback" id="end-date-error"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Notes</label>
                                    <textarea class="form-control" id="assignment-notes" rows="1"></textarea>
                                    <div class="invalid-feedback" id="assignment-notes-error"></div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveAssignment()">Assign Resource</button>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JavaScript -->
    <script src="js/main.js"></script>
</body>
</html>
