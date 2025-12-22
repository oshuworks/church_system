// General scripts for UI interaction and resource assignment
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        document.querySelectorAll('.alert').forEach(function(alert) {
            alert.style.opacity = '0';
        });
    }, 3000);

    // Initialize resource assignment functionality if on a project page
    initializeResourceAssignment();

    // Initialize notification system
    initializeNotificationSystem();
});

// Initialize resource assignment functionality
function initializeResourceAssignment() {
    const resourceAssignmentContainer = document.getElementById('resource-assignment-container');
    if (resourceAssignmentContainer) {
        loadProjectResources();
        setupResourceAssignmentEventListeners();
    }
}

// Load resources for current project
async function loadProjectResources() {
    const projectId = getCurrentProjectId();
    if (!projectId) return;

    try {
        const response = await fetch(`api/resource.php?utilization=report`);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();

        if (data.error) {
            throw new Error(data.error);
        }

        displayProjectResources(data.utilization_report);

    } catch (error) {
        console.error('Error loading project resources:', error);
    }
}

// Get current project ID from URL or page context
function getCurrentProjectId() {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get('project_id') || document.getElementById('current-project-id')?.value;
}

// Display resources for project assignment
function displayProjectResources(resources) {
    const container = document.getElementById('project-resources-list');
    if (!container) return;

    if (!resources || resources.length === 0) {
        container.innerHTML = '<p class="text-muted">No resources available for assignment</p>';
        return;
    }

    container.innerHTML = resources.map(resource => {
        const utilizationClass = getResourceUtilizationClass(resource.utilization_status);
        return `
            <div class="card mb-3 resource-card ${utilizationClass}" data-resource-id="${resource.resource_id}">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-1">${resource.name}</h6>
                            <p class="card-text small text-muted mb-1">${resource.resource_type}</p>
                            <div class="d-flex align-items-center">
                                <span class="me-2 small">Allocation: ${formatPercentage(resource.total_allocation)}</span>
                                <div class="progress" style="width: 80px; height: 6px;">
                                    <div class="progress-bar ${getUtilizationProgressBarClass(resource.utilization_status)}"
                                         style="width: ${Math.min(resource.total_allocation, 100)}%"></div>
                                </div>
                            </div>
                        </div>
                        <div class="text-end">
                            <span class="badge ${getUtilizationBadgeClass(resource.utilization_status)} mb-2">
                                ${resource.utilization_status}
                            </span>
                            <br>
                            <button class="btn btn-sm btn-outline-primary" onclick="assignResourceToProject(${resource.resource_id})">
                                Assign
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

// Get resource utilization CSS class
function getResourceUtilizationClass(status) {
    switch (status) {
        case 'Over-utilized':
            return 'border-danger';
        case 'Well-utilized':
            return 'border-success';
        case 'Under-utilized':
            return 'border-warning';
        default:
            return 'border-secondary';
    }
}

// Get utilization badge CSS class
function getUtilizationBadgeClass(status) {
    switch (status) {
        case 'Over-utilized':
            return 'bg-danger';
        case 'Well-utilized':
            return 'bg-success';
        case 'Under-utilized':
            return 'bg-warning text-dark';
        default:
            return 'bg-secondary';
    }
}

// Assign resource to project
async function assignResourceToProject(resourceId) {
    const projectId = getCurrentProjectId();
    if (!projectId) {
        alert('No project selected');
        return;
    }

    // Show assignment modal or form
    showResourceAssignmentModal(resourceId, projectId);
}

// Show resource assignment modal
function showResourceAssignmentModal(resourceId, projectId) {
    const modal = document.getElementById('resourceAssignmentModal');
    if (modal) {
        document.getElementById('assignmentResourceId').value = resourceId;
        document.getElementById('assignmentProjectId').value = projectId;
        loadTasksForAssignment(projectId);

        const bootstrapModal = new bootstrap.Modal(modal);
        bootstrapModal.show();
    } else {
        // Fallback to simple prompt
        assignResourceWithPrompt(resourceId, projectId);
    }
}

// Load tasks for assignment dropdown
async function loadTasksForAssignment(projectId) {
    try {
        const response = await fetch(`api/tasks.php?project_id=${projectId}`);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();
        const taskSelect = document.getElementById('assignmentTaskSelect');

        if (data.error) {
            taskSelect.innerHTML = '<option value="">Error loading tasks</option>';
            return;
        }

        taskSelect.innerHTML = '<option value="">Select a task...</option>' +
            data.tasks.map(task =>
                `<option value="${task.task_id}">${task.name}</option>`
            ).join('');

    } catch (error) {
        console.error('Error loading tasks for assignment:', error);
        document.getElementById('assignmentTaskSelect').innerHTML = '<option value="">Error loading tasks</option>';
    }
}

// Submit resource assignment
async function submitResourceAssignment() {
    const resourceId = document.getElementById('assignmentResourceId').value;
    const projectId = document.getElementById('assignmentProjectId').value;
    const taskId = document.getElementById('assignmentTaskSelect').value;
    const allocationPercent = document.getElementById('assignmentPercent').value;

    if (!resourceId || !projectId || !taskId || !allocationPercent) {
        alert('Please fill in all fields');
        return;
    }

    const formData = new FormData();
    formData.append('resource_id', resourceId);
    formData.append('task_id', taskId);
    formData.append('allocation_percent', allocationPercent);

    try {
        const response = await fetch('api/resource.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.error) {
            throw new Error(data.error);
        }

        // Close modal and refresh
        bootstrap.Modal.getInstance(document.getElementById('resourceAssignmentModal')).hide();
        loadProjectResources();

        alert('Resource assigned successfully');

    } catch (error) {
        console.error('Error assigning resource:', error);
        alert('Failed to assign resource: ' + error.message);
    }
}

// Setup event listeners for resource assignment
function setupResourceAssignmentEventListeners() {
    const assignButtons = document.querySelectorAll('[data-assign-resource]');
    assignButtons.forEach(button => {
        button.addEventListener('click', function() {
            const resourceId = this.getAttribute('data-resource-id');
            const projectId = this.getAttribute('data-project-id');
            assignResourceToProject(resourceId, projectId);
        });
    });

    // Setup search functionality
    const searchInput = document.getElementById('resourceSearchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            filterResources(e.target.value);
        });
    }
}

// Filter resources based on search
function filterResources(searchTerm) {
    const resourceCards = document.querySelectorAll('.resource-card');
    const term = searchTerm.toLowerCase();

    resourceCards.forEach(card => {
        const text = card.textContent.toLowerCase();
        card.style.display = text.includes(term) ? '' : 'none';
    });
}

// Fallback resource assignment with prompt (for simple implementation)
function assignResourceWithPrompt(resourceId, projectId) {
    const allocationPercent = prompt('Enter allocation percentage (0-100):', '50');
    if (allocationPercent === null) return;

    // Get available tasks for this project
    fetch(`api/tasks.php?project_id=${projectId}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert('Error: ' + data.error);
                return;
            }

            if (!data.tasks || data.tasks.length === 0) {
                alert('No tasks available for this project');
                return;
            }

            const taskNames = data.tasks.map(task => task.name).join(', ');
            const selectedTask = prompt('Available tasks: ' + taskNames + '\nEnter task name:', data.tasks[0].name);

            if (!selectedTask) return;

            const selectedTaskData = data.tasks.find(task => task.name === selectedTask);
            if (!selectedTaskData) {
                alert('Invalid task name');
                return;
            }

            // Assign resource to selected task
            const formData = new FormData();
            formData.append('resource_id', resourceId);
            formData.append('task_id', selectedTaskData.task_id);
            formData.append('allocation_percent', allocationPercent);

            return fetch('api/resource.php', {
                method: 'POST',
                body: formData
            });
        })
        .then(response => response ? response.json() : null)
        .then(data => {
            if (data && data.error) {
                alert('Error: ' + data.error);
            } else if (data) {
                alert(data.message || 'Resource assigned successfully');
                loadProjectResources(); // Refresh the list
            }
        })
        .catch(err => alert('Error assigning resource: ' + err));
}

