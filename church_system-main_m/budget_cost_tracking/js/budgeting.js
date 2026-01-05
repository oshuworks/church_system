/**
 * iServe Budgeting & Cost Tracking Logic
 */

document.addEventListener('DOMContentLoaded', () => {
    // Initial data load
    fetchBudgetSummary();
    fetchProjectFinancials();
    loadDropdownData();
    loadRecentTransactions(); // ✅ ADDED THIS

    // Event Listeners
    document.getElementById('refresh-budget-btn')?.addEventListener('click', () => {
        fetchBudgetSummary();
        fetchProjectFinancials();
        loadRecentTransactions(); // ✅ ADDED THIS
        showToast('Updating financial data...', 'info');
    });

    document.getElementById('expense-form')?.addEventListener('submit', handleExpenseSubmit);
});

/**
 * Fetches high-level metrics for the summary cards
 */
async function fetchBudgetSummary() {
    try {
        const response = await fetch(window.BASE_URL + '/api/budget_summary.php');
        const data = await response.json();

        if (data) {
            document.getElementById('total-budgeted').textContent = formatCurrency(data.budget.total_allocated);
            document.getElementById('total-spent').textContent = formatCurrency(data.budget.total_spent);
            document.getElementById('total-variance').textContent = formatCurrency(data.budget.variance);
            document.getElementById('active-project-count').textContent = data.projects.active_projects;
            document.getElementById('budget-alerts-count').textContent = data.alerts.critical + data.alerts.high;

            const burnProgress = document.getElementById('budget-burn-progress');
            if (burnProgress) {
                const percent = Math.min(data.budget.utilization_percent, 100);
                burnProgress.style.width = percent + '%';
                burnProgress.className = `progress-bar ${percent > 90 ? 'bg-danger' : 'bg-success'}`;
            }
        }
    } catch (error) {
        console.error('Error fetching budget summary:', error);
    }
}

/**
 * Loads project lists for the dropdown
 */
async function loadDropdownData() {
    try {
        const projRes = await fetch('../project_management/api/projects.php');
        const rawText = await projRes.text();
        
        let projects;
        try {
            projects = JSON.parse(rawText);
        } catch (jsonErr) {
            console.error("PHP Error Detected! Raw response:", rawText);
            const projectSelect = document.getElementById('expense-project');
            if (projectSelect) projectSelect.innerHTML = '<option value="">Server Error</option>';
            return;
        }

        const projectList = Array.isArray(projects) ? projects : (projects.projects || []);
        const projectSelect = document.getElementById('expense-project');

        if (projectSelect) {
            if (projectList.length === 0) {
                projectSelect.innerHTML = '<option value="">No projects found</option>';
            } else {
                projectSelect.innerHTML = '<option value="">Select Project...</option>' + 
                    projectList.map(p => `<option value="${p.project_id}">${p.project_name || p.name}</option>`).join('');
            }
        }
    } catch (error) {
        console.error('Network Error:', error);
    }
}

/**
 * Handles the submission of a new expense with Duplicate Prevention
 */
