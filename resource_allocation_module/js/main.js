// Resource Allocation Management JavaScript

// Global variables
let utilizationChart, statusChart, workloadChart, distributionChart;
let currentAssignments = [];
let currentAlerts = [];

// Field validation functions
function showFieldError(fieldId, message) {
    const field = document.getElementById(fieldId);
    const errorElement = document.getElementById(fieldId + '-error');
    
    if (field) {
        field.classList.add('is-invalid');
        field.classList.remove('is-valid');
    }
    
    if (errorElement) {
        errorElement.textContent = message;
        errorElement.style.display = 'block';
    }
}

function clearFieldError(fieldId) {
    const field = document.getElementById(fieldId);
    const errorElement = document.getElementById(fieldId + '-error');
    
    if (field) {
        field.classList.remove('is-invalid');
        field.classList.add('is-valid');
    }
    
    if (errorElement) {
        errorElement.textContent = '';
        errorElement.style.display = 'none';
    }
}

function validateField(fieldId) {
    const field = document.getElementById(fieldId);
    const value = field ? field.value.trim() : '';
    
    switch(fieldId) {
        case 'project-select':
            if (!value) {
                showFieldError(fieldId, 'Please select a project');
                return false;
            }
            break;
            
        case 'task-select':
            if (!value) {
                showFieldError(fieldId, 'Please select a task');
                return false;
            }
            break;
            
        case 'resource-type-select':
            if (!value) {
                showFieldError(fieldId, 'Please select a resource type');
                return false;
            }
            break;
            
        case 'resource-select':
            if (!value) {
                showFieldError(fieldId, 'Please select a resource');
                return false;
            }
            break;
            
        case 'allocated-hours':
            const hours = parseFloat(value);
            if (!value || isNaN(hours) || hours <= 0) {
                showFieldError(fieldId, 'Please enter a valid number of hours greater than 0');
                return false;
            }
            if (hours > 24) {
                showFieldError(fieldId, 'Allocated hours cannot exceed 24 hours');
                return false;
            }
            break;
            
        case 'allocation-percentage':
            const percentage = parseFloat(value);
            if (!value || isNaN(percentage) || percentage < 0 || percentage > 100) {
                showFieldError(fieldId, 'Allocation percentage must be between 0 and 100');
                return false;
            }
            break;
            
        case 'start-date':
            if (!value) {
                showFieldError(fieldId, 'Please select a start date');
                return false;
            }
            const startDate = new Date(value);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            if (startDate < today) {
                showFieldError(fieldId, 'Start date cannot be in the past');
                return false;
            }
            break;
            
        case 'end-date':
            if (!value) {
                showFieldError(fieldId, 'Please select an end date');
                return false;
            }
            const endDate = new Date(value);
            const startDateField = document.getElementById('start-date');
            if (startDateField && startDateField.value) {
                const startDate = new Date(startDateField.value);
                if (endDate < startDate) {
                    showFieldError(fieldId, 'End date must be after start date');
                    return false;
                }
            }
            break;
            
        case 'assignment-notes':
            if (value && value.length > 500) {
                showFieldError(fieldId, 'Notes cannot exceed 500 characters');
                return false;
            }
            break;
    }
    
    clearFieldError(fieldId);
    return true;
}

function validateForm() {
    const fields = [
        'project-select',
        'task-select', 
        'resource-type-select',
        'resource-select',
        'allocated-hours',
        'allocation-percentage',
        'start-date',
        'end-date',
        'assignment-notes'
    ];
    
    let isValid = true;
    fields.forEach(fieldId => {
        if (!validateField(fieldId)) {
            isValid = false;
        }
    });
    
    return isValid;
}

function setupFieldValidation() {
    const fields = [
        'project-select',
        'task-select', 
        'resource-type-select',
        'resource-select',
        'allocated-hours',
        'allocation-percentage',
        'start-date',
        'end-date',
        'assignment-notes'
    ];
    
    fields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            // Add real-time validation on blur
            field.addEventListener('blur', function() {
                validateField(fieldId);
            });
            
            // Add real-time validation on input for text fields
            if (field.tagName === 'INPUT' || field.tagName === 'TEXTAREA') {
                field.addEventListener('input', function() {
                    // Clear error immediately when user starts typing
                    if (field.classList.contains('is-invalid')) {
                        clearFieldError(fieldId);
                    }
                });
            }
            
            // Add validation on change for select fields
            if (field.tagName === 'SELECT') {
                field.addEventListener('change', function() {
                    validateField(fieldId);
                });
            }
        }
    });
}

