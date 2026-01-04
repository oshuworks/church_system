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

    <!-- Frappe Gantt -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/frappe-gantt@0.6.1/dist/frappe-gantt.css" />
    <script src="https://cdn.jsdelivr.net/npm/frappe-gantt@0.6.1/dist/frappe-gantt.min.js"></script>

    <!-- Shared CSS -->
    <link rel="stylesheet" href="/church_system-main/project_management/css/resources.css" />
    <link rel="stylesheet" href="/church_system-main/project_management/css/navbar.css" />
    
    <!-- Module-specific CSS -->
    <link rel="stylesheet" href="/church_system-main/project_management/modules/project_planning/css/style.css" />
</head>

<body class="bg-primary-bg">

<?php include '../../includes/navbar.php'; ?>

<div class="container mt-4">
    
    <!-- Project Header -->
    <div id="project-header" class="card secondary-card p-4 mb-4">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h1 id="project-name">Loading...</h1>
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
            <div>
                <button class="btn btn-primary" onclick="showEditProjectModal()">
                    <i class="fas fa-edit"></i> Edit Project
                </button>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Projects
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
                <i class="fas fa-sitemap"></i> Work Breakdown Structure
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="gantt-tab" data-bs-toggle="tab" data-bs-target="#gantt-view" type="button" role="tab">
                <i class="fas fa-chart-gantt"></i> Gantt Chart
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="timeline-tab" data-bs-toggle="tab" data-bs-target="#timeline-view" type="button" role="tab">
                <i class="fas fa-calendar-alt"></i> Timeline
            </button>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content" id="projectTabContent">
        
        <!-- WBS View -->
        <div class="tab-pane fade show active" id="wbs-view" role="tabpanel">
            <div class="card secondary-card p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3>Work Breakdown Structure</h3>
                    <button class="btn btn-primary" onclick="showAddTaskModal()">
                        <i class="fas fa-plus"></i> Add Task
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
                    <h3>Gantt Chart</h3>
                    <div>
                        <button class="btn btn-sm btn-outline-secondary" onclick="ganttChart.viewMode('Day')">
                            Day
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" onclick="ganttChart.viewMode('Week')">
                            Week
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" onclick="ganttChart.viewMode('Month')">
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
                    <h3>Project Timeline</h3>
                    <button class="btn btn-sm btn-outline-secondary" onclick="refreshTimeline()">
                        <i class="fas fa-sync"></i> Refresh
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
                <h5 class="modal-title">Add New Task</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addTaskForm">
                    <input type="hidden" id="task-project-id" name="project_id">
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
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveTask()">Save Task</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Task Modal -->
<div class="modal fade" id="editTaskModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Task</h5>
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
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="deleteTask()">Delete</button>
                <button type="button" class="btn btn-primary" onclick="updateTask()">Update Task</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Project Modal -->
<div class="modal fade" id="editProjectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Project</h5>
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
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="updateProject()">Update Project</button>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/church_system-main/project_management/modules/project_planning/js/gantt.js"></script>

<script>
// Global variables
let currentProjectId = null;
let currentProject = null;
let currentTasks = [];
let ganttChart = null;

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    // Get project ID from URL
    const urlParams = new URLSearchParams(window.location.search);
    currentProjectId = urlParams.get('project_id');
    
    if (!currentProjectId) {
        alert('Project ID is required');
        window.location.href = 'index.php';
        return;
    }
    
    loadProjectDetails();
    
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
        alert('Failed to load project details: ' + error.message);
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

