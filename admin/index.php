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
$stats['total_requests'] = $result->fetch_assoc()['count'];

// Pending requests
$result = $conn->query("SELECT COUNT(*) as count FROM facility_requests WHERE status = 'pending'");
$stats['pending_requests'] = $result->fetch_assoc()['count'];

// Approved requests
$result = $conn->query("SELECT COUNT(*) as count FROM facility_requests WHERE status = 'approved'");
$stats['approved_requests'] = $result->fetch_assoc()['count'];

// Total users
$result = $conn->query("SELECT COUNT(*) as count FROM users");
$stats['total_users'] = $result->fetch_assoc()['count'];

// Pending users
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE verified = 1 AND approved = 0");
$stats['pending_users'] = $result->fetch_assoc()['count'];

// Recent requests
$recent_requests = $conn->query("SELECT fr.*, u.name as user_name FROM facility_requests fr 
                                 JOIN users u ON fr.user_id = u.id 
                                 ORDER BY fr.created_at DESC LIMIT 5");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - MCNP-ISAP</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            /* Glassmorphism gradient background matching reference */
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
            min-height: 100vh;
            display: flex;
            overflow-x: hidden;
        }
        
        /* Glassmorphism sidebar with backdrop blur */
        .sidebar {
            width: 260px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-right: 1px solid rgba(255, 255, 255, 0.2);
            min-height: 100vh;
            padding: 32px 20px;
            position: fixed;
            box-shadow: 4px 0 24px rgba(0, 0, 0, 0.1);
        }
        
        .sidebar-brand {
            color: white;
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 40px;
            padding-bottom: 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .sidebar-brand img {
            height: 36px;
            width: auto;
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
            gap: 14px;
            padding: 14px 18px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-weight: 500;
            font-size: 15px;
        }
        
        .sidebar-menu a:hover {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            transform: translateX(4px);
        }
        
        .sidebar-menu a.active {
            background: rgba(255, 255, 255, 0.25);
            color: white;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .sidebar-menu svg {
            width: 22px;
            height: 22px;
        }
        
        /* Main content with glassmorphism cards */
        .main-content {
            margin-left: 260px;
            flex: 1;
            padding: 32px;
            animation: fadeIn 0.5s ease-in-out;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Glassmorphism top bar */
        .top-bar {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 32px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .top-bar-left h2 {
            font-size: 16px;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 4px;
        }
        
        .top-bar-left h1 {
            font-size: 32px;
            color: white;
            font-weight: 700;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .user-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 18px;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }
        
        .user-details {
            color: white;
        }
        
        .user-details .name {
            font-weight: 600;
            font-size: 15px;
        }
        
        .user-details .role {
            font-size: 13px;
            opacity: 0.8;
        }
        
        /* Glassmorphism stats cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            animation: slideUp 0.5s ease-in-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 48px rgba(0, 0, 0, 0.15);
            background: rgba(255, 255, 255, 0.2);
        }
        
        .stat-card-icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
        }
        
        .stat-card-icon.purple {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .stat-card-icon.orange {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        .stat-card-icon.teal {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        
        .stat-card-icon.pink {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        }
        
        .stat-card-icon svg {
            width: 28px;
            height: 28px;
            color: white;
        }
        
        .stat-card h3 {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .stat-card .number {
            font-size: 40px;
            font-weight: 700;
            color: white;
        }
        
        /* Glassmorphism content cards */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
        }
        
        .card {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            padding: 28px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            animation: fadeIn 0.6s ease-in-out;
        }
        
        .card h2 {
            font-size: 20px;
            color: white;
            margin-bottom: 24px;
            font-weight: 700;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .view-all-btn {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            font-weight: 500;
            padding: 6px 14px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            transition: all 0.2s;
        }
        
        .view-all-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        /* Modern table design */
        .table-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
        }
        
        thead th {
            text-align: left;
            padding: 12px 16px;
            color: rgba(255, 255, 255, 0.8);
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        tbody tr {
            background: rgba(255, 255, 255, 0.1);
            transition: all 0.2s;
        }
        
        tbody tr:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: scale(1.01);
        }
        
        tbody td {
            padding: 16px;
            color: white;
            font-size: 14px;
        }
        
        tbody td:first-child {
            border-top-left-radius: 10px;
            border-bottom-left-radius: 10px;
        }
        
        tbody td:last-child {
            border-top-right-radius: 10px;
            border-bottom-right-radius: 10px;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: lowercase;
            display: inline-block;
        }
        
        .status-badge.pending {
            background: rgba(251, 191, 36, 0.3);
            color: #fbbf24;
            border: 1px solid rgba(251, 191, 36, 0.5);
        }
        
        .status-badge.approved {
            background: rgba(34, 197, 94, 0.3);
            color: #22c55e;
            border: 1px solid rgba(34, 197, 94, 0.5);
        }
        
        .status-badge.rejected {
            background: rgba(239, 68, 68, 0.3);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.5);
        }
        
        .quick-actions {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .action-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 20px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .action-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateX(4px);
        }
        
        .action-btn svg {
            width: 20px;
            height: 20px;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: rgba(255, 255, 255, 0.7);
        }
        
        .empty-state svg {
            width: 64px;
            height: 64px;
            margin-bottom: 16px;
            opacity: 0.5;
        }
        
        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <!-- Glassmorphism Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <img src="../combined-logo.png" alt="MCNP-ISAP Logo">
            <span>Admin</span>
        </div>
        <ul class="sidebar-menu">
            <li>
                <a href="index.php" class="active">
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
                    Requests
                </a>
            </li>
            <li>
                <a href="users.php">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                    Users
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
                <a href="reports.php">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Reports
                </a>
            </li>
            <li style="margin-top: 32px; padding-top: 32px; border-top: 1px solid rgba(255, 255, 255, 0.2);">
                <a href="../logout.php">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    Logout
                </a>
            </li>
        </ul>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Glassmorphism top bar -->
        <div class="top-bar">
            <div class="top-bar-left">
                <h2>Welcome back</h2>
                <h1>Dashboard</h1>
            </div>
            <div class="user-info">
                <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['user_name'], 0, 1)); ?></div>
                <div class="user-details">
                    <div class="name"><?php echo htmlspecialchars($_SESSION['user_name']); ?></div>
                    <div class="role">Administrator</div>
                </div>
            </div>
        </div>

        <!-- Glassmorphism statistics cards -->
        <div class="stats-grid">
            <div class="stat-card" style="animation-delay: 0.1s;">
                <div class="stat-card-icon purple">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <h3>Total Requests</h3>
                <div class="number"><?php echo $stats['total_requests']; ?></div>
            </div>
            
            <div class="stat-card" style="animation-delay: 0.2s;">
                <div class="stat-card-icon orange">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h3>Pending Requests</h3>
                <div class="number"><?php echo $stats['pending_requests']; ?></div>
            </div>
            
            <div class="stat-card" style="animation-delay: 0.3s;">
                <div class="stat-card-icon teal">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                </div>
                <h3>Total Users</h3>
                <div class="number"><?php echo $stats['total_users']; ?></div>
            </div>
            
            <div class="stat-card" style="animation-delay: 0.4s;">
                <div class="stat-card-icon pink">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h3>Approved</h3>
                <div class="number"><?php echo $stats['approved_requests']; ?></div>
            </div>
        </div>

        <!-- Glassmorphism content grid -->
        <div class="content-grid">
            <!-- Recent Requests Table -->
            <div class="card">
                <h2>
                    Recent Requests
                    <a href="requests.php" class="view-all-btn">View all</a>
                </h2>
                <div class="table-container">
                    <?php if ($recent_requests->num_rows > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Control #</th>
                                    <th>Event Type</th>
                                    <th>Requestor</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($request = $recent_requests->fetch_assoc()): ?>
                                    <tr onclick="window.location.href='view_request.php?id=<?php echo $request['id']; ?>'" style="cursor: pointer;">
                                        <td><strong><?php echo htmlspecialchars($request['control_number']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($request['event_type']); ?></td>
                                        <td><?php echo htmlspecialchars($request['user_name']); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo $request['status']; ?>">
                                                <?php echo $request['status']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($request['created_at'])); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <p>No requests yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <h2>Quick Actions</h2>
                <div class="quick-actions">
                    <a href="requests.php" class="action-btn">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                        <span>Manage Requests</span>
                    </a>
                    <a href="users.php" class="action-btn">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                        <span>Manage Users</span>
                    </a>
                    <a href="facilities.php" class="action-btn">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                        <span>Manage Facilities</span>
                    </a>
                    <a href="reports.php" class="action-btn">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <span>Generate Reports</span>
                    </a>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