// Initialize the application
document.addEventListener('DOMContentLoaded', function() {
    initializeCharts();
    loadDashboardData();
    loadProjects();
    loadEmployees();
    loadEquipment();
    setupEventListeners();
    
    // Auto-refresh every 30 seconds
    setInterval(loadDashboardData, 30000);
});

// Setup event listeners
function setupEventListeners() {
    // Setup field validation
    setupFieldValidation();
    
    // Project selection change
    document.getElementById('project-select').addEventListener('change', function() {
        loadTasks(this.value);
    });
    
    // Resource type change
    document.getElementById('resource-type-select').addEventListener('change', function() {
        loadResourcesByType(this.value);
    });
    
    // Filter changes
    document.getElementById('filter-project').addEventListener('change', filterAssignments);
    document.getElementById('filter-type').addEventListener('change', filterAssignments);
    document.getElementById('alert-severity-filter').addEventListener('change', filterAlerts);
    
    // Tab changes
    document.getElementById('monitoring-tab').addEventListener('shown.bs.tab', function() {
        loadMonitoringData();
    });
    
    document.getElementById('balancing-tab').addEventListener('shown.bs.tab', function() {
        loadBalancingData();
    });
    
    document.getElementById('alerts-tab').addEventListener('shown.bs.tab', function() {
        loadAlerts();
    });
}

