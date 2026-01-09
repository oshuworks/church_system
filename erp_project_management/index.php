<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>iServe</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    
    <!-- Flatpickr -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <!-- Your UI theme -->
    <link rel="stylesheet" href="css/resources.css" />
    <link rel="stylesheet" href="css/navbar.css" />
    <link rel="stylesheet" href="css/style.css" />
</head>

<body class="bg-primary-bg" style="background-color: #f0f4f8;">

<?php include 'includes/navbar.php'; ?>

<div class="page-wrapper">
<div class="container mt-4">

    <!-- PROJECT LISTING -->
    <div id="projects-listing" class="card secondary-card p-4">

        <!-- HEADER -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-project-diagram me-2"></i>Project Management - iServe</h1>
            <div class="d-flex gap-2">
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-primary" id="projectListViewBtn" onclick="setProjectView('list')">
                        <i class="fas fa-list me-1"></i> Project List
                    </button>
                    <button type="button" class="btn btn-outline-dark" id="projectAnalyticsViewBtn" onclick="setProjectView('analytics')">
                        <i class="fas fa-chart-bar me-1"></i> Analytics
                    </button>
                </div>
                <a href="<?php echo $BASE_URL; ?>/notifications.php" class="btn btn-outline-primary position-relative" style="display: none;">
                    <i class="fas fa-bell me-1"></i> Notifications
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="header-notification-badge" style="display: none;">0</span>
                </a>
            </div>
        </div>

        <!-- SEARCH AND SORT -->
        <div class="row mb-3">
            <div class="col-md-8">
                <div class="position-relative">
                    <i class="fas fa-search position-absolute" style="left: 15px; top: 50%; transform: translateY(-50%); color: #000000;"></i>
                    <input type="text" class="form-control bg-dark text-dark border-secondary" 
                           id="projectSearchInput" placeholder="Search projects..." 
                           onkeyup="filterProjects()" style="padding-left: 40px;">
                </div>
            </div>
            <div class="col-md-4">
                <div class="d-flex align-items-center gap-2">
                    <label class="mb-0 small text-muted"><i class="fas fa-sort me-1"></i>Sort by:</label>
                    <select class="form-select form-select-sm" id="projectSortSelect" onchange="sortProjects()">
                        <option value="priority">Priority (Default)</option>
                        <option value="name-asc">Name (A-Z)</option>
                        <option value="name-desc">Name (Z-A)</option>
                        <option value="date-asc">Start Date (Oldest First)</option>
                        <option value="date-desc">Start Date (Newest First)</option>
                        <option value="end-date-asc">End Date (Oldest First)</option>
                        <option value="end-date-desc">End Date (Newest First)</option>
                        <option value="budget-asc">Budget (Low to High)</option>
                        <option value="budget-desc">Budget (High to Low)</option>
                        <option value="progress-asc">Progress (Low to High)</option>
                        <option value="progress-desc">Progress (High to Low)</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- PROJECT CARDS GRID -->
        <div id="projects-cards-container" class="row g-4">
            <?php
            include 'includes/db.php';
            $result = $conn->query("
                SELECT p.*, 
                       COUNT(DISTINCT t.task_id) as task_count,
                       COUNT(DISTINCT CASE WHEN t.progress_percentage = 100 THEN t.task_id END) as completed_tasks,
                       COUNT(DISTINCT CASE WHEN t.end_date < CURDATE() AND t.progress_percentage < 100 THEN t.task_id END) as overdue_tasks,
                       COALESCE(AVG(t.progress_percentage), 0) as avg_progress,
                       CASE 
                           WHEN (COUNT(DISTINCT CASE WHEN t.progress_percentage = 100 THEN t.task_id END) = COUNT(DISTINCT t.task_id) AND COUNT(DISTINCT t.task_id) > 0) THEN 3
                           WHEN (COUNT(DISTINCT CASE WHEN t.end_date < CURDATE() AND t.progress_percentage < 100 THEN t.task_id END) > 0) OR (p.end_date < CURDATE() AND COUNT(DISTINCT CASE WHEN t.progress_percentage = 100 THEN t.task_id END) < COUNT(DISTINCT t.task_id)) THEN 1
                           ELSE 2
                       END as sort_priority,
                       CASE 
                           WHEN (COUNT(DISTINCT CASE WHEN t.progress_percentage = 100 THEN t.task_id END) = COUNT(DISTINCT t.task_id) AND COUNT(DISTINCT t.task_id) > 0) THEN 
                               COALESCE(-TO_DAYS(p.end_date), 0)
                           ELSE 
                               COALESCE(TO_DAYS(p.end_date), 0)
                       END as sort_date
                FROM projects p
                LEFT JOIN tasks t ON p.project_id = t.project_id
                GROUP BY p.project_id
                ORDER BY sort_priority ASC, sort_date ASC, p.created_at DESC
            ");

            if ($result->num_rows === 0) {
                echo "<div class='col-12'><div class='text-center text-muted p-4'>No projects found. Click 'Create Project' to get started.</div></div>";
            }

            while ($row = $result->fetch_assoc()) {
                // Calculate progress percentage as average of all task progress
                $overall_progress = round($row['avg_progress'] ?? 0);
                $progress_class = $overall_progress === 100 ? 'bg-primary' : ($overall_progress > 0 ? 'bg-dark' : 'bg-secondary');
                
                $start_date_timestamp = $row['start_date'] ? strtotime($row['start_date']) : 0;
                $end_date_timestamp = $row['end_date'] ? strtotime($row['end_date']) : 0;
                $budget_value = $row['budget_allocated'] ?? 0;
                
                echo "<div class='col-12 col-md-6 col-lg-4 project-card' 
                          data-project-id='" . $row['project_id'] . "'
                          data-name='" . htmlspecialchars(strtolower($row['project_name']), ENT_QUOTES) . "'
                          data-start-date='" . $start_date_timestamp . "'
                          data-end-date='" . $end_date_timestamp . "'
                          data-budget='" . $budget_value . "'
                          data-progress='" . $overall_progress . "'
                          data-priority='" . $row['sort_priority'] . "'>";
                echo "    <div class='card h-100 bg-white shadow-sm' style='border-radius: 12px; border: 1px solid #e5e7eb; transition: all 0.3s ease;'>";
                echo "        <div class='card-body d-flex flex-column' style='padding: 1.5rem;'>";
                echo "            <h5 class='card-title mb-3 card-title-truncate'>";
                echo "                <a class='text-decoration-none text-primary' 
                       href='javascript:void(0)' onclick='showProjectDetailsModal(" . $row['project_id'] . ")' 
                       title='" . htmlspecialchars($row['project_name'], ENT_QUOTES) . "'>";
                echo "                   " . htmlspecialchars($row['project_name']) . "";
                echo "                </a>";
                echo "            </h5>";
                
                echo "            <p class='card-text text-muted small flex-grow-1 mb-3 card-text-truncate' 
                       title='" . htmlspecialchars($row['project_description'] ?? 'No description', ENT_QUOTES) . "'>";
                echo "                " . htmlspecialchars($row['project_description'] ?? 'No description') . "";
                echo "            </p>";
                
                echo "            <div class='mb-3'>";
                echo "                <div class='d-flex justify-content-between align-items-center mb-2'>";
                echo "                    <small class='text-muted'><i class='fas fa-calendar-alt me-1'></i>Start:</small>";
                echo "                    <small class='fw-semibold'>" . ($row['start_date'] ? date('M d, Y', strtotime($row['start_date'])) : '-') . "</small>";
                echo "                </div>";
                echo "                <div class='d-flex justify-content-between align-items-center mb-2'>";
                echo "                    <small class='text-muted'><i class='fas fa-calendar-check me-1'></i>End:</small>";
                echo "                    <small class='fw-semibold'>" . ($row['end_date'] ? date('M d, Y', strtotime($row['end_date'])) : '-') . "</small>";
                echo "                </div>";
                echo "                <div class='d-flex justify-content-between align-items-center mb-2'>";
                echo "                    <small class='text-muted'><i class='fas fa-peso-sign me-1'></i>Budget:</small>";
                echo "                    <small class='fw-semibold'>₱" . number_format($row['budget_allocated'] ?? 0, 2) . "</small>";
                echo "                </div>";
                echo "            </div>";
                
                echo "            <div class='mb-3'>";
                echo "                <div class='d-flex justify-content-between align-items-center mb-1'>";
                echo "                    <small class='text-muted'>Progress</small>";
                echo "                    <small class='fw-semibold'>" . $overall_progress . "%</small>";
                echo "                </div>";
                echo "                <div class='progress' style='height: 25px;'>";
                echo "                    <div class='progress-bar " . $progress_class . " d-flex align-items-center justify-content-center' 
                             style='width: " . $overall_progress . "%' role='progressbar'>";
                echo "                        " . $overall_progress . "%";
                echo "                    </div>";
                echo "                </div>";
                echo "            </div>";
                
                echo "            <div class='d-flex gap-2 mt-auto justify-content-end'>";
                echo "                <button onclick='showProjectDetailsModal(" . $row['project_id'] . ")' 
                       class='btn btn-primary btn-sm'>";
                echo "                    <i class='fas fa-eye me-1'></i>View";
                echo "                </button>";
                echo "                <button onclick='openFullDetails(" . $row['project_id'] . ")' 
                       class='btn btn-outline-primary btn-sm'>";
                echo "                    <i class='fas fa-external-link-alt me-1'></i>";
                echo "                </button>";
                echo "                <button onclick='showEditProjectModal(" . $row['project_id'] . ")' 
                        class='btn btn-outline-primary btn-sm'>";
                echo "                    <i class='fas fa-edit'></i>";
                echo "                </button>";
                echo "                <button onclick='showDeleteProjectModal(" . $row['project_id'] . ", \"" . htmlspecialchars($row['project_name'], ENT_QUOTES) . "\")' 
                        class='btn btn-danger btn-sm'>";
                echo "                    <i class='fas fa-trash'></i>";
                echo "                </button>";
                echo "            </div>";
                
                echo "        </div>";
                echo "    </div>";
                echo "</div>";
            }
            ?>
        </div>
    </div>

    <!-- ANALYTICS VIEW (HIDDEN INITIALLY) -->
    <div id="projects-analytics" class="card secondary-card p-4 d-none">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-project-diagram me-2"></i>Project Management - iServe</h1>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-dark" onclick="refreshProjectAnalytics()" id="refreshAnalyticsBtn">
                    <span id="refreshAnalyticsText"><i class="fas fa-sync-alt me-1"></i> Refresh</span>
                    <span id="refreshAnalyticsSpinner" class="spinner-border spinner-border-sm d-none ms-2" role="status"></span>
                </button>
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-outline-dark" id="projectListViewBtn2" onclick="setProjectView('list')">
                        <i class="fas fa-list me-1"></i> Project List
                    </button>
                    <button type="button" class="btn btn-primary" id="projectAnalyticsViewBtn2" onclick="setProjectView('analytics')">
                        <i class="fas fa-chart-bar me-1"></i> Analytics
                    </button>
                </div>
            </div>
        </div>

        <div id="analytics-error" class="alert alert-danger d-none" role="alert"></div>

        <div class="row mb-4">
            <div class="col-md-3 mb-3 position-relative">
                <div class="card p-3 text-center analytics-card-clickable" style="background-color: #88d0e6; color: #ffffff; border: 1px solid #0d4a5f; cursor: pointer;" onclick="toggleProjectList('total')">
                    <h3 id="total-projects" style="font-weight: normal; margin-bottom: 0.5rem;">0</h3>
                    <p style="margin-bottom: 0; font-weight: normal;">Total Projects</p>
                </div>
                <div id="total-projects-list" class="analytics-dropdown d-none">
                    <div class="card shadow-lg">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <strong>Total Projects</strong>
                            <button class="btn btn-sm btn-outline-dark" onclick="toggleProjectList('total', event)">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="card-body" id="total-projects-list-content" style="max-height: 300px; overflow-y: auto;">
                            <p class="text-muted mb-0">Loading...</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3 mb-3 position-relative">
                <div class="card p-3 text-center analytics-card-clickable" style="background-color: #88d0e6; color: #ffffff; border: 1px solid #0d4a5f; cursor: pointer;" onclick="toggleProjectList('active')">
                    <h3 id="projects-active" style="font-weight: normal; margin-bottom: 0.5rem;">0</h3>
                    <p style="margin-bottom: 0; font-weight: normal;">Active Projects</p>
                </div>
                <div id="active-projects-list" class="analytics-dropdown d-none">
                    <div class="card shadow-lg">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <strong>Active Projects</strong>
                            <button class="btn btn-sm btn-outline-dark" onclick="toggleProjectList('active', event)">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="card-body" id="active-projects-list-content" style="max-height: 300px; overflow-y: auto;">
                            <p class="text-muted mb-0">Loading...</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3 mb-3 position-relative">
                <div class="card p-3 text-center analytics-card-clickable" style="background-color: #88d0e6; color: #ffffff; border: 1px solid #0d4a5f; cursor: pointer;" onclick="toggleProjectList('upcoming')">
                    <h3 id="projects-upcoming" style="font-weight: normal; margin-bottom: 0.5rem;">0</h3>
                    <p style="margin-bottom: 0; font-weight: normal;">Upcoming Projects</p>
                </div>
                <div id="upcoming-projects-list" class="analytics-dropdown d-none">
                    <div class="card shadow-lg">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <strong>Upcoming Projects</strong>
                            <button class="btn btn-sm btn-outline-dark" onclick="toggleProjectList('upcoming', event)">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="card-body" id="upcoming-projects-list-content" style="max-height: 300px; overflow-y: auto;">
                            <p class="text-muted mb-0">Loading...</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3 mb-3 position-relative">
                <div class="card p-3 text-center analytics-card-clickable" style="background-color: #88d0e6; color: #ffffff; border: 1px solid #0d4a5f; cursor: pointer;" onclick="toggleProjectList('overdue')">
                    <h3 id="projects-overdue" style="font-weight: normal; margin-bottom: 0.5rem;">0</h3>
                    <p style="margin-bottom: 0; font-weight: normal;">Overdue Projects</p>
                </div>
                <div id="overdue-projects-list" class="analytics-dropdown d-none">
                    <div class="card shadow-lg">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <strong>Overdue Projects</strong>
                            <button class="btn btn-sm btn-outline-dark" onclick="toggleProjectList('overdue', event)">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="card-body" id="overdue-projects-list-content" style="max-height: 300px; overflow-y: auto;">
                            <p class="text-muted mb-0">Loading...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- CHART AREA -->
        <div class="card secondary-card p-3 mb-4">
            <h5 class="mb-3" style="color: #000000; font-weight: normal;">Project Timeline Overview</h5>
            <div style="position: relative; height: 400px;">
                <canvas id="projectsTimelineChart"></canvas>
            </div>
        </div>

        <!-- Budget Overview -->
        <div class="card secondary-card p-3">
            <h5 class="mb-3" style="color: #000000; font-weight: normal;">Budget Overview</h5>
            <div style="position: relative; height: 300px;">
                <canvas id="projectsBudgetChart"></canvas>
            </div>
        </div>
    </div>

</div>

<!-- Add Project Modal -->
<div class="modal fade" id="addProjectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Create New Project</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addProjectForm">
                    <div class="mb-3">
                        <label class="form-label">Project Name *</label>
                        <input type="text" class="form-control" id="project-name" name="project_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" id="project-description" name="project_description" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="project-start-date" name="start_date">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" id="project-end-date" name="end_date">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Budget</label>
                        <input type="number" class="form-control" id="project-budget" name="budget_allocated" step="0.01" min="0">
                    </div>
                    
                    <!-- Initial Tasks Section -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label mb-0">Initial Tasks (Optional)</label>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addInitialTask()">
                                <i class="fas fa-plus me-1"></i> Add Task
                            </button>
                        </div>
                        <div id="initial-tasks-container">
                            <!-- Tasks will be added here dynamically -->
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Cancel
                </button>
                <button type="button" class="btn btn-primary" onclick="saveProject()">
                    <i class="fas fa-check me-1"></i> Create Project
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Project Modal -->
<div class="modal fade" id="editProjectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Project</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editProjectForm">
                    <input type="hidden" id="edit-project-id" name="project_id">
                    <div class="mb-3">
                        <label class="form-label">Project Name *</label>
                        <input type="text" class="form-control" id="edit-project-name" name="project_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" id="edit-project-description" name="project_description" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="edit-project-start-date" name="start_date">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" id="edit-project-end-date" name="end_date">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Budget</label>
                        <input type="number" class="form-control" id="edit-project-budget" name="budget_allocated" step="0.01" min="0">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Cancel
                </button>
                <button type="button" class="btn btn-primary" onclick="updateProject()">
                    <i class="fas fa-save me-1"></i> Update Project
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <div class="w-100 text-center">
                    <div class="mb-3">
                        <i class="fas fa-exclamation-triangle" style="font-size: 4rem; color: #1a6985;"></i>
                    </div>
                    <h5 class="modal-title" id="deleteConfirmTitle">Confirm Deletion</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <p class="mb-0" id="deleteConfirmMessage">Are you sure you want to delete this item? This action cannot be undone.</p>
            </div>
            <div class="modal-footer border-0 justify-content-center gap-3">
                <button type="button" class="btn btn-outline-dark" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Cancel
                </button>
                <button type="button" class="btn" id="deleteConfirmBtn" style="background-color: #1a6985; color: #ffffff;" onclick="confirmDelete()">
                    <i class="fas fa-trash me-1"></i> Delete
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Project Details Modal -->
<div class="modal fade" id="projectDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-info-circle me-2"></i>Project Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="project-details-content">
                    <div class="text-center py-5">
                        <i class="fas fa-spinner fa-spin fa-2x mb-3"></i>
                        <p>Loading project details...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Close
                </button>
                <button type="button" id="view-full-details-btn" class="btn btn-primary" onclick="openFullDetailsFromModal()">
                    <i class="fas fa-external-link-alt me-1"></i> View Full Details
                </button>
            </div>
        </div>
    </div>
</div>
</div>

<?php include 'includes/footer.php'; ?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/main.js"></script>

<script>
// Set BASE_URL for API calls (set early so it's available for all functions)
window.BASE_URL = '<?php echo $BASE_URL ?? ""; ?>';

// View switch
function setProjectView(mode) {
    // Toggle views
    document.getElementById("projects-listing").classList.toggle("d-none", mode !== 'list');
    document.getElementById("projects-analytics").classList.toggle("d-none", mode !== 'analytics');
    
    // Update button states - sync both sets of buttons
    if (mode === 'list') {
        // Update buttons in list view header
        document.getElementById('projectListViewBtn').classList.remove('btn-outline-dark');
        document.getElementById('projectListViewBtn').classList.add('btn-primary');
        document.getElementById('projectAnalyticsViewBtn').classList.remove('btn-primary');
        document.getElementById('projectAnalyticsViewBtn').classList.add('btn-outline-dark');
        
        // Update buttons in analytics view header
        document.getElementById('projectListViewBtn2').classList.remove('btn-outline-dark');
        document.getElementById('projectListViewBtn2').classList.add('btn-primary');
        document.getElementById('projectAnalyticsViewBtn2').classList.remove('btn-primary');
        document.getElementById('projectAnalyticsViewBtn2').classList.add('btn-outline-dark');
    } else if (mode === 'analytics') {
        // Update buttons in list view header
        document.getElementById('projectListViewBtn').classList.remove('btn-primary');
        document.getElementById('projectListViewBtn').classList.add('btn-outline-dark');
        document.getElementById('projectAnalyticsViewBtn').classList.remove('btn-outline-dark');
        document.getElementById('projectAnalyticsViewBtn').classList.add('btn-primary');
        
        // Update buttons in analytics view header
        document.getElementById('projectListViewBtn2').classList.remove('btn-primary');
        document.getElementById('projectListViewBtn2').classList.add('btn-outline-dark');
        document.getElementById('projectAnalyticsViewBtn2').classList.remove('btn-outline-dark');
        document.getElementById('projectAnalyticsViewBtn2').classList.add('btn-primary');
        
        refreshProjectAnalytics();
    }
}

// Show add project modal
function showAddProjectModal() {
    document.getElementById('project-name').value = '';
    document.getElementById('project-description').value = '';
    document.getElementById('project-start-date').value = '';
    document.getElementById('project-end-date').value = '';
    document.getElementById('project-budget').value = '';
    document.getElementById('initial-tasks-container').innerHTML = '';
    taskCounter = 0;
    
    const modal = new bootstrap.Modal(document.getElementById('addProjectModal'));
    modal.show();
}

// Add initial task row
let taskCounter = 0;
function addInitialTask() {
    taskCounter++;
    const container = document.getElementById('initial-tasks-container');
    const taskDiv = document.createElement('div');
    taskDiv.className = 'card mb-2 p-3 initial-task-item';
    taskDiv.id = `task-item-${taskCounter}`;
    taskDiv.innerHTML = `
        <div class="d-flex justify-content-between align-items-start mb-2">
            <strong>Task ${taskCounter}</strong>
            <button type="button" class="btn btn-sm btn-outline-dark" onclick="removeInitialTask(${taskCounter})">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="row">
            <div class="col-md-12 mb-2">
                <input type="text" class="form-control form-control-sm" placeholder="Task Name *" 
                       id="task-name-${taskCounter}" required oninput="updateTaskDependencies()">
            </div>
            <div class="col-md-12 mb-2">
                <textarea class="form-control form-control-sm" rows="2" placeholder="Description" 
                          id="task-desc-${taskCounter}"></textarea>
            </div>
            <div class="col-md-6 mb-2">
                <input type="date" class="form-control form-control-sm" placeholder="Start Date" 
                       id="task-start-${taskCounter}">
            </div>
            <div class="col-md-6 mb-2">
                <input type="date" class="form-control form-control-sm" placeholder="End Date" 
                       id="task-end-${taskCounter}">
            </div>
            <div class="col-md-12 mb-2">
                <select class="form-select form-select-sm" id="task-parent-${taskCounter}" onchange="updateTaskDependencies()">
                    <option value="">No dependency (Main Task)</option>
                </select>
            </div>
        </div>
    `;
    container.appendChild(taskDiv);
    
    // Initialize date pickers for initial task dates
    const today = new Date().toISOString().split('T')[0];
    const projectStartDate = document.getElementById('project-start-date').value || today;
    const projectEndDate = document.getElementById('project-end-date').value;
    
    // Task start date - min is today or project start date (whichever is later)
    const taskStartMin = projectStartDate > today ? projectStartDate : today;
    const taskStartPicker = flatpickr(`#task-start-${taskCounter}`, {
        minDate: taskStartMin,
        maxDate: projectEndDate || null,
        dateFormat: 'Y-m-d',
        onChange: function(selectedDates, dateStr, instance) {
            // Update end date min when start date changes
            if (dateStr) {
                const taskEndInput = document.getElementById(`task-end-${taskCounter}`);
                if (taskEndInput && taskEndInput._flatpickr) {
                    taskEndInput._flatpickr.set('minDate', dateStr);
                    // Also ensure end date doesn't exceed project end date
                    if (projectEndDate) {
                        taskEndInput._flatpickr.set('maxDate', projectEndDate);
                    }
                }
            }
        }
    });
    
    // Task end date - min is today or task start date, max is project end date
    const taskEndPicker = flatpickr(`#task-end-${taskCounter}`, {
        minDate: taskStartMin,
        maxDate: projectEndDate || null,
        maxDate: projectEndDate || null,
        dateFormat: 'Y-m-d'
    });
    
    updateTaskDependencies();
}

// Remove initial task
function removeInitialTask(taskId) {
    const taskItem = document.getElementById(`task-item-${taskId}`);
    if (taskItem) {
        taskItem.remove();
        updateTaskDependencies();
    }
}

// Update task dependency dropdowns
function updateTaskDependencies() {
    const taskItems = document.querySelectorAll('.initial-task-item');
    taskItems.forEach((item, index) => {
        const taskId = item.id.replace('task-item-', '');
        const select = document.getElementById(`task-parent-${taskId}`);
        if (select) {
            const currentValue = select.value;
            select.innerHTML = '<option value="">No dependency (Main Task)</option>';
            taskItems.forEach((otherItem, otherIndex) => {
                if (otherIndex < index) {
                    const otherTaskId = otherItem.id.replace('task-item-', '');
                    const otherTaskName = document.getElementById(`task-name-${otherTaskId}`).value || `Task ${otherTaskId}`;
                    select.innerHTML += `<option value="${otherTaskId}">${otherTaskName}</option>`;
                }
            });
            select.value = currentValue;
        }
    });
}

// Save new project
async function saveProject() {
    const form = document.getElementById('addProjectForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    // Collect initial tasks
    const tasks = [];
    const taskItems = document.querySelectorAll('.initial-task-item');
    taskItems.forEach((item) => {
        const taskId = item.id.replace('task-item-', '');
        const name = document.getElementById(`task-name-${taskId}`).value.trim();
        if (name) {
            const parentTaskId = document.getElementById(`task-parent-${taskId}`).value;
            tasks.push({
                task_name: name,
                task_description: document.getElementById(`task-desc-${taskId}`).value.trim() || null,
                start_date: document.getElementById(`task-start-${taskId}`).value || null,
                end_date: document.getElementById(`task-end-${taskId}`).value || null,
                dependency_task_index: parentTaskId ? parseInt(parentTaskId) : null
            });
        }
    });
    
    const projectData = {
        name: document.getElementById('project-name').value,
        description: document.getElementById('project-description').value,
        start_date: document.getElementById('project-start-date').value || null,
        end_date: document.getElementById('project-end-date').value || null,
        budget: document.getElementById('project-budget').value ? 
            parseFloat(document.getElementById('project-budget').value) : null,
        tasks: tasks.length > 0 ? tasks.map(t => ({
            name: t.task_name || t.name,
            description: t.task_description || t.description || '',
            start_date: t.start_date || null,
            end_date: t.end_date || null,
            dependency_task_index: t.dependency_task_index || null
        })) : null
    }; 
    
    try {
        const response = await fetch(window.BASE_URL + '/api/project.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(projectData)
        });
        
        const data = await response.json();
        
        if (data.error) {
            throw new Error(data.error);
        }
        
        bootstrap.Modal.getInstance(document.getElementById('addProjectModal')).hide();
        showSuccess('Project created successfully');
        setTimeout(() => {
            location.reload(); // Reload to show new project
        }, 1000);
        
    } catch (error) {
        showError('Failed to create project: ' + error.message);
    }
}

// Show delete project confirmation modal
let pendingDeleteProjectId = null;
function showDeleteProjectModal(projectId, projectName) {
    pendingDeleteProjectId = projectId;
    document.getElementById('deleteConfirmTitle').textContent = 'Delete Project';
    document.getElementById('deleteConfirmMessage').innerHTML = `
        <p>Are you sure you want to delete <strong>"${projectName}"</strong>?</p>
        <p class="text-danger mb-0"><i class="fas fa-exclamation-triangle me-1"></i>This will delete the project and all associated tasks and subtasks. This action cannot be undone.</p>
    `;
    document.getElementById('deleteConfirmBtn').onclick = () => confirmDeleteProject(projectId);
    
    const modal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
    modal.show();
}

// Confirm delete project
async function confirmDeleteProject(projectId) {
    try {
        const response = await fetch(window.BASE_URL + '/api/project.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ project_id: parseInt(projectId) })
        });
        
        if (!response.ok) {
            const errorData = await response.json().catch(() => ({ error: 'Failed to delete project' }));
            throw new Error(errorData.error || 'Failed to delete project');
        }
        
        const data = await response.json();
        
        if (data.error) {
            throw new Error(data.error);
        }
        
        bootstrap.Modal.getInstance(document.getElementById('deleteConfirmModal')).hide();
        showSuccess(`Project and all associated tasks deleted successfully`);
        setTimeout(() => {
            location.reload();
        }, 1000);
        
    } catch (error) {
        console.error('Delete project error:', error);
        showError('Failed to delete project: ' + error.message);
    }
}

