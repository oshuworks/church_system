// General scripts for UI interaction (notifications, small UX tweaks)
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        document.querySelectorAll('.alert').forEach(function(alert) {
            alert.style.opacity = '0';
        });
    }, 3000);

    // Initialize notification system
    initializeNotificationSystem();
});

// Initialize notification system
let notificationIntervals = [];
function initializeNotificationSystem() {
    // Prevent duplicate initialization
    if (window.notificationSystemInitialized) {
        return;
    }

    // Only initialize if notifications UI exists on the page
    const notificationsList = document.getElementById('notifications-list');
    if (!notificationsList) {
        // Skip initializing notifications system on pages without notification UI
        return;
    }

    window.notificationSystemInitialized = true;
    
    // Load notifications first
    loadNotifications();
    
    // Check for deadline notifications on page load (this will also reload notifications)
    checkDeadlineNotifications();
    
    // Check for new notifications (resource balancing, etc.)
    checkForNewNotifications();

    // Check for notifications every 5 minutes
    notificationIntervals.push(setInterval(checkForNewNotifications, 5 * 60 * 1000));
    // Check for deadline notifications every hour
    notificationIntervals.push(setInterval(checkDeadlineNotifications, 60 * 60 * 1000));
}

// Check and create deadline notifications
async function checkDeadlineNotifications() {
    // Skip work if notifications UI is not present
    const notificationsList = document.getElementById('notifications-list');
    if (!notificationsList) return;

    try {
        const response = await fetch('project_management/api/deadline_notifications.php');
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const data = await response.json();
        // Always reload notifications after checking deadlines to show any new ones
        // Even if no new notifications were created, we want to show existing ones
        loadNotifications();
    } catch (error) {
        console.error('Error checking deadline notifications:', error);
    }
}

