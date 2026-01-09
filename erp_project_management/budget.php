<?php
$activePage = 'budget';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>iServe Budgeting & Costs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="css/navbar.css" rel="stylesheet">
    <link href="css/budget_page/budget.css" rel="stylesheet">
    <style>
        .progress { background-color: #e9ecef; border-radius: 10px; }
        .card { border: none; transition: transform 0.2s; }
        .table thead th { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: none; }
        .floating-action-btn {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        .floating-action-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.25);
        }
        .floating-action-btn:active {
            transform: scale(0.95);
        }
        .modal { position: fixed !important; }
        .modal-bottom-right { position: fixed; bottom: 2rem; right: 2rem; top: auto !important; left: auto !important; }
        .modal-bottom-right .modal-dialog { margin: 0; }
    </style>
</head>
<body style="background-color: #f0f4f8;">
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <main class="container my-4">
        <section class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h1 class="fw-bold text-primary mb-1">Budgeting & Costs</h1>
                <p class="text-muted small mb-0">Financial oversight and real-time expense tracking.</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary shadow-sm btn-sm px-3" id="refresh-budget-btn" onclick="window.location.reload();">
                    <i class="fas fa-sync-alt me-2"></i>Refresh Data
                </button>
            </div>
        </section>

        <section class="row g-3 mb-4" id="budget-summary-cards">
            <div class="col-md-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body border-start border-primary border-4 rounded-1">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-uppercase text-muted small fw-bold">Total Budgeted</span>
                            <i class="fas fa-wallet text-primary"></i>
                        </div>
                        <h3 class="fw-bold mb-1" id="total-budgeted">₱0.00</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body border-start border-danger border-4 rounded-1">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-uppercase text-muted small fw-bold">Total Spent</span>
                            <i class="fas fa-receipt text-danger"></i>
                        </div>
                        <h3 class="fw-bold mb-1" id="total-spent">₱0.00</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body border-start border-success border-4 rounded-1">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-uppercase text-muted small fw-bold">Remaining</span>
                            <i class="fas fa-chart-line text-success"></i>
                        </div>
                        <h3 class="fw-bold mb-1" id="total-variance">₱0.00</h3>
                        <p class="small text-muted mb-0">Available for allocation</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body border-start border-warning border-4 rounded-1">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-uppercase text-muted small fw-bold">Budget Alerts</span>
                            <i class="fas fa-triangle-exclamation text-warning"></i>
                        </div>
                        <h3 class="fw-bold mb-1" id="budget-alerts-count">0</h3>
                        <p class="small text-muted mb-0">Risk thresholds met</p>
                    </div>
                </div>
            </div>
        </section>

        <div class="row g-4 mb-4 align-items-stretch">
            <div class="col-lg-8 d-flex">
                <section class="card border-0 shadow-sm w-100">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold">Project Financial Health</h6>
                        <span class="badge bg-light text-dark fw-normal border">Budget vs Actual</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light text-muted">
                                <tr>
                                    <th class="ps-3">Project</th>
                                    <th>Budget</th>
                                    <th>Actual</th>
                                    <th class="text-center">Burn Rate</th>
                                    <th class="text-end pe-3">Status</th>
                                </tr>
                            </thead>
                            <tbody id="project-financials-body">
                                </tbody>
                        </table>
                    </div>
                </section>
            </div>
            <div class="col-lg-4 d-flex">
                <section class="card border-0 shadow-sm w-100">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold text-danger"><i class="fas fa-triangle-exclamation me-2"></i>Risk Monitoring</h6>
                    </div>
                    <div id="budget-alerts-list" class="list-group list-group-flush small" style="max-height: 400px; overflow-y: auto;">
                        <div class="p-4 text-center text-muted">Loading alerts...</div>
                    </div>
                    <div class="card-footer bg-white py-2 d-flex justify-content-between align-items-center">
                        <small class="text-muted" id="budget-alerts-footer-count">0 items</small>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-secondary" id="budget-alerts-prev" disabled>
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <button class="btn btn-outline-secondary" id="budget-alerts-next" disabled>
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-12">
                <section class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold">Transaction History</h6>
                        <span class="badge rounded-pill bg-light text-muted border fw-normal px-3">Latest Logs</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="bg-light text-muted">
                                <tr>
                                    <th class="ps-3 py-3">Date</th>
                                    <th>Project</th>
                                    <th>Category</th>
                                    <th>Amount</th>
									<th>Notes</th>
                                </tr>
                            </thead>
                            <tbody id="expenses-log-body">
                                </tbody>
                        </table>
                    </div>
                    <div class="card-footer bg-white py-2 d-flex justify-content-between align-items-center">
                        <small class="text-muted" id="transactions-footer-count">0 transactions</small>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-secondary" id="transactions-prev" disabled>
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <button class="btn btn-outline-secondary" id="transactions-next" disabled>
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </main>

    <!-- Floating Action Button -->
    <button type="button" class="floating-action-btn" data-bs-toggle="modal" data-bs-target="#recordExpenseModal" title="Record Expense">
        <i class="fas fa-plus"></i>
    </button>

    <!-- Record Expense Modal -->
    <div class="modal fade" id="recordExpenseModal" tabindex="-1" aria-labelledby="recordExpenseLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="recordExpenseLabel"><i class="fas fa-pen-to-square me-2"></i>Record Expense</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="expense-form" class="row g-3">
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-muted">Project Allocation</label>
                            <select class="form-select form-select-sm" id="expense-project" required>
                                <option value="">Select Project...</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-muted">Cost Category</label>
                            <select class="form-select form-select-sm" id="expense-category" required>
                                <option value="labor">Labor / Workforce</option>
                                <option value="equipment">Equipment & Tools</option>
                                <option value="materials">Materials & Supplies</option>
                                <option value="travel">Travel & Logistics</option>
                                <option value="other">Other Overhead</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold text-muted">Amount</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white">₱</span>
                                <input type="number" class="form-control" id="expense-amount" placeholder="0.00" step="0.01" required>
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold text-muted">Date</label>
                            <input type="date" class="form-control form-control-sm" id="expense-date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-muted">Notes</label>
                            <textarea class="form-control form-control-sm" id="expense-notes" rows="2" placeholder="Invoice details..."></textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-check-circle me-1"></i>Save Transaction
                            </button>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/budget_page/budgeting.js"></script>
    <script src="js/main.js"></script>
    <script>
        // Set BASE_URL for API calls
        window.BASE_URL = '<?php echo $BASE_URL ?? ""; ?>';
        
        // Initialize notification system
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof updateHeaderNotificationBadge === 'function') {
                updateHeaderNotificationBadge();
            }
            if (typeof updateNavbarNotificationCount === 'function') {
                updateNavbarNotificationCount();
            }
        });
    </script>
</body>
</html>