// Show edit project modal
async function showEditProjectModal(projectId) {
    try {
        const response = await fetch(`${window.BASE_URL}/api/project.php?project_id=${projectId}`);
        const data = await response.json();
        
        if (data.error) {
            throw new Error(data.error);
        }
        
        const project = data.project;
        document.getElementById('edit-project-id').value = project.project_id;
        document.getElementById('edit-project-name').value = project.name || project.project_name || '';
        document.getElementById('edit-project-description').value = project.description || project.project_description || '';
        document.getElementById('edit-project-start-date').value = project.start_date || '';
        document.getElementById('edit-project-end-date').value = project.end_date || '';
        document.getElementById('edit-project-budget').value = project.budget !== undefined ? project.budget : (project.budget_allocated || '');
        
        const modal = new bootstrap.Modal(document.getElementById('editProjectModal'));
        modal.show();
        
    } catch (error) {
        showError('Failed to load project: ' + error.message);
    }
}

// Update project
async function updateProject() {
    const projectData = {
        project_id: parseInt(document.getElementById('edit-project-id').value),
        name: document.getElementById('edit-project-name').value,
        description: document.getElementById('edit-project-description').value,
        start_date: document.getElementById('edit-project-start-date').value || null,
        end_date: document.getElementById('edit-project-end-date').value || null,
        budget: document.getElementById('edit-project-budget').value ? 
            parseFloat(document.getElementById('edit-project-budget').value) : null
    };
    
    try {
        const response = await fetch(window.BASE_URL + '/api/project.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(projectData)
        });
        
        const data = await response.json();
        
        if (data.error) {
            throw new Error(data.error);
        }
        
        const modalEl = document.getElementById('editProjectModal');
        if (document.activeElement && modalEl.contains(document.activeElement)) {
            document.activeElement.blur();
        }
        bootstrap.Modal.getInstance(modalEl).hide();
        showSuccess('Project updated successfully');
        setTimeout(() => {
            location.reload();
        }, 1000);
        
    } catch (error) {
        showError('Failed to update project: ' + error.message);
    }
}