// Load notifications for display
async function loadNotifications() {
    try {
        // Load deadline notifications (both read and unread to show the list)
        const response = await fetch('project_management/api/notifications_api.php?deadline_only=true');
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();

        if (data.error) {
            throw new Error(data.error);
        }

        console.log('Loaded notifications:', data.notifications?.length || 0, data.notifications);
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

    // If the notifications UI is not present on the page (some pages may omit the navbar), bail out gracefully
    if (!notificationsList) {
        // Nothing to render; avoid throwing errors on pages without notification UI
        console.warn('Notifications UI not present on this page. Skipping rendering.');
        return;
    }

    // Clear existing notifications (except headers and footer)
    const existingNotifications = notificationsList.querySelectorAll('.notification-item');
    existingNotifications.forEach(item => item.remove());

    // Filter to show only deadline notifications
    const deadlineNotifications = notifications.filter(n => 
        n.notification_type && (
            n.notification_type.includes('due_date') || 
            n.notification_type.includes('near_deadline')
        )
    );

    if (!deadlineNotifications || deadlineNotifications.length === 0) {
        if (noNotificationsItem) noNotificationsItem.style.display = 'block';
        if (notificationCount) notificationCount.style.display = 'none';
        return;
    }

    // Hide "no notifications" message
    if (noNotificationsItem) noNotificationsItem.style.display = 'none';

    // Show notification count (unread deadline notifications only)
    const unreadDeadlineCount = deadlineNotifications.filter(n => !n.is_read).length;
    if (unreadDeadlineCount > 0) {
        if (notificationCount) {
            notificationCount.textContent = unreadDeadlineCount;
            notificationCount.style.display = 'inline-block';
        }
    } else {
        if (notificationCount) notificationCount.style.display = 'none';
    }

    // Sort notifications: unread first, then by date (newest first)
    deadlineNotifications.sort((a, b) => {
        if (a.is_read !== b.is_read) {
            return a.is_read ? 1 : -1;
        }
        return new Date(b.created_at) - new Date(a.created_at);
    });

    // Add notifications (show all deadline notifications)
    const divider = notificationsList.querySelector('.dropdown-divider');
    deadlineNotifications.forEach(notification => {
        const notificationItem = createNotificationItem(notification);
        notificationsList.insertBefore(notificationItem, divider);
    });
}

// Create notification item element
function createNotificationItem(notification) {
    const item = document.createElement('li');
    item.className = 'notification-item';

    const typeClass = getNotificationTypeClass(notification.notification_type);
    const timeAgo = getTimeAgo(notification.created_at);

    const title = notification.resource_name || notification.project_name || 'System';
    const badgeClass = notification.badge_class || typeClass;
    
    // Determine notification type label
    let typeLabel = '';
    if (notification.notification_type === 'project_due_date') {
        typeLabel = '<span class="badge bg-danger me-1">Due Today</span>';
    } else if (notification.notification_type === 'project_near_deadline') {
        typeLabel = '<span class="badge bg-warning me-1">Near Deadline</span>';
    } else if (notification.notification_type === 'task_due_date') {
        typeLabel = '<span class="badge bg-danger me-1">Task Due Today</span>';
    } else if (notification.notification_type === 'task_near_deadline') {
        typeLabel = '<span class="badge bg-warning me-1">Task Near Deadline</span>';
    } else if (notification.notification_type === 'subtask_due_date') {
        typeLabel = '<span class="badge bg-danger me-1">Subtask Due Today</span>';
    } else if (notification.notification_type === 'subtask_near_deadline') {
        typeLabel = '<span class="badge bg-warning me-1">Subtask Near Deadline</span>';
    }
    
    item.innerHTML = `
        <a class="dropdown-item ${notification.is_read ? 'text-muted' : ''}" href="#" onclick="markNotificationRead(${notification.notification_id})">
            <div class="d-flex align-items-start">
                <div class="me-2">
                    <span class="badge ${badgeClass}">${getNotificationTypeIcon(notification.notification_type)}</span>
                </div>
                <div class="flex-grow-1">
                    <div class="small fw-bold mb-1">${typeLabel}${title}</div>
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
            return 'bg-info';
        case 'balancing_recommendation':
            return 'bg-primary';
        case 'system_alert':
            return 'bg-secondary';
        case 'project_due_date':
        case 'task_due_date':
        case 'subtask_due_date':
            return 'bg-danger';
        case 'project_near_deadline':
        case 'task_near_deadline':
        case 'subtask_near_deadline':
            return 'bg-warning';
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
        case 'project_due_date':
        case 'task_due_date':
        case 'subtask_due_date':
            return '🔴';
        case 'project_near_deadline':
        case 'task_near_deadline':
        case 'subtask_near_deadline':
            return '🟡';
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
        const response = await fetch('project_management/api/notifications_api.php', {
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
        const response = await fetch('project_management/api/notifications_api.php', {
            method: 'PUT',
            body: formData
        });

        const data = await response.json();

        if (data.error) {
            throw new Error(data.error);
        }

        // Reload notifications
        loadNotifications();
        showSuccess('All notifications marked as read');

    } catch (error) {
        console.error('Error marking all notifications as read:', error);
        showError('Failed to mark notifications as read');
    }
}

// Check for new notifications
async function checkForNewNotifications() {
    // Skip work if notifications UI is not present
    const notificationsList = document.getElementById('notifications-list');
    if (!notificationsList) return;

    try {
        // Trigger system check for new utilization notifications
        const response = await fetch('project_management/api/resource_balancing.php?check_notifications=true');
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
    showToast(message, 'info');
}

/**
 * Toast Notification System
 * Creates beautiful, animated toast notifications
 */

// Ensure toast container exists
function getToastContainer() {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'toast-container';
        document.body.appendChild(container);
    }
    return container;
}

/**
 * Show a toast notification
 * @param {string} message - The message to display
 * @param {string} type - Type: 'success', 'error', 'warning', 'info'
 * @param {string} title - Optional title
 * @param {number} duration - Duration in milliseconds (default: 5000, 0 = no auto-dismiss)
 */
function showToast(message, type = 'info', title = null, duration = 5000) {
    const container = getToastContainer();
    const toast = document.createElement('div');
    toast.className = `toast-notification toast-${type}`;
    
    // Get icon based on type
    const icons = {
        success: '<i class="fas fa-check-circle"></i>',
        error: '<i class="fas fa-exclamation-circle"></i>',
        warning: '<i class="fas fa-exclamation-triangle"></i>',
        info: '<i class="fas fa-info-circle"></i>'
    };
    
    const icon = icons[type] || icons.info;
    const displayTitle = title || (type === 'success' ? 'Success' : type === 'error' ? 'Error' : type === 'warning' ? 'Warning' : 'Information');
    
    toast.innerHTML = `
        <div class="toast-icon">${icon}</div>
        <div class="toast-content">
            ${title ? `<div class="toast-title">${displayTitle}</div>` : ''}
            <div class="toast-message">${message}</div>
        </div>
        <button type="button" class="toast-close" onclick="removeToast(this.closest('.toast-notification'))">
            <i class="fas fa-times"></i>
        </button>
        ${duration > 0 ? '<div class="toast-progress"><div class="toast-progress-bar" style="animation-duration: ' + duration + 'ms;"></div></div>' : ''}
    `;
    
    container.appendChild(toast);
    
    // Trigger animation
    setTimeout(() => {
        toast.style.opacity = '1';
    }, 10);
    
    // Auto-remove if duration is set
    if (duration > 0) {
        setTimeout(() => {
            removeToast(toast);
        }, duration);
    }
    
    return toast;
}

/**
 * Remove a toast notification
 */
function removeToast(toast) {
    if (!toast) return;
    
    toast.classList.add('toast-exiting');
    setTimeout(() => {
        if (toast.parentNode) {
            toast.parentNode.removeChild(toast);
        }
    }, 300);
}

/**
 * Show success toast
 */
function showSuccess(message, title = 'Success') {
    return showToast(message, 'success', title);
}

/**
 * Show error toast
 */
function showError(message, title = 'Error') {
    return showToast(message, 'error', title);
}

/**
 * Show warning toast
 */
function showWarning(message, title = 'Warning') {
    return showToast(message, 'warning', title);
}

/**
 * Show info toast
 */
function showInfo(message, title = 'Information') {
    return showToast(message, 'info', title);
}

/**
 * Show confirmation toast (for deletions and critical actions)
 * @param {string} message - The confirmation message
 * @param {string} title - Optional title
 * @param {function} onConfirm - Callback when confirmed
 * @param {function} onCancel - Optional callback when cancelled
 * @param {string} confirmText - Text for confirm button (default: 'Delete')
 * @param {string} cancelText - Text for cancel button (default: 'Cancel')
 */
function showConfirmToast(message, title = 'Confirm Action', onConfirm, onCancel = null, confirmText = 'Delete', cancelText = 'Cancel') {
    const container = getToastContainer();
    const toast = document.createElement('div');
    toast.className = 'toast-notification toast-confirmation';
    
    toast.innerHTML = `
        <div class="toast-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="toast-content">
            <div class="toast-title">${title}</div>
            <div class="toast-message">${message}</div>
            <div class="toast-confirmation-actions">
                <button type="button" class="btn btn-cancel" onclick="handleToastCancel(this)">
                    <i class="fas fa-times me-1"></i>${cancelText}
                </button>
                <button type="button" class="btn btn-confirm" onclick="handleToastConfirm(this)">
                    <i class="fas fa-trash me-1"></i>${confirmText}
                </button>
            </div>
        </div>
        <button type="button" class="toast-close" onclick="handleToastCancel(this)">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    // Store callbacks in toast element
    toast._onConfirm = onConfirm;
    toast._onCancel = onCancel;
    
    container.appendChild(toast);
    
    // Trigger animation
    setTimeout(() => {
        toast.style.opacity = '1';
    }, 10);
    
    return toast;
}

/**
 * Handle toast confirmation
 */
function handleToastConfirm(button) {
    const toast = button.closest('.toast-notification');
    if (toast && toast._onConfirm) {
        toast._onConfirm();
    }
    removeToast(toast);
}

/**
 * Handle toast cancellation
 */
function handleToastCancel(button) {
    const toast = button.closest('.toast-notification');
    if (toast && toast._onCancel) {
        toast._onCancel();
    }
    removeToast(toast);
}