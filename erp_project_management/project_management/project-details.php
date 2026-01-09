<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Project Details - iServe</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    
    <!-- Flatpickr -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <!-- Frappe Gantt -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/frappe-gantt@0.6.1/dist/frappe-gantt.css" />
    <script src="https://cdn.jsdelivr.net/npm/frappe-gantt@0.6.1/dist/frappe-gantt.min.js"></script>

    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/style.css" />
    <link rel="stylesheet" href="../css/navbar.css" />
    <link rel="stylesheet" href="css/resources.css" />
</head>

<body class="bg-primary-bg">

<?php include 'includes/navbar.php'; ?>

<div class="container mt-4">
    
    <!-- Project Header -->
    <div id="project-header" class="card secondary-card p-4 mb-4">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h1><i class="fas fa-project-diagram me-2"></i><span id="project-name">Loading...</span></h1>
                <p id="project-description" class="text-muted">Loading project details...</p>
                <div class="mt-3">
                    <span class="badge bg-primary me-2">
                        <i class="fas fa-calendar"></i> Start: <span id="project-start-date">-</span>
                    </span>
                    <span class="badge bg-primary me-2">
                        <i class="fas fa-calendar-check"></i> End: <span id="project-end-date">-</span>
                    </span>
                    <span class="badge bg-primary me-2">
                        <i class="fas fa-tasks"></i> Tasks: <span id="project-task-count">0</span>
                    </span>
                    <span class="badge bg-dark text-white">
                        <i class="fas fa-chart-line"></i> Progress: <span id="project-progress">0%</span>
                    </span>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-primary" onclick="showEditProjectModal()">
                    <i class="fas fa-edit me-1"></i> Edit Project
                </button>
                <a href="<?php echo $BASE_URL; ?>/index.php" class="btn btn-sm btn-outline-dark" onclick="return handleBackToProjects(event)">
                    <i class="fas fa-arrow-left me-1"></i> Back to Projects
                </a>
            </div>
        </div>
        
        <!-- Progress Bar -->
        <div class="mt-3">
            <div class="progress" style="height: 25px;">
                <div id="project-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" 
                     role="progressbar" style="width: 0%">
                    0%
                </div>
            </div>
        </div>
    </div>

    <!-- View Tabs -->
    <ul class="nav nav-tabs mb-4" id="projectTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="wbs-tab" data-bs-toggle="tab" data-bs-target="#wbs-view" type="button" role="tab">
                <i class="fas fa-sitemap me-1"></i> Work Breakdown Structure
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="gantt-tab" data-bs-toggle="tab" data-bs-target="#gantt-view" type="button" role="tab">
                <i class="fas fa-chart-gantt me-1"></i> Gantt Chart
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="timeline-tab" data-bs-toggle="tab" data-bs-target="#timeline-view" type="button" role="tab">
                <i class="fas fa-calendar-alt me-1"></i> Timeline
            </button>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content" id="projectTabContent">
        
        <!-- WBS View -->
        <div class="tab-pane fade show active" id="wbs-view" role="tabpanel">
            <div class="card secondary-card p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3><i class="fas fa-sitemap me-2"></i>Work Breakdown Structure</h3>
                    <button class="btn btn-primary" onclick="showAddTaskModal()">
                        <i class="fas fa-plus me-1"></i> Add Task
                    </button>
                </div>
                
                <div id="tasks-container">
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-spinner fa-spin fa-2x mb-3"></i>
                        <p>Loading tasks...</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gantt Chart View -->
        <div class="tab-pane fade" id="gantt-view" role="tabpanel">
            <div class="card secondary-card p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3><i class="fas fa-chart-gantt me-2"></i>Gantt Chart</h3>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-outline-primary" onclick="ganttChart.viewMode('Day')">
                            Day
                        </button>
                        <button class="btn btn-sm btn-outline-primary" onclick="ganttChart.viewMode('Week')">
                            Week
                        </button>
                        <button class="btn btn-sm btn-outline-primary" onclick="ganttChart.viewMode('Month')">
                            Month
                        </button>
                    </div>
                </div>
                <div id="gantt-container" style="overflow-x: auto;">
                    <svg id="gantt-chart"></svg>
                </div>
            </div>
        </div>

        <!-- Timeline View -->
        <div class="tab-pane fade" id="timeline-view" role="tabpanel">
            <div class="card secondary-card p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3><i class="fas fa-timeline me-2"></i>Project Timeline</h3>
                    <button class="btn btn-sm btn-outline-primary" onclick="refreshTimeline()">
                        <i class="fas fa-sync-alt me-1"></i> Refresh
                    </button>
                </div>
                <div id="timeline-container">
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-spinner fa-spin fa-2x mb-3"></i>
                        <p>Loading timeline...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Add Task Modal -->
<div class="modal fade" id="addTaskModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Add New Task</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addTaskForm">
                    <input type="hidden" id="task-project-id" name="project_id">
                    <input type="hidden" id="task-parent-id" name="parent_task_id">
                    <div class="mb-3">
                        <label class="form-label">Task Name *</label>
                        <input type="text" class="form-control" id="task-name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" id="task-description" name="description" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="task-start-date" name="start_date">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" id="task-end-date" name="end_date">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Depends On (Optional)</label>
                        <select class="form-select" id="task-dependency" name="dependency_task_id">
                            <option value="">No dependency</option>
                        </select>
                        <small class="form-text text-muted">Select a task that must be completed before this task starts</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Initial Progress (%)</label>
                        <input type="number" class="form-control" id="task-progress" name="progress_percent" min="0" max="100" value="0">
                    </div>
                </form>
            </div>
            <div class="modal-footer d-flex gap-2">
                <button type="button" class="btn btn-outline-dark" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Cancel
                </button>
                <button type="button" class="btn btn-primary" onclick="saveTask()">
                    <i class="fas fa-save me-1"></i> Save Task
                </button>
            </div>
        </div>
    </div>
</div>