// Initialize notification system
function initializeNotificationSystem() {
    loadNotifications();
    checkForNewNotifications();

    // Check for notifications every 5 minutes
    setInterval(checkForNewNotifications, 5 * 60 * 1000);
}

// Load notifications for display
async function loadNotifications() {
    try {
        const response = await fetch('api/notifications_api.php?unread_only=true');
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();

        if (data.error) {
            throw new Error(data.error);
        }

        displayNotifications(data.notifications || []);

    } catch (error) {
        console.error('Error loading notifications:', error);
    }
}

// Display notifications in dropdown
function displayNotifications(notifications) {
    const notificationsList = document.getElementById('notifications-list');
    const notificationCount = document.getElementById('notification-count');
    const noNotificationsItem = document.getElementById('no-notifications');

    // Clear existing notifications (except headers and footer)
    const existingNotifications = notificationsList.querySelectorAll('.notification-item');
    existingNotifications.forEach(item => item.remove());

    if (!notifications || notifications.length === 0) {
        noNotificationsItem.style.display = 'block';
        notificationCount.style.display = 'none';
        return;
    }

    // Hide "no notifications" message
    noNotificationsItem.style.display = 'none';

    // Show notification count
    notificationCount.textContent = notifications.length;
    notificationCount.style.display = 'inline-block';

    // Add notifications
    const divider = notificationsList.querySelector('.dropdown-divider');
    notifications.slice(0, 5).forEach(notification => {
        const notificationItem = createNotificationItem(notification);
        notificationsList.insertBefore(notificationItem, divider);
    });

    if (notifications.length > 5) {
        const moreItem = document.createElement('li');
        moreItem.innerHTML = `<a class="dropdown-item text-center text-muted" href="#">... and ${notifications.length - 5} more</a>`;
        notificationsList.insertBefore(moreItem, divider);
    }
}

