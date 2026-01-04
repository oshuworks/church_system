<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Project Planning & Scheduling - iServe</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />

    <!-- Shared UI theme -->
    <link rel="stylesheet" href="/church_system-main/project_management/css/resources.css" />
    <link rel="stylesheet" href="/church_system-main/project_management/css/navbar.css" />
    
    <!-- Module-specific CSS -->
    <link rel="stylesheet" href="/church_system-main/project_management/modules/project_planning/css/style.css" />
</head>

<body class="bg-primary-bg">

<?php include '../../includes/navbar.php'; ?>

<div class="container mt-4">

    <!-- PROJECT LISTING -->
    <div id="projects-listing" class="card secondary-card p-4">

        <!-- HEADER -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Project Planning & Scheduling - iServe</h1>
            <div class="d-flex gap-2">
                <button class="btn btn-primary" onclick="showAddProjectModal()">
                    <i class="fas fa-plus"></i> Create Project
                </button>
                <div class="btn-group" role="group">
                    <input type="radio" class="btn-check" name="projectView" id="projectListView" autocomplete="off" checked>
                    <label class="btn btn-outline-dark" for="projectListView" onclick="setProjectView('list')">
                        Project List
                    </label>
                    <input type="radio" class="btn-check" name="projectView" id="projectAnalyticsView" autocomplete="off">
                    <label class="btn btn-outline-dark" for="projectAnalyticsView" onclick="setProjectView('analytics')">
                        Analytics
                    </label>
                </div>
            </div>
        </div>

        <!-- SEARCH -->
        <div class="mb-3">
            <input type="text" class="form-control bg-dark text-dark border-secondary" 
                   id="projectSearchInput" placeholder="Search projects..." 
                   onkeyup="filterProjects()">
        </div>

        <!-- TABLE -->
        <div class="table-responsive">
            <table class="table table-dark">
                <thead>
                    <tr>
                        <th>Project</th>
                        <th>Description</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Budget</th>
                        <th>Progress</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody id="projects-table-body">
                    <?php
                    include '../../includes/db.php';
                    $result = $conn->query("
                        SELECT p.*, 
                               COUNT(DISTINCT t.task_id) as task_count,
                               COUNT(DISTINCT CASE WHEN t.progress_percentage = 100 THEN t.task_id END) as completed_tasks
                        FROM projects p
                        LEFT JOIN tasks t ON p.project_id = t.project_id
                        GROUP BY p.project_id
                        ORDER BY p.created_at DESC
                    ");

                    if ($result->num_rows === 0) {
                        echo "<tr><td colspan='7' class='text-center text-muted'>No projects found. Click 'Create Project' to get started.</td></tr>";
                    }

                    while ($row = $result->fetch_assoc()) {
                        $overall_progress = 0;
                        if ($row['task_count'] > 0) {
                            $overall_progress = round(($row['completed_tasks'] / $row['task_count']) * 100);
                        }
                        $progress_class = $overall_progress === 100 ? 'bg-primary' : ($overall_progress > 0 ? 'bg-dark' : 'bg-secondary');
                        
                        echo "<tr data-project-id='" . $row['project_id'] . "'>";
                        
                        echo "<td>
                                <a class='text-decoration-none text-primary fw-bold' 
                                   href='project-details.php?project_id=" . $row['project_id'] . "'>
                                   " . htmlspecialchars($row['name']) . "
                                </a>
                              </td>";

                        echo "<td>" . htmlspecialchars(substr($row['description'] ?? 'No description', 0, 50)) . (strlen($row['description'] ?? '') > 50 ? '...' : '') . "</td>";
                        echo "<td>" . ($row['start_date'] ?: '-') . "</td>";
                        echo "<td>" . ($row['end_date'] ?: '-') . "</td>";
                        echo "<td>₱" . number_format($row['budget'] ?? 0, 2) . "</td>";
                        echo "<td>
                                <div class='progress' style='height: 20px; width: 100px;'>
                                    <div class='progress-bar " . $progress_class . "' style='width: " . $overall_progress . "%'>
                                        " . $overall_progress . "%
                                    </div>
                                </div>
                              </td>";
                        echo "<td>
                                <a href='project-details.php?project_id=" . $row['project_id'] . "' 
                                   class='btn btn-sm btn-primary me-1'>
                                   <i class='fas fa-eye'></i>
                                </a>
                                <button onclick='showEditProjectModal(" . $row['project_id'] . ")' 
                                        class='btn btn-sm btn-outline-primary'>
                                   <i class='fas fa-edit'></i>
                                </button>
                              </td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ANALYTICS VIEW (HIDDEN INITIALLY) -->
    <div id="projects-analytics" class="card secondary-card p-4 d-none">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Project Analytics</h1>
            <button class="btn btn-outline-dark" onclick="refreshProjectAnalytics()">
                Refresh
            </button>
        </div>

        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card bg-primary text-white p-3 text-center">
                    <h3 id="total-projects">0</h3>
                    <p>Total Projects</p>
                </div>
            </div>

            <div class="col-md-3 mb-3">
                <div class="card bg-primary text-white p-3 text-center">
                    <h3 id="projects-active">0</h3>
                    <p>Active Projects</p>
                </div>
            </div>

            <div class="col-md-3 mb-3">
                <div class="card bg-dark text-white p-3 text-center">
                    <h3 id="projects-upcoming">0</h3>
                    <p>Upcoming Projects</p>
                </div>
            </div>

            <div class="col-md-3 mb-3">
                <div class="card bg-dark text-white p-3 text-center">
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
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveProject()">Create Project</button>
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

<script>
// View switch
function setProjectView(mode) {
    document.getElementById("projects-listing").classList.toggle("d-none", mode !== 'list');
    document.getElementById("projects-analytics").classList.toggle("d-none", mode !== 'analytics');
    
    if (mode === 'analytics') {
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
    const table = document.getElementById('projects-table-body');
    const rows = table.getElementsByTagName('tr');
    
    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        const text = row.textContent || row.innerText;
        row.style.display = text.toLowerCase().indexOf(filter) > -1 ? '' : 'none';
    }
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

