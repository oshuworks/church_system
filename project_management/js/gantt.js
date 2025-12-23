// gantt.js - Gantt chart functionality for project planning

/**
 * Initialize Gantt chart with tasks data
 * @param {Array} tasks - Array of task objects
 * @param {string} containerId - ID of the container element
 */
function initializeGanttChart(tasks, containerId = 'gantt-chart') {
    if (!tasks || tasks.length === 0) {
        console.warn('No tasks provided for Gantt chart');
        return null;
    }

    // Convert tasks to Gantt format
    const ganttData = tasks.map(task => {
        const start = task.start_date ? new Date(task.start_date) : new Date();
        const end = task.end_date ? new Date(task.end_date) : 
                   new Date(start.getTime() + 7 * 24 * 60 * 60 * 1000); // Default 7 days if no end date
        
        return {
            id: `task-${task.task_id}`,
            name: task.name,
            start: start.toISOString().split('T')[0],
            end: end.toISOString().split('T')[0],
            progress: task.progress_percent || 0,
            dependencies: task.dependency_task_id ? `task-${task.dependency_task_id}` : null,
            custom_class: getTaskStatusClass(task.progress_percent)
        };
    });

    // Initialize Frappe Gantt
    const gantt = new Gantt(`#${containerId}`, ganttData, {
        view_mode: 'Month',
        header_height: 50,
        column_width: 30,
        step: 24,
        bar_height: 20,
        bar_corner_radius: 3,
        arrow_curve: 5,
        padding: 18,
        date_format: 'YYYY-MM-DD',
        language: 'en',
        on_click: function(task) {
            handleGanttTaskClick(task);
        },
        on_date_change: function(task, start, end) {
            handleGanttDateChange(task, start, end);
        },
        on_progress_change: function(task, progress) {
            handleGanttProgressChange(task, progress);
        },
        on_view_change: function(mode) {
            console.log('Gantt view mode changed to:', mode);
        }
    });

    return gantt;
}

/**
 * Get CSS class based on task progress
 */
function getTaskStatusClass(progress) {
    if (progress === 100) return 'completed';
    if (progress > 0) return 'in-progress';
    return 'not-started';
}

/**
 * Handle task click in Gantt chart
 */
function handleGanttTaskClick(task) {
    const taskId = task.id.replace('task-', '');
    
    // Check if editTask function exists (from project-details.php)
    if (typeof editTask === 'function') {
        editTask(parseInt(taskId));
    } else {
        console.log('Task clicked:', task);
        // You can add custom click handling here
    }
}

/**
 * Handle date change in Gantt chart
 */
async function handleGanttDateChange(task, start, end) {
    const taskId = task.id.replace('task-', '');
    
    // Update task dates via API
    try {
        const response = await fetch('api/task.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                task_id: parseInt(taskId),
                start_date: start.toISOString().split('T')[0],
                end_date: end.toISOString().split('T')[0]
            })
        });

        const data = await response.json();
        
        if (data.error) {
            throw new Error(data.error);
        }

        // Show success notification
        showNotification('Task dates updated successfully', 'success');
        
        // Reload project if function exists
        if (typeof loadProjectDetails === 'function') {
            loadProjectDetails();
        }
        
    } catch (error) {
        console.error('Error updating task dates:', error);
        showNotification('Failed to update task dates: ' + error.message, 'error');
    }
}

/**
 * Handle progress change in Gantt chart
 */
async function handleGanttProgressChange(task, progress) {
    const taskId = task.id.replace('task-', '');
    
    // Update task progress via API
    try {
        const response = await fetch('api/task.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                task_id: parseInt(taskId),
                progress_percent: Math.round(progress)
            })
        });

        const data = await response.json();
        
        if (data.error) {
            throw new Error(data.error);
        }

        // Show success notification
        showNotification('Task progress updated successfully', 'success');
        
        // Reload project if function exists
        if (typeof loadProjectDetails === 'function') {
            loadProjectDetails();
        }
        
    } catch (error) {
        console.error('Error updating task progress:', error);
        showNotification('Failed to update task progress: ' + error.message, 'error');
    }
}

/**
 * Render Gantt chart with tasks from API
 */
async function renderGanttChart(projectId, containerId = 'gantt-chart') {
    try {
        const response = await fetch(`api/project.php?project_id=${projectId}`);
        const data = await response.json();
        
        if (data.error) {
            throw new Error(data.error);
        }
        
        const tasks = data.tasks || [];
        return initializeGanttChart(tasks, containerId);
        
    } catch (error) {
        console.error('Error loading tasks for Gantt chart:', error);
        showNotification('Failed to load tasks: ' + error.message, 'error');
        return null;
    }
}

/**
 * Show notification (helper function)
 * Uses the new toast notification system
 */
function showNotification(message, type = 'info') {
    // Map types to toast function
    const typeMap = {
        'success': 'success',
        'error': 'error',
        'warning': 'warning',
        'info': 'info'
    };
    
    const toastType = typeMap[type] || 'info';
    
    // Use the global toast functions if available, otherwise fallback
    if (typeof showToast === 'function') {
        showToast(message, toastType);
    } else if (typeof showSuccess === 'function' && type === 'success') {
        showSuccess(message);
    } else if (typeof showError === 'function' && type === 'error') {
        showError(message);
    } else if (typeof showWarning === 'function' && type === 'warning') {
        showWarning(message);
    } else if (typeof showInfo === 'function') {
        showInfo(message);
    } else {
        // Fallback to simple alert if toast system not available
        alert(message);
    }
}

/**
 * Export Gantt chart data
 */
function exportGanttData(tasks) {
    const ganttData = tasks.map(task => ({
        id: task.task_id,
        name: task.name,
        start: task.start_date,
        end: task.end_date,
        progress: task.progress_percent || 0,
        dependency: task.dependency_task_id || null
    }));
    
    const dataStr = JSON.stringify(ganttData, null, 2);
    const dataBlob = new Blob([dataStr], { type: 'application/json' });
    const url = URL.createObjectURL(dataBlob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `gantt-data-${Date.now()}.json`;
    link.click();
    URL.revokeObjectURL(url);
}

// Export functions for global use
if (typeof window !== 'undefined') {
    window.initializeGanttChart = initializeGanttChart;
    window.renderGanttChart = renderGanttChart;
    window.exportGanttData = exportGanttData;
}
