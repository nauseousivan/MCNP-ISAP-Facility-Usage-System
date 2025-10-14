<?php
session_start();
require_once '../config.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'Admin') {
    header("Location: ../index.php");
    exit();
}

// Function to create notification
// Function to create notification
function createNotification($conn, $user_id, $title, $message, $request_id = null, $type = null) {
    $sql = "INSERT INTO notifications (user_id, title, message, request_id, notification_type, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issis", $user_id, $title, $message, $request_id, $type);
    return $stmt->execute();
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $request_id = $_POST['request_id'];
    $action = $_POST['action'];
    $admin_notes = $_POST['admin_notes'] ?? '';
    
    $status = ($action === 'approve') ? 'approved' : 'rejected';
    
    // First, get the request details to know which user to notify
    $get_request_sql = "SELECT user_id, control_number, event_type FROM facility_requests WHERE id = ?";
    $get_stmt = $conn->prepare($get_request_sql);
    $get_stmt->bind_param("i", $request_id);
    $get_stmt->execute();
    $request_data = $get_stmt->get_result()->fetch_assoc();
    
    if ($request_data) {
        $user_id = $request_data['user_id'];
        $control_number = $request_data['control_number'];
        $event_type = $request_data['event_type'];
        
        // Update the request status
        $sql = "UPDATE facility_requests SET status = ?, admin_notes = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $status, $admin_notes, $request_id);
        
if ($stmt->execute()) {
    // Create notification for the user
    if ($action === 'approve') {
        $title = "Request Approved";
        $message = "Your facility request #{$control_number} for '{$event_type}' has been approved.";
        $type = 'request_approved';
    } else {
        $title = "Request Rejected";
        $message = "Your facility request #{$control_number} for '{$event_type}' has been rejected." . 
                  ($admin_notes ? " Note: {$admin_notes}" : "");
        $type = 'request_rejected';
    }
    
    createNotification($conn, $user_id, $title, $message, $request_id, $type);
    header("Location: requests.php?success=1");
    exit();
}
    }
    
    header("Location: requests.php?error=1");
    exit();
}

// Get filter
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query
$sql = "SELECT fr.*, u.name as user_name, u.email as user_email 
        FROM facility_requests fr 
        JOIN users u ON fr.user_id = u.id 
        WHERE 1=1";
$params = [];
$types = "";

if ($filter === 'pending') {
    $sql .= " AND fr.status = 'pending'";
} elseif ($filter === 'approved') {
    $sql .= " AND fr.status = 'approved'";
} elseif ($filter === 'rejected') {
    $sql .= " AND fr.status = 'rejected'";
}

if ($search) {
    $sql .= " AND (fr.control_number LIKE ? OR u.name LIKE ? OR fr.department LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

$sql .= " ORDER BY fr.created_at DESC";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $requests = $stmt->get_result();
} else {
    $requests = $conn->query($sql);
}

