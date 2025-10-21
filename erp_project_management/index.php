<?php 
include 'includes/navbar.php';
?>
<div class="container mt-4">
    <h1>Projects</h1>
    <table class="table table-striped table-bordered">
        <thead class="thead-dark">
            <tr>
                <th>Name</th>
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
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td><a href='project-details.php?project_id=" . $row['project_id'] . "'>" . htmlspecialchars($row['name']) . "</a></td>";
                echo "<td>" . htmlspecialchars($row['description']) . "</td>";
                echo "<td>" . $row['start_date'] . "</td>";
                echo "<td>" . $row['end_date'] . "</td>";
                echo "<td>$" . number_format($row['budget'], 2) . "</td>";
                echo "<td><a href='#' class='btn btn-sm btn-primary'>Edit</a></td>";
                echo "</tr>";
            }
            ?>
        </tbody>
    </table>
</div>
<?php 
include 'includes/footer.php';
?>