async function handleExpenseSubmit(e) {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    
    // 1. UI PROTECTION: Disable button immediately to prevent double-clicks
    btn.disabled = true;
    const originalBtnText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Saving...';

    const formData = {
        project_id: document.getElementById('expense-project').value,
        category_id: document.getElementById('expense-category').value,
        amount: document.getElementById('expense-amount').value,
        description: document.getElementById('expense-notes').value,
        task_id: null 
    };

    // 2. DATA PROTECTION: Simple duplicate check against the last 5 seconds
    // This prevents the user from clicking 'Save' on the same info twice quickly.
    const submissionKey = `${formData.project_id}-${formData.amount}-${formData.category_id}`;
    if (window.lastSubmissionKey === submissionKey && (Date.now() - window.lastSubmissionTime < 5000)) {
        showToast('Duplicate entry detected. Please wait a moment.', 'warning');
        btn.disabled = false;
        btn.innerHTML = originalBtnText;
        return;
    }

    try {
        const response = await fetch(window.BASE_URL + '/api/costs.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        });

        const result = await response.json();

        if (result.success) {
            // Store successful submission to prevent immediate re-entry
            window.lastSubmissionKey = submissionKey;
            window.lastSubmissionTime = Date.now();

            showToast('Expense recorded successfully!', 'success');
            e.target.reset();
            
            fetchBudgetSummary();
            fetchProjectFinancials();
            loadRecentTransactions();
        } else {
            showToast(result.error || 'Failed to record expense', 'danger');
        }
    } catch (error) {
        showToast('Network error occurred', 'danger');
    } finally {
        // Re-enable after a short delay to ensure the reset is processed
        setTimeout(() => {
            btn.disabled = false;
            btn.innerHTML = originalBtnText;
        }, 1000);
    }
}

/**
 * NEW: Fetches and displays recent transactions in the table
 */
async function loadRecentTransactions() {
    const tableBody = document.getElementById('expenses-log-body');
    if (!tableBody) return;

    try {
        const response = await fetch(window.BASE_URL + '/api/costs.php');
        const data = await response.json();

        if (data.costs && data.costs.length > 0) {
            tableBody.innerHTML = data.costs.map(expense => `
                <tr>
                    <td class="ps-3 small">${new Date(expense.expense_date).toLocaleDateString()}</td>
                    <td><div class="fw-bold">${expense.project_name || 'N/A'}</div></td>
                    <td><span class="badge bg-light text-dark border text-uppercase">${expense.expense_type}</span></td>
                    <td class="fw-bold text-danger">₱${parseFloat(expense.amount).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                </tr>
            `).join('');
        } else {
            tableBody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No recent transactions found.</td></tr>';
        }
    } catch (error) {
        console.error('Error loading transactions:', error);
        tableBody.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-4">Error loading transactions.</td></tr>';
    }
}

/**
 * Renders the project financial health table
 */
async function fetchProjectFinancials() {
    const tableBody = document.getElementById('project-financials-body');
    if (!tableBody) return;

    try {
        // Note: Using your existing endpoint name
        const response = await fetch(window.BASE_URL + '/api/budget_expenses.php?all=true');
        const data = await response.json();

        tableBody.innerHTML = '';
        if (data.projects && data.projects.length > 0) {
            data.projects.forEach(proj => {
                const budget = parseFloat(proj.budget || 0);
                const actual = parseFloat(proj.actual || 0);
                const burnRate = budget > 0 ? (actual / budget) * 100 : 0;
                
                const statusClass = burnRate > 100 ? 'text-danger' : burnRate > 90 ? 'text-warning' : 'text-success';

                tableBody.innerHTML += `
                    <tr>
                        <td class="ps-3 fw-bold">${proj.project_name}</td>
                        <td>${formatCurrency(budget)}</td>
                        <td>${formatCurrency(actual)}</td>
                        <td class="text-center">
                            <div class="progress mb-1" style="height: 6px; width: 100px; margin: 0 auto;">
                                <div class="progress-bar ${burnRate > 90 ? 'bg-danger' : 'bg-primary'}"
                                     style="width: ${Math.min(burnRate, 100)}%"></div>
                            </div>
                            <small class="${statusClass} fw-bold" style="font-size: 0.7rem;">${burnRate.toFixed(1)}%</small>
                        </td>
                        <td class="text-end pe-3">
                            <span class="badge ${burnRate > 100 ? 'bg-danger' : 'bg-success'} font-monospace" style="font-size: 0.65rem;">
                                ${proj.status ? proj.status.toUpperCase() : 'ACTIVE'}
                            </span>
                        </td>
                    </tr>
                `;
            });
        } else {
            tableBody.innerHTML = '<tr><td colspan="5" class="text-center py-4">No financial data found.</td></tr>';
        }
    } catch (error) {
        tableBody.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-4">Error loading financials.</td></tr>';
    }
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP'
    }).format(amount);
}

function showToast(message, type = 'primary') {
    const container = document.getElementById('toast-container');
    if (!container) return;
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type} border-0 show mb-2`;
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    container.appendChild(toast);
    setTimeout(() => toast.remove(), 4000);
}

function loadExpensesTable(expenses) {
    const logBody = document.getElementById('expenses-log-body');
    if (!logBody) return;

    logBody.innerHTML = expenses.map(item => `
        <tr>
            <td class="ps-3 py-3 small">${item.date}</td>
            <td class="small fw-semibold">${item.project_name || 'General'}</td>
            <td><span class="badge bg-light text-muted border fw-normal">${item.category}</span></td>
            <td class="fw-bold">₱${parseFloat(item.amount).toLocaleString()}</td>
            <td class="text-end pe-3">
                <button class="btn btn-sm btn-outline-primary px-3" onclick="editExpense(${item.id})">
                    <i class="fas fa-pen-to-square me-1"></i> Edit
                </button>
            </td>
        </tr>
    `).join('');
}

