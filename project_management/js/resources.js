// resources.js - Resource management functionality

let currentResourceId = null;

// Chart instances to track and destroy before creating new ones
let utilizationChartInstance = null;
let resourceTypesChartInstance = null;

// Clear charts when switching views
function clearCharts() {
    if (utilizationChartInstance) {
        utilizationChartInstance.destroy();
        utilizationChartInstance = null;
    }
    if (resourceTypesChartInstance) {
        resourceTypesChartInstance.destroy();
        resourceTypesChartInstance = null;
    }
}

// Delete resource
// Show delete resource confirmation modal
function showDeleteResourceModal() {
    if (!currentResourceId) {
        showError('No resource selected');
        return;
    }
    
    const resourceName = document.getElementById('resource-name').textContent || 'this resource';
    document.getElementById('deleteConfirmTitle').textContent = 'Delete Resource';
    document.getElementById('deleteConfirmMessage').textContent = `Are you sure you want to delete "${resourceName}"? This action cannot be undone.`;
    document.getElementById('deleteConfirmBtn').onclick = confirmDeleteResource;
    
    const modal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
    modal.show();
}

// Confirm delete resource
async function confirmDeleteResource() {
    try {
        const response = await fetch('api/resource.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ resource_id: currentResourceId })
        });
        
        const data = await response.json();
        
        if (data.error) {
            throw new Error(data.error);
        }
        
        bootstrap.Modal.getInstance(document.getElementById('deleteConfirmModal')).hide();
        showSuccess('Resource deleted successfully');
        setTimeout(() => {
            window.location.href = 'resources.php';
        }, 1000);
        
    } catch (error) {
        console.error('Error deleting resource:', error);
        showError('Failed to delete resource: ' + error.message);
    }
}

// Refresh analytics
function refreshAnalytics() {
    clearCharts();
    loadAnalyticsDashboard();
}

// Get resource ID from URL parameter
function getResourceIdFromURL() {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get('resource_id');
}

// Format currency
function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'PHP'
    }).format(amount || 0);
}

// Format percentage
function formatPercentage(percent) {
    return parseFloat(percent || 0).toFixed(1) + '%';
}

// Initialize page when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    const resourceId = getResourceIdFromURL();

    if (resourceId) {
        // Show specific resource details
        showResourceDetails(resourceId);
    } else {
        // Show resource listing
        showResourceList();
    }

    // Add event listener for allocation modal
    const allocateModal = document.getElementById('allocateModal');
    if (allocateModal) {
        allocateModal.addEventListener('show.bs.modal', function() {
            displayCurrentAllocationSummary();
        });

        allocateModal.addEventListener('hide.bs.modal', function() {
            // Reset form when modal closes
            const allocationInput = document.getElementById('allocationPercent');
            const taskSelect = document.getElementById('taskSelect');
            const formGroup = allocationInput.closest('.mb-3');
            const invalidFeedback = formGroup.querySelector('.invalid-feedback');
            const validFeedback = formGroup.querySelector('.valid-feedback');

            // Clear input and validation
            allocationInput.value = '';
            allocationInput.classList.remove('is-valid', 'is-invalid');
            invalidFeedback.style.display = 'none';
            validFeedback.style.display = 'none';

            // Reset task selection
            taskSelect.selectedIndex = 0;
        });
    }
});

// Show resource listing
function showResourceList() {
    document.getElementById('resource-listing').classList.remove('d-none');
    document.getElementById('resource-details-container').classList.add('d-none');

    loadResources();

    // Set up search functionality
    document.getElementById('searchInput').addEventListener('input', function(e) {
        filterResources(e.target.value);
    });
}

// Show specific resource details
function showResourceDetails(resourceId) {
    currentResourceId = resourceId;
    document.getElementById('resource-listing').classList.add('d-none');
    document.getElementById('resource-details-container').classList.remove('d-none');

    loadResourceDetails();
}

// Load all resources for the listing
async function loadResources() {
    try {
        const response = await fetch('api/resources.php');
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();

        if (data.error) {
            throw new Error(data.error);
        }

        displayResources(data.resources || []);

    } catch (error) {
        console.error('Error loading resources:', error);
        document.getElementById('resources-table').innerHTML =
            '<tr><td colspan="5" class="text-center text-error">Error loading resources: ' + error.message + '</td></tr>';
    }
}