// Create notification item element
function createNotificationItem(notification) {
    const item = document.createElement('li');
    item.className = 'notification-item';

    const typeClass = getNotificationTypeClass(notification.notification_type);
    const timeAgo = getTimeAgo(notification.created_at);

    item.innerHTML = `
        <a class="dropdown-item ${notification.is_read ? 'text-muted' : ''}" href="#" onclick="markNotificationRead(${notification.notification_id})">
            <div class="d-flex align-items-start">
                <div class="me-2">
                    <span class="badge ${typeClass}">${getNotificationTypeIcon(notification.notification_type)}</span>
                </div>
                <div class="flex-grow-1">
                    <div class="small fw-bold">${notification.resource_name || 'System'}</div>
                    <div class="small">${notification.message}</div>
                    <div class="small text-muted">${timeAgo}</div>
                </div>
                ${!notification.is_read ? '<div class="ms-2"><span class="badge bg-primary">New</span></div>' : ''}
            </div>
        </a>
    `;

    return item;
}

// Get notification type CSS class
function getNotificationTypeClass(type) {
    switch (type) {
        case 'over_utilization':
            return 'bg-danger';
        case 'under_utilization':
            return 'bg-warning text-dark';
        case 'balancing_recommendation':
            return 'bg-info';
        case 'system_alert':
            return 'bg-secondary';
        default:
            return 'bg-primary';
    }
}

// Get notification type icon
function getNotificationTypeIcon(type) {
    switch (type) {
        case 'over_utilization':
            return '⚠️';
        case 'under_utilization':
            return '📉';
        case 'balancing_recommendation':
            return '⚖️';
        case 'system_alert':
            return '🔔';
        default:
            return '📋';
    }
}

// Get time ago string
function getTimeAgo(dateString) {
    const now = new Date();
    const notificationDate = new Date(dateString);
    const diffInMinutes = Math.floor((now - notificationDate) / (1000 * 60));

    if (diffInMinutes < 1) return 'Just now';
    if (diffInMinutes < 60) return `${diffInMinutes}m ago`;

    const diffInHours = Math.floor(diffInMinutes / 60);
    if (diffInHours < 24) return `${diffInHours}h ago`;

    const diffInDays = Math.floor(diffInHours / 24);
    return `${diffInDays}d ago`;
}

// Mark notification as read
async function markNotificationRead(notificationId) {
    const formData = new FormData();
    formData.append('notification_id', notificationId);

    try {
        const response = await fetch('api/notifications_api.php', {
            method: 'PUT',
            body: formData
        });

        const data = await response.json();

        if (data.error) {
            throw new Error(data.error);
        }

        // Reload notifications
        loadNotifications();

    } catch (error) {
        console.error('Error marking notification as read:', error);
    }
}

// Mark all notifications as read
async function markAllNotificationsRead() {
    const formData = new FormData();
    formData.append('mark_all_read', true);

    try {
        const response = await fetch('api/notifications_api.php', {
            method: 'PUT',
            body: formData
        });

        const data = await response.json();

        if (data.error) {
            throw new Error(data.error);
        }

        // Reload notifications
        loadNotifications();
        alert('All notifications marked as read');

    } catch (error) {
        console.error('Error marking all notifications as read:', error);
        alert('Failed to mark notifications as read');
    }
}

// Check for new notifications
async function checkForNewNotifications() {
    try {
        // Trigger system check for new utilization notifications
        const response = await fetch('api/resource_balancing.php?check_notifications=true');
        if (response.ok) {
            const data = await response.json();
            if (data.success && data.notifications_created > 0) {
                // Reload notifications if new ones were created
                loadNotifications();

                // Show a subtle notification
                showSystemNotification(`Created ${data.notifications_created} new utilization alerts`);
            }
        }

        // Also check for new notifications
        loadNotifications();

    } catch (error) {
        console.error('Error checking for new notifications:', error);
    }
}

// Show system notification (toast)
function showSystemNotification(message) {
    // Create a simple toast notification
    const toast = document.createElement('div');
    toast.className = 'toast align-items-center text-white bg-info border-0';
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <i class="fas fa-info-circle me-2"></i>${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" onclick="this.parentElement.parentElement.remove()"></button>
        </div>
    `;

    // Add to page
    const container = document.querySelector('.container') || document.body;
    container.insertBefore(toast, container.firstChild);

    // Show and auto-hide
    setTimeout(() => toast.classList.add('show'), 100);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 5000);
}