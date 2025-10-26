
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
</head>
<body class="bg-primary-bg">

<?php include 'includes/navbar.php'; ?>

<div class="container mt-4">
    <!-- Resource Listing (shown when no resource_id provided) -->
    <div id="resource-listing" class="card secondary-card p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Resource Management</h1>
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
                        <th>Availability</th>
                        <th>Cost per Unit</th>
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
                                        <div class="progress-bar bg-success" id="availability-progress" style="width: 0%"></div>
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
                    <div class="mb-3">
                        <label for="allocationPercent" class="form-label text-dark">Allocation Percentage</label>
                        <input type="number" class="form-control bg-dark text-dark border-secondary" id="allocationPercent" min="0" max="100" step="0.01" required>
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

