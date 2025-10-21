<?php
include 'includes/navbar.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Resource Management</h1>
        <button class="btn btn-primary" onclick="loadResources()">
            <i class="fas fa-sync-alt"></i> Refresh
        </button>
    </div>
    
    <div class="alert alert-info" id="status-message" style="display: none;"></div>
    
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0">Resource List</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="resources-table">
                        <thead class="thead-light">
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Availability</th>
                                <th>Allocated</th>
                                <th>Cost/Unit</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Resources will be loaded here by JS -->
                            <tr id="loading-row">
                                <td colspan="6" class="text-center">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="sr-only">Loading...</span>
                                    </div>
                                    <p class="mt-2 mb-0">Loading resources...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Assign Resource to Task</h5>
                </div>
                <div class="card-body">
                    <form id="assign-form">
                        <div class="form-group">
                            <label for="taskId">Task ID</label>
                            <input type="number" class="form-control" id="taskId" required 
                                   placeholder="Enter task ID" min="1">
                        </div>
                        <div class="form-group">
                            <label for="resourceId">Resource</label>
                            <select class="form-control" id="resourceId" required 
                                   onchange="updateMaxAllocation(this.value)">
                                <option value="">Loading resources...</option>
                            </select>
                            <small class="form-text text-muted" id="availability-text"></small>
                        </div>
                        <div class="form-group">
                            <label for="allocationPercent">Allocation (%)</label>
                            <input type="number" class="form-control" id="allocationPercent" 
                                   min="1" max="100" required placeholder="1-100">
                            <div class="progress mt-2" style="height: 10px;">
                                <div id="allocation-bar" class="progress-bar bg-success" 
                                     role="progressbar" style="width: 0%"></div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-link"></i> Assign Resource
                        </button>
                    </form>
                    <div id="assign-message" class="alert mt-3 mb-0" style="display: none;"></div>
                </div>
            </div>
            
            <!-- Resource Summary Card -->
            <div class="card mt-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Resource Summary</h5>
                </div>
                <div class="card-body">
                    <div id="resource-summary">
                        <p class="text-center text-muted">Loading summary...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include 'includes/footer.php';
?>

<script src="js/main.js"></script>
<script>
// Global variable to store resources data
let resourcesData = [];

function showMessage(message, type = 'info') {
    const messageDiv = document.getElementById('status-message');
    messageDiv.textContent = message;
    messageDiv.className = `alert alert-${type}`;
    messageDiv.style.display = 'block';
    
    if (type !== 'danger') {
        setTimeout(() => {
            messageDiv.style.display = 'none';
        }, 5000);
    }
}

function updateResourceSummary() {
    const summaryDiv = document.getElementById('resource-summary');
    if (resourcesData.length === 0) {
        summaryDiv.innerHTML = '<p class="text-muted">No resources available</p>';
        return;
    }

    const totalResources = resourcesData.length;
    const availableResources = resourcesData.filter(r => r.availability > 0).length;
    const totalAllocated = resourcesData.reduce((sum, r) => sum + parseFloat(r.allocated_percent || 0), 0);
    const avgAllocation = resourcesData.length > 0 ? (totalAllocated / resourcesData.length).toFixed(1) : 0;

    summaryDiv.innerHTML = `
        <div class="mb-3">
            <h6>Total Resources: <span class="float-right">${totalResources}</span></h6>
            <div class="progress">
                <div class="progress-bar bg-success" role="progressbar" 
                     style="width: ${(availableResources / totalResources) * 100}%" 
                     aria-valuenow="${availableResources}" aria-valuemin="0" 
                     aria-valuemax="${totalResources}">
                    ${availableResources} Available
                </div>
            </div>
        </div>
        <div class="mb-3">
            <h6>Average Allocation: <span class="float-right">${avgAllocation}%</span></h6>
            <div class="progress">
                <div class="progress-bar bg-info" role="progressbar" 
                     style="width: ${avgAllocation}%" 
                     aria-valuenow="${avgAllocation}" aria-valuemin="0" 
                     aria-valuemax="100">
                </div>
            </div>
        </div>
        <div class="resource-types">
            <h6>By Resource Type:</h6>
            ${getResourceTypeSummary()}
        </div>
    `;
}

