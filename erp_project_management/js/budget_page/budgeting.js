/**
 * iServe Budgeting & Cost Tracking Logic
 */

const projectBudgetCache = {};

document.addEventListener('DOMContentLoaded', () => {
    fetchBudgetSummary();
    fetchProjectFinancials();
    loadDropdownData();
    loadRecentTransactions();
    loadBudgetAlerts();

    document.getElementById('refresh-budget-btn')?.addEventListener('click', () => {
        fetchBudgetSummary();
        fetchProjectFinancials();
        loadRecentTransactions();
        loadBudgetAlerts();
        showToast('Updating financial data...', 'info');
    });

    document.getElementById('expense-form')?.addEventListener('submit', handleExpenseSubmit);

    // Bind pagination controls for budget alerts
    const prevBtn = document.getElementById('budget-alerts-prev');
    const nextBtn = document.getElementById('budget-alerts-next');

    if (prevBtn) {
        prevBtn.addEventListener('click', () => {
            if (budgetAlertsPagination.page > 1) {
                budgetAlertsPagination.page--;
                renderBudgetAlertsPage();
                updateBudgetAlertsPagination();
            }
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', () => {
            const totalPages = Math.ceil(budgetAlertsPagination.allAlerts.length / budgetAlertsPagination.pageSize);
            if (budgetAlertsPagination.page < totalPages) {
                budgetAlertsPagination.page++;
                renderBudgetAlertsPage();
                updateBudgetAlertsPagination();
            }
        });
    }
    
    // Bind pagination controls for transactions
    const transPrevBtn = document.getElementById('transactions-prev');
    const transNextBtn = document.getElementById('transactions-next');

    if (transPrevBtn) {
        transPrevBtn.addEventListener('click', () => {
            if (transactionsPagination.page > 1) {
                transactionsPagination.page--;
                renderTransactionsPage();
                updateTransactionsPagination();
            }
        });
    }

    if (transNextBtn) {
        transNextBtn.addEventListener('click', () => {
            const totalPages = Math.ceil(transactionsPagination.allTransactions.length / transactionsPagination.pageSize);
            if (transactionsPagination.page < totalPages) {
                transactionsPagination.page++;
                renderTransactionsPage();
                updateTransactionsPagination();
            }
        });
    }
});

async function fetchBudgetSummary() {
    try {
        const response = await fetch(`${window.BASE_URL}/api/budget_page/budget_summary.php`);
        const data = await response.json();
        if (!data) return;

        document.getElementById('total-budgeted').textContent = formatCurrency(data.budget.total_allocated);
        document.getElementById('total-spent').textContent = formatCurrency(data.budget.total_spent);
        document.getElementById('total-variance').textContent = formatCurrency(data.budget.variance);
        document.getElementById('budget-alerts-count').textContent = data.alerts.critical + data.alerts.high;

        const burnProgress = document.getElementById('budget-burn-progress');
        if (burnProgress) {
            const percent = Math.min(data.budget.utilization_percent, 100);
            burnProgress.style.width = percent + '%';
            burnProgress.className = `progress-bar ${percent > 90 ? 'bg-danger' : 'bg-success'}`;
        }
    } catch (error) {
        console.error('Error fetching budget summary:', error);
    }
}

async function loadDropdownData() {
    try {
        const response = await fetch(`${window.BASE_URL}/api/projects.php`);
        const text = await response.text();
        console.log('Raw projects API response:', text);
        let projects;
        try { projects = JSON.parse(text); } 
        catch { projects = []; }
        console.log('Parsed projects data:', projects);

        const projectSelect = document.getElementById('expense-project');
        if (!projectSelect) return;

        const projectList = Array.isArray(projects) ? projects : (projects.projects || []);
        console.log('Project list for dropdown:', projectList);
        projectSelect.innerHTML = projectList.length
            ? '<option value="">Select Project...</option>' + projectList.map(p => `<option value="${p.project_id}">${p.project_name || p.name}</option>`).join('')
            : '<option value="">No projects found</option>';
    } catch (error) {
        console.error('Error loading projects:', error);
    }
}

