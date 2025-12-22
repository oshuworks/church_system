
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Resourcify - Resource Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <!-- Bootstrap CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="css/resources.css" />
    <link rel="stylesheet" href="css/navbar.css" />
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-primary-bg">

<?php include 'includes/navbar.php'; ?>

<div class="container mt-4">
    <!-- Resource Listing (shown when no resource_id provided) -->
    <div id="resource-listing" class="card secondary-card p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Resource Management</h1>
            <div class="btn-group" role="group">
                <input type="radio" class="btn-check" name="viewMode" id="listView" autocomplete="off" checked>
                <label class="btn btn-outline-dark" for="listView" onclick="setViewMode('list')">Resource List</label>

                <input type="radio" class="btn-check" name="viewMode" id="utilizationView" autocomplete="off">
                <label class="btn btn-outline-dark" for="utilizationView" onclick="setViewMode('utilization')">Utilization Report</label>

                <input type="radio" class="btn-check" name="viewMode" id="analyticsView" autocomplete="off">
                <label class="btn btn-outline-dark" for="analyticsView" onclick="setViewMode('analytics')">Analytics Dashboard</label>
            </div>
        </div>

        <div class="mb-3">
            <input type="text" class="form-control bg-dark text-dark border-secondary" id="searchInput" placeholder="Search resources...">
        </div>

        <div class="table-responsive">
            <table class="table table-dark">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th id="allocationHeader">Availability</th>
                        <th id="statusHeader" class="d-none">Cost per Unit</th>
                        <th id="costHeader" class="d-none">Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="resources-table">
                    <tr>
                        <td colspan="5" class="text-center text-muted">Loading resources...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Analytics Dashboard (shown when analytics view is selected) -->
    <div id="analytics-dashboard" class="card secondary-card p-4 d-none">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Resource Analytics Dashboard</h1>
            <button class="btn btn-outline-dark" onclick="refreshAnalytics()">
                <i class="fas fa-sync-alt me-2"></i>Refresh
            </button>
        </div>

        <!-- Summary Cards -->
        <div class="row mb-4" id="analytics-summary">
            <div class="col-md-3 mb-3">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <h3 id="total-resources">0</h3>
                        <p>Total Resources</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <h3 id="well-utilized">0</h3>
                        <p>Well Utilized (50-100%)</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-dark text-white">
                    <div class="card-body text-center">
                        <h3 id="under-utilized">0</h3>
                        <p>Under Utilized (&lt;50%)</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-dark text-white">
                    <div class="card-body text-center">
                        <h3 id="over-utilized">0</h3>
                        <p>Over Utilized (&gt;100%)</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts and Detailed Analytics -->
        <div class="row">
            <div class="col-md-8 mb-4">
                <div class="card bg-dark p-3">
                    <h5 class="text-dark">Resource Utilization Chart</h5>
                    <canvas id="utilizationChart" width="400" height="200"></canvas>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card bg-dark p-3">
                    <h5 class="text-dark">Resource Types Distribution</h5>
                    <canvas id="resourceTypesChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="card bg-dark p-3">
                    <h5 class="text-dark">Detailed Utilization Table</h5>
                    <div class="table-responsive">
                        <table class="table table-dark">
                            <thead>
                                <tr>
                                    <th>Resource</th>
                                    <th>Type</th>
                                    <th>Current Allocation</th>
                                    <th>Utilization Status</th>
                                    <th>Availability</th>
                                    <th>Cost per Unit</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="analytics-table">
                                <tr>
                                    <td colspan="7" class="text-center text-muted">Loading analytics...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Resource Details (shown when resource_id is provided) -->
    <div id="resource-details-container" class="d-none">
        <div class="card secondary-card p-4">
            <div id="loading" class="text-center">
                <div class="spinner-border text-dark" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2 text-dark">Loading resource details...</p>
            </div>

            <div id="resource-details" class="d-none">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="d-flex align-items-center">
                        <button class="btn btn-outline-dark me-3" onclick="showResourceList()">
                            <i class="fas fa-arrow-left me-2"></i>Back to Resources
                        </button>
                        <h1 id="resource-name">Resource Details</h1>
                    </div>
                    <button class="btn accent-btn text-dark" data-bs-toggle="modal" data-bs-target="#editResourceModal">
                        Edit Resource
                    </button>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card bg-dark p-3">
                            <h5 class="text-dark">Resource Information</h5>
                            <div class="row">
                                <div class="col-sm-4"><strong class="text-black">Type:</strong></div>
                                <div class="col-sm-8" id="resource-type">-</div>
                            </div>
                            <div class="row">
                                <div class="col-sm-4"><strong class="text-black">Availability:</strong></div>
                                <div class="col-sm-8">
                                    <span id="resource-availability">-</span>
                                    <div class="progress mt-2">
                                        <div class="progress-bar bg-primary" id="availability-progress" style="width: 0%"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-4"><strong class="text-black">Cost per Unit:</strong></div>
                                <div class="col-sm-8" id="resource-cost">-</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-dark p-3">
                            <h5 class="text-darl">Quick Actions</h5>
                            <button class="btn accent-btn w-100 mb-2 text-dark" data-bs-toggle="modal" data-bs-target="#allocateModal">
                                Allocate to Task
                            </button>
                            <button class="btn btn-warning w-100 mb-2 text-dark" onclick="autoBalanceResource(currentResourceId)">
                                Auto-Balance Allocation
                            </button>
                            <button class="btn btn-info w-100 text-dark" onclick="loadBalancingRecommendations()">
                                View Balancing Recommendations
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card bg-dark p-3">
                    <h5 class="text-dark">Current Allocations</h5>
                    <div class="table-responsive">
                        <table class="table table-dark">
                            <thead>
                                <tr>
                                    <th>Project</th>
                                    <th>Task</th>
                                    <th>Allocation %</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="allocations-table">
                                <tr>
                                    <td colspan="6" class="text-center text-muted">Loading allocations...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card bg-dark p-3 mt-3" id="balancing-recommendations" style="display: none;">
                    <h5 class="text-dark">Resource Balancing Recommendations</h5>
                    <div class="row">
                        <!-- Balancing recommendations will be populated here -->
                    </div>
                </div>
            </div>

            <div id="error-message" class="alert alert-danger d-none" role="alert">
                <strong>Error:</strong> <span id="error-text">Failed to load resource details.</span>
            </div>
        </div>
    </div>
