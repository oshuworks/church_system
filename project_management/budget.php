<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Budget Management - ERP Project Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <!-- Bootstrap CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    
    <link rel="stylesheet" href="css/resources.css" />
    <link rel="stylesheet" href="css/navbar.css" />
</head>
<body class="bg-primary-bg">

<?php include 'includes/navbar.php'; ?>

<div class="container mt-4">
    <!-- Budget Management Interface -->
    <div class="card secondary-card p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-dollar-sign me-2"></i>Budget Management</h1>
            <div>
                <button class="btn accent-btn text-dark me-2" onclick="showCreateBudgetModal()">
                    <i class="fas fa-plus me-1"></i> Create Budget
                </button>
                <div class="btn-group" role="group">
                    <input type="radio" class="btn-check" name="budgetViewMode" id="budgetListView" autocomplete="off" checked>
                    <label class="btn btn-outline-dark" for="budgetListView" onclick="setBudgetViewMode('list')">
                        <i class="fas fa-list me-1"></i> Budget Overview
                    </label>

                    <input type="radio" class="btn-check" name="budgetViewMode" id="budgetAllocationView" autocomplete="off">
                    <label class="btn btn-outline-dark" for="budgetAllocationView" onclick="setBudgetViewMode('allocations')">
                        <i class="fas fa-table me-1"></i> Detailed Allocations
                    </label>
                </div>
            </div>
        </div>

        <div class="mb-3">
            <select class="form-control bg-dark text-dark border-secondary" id="projectSelect">
                <option value="">Select a project...</option>
            </select>
        </div>

        <div class="mb-3">
            <input type="text" class="form-control bg-dark text-dark border-secondary" id="budgetSearchInput" placeholder="Search budgets..." style="display: none;">
        </div>

        <div class="table-responsive">
            <table class="table table-dark">
                <thead>
                    <tr>
                        <th>Project</th>
                        <th>Description</th>
                        <th>Estimated Cost</th>
                        <th>Actual Cost</th>
                        <th>Remaining</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="budgets-table">
                    <tr>
                        <td colspan="7" class="text-center text-muted">Select a project to view budgets</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create Budget Modal -->
<div class="modal fade" id="createBudgetModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Create Budget</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="createBudgetForm">
                    <div class="mb-3">
                        <label for="budgetProjectSelect" class="form-label text-dark">Project</label>
                        <select class="form-control bg-dark text-dark border-secondary" id="budgetProjectSelect" required>
                            <option value="">Select a project...</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="budgetDescription" class="form-label text-dark">Description</label>
                        <input type="text" class="form-control bg-dark text-dark border-secondary" id="budgetDescription" required>
                    </div>
                    <div class="mb-3">
                        <label for="estimatedCost" class="form-label text-dark">Estimated Cost (₱)</label>
                        <input type="number" class="form-control bg-dark text-dark border-secondary" id="estimatedCost" min="0" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label for="actualCost" class="form-label text-dark">Actual Cost (₱)</label>
                        <input type="number" class="form-control bg-dark text-dark border-secondary" id="actualCost" min="0" step="0.01">
                    </div>
                </form>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Cancel
                </button>
                <button type="button" class="btn accent-btn text-dark" onclick="createBudget()">
                    <i class="fas fa-check me-1"></i> Create Budget
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Budget Allocation Modal -->
<div class="modal fade" id="allocateBudgetModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title"><i class="fas fa-hand-holding-usd me-2"></i>Allocate Budget to Task</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="allocateBudgetForm">
                    <div class="mb-3">
                        <label for="budgetSelect" class="form-label text-dark">Budget</label>
                        <select class="form-control bg-dark text-dark border-secondary" id="budgetSelect" required>
                            <option value="">Select a budget...</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="allocationTaskSelect" class="form-label text-dark">Task</label>
                        <select class="form-control bg-dark text-dark border-secondary" id="allocationTaskSelect" required>
                            <option value="">Select a task...</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="allocationResourceSelect" class="form-label text-dark">Resource (Optional)</label>
                        <select class="form-control bg-dark text-dark border-secondary" id="allocationResourceSelect">
                            <option value="">No specific resource</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="allocatedAmount" class="form-label text-dark">Allocated Amount (₱)</label>
                        <input type="number" class="form-control bg-dark text-dark border-secondary" id="allocatedAmount" min="0" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label for="spentAmount" class="form-label text-dark">Spent Amount (₱)</label>
                        <input type="number" class="form-control bg-dark text-dark border-secondary" id="spentAmount" min="0" step="0.01">
                    </div>
                </form>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Cancel
                </button>
                <button type="button" class="btn accent-btn text-dark" onclick="allocateBudgetToTask()">
                    <i class="fas fa-check me-1"></i> Allocate Budget
                </button>
            </div>
        </div>
    </div>
</div>

<script src="js/budget.js"></script>
<?php
include 'includes/footer.php';
?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
