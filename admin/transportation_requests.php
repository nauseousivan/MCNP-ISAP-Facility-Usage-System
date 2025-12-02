<?php
session_start();
require_once '../config.php';
require_once '../functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'Admin') {
    header("Location: ../index.php");
    exit();
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'])) {
    $request_id = $_POST['request_id'];
    $status = $_POST['status'];
    $driver_assigned = $_POST['driver_assigned'] ?? null;

    // Get request details for notification
    $req_stmt = $conn->prepare("SELECT user_id, control_no FROM transportation_requests WHERE id = ?");
    $req_stmt->bind_param("i", $request_id);
    $req_stmt->execute();
    $request_data = $req_stmt->get_result()->fetch_assoc();

    $sql = "UPDATE transportation_requests SET status = ?, driver_assigned = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $status, $driver_assigned, $request_id);
    
    if ($stmt->execute()) {
        // If the request was approved, send a notification
        if ($status === 'approved' && $request_data) {
            add_action_notification($conn, $request_data['user_id'], 'transportation_request_approved', [
                'control_number' => $request_data['control_no']
            ]);
        }
    }
    header("Location: transportation_requests.php");
    exit();
}

// Get filters
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query
$sql = "SELECT tr.*, u.name as user_name 
        FROM transportation_requests tr
        JOIN users u ON tr.user_id = u.id
        WHERE 1=1";

$params = [];
$types = "";

if ($filter !== 'all') {
    $sql .= " AND tr.status = ?";
    $params[] = $filter;
    $types .= "s";
}

