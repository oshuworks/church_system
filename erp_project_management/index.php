<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Resourcify - Project Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />

    <!-- Your UI theme -->
    <link rel="stylesheet" href="css/resources.css" />
    <link rel="stylesheet" href="css/navbar.css" />
</head>

<body class="bg-primary-bg">

<?php include 'includes/navbar.php'; ?>

<div class="container mt-4">

    <!-- PROJECT LISTING -->
    <div id="projects-listing" class="card secondary-card p-4">

        <!-- HEADER -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Project Management</h1>
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

        <!-- SEARCH -->
        <div class="mb-3">
            <input type="text" class="form-control bg-dark text-dark border-secondary" 
                   id="projectSearchInput" placeholder="Search projects...">
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
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>
                    <?php
                    include 'includes/db.php';
                    $result = $conn->query("SELECT * FROM projects");

                    if ($result->num_rows === 0) {
                        echo "<tr><td colspan='6' class='text-center text-muted'>No projects found</td></tr>";
                    }

                    while ($row = $result->fetch_assoc()) {
                        echo "<tr>";
                        
                        // UPDATED LINK (BLUE LIKE NORMAL LINK)
                        echo "<td>
                                <a class='text-decoration-none text-primary' 
                                   href='project-details.php?project_id=" . $row['project_id'] . "'>
                                   " . htmlspecialchars($row['name']) . "
                                </a>
                              </td>";

                        echo "<td>" . htmlspecialchars($row['description']) . "</td>";
                        echo "<td>" . $row['start_date'] . "</td>";
                        echo "<td>" . $row['end_date'] . "</td>";
                        echo "<td>₱" . number_format($row['budget'], 2) . "</td>";
                        echo "<td>
                                <a href='edit-project.php?id=" . $row['project_id'] . "' 
                                   class='btn btn-sm accent-btn text-dark'>
                                   Edit
                                </a>
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
            <button class="btn btn-outline-dark" onclick="refreshProjectAnalytics()" id="refreshAnalyticsBtn">
                <span id="refreshAnalyticsText">Refresh</span>
                <span id="refreshAnalyticsSpinner" class="spinner-border spinner-border-sm d-none ms-2" role="status"></span>
            </button>
        </div>
        
        <div id="analytics-error" class="alert alert-danger d-none" role="alert"></div>

        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card bg-primary p-3 text-center" style="color: #ffffff;">
                    <h3 id="total-projects">0</h3>
                    <p>Total Projects</p>
                </div>
            </div>

            <div class="col-md-3 mb-3">
                <div class="card bg-success p-3 text-center" style="color: #ffffff;">
                    <h3 id="projects-active">0</h3>
                    <p>Active Projects</p>
                </div>
            </div>

            <div class="col-md-3 mb-3">
                <div class="card bg-warning p-3 text-center" style="color: #000000;">
                    <h3 id="projects-upcoming">0</h3>
                    <p>Upcoming Projects</p>
                </div>
            </div>

            <div class="col-md-3 mb-3">
                <div class="card bg-danger p-3 text-center" style="color: #ffffff;">
                    <h3 id="projects-overdue">0</h3>
                    <p>Overdue Projects</p>
                </div>
            </div>
        </div>

        <!-- CHART AREA -->
        <div class="card bg-dark p-3 mb-4">
            <h5 class="text-white mb-3">Project Timeline Overview</h5>
            <canvas id="projectsTimelineChart" style="max-height: 400px;"></canvas>
        </div>

        <!-- Budget Overview -->
        <div class="card bg-dark p-3">
            <h5 class="text-white mb-3">Budget Overview</h5>
            <canvas id="projectsBudgetChart" style="max-height: 300px;"></canvas>
        </div>
    </div>

</div>

<?php include 'includes/footer.php'; ?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
let timelineChart = null;
let budgetChart = null;

// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, Chart.js available:', typeof Chart !== 'undefined');
    
    // Check if analytics view is already selected (in case of page refresh)
    const analyticsView = document.getElementById('projectAnalyticsView');
    if (analyticsView && analyticsView.checked) {
        setTimeout(() => {
            refreshProjectAnalytics();
        }, 500);
    }
});