// Filter projects
function filterProjects() {
    const input = document.getElementById('projectSearchInput');
    const filter = input.value.toLowerCase();
    const container = document.getElementById('projects-cards-container');
    const cards = container.getElementsByClassName('project-card');
    
    for (let i = 0; i < cards.length; i++) {
        const card = cards[i];
        const text = card.textContent || card.innerText;
        card.style.display = text.toLowerCase().indexOf(filter) > -1 ? '' : 'none';
    }
    
    // Re-apply sorting after filtering
    sortProjects();
}

// Sort projects based on selected criteria
function sortProjects() {
    const sortSelect = document.getElementById('projectSortSelect');
    const sortValue = sortSelect ? sortSelect.value : 'priority';
    const container = document.getElementById('projects-cards-container');
    
    if (!container) return;
    
    // Get all visible project cards
    const cards = Array.from(container.getElementsByClassName('project-card'));
    const visibleCards = cards.filter(card => card.style.display !== 'none');
    
    // Sort the cards
    visibleCards.sort((a, b) => {
        let comparison = 0;
        
        switch(sortValue) {
            case 'name-asc':
                comparison = (a.dataset.name || '').localeCompare(b.dataset.name || '');
                break;
                
            case 'name-desc':
                comparison = (b.dataset.name || '').localeCompare(a.dataset.name || '');
                break;
                
            case 'date-asc':
                comparison = (parseInt(a.dataset.startDate) || 0) - (parseInt(b.dataset.startDate) || 0);
                break;
                
            case 'date-desc':
                comparison = (parseInt(b.dataset.startDate) || 0) - (parseInt(a.dataset.startDate) || 0);
                break;
                
            case 'end-date-asc':
                comparison = (parseInt(a.dataset.endDate) || 0) - (parseInt(b.dataset.endDate) || 0);
                break;
                
            case 'end-date-desc':
                comparison = (parseInt(b.dataset.endDate) || 0) - (parseInt(a.dataset.endDate) || 0);
                break;
                
            case 'budget-asc':
                comparison = (parseFloat(a.dataset.budget) || 0) - (parseFloat(b.dataset.budget) || 0);
                break;
                
            case 'budget-desc':
                comparison = (parseFloat(b.dataset.budget) || 0) - (parseFloat(a.dataset.budget) || 0);
                break;
                
            case 'progress-asc':
                comparison = (parseInt(a.dataset.progress) || 0) - (parseInt(b.dataset.progress) || 0);
                break;
                
            case 'progress-desc':
                comparison = (parseInt(b.dataset.progress) || 0) - (parseInt(a.dataset.progress) || 0);
                break;
                
            case 'priority':
            default:
                // Sort by priority first, then by end date
                const priorityA = parseInt(a.dataset.priority) || 2;
                const priorityB = parseInt(b.dataset.priority) || 2;
                if (priorityA !== priorityB) {
                    comparison = priorityA - priorityB;
                } else {
                    // If same priority, sort by end date
                    comparison = (parseInt(a.dataset.endDate) || 0) - (parseInt(b.dataset.endDate) || 0);
                }
                break;
        }
        
        return comparison;
    });
    
    // Re-append sorted cards to container
    visibleCards.forEach(card => {
        container.appendChild(card);
    });
    
    // Also re-append hidden cards at the end
    const hiddenCards = cards.filter(card => card.style.display === 'none');
    hiddenCards.forEach(card => {
        container.appendChild(card);
    });
}