// Set view mode (list, utilization report, or analytics)
function setViewMode(mode) {
    // Clear charts when switching away from analytics
    if (mode !== 'analytics') {
        clearCharts();
    }

    // Hide all views first
    document.getElementById('resource-listing').classList.add('d-none');
    document.getElementById('analytics-dashboard').classList.add('d-none');
    document.getElementById('resource-details-container').classList.add('d-none');

    if (mode === 'utilization') {
        document.getElementById('resource-listing').classList.remove('d-none');
        document.getElementById('allocationHeader').textContent = 'Total Allocation';
        document.getElementById('statusHeader').classList.remove('d-none');
        document.getElementById('costHeader').classList.remove('d-none');
        document.getElementById('allocationHeader').classList.add('d-none');
        loadUtilizationReport();
    } else if (mode === 'analytics') {
        document.getElementById('analytics-dashboard').classList.remove('d-none');
        loadAnalyticsDashboard();
    } else {
        document.getElementById('resource-listing').classList.remove('d-none');
        document.getElementById('allocationHeader').textContent = 'Availability';
        document.getElementById('statusHeader').classList.add('d-none');
        document.getElementById('costHeader').classList.add('d-none');
        document.getElementById('allocationHeader').classList.remove('d-none');
        loadResources();
    }
}

// Display resources in the table
function displayResources(resources) {
    const tbody = document.getElementById('resources-table');

    if (!resources || resources.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No resources found</td></tr>';
        return;
    }

    tbody.innerHTML = resources.map(resource => `
        <tr>
            <td>
                <a href="javascript:void(0)" onclick="viewResource(${resource.resource_id})" class="text-decoration-none">
                    ${resource.name}
                </a>
            </td>
            <td>
                <span class="badge bg-secondary">${resource.resource_type}</span>
            </td>
            <td>
                <div class="d-flex align-items-center">
                    <span class="me-2">${formatPercentage(resource.availability)}</span>
                    <div class="progress" style="width: 60px; height: 6px;">
                        <div class="progress-bar bg-primary" style="width: ${resource.availability}%"></div>
                    </div>
                </div>
            </td>
            <td>${formatCurrency(resource.cost_per_unit)}</td>
            <td>
                <button class="btn btn-sm btn-outline-primary" onclick="viewResource(${resource.resource_id})">
                    <i class="fas fa-eye me-1"></i> View
                </button>
            </td>
        </tr>
    `).join('');
}

// View specific resource (navigates to details)
function viewResource(resourceId) {
    // Update URL without page reload
    const url = new URL(window.location);
    url.searchParams.set('resource_id', resourceId);
    window.history.pushState({}, '', url);

    showResourceDetails(resourceId);
}

// Filter resources based on search input
function filterResources(searchTerm) {
    const rows = document.querySelectorAll('#resources-table tr');
    const term = searchTerm.toLowerCase();

    rows.forEach(row => {
        if (row.cells.length > 1) { // Skip header and empty rows
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(term) ? '' : 'none';
        }
    });
}

// Load utilization report
async function loadUtilizationReport() {
    try {
        const response = await fetch('api/resource.php?utilization=report');
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();

        if (data.error) {
            throw new Error(data.error);
        }

        displayUtilizationReport(data.utilization_report, data.summary);

    } catch (error) {
        console.error('Error loading utilization report:', error);
        showError('Failed to load utilization report: ' + error.message);
    }
}

