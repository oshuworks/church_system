<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Resourcify - Project Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />

    <!-- Your UI theme -->
    <link rel="stylesheet" href="css/resources.css" />
    <link rel="stylesheet" href="css/navbar.css" />
    <link rel="stylesheet" href="css/style.css" />
</head>

<body class="bg-primary-bg">

<?php include 'includes/navbar.php'; ?>

<div class="page-wrapper">
<div class="container mt-4">

    <!-- PROJECT LISTING -->
    <div id="projects-listing" class="card secondary-card p-4">

        <!-- HEADER -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Project Management - iServe</h1>
            <div class="d-flex gap-2">
                <button class="btn btn-primary" onclick="showAddProjectModal()">
                    <i class="fas fa-plus me-1"></i> Create Project
                </button>
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-primary" id="projectListViewBtn" onclick="setProjectView('list')">
                        <i class="fas fa-list me-1"></i> Project List
                    </button>
                    <button type="button" class="btn btn-outline-dark" id="projectAnalyticsViewBtn" onclick="setProjectView('analytics')">
                        <i class="fas fa-chart-bar me-1"></i> Analytics
                    </button>
                </div>
            </div>
        </div>

        <!-- SEARCH -->
        <div class="mb-3 position-relative">
            <i class="fas fa-search position-absolute" style="left: 15px; top: 50%; transform: translateY(-50%); color: #000000;"></i>
            <input type="text" class="form-control bg-dark text-dark border-secondary" 
                   id="projectSearchInput" placeholder="Search projects..." 
                   onkeyup="filterProjects()" style="padding-left: 40px;">
        </div>

        <!-- PROJECT CARDS GRID -->
        <div id="projects-cards-container" class="row g-3">
            <?php
            include 'includes/db.php';
            $result = $conn->query("
                SELECT p.*, 
                       COUNT(DISTINCT t.task_id) as task_count,
                       COUNT(DISTINCT CASE WHEN t.progress_percent = 100 THEN t.task_id END) as completed_tasks
                FROM projects p
                LEFT JOIN tasks t ON p.project_id = t.project_id
                GROUP BY p.project_id
                ORDER BY p.created_at DESC
            ");

            if ($result->num_rows === 0) {
                echo "<div class='col-12'><div class='text-center text-muted p-4'>No projects found. Click 'Create Project' to get started.</div></div>";
            }

            while ($row = $result->fetch_assoc()) {
                $overall_progress = 0;
                if ($row['task_count'] > 0) {
                    $overall_progress = round(($row['completed_tasks'] / $row['task_count']) * 100);
                }
                $progress_class = $overall_progress === 100 ? 'bg-primary' : ($overall_progress > 0 ? 'bg-dark' : 'bg-secondary');
                
                echo "<div class='col-md-6 col-lg-4 project-card' data-project-id='" . $row['project_id'] . "'>";
                echo "    <div class='card h-100 bg-white border-secondary shadow-sm'>";
                echo "        <div class='card-body d-flex flex-column'>";
                echo "            <h5 class='card-title mb-3'>";
                echo "                <a class='text-decoration-none text-primary fw-bold' 
                       href='javascript:void(0)' onclick='showProjectDetailsModal(" . $row['project_id'] . ")'>";
                echo "                   " . htmlspecialchars($row['name']) . "";
                echo "                </a>";
                echo "            </h5>";
                
                echo "            <p class='card-text text-muted small flex-grow-1 mb-3'>";
                echo "                " . htmlspecialchars($row['description'] ?? 'No description') . "";
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
                echo "                    <small class='fw-semibold'>₱" . number_format($row['budget'] ?? 0, 2) . "</small>";
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
                
                echo "            <div class='d-flex gap-2 mt-auto'>";
                echo "                <button onclick='showProjectDetailsModal(" . $row['project_id'] . ")' 
                       class='btn btn-primary btn-sm flex-fill'>";
                echo "                    <i class='fas fa-eye me-1'></i>View";
                echo "                </button>";
                echo "                <button onclick='showEditProjectModal(" . $row['project_id'] . ")' 
                        class='btn btn-outline-primary btn-sm'>";
                echo "                    <i class='fas fa-edit'></i>";
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
            <h1>Project Management - iServe</h1>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-dark" onclick="refreshProjectAnalytics()">
                    <i class="fas fa-sync-alt me-1"></i> Refresh
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

        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card bg-primary p-3 text-center" style="color: #60c3e6;">
                    <h3 id="total-projects">0</h3>
                    <p>Total Projects</p>
                </div>
            </div>

            <div class="col-md-3 mb-3">
                <div class="card bg-primary p-3 text-center" style="color: #60c3e6;">
                    <h3 id="projects-active">0</h3>
                    <p>Active Projects</p>
                </div>
            </div>

            <div class="col-md-3 mb-3">
                <div class="card bg-dark p-3 text-center" style="color: #ffffff;">
                    <h3 id="projects-upcoming">0</h3>
                    <p>Upcoming Projects</p>
                </div>
            </div>

            <div class="col-md-3 mb-3">
                <div class="card bg-dark p-3 text-center" style="color: #ffffff;">
                    <h3 id="projects-overdue">0</h3>
                    <p>Overdue Projects</p>
                </div>
            </div>
        </div>

        <!-- CHART AREA -->
        <div class="card bg-dark p-3">
            <h5 class="text-dark">Project Timeline Overview</h5>
            <canvas id="projectsTimelineChart"></canvas>
        </div>
    </div>

</div>

<!-- Add Project Modal -->
<div class="modal fade" id="addProjectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Project</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addProjectForm">
                    <div class="mb-3">
                        <label class="form-label">Project Name *</label>
                        <input type="text" class="form-control" id="project-name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" id="project-description" name="description" rows="3"></textarea>
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
                        <input type="number" class="form-control" id="project-budget" name="budget" step="0.01" min="0">
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

<!-- Project Details Modal -->
<div class="modal fade" id="projectDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Project Details</h5>
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
                <a id="view-full-details-btn" href="#" class="btn btn-primary" target="_blank">
                    <i class="fas fa-external-link-alt me-1"></i> View Full Details
                </a>
            </div>
        </div>
    </div>
</div>
</div>

<?php include 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/main.js"></script>

<script>
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
    
    const modal = new bootstrap.Modal(document.getElementById('addProjectModal'));
    modal.show();
}

// Save new project
async function saveProject() {
    const form = document.getElementById('addProjectForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const projectData = {
        name: document.getElementById('project-name').value,
        description: document.getElementById('project-description').value,
        start_date: document.getElementById('project-start-date').value || null,
        end_date: document.getElementById('project-end-date').value || null,
        budget: document.getElementById('project-budget').value ? 
            parseFloat(document.getElementById('project-budget').value) : null
    };
    
    try {
        const response = await fetch('api/project.php', {
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
        location.reload(); // Reload to show new project
        
    } catch (error) {
        alert('Failed to create project: ' + error.message);
    }
}

// Show edit project modal
async function showEditProjectModal(projectId) {
    try {
        const response = await fetch(`api/project.php?project_id=${projectId}`);
        const data = await response.json();
        
        if (data.error) {
            throw new Error(data.error);
        }
        
        const project = data.project;
        document.getElementById('edit-project-id').value = project.project_id;
        document.getElementById('edit-project-name').value = project.name;
        document.getElementById('edit-project-description').value = project.description || '';
        document.getElementById('edit-project-start-date').value = project.start_date || '';
        document.getElementById('edit-project-end-date').value = project.end_date || '';
        document.getElementById('edit-project-budget').value = project.budget || '';
        
        const modal = new bootstrap.Modal(document.getElementById('editProjectModal'));
        modal.show();
        
    } catch (error) {
        alert('Failed to load project: ' + error.message);
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
        location.reload();
        
    } catch (error) {
        alert('Failed to update project: ' + error.message);
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
    
    // Set the full details link
    document.getElementById('view-full-details-btn').href = `project-details.php?project_id=${projectId}`;
    
    modal.show();
    
    try {
        const response = await fetch(`api/project.php?project_id=${projectId}`);
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
                const progressClass = task.progress_percent === 100 ? 'bg-primary' : 
                                     task.progress_percent > 0 ? 'bg-dark' : 'bg-secondary';
                tasksHtml += `<tr>`;
                tasksHtml += `<td><strong>${escapeHtml(task.name)}</strong><br><small class="text-muted">${escapeHtml(task.description || 'No description')}</small></td>`;
                tasksHtml += `<td>
                    <div class="progress" style="height: 20px; width: 100px;">
                        <div class="progress-bar ${progressClass}" style="width: ${task.progress_percent || 0}%">
                            ${task.progress_percent || 0}%
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
        
        contentDiv.innerHTML = `
            <div class="mb-4">
                <h3>${escapeHtml(project.name)}</h3>
                <p class="text-muted">${escapeHtml(project.description || 'No description')}</p>
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
                            <strong>₱${parseFloat(project.budget || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong>
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

// Refresh project analytics
async function refreshProjectAnalytics() {
    try {
        const response = await fetch('api/projects.php');
        const data = await response.json();
        
        if (data.error) {
            throw new Error(data.error);
        }
        
        const projects = data.projects || [];
        const today = new Date();
        
        let total = projects.length;
        let active = 0;
        let upcoming = 0;
        let overdue = 0;
        
        projects.forEach(project => {
            const startDate = project.start_date ? new Date(project.start_date) : null;
            const endDate = project.end_date ? new Date(project.end_date) : null;
            
            if (startDate && endDate) {
                if (startDate <= today && endDate >= today) {
                    active++;
                } else if (startDate > today) {
                    upcoming++;
                } else if (endDate < today && project.overall_progress < 100) {
                    overdue++;
                }
            }
        });
        
        document.getElementById('total-projects').textContent = total;
        document.getElementById('projects-active').textContent = active;
        document.getElementById('projects-upcoming').textContent = upcoming;
        document.getElementById('projects-overdue').textContent = overdue;
        
    } catch (error) {
        console.error('Error loading analytics:', error);
    }
}
</script>

</body>
</html>