// Show project details modal
async function showProjectDetailsModal(projectId) {
    const modal = new bootstrap.Modal(document.getElementById('projectDetailsModal'));
    const contentDiv = document.getElementById('project-details-content');
    
    // Show loading state
    contentDiv.innerHTML = `
        <div class="text-center py-5">
            <i class="fas fa-spinner fa-spin fa-2x mb-3"></i>
            <p>Loading project details...</p>
        </div>
    `;
    
    // Store project ID for the full details button
    document.getElementById('view-full-details-btn').setAttribute('data-project-id', projectId);
    
    modal.show();
    
    try {
        const response = await fetch(`${window.BASE_URL}/api/project.php?project_id=${projectId}`);
        const data = await response.json();
        
        if (data.error) {
            throw new Error(data.error);
        }
        
        const project = data.project;
        const tasks = data.tasks || [];
        
        // Format dates
        const formatDate = (dateStr) => {
            if (!dateStr) return 'Not set';
            const date = new Date(dateStr);
            return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        };
        
        // Build tasks HTML
        let tasksHtml = '';
        if (tasks.length === 0) {
            tasksHtml = '<p class="text-muted text-center py-3">No tasks yet.</p>';
        } else {
            tasksHtml = '<div class="table-responsive"><table class="table table-sm">';
            tasksHtml += '<thead><tr><th>Task</th><th>Progress</th><th>Dates</th></tr></thead><tbody>';
            
            tasks.forEach(task => {
                // Use progress_percent (from API) or progress_percentage (fallback)
                const progress = task.progress_percent !== undefined ? task.progress_percent : (task.progress_percentage || 0);
                const progressClass = progress === 100 ? 'bg-primary' : 
                                     progress > 0 ? 'bg-dark' : 'bg-secondary';
                tasksHtml += `<tr>`;
                tasksHtml += `<td><strong>${escapeHtml(task.name || task.task_name)}</strong><br><small class="text-muted">${escapeHtml(task.description || task.task_description || 'No description')}</small></td>`;
                tasksHtml += `<td>
                    <div class="progress" style="height: 20px; width: 100px;">
                        <div class="progress-bar ${progressClass}" style="width: ${progress}%">
                            ${progress}%
                        </div>
                    </div>
                </td>`;
                tasksHtml += `<td><small><i class="fas fa-calendar-alt me-1"></i>${formatDate(task.start_date)} - ${formatDate(task.end_date)}</small></td>`;
                tasksHtml += `</tr>`;
            });
            
            tasksHtml += '</tbody></table></div>';
        }
        
        // Build the complete HTML
        const progressClass = project.overall_progress === 100 ? 'bg-primary' : 
                             project.overall_progress > 0 ? 'bg-dark' : 'bg-secondary';
        
        // Use normalized field names from API
        const projectName = project.name || project.project_name || '';
        const projectDescription = project.description || project.project_description || 'No description';
        const projectBudget = project.budget !== undefined ? project.budget : (project.budget_allocated || 0);
        
        contentDiv.innerHTML = `
            <div class="mb-4">
                <h3><i class="fas fa-project-diagram me-2"></i>${escapeHtml(projectName)}</h3>
                <p class="text-muted">${escapeHtml(projectDescription)}</p>
            </div>
            
            <div class="row mb-4">
                <div class="col-md-6 mb-3">
                    <div class="card bg-light p-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <small class="text-muted"><i class="fas fa-calendar me-1"></i>Start Date:</small>
                            <strong>${formatDate(project.start_date)}</strong>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <small class="text-muted"><i class="fas fa-calendar-check me-1"></i>End Date:</small>
                            <strong>${formatDate(project.end_date)}</strong>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted"><i class="fas fa-peso-sign me-1"></i>Budget:</small>
                            <strong>₱${parseFloat(projectBudget).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="card bg-light p-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <small class="text-muted"><i class="fas fa-tasks me-1"></i>Total Tasks:</small>
                            <strong>${project.total_tasks || 0}</strong>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <small class="text-muted"><i class="fas fa-check-circle me-1"></i>Completed:</small>
                            <strong>${project.completed_tasks || 0}</strong>
                        </div>
                        <div>
                            <small class="text-muted d-block mb-1"><i class="fas fa-chart-line me-1"></i>Overall Progress:</small>
                            <div class="progress" style="height: 25px;">
                                <div class="progress-bar ${progressClass} d-flex align-items-center justify-content-center" 
                                     style="width: ${project.overall_progress || 0}%" role="progressbar">
                                    ${project.overall_progress || 0}%
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <h5><i class="fas fa-tasks me-2"></i>Tasks</h5>
                ${tasksHtml}
            </div>
        `;
        
    } catch (error) {
        console.error('Error loading project:', error);
        contentDiv.innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Failed to load project details: ${error.message}
            </div>
        `;
    }
}

// Utility function to escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

let timelineChart = null;
let budgetChart = null;
let analyticsProjects = {
    total: [],
    active: [],
    upcoming: [],
    overdue: []
};

// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, Chart.js available:', typeof Chart !== 'undefined');
    
    // Notification system is initialized in main.js
    // Note: Notifications are created ONLY when visiting the notifications page on first load
    
    // Initialize date pickers for project dates
    initializeProjectDatePickers();
    
    // Initialize sorting (applies default priority sort)
    sortProjects();
    
    // Handle unhandled promise rejections (suppress browser extension errors)
    window.addEventListener('unhandledrejection', function(event) {
        // Check if it's a browser extension error
        const errorMessage = event.reason?.message || event.reason?.toString() || '';
        if (errorMessage.includes('message channel closed') || 
            errorMessage.includes('asynchronous response')) {
            // Suppress browser extension errors
            event.preventDefault();
            console.warn('Browser extension error suppressed:', errorMessage);
            return;
        }
        // Log other errors for debugging
        console.error('Unhandled promise rejection:', event.reason);
    });
});

// Open full details in a new tab
function openFullDetails(projectId) {
    // Open in a new tab
    const newWindow = window.open(`<?php echo $BASE_URL; ?>/project-details.php?project_id=${projectId}`, '_blank');
    // Store reference if needed
    if (newWindow) {
        sessionStorage.setItem('openedFromIndex', 'true');
    }
}

// Open full details from modal
function openFullDetailsFromModal() {
    const projectId = document.getElementById('view-full-details-btn').getAttribute('data-project-id');
    if (projectId) {
        // Close the modal first
        const modal = bootstrap.Modal.getInstance(document.getElementById('projectDetailsModal'));
        if (modal) {
            modal.hide();
        }
        // Open full details
        openFullDetails(projectId);
    }
}

// Initialize date pickers for project creation/editing
function initializeProjectDatePickers() {
    const today = new Date().toISOString().split('T')[0];
    
    // Function to show error for past dates
    function showPastDateError() {
        if (typeof window.showToast === 'function') {
            window.showToast('Cannot select past dates', 'error', null, 3000);
        } else {
            alert('❌ Cannot pick past dates');
        }
    }
    
    // Project start date - min is today
    const projectStartDate = flatpickr('#project-start-date', {
        minDate: today,
        dateFormat: 'Y-m-d',
        onDayCreate: function(dObj, dStr, fp, dayElem) {
            // Disable past dates visually
            if (dayElem.dateObj < new Date(today)) {
                dayElem.classList.add('flatpickr-disabled');
                dayElem.style.cursor = 'not-allowed';
                dayElem.style.opacity = '0.4';
            }
        },
        onChange: function(selectedDates, dateStr, instance) {
            // Update end date min when start date changes
            if (dateStr && projectEndDatePicker) {
                projectEndDatePicker.set('minDate', dateStr);
            }
            // Update all initial task date pickers
            updateInitialTaskDatePickers();
        },
        onOpen: function(selectedDates, dateStr, instance) {
            // Show message when calendar opens
            setTimeout(() => {
                const pastDays = instance.calendarContainer.querySelectorAll('.flatpickr-disabled');
                if (pastDays.length > 0) {
                    pastDays.forEach(day => {
                        day.addEventListener('click', (e) => {
                            e.preventDefault();
                            showPastDateError();
                        });
                    });
                }
            }, 100);
        }
    });
    
    // Project end date - min is today or start date
    const projectEndDatePicker = flatpickr('#project-end-date', {
        minDate: today,
        dateFormat: 'Y-m-d',
        onDayCreate: function(dObj, dStr, fp, dayElem) {
            if (dayElem.dateObj < new Date(today)) {
                dayElem.classList.add('flatpickr-disabled');
                dayElem.style.cursor = 'not-allowed';
                dayElem.style.opacity = '0.4';
            }
        },
        onChange: function(selectedDates, dateStr, instance) {
            // Update all initial task date pickers
            updateInitialTaskDatePickers();
        },
        onOpen: function(selectedDates, dateStr, instance) {
            setTimeout(() => {
                const pastDays = instance.calendarContainer.querySelectorAll('.flatpickr-disabled');
                if (pastDays.length > 0) {
                    pastDays.forEach(day => {
                        day.addEventListener('click', (e) => {
                            e.preventDefault();
                            showPastDateError();
                        });
                    });
                }
            }, 100);
        }
    });
    
    // Edit project start date
    const editProjectStartDate = flatpickr('#edit-project-start-date', {
        minDate: today,
        dateFormat: 'Y-m-d',
        onDayCreate: function(dObj, dStr, fp, dayElem) {
            if (dayElem.dateObj < new Date(today)) {
                dayElem.classList.add('flatpickr-disabled');
                dayElem.style.cursor = 'not-allowed';
                dayElem.style.opacity = '0.4';
            }
        },
        onChange: function(selectedDates, dateStr, instance) {
            if (dateStr && editProjectEndDatePicker) {
                editProjectEndDatePicker.set('minDate', dateStr);
            }
        },
        onOpen: function(selectedDates, dateStr, instance) {
            setTimeout(() => {
                const pastDays = instance.calendarContainer.querySelectorAll('.flatpickr-disabled');
                if (pastDays.length > 0) {
                    pastDays.forEach(day => {
                        day.addEventListener('click', (e) => {
                            e.preventDefault();
                            showPastDateError();
                        });
                    });
                }
            }, 100);
        }
    });
    
    // Edit project end date
    const editProjectEndDatePicker = flatpickr('#edit-project-end-date', {
        minDate: today,
        dateFormat: 'Y-m-d',
        onDayCreate: function(dObj, dStr, fp, dayElem) {
            if (dayElem.dateObj < new Date(today)) {
                dayElem.classList.add('flatpickr-disabled');
                dayElem.style.cursor = 'not-allowed';
                dayElem.style.opacity = '0.4';
            }
        },
        onOpen: function(selectedDates, dateStr, instance) {
            setTimeout(() => {
                const pastDays = instance.calendarContainer.querySelectorAll('.flatpickr-disabled');
                if (pastDays.length > 0) {
                    pastDays.forEach(day => {
                        day.addEventListener('click', (e) => {
                            e.preventDefault();
                            showPastDateError();
                        });
                    });
                }
            }, 100);
        }
    });
    
    // Store pickers globally for access
    window.projectStartDatePicker = projectStartDate;
    window.projectEndDatePicker = projectEndDatePicker;
    window.editProjectStartDatePicker = editProjectStartDate;
    window.editProjectEndDatePicker = editProjectEndDatePicker;
}

// Update initial task date pickers when project dates change
function updateInitialTaskDatePickers() {
    const today = new Date().toISOString().split('T')[0];
    const projectStartDate = document.getElementById('project-start-date').value || today;
    const projectEndDate = document.getElementById('project-end-date').value;
    
    const taskItems = document.querySelectorAll('.initial-task-item');
    taskItems.forEach((item) => {
        const taskId = item.id.replace('task-item-', '');
        const taskStartInput = document.getElementById(`task-start-${taskId}`);
        const taskEndInput = document.getElementById(`task-end-${taskId}`);
        
        if (taskStartInput && taskEndInput) {
            // Get flatpickr instances using the _flatpickr property (stored by flatpickr)
            const taskStartPicker = taskStartInput._flatpickr;
            const taskEndPicker = taskEndInput._flatpickr;
            
            const taskStartMin = projectStartDate > today ? projectStartDate : today;
            
            if (taskStartPicker) {
                taskStartPicker.set('minDate', taskStartMin);
                taskStartPicker.set('maxDate', projectEndDate || null);
                // If current date exceeds max, reset it
                if (projectEndDate && taskStartInput.value && taskStartInput.value > projectEndDate) {
                    taskStartPicker.setDate(projectEndDate);
                }
            }
            
            if (taskEndPicker) {
                // Ensure end date min is at least task start date if set
                const currentStartDate = taskStartInput.value;
                const endDateMin = currentStartDate && currentStartDate > taskStartMin ? currentStartDate : taskStartMin;
                taskEndPicker.set('minDate', endDateMin);
                taskEndPicker.set('maxDate', projectEndDate || null);
                // If current date exceeds max, reset it
                if (projectEndDate && taskEndInput.value && taskEndInput.value > projectEndDate) {
                    taskEndPicker.setDate(projectEndDate);
                }
            }
        }
    });
}

// Refresh project analytics
async function refreshProjectAnalytics() {
    console.log('Loading analytics...');
    
    // Show loading state
    const refreshBtn = document.getElementById('refreshAnalyticsBtn');
    const refreshText = document.getElementById('refreshAnalyticsText');
    const refreshSpinner = document.getElementById('refreshAnalyticsSpinner');
    const errorDiv = document.getElementById('analytics-error');
    const analyticsView = document.getElementById('projects-analytics');
    
    if (refreshBtn) {
        refreshBtn.disabled = true;
    }
    if (refreshText) {
        refreshText.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Loading...';
    }
    if (refreshSpinner) {
        refreshSpinner.classList.remove('d-none');
    }
    if (errorDiv) {
        errorDiv.classList.add('d-none');
        errorDiv.textContent = '';
    }
    
    try {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 seconds timeout
        
        const response = await fetch(window.BASE_URL + '/api/projects.php?analytics=true', {
            signal: controller.signal
        });
        
        clearTimeout(timeoutId);
        
        // Check if analytics view is still visible
        if (analyticsView.classList.contains('d-none')) {
            console.log('Analytics view closed, aborting processing');
            return;
        }
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        console.log('API Response:', data);
        
        // Check for API errors
        if (data.error) {
            throw new Error(data.error);
        }
        
        // Handle both response formats
        let projects = [];
        if (Array.isArray(data)) {
            projects = data;
        } else if (data.projects && Array.isArray(data.projects)) {
            projects = data.projects;
        } else {
            console.error('Unexpected data format:', data);
            throw new Error('Invalid data format received from API');
        }
        
        console.log('Projects loaded:', projects.length);
        
        if (projects.length === 0) {
            console.warn('No projects found');
            // Still update the UI to show 0
            document.getElementById('total-projects').textContent = '0';
            document.getElementById('projects-active').textContent = '0';
            document.getElementById('projects-upcoming').textContent = '0';
            document.getElementById('projects-overdue').textContent = '0';
            return;
        }
        
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        // Reset categorized projects
        analyticsProjects.total = projects;
        analyticsProjects.active = [];
        analyticsProjects.upcoming = [];
        analyticsProjects.overdue = [];
        
        let total = projects.length;
        let active = 0;
        let upcoming = 0;
        let overdue = 0;
        
        projects.forEach(project => {
            if (!project.start_date || !project.end_date) {
                return; // Skip projects without dates
            }
            
            const startDate = new Date(project.start_date);
            const endDate = new Date(project.end_date);
            
            if (isNaN(startDate.getTime()) || isNaN(endDate.getTime())) {
                console.warn('Invalid date for project:', project.project_name);
                return;
            }
            
            startDate.setHours(0, 0, 0, 0);
            endDate.setHours(0, 0, 0, 0);
            
            if (startDate <= today && endDate >= today) {
                active++;
                analyticsProjects.active.push(project);
            } else if (startDate > today) {
                upcoming++;
                analyticsProjects.upcoming.push(project);
            } else if (endDate < today) {
                // Check if project is completed (progress = 100) or has incomplete tasks
                const progress = project.overall_progress || 0;
                if (progress < 100) {
                    overdue++;
                    analyticsProjects.overdue.push(project);
                }
            }
        });
        
        console.log('Analytics calculated:', { total, active, upcoming, overdue });
        
        // Update summary cards
        const totalEl = document.getElementById('total-projects');
        const activeEl = document.getElementById('projects-active');
        const upcomingEl = document.getElementById('projects-upcoming');
        const overdueEl = document.getElementById('projects-overdue');
        
        if (totalEl) totalEl.textContent = total;
        if (activeEl) activeEl.textContent = active;
        if (upcomingEl) upcomingEl.textContent = upcoming;
        if (overdueEl) overdueEl.textContent = overdue;
        
        // Create/update charts
        createTimelineChart(projects);
        createBudgetChart(projects);
        
        // Update project lists in dropdowns
        updateProjectLists();
        
        console.log('Analytics loaded successfully');
        
    } catch (error) {
        console.error('Error loading analytics:', error);
        
        // Show error message
        if (errorDiv) {
            errorDiv.textContent = 'Failed to load analytics: ' + error.message;
            errorDiv.classList.remove('d-none');
        } else {
            showError('Failed to load analytics: ' + error.message);
        }
    } finally {
        // Reset loading state
        if (refreshBtn) {
            refreshBtn.disabled = false;
        }
        if (refreshText) {
            refreshText.innerHTML = '<i class="fas fa-sync-alt me-1"></i> Refresh';
        }
        if (refreshSpinner) {
            refreshSpinner.classList.add('d-none');
        }
    }
}

// Create timeline chart
function createTimelineChart(projects) {
    const canvas = document.getElementById('projectsTimelineChart');
    if (!canvas) {
        console.error('Timeline chart canvas not found');
        return;
    }
    
    // Check if Chart.js is available
    if (typeof Chart === 'undefined') {
        console.error('Chart.js is not loaded');
        return;
    }
    
    // Destroy existing chart if it exists
    if (timelineChart) {
        timelineChart.destroy();
        timelineChart = null;
    }
    
    // Filter projects with valid dates
    const validProjects = projects.filter(p => p.start_date && p.end_date);
    
    if (validProjects.length === 0) {
        console.warn('No projects with valid dates for timeline chart');
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        return;
    }
    
    // Prepare data for timeline
    const labels = validProjects.map(p => p.name || p.project_name || 'Unnamed Project');
    
    // Calculate durations in days
    const durations = validProjects.map(p => {
        const startDate = new Date(p.start_date);
        const endDate = new Date(p.end_date);
        if (isNaN(startDate.getTime()) || isNaN(endDate.getTime())) {
            return 0;
        }
        const diffTime = Math.abs(endDate - startDate);
        return Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    });
    
    console.log('Creating timeline chart with', validProjects.length, 'projects');
    
    timelineChart = new Chart(canvas, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Project Duration (days)',
                data: durations,
                backgroundColor: 'rgba(54, 162, 235, 0.6)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: {
                        color: '#000000',
                        font: {
                            size: 12,
                            weight: '500'
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: '#000000',
                        font: {
                            size: 11
                        }
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.1)'
                    }
                },
                x: {
                    ticks: {
                        color: '#000000',
                        maxRotation: 45,
                        minRotation: 45,
                        font: {
                            size: 11
                        }
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.1)'
                    }
                }
            }
        }
    });
}

// Create budget chart
function createBudgetChart(projects) {
    const canvas = document.getElementById('projectsBudgetChart');
    if (!canvas) {
        console.error('Budget chart canvas not found');
        return;
    }
    
    // Check if Chart.js is available
    if (typeof Chart === 'undefined') {
        console.error('Chart.js is not loaded');
        return;
    }
    
    // Destroy existing chart if it exists
    if (budgetChart) {
        budgetChart.destroy();
        budgetChart = null;
    }
    
    // Filter projects with valid budgets
    const validProjects = projects.filter(p => {
        const budget = p.budget !== undefined ? p.budget : p.budget_allocated;
        return budget && parseFloat(budget) > 0;
    });
    
    if (validProjects.length === 0) {
        console.warn('No projects with valid budgets for budget chart');
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        return;
    }
    
    const labels = validProjects.map(p => p.name || p.project_name || 'Unnamed Project');
    const budgets = validProjects.map(p => {
        const budget = p.budget !== undefined ? p.budget : p.budget_allocated;
        return parseFloat(budget) || 0;
    });
    
    console.log('Creating budget chart with', validProjects.length, 'projects');
    
    budgetChart = new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                label: 'Budget (₱)',
                data: budgets,
                backgroundColor: [
                    'rgba(54, 162, 235, 0.6)',
                    'rgba(75, 192, 192, 0.6)',
                    'rgba(255, 206, 86, 0.6)',
                    'rgba(255, 99, 132, 0.6)',
                    'rgba(153, 102, 255, 0.6)',
                    'rgba(255, 159, 64, 0.6)'
                ],
                borderColor: [
                    'rgba(54, 162, 235, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(255, 206, 86, 1)',
                    'rgba(255, 99, 132, 1)',
                    'rgba(153, 102, 255, 1)',
                    'rgba(255, 159, 64, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        color: '#000000',
                        padding: 15,
                        font: {
                            size: 12,
                            weight: '500'
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.label + ': ₱' + context.parsed.toLocaleString('en-US', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            });
                        }
                    }
                }
            }
        }
    });
}

// Toggle project list dropdown
function toggleProjectList(category, event) {
    if (event) {
        event.stopPropagation(); // Prevent card click when clicking X button
    }
    
    const dropdownId = `${category}-projects-list`;
    const dropdown = document.getElementById(dropdownId);
    
    if (!dropdown) return;
    
    // Toggle visibility
    if (dropdown.classList.contains('d-none')) {
        // Show dropdown
        dropdown.classList.remove('d-none');
        // Update content if needed
        updateProjectListContent(category);
    } else {
        // Hide dropdown
        dropdown.classList.add('d-none');
    }
}

// Update project list content
function updateProjectListContent(category) {
    const contentId = `${category}-projects-list-content`;
    const contentDiv = document.getElementById(contentId);
    
    if (!contentDiv) return;
    
    const projects = analyticsProjects[category] || [];
    
    if (projects.length === 0) {
        contentDiv.innerHTML = '<p class="text-muted mb-0 text-center">No projects in this category.</p>';
        return;
    }
    
    let html = '<ul class="list-group list-group-flush">';
    projects.forEach(project => {
        const progress = project.overall_progress || 0;
        const progressClass = progress === 100 ? 'bg-primary' : (progress > 0 ? 'bg-dark' : 'bg-secondary');
        
        // Use normalized field names from API
        const projectName = project.name || project.project_name || 'Unnamed Project';
        const projectDescription = project.description || project.project_description || 'No description';
        
        html += `
            <li class="list-group-item">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <h6 class="mb-1">
                            <a href="javascript:void(0)" onclick="showProjectDetailsModal(${project.project_id})" class="text-decoration-none">
                                ${escapeHtml(projectName)}
                            </a>
                        </h6>
                        <p class="mb-1 small text-muted">${escapeHtml(projectDescription)}</p>
                        <div class="d-flex gap-2 small">
                            <span><i class="fas fa-calendar-alt me-1"></i>${project.start_date || 'Not set'}</span>
                            <span><i class="fas fa-calendar-check me-1"></i>${project.end_date || 'Not set'}</span>
                        </div>
                        <div class="progress mt-2" style="height: 20px;">
                            <div class="progress-bar ${progressClass}" style="width: ${progress}%">${progress}%</div>
                        </div>
                    </div>
                    <div class="ms-2">
                        <a href="<?php echo $BASE_URL; ?>/project-details.php?project_id=${project.project_id}" 
                           class="btn btn-sm btn-outline-primary" 
                           onclick="openFullDetails(${project.project_id}); return false;">
                            <i class="fas fa-external-link-alt"></i>
                        </a>
                    </div>
                </div>
            </li>
        `;
    });
    html += '</ul>';
    
    contentDiv.innerHTML = html;
}

// Update all project lists
function updateProjectLists() {
    ['total', 'active', 'upcoming', 'overdue'].forEach(category => {
        const dropdown = document.getElementById(`${category}-projects-list`);
        if (dropdown && !dropdown.classList.contains('d-none')) {
            updateProjectListContent(category);
        }
    });
}

// Initialize notification system
document.addEventListener('DOMContentLoaded', function() {
    // Create notifications only on first login (check sessionStorage flag)
    const hasCreatedNotificationsOnLogin = sessionStorage.getItem('notifications_created_on_login');
    
    if (!hasCreatedNotificationsOnLogin) {
        // First page load after login - create notifications
        Promise.all([
            fetch(window.BASE_URL + '/api/budget_notifications.php').catch(e => console.error('Budget notifications error:', e)),
            fetch(window.BASE_URL + '/api/deadline_notifications.php').catch(e => console.error('Deadline notifications error:', e))
        ]).then(() => {
            // Mark that we've created notifications for this session
            sessionStorage.setItem('notifications_created_on_login', 'true');
            // Update badge after creating notifications
            if (typeof updateHeaderNotificationBadge === 'function') {
                setTimeout(updateHeaderNotificationBadge, 500);
            }
        });
    }
    
    // Update header badge
    if (typeof updateHeaderNotificationBadge === 'function') {
        updateHeaderNotificationBadge();
    }
    
    // Update navbar badge
    if (typeof updateNavbarNotificationCount === 'function') {
        updateNavbarNotificationCount();
    }
});
</script>

    <!-- Floating Action Button for Create Project -->
    <button type="button" class="fab" id="createProjectFab" onclick="showAddProjectModal()" title="Create Project" style="position: fixed; bottom: 2rem; right: 2rem; width: 56px; height: 56px; border-radius: 50%; background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); color: white; border: none; cursor: pointer; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); transition: all 0.3s ease; z-index: 1000; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
        <i class="fas fa-plus"></i>
    </button>

    <script>
        // Add hover effects to FAB button
        const fabBtn = document.getElementById('createProjectFab');
        if (fabBtn) {
            fabBtn.addEventListener('mouseover', function() {
                this.style.transform = 'scale(1.1)';
                this.style.boxShadow = '0 6px 20px rgba(0, 0, 0, 0.25)';
            });
            
            fabBtn.addEventListener('mouseout', function() {
                this.style.transform = 'scale(1)';
                this.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.15)';
            });
            
            fabBtn.addEventListener('mousedown', function() {
                this.style.transform = 'scale(0.95)';
            });
            
            fabBtn.addEventListener('mouseup', function() {
                this.style.transform = 'scale(1)';
            });
        }
    </script>

</body>
</html>
