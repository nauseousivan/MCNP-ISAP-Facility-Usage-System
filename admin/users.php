<?php
session_start();
require_once '../config.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'Admin') {
    header("Location: ../index.php");
    exit();
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Handle user approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $user_id = $_POST['user_id'];
    $action = $_POST['action'];
    
    if ($action === 'approve') {
        $sql = "UPDATE users SET approved = 1 WHERE id = ?";
    } else {
        $sql = "DELETE FROM users WHERE id = ?";
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    header("Location: users.php?success=1");
    exit();
}

// Get filters
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';
$user_type_filter = $_GET['user_type'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Build query
$sql = "SELECT * FROM users WHERE 1=1";
$params = [];
$types = "";

if ($filter === 'pending') {
    $sql .= " AND verified = 1 AND approved = 0";
} elseif ($filter === 'approved') {
    $sql .= " AND approved = 1";
} elseif ($filter === 'admin') {
    $sql .= " AND user_type = 'Admin'";
}

if ($search) {
    $sql .= " AND (name LIKE ? OR email LIKE ? OR department LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

if ($user_type_filter && $user_type_filter !== 'all') {
    $sql .= " AND user_type = ?";
    $params[] = $user_type_filter;
    $types .= "s";
}

if ($status_filter && $status_filter !== 'all') {
    if ($status_filter === 'verified') {
        $sql .= " AND verified = 1 AND approved = 0";
    } elseif ($status_filter === 'approved') {
        $sql .= " AND approved = 1";
    } elseif ($status_filter === 'not_verified') {
        $sql .= " AND verified = 0";
    }
}

$sql .= " ORDER BY created_at DESC";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $users = $stmt->get_result();
} else {
    $users = $conn->query($sql);
}

// Function to shorten department names
function shortenDepartment($department) {
    $shortNames = [
        'Medical Colleges of Northern Philippines' => 'MCNP',
        'International School of Asia and the Pacific' => 'ISAP'
    ];
    
    return $shortNames[$department] ?? $department;
}

// Get theme preference from cookie
$theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'light';
?>
<!DOCTYPE html>
<html lang="en" class="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin</title>
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
        
        /* Filters */
        .filters {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        
        .filter-btn {
            padding: 8px 16px;
            border: 1px solid var(--border);
            background: var(--card);
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            color: var(--muted-foreground);
            transition: all 0.2s;
            font-size: 14px;
        }
        
        .filter-btn:hover,
        .filter-btn.active {
            background: var(--primary);
            color: var(--primary-foreground);
            border-color: var(--primary);
        }
        
        .search-box input {
            padding: 8px 16px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            background: var(--card);
            color: var(--foreground);
            min-width: 250px;
        }
        
        .search-box input::placeholder {
            color: var(--muted-foreground);
        }

        /* Advanced Filters */
        .advanced-filters {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
        }

        .filter-row {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            align-items: end;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--muted-foreground);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            background: var(--background);
            color: var(--foreground);
        }

        .btn-apply {
            padding: 10px 24px;
            background: var(--primary);
            color: var(--primary-foreground);
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.2s;
        }

        .btn-apply:hover {
            opacity: 0.9;
            transform: translateY(-1px);
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
        
        .badge.admin {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }
        
        .badge.faculty {
            background: rgba(139, 92, 246, 0.1);
            color: #8b5cf6;
        }
        
        .badge.student {
            background: rgba(236, 72, 153, 0.1);
            color: #ec4899;
        }
        
        .badge.staff {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }
        
        .badge.pending {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }
        
        .badge.approved {
            background: rgba(34, 197, 94, 0.1);
            color: var(--success);
        }

        .badge.not-verified {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }
        
        /* Buttons */
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            font-size: 13px;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
            margin: 2px;
        }
        
        .btn-approve {
            background: var(--success);
            color: white;
        }
        
        .btn-reject {
            background: var(--danger);
            color: white;
        }

        .btn-activate {
            background: var(--info);
            color: white;
        }

        .btn-delete {
            background: var(--danger);
            color: white;
        }
        
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        /* Action buttons container */
        .action-buttons {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        
        /* Alert */
        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            color: var(--success);
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            border: 1px solid rgba(34, 197, 94, 0.2);
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

        /* Responsive */
        @media (max-width: 1024px) {
            .table {
                font-size: 13px;
            }
            
            .table thead th,
            .table tbody td {
                padding: 10px 8px;
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
                gap: 8px;
            }
            
            .search-box input {
                min-width: 100%;
                width: 100%;
            }

            .filter-row {
                flex-direction: column;
            }

            .filter-group {
                width: 100%;
            }
            
            .card {
                padding: 16px;
            }
            
            .table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
            
            .table thead th,
            .table tbody td {
                padding: 10px 6px;
                font-size: 12px;
            }
            
            .btn {
                padding: 6px 12px;
                font-size: 12px;
            }

            .action-buttons {
                gap: 4px;
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
                gap: 6px;
            }
            
            .filter-btn {
                padding: 6px 12px;
                font-size: 12px;
            }
            
            .card {
                padding: 12px;
            }
            
            .table {
                font-size: 11px;
            }
            
            .badge {
                font-size: 10px;
                padding: 3px 8px;
            }

            .action-buttons {
                flex-direction: column;
                gap: 3px;
            }

            .btn {
                padding: 5px 10px;
                font-size: 11px;
                margin: 1px;
            }
        }
    </style>
</head>
<body>
    
     <!-- Sidebar -->
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
                <h1>Manage Users</h1>
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
                <div class="alert-success">
                    User action completed successfully!
                </div>
            <?php endif; ?>

             <!-- Quick Filters -->
            <div class="card">
                <form method="GET" class="filters">
                    <a href="?filter=all" class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">All Users</a>
                    <a href="?filter=pending" class="filter-btn <?php echo $filter === 'pending' ? 'active' : ''; ?>">Pending</a>
                    <a href="?filter=approved" class="filter-btn <?php echo $filter === 'approved' ? 'active' : ''; ?>">Approved</a>
                    <a href="?filter=admin" class="filter-btn <?php echo $filter === 'admin' ? 'active' : ''; ?>">Admins</a>
                    <div class="search-box">
                        <input type="text" name="search" placeholder="Search users..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </form>
            </div>

            <!-- Advanced Filters -->
            <div class="advanced-filters">
                <form method="GET" id="advancedFilters">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="search">Search Users</label>
                            <input type="text" id="search" name="search" placeholder="Search by name, email, or department..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="filter-group">
                            <label for="user_type">User Type</label>
                            <select id="user_type" name="user_type">
                                <option value="all">All Types</option>
                                <option value="Student" <?php echo $user_type_filter === 'Student' ? 'selected' : ''; ?>>Student</option>
                                <option value="Faculty" <?php echo $user_type_filter === 'Faculty' ? 'selected' : ''; ?>>Faculty</option>
                                <option value="Staff" <?php echo $user_type_filter === 'Staff' ? 'selected' : ''; ?>>Staff</option>
                                <option value="Admin" <?php echo $user_type_filter === 'Admin' ? 'selected' : ''; ?>>Admin</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                <option value="all">All Status</option>
                                <option value="not_verified" <?php echo $status_filter === 'not_verified' ? 'selected' : ''; ?>>Not Verified</option>
                                <option value="verified" <?php echo $status_filter === 'verified' ? 'selected' : ''; ?>>Verified (Pending)</option>
                                <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <button type="submit" class="btn-apply">Apply Filters</button>
                        </div>
                    </div>
                </form>
            </div>

             <!-- Users Table -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Users</h2>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Department</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($user = $users->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($user['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars(shortenDepartment($user['department'])); ?></td>
                                <td>
                                    <span class="badge <?php echo strtolower($user['user_type']); ?>">
                                        <?php echo $user['user_type']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    // Determine user status based on verified and approved fields
                                    if ($user['verified'] == 0) {
                                        echo '<span class="badge not-verified">Not Verified</span>';
                                    } elseif ($user['approved'] == 0) {
                                        echo '<span class="badge pending">Pending Approval</span>';
                                    } else {
                                        echo '<span class="badge approved">Approved</span>';
                                    }
                                    ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($user['verified'] == 1 && $user['approved'] == 0): ?>
                                            <!-- Pending approval - show approve/reject buttons -->
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" name="action" value="approve" class="btn btn-approve">Approve</button>
                                                <button type="submit" name="action" value="reject" class="btn btn-reject" onclick="return confirm('Are you sure you want to reject this user?')">Reject</button>
                                            </form>
                                        <?php elseif ($user['user_type'] !== 'Admin'): ?>
                                            <!-- For non-admin users who are already approved or not verified -->
                                            <?php if ($user['verified'] == 0): ?>
                                                <!-- Not verified - show activate option -->
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" name="action" value="approve" class="btn btn-activate">Activate</button>
                                                </form>
                                            <?php endif; ?>
                                            <!-- Always show delete option for non-admin users -->
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" name="action" value="reject" class="btn btn-delete" onclick="return confirm('Are you sure you want to delete this user?')">Delete</button>
                                            </form>
                                        <?php else: ?>
                                            <!-- Admin users - no actions -->
                                            <span style="color: var(--muted-foreground);">-</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <script>
        function toggleTheme() {
            const html = document.documentElement;
            const currentTheme = html.classList.contains('dark') ? 'dark' : 'light';
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            html.classList.remove(currentTheme);
            html.classList.add(newTheme);
            
            document.cookie = `theme=${newTheme}; path=/; max-age=31536000`;
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
        
        document.addEventListener('DOMContentLoaded', function() {
            const theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
            updateThemeIcon(theme);
        });

        // Auto-submit advanced filters when selections change
        document.getElementById('user_type').addEventListener('change', function() {
            document.getElementById('advancedFilters').submit();
        });

        document.getElementById('status').addEventListener('change', function() {
            document.getElementById('advancedFilters').submit();
        });
    </script>
</body>
</html>