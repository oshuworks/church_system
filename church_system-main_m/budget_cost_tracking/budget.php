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
    <link href="css/resources.css" rel="stylesheet">
    <style>
        .progress { background-color: #e9ecef; border-radius: 10px; }
        .card { border: none; transition: transform 0.2s; }
        .table thead th { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: none; }
    </style>
</head>
<body class="bg-light">
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <main class="container my-4">
        <section class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h1 class="fw-bold text-primary mb-1">Budgeting & Costs</h1>
                <p class="text-muted small mb-0">Financial oversight and real-time expense tracking.</p>
            </div>
			<button class="btn btn-outline-primary shadow-sm btn-sm px-3" id="refresh-budget-btn" onclick="window.location.reload();">
				<i class="fas fa-sync-alt me-2"></i>Refresh Data
			</button>
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
                        <p class="small text-muted mb-0">Across <span id="active-project-count">0</span> projects</p>
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
                        <div class="progress mt-2" style="height: 6px;">
                            <div id="budget-burn-progress" class="progress-bar bg-danger" role="progressbar" style="width: 0%"></div>
                        </div>
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

        <div class="row g-4">
            <div class="col-lg-4">
                <section class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-primary text-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="fas fa-pen-to-square me-2"></i>Record Expense</h6>
                    </div>
                    <div class="card-body bg-light-subtle">
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
                            <div class="col-12 pt-2">
                                <button type="submit" class="btn btn-primary w-100 shadow-sm">
                                    <i class="fas fa-check-circle me-1"></i>Save Transaction
                                </button>
                            </div>
                        </form>
                    </div>
                </section>

                <section class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold text-danger"><i class="fas fa-triangle-exclamation me-2"></i>Risk Monitoring</h6>
                    </div>
                    <div id="budget-alerts-list" class="list-group list-group-flush small">
                        <div class="p-4 text-center text-muted">Loading alerts...</div>
                    </div>
                </section>
            </div>

            <div class="col-lg-8">
                <section class="card border-0 shadow-sm mb-4">
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
                                </tr>
                            </thead>
                            <tbody id="expenses-log-body">
                                </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </main>

    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1080">
        <div id="toast-container"></div>
    </div>

    <?php include __DIR__ . '/includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/budgeting.js"></script>
</body>
</html>