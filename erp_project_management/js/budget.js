// budget.js - Budget management functionality

let currentBudgetId = null;

// Format currency
function formatCurrency(amount) {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP'
    }).format(amount || 0);
}

// Initialize page when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    loadProjects();
    setBudgetViewMode('list');
});

// Load projects for dropdown
async function loadProjects() {
    try {
        const response = await fetch('api/projects.php');
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();

        if (data.error) {
            throw new Error(data.error);
        }

        // API returns array directly, not object with projects property
        const projects = Array.isArray(data) ? data : [];

        const projectSelect = document.getElementById('projectSelect');
        const budgetProjectSelect = document.getElementById('budgetProjectSelect');

        const options = '<option value="">Select a project...</option>' +
            projects.map(project =>
                `<option value="${project.project_id}">${project.name}</option>`
            ).join('');

        projectSelect.innerHTML = options;
        budgetProjectSelect.innerHTML = options;

    } catch (error) {
        console.error('Error loading projects:', error);
    }
}

// Set budget view mode
function setBudgetViewMode(mode) {
    const projectSelect = document.getElementById('projectSelect');
    const searchInput = document.getElementById('budgetSearchInput');

    if (mode === 'allocations') {
        projectSelect.style.display = 'none';
        searchInput.style.display = 'block';
        loadAllBudgetAllocations();
    } else {
        projectSelect.style.display = 'block';
        searchInput.style.display = 'none';
        loadBudgets();
    }
}

// Load budgets for selected project
async function loadBudgets() {
    const projectId = document.getElementById('projectSelect').value;

    if (!projectId) {
        document.getElementById('budgets-table').innerHTML =
            '<tr><td colspan="7" class="text-center text-muted">Select a project to view budgets</td></tr>';
        return;
    }

    try {
        const response = await fetch(`api/budget_allocation.php?project_id=${projectId}`);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();

        if (data.error) {
            throw new Error(data.error);
        }

        displayBudgets(data.budgets || []);

    } catch (error) {
        console.error('Error loading budgets:', error);
        document.getElementById('budgets-table').innerHTML =
            '<tr><td colspan="7" class="text-center text-error">Error loading budgets: ' + error.message + '</td></tr>';
    }
}

// Load all budget allocations
async function loadAllBudgetAllocations() {
    try {
        const response = await fetch('api/budget_allocation.php');
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();

        if (data.error) {
            throw new Error(data.error);
        }

        displayBudgetAllocations(data.budgets || []);

    } catch (error) {
        console.error('Error loading budget allocations:', error);
        document.getElementById('budgets-table').innerHTML =
            '<tr><td colspan="7" class="text-center text-error">Error loading budget allocations: ' + error.message + '</td></tr>';
    }
}

