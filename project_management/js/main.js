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
            return 'bg-dark';
        case 'under_utilization':
            return 'bg-dark text-white';
        case 'balancing_recommendation':
            return 'bg-primary';
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
    toast.className = 'toast align-items-center text-white bg-primary border-0';
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