function getResourceTypeSummary() {
    const types = {};
    resourcesData.forEach(resource => {
        if (!types[resource.resource_type]) {
            types[resource.resource_type] = { count: 0, allocated: 0 };
        }
        types[resource.resource_type].count++;
        types[resource.resource_type].allocated += parseFloat(resource.allocated_percent || 0);
    });

    return Object.entries(types).map(([type, data]) => {
        const avgAlloc = (data.allocated / data.count).toFixed(1);
        return `
            <div class="mb-2">
                <small class="d-block">${type} (${data.count})</small>
                <div class="progress" style="height: 8px;">
                    <div class="progress-bar" 
                         style="width: ${avgAlloc}%; background-color: ${getRandomColor(type)}">
                    </div>
                </div>
                <small class="text-muted">${avgAlloc}% allocated on average</small>
            </div>
        `;
    }).join('');
}

function getRandomColor(seed) {
    // Simple hash function to generate consistent colors
    let hash = 0;
    for (let i = 0; i < seed.length; i++) {
        hash = seed.charCodeAt(i) + ((hash << 5) - hash);
    }
    const hue = Math.abs(hash) % 360;
    return `hsl(${hue}, 70%, 60%)`;
}

function updateMaxAllocation(resourceId) {
    if (!resourceId) return;
    
    const resource = resourcesData.find(r => r.resource_id == resourceId);
    if (!resource) return;
    
    const allocationInput = document.getElementById('allocationPercent');
    const allocationBar = document.getElementById('allocation-bar');
    const availabilityText = document.getElementById('availability-text');
    
    const maxAllocation = Math.min(100, resource.availability);
    
    allocationInput.max = maxAllocation;
    allocationInput.placeholder = `1-${maxAllocation}%`;
    
    // Update the allocation bar
    const allocatedPercent = parseFloat(resource.allocated_percent || 0);
    const availablePercent = 100 - allocatedPercent;
    
    allocationBar.style.width = `${allocatedPercent}%`;
    allocationBar.textContent = `${allocatedPercent}%`;
    allocationBar.className = `progress-bar ${allocatedPercent > 80 ? 'bg-danger' : allocatedPercent > 50 ? 'bg-warning' : 'bg-success'}`;
    
    // Update availability text
    availabilityText.textContent = `Available: ${availablePercent}% (${resource.availability}% total)`;
    availabilityText.className = `form-text ${availablePercent < 20 ? 'text-danger' : availablePercent < 50 ? 'text-warning' : 'text-success'}`;
}