// Display utilization report
function displayUtilizationReport(resources, summary) {
    const tbody = document.getElementById('resources-table');

    if (!resources || resources.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No resources found</td></tr>';
        return;
    }

    tbody.innerHTML = resources.map(resource => {
        const statusBadge = getUtilizationStatusBadge(resource.utilization_status);
        return `
            <tr>
                <td>
                    <a href="javascript:void(0)" onclick="viewResource(${resource.resource_id})" class="text-decoration-none">
                        ${resource.name}
                    </a>
                </td>
                <td>
                    <span class="badge bg-secondary">${resource.resource_type}</span>
                </td>
                <td>
                    <div class="d-flex align-items-center">
                        <span class="me-2">${formatPercentage(resource.total_allocation)}</span>
                        <div class="progress" style="width: 60px; height: 6px;">
                            <div class="progress-bar ${getUtilizationProgressBarClass(resource.utilization_status)}" style="width: ${Math.min(resource.total_allocation, 100)}%"></div>
                        </div>
                    </div>
                </td>
                <td>${statusBadge}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="viewResource(${resource.resource_id})">
                        View
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

// Get utilization status badge
function getUtilizationStatusBadge(status) {
    switch (status) {
        case 'Over-utilized':
            return '<span class="badge bg-dark">Over-utilized</span>';
        case 'Well-utilized':
            return '<span class="badge bg-primary">Well-utilized</span>';
        case 'Under-utilized':
            return '<span class="badge bg-dark text-white">Under-utilized</span>';
        default:
            return '<span class="badge bg-secondary">Unknown</span>';
    }
}

// Get progress bar class based on utilization status
function getUtilizationProgressBarClass(status) {
    switch (status) {
        case 'Over-utilized':
            return 'bg-dark';
        case 'Well-utilized':
            return 'bg-primary';
        case 'Under-utilized':
            return 'bg-dark';
        default:
            return 'bg-secondary';
    }
}

// Load resource details from API
async function loadResourceDetails() {
    const resourceId = getResourceIdFromURL();
    if (!resourceId) {
        showError('No resource ID provided in URL');
        return;
    }

    currentResourceId = resourceId;

    try {
        const response = await fetch(`api/resource.php?resource_id=${resourceId}`);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();

        if (data.error) {
            throw new Error(data.error);
        }

        displayResourceDetails(data.resource, data.allocations);
        await loadAvailableTasks();

    } catch (error) {
        console.error('Error loading resource details:', error);
        showError('Failed to load resource details: ' + error.message);
    }
}

// Display resource details in the UI
function displayResourceDetails(resource, allocations) {
// Hide loading, show content
document.getElementById('loading').classList.add('d-none');
document.getElementById('resource-details').classList.remove('d-none');
document.getElementById('error-message').classList.add('d-none');
document.getElementById('resource-name').textContent = resource.name;
document.getElementById('resource-type').textContent = resource.resource_type;
document.getElementById('resource-availability').textContent = formatPercentage(resource.availability);
document.getElementById('resource-cost').textContent = formatCurrency(resource.cost_per_unit);

// Update availability progress bar
const progressBar = document.getElementById('availability-progress');
progressBar.style.width = resource.availability + '%';

    // Update form fields in edit modal
    document.getElementById('editName').value = resource.name;
    document.getElementById('editType').value = resource.resource_type;
    document.getElementById('editAvailability').value = resource.availability;
    document.getElementById('editCost').value = resource.cost_per_unit;

        // Display allocations
        displayAllocations(allocations);
}

// Display allocations table
function displayAllocations(allocations) {
    const tbody = document.getElementById('allocations-table');

    if (!allocations || allocations.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No allocations found</td></tr>';
        return;
    }

    tbody.innerHTML = allocations.map(allocation => `
        <tr>
            <td>${allocation.project_name || 'N/A'}</td>
            <td>${allocation.task_name || 'N/A'}</td>
            <td>
                <span class="badge bg-primary text-dark">${formatPercentage(allocation.allocation_percent)}</span>
            </td>
            <td>${allocation.start_date || 'N/A'}</td>
            <td>${allocation.end_date || 'N/A'}</td>
            <td>
                <button class="btn btn-sm btn-outline-dark" onclick="removeAllocation(${allocation.task_id})">
                    <i class="fas fa-times me-1"></i> Remove
                </button>
            </td>
        </tr>
    `).join('');
}

// Load available tasks for allocation
async function loadAvailableTasks() {
    try {
        const response = await fetch('api/tasks.php');
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();
        const taskSelect = document.getElementById('taskSelect');

        if (data.error) {
            taskSelect.innerHTML = '<option value="">Error loading tasks</option>';
            return;
        }

        // Get current allocations to exclude already allocated tasks
        const currentAllocationsResponse = await fetch(`api/resource.php?resource_id=${currentResourceId}`);
        const currentData = await currentAllocationsResponse.json();
        const allocatedTaskIds = currentData.allocations.map(a => a.task_id);

        const availableTasks = data.tasks.filter(task => !allocatedTaskIds.includes(task.task_id));

        if (availableTasks.length === 0) {
            taskSelect.innerHTML = '<option value="">No available tasks for allocation</option>';
            return;
        }

        taskSelect.innerHTML = '<option value="">Select a task...</option>' +
            availableTasks.map(task =>
                `<option value="${task.task_id}">${task.name} (${task.project_name || 'No Project'})</option>`
            ).join('');

    } catch (error) {
        console.error('Error loading tasks:', error);
        document.getElementById('taskSelect').innerHTML = '<option value="">Error loading tasks</option>';
    }
}

// Update resource information
async function updateResource() {
    const formData = new FormData();
    formData.append('resource_id', currentResourceId);
    formData.append('name', document.getElementById('editName').value);
    formData.append('resource_type', document.getElementById('editType').value);
    formData.append('availability', document.getElementById('editAvailability').value);
    formData.append('cost_per_unit', document.getElementById('editCost').value);

    try {
        const response = await fetch('api/resource.php', {
            method: 'PUT',
            body: formData
        });

        const data = await response.json();

        if (data.error) {
            throw new Error(data.error);
        }

        // Close modal and reload data
        bootstrap.Modal.getInstance(document.getElementById('editResourceModal')).hide();
        loadResourceDetails();

        showSuccess('Resource updated successfully');

    } catch (error) {
        console.error('Error updating resource:', error);
        showError('Failed to update resource: ' + error.message);
    }
}

// Allocate resource to task
async function allocateToTask() {
    const taskId = document.getElementById('taskSelect').value;
    const allocationPercentInput = document.getElementById('allocationPercent');
    const allocationPercent = parseFloat(allocationPercentInput.value);

    if (!taskId || !allocationPercent) {
        showError('Please fill in all fields');
        return;
    }

    // Validate allocation percentage
    if (allocationPercent <= 0) {
        showError('Allocation percentage must be greater than 0');
        allocationPercentInput.focus();
        return;
    }

    if (allocationPercent > 100) {
        showError('Allocation percentage cannot exceed 100%');
        allocationPercentInput.focus();
        return;
    }

    // Check current total allocation for this resource
    try {
        const response = await fetch(`api/resource.php?resource_id=${currentResourceId}`);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();

        if (data.error) {
            throw new Error(data.error);
        }

        const currentTotalAllocation = data.allocations.reduce((total, allocation) => total + parseFloat(allocation.allocation_percent), 0);

        if (currentTotalAllocation + allocationPercent > 100) {
            showError(`Total allocation would exceed 100%. Current total: ${currentTotalAllocation}%, requested: ${allocationPercent}%`);
            allocationPercentInput.focus();
            return;
        }

        // Proceed with allocation
        const formData = new FormData();
        formData.append('resource_id', currentResourceId);
        formData.append('task_id', taskId);
        formData.append('allocation_percent', allocationPercent);

        const allocateResponse = await fetch('api/resource.php', {
            method: 'POST',
            body: formData
        });

        const allocateData = await allocateResponse.json();

        if (allocateData.error) {
            throw new Error(allocateData.error);
        }

        // Close modal and reload data
        bootstrap.Modal.getInstance(document.getElementById('allocateModal')).hide();
        loadResourceDetails();

        showSuccess('Resource allocated successfully');

    } catch (error) {
        console.error('Error allocating resource:', error);
        showError('Failed to allocate resource: ' + error.message);
    }
}

// Remove allocation
async function removeAllocation(taskId) {
    if (!confirm('Are you sure you want to remove this allocation?')) {
        return;
    }

    // Validate that we have the required IDs
    if (!currentResourceId || !taskId) {
        console.error('Missing required IDs:', { currentResourceId, taskId });
        showError('Missing required resource or task ID');
        return;
    }

    try {
        const response = await fetch(`api/resource.php?resource_id=${currentResourceId}&task_id=${taskId}`, {
            method: 'DELETE'
        });

        const data = await response.json();

        if (data.error) {
            throw new Error(data.error);
        }

        loadResourceDetails();
        showSuccess('Allocation removed successfully');

    } catch (error) {
        console.error('Error removing allocation:', error);
        showError('Failed to remove allocation: ' + error.message);
    }
}

// Refresh allocations
function refreshAllocations() {
    loadResourceDetails();
}

// Validate allocation percentage input in real-time
function validateAllocationPercent(input) {
    const value = parseFloat(input.value);
    const formGroup = input.closest('.mb-3');
    const invalidFeedback = formGroup.querySelector('.invalid-feedback');
    const validFeedback = formGroup.querySelector('.valid-feedback');

    // Hide both feedback messages initially
    invalidFeedback.style.display = 'none';
    validFeedback.style.display = 'none';

    // Remove any existing validation classes
    input.classList.remove('is-valid', 'is-invalid');

    if (input.value === '' || input.value === '.') {
        // Reset display when input is cleared
        const formGroup = input.closest('.mb-3');
        const invalidFeedback = formGroup.querySelector('.invalid-feedback');
        const validFeedback = formGroup.querySelector('.valid-feedback');

        invalidFeedback.style.display = 'none';
        validFeedback.style.display = 'none';
        input.classList.remove('is-valid', 'is-invalid');

        // Reset allocation summary to current state
        displayCurrentAllocationSummary();
        return;
    }

    if (isNaN(value) || value < 0) {
        input.classList.add('is-invalid');
        invalidFeedback.textContent = 'Please enter a valid percentage (0-100)';
        invalidFeedback.style.display = 'block';
        return;
    }

    if (value > 100) {
        input.classList.add('is-invalid');
        invalidFeedback.textContent = 'Allocation percentage cannot exceed 100%';
        invalidFeedback.style.display = 'block';
        return;
    }

    // Check if this allocation would exceed the current total
    if (currentResourceId) {
        // Get current allocations
        fetch(`api/resource.php?resource_id=${currentResourceId}`)
            .then(response => response.json())
            .then(data => {
                const currentTotalAllocation = data.allocations.reduce((total, allocation) => total + parseFloat(allocation.allocation_percent || 0), 0);
                const newTotal = currentTotalAllocation + value;
                const remainingAllocation = 100 - newTotal;

                if (newTotal > 100) {
                    input.classList.add('is-invalid');
                    input.classList.remove('is-valid');
                    invalidFeedback.innerHTML = `
                        <strong>Allocation would exceed 100%</strong><br>
                        Current allocation: <span class="fw-bold">${currentTotalAllocation.toFixed(1)}%</span><br>
                        Your input: <span class="fw-bold">${value}%</span><br>
                        New total: <span class="fw-bold text-dark">${newTotal.toFixed(1)}%</span><br>
                        <small class="text-muted">Maximum allowed: 100%</small>
                    `;
                    invalidFeedback.style.display = 'block';
                } else {
                    input.classList.add('is-valid');
                    input.classList.remove('is-invalid');

                    let feedbackHTML = `
                        <strong>Allocation is valid</strong><br>
                        Current allocation: <span class="fw-bold">${currentTotalAllocation.toFixed(1)}%</span><br>
                        Your input: <span class="fw-bold text-primary">${value}%</span><br>
                        New total: <span class="fw-bold">${newTotal.toFixed(1)}%</span>
                    `;

                    if (remainingAllocation > 0) {
                        feedbackHTML += `<br><small class="text-muted">Remaining capacity: ${remainingAllocation.toFixed(1)}%</small>`;
                    } else {
                        feedbackHTML += `<br><small class="text-dark">⚠️ Fully allocated</small>`;
                    }

                    validFeedback.innerHTML = feedbackHTML;
                    validFeedback.style.display = 'block';

                    // Update the summary display in real-time
                    updateAllocationSummary(currentTotalAllocation, value);
                }
            })
            .catch(error => {
                console.error('Error checking current allocation:', error);
                // If we can't check, assume it's valid for now
                input.classList.add('is-valid');
                validFeedback.textContent = 'Allocation percentage is valid.';
                validFeedback.style.display = 'block';
            });
    } else {
        input.classList.add('is-valid');
        validFeedback.innerHTML = `
            <strong>Allocation is valid</strong><br>
            Your input: <span class="fw-bold text-primary">${value}%</span>
        `;
        validFeedback.style.display = 'block';
    }
}

// Update allocation summary display
function updateAllocationSummary(currentTotal, newAllocation) {
    const currentTotalText = document.getElementById('currentTotalText');
    const availableCapacityText = document.getElementById('availableCapacityText');
    const progressBar = document.getElementById('allocationProgressBar');
    const summaryDiv = document.getElementById('currentAllocationSummary');

    if (summaryDiv && currentTotalText && availableCapacityText && progressBar) {
        // Handle invalid input
        if (isNaN(newAllocation) || newAllocation < 0) {
            return;
        }

        const newTotal = currentTotal + newAllocation;
        const availableCapacity = 100 - newTotal;

        currentTotalText.textContent = `${newTotal.toFixed(1)}%`;
        availableCapacityText.textContent = `${Math.max(0, availableCapacity).toFixed(1)}%`;
        progressBar.style.width = `${Math.min(newTotal, 100)}%`;

        // Update progress bar color based on allocation level
        progressBar.className = 'progress-bar';
        if (newTotal > 90) {
            progressBar.classList.add('bg-dark');
            availableCapacityText.classList.remove('text-primary');
            availableCapacityText.classList.add('text-dark');
        } else if (newTotal > 70) {
            progressBar.classList.add('bg-dark');
            availableCapacityText.classList.remove('text-primary', 'text-dark');
            availableCapacityText.classList.add('text-dark');
        } else {
            progressBar.classList.add('bg-primary');
            availableCapacityText.classList.remove('text-dark');
            availableCapacityText.classList.add('text-primary');
        }

        if (availableCapacity < 0) {
            availableCapacityText.textContent = 'Over-allocated';
        }
    }
}

// Display current allocation summary in modal
function displayCurrentAllocationSummary() {
    const summaryDiv = document.getElementById('currentAllocationSummary');
    const currentTotalText = document.getElementById('currentTotalText');
    const availableCapacityText = document.getElementById('availableCapacityText');
    const progressBar = document.getElementById('allocationProgressBar');

    if (!currentResourceId) {
        summaryDiv.style.display = 'none';
        return;
    }

    // Get current allocations
    fetch(`api/resource.php?resource_id=${currentResourceId}`)
        .then(response => response.json())
        .then(data => {
            const currentTotalAllocation = data.allocations.reduce((total, allocation) => total + parseFloat(allocation.allocation_percent || 0), 0);
            const availableCapacity = 100 - currentTotalAllocation;

            // Update display
            currentTotalText.textContent = `${currentTotalAllocation.toFixed(1)}%`;
            availableCapacityText.textContent = `${availableCapacity.toFixed(1)}%`;
            progressBar.style.width = `${Math.min(currentTotalAllocation, 100)}%`;

            // Update progress bar color based on allocation level
            progressBar.className = 'progress-bar';
            if (currentTotalAllocation > 90) {
                progressBar.classList.add('bg-dark');
                availableCapacityText.classList.remove('text-primary');
                availableCapacityText.classList.add('text-dark');
            } else if (currentTotalAllocation > 70) {
                progressBar.classList.add('bg-dark');
                availableCapacityText.classList.remove('text-primary', 'text-dark');
                availableCapacityText.classList.add('text-dark');
            } else {
                progressBar.classList.add('bg-primary');
                availableCapacityText.classList.remove('text-dark');
                availableCapacityText.classList.add('text-primary');
            }

            if (availableCapacity < 0) {
                availableCapacityText.textContent = 'Over-allocated';
            }

            summaryDiv.style.display = 'block';
        })
        .catch(error => {
            console.error('Error getting current allocation:', error);
            summaryDiv.style.display = 'none';
        });
}

// Load resource balancing recommendations
async function loadBalancingRecommendations() {
    try {
        const response = await fetch('api/resource_balancing.php');
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();

        if (data.error) {
            throw new Error(data.error);
        }

        displayBalancingRecommendations(data.system_balancing_recommendations);

        // Show the recommendations section
        document.getElementById('balancing-recommendations').style.display = 'block';

    } catch (error) {
        console.error('Error loading balancing recommendations:', error);
        showError('Failed to load balancing recommendations: ' + error.message);
    }
}

// Display balancing recommendations
function displayBalancingRecommendations(recommendations) {
    const recommendationsDiv = document.getElementById('balancing-recommendations');
    if (!recommendationsDiv) return;

    let html = '<div class="row">';

    // Over-allocated resources
    if (recommendations.over_allocated.length > 0) {
        html += `
            <div class="col-md-4 mb-3">
                <div class="card bg-dark text-white">
                    <div class="card-header">
                        <h6>Over-allocated Resources (${recommendations.summary.over_allocated_count})</h6>
                    </div>
                    <div class="card-body">
        `;

        recommendations.over_allocated.forEach(resource => {
            html += `
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span>${resource.name}</span>
                    <span class="badge bg-light text-dark">${formatPercentage(resource.total_allocation)}</span>
                </div>
            `;
        });

        html += '</div></div></div>';
    }

    // Under-allocated resources
    if (recommendations.under_allocated.length > 0) {
        html += `
            <div class="col-md-4 mb-3">
                <div class="card bg-dark text-white">
                    <div class="card-header">
                        <h6>Under-allocated Resources (${recommendations.summary.under_allocated_count})</h6>
                    </div>
                    <div class="card-body">
        `;

        recommendations.under_allocated.forEach(resource => {
            html += `
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span>${resource.name}</span>
                    <span class="badge bg-dark">${formatPercentage(resource.total_allocation)}</span>
                </div>
            `;
        });

        html += '</div></div></div>';
    }

    // Well-allocated resources
    if (recommendations.well_allocated.length > 0) {
        html += `
            <div class="col-md-4 mb-3">
                <div class="card bg-primary text-white">
                    <div class="card-header">
                        <h6>Well-allocated Resources (${recommendations.summary.well_allocated_count})</h6>
                    </div>
                    <div class="card-body">
        `;

        recommendations.well_allocated.forEach(resource => {
            html += `
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span>${resource.name}</span>
                    <span class="badge bg-light text-dark">${formatPercentage(resource.total_allocation)}</span>
                </div>
            `;
        });

        html += '</div></div></div>';
    }

    html += '</div>';
    recommendationsDiv.innerHTML = html;
}

// Auto-balance resource
async function autoBalanceResource(resourceId) {
    if (!confirm('Are you sure you want to automatically balance this resource allocation? This will modify existing allocations.')) {
        return;
    }

    const formData = new FormData();
    formData.append('resource_id', resourceId);
    formData.append('auto_balance', true);

    try {
        const response = await fetch('api/resource_balancing.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.error) {
            throw new Error(data.error);
        }

        alert(data.message);
        loadResourceDetails(); // Reload resource details

    } catch (error) {
        console.error('Error auto-balancing resource:', error);
        alert('Failed to auto-balance resource: ' + error.message);
    }
}

// Load analytics dashboard
async function loadAnalyticsDashboard() {
    try {
        const response = await fetch('api/resource.php?utilization=report');
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();

        if (data.error) {
            throw new Error(data.error);
        }

        displayAnalyticsDashboard(data.utilization_report, data.summary);

    } catch (error) {
        console.error('Error loading analytics dashboard:', error);
        document.getElementById('analytics-table').innerHTML =
            '<tr><td colspan="7" class="text-center text-error">Error loading analytics: ' + error.message + '</td></tr>';
    }
}

// Display analytics dashboard
function displayAnalyticsDashboard(resources, summary) {
    // Update summary cards
    document.getElementById('total-resources').textContent = summary.total_resources;
    document.getElementById('well-utilized').textContent = summary.well_utilized;
    document.getElementById('under-utilized').textContent = summary.under_utilized;
    document.getElementById('over-utilized').textContent = summary.over_utilized;

    // Display detailed table
    displayAnalyticsTable(resources);

    // Create charts
    createUtilizationChart(resources);
    createResourceTypesChart(resources);
}

// Display analytics table
function displayAnalyticsTable(resources) {
    const tbody = document.getElementById('analytics-table');

    if (!resources || resources.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No resources found</td></tr>';
        return;
    }

    tbody.innerHTML = resources.map(resource => {
        const statusBadge = getAnalyticsStatusBadge(resource.utilization_status);
        return `
            <tr>
                <td>
                    <a href="javascript:void(0)" onclick="viewResource(${resource.resource_id})" class="text-decoration-none">
                        ${resource.name}
                    </a>
                </td>
                <td>
                    <span class="badge bg-secondary">${resource.resource_type}</span>
                </td>
                <td>
                    <div class="d-flex align-items-center">
                        <span class="me-2">${formatPercentage(resource.total_allocation)}</span>
                        <div class="progress" style="width: 60px; height: 6px;">
                            <div class="progress-bar ${getUtilizationProgressBarClass(resource.utilization_status)}" style="width: ${Math.min(resource.total_allocation, 100)}%"></div>
                        </div>
                    </div>
                </td>
                <td>${statusBadge}</td>
                <td>${formatPercentage(resource.availability)}</td>
                <td>${formatCurrency(resource.cost_per_unit)}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary me-1" onclick="viewResource(${resource.resource_id})">
                        <i class="fas fa-eye me-1"></i> View
                    </button>
                    <button class="btn btn-sm btn-warning" onclick="autoBalanceResource(${resource.resource_id})">
                        <i class="fas fa-balance-scale me-1"></i> Balance
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

// Get analytics status badge
function getAnalyticsStatusBadge(status) {
    switch (status) {
        case 'Over-utilized':
            return '<span class="badge bg-dark">Over-utilized</span>';
        case 'Well-utilized':
            return '<span class="badge bg-primary">Well-utilized</span>';
        case 'Under-utilized':
            return '<span class="badge bg-dark text-white">Under-utilized</span>';
        default:
            return '<span class="badge bg-secondary">Unknown</span>';
    }
}

// Create utilization chart
function createUtilizationChart(resources) {
    const canvas = document.getElementById('utilizationChart');
    if (!canvas) {
        console.error('Utilization chart canvas not found');
        return;
    }
    const ctx = canvas.getContext('2d');

    // Destroy existing chart if it exists
    if (utilizationChartInstance) {
        utilizationChartInstance.destroy();
    }

    const utilizationData = {
        'Under-utilized': resources.filter(r => r.utilization_status === 'Under-utilized').length,
        'Well-utilized': resources.filter(r => r.utilization_status === 'Well-utilized').length,
        'Over-utilized': resources.filter(r => r.utilization_status === 'Over-utilized').length
    };

    utilizationChartInstance = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: Object.keys(utilizationData),
            datasets: [{
                data: Object.values(utilizationData),
                backgroundColor: [
                    'rgba(255, 193, 7, 0.8)',
                    'rgba(40, 167, 69, 0.8)',
                    'rgba(220, 53, 69, 0.8)'
                ],
                borderColor: [
                    'rgba(255, 193, 7, 1)',
                    'rgba(40, 167, 69, 1)',
                    'rgba(220, 53, 69, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            aspectRatio: 2,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: 'white',
                        padding: 15,
                        usePointStyle: true,
                        font: {
                            size: 12
                        }
                    }
                }
            }
        }
    });
}