if ($search) {
    $sql .= " AND (tr.control_no LIKE ? OR u.name LIKE ? OR tr.vehicle_requested LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

$sql .= " ORDER BY tr.created_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$requests = $stmt->get_result();

// Get available drivers
$drivers = [];
$driver_sql = "SELECT DISTINCT driver_name FROM transportation_vehicles WHERE driver_name IS NOT NULL AND driver_name != ''";
$driver_result = $conn->query($driver_sql);
while ($row = $driver_result->fetch_assoc()) {
    $drivers[] = $row['driver_name'];
}

$theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'light';
?>
<!DOCTYPE html>
<html lang="en" class="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transportation Requests - Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --background: #fdfaf6;
            --foreground: #0a0a0a;
            --card: #ffffff;
            --card-foreground: #0a0a0a;
            --muted: #f8f5f1;
            --muted-foreground: #71717a;
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
            --sidebar: #ffffff;
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
            --sidebar: #0a0a0a;
            --sidebar-foreground: #fafafa;
            --sidebar-border: #262626;
        }
        
        @font-face {
            font-family: 'Geist Sans';
            src: url('../node_modules/geist/dist/fonts/geist-sans/Geist-Variable.woff2') format('woff2');
            font-weight: 100 900;
            font-style: normal;
        }

        body {
            font-family: 'Geist Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: var(--background);
            color: var(--foreground);
            display: flex;
            min-height: 100vh;
            transition: background-color 0.3s, color 0.3s;
        }
        
        /* Sidebar */
        .sidebar { width: 260px; background: var(--sidebar); border-right: 1px solid var(--sidebar-border); padding: 24px 16px; position: fixed; height: 100vh; overflow-y: auto; transition: all 0.3s ease; z-index: 1000; }
        .sidebar-brand { display: flex; align-items: center; gap: 12px; padding: 8px 12px; margin-bottom: 32px; font-size: 18px; font-weight: 700; color: var(--sidebar-foreground); }
        .sidebar-brand img { height: 32px; width: auto; }
        .sidebar-menu { list-style: none; }
        .sidebar-menu li { margin-bottom: 4px; }
        .sidebar-menu a { display: flex; align-items: center; gap: 12px; padding: 10px 12px; color: var(--muted-foreground); text-decoration: none; border-radius: 12px; transition: all 0.2s; font-size: 14px; font-weight: 500; }
        .sidebar-menu a:hover { background: var(--accent); color: var(--accent-foreground); }
        .sidebar-menu a.active { background: var(--primary); color: var(--primary-foreground); }
        .sidebar-menu svg { width: 20px; height: 20px; flex-shrink: 0; }
        .sidebar-divider { margin: 24px 0; padding-top: 24px; border-top: 1px solid var(--border); }
        
        /* Main Content */
        .main-content { margin-left: 260px; flex: 1; display: flex; flex-direction: column; transition: margin-left 0.3s ease; }
        
        /* Top Nav */
        .top-nav { height: 64px; border-bottom: 1px solid var(--border); padding: 0 32px; display: flex; align-items: center; justify-content: space-between; background: var(--background); position: sticky; top: 0; z-index: 10; transition: background-color 0.3s, border-color 0.3s; }
        .top-nav-left { display: flex; align-items: center; gap: 16px; }
        .top-nav h1 { font-size: 20px; font-weight: 600; }
        .top-nav-actions { display: flex; align-items: center; gap: 12px; }
        .theme-toggle { padding: 8px; border-radius: 8px; border: none; background: transparent; cursor: pointer; color: var(--foreground); transition: background-color 0.2s; }
        .theme-toggle:hover { background: var(--muted); }
        .theme-toggle svg { width: 20px; height: 20px; }

        /* Mobile menu button */
        .mobile-menu-btn { display: none; padding: 8px; background: transparent; border: none; color: var(--foreground); cursor: pointer; border-radius: 8px; transition: background-color 0.2s; }
        .mobile-menu-btn:hover { background: var(--muted); }
        .mobile-menu-btn svg { width: 20px; height: 20px; }
        
        /* Content Area */
        .content { flex: 1; padding: 32px; overflow-y: auto; }
        
        /* Filters */
        .filters { background: var(--card); border: 1px solid var(--border); border-radius: 20px; padding: 20px; margin-bottom: 24px; box-shadow: 0 4px 10px rgba(0,0,0,0.03); display: flex; gap: 12px; flex-wrap: wrap; align-items: center; }
        .filter-btn { padding: 8px 16px; border: 1px solid var(--border); background: var(--background); border-radius: 8px; cursor: pointer; font-weight: 500; text-decoration: none; color: var(--foreground); transition: all 0.2s; font-size: 14px; }
        .filter-btn:hover, .filter-btn.active { background: var(--primary); color: var(--primary-foreground); border-color: var(--primary); }
        .search-box { flex: 1; min-width: 250px; }
        .search-box input { width: 100%; padding: 8px 16px; border: 1px solid var(--border); border-radius: 8px; font-size: 14px; background: var(--background); color: var(--foreground); }
        
        /* Card */
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 20px; padding: 24px; margin-bottom: 24px; transition: background-color 0.3s, border-color 0.3s; box-shadow: 0 4px 10px rgba(0,0,0,0.03); }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid var(--border); }
        .card-title { font-size: 18px; font-weight: 600; color: var(--foreground); }
        
        /* Table */
        .table { width: 100%; border-collapse: collapse; }
        .table thead th { text-align: left; padding: 12px; font-size: 12px; font-weight: 600; color: var(--muted-foreground); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--border); }
        .table tbody td { padding: 16px 12px; border-bottom: 1px solid var(--border); font-size: 14px; }
        .table tbody tr:hover { background: var(--muted); }
        
        /* Badge */
        .badge { display: inline-flex; align-items: center; padding: 6px 12px; border-radius: 999px; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; }
        .badge.pending { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
        .badge.approved { background: rgba(34, 197, 94, 0.1); color: var(--success); }
        .badge.rejected { background: rgba(239, 68, 68, 0.1); color: var(--danger); }
        .badge.completed { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
        
        /* Empty State */
        .empty-state { text-align: center; padding: 60px 20px; color: var(--muted-foreground); }
        .empty-state svg { width: 64px; height: 64px; margin-bottom: 16px; opacity: 0.5; }
        
        /* Action Form Inline */
        .action-form-inline { display: flex; gap: 8px; align-items: center; }
        .form-input-sm { padding: 6px 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px; background: var(--background); color: var(--foreground); }
        .btn-sm { padding: 6px 12px; font-size: 13px; border-radius: 6px; }
        .btn-primary { background: var(--primary); color: var(--primary-foreground); border: none; cursor: pointer; transition: all 0.2s; }
        .btn-primary:hover { opacity: 0.9; }
        .btn-secondary { background: var(--secondary); color: var(--secondary-foreground); border: 1px solid var(--border); }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .filters { gap: 10px; }
            .search-box { min-width: 200px; }
        }
        
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); width: 280px; }
            .sidebar.mobile-open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .content { padding: 16px; }
            .top-nav { padding: 0 16px; height: 60px; }
            .top-nav h1 { font-size: 18px; }
            .mobile-menu-btn { display: flex; align-items: center; justify-content: center; }
            .filters { flex-direction: column; align-items: stretch; gap: 12px; }
            .search-box { width: 100%; min-width: auto; }
            .filter-btn { text-align: center; }
            .table { display: block; overflow-x: auto; white-space: nowrap; }
            .table thead th, .table tbody td { padding: 10px 8px; font-size: 13px; }
            .card { padding: 16px; }
        }

        @media (max-width: 480px) {
            .content { padding: 12px; }
            .top-nav { padding: 0 12px; }
            .filters { padding: 16px; }
            .filter-btn { padding: 10px 12px; font-size: 13px; }
            .table thead th, .table tbody td { padding: 8px 6px; font-size: 12px; }
            .btn-sm { padding: 6px 12px; font-size: 12px; }
            .badge { font-size: 11px; padding: 3px 8px; }
        }

        /* Sidebar overlay for mobile */
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.5); z-index: 999; }
        .sidebar-overlay.active { display: block; }
        body.sidebar-open { overflow: hidden; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <nav class="top-nav">
            <div class="top-nav-left">
                <button class="mobile-menu-btn" onclick="toggleSidebar()">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
                <h1>Transportation Requests</h1>
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

        <main class="content">
            <div class="filters">
                <a href="?filter=all" class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">All</a>
                <a href="?filter=pending" class="filter-btn <?php echo $filter === 'pending' ? 'active' : ''; ?>">Pending</a>
                <a href="?filter=approved" class="filter-btn <?php echo $filter === 'approved' ? 'active' : ''; ?>">Approved</a>
                <a href="?filter=rejected" class="filter-btn <?php echo $filter === 'rejected' ? 'active' : ''; ?>">Rejected</a>
                <a href="?filter=completed" class="filter-btn <?php echo $filter === 'completed' ? 'active' : ''; ?>">Completed</a>
                <div class="search-box">
                    <form method="GET">
                        <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                        <input type="text" name="search" placeholder="Search requests..." value="<?php echo htmlspecialchars($search); ?>">
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">All Transportation Requests</h2>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Control No.</th>
                            <th>Requestor</th>
                            <th>Vehicle</th>
                            <th>Trip Date</th>
                            <th>Purpose</th>
                            <th>Passengers</th>
                            <th style="width: 120px;">Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($requests->num_rows > 0): ?>
                            <?php while ($request = $requests->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($request['control_no']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($request['user_name']); ?></td>
                                    <td><?php echo htmlspecialchars($request['vehicle_requested']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($request['date_vehicle_used'])); ?></td>
                                    <td title="<?php echo htmlspecialchars($request['purpose']); ?>"><?php echo htmlspecialchars(substr($request['purpose'], 0, 30)); ?>...</td>
                                    <td><?php echo htmlspecialchars($request['no_of_passengers']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $request['status']; ?>">
                                            <?php echo ucfirst($request['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="POST" class="action-form-inline">
                                            <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                            
                                            <select name="status" class="form-input-sm">
                                                <option value="pending" <?php echo $request['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="approved" <?php echo $request['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                                <option value="rejected" <?php echo $request['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                                <option value="completed" <?php echo $request['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                            </select>
                                            
                                            <select name="driver_assigned" class="form-input-sm" style="width: 150px;">
                                                <option value="">Assign Driver</option>
                                                <?php foreach ($drivers as $driver): ?>
                                                    <option value="<?php echo htmlspecialchars($driver); ?>" <?php echo $request['driver_assigned'] === $driver ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($driver); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            
                                            <button type="submit" class="btn-sm btn-primary">Update</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state" style="padding: 40px; text-align: center;">
                                        <p>No transportation requests found.</p>
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
        
        document.addEventListener('DOMContentLoaded', function() {
            const theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
            updateThemeIcon(theme);
        });

        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('mobile-open');
        }

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