function loadResources() {
    const loadingRow = document.getElementById('loading-row');
    const tbody = document.querySelector('#resources-table tbody');
    const select = document.getElementById('resourceId');
    
    // Show loading state
    loadingRow.style.display = '';
    tbody.innerHTML = '';
    tbody.appendChild(loadingRow);
    
    fetch('api/resource.php')
        .then(res => {
            if (!res.ok) {
                throw new Error(`HTTP error! status: ${res.status}`);
            }
            return res.json();
        })
        .then(response => {
            if (response.status !== 'success') {
                throw new Error(response.message || 'Failed to load resources');
            }
            
            resourcesData = Array.isArray(response.data) ? response.data : [];
            tbody.innerHTML = ''; // Clear loading row
            
            if (resourcesData.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            <i class="fas fa-inbox fa-3x mb-2"></i>
                            <p class="mb-0">No resources found</p>
                        </td>
                    </tr>`;
                return;
            }
            
            // Populate resources table
            resourcesData.forEach(resource => {
                const allocatedPercent = parseFloat(resource.allocated_percent || 0);
                const availablePercent = resource.availability;
                
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${escapeHtml(resource.name)}</td>
                    <td>
                        <span class="badge" style="background-color: ${getRandomColor(resource.resource_type)};">
                            ${escapeHtml(resource.resource_type)}
                        </span>
                    </td>
                    <td>
                        <div class="d-flex align-items-center">
                            <div class="progress flex-grow-1" style="height: 5px;">
                                <div class="progress-bar bg-success" role="progressbar" 
                                     style="width: ${availablePercent}%" 
                                     aria-valuenow="${availablePercent}" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100">
                                </div>
                            </div>
                            <small class="ml-2">${availablePercent}%</small>
                        </div>
                    </td>
                    <td>${allocatedPercent}%</td>
                    <td>$${parseFloat(resource.cost_per_unit || 0).toFixed(2)}</td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" 
                                onclick="editResource(${resource.resource_id})">
                            <i class="fas fa-edit"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
            });
            
            // Update resource dropdown
            select.innerHTML = resourcesData.map(resource => 
                `<option value="${resource.resource_id}">
                    ${escapeHtml(resource.name)} (${escapeHtml(resource.resource_type)})
                </option>`
            ).join('');
            
            // Update resource summary
            updateResourceSummary();
            
            // Update allocation UI for the first resource if available
            if (resourcesData.length > 0) {
                updateMaxAllocation(resourcesData[0].resource_id);
            }
            
        })
        .catch(error => {
            console.error('Error loading resources:', error);
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center text-danger py-4">
                        <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                        <p class="mb-0">Failed to load resources</p>
                        <small>${escapeHtml(error.message)}</small>
                    </td>
                </tr>`;
            
            showMessage(`Error: ${error.message}`, 'danger');
        });
}

function escapeHtml(unsafe) {
    return unsafe
         .toString()
         .replace(/&/g, "&amp;")
         .replace(/</g, "&lt;")
         .replace(/>/g, "&gt;")
         .replace(/"/g, "&quot;")
         .replace(/'/g, "&#039;");
}

function editResource(resourceId) {
    // This function can be implemented to show a modal for editing a resource
    alert(`Edit resource ${resourceId} - To be implemented`);
}

document.getElementById('assign-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const taskId = document.getElementById('taskId').value.trim();
    const resourceId = document.getElementById('resourceId').value;
    const allocationPercent = document.getElementById('allocationPercent').value;
    const submitBtn = this.querySelector('button[type="submit"]');
    const messageDiv = document.getElementById('assign-message');
    
    // Validate inputs
    if (!taskId || !resourceId || !allocationPercent) {
        showMessage('Please fill in all fields', 'warning');
        return;
    }
    
    // Show loading state
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Assigning...';
    messageDiv.style.display = 'none';
    
    // Log the request payload
    const payload = {
        task_id: taskId,
        resource_id: resourceId,
        allocation_percent: allocationPercent
    };
    console.log('Sending request:', payload);

    fetch('api/allocate_resource.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(payload)
    })
    .then(async response => {
        const text = await response.text();
        console.log('Raw response:', text);
        
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('Failed to parse JSON response:', e);
            throw new Error(`Invalid server response: ${text.substring(0, 100)}...`);
        }

        if (!response.ok) {
            const error = new Error(data.message || 'Server error');
            error.response = data;
            throw error;
        }

        if (data.status !== 'success') {
            const error = new Error(data.message || 'Failed to assign resource');
            error.response = data;
            throw error;
        }

        return data;
    })
    .then(data => {
        showMessage('Resource assigned successfully!', 'success');
        // Reset form
        this.reset();
        // Refresh resources
        loadResources();
    })
    .catch(error => {
        console.error('Error assigning resource:', {
            error: error,
            message: error.message,
            stack: error.stack,
            response: error.response
        });
        showMessage(`Error: ${error.message}`, 'danger');
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-link"></i> Assign Resource';
    });
});

document.addEventListener('DOMContentLoaded', loadResources);
</script>