async function handleExpenseSubmit(e) {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    btn.disabled = true;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Saving...';

    const formData = {
        project_id: document.getElementById('expense-project').value,
        category_id: document.getElementById('expense-category').value,
        amount: document.getElementById('expense-amount').value,
        description: document.getElementById('expense-notes').value,
        expense_date: document.getElementById('expense-date').value,
        task_id: null,
    };

    // Log the form data for debugging
    console.log('Submitting form data:', formData);

    // Validate that required fields are filled
    if (!formData.project_id || !formData.amount) {
        showToast('Please fill in all required fields', 'warning');
        btn.disabled = false;
        btn.innerHTML = originalText;
        return;
    }

    const submissionKey = `${formData.project_id}-${formData.amount}-${formData.category_id}`;
    if (window.lastSubmissionKey === submissionKey && (Date.now() - window.lastSubmissionTime < 5000)) {
        showToast('Duplicate entry detected. Please wait a moment.', 'warning');
        btn.disabled = false;
        btn.innerHTML = originalText;
        return;
    }

    try {
        console.log('Sending request to:', `${window.BASE_URL}/api/budget_page/costs.php`);
        console.log('Request payload:', JSON.stringify(formData, null, 2));
        
        const response = await fetch(`${window.BASE_URL}/api/budget_page/costs.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        });

        console.log('Response status:', response.status);
        console.log('Response headers:', response.headers);
        
        const responseText = await response.text();
        console.log('Raw response text:', responseText);
        
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (e) {
            console.error('Failed to parse JSON response:', e);
            result = { error: 'Invalid JSON response: ' + responseText };
        }
        
        console.log('API Response:', { status: response.status, ok: response.ok, result });
        
        // Check if the response was successful
        if (response.ok && result.success) {
            window.lastSubmissionKey = submissionKey;
            window.lastSubmissionTime = Date.now();
            showToast('Expense recorded successfully!', 'success');
            e.target.reset();
            // Close the modal
            const modal = document.getElementById('recordExpenseModal');
            if (modal) {
                const bsModal = bootstrap.Modal.getInstance(modal);
                if (bsModal) {
                    bsModal.hide();
                }
            }
            fetchBudgetSummary();
            fetchProjectFinancials();
            loadRecentTransactions();
            loadBudgetAlerts();
        } else {
            // Show error message from API or default message
            const errorMsg = result.error || 'Failed to record expense';
            console.log('API Error Response:', { status: response.status, result, errorMsg });
            
            showToast(errorMsg, 'danger');
            
            // Also try alert as backup for debugging
            setTimeout(() => alert(`API Error (${response.status}): ${errorMsg}`), 100);
        }
    } catch (error) {
        console.error('Error submitting expense:', error);
        showToast('Network error occurred', 'danger');
    } finally {
        setTimeout(() => {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }, 1000);
    }
}

/** Load recent transactions including Notes */
async function loadRecentTransactions() {
    const tableBody = document.getElementById('expenses-log-body');
    if (!tableBody) return;

    try {
        const response = await fetch(`${window.BASE_URL}/api/budget_page/costs.php`);
        const data = await response.json();

        if (data.costs && data.costs.length) {
            transactionsPagination.allTransactions = data.costs;
            transactionsPagination.page = 1;
            renderTransactionsPage();
            updateTransactionsPagination();
        } else {
            tableBody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No recent transactions found.</td></tr>';
            updateTransactionsPagination();
        }
    } catch (error) {
        console.error('Error loading transactions:', error);
        tableBody.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-4">Error loading transactions.</td></tr>';
    }
}

/** Render paginated transactions */
function renderTransactionsPage() {
    const tableBody = document.getElementById('expenses-log-body');
    if (!tableBody) return;
    
    // Map expense types to display labels
    const expenseTypeLabels = {
        'labor': 'Labor / Workforce',
        'equipment': 'Equipment & Tools',
        'materials': 'Materials & Supplies',
        'travel': 'Travel & Logistics',
        'other': 'Other Overhead'
    };
    
    const { page, pageSize, allTransactions } = transactionsPagination;
    const startIndex = (page - 1) * pageSize;
    const endIndex = startIndex + pageSize;
    const currentPageTransactions = allTransactions.slice(startIndex, endIndex);
    
    if (currentPageTransactions.length > 0) {
        tableBody.innerHTML = currentPageTransactions.map(expense => {
            const expenseTypeLabel = expenseTypeLabels[expense.expense_type] || expense.expense_type;
            return `
            <tr>
                <td class="ps-3 small">${new Date(expense.expense_date).toLocaleDateString()}</td>
                <td><div class="fw-bold">${expense.project_name || 'N/A'}</div></td>
                <td><span class="badge bg-light text-dark border text-uppercase">${expenseTypeLabel}</span></td>
                <td class="fw-bold text-dark">₱${parseFloat(expense.amount).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                <td class="small text-muted">${expense.expense_description || ''}</td>
            </tr>
        `}).join('');
    } else {
        tableBody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No transactions on this page.</td></tr>';
    }
}

/** Update transaction pagination controls */
function updateTransactionsPagination() {
    const { page, pageSize, allTransactions } = transactionsPagination;
    const totalPages = Math.ceil(allTransactions.length / pageSize);
    const footerCountEl = document.getElementById('transactions-footer-count');
    const prevBtn = document.getElementById('transactions-prev');
    const nextBtn = document.getElementById('transactions-next');

    if (footerCountEl) {
        footerCountEl.textContent = `${allTransactions.length} transactions`;
    }

    if (prevBtn) {
        prevBtn.disabled = page <= 1;
    }

    if (nextBtn) {
        nextBtn.disabled = page >= totalPages || totalPages === 0;
    }
}

// Rest of your functions (fetchProjectFinancials, formatCurrency, showToast) remain unchanged


/**
 * Renders the project financial health table
 */
async function fetchProjectFinancials() {
    const tableBody = document.getElementById('project-financials-body');
    if (!tableBody) return;

    try {
        const response = await fetch(window.BASE_URL + '/api/budget_page/budget_expenses.php?all=true');
        const data = await response.json();

        tableBody.innerHTML = '';
        if (data.projects && data.projects.length > 0) {
            data.projects.forEach(proj => {
                const budget = parseFloat(proj.budget || 0);
                const actual = parseFloat(proj.actual || 0);

                // 🔒 Cache current budget info
                const projectId = proj.project_id;
                projectBudgetCache[projectId] = { allocated: budget, spent: actual };
                
                const burnRate = budget > 0 ? (actual / budget) * 100 : 0;
                
                // Determine colors based on burn rate thresholds
                let progressBarColor, statusTextClass, statusBadgeClass;
                if (burnRate >= 100) {
                    progressBarColor = 'bg-danger';
                    statusTextClass = 'text-danger';
                    statusBadgeClass = 'bg-danger';
                } else if (burnRate >= 80) {
                    progressBarColor = 'bg-warning';
                    statusTextClass = 'text-warning';
                    statusBadgeClass = 'bg-warning';
                } else {
                    progressBarColor = 'bg-success';
                    statusTextClass = 'text-success';
                    statusBadgeClass = 'bg-success';
                }

                tableBody.innerHTML += `
                    <tr>
                        <td class="ps-3 fw-bold">${proj.project_name}</td>
                        <td>${formatCurrency(budget)}</td>
                        <td>${formatCurrency(actual)}</td>
                        <td class="text-center">
                            <div class="progress mb-1" style="height: 6px; width: 100px; margin: 0 auto;">
                                <div class="progress-bar ${progressBarColor}"
                                     style="width: ${Math.min(burnRate, 100)}%"></div>
                            </div>
                            <small class="${statusTextClass} fw-bold" style="font-size: 0.7rem;">${burnRate.toFixed(1)}%</small>
                        </td>
                        <td class="text-end pe-3">
                            <span class="badge ${statusBadgeClass} font-monospace" style="font-size: 0.65rem;">
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

// ... rest of your functions (formatCurrency, showToast, loadExpensesTable) remain unchanged


function formatCurrency(amount) {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP'
    }).format(amount);
}

function showToast(message, type = 'primary') {
    // Immediate fallback: use browser alert for errors
    if (type === 'danger' || type === 'error') {
        alert(message);
        return;
    }
    
    // Simple, guaranteed-to-work notification for success messages
    const colors = {
        'success': '#28a745',
        'danger': '#dc3545',
        'warning': '#ffc107',
        'info': '#17a2b8',
        'primary': '#007bff'
    };
    
    const bgColor = colors[type] || colors['info'];
    
    // Create notification element
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${bgColor};
        color: white;
        padding: 15px 20px;
        border-radius: 5px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.3);
        z-index: 99999;
        max-width: 400px;
        font-size: 14px;
        animation: slideIn 0.3s ease;
    `;
    notification.innerHTML = `
        <div style="display: flex; align-items: start; gap: 10px;">
            <div style="flex: 1;">${message}</div>
            <button onclick="this.parentElement.parentElement.remove()" style="background: none; border: none; color: white; font-size: 20px; cursor: pointer; padding: 0; line-height: 1;">&times;</button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Auto-remove after 6 seconds
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 6000);
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

// Pagination state for budget alerts
let budgetAlertsPagination = {
    page: 1,
    pageSize: 6,
    allAlerts: []
};

// Pagination state for transactions
let transactionsPagination = {
    page: 1,
    pageSize: 10,
    allTransactions: []
};

/**
 * Loads and displays budget alerts in the Risk Monitoring section
 */
async function loadBudgetAlerts() {
    const alertsList = document.getElementById('budget-alerts-list');
    if (!alertsList) return;

    try {
        const response = await fetch(`${window.BASE_URL}/api/budget_page/budget_alerts.php`);
        const data = await response.json();

        if (!data.success || !data.alerts || data.alerts.length === 0) {
            alertsList.innerHTML = `
                <div class="p-4 text-center text-muted">
                    <i class="fas fa-check-circle text-success mb-2" style="font-size: 2rem;"></i>
                    <p class="mb-0">No budget alerts at this time.</p>
                    <small>All projects are within budget limits.</small>
                </div>
            `;
            document.getElementById('budget-alerts-count').textContent = '0';
            document.getElementById('budget-alerts-prev').disabled = true;
            document.getElementById('budget-alerts-next').disabled = true;
            return;
        }

        // Store all alerts for pagination
        budgetAlertsPagination.allAlerts = data.alerts;
        budgetAlertsPagination.page = 1;

        // Render paginated list
        renderBudgetAlertsPage();
        updateBudgetAlertsPagination();

    } catch (error) {
        console.error('Error loading budget alerts:', error);
        alertsList.innerHTML = `
            <div class="p-4 text-center text-danger">
                <i class="fas fa-exclamation-triangle mb-2" style="font-size: 2rem;"></i>
                <p class="mb-0">Failed to load budget alerts.</p>
                <small>Please refresh the page.</small>
            </div>
        `;
    }
}

/**
 * Renders a paginated page of budget alerts
 */
function renderBudgetAlertsPage() {
    const alertsList = document.getElementById('budget-alerts-list');
    if (!alertsList) return;

    const { page, pageSize, allAlerts } = budgetAlertsPagination;
    const startIndex = (page - 1) * pageSize;
    const endIndex = startIndex + pageSize;
    const currentPageAlerts = allAlerts.slice(startIndex, endIndex);

    if (currentPageAlerts.length === 0) {
        alertsList.innerHTML = '<div class="p-3 text-center text-muted">No alerts to display.</div>';
        return;
    }

    let html = '';
    currentPageAlerts.forEach(alert => {
        const severity = alert.severity || 'medium';
        const burnRate = alert.burn_rate !== undefined ? alert.burn_rate : 0;
        const projectName = alert.project_name || alert.message.split('"')[1] || 'Unknown Project';
        
        // Use consistent project icon instead of severity-based icons
        const icon = '<i class="fas fa-folder text-secondary"></i>';
        
        // Determine risk level text and color
        let riskLevel = '';
        let riskColor = '';
        let riskBadgeColor = '';
        if (burnRate >= 100) {
            riskLevel = 'critical';
            riskColor = 'text-danger';
            riskBadgeColor = 'bg-danger';
        } else if (burnRate >= 80) {
            riskLevel = 'high';
            riskColor = 'text-warning';
            riskBadgeColor = 'bg-warning';
        } else {
            riskLevel = 'low';
            riskColor = 'text-success';
            riskBadgeColor = 'bg-success';
        }

        html += `
            <div class="list-group-item border-0 border-bottom py-2">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center flex-grow-1" style="min-width: 0;">
                        <div class="me-2" style="font-size: 1rem;">
                            ${icon}
                        </div>
                        <div class="flex-grow-1" style="min-width: 0;">
                            <div class="fw-bold text-truncate" style="font-size: 0.85rem;">${escapeHtml(projectName)}</div>
                            <small class="text-muted">${escapeHtml(alert.message.split('"')[0] || 'Budget alert')}</small>
                        </div>
                    </div>
                    <div class="ms-2 text-end">
                        <span class="badge ${riskBadgeColor} text-white" style="font-size: 0.7rem;">
                            ${burnRate.toFixed(1)}% - ${riskLevel.toLowerCase().replace(' risk', '')}
                        </span>
                    </div>
                </div>
            </div>
        `;
    });

    alertsList.innerHTML = html;
}

/**
 * Updates pagination controls for budget alerts
 */
function updateBudgetAlertsPagination() {
    const { page, pageSize, allAlerts } = budgetAlertsPagination;
    const totalPages = Math.ceil(allAlerts.length / pageSize);
    const footerCountEl = document.getElementById('budget-alerts-footer-count');
    const cardCountEl = document.getElementById('budget-alerts-count');
    const prevBtn = document.getElementById('budget-alerts-prev');
    const nextBtn = document.getElementById('budget-alerts-next');

    if (footerCountEl) {
        footerCountEl.textContent = `${allAlerts.length} projects`;
    }
    
    if (cardCountEl) {
        cardCountEl.textContent = allAlerts.length;
    }

    if (prevBtn) {
        prevBtn.disabled = page <= 1;
    }

    if (nextBtn) {
        nextBtn.disabled = page >= totalPages || totalPages === 0;
    }
}


/**
 * Utility function to escape HTML (if not already defined)
 */
function escapeHtml(text) {
    if (typeof text === 'undefined' || text === null) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