// Get theme preference
$theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'light';
?>
<!DOCTYPE html>
<html lang="en" class="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Requests - Admin</title>
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
            --sidebar: #fafafa;
            --sidebar-foreground: #0a0a0a;
            --sidebar-border: #e5e5e5;
        }
        
        .dark {
            --background: #0a0a0a;
            --foreground: #fafafa;
            --card: #0a0a0a;
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
            transition: all 0.3s ease;
            z-index: 1000;
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
            transition: margin-left 0.3s ease;
        }
        
        /* Top Nav */
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
        
        .top-nav-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .top-nav h1 {
            font-size: 20px;
            font-weight: 600;
        }
        
        .top-nav-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .theme-toggle {
            padding: 8px;
            border-radius: 8px;
            border: none;
            background: transparent;
            cursor: pointer;
            color: var(--foreground);
            transition: background-color 0.2s;
        }
        
        .theme-toggle:hover {
            background: var(--muted);
        }
        
        .theme-toggle svg {
            width: 20px;
            height: 20px;
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

        .mobile-menu-btn svg {
            width: 20px;
            height: 20px;
        }
        
        /* Content Area */
        .content {
            flex: 1;
            padding: 32px;
            overflow-y: auto;
        }
        
        /* Alert */
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .alert.success {
            background: rgba(34, 197, 94, 0.1);
            color: var(--success);
            border: 1px solid rgba(34, 197, 94, 0.2);
        }
        
        /* Filters */
        .filters {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .filter-btn {
            padding: 8px 16px;
            border: 1px solid var(--border);
            background: var(--background);
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            color: var(--foreground);
            transition: all 0.2s;
            font-size: 14px;
        }
        
        .filter-btn:hover,
        .filter-btn.active {
            background: var(--primary);
            color: var(--primary-foreground);
            border-color: var(--primary);
        }
        
        .search-box {
            flex: 1;
            min-width: 250px;
        }
        
        .search-box input {
            width: 100%;
            padding: 8px 16px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            background: var(--background);
            color: var(--foreground);
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
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .badge.pending {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }
        
        .badge.approved {
            background: rgba(34, 197, 94, 0.1);
            color: var(--success);
        }
        
        .badge.rejected {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }
        
        /* Button */
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            font-size: 13px;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: var(--primary);
            color: var(--primary-foreground);
        }
        
        .btn-primary:hover {
            opacity: 0.9;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--muted-foreground);
        }
        
        .empty-state svg {
            width: 64px;
            height: 64px;
            margin-bottom: 16px;
            opacity: 0.5;
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .filters {
                gap: 10px;
            }
            
            .search-box {
                min-width: 200px;
            }
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
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
            
            .mobile-menu-btn {
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .filters {
                flex-direction: column;
                align-items: stretch;
                gap: 12px;
            }
            
            .search-box {
                width: 100%;
                min-width: auto;
            }
            
            .filter-btn {
                text-align: center;
            }
            
            .table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
            
            .table thead th,
            .table tbody td {
                padding: 10px 8px;
                font-size: 13px;
            }
            
            .card {
                padding: 16px;
            }
        }

        @media (max-width: 480px) {
            .content {
                padding: 12px;
            }
            
            .top-nav {
                padding: 0 12px;
            }
            
            .filters {
                padding: 16px;
            }
            
            .filter-btn {
                padding: 10px 12px;
                font-size: 13px;
            }
            
            .table thead th,
            .table tbody td {
                padding: 8px 6px;
                font-size: 12px;
            }
            
            .btn {
                padding: 6px 12px;
                font-size: 12px;
            }
            
            .badge {
                font-size: 11px;
                padding: 3px 8px;
            }
        }

        /* Sidebar overlay for mobile */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }

        .sidebar-overlay.active {
            display: block;
        }

        body.sidebar-open {
            overflow: hidden;
        }
        .btn-reject {
    background: var(--danger);
    color: white;
}

.btn-reject:hover {
    background: #dc2626;
    opacity: 0.9;
}
    </style>
</head>
<body>
    <!-- Include Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Mobile Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Nav -->
        <nav class="top-nav">
            <div class="top-nav-left">
                <button class="mobile-menu-btn" onclick="toggleSidebar()">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
                <h1>Manage Requests</h1>
            </div>
            <div class="top-nav-actions">
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
            <?php if (isset($_GET['success'])): ?>
                <div class="alert success">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Request updated successfully!
                </div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="filters">
                <a href="?filter=all" class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">All</a>
                <a href="?filter=pending" class="filter-btn <?php echo $filter === 'pending' ? 'active' : ''; ?>">Pending</a>
                <a href="?filter=approved" class="filter-btn <?php echo $filter === 'approved' ? 'active' : ''; ?>">Approved</a>
                <a href="?filter=rejected" class="filter-btn <?php echo $filter === 'rejected' ? 'active' : ''; ?>">Rejected</a>
                <div class="search-box">
                    <form method="GET">
                        <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                        <input type="text" name="search" placeholder="Search requests..." value="<?php echo htmlspecialchars($search); ?>">
                    </form>
                </div>
            </div>

            <!-- Requests Table -->
            <div class="card">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Control Number</th>
                            <th>Requestor</th>
                            <th>Department</th>
                            <th>Event Type</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($requests && $requests->num_rows > 0): ?>
                            <?php while ($request = $requests->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($request['control_number']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($request['user_name']); ?><br>
                                        <small style="color: var(--muted-foreground);"><?php echo htmlspecialchars($request['user_email']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($request['department']); ?></td>
                                    <td><?php echo htmlspecialchars($request['event_type']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $request['status']; ?>">
                                            <?php echo ucfirst($request['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($request['created_at'])); ?></td>
                                    <td>
                                        <?php if (strtolower($request['status']) === 'pending'): ?>
                                            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                    <input type="hidden" name="admin_notes" value="">
                                                    <button type="submit" name="action" value="approve" class="btn btn-primary" onclick="return confirm('Approve this request?')">Approve</button>
                                                </form>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                    <input type="hidden" name="admin_notes" value="">
                                                    <button type="submit" name="action" value="reject" class="btn btn-reject" onclick="return confirm('Reject this request?')">Reject</button>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <a href="view_request_admin.php?id=<?php echo $request['id']; ?>" class="btn btn-primary">View</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                        <p>No requests found</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <script>
        // Theme toggle functionality
        function toggleTheme() {
            const html = document.documentElement;
            const currentTheme = html.classList.contains('dark') ? 'dark' : 'light';
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            html.classList.remove(currentTheme);
            html.classList.add(newTheme);
            
            // Save to cookie
            document.cookie = `theme=${newTheme}; path=/; max-age=31536000`;
            
            // Update icon
            updateThemeIcon(newTheme);
        }
        
        function updateThemeIcon(theme) {
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
        
        // Initialize theme icon on load
        document.addEventListener('DOMContentLoaded', function() {
            const theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
            updateThemeIcon(theme);
        });

        // Mobile sidebar functionality
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            sidebar.classList.toggle('mobile-open');
            overlay.classList.toggle('active');
            document.body.classList.toggle('sidebar-open');
        }

        // Close sidebar when clicking overlay
        document.getElementById('sidebarOverlay').addEventListener('click', function() {
            toggleSidebar();
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.querySelector('.sidebar');
            const mobileBtn = document.querySelector('.mobile-menu-btn');
            const overlay = document.getElementById('sidebarOverlay');
            
            if (window.innerWidth <= 768 && 
                !sidebar.contains(event.target) && 
                !mobileBtn.contains(event.target) &&
                sidebar.classList.contains('mobile-open')) {
                toggleSidebar();
            }
        });

        // Handle window resize
        window.addEventListener('resize', function() {
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            if (window.innerWidth > 768 && sidebar.classList.contains('mobile-open')) {
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('active');
                document.body.classList.remove('sidebar-open');
            }
        });
    </script>
</body>
</html>