// Initialize charts
function initializeCharts() {
    // Utilization Chart
    const utilizationCtx = document.getElementById('utilizationChart').getContext('2d');
    utilizationChart = new Chart(utilizationCtx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label: 'Employee Utilization',
                data: [],
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                tension: 0.1
            }, {
                label: 'Equipment Utilization',
                data: [],
                borderColor: 'rgb(255, 99, 132)',
                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    ticks: {
                        callback: function(value) {
                            return value + '%';
                        }
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + context.parsed.y.toFixed(1) + '%';
                        }
                    }
                }
            }
        }
    });
    
    // Status Chart
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    statusChart = new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: ['Optimal', 'Overloaded', 'Underutilized', 'Critical'],
            datasets: [{
                data: [0, 0, 0, 0],
                backgroundColor: [
                    'rgba(40, 167, 69, 0.8)',
                    'rgba(255, 193, 7, 0.8)',
                    'rgba(23, 162, 184, 0.8)',
                    'rgba(220, 53, 69, 0.8)'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
    
    // Workload Chart
    const workloadCtx = document.getElementById('workloadChart').getContext('2d');
    workloadChart = new Chart(workloadCtx, {
        type: 'bar',
        data: {
            labels: [],
            datasets: [{
                label: 'Allocated Hours',
                data: [],
                backgroundColor: 'rgba(54, 162, 235, 0.8)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }, {
                label: 'Available Hours',
                data: [],
                backgroundColor: 'rgba(75, 192, 192, 0.8)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    stacked: true
                },
                y: {
                    stacked: true,
                    beginAtZero: true
                }
            }
        }
    });
    
    // Distribution Chart
    const distributionCtx = document.getElementById('distributionChart').getContext('2d');
    distributionChart = new Chart(distributionCtx, {
        type: 'pie',
        data: {
            labels: [],
            datasets: [{
                data: [],
                backgroundColor: [
                    'rgba(255, 99, 132, 0.8)',
                    'rgba(54, 162, 235, 0.8)',
                    'rgba(255, 205, 86, 0.8)',
                    'rgba(75, 192, 192, 0.8)',
                    'rgba(153, 102, 255, 0.8)'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}

// Load dashboard data
async function loadDashboardData() {
    try {
        // Load metrics
        const [assignmentsResponse, utilizationResponse, alertsResponse] = await Promise.all([
            fetch('api/tasks.php?action=get_assignments'),
            fetch('api/tasks.php?action=get_utilization'),
            fetch('api/tasks.php?action=get_alerts&status=active')
        ]);
        
        const assignments = await assignmentsResponse.json();
        const utilization = await utilizationResponse.json();
        const alerts = await alertsResponse.json();
        
        currentAssignments = assignments;
        currentAlerts = alerts;
        
        // Update metrics
        updateMetrics(assignments, utilization, alerts);
        
        // Update charts
        updateCharts(assignments, utilization);
        
        // Update recent assignments table
        updateRecentAssignments(assignments);
        
    } catch (error) {
        console.error('Error loading dashboard data:', error);
        showNotification('Error loading dashboard data', 'error');
    }
}

// Update metrics
function updateMetrics(assignments, utilization, alerts) {
    // Calculate metrics
    const activeResources = new Set(assignments.map(a => `${a.assignment_type}_${a.resource_id}`)).size;
    const criticalAlerts = alerts.filter(a => a.severity === 'critical').length;
    const avgUtilization = utilization.length > 0 
        ? utilization.reduce((sum, u) => sum + parseFloat(u.utilization_percentage || 0), 0) / utilization.length 
        : 0;
    
    // Calculate balance score
    const optimalCount = utilization.filter(u => u.workload_level === 'optimal').length;
    const balanceScore = utilization.length > 0 ? (optimalCount / utilization.length) * 100 : 0;
    
    // Update DOM
    document.getElementById('balance-score').textContent = Math.round(balanceScore) + '%';
    document.getElementById('active-resources').textContent = activeResources;
    document.getElementById('active-alerts').textContent = criticalAlerts;
    document.getElementById('avg-utilization').textContent = Math.round(avgUtilization) + '%';
}

// Update charts
function updateCharts(assignments, utilization) {
    // Update utilization chart
    const dates = [...new Set(utilization.map(u => u.date))].sort().slice(-7);
    const employeeData = dates.map(date => {
        const dayData = utilization.filter(u => u.date === date && u.resource_type === 'employee');
        return dayData.length > 0 ? dayData.reduce((sum, u) => sum + parseFloat(u.utilization_percentage || 0), 0) / dayData.length : 0;
    });
    const equipmentData = dates.map(date => {
        const dayData = utilization.filter(u => u.date === date && u.resource_type === 'equipment');
        return dayData.length > 0 ? dayData.reduce((sum, u) => sum + parseFloat(u.utilization_percentage || 0), 0) / dayData.length : 0;
    });
    
    utilizationChart.data.labels = dates;
    utilizationChart.data.datasets[0].data = employeeData;
    utilizationChart.data.datasets[1].data = equipmentData;
    utilizationChart.update();
    
    // Update status chart
    const statusCounts = {
        optimal: utilization.filter(u => u.workload_level === 'optimal').length,
        overloaded: utilization.filter(u => u.workload_level === 'overloaded').length,
        underutilized: utilization.filter(u => u.workload_level === 'underutilized').length,
        critical: utilization.filter(u => u.workload_level === 'critical').length
    };
    
    statusChart.data.datasets[0].data = [
        statusCounts.optimal,
        statusCounts.overloaded,
        statusCounts.underutilized,
        statusCounts.critical
    ];
    statusChart.update();
}

// Update recent assignments table
function updateRecentAssignments(assignments) {
    const tbody = document.querySelector('#recent-assignments-table tbody');
    tbody.innerHTML = '';
    
    const recentAssignments = assignments
        .sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
        .slice(0, 10);
    
    recentAssignments.forEach(assignment => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${assignment.resource_name}</td>
            <td><span class="badge bg-info">${assignment.assignment_type}</span></td>
            <td>${assignment.task_name}<br><small class="text-muted">${assignment.project_name}</small></td>
            <td>${assignment.allocated_hours}h</td>
            <td>
                <div class="progress resource-progress">
                    <div class="progress-bar ${getUtilizationClass(75)}" style="width: 75%">75%</div>
                </div>
            </td>
            <td><span class="badge ${getStatusClass(assignment.status)}">${assignment.status}</span></td>
            <td>
                <button class="btn btn-sm btn-outline-primary" onclick="editAssignment(${assignment.assignment_id})">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger" onclick="deleteAssignment(${assignment.assignment_id})">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;
        tbody.appendChild(row);
    });
}

// Load projects
async function loadProjects() {
    try {
        const response = await fetch('api/tasks.php?action=get_projects');
        const projects = await response.json();
        
        const projectSelect = document.getElementById('project-select');
        const filterSelect = document.getElementById('filter-project');
        
        projects.forEach(project => {
            const option = new Option(project.project_name, project.project_id);
            projectSelect.add(option.cloneNode(true));
            filterSelect.add(option);
        });
        
    } catch (error) {
        console.error('Error loading projects:', error);
    }
}

// Load tasks for a project
async function loadTasks(projectId) {
    if (!projectId) return;
    
    try {
        const response = await fetch(`api/tasks.php?action=get_tasks&project_id=${projectId}`);
        const tasks = await response.json();
        
        const taskSelect = document.getElementById('task-select');
        taskSelect.innerHTML = '<option value="">Select Task</option>';
        
        tasks.forEach(task => {
            taskSelect.add(new Option(task.task_name, task.task_id));
        });
        
    } catch (error) {
        console.error('Error loading tasks:', error);
    }
}

// Load employees
async function loadEmployees() {
    try {
        const response = await fetch('api/tasks.php?action=get_employees');
        const employees = await response.json();
        
        window.employeesData = employees;
        
    } catch (error) {
        console.error('Error loading employees:', error);
    }
}

// Load equipment
async function loadEquipment() {
    try {
        const response = await fetch('api/tasks.php?action=get_equipment');
        const equipment = await response.json();
        
        window.equipmentData = equipment;
        
    } catch (error) {
        console.error('Error loading equipment:', error);
    }
}

// Load resources by type
function loadResourcesByType(type) {
    const resourceSelect = document.getElementById('resource-select');
    resourceSelect.innerHTML = '<option value="">Select Resource</option>';
    
    if (type === 'employee' && window.employeesData) {
        window.employeesData.forEach(employee => {
            resourceSelect.add(new Option(employee.employee_name, employee.employee_id));
        });
    } else if (type === 'equipment' && window.equipmentData) {
        window.equipmentData.forEach(eq => {
            resourceSelect.add(new Option(eq.equipment_name, eq.equipment_id));
        });
    }
}

// Open assignment modal
function openAssignModal() {
    clearAllFieldErrors();
    const modal = new bootstrap.Modal(document.getElementById('assignmentModal'));
    modal.show();
}

// Clear all field errors
function clearAllFieldErrors() {
    const fields = [
        'project-select',
        'task-select', 
        'resource-type-select',
        'resource-select',
        'allocated-hours',
        'allocation-percentage',
        'start-date',
        'end-date',
        'assignment-notes'
    ];
    
    fields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        const errorElement = document.getElementById(fieldId + '-error');
        
        if (field) {
            field.classList.remove('is-invalid', 'is-valid');
        }
        
        if (errorElement) {
            errorElement.textContent = '';
            errorElement.style.display = 'none';
        }
    });
}

// Save assignment
async function saveAssignment() {
    // Validate all fields first
    if (!validateForm()) {
        showNotification('Please correct the errors in the form', 'error');
        return;
    }
    
    const formData = {
        task_id: document.getElementById('task-select').value,
        project_id: document.getElementById('project-select').value,
        assignment_type: document.getElementById('resource-type-select').value,
        resource_id: document.getElementById('resource-select').value,
        allocated_hours: parseFloat(document.getElementById('allocated-hours').value),
        allocation_percentage: parseFloat(document.getElementById('allocation-percentage').value),
        start_date: document.getElementById('start-date').value,
        end_date: document.getElementById('end-date').value,
        notes: document.getElementById('assignment-notes').value
    };
    
    try {
        const response = await fetch('api/tasks.php?action=assign_resource', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        });
        
        // Check if response is ok before parsing JSON
        if (!response.ok) {
            const text = await response.text();
            console.error('Server response not ok:', response.status, text);
            showNotification(`Server error: ${response.status}`, 'error');
            return;
        }
        
        // Get response text first to check if it's valid JSON
        const responseText = await response.text();
        console.log('Raw response:', responseText);
        
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (jsonError) {
            console.error('JSON parsing error:', jsonError, 'Response text:', responseText);
            showNotification('Invalid JSON response from server', 'error');
            return;
        }
        
        if (result.message && result.message.includes('successfully')) {
            showNotification('Resource assigned successfully', 'success');
            bootstrap.Modal.getInstance(document.getElementById('assignmentModal')).hide();
            document.getElementById('assignment-form').reset();
            clearAllFieldErrors(); // Clear validation states after successful submission
            loadDashboardData();
        } else {
            showNotification(result.message || 'Unknown error occurred', 'error');
        }
        
    } catch (error) {
        console.error('Error saving assignment:', error);
        showNotification('Error saving assignment: ' + error.message, 'error');
    }
}

// Auto assign resources
async function autoAssignResources() {
    const taskId = prompt('Enter Task ID for auto-assignment:');
    if (!taskId) return;
    
    try {
        const response = await fetch('api/resource_balancing.php?action=auto_assign', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                task_id: taskId,
                criteria: 'availability'
            })
        });
        
        const result = await response.json();
        
        if (result.message.includes('completed')) {
            showNotification(`Auto-assignment completed: ${result.assignments.length} assignments made`, 'success');
            loadDashboardData();
        } else {
            showNotification(result.message, 'error');
        }
        
    } catch (error) {
        console.error('Error auto-assigning resources:', error);
        showNotification('Error auto-assigning resources', 'error');
    }
}

// Balance resources
async function balanceResources() {
    try {
        const response = await fetch('api/resource_balancing.php?action=rebalance_resources', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                strategy: 'utilization_balance'
            })
        });
        
        const result = await response.json();
        
        if (result.message.includes('successfully')) {
            showNotification('Resources rebalanced successfully', 'success');
            loadBalancingData();
        } else {
            showNotification(result.message, 'error');
        }
        
    } catch (error) {
        console.error('Error balancing resources:', error);
        showNotification('Error balancing resources', 'error');
    }
}

