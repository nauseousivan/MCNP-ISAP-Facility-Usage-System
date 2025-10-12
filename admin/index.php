<?php
session_start();
require_once '../config.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'Admin') {
    header("Location: ../index.php");
    exit();
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Get statistics
$stats = [];

// Total requests
$result = $conn->query("SELECT COUNT(*) as count FROM facility_requests");
$stats['total'] = $result->fetch_assoc()['count'];

// Pending requests
$result = $conn->query("SELECT COUNT(*) as count FROM facility_requests WHERE status = 'pending'");
$stats['pending'] = $result->fetch_assoc()['count'];

// Approved requests
$result = $conn->query("SELECT COUNT(*) as count FROM facility_requests WHERE status = 'approved'");
$stats['approved'] = $result->fetch_assoc()['count'];

// Total users
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE user_type != 'Admin'");
$stats['users'] = $result->fetch_assoc()['count'];

// Recent requests
$recent_requests = $conn->query("
    SELECT fr.*, u.name as user_name 
    FROM facility_requests fr 
    JOIN users u ON fr.user_id = u.id 
    ORDER BY fr.created_at DESC 
    LIMIT 5
");

$recent_activities_display = $conn->query("
    SELECT 
        'request_submitted' as type,
        fr.id,
        u.name as user_name,
        CONCAT(u.name, ' submitted a ', fr.event_type, ' request') as message,
        fr.created_at as timestamp,
        'info' as activity_type
    FROM facility_requests fr
    JOIN users u ON fr.user_id = u.id
    WHERE fr.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    
    UNION ALL
    
    SELECT 
        'user_registered' as type,
        u.id,
        u.name as user_name,
        CONCAT(u.name, ' registered as ', u.user_type) as message,
        u.created_at as timestamp,
        'success' as activity_type
    FROM users u
    WHERE u.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    AND u.user_type != 'Admin'
    
    UNION ALL
    
    SELECT 
        'request_approved' as type,
        fr.id,
        u.name as user_name,
        CONCAT('Request ', fr.control_number, ' was approved') as message,
        fr.updated_at as timestamp,
        'success' as activity_type
    FROM facility_requests fr
    JOIN users u ON fr.user_id = u.id
    WHERE fr.status = 'approved'
    AND fr.updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    
    UNION ALL
    
    SELECT 
        'request_rejected' as type,
        fr.id,
        u.name as user_name,
        CONCAT('Request ', fr.control_number, ' was rejected') as message,
        fr.updated_at as timestamp,
        'danger' as activity_type
    FROM facility_requests fr
    JOIN users u ON fr.user_id = u.id
    WHERE fr.status = 'rejected'
    AND fr.updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    
    ORDER BY timestamp DESC
    LIMIT 8
");

// Get theme preference from cookie
$theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'light';
?>
<!DOCTYPE html>
<html lang="en" class="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Facility Management</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --background: #ffffff;
            --foreground: #0a0a0a;
            --card: #ffffff;
            --card-foreground: #0a0a0a;
            --muted: #f5f5f5;
            --muted-foreground: #737373;
            --border: #e5e5e5;
            --primary: #0a0a0a;
            --primary-foreground: #fafafa;
            --secondary: #f5f5f5;
            --secondary-foreground: #0a0a0a;
            --accent: #f5f5f5;
            --accent-foreground: #0a0a0a;
            --success: #22c55e;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --sidebar: #fafafa;
            --sidebar-foreground: #0a0a0a;
            --sidebar-border: #e5e5e5;
        }
        
        .dark {
            --background: #0a0a0a;
            --foreground: #fafafa;
            --card: #171717;
            --card-foreground: #fafafa;
            --muted: #262626;
            --muted-foreground: #a3a3a3;
            --border: #262626;
            --primary: #fafafa;
            --primary-foreground: #0a0a0a;
            --secondary: #262626;
            --secondary-foreground: #fafafa;
            --accent: #262626;
            --accent-foreground: #fafafa;
            --sidebar: #171717;
            --sidebar-foreground: #fafafa;
            --sidebar-border: #262626;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: var(--background);
            color: var(--foreground);
            display: flex;
            min-height: 100vh;
            transition: background-color 0.3s, color 0.3s;
        }
        
        /* Sidebar */
        .sidebar {
            width: 260px;
            background: var(--sidebar);
            border-right: 1px solid var(--sidebar-border);
            padding: 24px 16px;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: background-color 0.3s, border-color 0.3s;
        }
        
        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 12px;
            margin-bottom: 32px;
            font-size: 18px;
            font-weight: 700;
            color: var(--sidebar-foreground);
        }
        
        .sidebar-brand img {
            height: 32px;
            width: auto;
        }
        
        .sidebar-menu {
            list-style: none;
        }
        
        .sidebar-menu li {
            margin-bottom: 4px;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            color: var(--muted-foreground);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.2s;
            font-size: 14px;
            font-weight: 500;
        }
        
        .sidebar-menu a:hover {
            background: var(--accent);
            color: var(--accent-foreground);
        }
        
        .sidebar-menu a.active {
            background: var(--primary);
            color: var(--primary-foreground);
        }
        
        .sidebar-menu svg {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }
        
        .sidebar-divider {
            margin: 24px 0;
            padding-top: 24px;
            border-top: 1px solid var(--border);
        }
        
        /* Main Content */
        .main-content {
            margin-left: 260px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        /* Enhanced top nav with notifications */
        .top-nav {
            height: 64px;
            border-bottom: 1px solid var(--border);
            padding: 0 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--background);
            position: sticky;
            top: 0;
            z-index: 10;
            transition: background-color 0.3s, border-color 0.3s;
        }
        
        .top-nav h1 {
            font-size: 20px;
            font-weight: 600;
        }
        
        .top-nav-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        /* Notification button styling */
        .notification-btn,
        .theme-toggle {
            position: relative;
            padding: 10px;
            border-radius: 8px;
            border: none;
            background: transparent;
            cursor: pointer;
            color: var(--foreground);
            transition: all 0.2s;
        }
        
        .notification-btn:hover,
        .theme-toggle:hover {
            background: var(--muted);
        }
        
        .notification-btn svg,
        .theme-toggle svg {
            width: 20px;
            height: 20px;
            display: block;
        }
        
        /* Notification badge */
        .notification-badge {
            position: absolute;
            top: 6px;
            right: 6px;
            width: 8px;
            height: 8px;
            background: var(--danger);
            border-radius: 50%;
            border: 2px solid var(--background);
        }
        
        /* Notification dropdown */
        .notification-dropdown {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            width: 360px;
            max-height: 480px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
            overflow: hidden;
            display: none;
            z-index: 100;
        }
        
        .notification-dropdown.show {
            display: block;
            animation: slideDown 0.2s ease-out;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .notification-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            font-weight: 600;
            font-size: 14px;
        }
        
        .notification-list {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .notification-item {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            transition: background 0.2s;
            cursor: pointer;
        }
        
        .notification-item:hover {
            background: var(--muted);
        }
        
        .notification-item:last-child {
            border-bottom: none;
        }
        
        .notification-message {
            font-size: 14px;
            color: var(--foreground);
            margin-bottom: 4px;
            font-weight: 500;
        }
        
        .notification-time {
            font-size: 12px;
            color: var(--muted-foreground);
        }
        
        .notification-empty {
            padding: 40px 20px;
            text-align: center;
            color: var(--muted-foreground);
            font-size: 14px;
        }
        
        /* Content Area */
        .content {
            flex: 1;
            padding: 32px;
            overflow-y: auto;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }
        
        .stat-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 24px;
            transition: all 0.3s;
            cursor: pointer;
            text-decoration: none;
            display: block;
            color: inherit;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
        }
        
        .stat-label {
            font-size: 14px;
            color: var(--muted-foreground);
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .stat-value {
            font-size: 36px;
            font-weight: 700;
            color: var(--foreground);
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
        }
        
        .stat-icon.primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .stat-icon.warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
        
        .stat-icon.success {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }
        
        .stat-icon.info {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            color: white;
        }
        
        /* Card */
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            transition: background-color 0.3s, border-color 0.3s;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border);
        }
        
        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--foreground);
        }
        
        .card-link {
            font-size: 14px;
            color: var(--muted-foreground);
            text-decoration: none;
            transition: color 0.2s;
            font-weight: 500;
        }
        
        .card-link:hover {
            color: var(--foreground);
        }
        
        /* Table */
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table thead th {
            text-align: left;
            padding: 12px;
            font-size: 12px;
            font-weight: 600;
            color: var(--muted-foreground);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border);
        }
        
        .table tbody td {
            padding: 16px 12px;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
        }
        
        .table tbody tr {
            transition: background 0.2s;
            cursor: pointer;
        }
        
        .table tbody tr:hover {
            background: var(--muted);
        }
        
        /* Badge */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            text-transform: capitalize;
            letter-spacing: 0.3px;
        }
        
        .badge.pending {
            background: rgba(245, 158, 11, 0.15);
            color: var(--warning);
        }
        
        .badge.approved {
            background: rgba(34, 197, 94, 0.15);
            color: var(--success);
        }
        
        .badge.rejected {
            background: rgba(239, 68, 68, 0.15);
            color: var(--danger);
        }
        
        /* Recent Activities section styles */
        .activity-item {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            padding: 16px;
            border-radius: 8px;
            transition: background 0.2s;
        }
        
        .activity-item:hover {
            background: var(--muted);
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .activity-icon.info {
            background: rgba(59, 130, 246, 0.15);
            color: var(--info);
        }
        
        .activity-icon.success {
            background: rgba(34, 197, 94, 0.15);
            color: var(--success);
        }
        
        .activity-icon.danger {
            background: rgba(239, 68, 68, 0.15);
            color: var(--danger);
        }
        
        .activity-icon svg {
            width: 20px;
            height: 20px;
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-message {
            font-size: 14px;
            color: var(--foreground);
            margin-bottom: 4px;
            font-weight: 500;
        }
        
        .activity-time {
            font-size: 12px;
            color: var(--muted-foreground);
        }
        
        .activities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 0;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                z-index: 1000;
            }
            
            .sidebar.mobile-open {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .content {
                padding: 16px;
            }
            
            .top-nav {
                padding: 0 16px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .notification-dropdown {
                width: calc(100vw - 32px);
                right: -16px;
            }
            
            .mobile-menu-btn {
                display: block;
                padding: 8px;
                background: transparent;
                border: none;
                color: var(--foreground);
                cursor: pointer;
            }
        }

        /* Mobile menu button */
        .mobile-menu-btn {
            display: none;
            padding: 8px;
            background: transparent;
            border: none;
            color: var(--foreground);
            cursor: pointer;
            border-radius: 8px;
            transition: background-color 0.2s;
        }

        .mobile-menu-btn:hover {
            background: var(--muted);
        }
        /* Enhanced Mobile Responsiveness */
@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
        width: 280px;
        z-index: 1000;
        transition: transform 0.3s ease;
    }
    
    .sidebar.mobile-open {
        transform: translateX(0);
    }
    
    .main-content {
        margin-left: 0;
    }
    
    .content {
        padding: 16px;
    }
    
    .top-nav {
        padding: 0 16px;
        height: 60px;
    }
    
    .top-nav h1 {
        font-size: 18px;
    }
    
    /* Mobile menu button */
    .mobile-menu-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 8px;
        background: transparent;
        border: none;
        color: var(--foreground);
        cursor: pointer;
        border-radius: 8px;
        transition: background-color 0.2s;
    }
    
    .mobile-menu-btn:hover {
        background: var(--muted);
    }
    
    .mobile-menu-btn svg {
        width: 20px;
        height: 20px;
    }
    
    /* Overlay for mobile sidebar */
    .sidebar::before {
        content: '';
        position: fixed;
        top: 0;
        left: 280px;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: -1;
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    
    .sidebar.mobile-open::before {
        opacity: 1;
        left: 280px;
    }
}

