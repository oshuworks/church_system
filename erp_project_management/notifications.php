<?php
// Ensure session and auto-login run before any output to avoid headers already sent errors
if (session_status() == PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config/auth_helpers.php';
auto_login_from_remember();
$BASE_URL = $BASE_URL ?? '/church_system-main_m';
$loggedIn = $_SESSION['is_admin'] ?? false;
if (!$loggedIn) {
    header("Location: {$BASE_URL}/admin_login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Notifications - iServe</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/resources.css" />
    <link rel="stylesheet" href="css/navbar.css" />
    <link rel="stylesheet" href="css/style.css" />
    
    <style>
        body { background-color: #f0f4f8; }
        .notification-card {
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
            background: #ffffff;
        }
        
        .notification-card.unread {
            background: linear-gradient(135deg, #f8f9ff 0%, #e3f2fd 100%);
            border-left-color: #2196f3;
            box-shadow: 0 2px 8px rgba(33, 150, 243, 0.1);
        }
        
        .notification-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.12);
            border-left-color: #1976d2;
        }
        
        .notification-badge {
            font-size: 0.75rem;
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-weight: 700;
            letter-spacing: 0.3px;
        }
        
        .notification-time {
            font-size: 0.85rem;
            color: #6c757d;
            font-weight: 500;
        }
        
        .notification-message {
            color: #495057;
            line-height: 1.5;
            font-size: 0.95rem;
        }
        
        .notification-icon {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.9), rgba(255,255,255,0.7));
            backdrop-filter: blur(10px);
        }
        
        .notification-actions {
            opacity: 0;
            transition: all 0.3s ease;
            transform: translateY(4px);
        }
        
        .notification-card:hover .notification-actions {
            opacity: 1;
            transform: translateY(0);
        }
        
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: #6c757d;
            background: #f8f9fa;
            border-radius: 12px;
            margin: 20px;
        }
        
        .empty-state i {
            font-size: 4.5rem;
            margin-bottom: 20px;
            opacity: 0.4;
        }
        
        .empty-state h5 {
            font-weight: 700;
            color: #495057;
        }
        
        .filter-buttons .btn {
            border-radius: 8px;
            padding: 10px 18px;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.2s ease;
            border-width: 2px;
        }
        
        .filter-buttons .btn.active {
            font-weight: 700;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(13, 110, 253, 0.25);
        }
        
        .filter-buttons .btn:hover:not(.active) {
            background: #e7f1ff;
            border-color: #0d6efd;
        }
        
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.3);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, transparent 100%);
            pointer-events: none;
        }
        
        .stats-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(102, 126, 234, 0.4);
        }
        
        .stats-card .stat-number {
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 5px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .stats-card .stat-label {
            font-size: 0.95rem;
            opacity: 0.95;
            font-weight: 600;
            letter-spacing: 0.3px;
        }
        
        .stats-card .opacity-25 {
            opacity: 0.25;
            transition: transform 0.3s ease;
        }
        
        .stats-card:hover .opacity-25 {
            transform: scale(1.1);
        }
        
        /* Loading spinner enhancement */
        .spinner-border {
            width: 3rem;
            height: 3rem;
            border-width: 0.3em;
        }
        
        /* Page container enhancement */
        .container.mt-4 {
            max-width: 1400px;
        }
        
        /* Card shadow enhancement */
        .card.shadow-sm {
            border-radius: 12px;
            overflow: hidden;
        }
        
        .card-body.p-0 .list-group-item:first-child {
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
        }
        
        .card-body.p-0 .list-group-item:last-child {
            border-bottom-left-radius: 12px;
            border-bottom-right-radius: 12px;
        }
        
        .stats-card .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stats-card .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        /* Pagination Styles */
        .pagination {
            gap: 4px;
        }
        
        .pagination .page-link {
            border: 1px solid #dee2e6;
            color: #0d6efd;
            border-radius: 6px;
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
        }
        
        .pagination .page-link:hover {
            background-color: #e7f1ff;
            border-color: #0d6efd;
            color: #0d6efd;
        }
        
        .pagination .page-item.active .page-link {
            background-color: #0d6efd;
            border-color: #0d6efd;
            color: white;
        }
        
        .pagination .page-item.disabled .page-link {
            color: #6c757d;
            cursor: not-allowed;
            background-color: #f8f9fa;
            border-color: #dee2e6;
        }
    </style>
</head>
<body class="bg-primary-bg" style="background-color: #f0f4f8;">

<?php include 'includes/navbar.php'; ?>

<div class="container mt-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-1"><i class="fas fa-bell me-2 text-primary"></i>Notifications</h1>
            <p class="text-muted mb-0">Stay updated with project alerts and system notifications</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary btn-sm" onclick="refreshNotifications()">
                <i class="fas fa-sync-alt me-1"></i> Refresh
            </button>
            <button class="btn btn-outline-danger btn-sm" onclick="clearAllRead()">
                <i class="fas fa-trash me-1"></i> Clear Read
            </button>
            <button class="btn btn-primary btn-sm" onclick="markAllRead()">
                <i class="fas fa-check-double me-1"></i> Mark All Read
            </button>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stats-card shadow-sm" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="stat-number" id="total-notifications">0</div>
                        <div class="stat-label">Total Notifications</div>
                    </div>
                    <i class="fas fa-inbox fa-2x opacity-25"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card shadow-sm" style="background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="stat-number" id="unread-count">0</div>
                        <div class="stat-label">Unread</div>
                    </div>
                    <i class="fas fa-envelope fa-2x opacity-25"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card shadow-sm" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="stat-number" id="read-count">0</div>
                        <div class="stat-label">Read</div>
                    </div>
                    <i class="fas fa-envelope-open fa-2x opacity-25"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card shadow-sm" style="background: linear-gradient(135deg, #172554 0%, #1e3a8a 100%);">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="stat-number" id="system-count">0</div>
                        <div class="stat-label">System</div>
                    </div>
                    <i class="fas fa-cog fa-2x opacity-25"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Buttons -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="filter-buttons btn-group shadow-sm" role="group" style="border-radius: 8px; overflow: hidden;">
                <button type="button" class="btn btn-outline-primary active" data-filter="all" onclick="filterNotifications('all')">
                    <i class="fas fa-list me-1"></i> All
                </button>
                <button type="button" class="btn btn-outline-primary" data-filter="unread" onclick="filterNotifications('unread')">
                    <i class="fas fa-envelope me-1"></i> Unread
                </button>
                <button type="button" class="btn btn-outline-primary" data-filter="read" onclick="filterNotifications('read')">
                    <i class="fas fa-envelope-open me-1"></i> Read
                </button>
                <button type="button" class="btn btn-outline-primary" data-filter="system" onclick="filterNotifications('system')">
                    <i class="fas fa-cog me-1"></i> System
                </button>
            </div>
        </div>
    </div>

    <!-- Notifications List -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div id="notifications-container" class="list-group list-group-flush">
                        <!-- Loading State -->
                        <div id="loading-state" class="empty-state">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <h5 class="mt-3">Loading notifications...</h5>
                            <p class="text-muted">Please wait while we fetch your notifications</p>
                        </div>
                        
                        <!-- Empty State -->
                        <div id="empty-state" class="empty-state" style="display: none;">
                            <i class="fas fa-bell-slash text-muted"></i>
                            <h5>No notifications found</h5>
                            <p class="text-muted">You're all caught up! Check back later for new updates.</p>
                        </div>
                        
                        <!-- Notifications will be inserted here -->
                        <div id="notifications-list"></div>
                    </div>
                </div>
                
                <!-- Pagination -->
                <div class="card-footer bg-light d-flex justify-content-between align-items-center" style="border-top: 1px solid #dee2e6;">
                    <small id="pagination-info" class="text-muted">Showing 0-0 of 0</small>
                    <nav aria-label="Page navigation">
                        <ul class="pagination mb-0 pagination-sm">
                            <li class="page-item" id="prev-page-item">
                                <button class="page-link" onclick="previousPage()" id="prev-page">Previous</button>
                            </li>
                            <li class="page-item active" id="page-1-item">
                                <button class="page-link" onclick="goToPage(1)">1</button>
                            </li>
                            <li class="page-item" id="next-page-item">
                                <button class="page-link" onclick="nextPage()" id="next-page">Next</button>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toast-container" class="toast-container"></div>

<?php include 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/main.js"></script>

<script>
// Set BASE_URL for API calls
window.BASE_URL = '<?php echo $BASE_URL ?? ""; ?>';

let currentFilter = 'all';
let allNotifications = [];

// Pagination variables
const itemsPerPage = 10;
let currentPage = 1;
let filteredNotifications = [];
let totalPages = 1;

// Initialize page
document.addEventListener('DOMContentLoaded', async function() {
    console.log('Notifications page loaded');
    
    loadAllNotifications();
    
    // Auto-refresh every 30 seconds (just reloads from DB, doesn't create new ones)
    setInterval(loadAllNotifications, 30000);
});

// Load all notifications
async function loadAllNotifications() {
    showLoading();
    
    try {
        const response = await fetch(window.BASE_URL + '/api/notifications_api.php');
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.error) {
            throw new Error(data.error);
        }
        
        allNotifications = data.notifications || [];
        const budgetNotifs = allNotifications.filter(n => n.notification_type === 'budget_approaching' || n.notification_type === 'budget_exceeded');
        const deadlineNotifs = allNotifications.filter(n => n.notification_type && n.notification_type.includes('due_date'));
        const unreadNotifs = allNotifications.filter(n => !n.is_read);
        console.log('✓ Loaded', allNotifications.length, 'total notifications');
        console.log('  - Budget:', budgetNotifs.length, 'notifications');
        console.log('  - Deadline:', deadlineNotifs.length, 'notifications');
        console.log('  - Unread:', unreadNotifs.length, 'notifications');
        console.log('  All notifications:', allNotifications.map(n => ({type: n.notification_type, id: n.notification_id})));
        updateStats();
        renderNotifications();
        // Update header badge after loading
        if (typeof updateHeaderNotificationBadge === 'function') {
            updateHeaderNotificationBadge();
        }
        
    } catch (error) {
        console.error('Error loading notifications:', error);
        showError('Failed to load notifications');
        showEmptyState();
    }
}

// Update statistics
function updateStats() {
    const total = allNotifications.length;
    const unread = allNotifications.filter(n => !n.is_read).length;
    const read = allNotifications.filter(n => n.is_read).length;
    const system = allNotifications.filter(n => 
        n.notification_type && (
            n.notification_type === 'system' ||
            n.notification_type.includes('system')
        )
    ).length;
    
    document.getElementById('total-notifications').textContent = total;
    document.getElementById('unread-count').textContent = unread;
    document.getElementById('read-count').textContent = read;
    document.getElementById('system-count').textContent = system;
    
    console.log(`Stats updated: Total=${total}, Unread=${unread}, Read=${read}, System=${system}`);
}

// Filter notifications
function filterNotifications(filter) {
    currentFilter = filter;
    currentPage = 1; // Reset to first page when filter changes
    
    // Update button states
    document.querySelectorAll('.filter-buttons .btn').forEach(btn => {
        btn.classList.remove('active');
        if (btn.getAttribute('data-filter') === filter) {
            btn.classList.add('active');
        }
    });
    
    renderNotifications();
}

// Render notifications with pagination
function renderNotifications() {
    const container = document.getElementById('notifications-list');
    const emptyState = document.getElementById('empty-state');
    const loadingState = document.getElementById('loading-state');
    
    // Hide loading state
    loadingState.style.display = 'none';
    
    // Filter notifications based on current filter
    filteredNotifications = allNotifications;
    
    switch(currentFilter) {
        case 'unread':
            filteredNotifications = allNotifications.filter(n => !n.is_read);
            break;
        case 'read':
            filteredNotifications = allNotifications.filter(n => n.is_read);
            break;
        case 'deadline':
            filteredNotifications = allNotifications.filter(n => 
                n.notification_type && (
                    n.notification_type.includes('due_date') || 
                    n.notification_type.includes('overdue') ||
                    n.notification_type.includes('near_deadline')
                )
            );
            break;
        case 'budget':
            filteredNotifications = allNotifications.filter(n => 
                n.notification_type && (
                    n.notification_type === 'budget_approaching' ||
                    n.notification_type === 'budget_exceeded'
                )
            );
            break;
        case 'system':
            filteredNotifications = allNotifications.filter(n => 
                n.notification_type && (
                    n.notification_type.includes('alert') ||
                    n.notification_type === 'balancing_recommendation' ||
                    n.notification_type === 'over_utilization' ||
                    n.notification_type === 'under_utilization'
                )
            );
            break;
    }
    
    // Sort: unread first, then by date (newest first)
    filteredNotifications.sort((a, b) => {
        if (a.is_read !== b.is_read) {
            return a.is_read ? 1 : -1;
        }
        return new Date(b.created_at) - new Date(a.created_at);
    });
    
    // Calculate pagination
    totalPages = Math.ceil(filteredNotifications.length / itemsPerPage);
    if (totalPages === 0) totalPages = 1;
    if (currentPage > totalPages) currentPage = totalPages;
    
    // Show empty state if no notifications
    if (filteredNotifications.length === 0) {
        emptyState.style.display = 'block';
        container.innerHTML = '';
        updatePagination();
        return;
    }
    
    emptyState.style.display = 'none';
    
    // Get notifications for current page
    const startIdx = (currentPage - 1) * itemsPerPage;
    const endIdx = startIdx + itemsPerPage;
    const pageNotifications = filteredNotifications.slice(startIdx, endIdx);
    
    // Render notifications
    container.innerHTML = pageNotifications.map(notification => createNotificationCard(notification)).join('');
    
    // Update pagination UI
    updatePagination();
}

// Update pagination UI
function updatePagination() {
    const paginationInfo = document.getElementById('pagination-info');
    const prevPageItem = document.getElementById('prev-page-item');
    const nextPageItem = document.getElementById('next-page-item');
    const pageNumbersContainer = document.querySelector('.pagination');
    
    // Update info text
    if (filteredNotifications.length === 0) {
        paginationInfo.textContent = 'Showing 0-0 of 0';
    } else {
        const startIdx = (currentPage - 1) * itemsPerPage + 1;
        const endIdx = Math.min(currentPage * itemsPerPage, filteredNotifications.length);
        paginationInfo.textContent = `Showing ${startIdx}-${endIdx} of ${filteredNotifications.length}`;
    }
    
    // Update Previous button
    prevPageItem.classList.toggle('disabled', currentPage === 1);
    
    // Generate page buttons
    let pageButtonsHtml = '';
    const maxVisiblePages = 5;
    let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
    let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
    
    if (endPage - startPage < maxVisiblePages - 1) {
        startPage = Math.max(1, endPage - maxVisiblePages + 1);
    }
    
    // First page button
    if (startPage > 1) {
        pageButtonsHtml += `<li class="page-item"><button class="page-link" onclick="goToPage(1)">1</button></li>`;
        if (startPage > 2) {
            pageButtonsHtml += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
    }
    
    // Page number buttons
    for (let i = startPage; i <= endPage; i++) {
        const isActive = i === currentPage ? 'active' : '';
        pageButtonsHtml += `<li class="page-item ${isActive}"><button class="page-link" onclick="goToPage(${i})">${i}</button></li>`;
    }
    
    // Last page button
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            pageButtonsHtml += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
        pageButtonsHtml += `<li class="page-item"><button class="page-link" onclick="goToPage(${totalPages})">${totalPages}</button></li>`;
    }
    
    // Replace page buttons (between prev and next)
    const firstLi = pageNumbersContainer.querySelector('li:first-child');
    const lastLi = pageNumbersContainer.querySelector('li:last-child');
    const existingPages = pageNumbersContainer.querySelectorAll('li:not(:first-child):not(:last-child)');
    existingPages.forEach(li => li.remove());
    
    // Insert new page buttons before next button
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = pageButtonsHtml;
    while (tempDiv.firstChild) {
        lastLi.parentNode.insertBefore(tempDiv.firstChild, lastLi);
    }
    
    // Update Next button
    nextPageItem.classList.toggle('disabled', currentPage === totalPages);
}

// Pagination functions
function nextPage() {
    if (currentPage < totalPages) {
        currentPage++;
        renderNotifications();
        window.scrollTo(0, 0);
    }
}

function previousPage() {
    if (currentPage > 1) {
        currentPage--;
        renderNotifications();
        window.scrollTo(0, 0);
    }
}

function goToPage(page) {
    if (page >= 1 && page <= totalPages) {
        currentPage = page;
        renderNotifications();
        window.scrollTo(0, 0);
    }
}

// Create notification card HTML
function createNotificationCard(notification) {
    const isUnread = !notification.is_read;
    const typeClass = getNotificationTypeClass(notification.notification_type);
    const icon = getNotificationTypeIcon(notification.notification_type);
    const timeAgo = getTimeAgo(notification.created_at);
    const badgeClass = notification.badge_class || typeClass;
    
    // Determine type label
    let typeLabel = '';
    let typeColor = '';
    
    if (notification.notification_type === 'project_due_date') {
        typeLabel = 'Project Due Today';
        typeColor = 'bg-danger';
    } else if (notification.notification_type === 'project_overdue') {
        typeLabel = 'Project Overdue';
        typeColor = 'bg-danger';
    } else if (notification.notification_type === 'project_near_deadline') {
        typeLabel = 'Project Near Deadline';
        typeColor = 'bg-warning';
    } else if (notification.notification_type === 'task_due_date') {
        typeLabel = 'Task Due Today';
        typeColor = 'bg-danger';
    } else if (notification.notification_type === 'task_overdue') {
        typeLabel = 'Task Overdue';
        typeColor = 'bg-danger';
    } else if (notification.notification_type === 'task_near_deadline') {
        typeLabel = 'Task Near Deadline';
        typeColor = 'bg-warning';
    } else if (notification.notification_type === 'subtask_due_date') {
        typeLabel = 'Subtask Due Today';
        typeColor = 'bg-danger';
    } else if (notification.notification_type === 'subtask_overdue') {
        typeLabel = 'Subtask Overdue';
        typeColor = 'bg-danger';
    } else if (notification.notification_type === 'subtask_near_deadline') {
        typeLabel = 'Subtask Near Deadline';
        typeColor = 'bg-warning';
    } else if (notification.notification_type === 'over_utilization') {
        typeLabel = 'Over Utilization';
        typeColor = 'bg-danger';
    } else if (notification.notification_type === 'under_utilization') {
        typeLabel = 'Under Utilization';
        typeColor = 'bg-info';
    } else if (notification.notification_type === 'balancing_recommendation') {
        typeLabel = 'Balancing Recommendation';
        typeColor = 'bg-primary';
    } else if (notification.notification_type === 'budget_approaching') {
        typeLabel = 'Budget Alert';
        typeColor = 'bg-warning';
    } else if (notification.notification_type === 'budget_exceeded') {
        typeLabel = 'Budget Exceeded';
        typeColor = 'bg-danger';
    } else if (notification.notification_type === 'system_alert') {
        typeLabel = 'System Alert';
        typeColor = 'bg-secondary';
    }
    
    const projectName = notification.project_name ? `<strong>${notification.project_name}</strong> - ` : '';
    const resourceName = notification.resource_name ? ` (${notification.resource_name})` : '';
    
    return `
        <div class="list-group-item notification-card ${isUnread ? 'unread' : ''} border-start-0 border-end-0 py-3">
            <div class="d-flex align-items-start gap-3">
                <!-- Icon -->
                <div class="notification-icon shadow-sm" style="background-color: ${getBadgeColor(badgeClass)}; color: white;">
                    ${icon}
                </div>
                
                <!-- Content -->
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <span class="badge ${typeColor} notification-badge me-2 rounded-pill">${typeLabel}</span>
                            ${isUnread ? '<span class="badge bg-primary notification-badge rounded-pill">New</span>' : ''}
                        </div>
                        <span class="notification-time"><i class="far fa-clock me-1"></i>${timeAgo}</span>
                    </div>
                    
                    <div class="notification-message mb-2 fw-medium">
                        ${projectName}${notification.message}${resourceName}
                    </div>
                    
                    <div class="d-flex gap-2 notification-actions">
                        ${isUnread ? `
                            <button class="btn btn-sm btn-outline-primary rounded-pill" onclick="markAsRead(${notification.notification_id})">
                                <i class="fas fa-check me-1"></i> Mark as Read
                            </button>
                        ` : ''}
                        <button class="btn btn-sm btn-outline-danger rounded-pill" onclick="deleteNotification(${notification.notification_id})">
                            <i class="fas fa-trash me-1"></i> Delete
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
}

// Get notification type class
function getNotificationTypeClass(type) {
    switch (type) {
        case 'over_utilization': return 'bg-danger';
        case 'under_utilization': return 'bg-info';
        case 'balancing_recommendation': return 'bg-primary';
        case 'system_alert': return 'bg-secondary';
        case 'project_due_date':
        case 'task_due_date':
        case 'subtask_due_date': return 'bg-danger';
        case 'project_near_deadline':
        case 'task_near_deadline':
        case 'subtask_near_deadline': return 'bg-warning';
        default: return 'bg-primary';
    }
}

// Get notification type icon
function getNotificationTypeIcon(type) {
    switch (type) {
        case 'over_utilization': return '⚠️';
        case 'under_utilization': return '📉';
        case 'balancing_recommendation': return '⚖️';
        case 'system_alert': return '🔔';
        case 'project_due_date':
        case 'task_due_date':
        case 'subtask_due_date': return '🔴';
        case 'project_near_deadline':
        case 'task_near_deadline':
        case 'subtask_near_deadline': return '🟡';
        default: return '📋';
    }
}

// Get badge color
function getBadgeColor(badgeClass) {
    const colorMap = {
        'bg-danger': '#dc3545',
        'bg-warning': '#ffc107',
        'bg-info': '#17a2b8',
        'bg-primary': '#0d6efd',
        'bg-secondary': '#6c757d'
    };
    return colorMap[badgeClass] || '#0d6efd';
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
    if (diffInDays < 7) return `${diffInDays}d ago`;
    
    return notificationDate.toLocaleDateString();
}

// Mark notification as read
async function markAsRead(notificationId) {
    // Use styled confirmation toast instead of browser confirm
    showConfirmToast(
        'Are you sure you want to mark this notification as read?',
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
                
                // Update local notification
                const notif = allNotifications.find(n => n.notification_id === notificationId);
                if (notif) {
                    notif.is_read = true;
                }
                
                showSuccess('Notification marked as read');
                updateStats();
                renderNotifications();
                
                // Update header badge immediately based on local data
                const badge = document.getElementById('header-notification-badge');
                if (badge) {
                    const unreadCount = allNotifications.filter(n => !n.is_read).length;
                    if (unreadCount > 0) {
                        badge.textContent = unreadCount;
                        badge.style.display = 'inline-block';
                    } else {
                        badge.style.display = 'none';
                    }
                }
                
                // Update navbar notification count badge
                const navbarBadge = document.getElementById('notification-count');
                if (navbarBadge) {
                    const unreadCount = allNotifications.filter(n => !n.is_read).length;
                    if (unreadCount > 0) {
                        navbarBadge.textContent = unreadCount;
                        navbarBadge.style.display = 'inline-block';
                    } else {
                        navbarBadge.style.display = 'none';
                    }
                }
                
                // Also call async version for server sync
                if (typeof updateHeaderNotificationBadge === 'function') {
                    updateHeaderNotificationBadge();
                }
                
            } catch (error) {
                console.error('Error marking as read:', error);
                showError('Failed to mark as read');
            }
        },
        null,
        'Mark as Read',
        'Cancel'
    );
}

// Mark all notifications as read
async function markAllRead() {
    // Use styled confirmation toast instead of browser confirm
    showConfirmToast(
        'Mark all notifications as read?',
        'Mark All as Read',
        async () => {
            try {
                // Use URL-encoded format instead of FormData for better compatibility
                const params = new URLSearchParams();
                params.append('mark_all_read', 'true');
                
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
                
                // Update all local notifications to be read
                allNotifications.forEach(n => n.is_read = true);
                
                showSuccess('All notifications marked as read');
                updateStats();
                renderNotifications();
                
                // Update header badge immediately - hide it since all are read
                const badge = document.getElementById('header-notification-badge');
                if (badge) {
                    badge.textContent = '0';
                    badge.style.display = 'none';
                }
                
                // Update navbar notification count badge
                const navbarBadge = document.getElementById('notification-count');
                if (navbarBadge) {
                    navbarBadge.textContent = '0';
                    navbarBadge.style.display = 'none';
                }
                
                // Also hide badge on project details page if it exists
                const projectDetailsBadge = document.querySelector('project-details')?.querySelector('#header-notification-badge');
                if (projectDetailsBadge) {
                    projectDetailsBadge.style.display = 'none';
                }
                
                // Also call async version for server sync
                if (typeof updateHeaderNotificationBadge === 'function') {
                    updateHeaderNotificationBadge();
                }
                
                // After a short delay, reload to get fresh data from server
                setTimeout(() => loadAllNotifications(), 1000);
                
            } catch (error) {
                console.error('Error marking all as read:', error);
                showError('Failed to mark all as read');
            }
        },
        null,
        'Mark All as Read',
        'Cancel'
    );
}

// Delete notification
async function deleteNotification(notificationId) {
    // Use styled confirmation toast instead of browser confirm
    showConfirmToast(
        'Delete this notification?',
        'Delete Notification',
        async () => {
            try {
                // Use URL-encoded format instead of FormData for better compatibility
                const params = new URLSearchParams();
                params.append('notification_id', notificationId);
                
                const response = await fetch(window.BASE_URL + '/api/notifications_api.php', {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: params
                });
                
                const data = await response.json();
                
                if (data.error) {
                    throw new Error(data.error);
                }
                
                showSuccess('Notification deleted');
                
                // Remove from local array and update badges immediately
                const notifIndex = allNotifications.findIndex(n => n.notification_id === notificationId);
                if (notifIndex > -1) {
                    allNotifications.splice(notifIndex, 1);
                }
                
                // Update header badge immediately
                const badge = document.getElementById('header-notification-badge');
                if (badge) {
                    const unreadCount = allNotifications.filter(n => !n.is_read).length;
                    if (unreadCount > 0) {
                        badge.textContent = unreadCount;
                        badge.style.display = 'inline-block';
                    } else {
                        badge.style.display = 'none';
                    }
                }
                
                // Update navbar notification count badge
                const navbarBadge = document.getElementById('notification-count');
                if (navbarBadge) {
                    const unreadCount = allNotifications.filter(n => !n.is_read).length;
                    if (unreadCount > 0) {
                        navbarBadge.textContent = unreadCount;
                        navbarBadge.style.display = 'inline-block';
                    } else {
                        navbarBadge.style.display = 'none';
                    }
                }
                
                loadAllNotifications();
                
            } catch (error) {
                console.error('Error deleting notification:', error);
                showError('Failed to delete notification');
            }
        },
        null,
        'Delete',
        'Cancel'
    );
}

// Clear all read notifications
async function clearAllRead() {
    // Use styled confirmation toast instead of browser confirm
    showConfirmToast(
        'Clear all read notifications? This cannot be undone.',
        'Clear Read Notifications',
        async () => {
            try {
                // Use URL-encoded format instead of FormData for better compatibility
                const params = new URLSearchParams();
                params.append('clear_all', 'true');
                
                const response = await fetch(window.BASE_URL + '/api/notifications_api.php', {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: params
                });
                
                const data = await response.json();
                
                if (data.error) {
                    throw new Error(data.error);
                }
                
                showSuccess('All read notifications cleared');
                loadAllNotifications();
                
            } catch (error) {
                console.error('Error clearing read notifications:', error);
                showError('Failed to clear notifications');
            }
        },
        null,
        'Clear All',
        'Cancel'
    );
}

// Refresh notifications
function refreshNotifications() {
    loadAllNotifications();
    showSuccess('Notifications refreshed');
}

// Show loading state
function showLoading() {
    document.getElementById('loading-state').style.display = 'block';
    document.getElementById('empty-state').style.display = 'none';
    document.getElementById('notifications-list').innerHTML = '';
}

// Show empty state
function showEmptyState() {
    document.getElementById('loading-state').style.display = 'none';
    document.getElementById('empty-state').style.display = 'block';
    document.getElementById('notifications-list').innerHTML = '';
}

// Show success message
function showSuccess(message) {
    showToast(message, 'success');
}

// Show error message
function showError(message) {
    showToast(message, 'error');
}

// Show warning message
function showWarning(message) {
    showToast(message, 'warning');
}

// Show info message
function showInfo(message) {
    showToast(message, 'info');
}

// Show toast notification
function showToast(message, type = 'info') {
    const container = document.getElementById('toast-container');
    if (!container) return;
    
    const toast = document.createElement('div');
    toast.className = `toast-notification toast-${type}`;
    
    const icons = {
        success: '<i class="fas fa-check-circle"></i>',
        error: '<i class="fas fa-exclamation-circle"></i>',
        warning: '<i class="fas fa-exclamation-triangle"></i>',
        info: '<i class="fas fa-info-circle"></i>'
    };
    
    const titles = {
        success: 'Success',
        error: 'Error',
        warning: 'Warning',
        info: 'Information'
    };
    
    const icon = icons[type] || icons.info;
    const title = titles[type] || titles.info;
    
    toast.innerHTML = `
        <div class="toast-icon">${icon}</div>
        <div class="toast-content">
            <div class="toast-title">${title}</div>
            <div class="toast-message">${message}</div>
        </div>
        <button type="button" class="toast-close" onclick="this.closest('.toast-notification').remove()">
            <i class="fas fa-times"></i>
        </button>
        <div class="toast-progress"><div class="toast-progress-bar"></div></div>
    `;
    
    container.appendChild(toast);
    
    // Trigger animation
    setTimeout(() => {
        toast.classList.add('show');
    }, 10);
    
    // Auto remove after 3 seconds
    setTimeout(() => {
        toast.classList.add('toast-exiting');
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 400);
    }, 3000);
}

// Show confirmation toast (for deletions and critical actions)
function showConfirmToast(message, title = 'Confirm Action', onConfirm, onCancel = null, confirmText = 'Delete', cancelText = 'Cancel') {
    let container = document.getElementById('toast-container');
    if (!container) {
        // Create container if it doesn't exist
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'toast-container';
        document.body.appendChild(container);
    }
    
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
    
    // Trigger animation
    setTimeout(() => {
        toast.classList.add('show');
    }, 10);
    
    return toast;
}

// Handle toast confirmation
function handleToastConfirm(button) {
    const toast = button.closest('.toast-notification');
    if (toast && toast._onConfirm) {
        toast._onConfirm();
    }
    removeToast(toast);
}

// Handle toast cancellation
function handleToastCancel(button) {
    const toast = button.closest('.toast-notification');
    if (toast && toast._onCancel) {
        toast._onCancel();
    }
    removeToast(toast);
}

// Remove toast
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
    }, 400);
}
</script>

</body>
</html>