// Create resource types chart
function createResourceTypesChart(resources) {
    const canvas = document.getElementById('resourceTypesChart');
    if (!canvas) {
        console.error('Resource types chart canvas not found');
        return;
    }
    const ctx = canvas.getContext('2d');

    // Destroy existing chart if it exists
    if (resourceTypesChartInstance) {
        resourceTypesChartInstance.destroy();
    }

    const typeData = {};
    resources.forEach(resource => {
        typeData[resource.resource_type] = (typeData[resource.resource_type] || 0) + 1;
    });

    resourceTypesChartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: Object.keys(typeData),
            datasets: [{
                label: 'Count',
                data: Object.values(typeData),
                backgroundColor: [
                    'rgba(136, 208, 230, 0.8)',
                    'rgba(40, 167, 69, 0.8)',
                    'rgba(255, 193, 7, 0.8)'
                ],
                borderColor: [
                    'rgba(136, 208, 230, 1)',
                    'rgba(40, 167, 69, 1)',
                    'rgba(255, 193, 7, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            aspectRatio: 1.5,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: 'white',
                        precision: 0
                    },
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    }
                },
                x: {
                    ticks: {
                        color: 'white',
                        maxRotation: 45
                    },
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    }
                }
            }
        }
    });
}

// Clear charts when switching views
function clearCharts() {
    if (utilizationChartInstance) {
        utilizationChartInstance.destroy();
        utilizationChartInstance = null;
    }
    if (resourceTypesChartInstance) {
        resourceTypesChartInstance.destroy();
        resourceTypesChartInstance = null;
    }
}

// Delete resource
// Show delete resource confirmation modal
function showDeleteResourceModal() {
    if (!currentResourceId) {
        showError('No resource selected');
        return;
    }
    
    const resourceName = document.getElementById('resource-name').textContent || 'this resource';
    document.getElementById('deleteConfirmTitle').textContent = 'Delete Resource';
    document.getElementById('deleteConfirmMessage').textContent = `Are you sure you want to delete "${resourceName}"? This action cannot be undone.`;
    document.getElementById('deleteConfirmBtn').onclick = confirmDeleteResource;
    
    const modal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
    modal.show();
}

// Confirm delete resource
async function confirmDeleteResource() {
    try {
        const response = await fetch('api/resource.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ resource_id: currentResourceId })
        });
        
        const data = await response.json();
        
        if (data.error) {
            throw new Error(data.error);
        }
        
        bootstrap.Modal.getInstance(document.getElementById('deleteConfirmModal')).hide();
        showSuccess('Resource deleted successfully');
        setTimeout(() => {
            window.location.href = 'resources.php';
        }, 1000);
        
    } catch (error) {
        console.error('Error deleting resource:', error);
        showError('Failed to delete resource: ' + error.message);
    }
}