<!-- View Task Description Modal -->
<div class="modal fade" id="viewTaskDescriptionModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-eye me-2"></i>Task Description: <span id="viewTaskDescriptionTitle"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Description:</label>
                    <div class="p-3 bg-light border rounded" id="viewTaskDescriptionContent" style="min-height: 100px; white-space: pre-wrap; word-wrap: break-word;">
                        Loading...
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-dark" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Close
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

<!-- Edit Task Modal -->
<div class="modal fade" id="editTaskModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Task</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editTaskForm">
                    <input type="hidden" id="edit-task-id" name="task_id">
                    <input type="hidden" id="edit-task-project-id" name="project_id">
                    <div class="mb-3">
                        <label class="form-label">Task Name *</label>
                        <input type="text" class="form-control" id="edit-task-name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" id="edit-task-description" name="description" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="edit-task-start-date" name="start_date">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" id="edit-task-end-date" name="end_date">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Depends On (Optional)</label>
                        <select class="form-select" id="edit-task-dependency" name="dependency_task_id">
                            <option value="">No dependency</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Progress (%)</label>
                        <input type="number" class="form-control" id="edit-task-progress" name="progress_percent" min="0" max="100" value="0">
                    </div>
                </form>
            </div>
            <div class="modal-footer d-flex gap-2">
                <button type="button" class="btn btn-outline-dark" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Cancel
                </button>
                <button type="button" class="btn btn-outline-dark" onclick="showDeleteTaskModalFromForm()">
                    <i class="fas fa-trash me-1"></i> Delete
                </button>
                <button type="button" class="btn btn-primary" onclick="updateTask()">
                    <i class="fas fa-save me-1"></i> Update Task
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
                        <input type="text" class="form-control" id="edit-project-name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" id="edit-project-description" name="description" rows="3"></textarea>
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
                        <input type="number" class="form-control" id="edit-project-budget" name="budget" step="0.01" min="0">
                    </div>
                </form>
            </div>
            <div class="modal-footer d-flex gap-2">
                <button type="button" class="btn btn-outline-dark" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Cancel
                </button>
                <button type="button" class="btn btn-primary" onclick="updateProject()">
                    <i class="fas fa-save me-1"></i> Update Project
                </button>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/gantt.js"></script>
<script src="../js/main.js"></script>

<script>
// Set BASE_URL for API calls
window.BASE_URL = '<?php echo $BASE_URL ?? ""; ?>';

// Global variables
let currentProjectId = null;
let currentProject = null;
let currentTasks = [];
let ganttChart = null;

// Initialize the page when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize progress bar with 0%
    updateProjectProgressBar(0);
    // Get project ID from URL
    const urlParams = new URLSearchParams(window.location.search);
    currentProjectId = urlParams.get('project_id');
    
    if (!currentProjectId) {
        showError('Project ID is required');
        setTimeout(() => {
            window.location.href = '<?php echo $BASE_URL; ?>/index.php';
        });
        return;
    }
    
    loadProjectDetails();
    
    // Initialize notification system (includes deadline checking)
    // Notification system is initialized in main.js
    
    // Initialize date pickers
    initializeTaskDatePickers();
    
    // Setup tab switching
    const tabButtons = document.querySelectorAll('#projectTabs button[data-bs-toggle="tab"]');
    tabButtons.forEach(button => {
        button.addEventListener('shown.bs.tab', function(event) {
            if (event.target.id === 'gantt-tab') {
                initializeGanttChart();
            } else if (event.target.id === 'timeline-tab') {
                renderTimeline();
            }
        });
    });
});