// Load monitoring data
async function loadMonitoringData() {
    try {
        const [utilizationResponse, capacityResponse] = await Promise.all([
            fetch('api/tasks.php?action=get_utilization'),
            fetch('api/resource_balancing.php?action=get_capacity_analysis')
        ]);
        
        const utilization = await utilizationResponse.json();
        const capacity = await capacityResponse.json();
        
        updateUtilizationList(utilization);
        updateWorkloadChart(capacity);
        updateCapacityAnalysis(capacity);
        
    } catch (error) {
        console.error('Error loading monitoring data:', error);
    }
}

// Update utilization list
function updateUtilizationList(utilization) {
    const container = document.getElementById('resource-utilization-list');
    container.innerHTML = '';
    
    utilization.forEach(resource => {
        const item = document.createElement('div');
        item.className = 'resource-item mb-3';
        item.innerHTML = `
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-1">${resource.resource_name}</h6>
                    <small class="text-muted">${resource.resource_type}</small>
                </div>
                <div class="text-end">
                    <div class="fw-bold">${Math.round(resource.utilization_percentage)}%</div>
                    <span class="badge ${getWorkloadClass(resource.workload_level)}">${resource.workload_level}</span>
                </div>
            </div>
            <div class="progress resource-progress mt-2">
                <div class="progress-bar ${getUtilizationClass(resource.utilization_percentage)}" 
                     style="width: ${resource.utilization_percentage}%"></div>
            </div>
        `;
        container.appendChild(item);
    });
}

