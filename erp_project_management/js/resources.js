// resources.js - Resource management functionality

let currentResourceId = null;

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
                        <div class="progress-bar bg-success" style="width: ${resource.availability}%"></div>
                    </div>
                </div>
            </td>
            <td>${formatCurrency(resource.cost_per_unit)}</td>
            <td>
                <button class="btn btn-sm btn-outline-primary" onclick="viewResource(${resource.resource_id})">
                    View
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

// Refresh resource list
function refreshResourceList() {
    loadResources();
}

// Load resource details from API (existing function - keeping for compatibility)
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

// Display resource details in the UI (existing function - keeping for compatibility)
function displayResourceDetails(resource, allocations) {
    // Hide loading, show content
    document.getElementById('loading').classList.add('d-none');
    document.getElementById('resource-details').classList.remove('d-none');
    document.getElementById('error-message').classList.add('d-none');

    // Update resource information
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

// Display allocations table (existing function - keeping for compatibility)
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
                <button class="btn btn-sm btn-danger text-dark" onclick="removeAllocation(${allocation.task_id})">
                    Remove
                </button>
            </td>
        </tr>
    `).join('');
}

// Load available tasks for allocation (existing function - keeping for compatibility)
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

// Update resource information (existing function - keeping for compatibility)
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

// Allocate resource to task (existing function - keeping for compatibility)
async function allocateToTask() {
    const taskId = document.getElementById('taskSelect').value;
    const allocationPercent = document.getElementById('allocationPercent').value;

    if (!taskId || !allocationPercent) {
        showError('Please fill in all fields');
        return;
    }

    const formData = new FormData();
    formData.append('resource_id', currentResourceId);
    formData.append('task_id', taskId);
    formData.append('allocation_percent', allocationPercent);

    try {
        const response = await fetch('api/resource.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.error) {
            throw new Error(data.error);
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

// Remove allocation (existing function - keeping for compatibility)
async function removeAllocation(taskId) {
    if (!confirm('Are you sure you want to remove this allocation?')) {
        return;
    }

    const formData = new FormData();
    formData.append('resource_id', currentResourceId);
    formData.append('task_id', taskId);

    try {
        const response = await fetch('api/resource.php', {
            method: 'DELETE',
            body: formData
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

// Refresh allocations (existing function - keeping for compatibility)
function refreshAllocations() {
    loadResourceDetails();
}

// Show success message (existing function - keeping for compatibility)
function showSuccess(message) {
    // You could implement a toast notification here
    alert('Success: ' + message);
}

// Show error message (existing function - keeping for compatibility)
function showError(message) {
    document.getElementById('loading').classList.add('d-none');
    document.getElementById('resource-details').classList.add('d-none');
    document.getElementById('error-message').classList.remove('d-none');
    document.getElementById('error-text').textContent = message;
}

// Legacy function for backward compatibility (can be removed if not used elsewhere)
function allocateResource(taskId, resourceId) {
    // This function is deprecated - use the new modal-based allocation instead
    console.warn('allocateResource function is deprecated. Use the modal interface instead.');
    const allocationPercent = prompt('Enter allocation percent (0-100):', '100');
    if (allocationPercent === null) return;

    const formData = new FormData();
    formData.append('resource_id', resourceId);
    formData.append('task_id', taskId);
    formData.append('allocation_percent', allocationPercent);

    fetch('api/resource.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            alert('Error: ' + data.error);
        } else {
            alert(data.message);
            if (currentResourceId) {
                loadResourceDetails();
            }
        }
    })
    .catch(err => alert('Error allocating resource: ' + err));
}