// Render Work Breakdown Structure (with subtasks nested under parent tasks)
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

    // Build parent -> subtasks map
    const parentTasks = currentTasks.filter(t => !t.parent_task_id);
    const subtasksMap = new Map();
    currentTasks.forEach(t => {
        if (t.parent_task_id) {
            if (!subtasksMap.has(t.parent_task_id)) subtasksMap.set(t.parent_task_id, []);
            subtasksMap.get(t.parent_task_id).push(t);
        }
    });

    // Sort parents by start date then name
    parentTasks.sort((a, b) => {
        const da = a.start_date ? new Date(a.start_date) : new Date(0);
        const db = b.start_date ? new Date(b.start_date) : new Date(0);
        if (da - db !== 0) return da - db;
        return (a.name || '').localeCompare(b.name || '');
    });

    let html = '<div class="table-responsive"><table class="table table-hover">';
    html += '<thead><tr>';
    html += '<th>Task</th><th>Description</th><th>Start Date</th><th>End Date</th><th>Dependency</th><th>Progress</th><th>Actions</th>';
    html += '</tr></thead><tbody>';

    parentTasks.forEach(parent => {
        const progressClass = parent.progress_percent === 100 ? 'bg-primary' : 
                              parent.progress_percent > 0 ? 'bg-dark' : 'bg-secondary';
        const dependencyText = parent.dependency_task_name ? 
            `<span class="badge bg-primary">${escapeHtml(parent.dependency_task_name)}</span>` : 
            '<span class="text-muted">None</span>';

        html += `<tr data-task-id="${parent.task_id}">`;
        html += `<td><strong>${escapeHtml(parent.name)}</strong></td>`;
        const pDesc = escapeHtml(parent.description || '-');
        html += `<td>${pDesc.length > 140 ? pDesc.slice(0,140) + '...' : pDesc}<br/><button class="btn btn-sm btn-outline-primary mt-1" onclick='showTaskDescriptionModal(${parent.task_id}, ${JSON.stringify(parent.name)}, ${JSON.stringify(parent.description || '-')})'><i class="fas fa-eye me-1"></i> View Full Description</button></td>`;
        html += `<td>${parent.start_date || '-'}</td>`;
        html += `<td>${parent.end_date || '-'}</td>`;
        html += `<td>${dependencyText}</td>`;
        html += `<td>
            <div class="progress" style="height: 20px;">
                <div class="progress-bar ${progressClass}" style="width: ${parent.progress_percent}%">
                    ${parent.progress_percent}%
                </div>
            </div>
        </td>`;
        html += `<td>
            <button class="btn btn-sm btn-primary" onclick="editTask(${parent.task_id})">
                <i class="fas fa-edit"></i>
            </button>
        </td>`;
        html += `</tr>`;

        // render subtasks directly under parent
        const subs = subtasksMap.get(parent.task_id) || [];
        subs.forEach(sub => {
            const sProg = sub.progress_percent === 100 ? 'bg-primary' : sub.progress_percent > 0 ? 'bg-dark' : 'bg-secondary';
            const sDesc = escapeHtml(sub.description || '-');
            html += `<tr class="subtask-row" data-parent-id="${parent.task_id}">`;
            html += `<td class="ps-4"><i class="fas fa-level-down-alt me-2"></i>${escapeHtml(sub.name)}</td>`;
            html += `<td>${sDesc.length > 120 ? sDesc.slice(0,120) + '...' : sDesc}<br/><button class="btn btn-sm btn-outline-primary mt-1" onclick='showTaskDescriptionModal(${sub.task_id}, ${JSON.stringify(sub.name)}, ${JSON.stringify(sub.description || '-')})'><i class="fas fa-eye me-1"></i> View Full Description</button></td>`;
            html += `<td>${sub.start_date || '-'}</td>`;
            html += `<td>${sub.end_date || '-'}</td>`;
            html += `<td>${sub.dependency_task_name ? `<span class="badge bg-primary">${escapeHtml(sub.dependency_task_name)}</span>` : '<span class="text-muted">None</span>'}</td>`;
            html += `<td>
                <div class="progress" style="height: 20px;">
                    <div class="progress-bar ${sProg}" style="width: ${sub.progress_percent}%">
                        ${sub.progress_percent}%
                    </div>
                </div>
            </td>`;
            html += `<td>
                <button class="btn btn-sm btn-primary" onclick="editTask(${sub.task_id})">
                    <i class="fas fa-edit"></i>
                </button>
            </td>`;
            html += `</tr>`;
        });
    });

    html += '</tbody></table></div>';
    container.innerHTML = html;
}

// Show add task modal
function showAddTaskModal() {
    document.getElementById('task-project-id').value = currentProjectId;
    document.getElementById('task-name').value = '';
    document.getElementById('task-description').value = '';
    document.getElementById('task-start-date').value = '';
    document.getElementById('task-end-date').value = '';
    document.getElementById('task-dependency').value = '';
    document.getElementById('task-progress').value = '0';

    // Populate dependency dropdown with only parent tasks (no subtasks)
    const dependencySelect = document.getElementById('task-dependency');
    dependencySelect.innerHTML = '<option value="">No dependency</option>';
    currentTasks.forEach(task => {
        if (!task.parent_task_id) {
            dependencySelect.innerHTML += `<option value="${task.task_id}">${escapeHtml(task.name)}</option>`;
        }
    });

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
        progress_percent: parseInt(formData.get('progress_percent')) || 0
    };
    
    try {
        const response = await fetch('api/task.php', {
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
        alert('Failed to create task: ' + error.message);
    }
}

// Edit task
async function editTask(taskId) {
    const task = currentTasks.find(t => t.task_id == taskId);
    if (!task) {
        alert('Task not found');
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
            dependencySelect.innerHTML += `<option value="${t.task_id}" ${t.task_id == task.dependency_task_id ? 'selected' : ''}>${escapeHtml(t.name)}</option>`;
        }
    });
    
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
        alert('Failed to update task: ' + error.message);
    }
}

// Delete task
async function deleteTask() {
    if (!confirm('Are you sure you want to delete this task? This action cannot be undone.')) {
        return;
    }
    
    const taskId = document.getElementById('edit-task-id').value;
    
    try {
        const response = await fetch('api/task.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ task_id: parseInt(taskId) })
        });
        
        const data = await response.json();
        
        if (data.error) {
            throw new Error(data.error);
        }
        
        bootstrap.Modal.getInstance(document.getElementById('editTaskModal')).hide();
        loadProjectDetails();
        
    } catch (error) {
        alert('Failed to delete task: ' + error.message);
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
        const response = await fetch('api/project.php', {
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
        
        bootstrap.Modal.getInstance(document.getElementById('editProjectModal')).hide();
        loadProjectDetails();
        
    } catch (error) {
        alert('Failed to update project: ' + error.message);
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
    
    // Sort tasks by start date
    const sortedTasks = [...currentTasks].sort((a, b) => {
        const dateA = a.start_date ? new Date(a.start_date) : new Date(0);
        const dateB = b.start_date ? new Date(b.start_date) : new Date(0);
        return dateA - dateB;
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
        html += `<button class="btn btn-sm btn-primary" onclick="editTask(${task.task_id})">Edit</button>`;
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

// Show task description modal (module)
function showTaskDescriptionModal(taskId, taskName, taskDescription) {
    document.getElementById('viewTaskDescriptionTitleModule').textContent = taskName;
    const descriptionContent = document.getElementById('viewTaskDescriptionContentModule');
    const safeDesc = taskDescription ? escapeHtml(taskDescription) : 'No description available.';
    descriptionContent.innerHTML = safeDesc.replace(/\n/g, '<br>');
    const modal = new bootstrap.Modal(document.getElementById('viewTaskDescriptionModalModule'));
    modal.show();
}
</script>

</body>
</html>

