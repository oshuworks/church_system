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
    // Skip - Notifications are created ONLY on the notifications page on first load
    // This prevents duplicate notification creation
    return;
}

// Load notifications for display
async function loadNotifications() {
    try {
        // Load deadline notifications (both read and unread to show the list)
        const response = await fetch(window.BASE_URL + '/api/notifications_api.php?deadline_only=true');
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
    // Use URL-encoded format instead of FormData for better compatibility
    const params = new URLSearchParams();
    params.append('notification_id', notificationId);

    try {
        const response = await fetch(window.BASE_URL + '/api/notifications_api.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: params
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

// Mark all notifications as read (for notifications page)
async function markAllNotificationsRead() {
    // Use URL-encoded format instead of FormData for better compatibility
    const params = new URLSearchParams();
    params.append('mark_all_read', 'true');

    try {
        const response = await fetch(window.BASE_URL + '/api/notifications_api.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: params
        });

        const data = await response.json();

        if (data.error) {
            throw new Error(data.error);
        }

        // Reload notifications in dropdown
        if (typeof loadNavbarNotifications === 'function') {
            loadNavbarNotifications();
        }
        
        // Update badges
        if (typeof updateNavbarNotificationCount === 'function') {
            updateNavbarNotificationCount();
        }
        if (typeof updateHeaderNotificationBadge === 'function') {
            updateHeaderNotificationBadge();
        }
        
        // If on notifications page, reload it
        if (window.location.pathname.includes('notifications.php')) {
            if (typeof loadAllNotifications === 'function') {
                loadAllNotifications();
            }
        }

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
        const response = await fetch(window.BASE_URL + '/api/resource_balancing.php?check_notifications=true');
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
    
    // Trigger animation - add the show class for proper CSS transitions
    setTimeout(() => {
        toast.classList.add('show');
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
            // Remove centered class if no more toasts
            const container = document.getElementById('toast-container');
            if (container && container.classList.contains('centered') && container.children.length === 0) {
                container.classList.remove('centered');
            }
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
    // Add centered class for confirmations
    container.classList.add('centered');
    
    // Determine icon and class based on confirm text
    let icon = 'fa-exclamation-triangle';
    let confirmationClass = 'delete-confirmation';
    
    if (confirmText.includes('Mark')) {
        icon = 'fa-check-circle';
        confirmationClass = 'mark-confirmation';
    } else if (confirmText.includes('Clear')) {
        icon = 'fa-broom';
        confirmationClass = 'clear-confirmation';
    } else if (confirmText.includes('Delete')) {
        icon = 'fa-trash';
        confirmationClass = 'delete-confirmation';
    }
    
    const toast = document.createElement('div');
    toast.className = `toast-notification toast-confirmation ${confirmationClass}`;
    
    toast.innerHTML = `
        <div class="toast-icon">
            <i class="fas ${icon}"></i>
        </div>
        <div class="toast-content">
            <div class="toast-title">${title}</div>
            <div class="toast-message">${message}</div>
            <div class="toast-confirmation-actions">
                <button type="button" class="btn btn-cancel" onclick="handleToastCancel(this)">
                    <i class="fas fa-times me-1"></i>${cancelText}
                </button>
                <button type="button" class="btn btn-confirm" onclick="handleToastConfirm(this)">
                    <i class="fas ${confirmText.includes('Mark') ? 'fa-check' : confirmText.includes('Clear') ? 'fa-broom' : confirmText.includes('Delete') ? 'fa-trash' : 'fa-check'} me-1"></i>${confirmText}
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
    
    // Trigger animation - add the show class for proper CSS transitions
    setTimeout(() => {
        toast.classList.add('show');
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

/**
 * Load notifications for navbar dropdown
 */
async function loadNavbarNotifications() {
    const container = document.getElementById('navbar-notifications-list');
    const countBadge = document.getElementById('navbar-unread-badge');
    
    if (!container) return;
    
    // Show loading state
    container.innerHTML = '<li class="text-center py-3"><i class="fas fa-spinner fa-spin"></i> Loading...</li>';
    
    try {
        // Get all notifications (not just deadline)
        const response = await fetch(window.BASE_URL + '/api/notifications_api.php');
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.error) {
            throw new Error(data.error);
        }
        
        const notifications = data.notifications || [];
        
        // Update unread count badge
        const unreadCount = notifications.filter(n => !n.is_read).length;
        if (unreadCount > 0) {
            countBadge.textContent = unreadCount;
            countBadge.style.display = 'inline-block';
        } else {
            countBadge.style.display = 'none';
        }
        
        // Sort: unread first, then by date (newest first)
        notifications.sort((a, b) => {
            if (a.is_read !== b.is_read) {
                return a.is_read ? 1 : -1;
            }
            return new Date(b.created_at) - new Date(a.created_at);
        });
        
        // Show only top 5 notifications
        const topNotifications = notifications.slice(0, 5);
        
        if (topNotifications.length === 0) {
            container.innerHTML = '<li class="text-center py-3 text-muted">No notifications</li>';
            return;
        }
        
        // Render notifications
        container.innerHTML = topNotifications.map(n => createNavbarNotificationItem(n)).join('');
        
    } catch (error) {
        console.error('Error loading navbar notifications:', error);
        container.innerHTML = '<li class="text-center py-3 text-danger"><i class="fas fa-exclamation-triangle"></i> Failed to load</li>';
    }
}

/**
 * Create navbar notification item
 */
function createNavbarNotificationItem(notification) {
    const isUnread = !notification.is_read;
    const icon = getNotificationTypeIcon(notification.notification_type);
    const timeAgo = getTimeAgo(notification.created_at);
    const badgeClass = notification.badge_class || getNotificationTypeClass(notification.notification_type);
    
    // Determine type label
    let typeLabel = '';
    if (notification.notification_type === 'project_due_date') {
        typeLabel = '<span class="badge bg-danger me-1">Due</span>';
    } else if (notification.notification_type === 'project_near_deadline') {
        typeLabel = '<span class="badge bg-warning me-1">Near</span>';
    } else if (notification.notification_type === 'task_due_date') {
        typeLabel = '<span class="badge bg-danger me-1">Task</span>';
    } else if (notification.notification_type === 'task_near_deadline') {
        typeLabel = '<span class="badge bg-warning me-1">Task</span>';
    } else if (notification.notification_type === 'over_utilization') {
        typeLabel = '<span class="badge bg-danger me-1">High</span>';
    } else if (notification.notification_type === 'under_utilization') {
        typeLabel = '<span class="badge bg-info me-1">Low</span>';
    } else if (notification.notification_type === 'balancing_recommendation') {
        typeLabel = '<span class="badge bg-primary me-1">Balance</span>';
    } else if (notification.notification_type === 'system_alert') {
        typeLabel = '<span class="badge bg-secondary me-1">Alert</span>';
    }
    
    const projectName = notification.project_name ? `<strong>${notification.project_name}</strong> ` : '';
    
    return `
        <li>
            <a class="dropdown-item notification-item ${isUnread ? 'fw-bold' : ''}" href="#" onclick="markNavbarNotificationRead(${notification.notification_id}); return false;">
                <div class="d-flex align-items-start gap-2">
                    <div class="flex-shrink-0" style="font-size: 1.2rem;">${icon}</div>
                    <div class="flex-grow-1">
                        <div class="small">${typeLabel}${projectName}</div>
                        <div class="small text-truncate" style="max-width: 200px;">${notification.message}</div>
                        <div class="small text-muted">${timeAgo}</div>
                    </div>
                    ${isUnread ? '<div class="flex-shrink-0"><span class="badge bg-primary">New</span></div>' : ''}
                </div>
            </a>
        </li>
    `;
}

/**
 * Mark navbar notification as read
 */
async function markNavbarNotificationRead(notificationId) {
    // Use styled confirmation toast instead of browser confirm
    showConfirmToast(
        'Mark this notification as read?',
        'Mark as Read',
        async () => {
            try {
                // Use URL-encoded format instead of FormData for better compatibility
                const params = new URLSearchParams();
                params.append('notification_id', notificationId);
                
                const response = await fetch(window.BASE_URL + '/api/notifications_api.php', {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: params
                });
                
                const data = await response.json();
                
                if (data.error) {
                    throw new Error(data.error);
                }
                
                // Reload navbar notifications
                loadNavbarNotifications();
                
                // If on notifications page, reload it too
                if (window.location.pathname.includes('notifications.php')) {
                    if (typeof loadAllNotifications === 'function') {
                        loadAllNotifications();
                    }
                }
                
            } catch (error) {
                console.error('Error marking navbar notification as read:', error);
            }
        },
        null,
        'Mark as Read',
        'Cancel'
    );
}

/**
 * Mark all navbar notifications as read
 * This is an alias for markAllNotificationsRead for backward compatibility
 */
async function markAllNavbarNotificationsRead() {
    return markAllNotificationsRead();
}

/**
 * Update navbar notification count (call this periodically)
 */
async function updateNavbarNotificationCount() {
    const countBadge = document.getElementById('notification-count');
    if (!countBadge) return;
    
    try {
        const response = await fetch(window.BASE_URL + '/api/notifications_api.php?unread_only=true');
        if (!response.ok) return;
        
        const data = await response.json();
        if (data.error) return;
        
        const unreadCount = (data.notifications || []).length;
        
        if (unreadCount > 0) {
            countBadge.textContent = unreadCount;
            countBadge.style.display = 'inline-block';
        } else {
            countBadge.style.display = 'none';
        }
        
    } catch (error) {
        console.error('Error updating notification count:', error);
    }
}

// Auto-update navbar notification count every 2 minutes
if (typeof window !== 'undefined') {
    window.addEventListener('load', () => {
        // Initial load
        setTimeout(updateNavbarNotificationCount, 1000);
        
        // Periodic updates
        setInterval(updateNavbarNotificationCount, 120000); // 2 minutes
    });
}

/**
 * Check for new notifications and show toast if found
 * This runs automatically in the background
 */
async function checkForNewNotificationsBackground() {
    try {
        const response = await fetch(window.BASE_URL + '/api/notifications_api.php?unread_only=true');
        if (!response.ok) return;
        
        const data = await response.json();
        if (data.error) return;
        
        const unreadCount = (data.notifications || []).length;
        
        // Update navbar badge
        const countBadge = document.getElementById('notification-count');
        if (countBadge) {
            if (unreadCount > 0) {
                countBadge.textContent = unreadCount;
                countBadge.style.display = 'inline-block';
            } else {
                countBadge.style.display = 'none';
            }
        }
        
        // Update header badge
        const headerBadge = document.getElementById('header-notification-badge');
        if (headerBadge) {
            if (unreadCount > 0) {
                headerBadge.textContent = unreadCount;
                headerBadge.style.display = 'inline-block';
            } else {
                headerBadge.style.display = 'none';
            }
        }
        
        // Show toast for new notifications (if any)
        if (unreadCount > 0 && window.lastNotificationCount !== undefined && unreadCount > window.lastNotificationCount) {
            const newCount = unreadCount - window.lastNotificationCount;
            if (newCount > 0) {
                showNewNotificationToast(newCount);
            }
        }
        
        window.lastNotificationCount = unreadCount;
        
    } catch (error) {
        // Silent fail for background checks
        console.log('Background notification check failed:', error.message);
    }
}

// Run background notification check every 5 minutes
if (typeof window !== 'undefined') {
    window.addEventListener('load', () => {
        // Initial check
        setTimeout(checkForNewNotificationsBackground, 5000);
        
        // Periodic checks
        setInterval(checkForNewNotificationsBackground, 300000); // 5 minutes
    });
}

/**
 * Update header notification badge (for pages with header button)
 */
async function updateHeaderNotificationBadge() {
    const badge = document.getElementById('header-notification-badge');
    if (!badge) return;
    
    try {
        const response = await fetch(window.BASE_URL + '/api/notifications_api.php?unread_only=true');
        if (!response.ok) return;
        
        const data = await response.json();
        if (data.error) return;
        
        const unreadCount = (data.notifications || []).length;
        
        if (unreadCount > 0) {
            badge.textContent = unreadCount;
            badge.style.display = 'inline-block';
        } else {
            badge.style.display = 'none';
        }
        
    } catch (error) {
        console.log('Header badge update failed:', error.message);
    }
}

// Auto-update header badge every 2 minutes
if (typeof window !== 'undefined') {
    window.addEventListener('load', () => {
        // Initial load
        setTimeout(updateHeaderNotificationBadge, 1500);
        
        // Periodic updates
        setInterval(updateHeaderNotificationBadge, 120000); // 2 minutes
    });
}

/**
 * Initialize notification system on all pages
 * This runs automatically on page load
 */
function initializePageNotifications() {
    // Update navbar badge
    if (typeof updateNavbarNotificationCount === 'function') {
        updateNavbarNotificationCount();
    }
    
    // Update header badge
    if (typeof updateHeaderNotificationBadge === 'function') {
        updateHeaderNotificationBadge();
    }
    
    // If on notifications page, load all notifications
    if (window.location.pathname.includes('notifications.php')) {
        if (typeof loadAllNotifications === 'function') {
            loadAllNotifications();
        }
    }
    
    // Check for new notifications after 10 seconds
    setTimeout(() => {
        if (typeof checkForNewNotificationsBackground === 'function') {
            checkForNewNotificationsBackground();
        }
    }, 10000);
}

// Initialize on all pages
if (typeof window !== 'undefined') {
    window.addEventListener('DOMContentLoaded', initializePageNotifications);
}

/**
 * Show a notification toast for new notifications
 * This is called when new notifications are detected
 */
function showNewNotificationToast(count) {
    if (count <= 0) return;
    
    const message = `${count} new notification${count > 1 ? 's' : ''} received`;
    const toast = showToast(message, 'info', 'New Notifications', 8000);
    
    // Add a "View" button to the toast
    if (toast) {
        const actions = document.createElement('div');
        actions.className = 'toast-actions mt-2 d-flex gap-2';
        
        // Get BASE_URL from navbar or use empty string
        const baseUrl = window.BASE_URL || '';
        
        actions.innerHTML = `
            <button class="btn btn-sm btn-primary" onclick="window.location.href = '${baseUrl}/notifications.php'">
                <i class="fas fa-eye me-1"></i> View
            </button>
            <button class="btn btn-sm btn-outline-secondary" onclick="this.closest('.toast-notification').remove()">
                Dismiss
            </button>
        `;
        
        // Find the toast content and append actions
        const toastContent = toast.querySelector('.toast-content');
        if (toastContent) {
            toastContent.appendChild(actions);
        }
    }
}