// Update workload chart
function updateWorkloadChart(capacity) {
    const employees = capacity.employees.slice(0, 10);
    
    workloadChart.data.labels = employees.map(e => e.employee_name);
    workloadChart.data.datasets[0].data = employees.map(e => parseFloat(e.allocated_hours));
    workloadChart.data.datasets[1].data = employees.map(e => parseFloat(e.available_hours));
    workloadChart.update();
}

// Update capacity analysis
function updateCapacityAnalysis(capacity) {
    const container = document.getElementById('capacity-analysis');
    container.innerHTML = '';
    
    const summary = capacity.summary;
    
    const cards = [
        {
            title: 'Employee Capacity',
            used: summary.total_employee_allocated,
            total: summary.total_employee_capacity,
            utilization: summary.employee_utilization_rate
        },
        {
            title: 'Equipment Capacity',
            used: summary.total_equipment_allocated,
            total: summary.total_equipment_capacity,
            utilization: summary.equipment_utilization_rate
        }
    ];
    
    cards.forEach(card => {
        const col = document.createElement('div');
        col.className = 'col-md-6';
        col.innerHTML = `
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">${card.title}</h6>
                    <div class="d-flex justify-content-between">
                        <span>Used: ${card.used}h</span>
                        <span>Total: ${card.total}h</span>
                    </div>
                    <div class="progress resource-progress mt-2">
                        <div class="progress-bar ${getUtilizationClass(card.utilization)}" 
                             style="width: ${card.utilization}%">${card.utilization}%</div>
                    </div>
                </div>
            </div>
        `;
        container.appendChild(col);
    });
}

