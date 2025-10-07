<?php
session_start();
require_once '../config.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'Admin') {
    header("Location: ../index.php");
    exit();
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Get date range from query params
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// Get statistics for the date range
$stats = [];

// Total requests
$sql = "SELECT COUNT(*) as count FROM facility_requests WHERE DATE(created_at) BETWEEN ? AND ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$stats['total'] = $stmt->get_result()->fetch_assoc()['count'];

// Approved requests
$sql = "SELECT COUNT(*) as count FROM facility_requests WHERE status = 'approved' AND DATE(created_at) BETWEEN ? AND ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$stats['approved'] = $stmt->get_result()->fetch_assoc()['count'];

// Rejected requests
$sql = "SELECT COUNT(*) as count FROM facility_requests WHERE status = 'rejected' AND DATE(created_at) BETWEEN ? AND ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$stats['rejected'] = $stmt->get_result()->fetch_assoc()['count'];

// Pending requests
$sql = "SELECT COUNT(*) as count FROM facility_requests WHERE status = 'pending' AND DATE(created_at) BETWEEN ? AND ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$stats['pending'] = $stmt->get_result()->fetch_assoc()['count'];

// Most requested facilities
$sql = "SELECT frd.facility_name, COUNT(*) as count 
        FROM facility_request_details frd
        JOIN facility_requests fr ON frd.request_id = fr.id
        WHERE DATE(fr.created_at) BETWEEN ? AND ?
        GROUP BY frd.facility_name
        ORDER BY count DESC
        LIMIT 5";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$top_facilities = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f7fa;
            display: flex;
        }
        
        .sidebar {
            width: 260px;
            background: #1a1a1a;
            min-height: 100vh;
            padding: 24px;
            position: fixed;
        }
        
        .sidebar-brand {
            color: white;
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 32px;
            padding-bottom: 24px;
            border-bottom: 1px solid #374151;
        }
        
        .sidebar-menu {
            list-style: none;
        }
        
        .sidebar-menu li {
            margin-bottom: 8px;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: #9ca3af;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.2s;
        }
        
        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: #374151;
            color: white;
        }
        
        .sidebar-menu svg {
            width: 20px;
            height: 20px;
        }
        
        .main-content {
            margin-left: 260px;
            flex: 1;
            padding: 32px;
        }
        
        .top-bar {
            background: white;
            border-radius: 12px;
            padding: 24px 32px;
            margin-bottom: 32px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .top-bar h1 {
            font-size: 28px;
            color: #1a1a1a;
            margin-bottom: 16px;
        }
        
        .date-filter {
            display: flex;
            gap: 16px;
            align-items: end;
        }
        
        .form-group {
            flex: 1;
        }
        
        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
        }
        
        .form-group input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 15px;
        }
        
        .btn-filter {
            padding: 10px 24px;
            background: #1a1a1a;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .stat-card h3 {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-card .number {
            font-size: 36px;
            font-weight: 700;
            color: #1a1a1a;
        }
        
        .chart-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .chart-card h2 {
            font-size: 18px;
            color: #1a1a1a;
            margin-bottom: 20px;
        }
        
        .facility-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .facility-item:last-child {
            border-bottom: none;
        }
        
        .facility-name {
            font-weight: 500;
            color: #1a1a1a;
        }
        
        .facility-count {
            font-size: 20px;
            font-weight: 700;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            Admin Panel
        </div>
        <ul class="sidebar-menu">
            <li>
                <a href="index.php">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    Dashboard
                </a>
            </li>
            <li>
                <a href="requests.php">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Manage Requests
                </a>
            </li>
            <li>
                <a href="users.php">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                    Manage Users
                </a>
            </li>
            <li>
                <a href="facilities.php">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                    Facilities
                </a>
            </li>
            <li>
                <a href="reports.php" class="active">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Reports
                </a>
            </li>
            <li>
                <a href="../dashboard.php">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Back to Portal
                </a>
            </li>
        </ul>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="top-bar">
            <h1>Reports & Analytics</h1>
            <form method="GET" class="date-filter">
                <div class="form-group">
                    <label>Start Date</label>
                    <input type="date" name="start_date" value="<?php echo $start_date; ?>">
                </div>
                <div class="form-group">
                    <label>End Date</label>
                    <input type="date" name="end_date" value="<?php echo $end_date; ?>">
                </div>
                <button type="submit" class="btn-filter">Generate Report</button>
            </form>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Requests</h3>
                <div class="number"><?php echo $stats['total']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Approved</h3>
                <div class="number" style="color: #10b981;"><?php echo $stats['approved']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Rejected</h3>
                <div class="number" style="color: #ef4444;"><?php echo $stats['rejected']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Pending</h3>
                <div class="number" style="color: #f59e0b;"><?php echo $stats['pending']; ?></div>
            </div>
        </div>

        <!-- Top Facilities -->
        <div class="chart-card">
            <h2>Most Requested Facilities</h2>
            <?php while ($facility = $top_facilities->fetch_assoc()): ?>
                <div class="facility-item">
                    <span class="facility-name"><?php echo htmlspecialchars($facility['facility_name']); ?></span>
                    <span class="facility-count"><?php echo $facility['count']; ?></span>
                </div>
            <?php endwhile; ?>
        </div>
    </main>
</body>
</html>