</div>

<!-- Edit Resource Modal -->
<div class="modal fade" id="editResourceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-dark">Edit Resource</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editResourceForm">
                    <div class="mb-3">
                        <label for="editName" class="form-label text-dark">Resource Name</label>
                        <input type="text" class="form-control bg-dark text-dark border-secondary" id="editName" required>
                    </div>
                    <div class="mb-3">
                        <label for="editType" class="form-label text-dark">Resource Type</label>
                        <select class="form-control bg-dark text-dark border-secondary" id="editType" required>
                            <option value="Human">Human</option>
                            <option value="Equipment">Equipment</option>
                            <option value="Financial">Financial</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="editAvailability" class="form-label text-dark">Availability (%)</label>
                        <input type="number" class="form-control bg-dark text-dark border-secondary" id="editAvailability" min="0" max="100" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label for="editCost" class="form-label text-dark">Cost per Unit</label>
                        <input type="number" class="form-control bg-dark text-dark border-secondary" id="editCost" min="0" step="0.01">
                    </div>
                </form>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn accent-btn text-dark" onclick="updateResource()">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<!-- Allocate to Task Modal -->
<div class="modal fade" id="allocateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-dark">Allocate Resource to Task</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="allocateForm">
                    <div class="mb-3">
                        <label for="taskSelect" class="form-label text-dark">Select Task</label>
                        <select class="form-control bg-dark text-dark border-secondary" id="taskSelect" required>
                            <option value="">Loading tasks...</option>
                        </select>
                    </div>

                    <!-- Current Allocation Summary -->
                    <div class="mb-3" id="currentAllocationSummary" style="display: none;">
                        <label class="form-label text-dark">Current Resource Allocation</label>
                        <div class="card allocation-summary border">
                            <div class="card-body p-2">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="allocation-text text-muted">Currently allocated:</span>
                                    <span class="allocation-value" id="currentTotalText">0.0%</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="allocation-text text-muted">Available capacity:</span>
                                    <span class="allocation-value text-success" id="availableCapacityText">100.0%</span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar" id="allocationProgressBar" style="width: 0%"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="allocationPercent" class="form-label text-dark">New Allocation Percentage</label>
                        <input type="number" class="form-control bg-dark text-dark border-secondary" id="allocationPercent"
                               min="0.01" max="100" step="0.01" required
                               placeholder="Enter percentage (1-100)"
                               oninput="validateAllocationPercent(this)">
                        <div class="form-text text-muted">Enter a percentage between 0.01% and 100%</div>
                        <div class="invalid-feedback">Allocation percentage cannot exceed 100% or current total allocation.</div>
                        <div class="valid-feedback">Allocation percentage is valid.</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn accent-btn text-dark" onclick="allocateToTask()">Allocate</button>
            </div>
        </div>
    </div>
</div>

<script src="js/resources.js"></script>
<?php 
include 'includes/footer.php';
?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

