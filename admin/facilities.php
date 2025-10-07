<?php
session_start();
require_once '../config.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'Admin') {
    header("Location: ../index.php");
    exit();
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Get facility usage statistics
$facilities_list = [
    'HM Laboratory', 'Function Hall', 'Conference Hall', 'Hotel Room',
    'TM Laboratory', 'Gymnasium', 'AVR 1', 'AVR 2', 'AVR 3',
    'AMPHI 1', 'AMPHI 2', 'AMPHI 3', 'Quadrangle', 'Reading Area',
    'Studio Room', 'Cabbo La Vista', 'Pamplona La Vista', 'ISAP-Tug Retreat House'
];

$facility_stats = [];
foreach ($facilities_list as $facility) {
    $sql = "SELECT COUNT(*) as total_bookings,
            SUM(CASE WHEN fr.status = 'approved' THEN 1 ELSE 0 END) as approved_bookings
            FROM facility_request_details frd
            JOIN facility_requests fr ON frd.request_id = fr.id
            WHERE frd.facility_name = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $facility);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $facility_stats[$facility] = $result;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facilities - Admin</title>
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
        }
        
        .facilities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 24px;
        }
        
        .facility-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .facility-card h3 {
            font-size: 18px;
            color: #1a1a1a;
            margin-bottom: 16px;
        }
        
        .facility-stats {
            display: flex;
            gap: 16px;
        }
        
        .stat-item {
            flex: 1;
            padding: 12px;
            background: #f9fafb;
            border-radius: 8px;
        }
        
        .stat-label {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 4px;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: #1a1a1a;
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
                <a href="facilities.php" class="active">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                    Facilities
                </a>
            </li>
            <li>
                <a href="reports.php">
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
            <h1>Facilities Overview</h1>
            <p style="color: #6b7280; margin-top: 8px;">View booking statistics for all facilities</p>
        </div>

        <div class="facilities-grid">
            <?php foreach ($facility_stats as $facility => $stats): ?>
                <div class="facility-card">
                    <h3><?php echo htmlspecialchars($facility); ?></h3>
                    <div class="facility-stats">
                        <div class="stat-item">
                            <div class="stat-label">Total Bookings</div>
                            <div class="stat-value"><?php echo $stats['total_bookings']; ?></div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">Approved</div>
                            <div class="stat-value" style="color: #10b981;"><?php echo $stats['approved_bookings']; ?></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </main>
</body>
</html>
