<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Resourcify - Project Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />

    <!-- Your UI theme -->
    <link rel="stylesheet" href="css/resources.css" />
    <link rel="stylesheet" href="css/navbar.css" />
</head>

<body class="bg-primary-bg">

<?php include 'includes/navbar.php'; ?>

<div class="container mt-4">

    <!-- PROJECT LISTING -->
    <div id="projects-listing" class="card secondary-card p-4">

        <!-- HEADER -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Project Management</h1>
            <div class="btn-group" role="group">

                <input type="radio" class="btn-check" name="projectView" id="projectListView" autocomplete="off" checked>
                <label class="btn btn-outline-dark" for="projectListView" onclick="setProjectView('list')">
                    Project List
                </label>

                <input type="radio" class="btn-check" name="projectView" id="projectAnalyticsView" autocomplete="off">
                <label class="btn btn-outline-dark" for="projectAnalyticsView" onclick="setProjectView('analytics')">
                    Analytics
                </label>

            </div>
        </div>

        <!-- SEARCH -->
        <div class="mb-3">
            <input type="text" class="form-control bg-dark text-dark border-secondary" 
                   id="projectSearchInput" placeholder="Search projects...">
        </div>

        <!-- TABLE -->
        <div class="table-responsive">
            <table class="table table-dark">
                <thead>
                    <tr>
                        <th>Project</th>
                        <th>Description</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Budget</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>
                    <?php
                    include 'includes/db.php';
                    $result = $conn->query("SELECT * FROM projects");

                    if ($result->num_rows === 0) {
                        echo "<tr><td colspan='6' class='text-center text-muted'>No projects found</td></tr>";
                    }

                    while ($row = $result->fetch_assoc()) {
                        echo "<tr>";
                        
                        // UPDATED LINK (BLUE LIKE NORMAL LINK)
                        echo "<td>
                                <a class='text-decoration-none text-primary' 
                                   href='project-details.php?project_id=" . $row['project_id'] . "'>
                                   " . htmlspecialchars($row['name']) . "
                                </a>
                              </td>";

                        echo "<td>" . htmlspecialchars($row['description']) . "</td>";
                        echo "<td>" . $row['start_date'] . "</td>";
                        echo "<td>" . $row['end_date'] . "</td>";
                        echo "<td>₱" . number_format($row['budget'], 2) . "</td>";
                        echo "<td>
                                <a href='edit-project.php?id=" . $row['project_id'] . "' 
                                   class='btn btn-sm accent-btn text-dark'>
                                   Edit
                                </a>
                              </td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ANALYTICS VIEW (HIDDEN INITIALLY) -->
    <div id="projects-analytics" class="card secondary-card p-4 d-none">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Project Analytics</h1>
            <button class="btn btn-outline-dark" onclick="refreshProjectAnalytics()">
                Refresh
            </button>
        </div>

        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card bg-primary text-white p-3 text-center">
                    <h3 id="total-projects">0</h3>
                    <p>Total Projects</p>
                </div>
            </div>

            <div class="col-md-3 mb-3">
                <div class="card bg-success text-white p-3 text-center">
                    <h3 id="projects-active">0</h3>
                    <p>Active Projects</p>
                </div>
            </div>

            <div class="col-md-3 mb-3">
                <div class="card bg-warning p-3 text-center">
                    <h3 id="projects-upcoming">0</h3>
                    <p>Upcoming Projects</p>
                </div>
            </div>

            <div class="col-md-3 mb-3">
                <div class="card bg-danger text-white p-3 text-center">
                    <h3 id="projects-overdue">0</h3>
                    <p>Overdue Projects</p>
                </div>
            </div>
        </div>

        <!-- CHART AREA -->
        <div class="card bg-dark p-3">
            <h5 class="text-dark">Project Timeline Overview</h5>
            <canvas id="projectsTimelineChart"></canvas>
        </div>
    </div>

</div>

<?php include 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// View switch
function setProjectView(mode) {
    document.getElementById("projects-listing").classList.toggle("d-none", mode !== 'list');
    document.getElementById("projects-analytics").classList.toggle("d-none", mode !== 'analytics');
}

// Placeholder
function refreshProjectAnalytics() {
    console.log("refresh analytics...");
}
</script>

</body>
</html>