// Load balancing data
async function loadBalancingData() {
    try {
        const [recommendationsResponse, distributionResponse, conflictsResponse] = await Promise.all([
            fetch('api/resource_balancing.php?action=get_balance_recommendations'),
            fetch('api/resource_balancing.php?action=get_project_distribution'),
            fetch('api/resource_balancing.php?action=get_resource_conflicts')
        ]);
        
        const recommendations = await recommendationsResponse.json();
        const distribution = await distributionResponse.json();
        const conflicts = await conflictsResponse.json();
        
        updateRecommendationsList(recommendations);
        updateDistributionChart(distribution);
        updateConflictsList(conflicts);
        
    } catch (error) {
        console.error('Error loading balancing data:', error);
    }
}

// Update recommendations list
function updateRecommendationsList(recommendations) {
    const container = document.getElementById('recommendations-list');
    container.innerHTML = '';
    
    if (recommendations.length === 0) {
        container.innerHTML = '<p class="text-muted">No recommendations at this time.</p>';
        return;
    }
    
    recommendations.forEach(rec => {
        const item = document.createElement('div');
        item.className = `alert alert-${getSeverityClass(rec.severity)} alert-dismissible fade show`;
        item.innerHTML = `
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h6 class="alert-heading mb-1">${rec.message}</h6>
                    <p class="mb-1"><strong>Suggestion:</strong> ${rec.suggestion}</p>
                    <small class="text-muted">Resource: ${rec.resource_name} (${rec.resource_type})</small>
                </div>
                <span class="badge bg-${getSeverityClass(rec.severity)}">${rec.severity}</span>
            </div>
        `;
        container.appendChild(item);
    });
}

// Update distribution chart
function updateDistributionChart(distribution) {
    const projects = distribution.distribution.slice(0, 5);
    
    distributionChart.data.labels = projects.map(p => p.project_name);
    distributionChart.data.datasets[0].data = projects.map(p => p.total_assignments);
    distributionChart.update();
}

// Update conflicts list
function updateConflictsList(conflicts) {
    const container = document.getElementById('conflicts-list');
    container.innerHTML = '';
    
    if (conflicts.length === 0) {
        container.innerHTML = '<p class="text-muted">No resource conflicts detected.</p>';
        return;
    }
    
    conflicts.forEach(conflict => {
        const item = document.createElement('div');
        item.className = 'conflict-item';
        item.innerHTML = `
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h6 class="mb-1">${conflict.message}</h6>
                    <small class="text-muted">
                        ${conflict.assignment1.task} (${conflict.assignment1.project}) ↔ 
                        ${conflict.assignment2.task} (${conflict.assignment2.project})
                    </small>
                </div>
                <span class="badge bg-${getSeverityClass(conflict.severity)}">${conflict.severity}</span>
            </div>
        `;
        container.appendChild(item);
    });
}

// Load alerts
async function loadAlerts() {
    try {
        const response = await fetch('api/tasks.php?action=get_alerts&status=active');
        const alerts = await response.json();
        
        updateAlertsList(alerts);
        
    } catch (error) {
        console.error('Error loading alerts:', error);
    }
}

// Update alerts list
function updateAlertsList(alerts) {
    const container = document.getElementById('alerts-list');
    container.innerHTML = '';
    
    if (alerts.length === 0) {
        container.innerHTML = '<p class="text-muted">No active alerts.</p>';
        return;
    }
    
    alerts.forEach(alert => {
        const item = document.createElement('div');
        item.className = 'card mb-2';
        item.innerHTML = `
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="d-flex align-items-center mb-2">
                            <span class="alert-indicator alert-${getSeverityClass(alert.severity)}"></span>
                            <h6 class="mb-0">${alert.alert_type.replace('_', ' ').toUpperCase()}</h6>
                        </div>
                        <p class="mb-1">${alert.message}</p>
                        <small class="text-muted">
                            Resource: ${alert.resource_name} | 
                            Project: ${alert.project_name || 'N/A'} | 
                            ${new Date(alert.created_at).toLocaleDateString()}
                        </small>
                    </div>
                    <div>
                        <span class="badge bg-${getSeverityClass(alert.severity)} me-2">${alert.severity}</span>
                        <button class="btn btn-sm btn-outline-success" onclick="resolveAlert(${alert.alert_id})">
                            <i class="fas fa-check"></i> Resolve
                        </button>
                    </div>
                </div>
            </div>
        `;
        container.appendChild(item);
    });
}