// Initialize date pickers for tasks
function initializeTaskDatePickers() {
    const today = new Date().toISOString().split('T')[0];
    
    // Get project dates for restrictions
    const projectStartDate = currentProject?.start_date || today;
    const projectEndDate = currentProject?.end_date;
    const taskStartMin = projectStartDate > today ? projectStartDate : today;
    
    // Function to show error for past dates
    function showPastDateError() {
        if (typeof window.showToast === 'function') {
            window.showToast('Cannot select past dates', 'error', null, 3000);
        } else {
            alert('❌ Cannot pick past dates');
        }
    }
    
    // Task start date
    window.taskStartDatePicker = flatpickr('#task-start-date', {
        minDate: taskStartMin,
        maxDate: projectEndDate || null,
        dateFormat: 'Y-m-d',
        onDayCreate: function(dObj, dStr, fp, dayElem) {
            if (dayElem.dateObj < new Date(taskStartMin)) {
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
        },
        onChange: function(selectedDates, dateStr, instance) {
            if (dateStr && window.taskEndDatePicker) {
                window.taskEndDatePicker.set('minDate', dateStr);
                // Also ensure end date doesn't exceed project end date
                if (projectEndDate) {
                    window.taskEndDatePicker.set('maxDate', projectEndDate);
                }
            }
        }
    });
    
    // Task end date
    window.taskEndDatePicker = flatpickr('#task-end-date', {
        minDate: taskStartMin,
        maxDate: projectEndDate || null,
        dateFormat: 'Y-m-d',
        onDayCreate: function(dObj, dStr, fp, dayElem) {
            if (dayElem.dateObj < new Date(taskStartMin)) {
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
    
    // Edit task start date (reuse project dates from above)
    window.editTaskStartDatePicker = flatpickr('#edit-task-start-date', {
        minDate: taskStartMin,
        maxDate: projectEndDate || null,
        dateFormat: 'Y-m-d',
        onDayCreate: function(dObj, dStr, fp, dayElem) {
            if (dayElem.dateObj < new Date(taskStartMin)) {
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
        },
        onChange: function(selectedDates, dateStr, instance) {
            if (dateStr && window.editTaskEndDatePicker) {
                window.editTaskEndDatePicker.set('minDate', dateStr);
                // Also ensure end date doesn't exceed project end date
                if (projectEndDate) {
                    window.editTaskEndDatePicker.set('maxDate', projectEndDate);
                }
            }
        }
    });
    
    // Edit task end date
    window.editTaskEndDatePicker = flatpickr('#edit-task-end-date', {
        minDate: taskStartMin,
        maxDate: projectEndDate || null,
        dateFormat: 'Y-m-d',
        onDayCreate: function(dObj, dStr, fp, dayElem) {
            if (dayElem.dateObj < new Date(taskStartMin)) {
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
    });
}

// Load project details
async function loadProjectDetails() {
    try {
        const response = await fetch(`api/project.php?project_id=${currentProjectId}`);
        const data = await response.json();
        
        if (data.error) {
            throw new Error(data.error);
        }
        
        currentProject = data.project;
        currentTasks = data.tasks || [];
        
        renderProjectHeader();
        renderWBS();
        
        // Initialize Gantt if on that tab
        const activeTab = document.querySelector('#projectTabs .nav-link.active');
        if (activeTab && activeTab.id === 'gantt-tab') {
            initializeGanttChart();
        }
        
    } catch (error) {
        console.error('Error loading project:', error);
        showError('Failed to load project details: ' + error.message);
    }
}

// Render project header
function renderProjectHeader() {
    document.getElementById('project-name').textContent = currentProject.name;
    document.getElementById('project-description').textContent = currentProject.description || 'No description';
    document.getElementById('project-start-date').textContent = currentProject.start_date || 'Not set';
    document.getElementById('project-end-date').textContent = currentProject.end_date || 'Not set';
    document.getElementById('project-task-count').textContent = currentProject.total_tasks || 0;
    document.getElementById('project-progress').textContent = (currentProject.overall_progress || 0) + '%';
    
    const progressBar = document.getElementById('project-progress-bar');
    const progress = currentProject.overall_progress || 0;
    progressBar.style.width = progress + '%';
    progressBar.textContent = progress + '%';
    progressBar.setAttribute('aria-valuenow', progress);
}

// Render Work Breakdown Structure
function renderWBS() {
    const container = document.getElementById('tasks-container');
    
    if (currentTasks.length === 0) {
        container.innerHTML = `
            <div class="text-center text-muted py-5">
                <i class="fas fa-tasks fa-3x mb-3"></i>
                <p>No tasks yet. Click "Add Task" to create your first task.</p>
            </div>
        `;
        return;
    }
    
    // Build task map for dependency lookup
    const taskMap = new Map();
    currentTasks.forEach(task => {
        taskMap.set(task.task_id, task);
    });
    
    // Separate parent tasks and subtasks
    const parentTasks = currentTasks.filter(task => !task.parent_task_id);
    const subtasksMap = new Map();
    
    // Sort subtasks: urgent/unfinished at top, completed at bottom (by date)
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    currentTasks.forEach(task => {
        if (task.parent_task_id) {
            if (!subtasksMap.has(task.parent_task_id)) {
                subtasksMap.set(task.parent_task_id, []);
            }
            subtasksMap.get(task.parent_task_id).push(task);
        }
    });
    
    // Sort subtasks within each parent
    subtasksMap.forEach((subtasks, parentId) => {
        subtasks.sort((a, b) => {
            const aCompleted = a.progress_percent === 100;
            const bCompleted = b.progress_percent === 100;
            const aEndDate = a.end_date ? new Date(a.end_date) : null;
            const bEndDate = b.end_date ? new Date(b.end_date) : null;
            const aOverdue = aEndDate && aEndDate < today && !aCompleted;
            const bOverdue = bEndDate && bEndDate < today && !bCompleted;
            
            // Priority: 1 = urgent/overdue, 2 = active/unfinished, 3 = completed
            const aPriority = aCompleted ? 3 : (aOverdue ? 1 : 2);
            const bPriority = bCompleted ? 3 : (bOverdue ? 1 : 2);
            
            // First sort by priority
            if (aPriority !== bPriority) {
                return aPriority - bPriority;
            }
            
            // If both completed, sort by end_date DESC (most recent first)
            if (aCompleted && bCompleted) {
                if (aEndDate && bEndDate) {
                    return bEndDate - aEndDate;
                }
                return aEndDate ? -1 : (bEndDate ? 1 : 0);
            }
            
            // If both urgent or both active, sort by end_date ASC (earliest first)
            if (aEndDate && bEndDate) {
                return aEndDate - bEndDate;
            }
            
            // Fallback to start_date
            const aStartDate = a.start_date ? new Date(a.start_date) : new Date(0);
            const bStartDate = b.start_date ? new Date(b.start_date) : new Date(0);
            return aStartDate - bStartDate;
        });
    });
    
    // Render tasks as cards
    let html = '<div class="row g-4">';
    
    parentTasks.forEach(task => {
        const progressClass = task.progress_percent === 100 ? 'bg-primary' : 
                             task.progress_percent >= 75 ? 'bg-success' :
                             task.progress_percent >= 50 ? 'bg-info' :
                             task.progress_percent >= 25 ? 'bg-warning' : 'bg-secondary';
        const dependencyTask = task.dependency_task_id ? taskMap.get(task.dependency_task_id) : null;
        const subtasks = subtasksMap.get(task.task_id) || [];
        const hasSubtasks = subtasks.length > 0;
        
        html += `<div class="col-md-6 col-lg-4">
            <div class="card h-100 shadow-sm border-secondary">
                <div class="card-header bg-white border-bottom">
                    <div class="d-flex justify-content-between align-items-start">
                        <h5 class="card-title mb-0 flex-grow-1" style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; min-width: 0;" title="${escapeHtml(task.name)}">
                            <i class="fas fa-tasks me-2" style="color: #1a6985;"></i>
                            ${escapeHtml(task.name)}
                        </h5>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-dark" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="#" onclick="editTask(${task.task_id})">
                                    <i class="fas fa-edit me-2"></i> Edit
                                </a></li>
                                <li><a class="dropdown-item" href="#" onclick="showAddSubtaskModal(${task.task_id})">
                                    <i class="fas fa-plus-circle me-2"></i> Add Subtask
                                </a></li>
                                <li><a class="dropdown-item" href="#" onclick="showDeleteTaskModal(${task.task_id}, '${escapeHtml(task.name)}', ${hasSubtasks})">
                                    <i class="fas fa-trash me-2"></i> Delete
                                </a></li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="task-description-container mb-3">
                        <p class="card-text text-muted small mb-2 task-description-text" style="display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis; line-height: 1.4; min-height: 2.8em;">
                            ${escapeHtml(task.description || 'No description')}
                        </p>
                        <button class="btn btn-sm btn-outline-primary w-100" onclick="showTaskDescriptionModal(${task.task_id}, ${JSON.stringify(task.name)}, ${JSON.stringify(task.description || 'No description')})">
                            <i class="fas fa-eye me-1"></i> View Full Description
                        </button>
                    </div>
                    
                    <div class="mb-3 task-info-section">
                        <div class="d-flex justify-content-between align-items-center mb-2 task-info-row">
                            <small class="text-muted task-info-label"><i class="fas fa-calendar-alt me-1"></i>Start:</small>
                            <small class="fw-semibold task-info-value">${task.start_date ? new Date(task.start_date).toLocaleDateString() : 'Not set'}</small>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2 task-info-row">
                            <small class="text-muted task-info-label"><i class="fas fa-calendar-check me-1"></i>End:</small>
                            <small class="fw-semibold task-info-value">${task.end_date ? new Date(task.end_date).toLocaleDateString() : 'Not set'}</small>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2 task-info-row">
                            <small class="text-muted task-info-label"><i class="fas fa-link me-1"></i>Depends on:</small>
                            <span class="badge ${dependencyTask ? 'bg-primary' : 'bg-secondary'} task-info-value">${dependencyTask ? escapeHtml(dependencyTask.name) : 'None'}</span>
                        </div>
                        ${hasSubtasks ? `
                        <div class="d-flex justify-content-between align-items-center mb-2 task-info-row">
                            <small class="text-muted task-info-label"><i class="fas fa-list-ul me-1"></i>Subtasks:</small>
                            <span class="badge bg-info task-info-value">${subtasks.length}</span>
                        </div>
                        ` : ''}
                    </div>
                    
                    <div class="mb-3 task-progress-section">
                        <div class="d-flex justify-content-between align-items-center mb-2 task-info-row">
                            <small class="text-muted fw-semibold task-info-label">Progress:</small>
                            <span class="badge ${progressClass} text-white task-info-value">${task.progress_percent}%</span>
                        </div>
                        <div class="progress mb-2" style="height: 25px;">
                            <div class="progress-bar ${progressClass}" style="width: ${task.progress_percent}%">
                                ${task.progress_percent}%
                            </div>
                        </div>
                        ${hasSubtasks ? `
                        <div class="alert alert-info small mb-0">
                            <i class="fas fa-info-circle me-1"></i>Progress is calculated from subtasks
                        </div>
                        ` : `
                        <div class="progress-toggle-buttons">
                            <div class="btn-group btn-group-sm w-100" role="group">
                                <button type="button" class="btn ${task.progress_percent === 0 ? 'btn-primary' : 'btn-outline-dark'}" 
                                        onclick="updateTaskProgress(${task.task_id}, 0)" title="0%">
                                    0%
                                </button>
                                <button type="button" class="btn ${task.progress_percent === 25 ? 'btn-primary' : 'btn-outline-dark'}" 
                                        onclick="updateTaskProgress(${task.task_id}, 25)" title="25%">
                                    25%
                                </button>
                                <button type="button" class="btn ${task.progress_percent === 50 ? 'btn-primary' : 'btn-outline-dark'}" 
                                        onclick="updateTaskProgress(${task.task_id}, 50)" title="50%">
                                    50%
                                </button>
                                <button type="button" class="btn ${task.progress_percent === 75 ? 'btn-primary' : 'btn-outline-dark'}" 
                                        onclick="updateTaskProgress(${task.task_id}, 75)" title="75%">
                                    75%
                                </button>
                                <button type="button" class="btn ${task.progress_percent === 100 ? 'btn-primary' : 'btn-outline-dark'}" 
                                        onclick="updateTaskProgress(${task.task_id}, 100)" title="100%">
                                    100%
                                </button>
                            </div>
                        </div>
                        `}
                    </div>
                    
                    ${hasSubtasks ? `
                    <div class="subtasks-container mt-3">
                        <h6 class="mb-2"><i class="fas fa-list-ul me-2"></i>Subtasks:</h6>
                        ${subtasks.map(subtask => {
                            const subtaskProgressClass = subtask.progress_percent === 100 ? 'bg-primary' : 
                                                         subtask.progress_percent >= 75 ? 'bg-success' :
                                                         subtask.progress_percent >= 50 ? 'bg-info' :
                                                         subtask.progress_percent >= 25 ? 'bg-warning' : 'bg-secondary';
                            return `
                            <div class="subtask-card border rounded p-2 mb-2" style="background-color: #f8f9fa;">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div class="flex-grow-1" style="min-width: 0;">
                                        <strong style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: block;" title="${escapeHtml(subtask.name)}">
                                            <i class="fas fa-circle-notch me-1" style="font-size: 0.6em;"></i>${escapeHtml(subtask.name)}
                                        </strong>
                                        <div class="progress mt-2" style="height: 20px;">
                                            <div class="progress-bar ${subtaskProgressClass}" style="width: ${subtask.progress_percent}%">
                                                ${subtask.progress_percent}%
                                            </div>
                                        </div>
                                    </div>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-dark" type="button" data-bs-toggle="dropdown">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li><a class="dropdown-item" href="#" onclick="editTask(${subtask.task_id})">
                                                <i class="fas fa-edit me-2"></i> Edit
                                            </a></li>
                                            <li><a class="dropdown-item" href="#" onclick="showDeleteTaskModal(${subtask.task_id}, '${escapeHtml(subtask.name)}', false)">
                                                <i class="fas fa-trash me-2"></i> Delete
                                            </a></li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="btn-group btn-group-sm w-100" role="group">
                                    <button type="button" class="btn ${subtask.progress_percent === 0 ? 'btn-primary' : 'btn-outline-dark'}" 
                                            onclick="updateTaskProgress(${subtask.task_id}, 0)" title="0%">
                                        0%
                                    </button>
                                    <button type="button" class="btn ${subtask.progress_percent === 25 ? 'btn-primary' : 'btn-outline-dark'}" 
                                            onclick="updateTaskProgress(${subtask.task_id}, 25)" title="25%">
                                        25%
                                    </button>
                                    <button type="button" class="btn ${subtask.progress_percent === 50 ? 'btn-primary' : 'btn-outline-dark'}" 
                                            onclick="updateTaskProgress(${subtask.task_id}, 50)" title="50%">
                                        50%
                                    </button>
                                    <button type="button" class="btn ${subtask.progress_percent === 75 ? 'btn-primary' : 'btn-outline-dark'}" 
                                            onclick="updateTaskProgress(${subtask.task_id}, 75)" title="75%">
                                        75%
                                    </button>
                                    <button type="button" class="btn ${subtask.progress_percent === 100 ? 'btn-primary' : 'btn-outline-dark'}" 
                                            onclick="updateTaskProgress(${subtask.task_id}, 100)" title="100%">
                                        100%
                                    </button>
                                </div>
                            </div>
                            `;
                        }).join('')}
                    </div>
                    ` : ''}
                </div>
            </div>
        </div>`;
    });
    
    html += '</div>';
    container.innerHTML = html;
}

// Update task type label
function updateTaskTypeLabel() {
    const dependencySelect = document.getElementById('task-dependency');
    const modalTitle = document.querySelector('#addTaskModal .modal-title');
    if (dependencySelect && modalTitle) {
        if (dependencySelect.value) {
            modalTitle.innerHTML = '<i class="fas fa-plus-circle me-2"></i>Add New Sub-task';
        } else {
            modalTitle.innerHTML = '<i class="fas fa-plus-circle me-2"></i>Add New Task';
        }
    }
}

// Show add task modal
function showAddTaskModal() {
    document.getElementById('task-project-id').value = currentProjectId;
    document.getElementById('task-parent-id').value = '';
    document.getElementById('task-name').value = '';
    document.getElementById('task-description').value = '';
    document.getElementById('task-start-date').value = '';
    document.getElementById('task-end-date').value = '';
    document.getElementById('task-dependency').value = '';
    document.getElementById('task-progress').value = '0';
    
    // Update modal title
    const modalTitle = document.querySelector('#addTaskModal .modal-title');
    if (modalTitle) {
        modalTitle.innerHTML = '<i class="fas fa-plus-circle me-2"></i>Add New Task';
    }
    
    // Populate dependency dropdown (only parent tasks)
    const dependencySelect = document.getElementById('task-dependency');
    dependencySelect.innerHTML = '<option value="">No dependency</option>';
    currentTasks.forEach(task => {
        if (!task.parent_task_id) {
            dependencySelect.innerHTML += `<option value="${task.task_id}">${task.name}</option>`;
        }
    });
    
    // Set date restrictions based on project timeline
    const today = new Date().toISOString().split('T')[0];
    const projectStartDate = currentProject?.start_date || today;
    const projectEndDate = currentProject?.end_date;
    
    const taskStartMin = projectStartDate > today ? projectStartDate : today;
    
    if (window.taskStartDatePicker) {
        window.taskStartDatePicker.setDate(null);
        window.taskStartDatePicker.set('minDate', taskStartMin);
        window.taskStartDatePicker.set('maxDate', projectEndDate || null);
    }
    
    if (window.taskEndDatePicker) {
        window.taskEndDatePicker.setDate(null);
        window.taskEndDatePicker.set('minDate', taskStartMin);
        window.taskEndDatePicker.set('maxDate', projectEndDate || null);
    }
    
    const modal = new bootstrap.Modal(document.getElementById('addTaskModal'));
    modal.show();
}

// Show add subtask modal
function showAddSubtaskModal(parentTaskId) {
    document.getElementById('task-project-id').value = currentProjectId;
    document.getElementById('task-parent-id').value = parentTaskId;
    document.getElementById('task-name').value = '';
    document.getElementById('task-description').value = '';
    document.getElementById('task-start-date').value = '';
    document.getElementById('task-end-date').value = '';
    document.getElementById('task-dependency').value = '';
    document.getElementById('task-progress').value = '0';
    
    // Update modal title
    const modalTitle = document.querySelector('#addTaskModal .modal-title');
    if (modalTitle) {
        const parentTask = currentTasks.find(t => t.task_id == parentTaskId);
        modalTitle.innerHTML = `<i class="fas fa-plus-circle me-2"></i>Add Subtask to "${parentTask ? escapeHtml(parentTask.name) : 'Task'}"`;
    }
    
    // Populate dependency dropdown (only parent tasks, excluding the parent task itself)
    const dependencySelect = document.getElementById('task-dependency');
    dependencySelect.innerHTML = '<option value="">No dependency</option>';
    currentTasks.forEach(task => {
        if (!task.parent_task_id && task.task_id != parentTaskId) {
            dependencySelect.innerHTML += `<option value="${task.task_id}">${task.name}</option>`;
        }
    });
    
    // Set date restrictions based on parent task timeline
    const today = new Date().toISOString().split('T')[0];
    const parentTask = currentTasks.find(t => t.task_id == parentTaskId);
    
    if (parentTask) {
        const parentStartDate = parentTask.start_date || today;
        const parentEndDate = parentTask.end_date;
        const subtaskStartMin = parentStartDate > today ? parentStartDate : today;
        
        if (window.taskStartDatePicker) {
            window.taskStartDatePicker.setDate(null);
            window.taskStartDatePicker.set('minDate', subtaskStartMin);
            window.taskStartDatePicker.set('maxDate', parentEndDate || null);
        }
        
        if (window.taskEndDatePicker) {
            window.taskEndDatePicker.setDate(null);
            window.taskEndDatePicker.set('minDate', subtaskStartMin);
            window.taskEndDatePicker.set('maxDate', parentEndDate || null);
        }
    }
    
    const modal = new bootstrap.Modal(document.getElementById('addTaskModal'));
    modal.show();
}

// Save new task
async function saveTask() {
    const form = document.getElementById('addTaskForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const formData = new FormData(form);
    const taskData = {
        name: formData.get('name'),
        description: formData.get('description'),
        project_id: parseInt(formData.get('project_id')),
        start_date: formData.get('start_date') || null,
        end_date: formData.get('end_date') || null,
        dependency_task_id: formData.get('dependency_task_id') ? parseInt(formData.get('dependency_task_id')) : null,
        parent_task_id: formData.get('parent_task_id') ? parseInt(formData.get('parent_task_id')) : null,
        progress_percent: parseInt(formData.get('progress_percent')) || 0
    };
    
    try {
        const response = await fetch(window.BASE_URL + '/api/task.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(taskData)
        });
        
        const data = await response.json();
        
        if (data.error) {
            throw new Error(data.error);
        }
        
        bootstrap.Modal.getInstance(document.getElementById('addTaskModal')).hide();
        loadProjectDetails();
        
    } catch (error) {
        showError('Failed to create task: ' + error.message);
    }
}

// Edit task
async function editTask(taskId) {
    const task = currentTasks.find(t => t.task_id == taskId);
    if (!task) {
        showError('Task not found');
        return;
    }
    
    document.getElementById('edit-task-id').value = task.task_id;
    document.getElementById('edit-task-project-id').value = task.project_id;
    document.getElementById('edit-task-name').value = task.name;
    document.getElementById('edit-task-description').value = task.description || '';
    document.getElementById('edit-task-start-date').value = task.start_date || '';
    document.getElementById('edit-task-end-date').value = task.end_date || '';
    document.getElementById('edit-task-progress').value = task.progress_percent || 0;
    
    // Populate dependency dropdown (exclude current task)
    const dependencySelect = document.getElementById('edit-task-dependency');
    dependencySelect.innerHTML = '<option value="">No dependency</option>';
    currentTasks.forEach(t => {
        if (t.task_id != taskId) {
            // If editing a subtask, only show parent tasks (not other subtasks)
            if (task.parent_task_id) {
                if (!t.parent_task_id) {
                    dependencySelect.innerHTML += `<option value="${t.task_id}" ${t.task_id == task.dependency_task_id ? 'selected' : ''}>${t.name}</option>`;
                }
            } else {
                // If editing a parent task, show all other parent tasks
                if (!t.parent_task_id) {
                    dependencySelect.innerHTML += `<option value="${t.task_id}" ${t.task_id == task.dependency_task_id ? 'selected' : ''}>${t.name}</option>`;
                }
            }
        }
    });
    
    // Set date restrictions based on task type (subtask or regular task)
    const today = new Date().toISOString().split('T')[0];
    let minDate = today;
    let maxDate = null;
    
    if (task.parent_task_id) {
        // This is a subtask - limit to parent task dates
        const parentTask = currentTasks.find(t => t.task_id == task.parent_task_id);
        if (parentTask) {
            const parentStartDate = parentTask.start_date || today;
            minDate = parentStartDate > today ? parentStartDate : today;
            maxDate = parentTask.end_date || null;
        }
    } else {
        // Regular task - limit to project dates
        const projectStartDate = currentProject?.start_date || today;
        minDate = projectStartDate > today ? projectStartDate : today;
        maxDate = currentProject?.end_date || null;
    }
    
    if (window.editTaskStartDatePicker) {
        window.editTaskStartDatePicker.set('minDate', minDate);
        window.editTaskStartDatePicker.set('maxDate', maxDate);
    }
    
    if (window.editTaskEndDatePicker) {
        window.editTaskEndDatePicker.set('minDate', minDate);
        window.editTaskEndDatePicker.set('maxDate', maxDate);
    }
    
    const modal = new bootstrap.Modal(document.getElementById('editTaskModal'));
    modal.show();
}

// Update task
async function updateTask() {
    const form = document.getElementById('editTaskForm');
    const taskId = document.getElementById('edit-task-id').value;
    
    const taskData = {
        task_id: parseInt(taskId),
        name: document.getElementById('edit-task-name').value,
        description: document.getElementById('edit-task-description').value,
        start_date: document.getElementById('edit-task-start-date').value || null,
        end_date: document.getElementById('edit-task-end-date').value || null,
        dependency_task_id: document.getElementById('edit-task-dependency').value ? 
            parseInt(document.getElementById('edit-task-dependency').value) : null,
        progress_percent: parseInt(document.getElementById('edit-task-progress').value) || 0
    };
    
    try {
        const response = await fetch('api/task.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(taskData)
        });
        
        const data = await response.json();
        
        if (data.error) {
            throw new Error(data.error);
        }
        
        bootstrap.Modal.getInstance(document.getElementById('editTaskModal')).hide();
        loadProjectDetails();
        
    } catch (error) {
        showError('Failed to update task: ' + error.message);
    }
}

// Show delete task confirmation toast
let pendingDeleteTaskId = null;
function showDeleteTaskModal(taskId, taskName, hasSubTasks = false) {
    pendingDeleteTaskId = taskId;
    document.getElementById('deleteConfirmTitle').textContent = 'Delete Task';
    const message = hasSubTasks 
        ? `<p>Are you sure you want to delete <strong>"${escapeHtml(taskName)}"</strong>?</p><p class="text-danger mb-0"><i class="fas fa-exclamation-triangle me-1"></i>This will also delete all its sub-tasks. This action cannot be undone.</p>`
        : `<p>Are you sure you want to delete <strong>"${escapeHtml(taskName)}"</strong>?</p><p class="text-danger mb-0"><i class="fas fa-exclamation-triangle me-1"></i>This action cannot be undone.</p>`;
    document.getElementById('deleteConfirmMessage').innerHTML = message;
    document.getElementById('deleteConfirmBtn').onclick = () => confirmDeleteTask(taskId);
    
    const modal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
    modal.show();
}

// Show delete task modal from edit form
function showDeleteTaskModalFromForm() {
    const taskId = document.getElementById('edit-task-id').value;
    const taskName = document.getElementById('edit-task-name').value;
    showDeleteTaskModal(parseInt(taskId), taskName, false);
}

// Confirm delete task
async function confirmDeleteTask(taskId) {
    try {
        const response = await fetch('api/task.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ task_id: parseInt(taskId) })
        });
        
        if (!response.ok) {
            const errorData = await response.json().catch(() => ({ error: 'Failed to delete task' }));
            throw new Error(errorData.error || 'Failed to delete task');
        }
        
        const data = await response.json();
        
        if (data.error) {
            throw new Error(data.error);
        }
        
        bootstrap.Modal.getInstance(document.getElementById('deleteConfirmModal')).hide();
        const editModal = bootstrap.Modal.getInstance(document.getElementById('editTaskModal'));
        if (editModal) {
            editModal.hide();
        }
        showSuccess('Task and all associated subtasks deleted successfully');
        loadProjectDetails();
        
    } catch (error) {
        console.error('Delete task error:', error);
        showError('Failed to delete task: ' + error.message);
    }
}

// Function to update the main project progress bar
function updateProjectProgressBar(progress) {
    const progressBar = document.getElementById('project-progress-bar');
    if (progressBar) {
        progressBar.style.width = `${progress}%`;
        progressBar.textContent = `${progress}%`;
        progressBar.setAttribute('aria-valuenow', progress);
    }
}

// Update task progress
async function updateTaskProgress(taskId, progress) {
    const task = currentTasks.find(t => t.task_id == taskId);
    if (!task) {
        showError('Task not found');
        return;
    }
    
    try {
        const response = await fetch('api/task.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                task_id: parseInt(taskId),
                name: task.name,
                description: task.description || '',
                start_date: task.start_date,
                end_date: task.end_date,
                dependency_task_id: task.dependency_task_id,
                parent_task_id: task.parent_task_id || null,
                progress_percent: progress
            })
        });
        
        const data = await response.json();
        
        if (data.error) {
            throw new Error(data.error);
        }
        
        // Reload project details to refresh display and recalculate overall progress
        loadProjectDetails();
        
    } catch (error) {
        console.error('Error updating task progress:', error);
        showError('Failed to update task progress: ' + error.message);
    }
}

// Show edit project modal
function showEditProjectModal() {
    document.getElementById('edit-project-id').value = currentProject.project_id;
    document.getElementById('edit-project-name').value = currentProject.name;
    document.getElementById('edit-project-description').value = currentProject.description || '';
    document.getElementById('edit-project-start-date').value = currentProject.start_date || '';
    document.getElementById('edit-project-end-date').value = currentProject.end_date || '';
    document.getElementById('edit-project-budget').value = currentProject.budget || '';
    
    const modal = new bootstrap.Modal(document.getElementById('editProjectModal'));
    modal.show();
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
        
        // Blur anything still focused inside the modal to avoid accessibility warnings when it's hidden
        const modalEl = document.getElementById('editProjectModal');
        if (document.activeElement && modalEl.contains(document.activeElement)) {
            document.activeElement.blur();
        }
        bootstrap.Modal.getInstance(modalEl).hide();
        loadProjectDetails();
        
    } catch (error) {
        showError('Failed to update project: ' + error.message);
    }
}

// Initialize Gantt chart
function initializeGanttChart() {
    if (!currentTasks || currentTasks.length === 0) {
        document.getElementById('gantt-container').innerHTML = `
            <div class="text-center text-muted py-5">
                <i class="fas fa-chart-gantt fa-3x mb-3"></i>
                <p>No tasks available for Gantt chart. Add tasks to visualize the timeline.</p>
            </div>
        `;
        return;
    }
    
    // Convert tasks to Gantt format
    const ganttData = currentTasks.map(task => {
        const start = task.start_date ? new Date(task.start_date) : new Date();
        const end = task.end_date ? new Date(task.end_date) : new Date(start.getTime() + 7 * 24 * 60 * 60 * 1000); // Default 7 days
        
        return {
            id: `task-${task.task_id}`,
            name: task.name,
            start: start.toISOString().split('T')[0],
            end: end.toISOString().split('T')[0],
            progress: task.progress_percent || 0,
            dependencies: task.dependency_task_id ? `task-${task.dependency_task_id}` : null,
            custom_class: task.progress_percent === 100 ? 'completed' : 
                         task.progress_percent > 0 ? 'in-progress' : 'not-started'
        };
    }).filter(task => task.dependencies !== null || true); // Include all tasks
    
    // Clear previous chart
    const container = document.getElementById('gantt-container');
    container.innerHTML = '<svg id="gantt-chart"></svg>';
    
    // Initialize Frappe Gantt
    ganttChart = new Gantt('#gantt-chart', ganttData, {
        view_mode: 'Month',
        header_height: 50,
        column_width: 30,
        step: 24,
        bar_height: 20,
        bar_corner_radius: 3,
        arrow_curve: 5,
        padding: 18,
        date_format: 'YYYY-MM-DD',
        language: 'en',
        on_click: function(task) {
            const taskId = task.id.replace('task-', '');
            editTask(parseInt(taskId));
        },
        on_date_change: function(task, start, end) {
            // Handle date changes if needed
            console.log('Task date changed:', task, start, end);
        },
        on_progress_change: function(task, progress) {
            // Handle progress changes if needed
            console.log('Task progress changed:', task, progress);
        },
        on_view_change: function(mode) {
            console.log('View mode changed:', mode);
        }
    });
}

// Render timeline view
function renderTimeline() {
    const container = document.getElementById('timeline-container');
    
    if (!currentTasks || currentTasks.length === 0) {
        container.innerHTML = `
            <div class="text-center text-muted py-5">
                <i class="fas fa-calendar-alt fa-3x mb-3"></i>
                <p>No tasks available for timeline view. Add tasks to see the timeline.</p>
            </div>
        `;
        return;
    }
    
    // Sort tasks: urgent/unfinished at top, completed at bottom (by date)
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    const sortedTasks = [...currentTasks].sort((a, b) => {
        const aCompleted = a.progress_percent === 100;
        const bCompleted = b.progress_percent === 100;
        const aEndDate = a.end_date ? new Date(a.end_date) : null;
        const bEndDate = b.end_date ? new Date(b.end_date) : null;
        const aOverdue = aEndDate && aEndDate < today && !aCompleted;
        const bOverdue = bEndDate && bEndDate < today && !bCompleted;
        
        // Priority: 1 = urgent/overdue, 2 = active/unfinished, 3 = completed
        const aPriority = aCompleted ? 3 : (aOverdue ? 1 : 2);
        const bPriority = bCompleted ? 3 : (bOverdue ? 1 : 2);
        
        // First sort by priority
        if (aPriority !== bPriority) {
            return aPriority - bPriority;
        }
        
        // If both completed, sort by end_date DESC (most recent first)
        if (aCompleted && bCompleted) {
            if (aEndDate && bEndDate) {
                return bEndDate - aEndDate;
            }
            return aEndDate ? -1 : (bEndDate ? 1 : 0);
        }
        
        // If both urgent or both active, sort by end_date ASC (earliest first)
        if (aEndDate && bEndDate) {
            return aEndDate - bEndDate;
        }
        
        // Fallback to start_date
        const aStartDate = a.start_date ? new Date(a.start_date) : new Date(0);
        const bStartDate = b.start_date ? new Date(b.start_date) : new Date(0);
        return aStartDate - bStartDate;
    });
    
    let html = '<div class="timeline">';
    
    sortedTasks.forEach((task, index) => {
        const startDate = task.start_date ? new Date(task.start_date) : null;
        const endDate = task.end_date ? new Date(task.end_date) : null;
        const duration = startDate && endDate ? 
            Math.ceil((endDate - startDate) / (1000 * 60 * 60 * 24)) : 'N/A';
        
        const isOverdue = endDate && endDate < new Date() && task.progress_percent < 100;
        const statusClass = task.progress_percent === 100 ? 'completed' : 
                           task.progress_percent > 0 ? 'in-progress' : 'not-started';
        
        html += `<div class="timeline-item ${statusClass} ${isOverdue ? 'overdue' : ''}" data-task-id="${task.task_id}">`;
        html += `<div class="timeline-marker"></div>`;
        html += `<div class="timeline-content">`;
        html += `<h5>${escapeHtml(task.name)}</h5>`;
        html += `<p class="text-muted">${escapeHtml(task.description || 'No description')}</p>`;
        html += `<div class="d-flex gap-3 mb-2">`;
        html += `<span><i class="fas fa-calendar"></i> Start: ${task.start_date || 'Not set'}</span>`;
        html += `<span><i class="fas fa-calendar-check"></i> End: ${task.end_date || 'Not set'}</span>`;
        html += `<span><i class="fas fa-clock"></i> Duration: ${duration} days</span>`;
        html += `</div>`;
        if (task.dependency_task_name) {
            html += `<p class="small"><i class="fas fa-link"></i> Depends on: <strong>${escapeHtml(task.dependency_task_name)}</strong></p>`;
        }
        html += `<div class="progress mb-2" style="height: 10px;">`;
        html += `<div class="progress-bar ${task.progress_percent === 100 ? 'bg-primary' : 'bg-dark'}" style="width: ${task.progress_percent}%"></div>`;
        html += `</div>`;
        html += `<button class="btn btn-sm btn-primary" onclick="editTask(${task.task_id})"><i class="fas fa-edit me-1"></i> Edit</button>`;
        html += `</div>`;
        html += `</div>`;
    });
    
    html += '</div>';
    container.innerHTML = html;
}

// Refresh timeline
function refreshTimeline() {
    renderTimeline();
}

// Utility function to escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Handle back to projects button click
function handleBackToProjects(event) {
    event.preventDefault();
    
    // Check if we came from index.php (opened in new tab)
    const openedFromIndex = sessionStorage.getItem('openedFromIndex');
    
    if (openedFromIndex === 'true') {
        // Clear the flag
        sessionStorage.removeItem('openedFromIndex');
        // Try to close the window/tab (works if opened with window.open())
        try {
            window.close();
            // If window.close() doesn't work (browser blocked it), navigate back after a short delay
            setTimeout(() => {
                // Check if window is still open (close was blocked)
                if (window && !window.closed) {
                    window.location.href = '<?php echo $BASE_URL; ?>/index.php';
                }
            }, 100);
        } catch (e) {
            // If we can't close, just navigate
            window.location.href = '<?php echo $BASE_URL; ?>/index.php';
        }
    } else {
        // Normal navigation if not opened from index
        window.location.href = '<?php echo $BASE_URL; ?>/index.php';
    }
    
    return false; // Prevent default navigation
}

// Show task description modal
function showTaskDescriptionModal(taskId, taskName, taskDescription) {
    document.getElementById('viewTaskDescriptionTitle').textContent = taskName;
    const descriptionContent = document.getElementById('viewTaskDescriptionContent');
    // taskDescription is already properly escaped from JSON.stringify
    descriptionContent.textContent = taskDescription || 'No description available.';
    
    const modal = new bootstrap.Modal(document.getElementById('viewTaskDescriptionModal'));
    modal.show();
}
</script>

</body>
</html>
