<?php
require_once __DIR__ . '/bootstrap.php';

// This function can be called whenever task status changes
function updateProjectStatus($mysqli, $projectId = null) {
    if ($projectId) {
        // Update specific project
        $projectCheckSql = "
            SELECT p.project_id, COUNT(t.task_id) as total_tasks, 
                   SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks
            FROM projects p
            LEFT JOIN tasks t ON t.project_id = p.project_id
            WHERE p.project_id = ?
            GROUP BY p.project_id
        ";
        $projectCheck = fetchAllAssoc(runQuery($mysqli, $projectCheckSql, ['i', [$projectId]]));
        
        if (!empty($projectCheck) && $projectCheck[0]['total_tasks'] > 0) {
            $totalTasks = $projectCheck[0]['total_tasks'];
            $completedTasks = $projectCheck[0]['completed_tasks'];
            
            if ($totalTasks == $completedTasks) {
                // All tasks completed, mark project as completed
                runQuery($mysqli, "UPDATE projects SET status = 'completed' WHERE project_id = ?", ['i', [$projectId]]);
            } elseif ($completedTasks > 0) {
                // Some tasks completed, ensure project is active
                runQuery($mysqli, "UPDATE projects SET status = 'active' WHERE project_id = ? AND status != 'completed'", ['i', [$projectId]]);
            }
        }
    } else {
        // Update all projects
        $allProjectsSql = "
            SELECT p.project_id, COUNT(t.task_id) as total_tasks, 
                   SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks
            FROM projects p
            LEFT JOIN tasks t ON t.project_id = p.project_id
            GROUP BY p.project_id
        ";
        $allProjects = fetchAllAssoc(runQuery($mysqli, $allProjectsSql));
        
        foreach ($allProjects as $project) {
            $totalTasks = $project['total_tasks'];
            $completedTasks = $project['completed_tasks'];
            $projectId = $project['project_id'];
            
            if ($totalTasks == 0) {
                // No tasks, keep as planning
                continue;
            } elseif ($totalTasks == $completedTasks) {
                // All tasks completed, mark project as completed
                runQuery($mysqli, "UPDATE projects SET status = 'completed' WHERE project_id = ?", ['i', [$projectId]]);
            } elseif ($completedTasks > 0) {
                // Some tasks completed, ensure project is active
                runQuery($mysqli, "UPDATE projects SET status = 'active' WHERE project_id = ? AND status != 'completed'", ['i', [$projectId]]);
            }
        }
    }
}

// If called directly, update all projects
if (basename($_SERVER['PHP_SELF']) === 'update_project_status.php') {
    updateProjectStatus($mysqli);
    respond(['message' => 'Project statuses updated successfully']);
}
?>