@media (max-width: 768px) {
            /* Stats Grid - 2x2 layout */
            .stats-grid {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 12px !important;
            }
            
            .stat-card {
                padding: 16px !important;
            }
            
            .stat-icon {
                width: 36px !important;
                height: 36px !important;
                margin-bottom: 12px !important;
            }
            
            .stat-icon svg {
                width: 18px !important;
                height: 18px !important;
            }
            
            .stat-value {
                font-size: 24px !important;
            }
            
            .stat-label {
                font-size: 12px !important;
            }
}
    </style>
</head>
<body>
    <!-- Include Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Nav -->
<nav class="top-nav">
    <div style="display: flex; align-items: center; gap: 16px;">
        <button class="mobile-menu-btn" onclick="toggleSidebar()">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>
        <h1>Dashboard</h1>
    </div>
    <div class="top-nav-actions">
        <!-- Your existing notification and theme buttons stay here -->
        <div style="position: relative;">
            <button class="notification-btn" onclick="toggleNotifications()">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
                <?php if ($recent_activities_display->num_rows > 0): ?>
                    <span class="notification-badge"></span>
                <?php endif; ?>
            </button>
                    
                    <div class="notification-dropdown" id="notificationDropdown">
                        <div class="notification-header">Recent Activities</div>
                        <div class="notification-list">
                            <?php if ($recent_activities_display->num_rows > 0): ?>
                                <?php while ($activity = $recent_activities_display->fetch_assoc()): ?>
                                    <div class="notification-item">
                                        <div class="notification-message"><?php echo htmlspecialchars($activity['message']); ?></div>
                                        <div class="notification-time">
                                            <?php 
                                            $time_diff = time() - strtotime($activity['timestamp']);
                                            if ($time_diff < 3600) {
                                                echo floor($time_diff / 60) . ' minutes ago';
                                            } elseif ($time_diff < 86400) {
                                                echo floor($time_diff / 3600) . ' hours ago';
                                            } else {
                                                echo floor($time_diff / 86400) . ' days ago';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="notification-empty">No recent activities</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                 <button class="theme-toggle" onclick="toggleTheme()">
            <svg class="sun-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
            </svg>
            <svg class="moon-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
            </svg>
        </button>
    </div>
</nav>
        <!-- Content -->
        <main class="content">
            <!-- Stats Grid -->
            <div class="stats-grid">
                <a href="requests.php" class="stat-card">
                    <div class="stat-icon primary">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="24" height="24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <div class="stat-label">Total Requests</div>
                    <div class="stat-value"><?php echo $stats['total']; ?></div>
                </a>
                
                <a href="requests.php?filter=pending" class="stat-card">
                    <div class="stat-icon warning">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="24" height="24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="stat-label">Pending</div>
                    <div class="stat-value"><?php echo $stats['pending']; ?></div>
                </a>
                
                <a href="requests.php?filter=approved" class="stat-card">
                    <div class="stat-icon success">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="24" height="24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="stat-label">Approved</div>
                    <div class="stat-value"><?php echo $stats['approved']; ?></div>
                </a>
                
                <a href="users.php" class="stat-card">
                    <div class="stat-icon info">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="24" height="24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                    </div>
                    <div class="stat-label">Total Users</div>
                    <div class="stat-value"><?php echo $stats['users']; ?></div>
                </a>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                <!-- Recent Requests -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Recent Requests</h2>
                        <a href="requests.php" class="card-link">View all →</a>
                    </div>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Control Number</th>
                                <th>Requestor</th>
                                <th>Department</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($request = $recent_requests->fetch_assoc()): ?>
                                <tr onclick="window.location.href='view_request_admin.php?id=<?php echo $request['id']; ?>'">
                                    <td><strong><?php echo htmlspecialchars($request['control_number']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($request['user_name']); ?></td>
                                    <td><?php echo htmlspecialchars($request['department']); ?></td>
                                    <td>
                                        <span class="badge <?php echo strtolower($request['status']); ?>">
                                            <?php echo ucfirst($request['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($request['created_at'])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Recent Activities -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Recent Activities</h2>
                        <span class="card-link">Last 7 days</span>
                    </div>
                    <div style="max-height: 400px; overflow-y: auto;">
                        <?php 
                        // Reset pointer for activities
                        $recent_activities_display->data_seek(0);
                        if ($recent_activities_display->num_rows > 0): ?>
                            <?php while ($activity = $recent_activities_display->fetch_assoc()): ?>
                                <div class="activity-item">
                                    <div class="activity-icon <?php echo $activity['activity_type']; ?>">
                                        <?php if ($activity['type'] == 'user_registered'): ?>
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                                            </svg>
                                        <?php elseif ($activity['type'] == 'request_submitted'): ?>
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                            </svg>
                                        <?php else: ?>
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                        <?php endif; ?>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-message"><?php echo htmlspecialchars($activity['message']); ?></div>
                                        <div class="activity-time">
                                            <?php 
                                            $time_diff = time() - strtotime($activity['timestamp']);
                                            if ($time_diff < 3600) {
                                                echo floor($time_diff / 60) . ' minutes ago';
                                            } elseif ($time_diff < 86400) {
                                                echo floor($time_diff / 3600) . ' hours ago';
                                            } else {
                                                echo floor($time_diff / 86400) . ' days ago';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div style="text-align: center; padding: 40px; color: var(--muted-foreground);">
                                No recent activities
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Theme toggle
        function toggleTheme() {
            const html = document.documentElement;
            const isDark = html.classList.contains('dark');
            const newTheme = isDark ? 'light' : 'dark';
            
            html.classList.remove(isDark ? 'dark' : 'light');
            html.classList.add(newTheme);
            
            // Save theme preference in cookie
            document.cookie = `theme=${newTheme}; path=/; max-age=31536000`; // 1 year
            
            // Update icons
            updateThemeIcons(newTheme);
        }
        
        function updateThemeIcons(theme) {
            const sunIcon = document.querySelector('.sun-icon');
            const moonIcon = document.querySelector('.moon-icon');
            
            if (theme === 'dark') {
                sunIcon.style.display = 'block';
                moonIcon.style.display = 'none';
            } else {
                sunIcon.style.display = 'none';
                moonIcon.style.display = 'block';
            }
        }
        
        // Initialize theme icons
        document.addEventListener('DOMContentLoaded', function() {
            const currentTheme = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
            updateThemeIcons(currentTheme);
        });
        
        // Notification dropdown
        function toggleNotifications() {
            const dropdown = document.getElementById('notificationDropdown');
            dropdown.classList.toggle('show');
        }
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('notificationDropdown');
            const notificationBtn = document.querySelector('.notification-btn');
            
            if (!notificationBtn.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });
        
        // Mobile sidebar toggle
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('mobile-open');
        }
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.querySelector('.sidebar');
            const mobileBtn = document.querySelector('.mobile-menu-btn');
            
            if (window.innerWidth <= 768 && 
                !sidebar.contains(event.target) && 
                !mobileBtn.contains(event.target) &&
                sidebar.classList.contains('mobile-open')) {
                sidebar.classList.remove('mobile-open');
            }
        });
    </script>
</body>
</html>