// Display budgets in the table
function displayBudgets(budgets) {
    const tbody = document.getElementById('budgets-table');

    if (!budgets || budgets.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No budgets found for this project</td></tr>';
        return;
    }

    tbody.innerHTML = budgets.map(budget => {
        const remaining = budget.estimated_cost - (budget.allocated_amount || 0);
        const status = getBudgetStatus(remaining, budget.estimated_cost);
        const statusBadge = getBudgetStatusBadge(status);

        return `
            <tr>
                <td>${budget.project_name}</td>
                <td>${budget.description}</td>
                <td>${formatCurrency(budget.estimated_cost)}</td>
                <td>${formatCurrency(budget.actual_cost || 0)}</td>
                <td>${formatCurrency(remaining)}</td>
                <td>${statusBadge}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary me-2" onclick="viewBudgetDetails(${budget.budget_id})">
                        View Details
                    </button>
                    <button class="btn btn-sm btn-outline-warning me-2" onclick="editBudget(${budget.budget_id})">
                        Edit
                    </button>
                    <button class="btn btn-sm btn-outline-success" onclick="showAllocationModal(${budget.budget_id}, '${budget.description}')">
                        Allocate
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

// Display budget allocations
function displayBudgetAllocations(allocations) {
    const tbody = document.getElementById('budgets-table');

    if (!allocations || allocations.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No budget allocations found</td></tr>';
        return;
    }

    tbody.innerHTML = allocations.map(allocation => {
        const remaining = allocation.estimated_cost - (allocation.allocated_amount || 0);
        const status = getBudgetStatus(remaining, allocation.estimated_cost);
        const statusBadge = getBudgetStatusBadge(status);

        return `
            <tr>
                <td>${allocation.project_name}</td>
                <td>${allocation.description}</td>
                <td>${formatCurrency(allocation.estimated_cost)}</td>
                <td>${formatCurrency(allocation.actual_cost || 0)}</td>
                <td>${formatCurrency(remaining)}</td>
                <td>${statusBadge}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary me-2" onclick="viewBudgetDetails(${allocation.budget_id})">
                        View Details
                    </button>
                    <button class="btn btn-sm btn-outline-warning me-2" onclick="editBudget(${allocation.budget_id})">
                        Edit
                    </button>
                    <button class="btn btn-sm btn-outline-success" onclick="showAllocationModal(${allocation.budget_id}, '${allocation.description}')">
                        Allocate
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

// Get budget status
function getBudgetStatus(remaining, total) {
    const percentage = (remaining / total) * 100;
    if (percentage > 20) return 'good';
    if (percentage > 0) return 'warning';
    return 'danger';
}

// Get budget status badge
function getBudgetStatusBadge(status) {
    switch (status) {
        case 'good':
            return '<span class="badge bg-success">On Track</span>';
        case 'warning':
            return '<span class="badge bg-warning text-dark">Low Budget</span>';
        case 'danger':
            return '<span class="badge bg-danger">Over Budget</span>';
        default:
            return '<span class="badge bg-secondary">Unknown</span>';
    }
}

// Show budget allocation modal
function showAllocationModal(budgetId, budgetDescription) {
    currentBudgetId = budgetId;
    document.getElementById('budgetSelect').value = budgetId;
    document.getElementById('allocateBudgetModal').querySelector('.modal-title').textContent =
        `Allocate Budget: ${budgetDescription}`;

    loadTasksForAllocation();
    loadResourcesForAllocation();

    const modal = new bootstrap.Modal(document.getElementById('allocateBudgetModal'));
    modal.show();
}

// Load tasks for budget allocation
async function loadTasksForAllocation() {
    try {
        const response = await fetch('api/tasks.php');
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();
        const taskSelect = document.getElementById('allocationTaskSelect');

        if (data.error) {
            taskSelect.innerHTML = '<option value="">Error loading tasks</option>';
            return;
        }

        taskSelect.innerHTML = '<option value="">Select a task...</option>' +
            data.tasks.map(task =>
                `<option value="${task.task_id}">${task.name} (${task.project_name || 'No Project'})</option>`
            ).join('');

    } catch (error) {
        console.error('Error loading tasks:', error);
        document.getElementById('allocationTaskSelect').innerHTML = '<option value="">Error loading tasks</option>';
    }
}

// Load resources for budget allocation
async function loadResourcesForAllocation() {
    try {
        const response = await fetch('api/resources.php');
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();
        const resourceSelect = document.getElementById('allocationResourceSelect');

        if (data.error) {
            resourceSelect.innerHTML = '<option value="">Error loading resources</option>';
            return;
        }

        resourceSelect.innerHTML = '<option value="">No specific resource</option>' +
            data.resources.map(resource =>
                `<option value="${resource.resource_id}">${resource.name} (${resource.resource_type})</option>`
            ).join('');

    } catch (error) {
        console.error('Error loading resources:', error);
        document.getElementById('allocationResourceSelect').innerHTML = '<option value="">Error loading resources</option>';
    }
}

// Allocate budget to task
async function allocateBudgetToTask() {
    const budgetId = document.getElementById('budgetSelect').value;
    const taskId = document.getElementById('allocationTaskSelect').value;
    const resourceId = document.getElementById('allocationResourceSelect').value || null;
    const allocatedAmount = document.getElementById('allocatedAmount').value;
    const spentAmount = document.getElementById('spentAmount').value || 0;

    if (!budgetId || !taskId || !allocatedAmount) {
        alert('Please fill in all required fields');
        return;
    }

    const formData = new FormData();
    formData.append('budget_id', budgetId);
    formData.append('task_id', taskId);
    if (resourceId) formData.append('resource_id', resourceId);
    formData.append('allocated_amount', allocatedAmount);
    formData.append('spent_amount', spentAmount);

    try {
        const response = await fetch('api/budget_allocation.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.error) {
            throw new Error(data.error);
        }

        // Close modal and reload data
        bootstrap.Modal.getInstance(document.getElementById('allocateBudgetModal')).hide();
        loadBudgets();

        alert('Budget allocated successfully');

    } catch (error) {
        console.error('Error allocating budget:', error);
        alert('Failed to allocate budget: ' + error.message);
    }
}

// Create new budget
async function createBudget() {
    const projectId = document.getElementById('budgetProjectSelect').value;
    const description = document.getElementById('budgetDescription').value;
    const estimatedCost = document.getElementById('estimatedCost').value;
    const actualCost = document.getElementById('actualCost').value || 0;

    if (!projectId || !description || !estimatedCost) {
        alert('Please fill in all required fields');
        return;
    }

    const formData = new FormData();
    formData.append('project_id', projectId);
    formData.append('description', description);
    formData.append('estimated_cost', estimatedCost);
    formData.append('actual_cost', actualCost);

    try {
        const response = await fetch('api/budget_allocation.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.error) {
            throw new Error(data.error);
        }

        // Close modal and reload data
        bootstrap.Modal.getInstance(document.getElementById('createBudgetModal')).hide();
        loadBudgets();

        alert('Budget created successfully');

    } catch (error) {
        console.error('Error creating budget:', error);
        alert('Failed to create budget: ' + error.message);
    }
}

// View budget details
function viewBudgetDetails(budgetId) {
    // This could open a detailed view modal or navigate to a budget details page
    alert(`Budget details view for budget ID: ${budgetId} (to be implemented)`);
}

// Edit budget
function editBudget(budgetId) {
    // This could open an edit modal or navigate to an edit page
    alert(`Budget edit for budget ID: ${budgetId} (to be implemented)`);
}

// Show create budget modal
function showCreateBudgetModal() {
    const modal = new bootstrap.Modal(document.getElementById('createBudgetModal'));
    modal.show();
}