// View switch
function setProjectView(mode) {
    const listingEl = document.getElementById("projects-listing");
    const analyticsEl = document.getElementById("projects-analytics");
    
    if (!listingEl || !analyticsEl) {
        console.error('Analytics elements not found');
        return;
    }
    
    listingEl.classList.toggle("d-none", mode !== 'list');
    analyticsEl.classList.toggle("d-none", mode !== 'analytics');
    
    // Load analytics when switching to analytics view
    if (mode === 'analytics') {
        // Small delay to ensure view is visible
        setTimeout(() => {
            refreshProjectAnalytics();
        }, 100);
    }
}

// Refresh project analytics
async function refreshProjectAnalytics() {
    console.log('Loading analytics...');
    
    // Show loading state
    const refreshBtn = document.getElementById('refreshAnalyticsBtn');
    const refreshText = document.getElementById('refreshAnalyticsText');
    const refreshSpinner = document.getElementById('refreshAnalyticsSpinner');
    const errorDiv = document.getElementById('analytics-error');
    
    if (refreshBtn) {
        refreshBtn.disabled = true;
    }
    if (refreshText) {
        refreshText.textContent = 'Loading...';
    }
    if (refreshSpinner) {
        refreshSpinner.classList.remove('d-none');
    }
    if (errorDiv) {
        errorDiv.classList.add('d-none');
        errorDiv.textContent = '';
    }
    
    try {
        const response = await fetch('api/projects.php?analytics=true');
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
                console.warn('Invalid date for project:', project.name);
                return;
            }
            
            startDate.setHours(0, 0, 0, 0);
            endDate.setHours(0, 0, 0, 0);
            
            if (startDate <= today && endDate >= today) {
                active++;
            } else if (startDate > today) {
                upcoming++;
            } else if (endDate < today) {
                // Check if project is completed (progress = 100) or has incomplete tasks
                const progress = project.overall_progress || 0;
                if (progress < 100) {
                    overdue++;
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
        
        console.log('Analytics loaded successfully');
        
    } catch (error) {
        console.error('Error loading analytics:', error);
        
        // Show error message
        if (errorDiv) {
            errorDiv.textContent = 'Failed to load analytics: ' + error.message;
            errorDiv.classList.remove('d-none');
        } else {
            alert('Failed to load analytics: ' + error.message);
        }
    } finally {
        // Reset loading state
        if (refreshBtn) {
            refreshBtn.disabled = false;
        }
        if (refreshText) {
            refreshText.textContent = 'Refresh';
        }
        if (refreshSpinner) {
            refreshSpinner.classList.add('d-none');
        }
    }
}

// Create timeline chart
function createTimelineChart(projects) {
    const ctx = document.getElementById('projectsTimelineChart');
    if (!ctx) {
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
        ctx.getContext('2d').clearRect(0, 0, ctx.width, ctx.height);
        return;
    }
    
    // Prepare data for timeline
    const labels = validProjects.map(p => p.name || 'Unnamed Project');
    
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
    
    timelineChart = new Chart(ctx, {
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
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    labels: {
                        color: '#ffffff'
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: '#ffffff'
                    },
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    }
                },
                x: {
                    ticks: {
                        color: '#ffffff',
                        maxRotation: 45,
                        minRotation: 45
                    },
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    }
                }
            }
        }
    });
}

// Create budget chart
function createBudgetChart(projects) {
    const ctx = document.getElementById('projectsBudgetChart');
    if (!ctx) {
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
    const validProjects = projects.filter(p => p.budget && parseFloat(p.budget) > 0);
    
    if (validProjects.length === 0) {
        console.warn('No projects with valid budgets for budget chart');
        ctx.getContext('2d').clearRect(0, 0, ctx.width, ctx.height);
        return;
    }
    
    const labels = validProjects.map(p => p.name || 'Unnamed Project');
    const budgets = validProjects.map(p => parseFloat(p.budget) || 0);
    
    console.log('Creating budget chart with', validProjects.length, 'projects');
    
    budgetChart = new Chart(ctx, {
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
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        color: '#ffffff',
                        padding: 15
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
</script>

</body>
</html>