// Resolve alert
async function resolveAlert(alertId) {
    try {
        const response = await fetch('api/tasks.php?action=resolve_alert', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ alert_id: alertId })
        });
        
        const result = await response.json();
        
        if (result.message.includes('successfully')) {
            showNotification('Alert resolved successfully', 'success');
            loadAlerts();
        } else {
            showNotification(result.message, 'error');
        }
        
    } catch (error) {
        console.error('Error resolving alert:', error);
        showNotification('Error resolving alert', 'error');
    }
}

// Helper functions
function getUtilizationClass(percentage) {
    if (percentage > 100) return 'bg-danger';
    if (percentage > 85) return 'bg-warning';
    if (percentage < 60) return 'bg-info';
    return 'bg-success';
}

function getWorkloadClass(level) {
    switch (level) {
        case 'critical': return 'bg-danger';
        case 'overloaded': return 'bg-warning';
        case 'underutilized': return 'bg-info';
        case 'optimal': return 'bg-success';
        default: return 'bg-secondary';
    }
}

function getStatusClass(status) {
    switch (status) {
        case 'assigned': return 'bg-primary';
        case 'in_progress': return 'bg-info';
        case 'completed': return 'bg-success';
        case 'cancelled': return 'bg-danger';
        default: return 'bg-secondary';
    }
}

function getSeverityClass(severity) {
    switch (severity) {
        case 'critical': return 'danger';
        case 'high': return 'warning';
        case 'medium': return 'info';
        case 'low': return 'success';
        default: return 'secondary';
    }
}

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 5000);
}

// Filter functions
function filterAssignments() {
    const projectFilter = document.getElementById('filter-project').value;
    const typeFilter = document.getElementById('filter-type').value;
    
    let filtered = currentAssignments;
    
    if (projectFilter) {
        filtered = filtered.filter(a => a.project_id == projectFilter);
    }
    
    if (typeFilter) {
        filtered = filtered.filter(a => a.assignment_type === typeFilter);
    }
    
    // Update assignments table
    updateAssignmentsTable(filtered);
}

function filterAlerts() {
    const severityFilter = document.getElementById('alert-severity-filter').value;
    
    let filtered = currentAlerts;
    
    if (severityFilter) {
        filtered = filtered.filter(a => a.severity === severityFilter);
    }
    
    updateAlertsList(filtered);
}

function updateAssignmentsTable(assignments) {
    const tbody = document.querySelector('#assignments-table tbody');
    tbody.innerHTML = '';
    
    assignments.forEach(assignment => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${assignment.resource_name}</td>
            <td><span class="badge bg-info">${assignment.assignment_type}</span></td>
            <td>${assignment.project_name}</td>
            <td>${assignment.task_name}</td>
            <td>${assignment.allocated_hours}h</td>
            <td>${assignment.actual_hours_used}h</td>
            <td>${assignment.start_date}</td>
            <td>${assignment.end_date}</td>
            <td><span class="badge ${getStatusClass(assignment.status)}">${assignment.status}</span></td>
            <td>
                <button class="btn btn-sm btn-outline-primary" onclick="editAssignment(${assignment.assignment_id})">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger" onclick="deleteAssignment(${assignment.assignment_id})">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;
        tbody.appendChild(row);
    });
}

// Placeholder functions for future implementation
function editAssignment(assignmentId) {
    showNotification('Edit assignment functionality coming soon', 'info');
}

function deleteAssignment(assignmentId) {
    if (confirm('Are you sure you want to delete this assignment?')) {
        fetch(`api/tasks.php?action=delete_assignment&assignment_id=${assignmentId}`, {
            method: 'DELETE'
        })
        .then(response => response.json())
        .then(result => {
            if (result.message.includes('successfully')) {
                showNotification('Assignment deleted successfully', 'success');
                loadDashboardData();
            } else {
                showNotification(result.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error deleting assignment:', error);
            showNotification('Error deleting assignment', 'error');
        });
    }
}

function refreshAssignments() {
    loadDashboardData();
    showNotification('Assignments refreshed', 'success');
}

function refreshAlerts() {
    loadAlerts();
    showNotification('Alerts refreshed', 'success');
}

function getRecommendations() {
    loadBalancingData();
    showNotification('Recommendations updated', 'success');
}
