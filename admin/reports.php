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
        LIMIT 10";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$top_facilities = $stmt->get_result();

// Requests by status for pie chart
$sql = "SELECT status, COUNT(*) as count 
        FROM facility_requests 
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY status";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$requests_by_status = $stmt->get_result();

// Requests by user type
$sql = "SELECT u.user_type, COUNT(*) as count 
        FROM facility_requests fr
        JOIN users u ON fr.user_id = u.id
        WHERE DATE(fr.created_at) BETWEEN ? AND ?
        GROUP BY u.user_type";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$requests_by_user_type = $stmt->get_result();

// Daily requests for line chart (last 30 days)
$sql = "SELECT DATE(created_at) as date, COUNT(*) as count 
        FROM facility_requests 
        WHERE DATE(created_at) BETWEEN DATE_SUB(?, INTERVAL 30 DAY) AND ?
        GROUP BY DATE(created_at)
        ORDER BY date";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $end_date, $end_date);
$stmt->execute();
$daily_requests = $stmt->get_result();

// Get theme preference
$theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'light';
?>
<!DOCTYPE html>
<html lang="en" class="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - Admin</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.68/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.68/vfs_fonts.js"></script>
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
            --info: #3b82f6;
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
            border-radius: 12px;
            transition: all 0.2s;
            font-size: 14px;
            font-weight: 500;
        }
        
        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: var(--accent);
            color: var(--accent-foreground);
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
        
        /* Date Filter Card */
        .filter-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.03);
            margin-bottom: 32px;
        }
        
        .date-filter {
            display: flex;
            gap: 16px;
            align-items: end;
            flex-wrap: wrap;
        }
        
        .form-group {
            flex: 1;
            min-width: 200px;
        }
        
        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--muted-foreground);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .form-group input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            background: var(--background);
            color: var(--foreground);
        }
        
        .btn-filter {
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
        
        .btn-filter:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        /* Download Section */
        .download-section {
            display: flex;
            gap: 16px;
            align-items: end;
            flex-wrap: wrap;
            margin-bottom: 32px;
        }

        .download-format {
            flex: 1;
            min-width: 200px;
        }

        .download-format label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--muted-foreground);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .download-format select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            background: var(--background);
            color: var(--foreground);
        }
        
        .btn-download {
            padding: 10px 24px;
            background: var(--success);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-download:hover {
            opacity: 0.9;
            transform: translateY(-1px);
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
            border-radius: 20px;
            padding: 24px;
            text-align: center;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 4px 10px rgba(0,0,0,0.03);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .stat-label {
            font-size: 14px;
            color: var(--muted-foreground);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Charts Grid */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }
        
        /* Card */
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 24px;
            margin-bottom: 24px;
            transition: background-color 0.3s, border-color 0.3s;
            box-shadow: 0 4px 10px rgba(0,0,0,0.03);
        }
        
        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        
        .card-title {
            font-size: 18px;
            font-weight: 600;
        }

        .chart-type-selector {
            display: flex;
            gap: 8px;
        }

        .chart-type-btn {
            padding: 6px 12px;
            border: 1px solid var(--border);
            background: var(--background);
            color: var(--foreground);
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.2s;
        }

        .chart-type-btn.active {
            background: var(--primary);
            color: var(--primary-foreground);
            border-color: var(--primary);
        }

        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
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
        
        /* Progress Bar */
        .progress-bar {
            width: 100%;
            height: 8px;
            background: var(--muted);
            border-radius: 4px;
            overflow: hidden;
            margin-top: 8px;
        }
        
        .progress-fill {
            height: 100%;
            background: var(--primary);
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
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
            .charts-grid {
                grid-template-columns: 1fr;
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
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 16px;
            }
            
            .stat-card {
                padding: 20px;
            }
            
            .stat-number {
                font-size: 28px;
            }
            
            .date-filter {
                flex-direction: column;
                align-items: stretch;
                gap: 12px;
            }
            
            .form-group {
                min-width: auto;
            }

            .download-section {
                flex-direction: column;
                align-items: stretch;
                gap: 12px;
            }

            .download-format {
                min-width: auto;
            }

            .charts-grid {
                grid-template-columns: 1fr;
            }

            .chart-container {
                height: 250px;
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
                padding: 10px 8px;
                font-size: 13px;
            }
        }

        @media (max-width: 480px) {
            .content {
                padding: 12px;
            }
            
            .top-nav {
                padding: 0 12px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            
            .stat-card {
                padding: 16px;
            }
            
            .stat-number {
                font-size: 24px;
            }
            
            .filter-card {
                padding: 16px;
            }
            
            .card {
                padding: 12px;
            }

            .chart-container {
                height: 200px;
            }

            .card-header {
                flex-direction: column;
                gap: 12px;
                align-items: start;
            }

            .chart-type-selector {
                width: 100%;
                justify-content: center;
            }
            
            .table thead th,
            .table tbody td {
                padding: 8px 6px;
                font-size: 12px;
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

        /* Spinner */
        .spinner {
            display: inline-block;
            width: 1rem;
            height: 1rem;
            border: 2px solid currentColor;
            border-right-color: transparent;
            border-radius: 50%;
            animation: spinner 0.75s linear infinite;
        }

        @keyframes spinner {
            to {
                transform: rotate(360deg);
            }
        }

        /* Chart colors for different themes */
        .dark .chart-colors {
            --chart-color-1: #3b82f6;
            --chart-color-2: #ef4444;
            --chart-color-3: #10b981;
            --chart-color-4: #f59e0b;
            --chart-color-5: #8b5cf6;
            --chart-color-6: #ec4899;
            --chart-color-7: #06b6d4;
            --chart-color-8: #84cc16;
        }

        .light .chart-colors {
            --chart-color-1: #3b82f6;
            --chart-color-2: #ef4444;
            --chart-color-3: #10b981;
            --chart-color-4: #f59e0b;
            --chart-color-5: #8b5cf6;
            --chart-color-6: #ec4899;
            --chart-color-7: #06b6d4;
            --chart-color-8: #84cc16;
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
                <h1>Reports & Analytics Dashboard</h1>
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
            <!-- Date Filter -->
            <div class="filter-card">
                <form method="GET">
                    <div class="date-filter">
                        <div class="form-group">
                            <label for="start_date">Start Date</label>
                            <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="end_date">End Date</label>
                            <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" required>
                        </div>
                        <button type="submit" class="btn-filter">Generate Report</button>
                    </div>
                </form>
            </div>

            <!-- Download Section -->
            <div class="filter-card">
                <div class="download-section">
                    <div class="download-format">
                        <label for="export_format">Export Format</label>
                        <select id="export_format" class="form-select">
                            <option value="excel">Excel (.xlsx)</option>
                            <option value="csv">CSV</option>
                            <option value="pdf">PDF</option>
                        </select>
                    </div>
                    <button id="downloadReportBtn" class="btn-download">
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/>
                            <path d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708l3 3z"/>
                        </svg>
                        Download Full Report
                    </button>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">Total Requests</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['approved']; ?></div>
                    <div class="stat-label">Approved</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['rejected']; ?></div>
                    <div class="stat-label">Rejected</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['pending']; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="charts-grid">
                <!-- Requests by Status Chart -->
                <div class="card chart-colors">
                    <div class="card-header">
                        <h3 class="card-title">Requests by Status</h3>
                        <div class="chart-type-selector">
                            <button class="chart-type-btn active" data-chart="statusPie">Pie</button>
                            <button class="chart-type-btn" data-chart="statusBar">Bar</button>
                            <button class="chart-type-btn" data-chart="statusDoughnut">Doughnut</button>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>

                <!-- Requests Trend Chart -->
                <div class="card chart-colors">
                    <div class="card-header">
                        <h3 class="card-title">Requests Trend (Last 30 Days)</h3>
                        <div class="chart-type-selector">
                            <button class="chart-type-btn active" data-chart="trendLine">Line</button>
                            <button class="chart-type-btn" data-chart="trendBar">Bar</button>
                            <button class="chart-type-btn" data-chart="trendArea">Area</button>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>

                <!-- Requests by User Type -->
                <div class="card chart-colors">
                    <div class="card-header">
                        <h3 class="card-title">Requests by User Type</h3>
                        <div class="chart-type-selector">
                            <button class="chart-type-btn active" data-chart="userTypeBar">Bar</button>
                            <button class="chart-type-btn" data-chart="userTypePie">Pie</button>
                            <button class="chart-type-btn" data-chart="userTypeHorizontal">Horizontal</button>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="userTypeChart"></canvas>
                    </div>
                </div>

                <!-- Top Facilities -->
                <div class="card chart-colors">
                    <div class="card-header">
                        <h3 class="card-title">Most Requested Facilities</h3>
                        <div class="chart-type-selector">
                            <button class="chart-type-btn active" data-chart="facilitiesBar">Bar</button>
                            <button class="chart-type-btn" data-chart="facilitiesHorizontal">Horizontal</button>
                            <button class="chart-type-btn" data-chart="facilitiesPie">Pie</button>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="facilitiesChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Top Facilities Table -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Most Requested Facilities</h3>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Facility</th>
                            <th>Request Count</th>
                            <th>Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_requests = $stats['total'];
                        while ($facility = $top_facilities->fetch_assoc()): 
                            $percentage = $total_requests > 0 ? ($facility['count'] / $total_requests) * 100 : 0;
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($facility['facility_name']); ?></td>
                                <td><?php echo $facility['count']; ?></td>
                                <td>
                                    <div><?php echo number_format($percentage, 1); ?>%</div>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $percentage; ?>%"></div>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                
                <?php if ($top_facilities->num_rows === 0): ?>
                    <div class="empty-state">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        <p>No data available for the selected period</p>
                    </div>
                <?php endif; ?>
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
            // Update charts for new theme
            updateChartsForTheme();
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
            initializeCharts();
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

        // Chart configuration
        const chartColors = {
            approved: '#10b981',
            rejected: '#ef4444',
            pending: '#f59e0b',
            total: '#3b82f6'
        };

        const chartInstances = {};

        function initializeCharts() {
            // Requests by Status Data
            const statusData = {
                labels: ['Approved', 'Rejected', 'Pending'],
                datasets: [{
                    data: [<?php echo $stats['approved']; ?>, <?php echo $stats['rejected']; ?>, <?php echo $stats['pending']; ?>],
                    backgroundColor: [chartColors.approved, chartColors.rejected, chartColors.pending],
                    borderColor: getComputedStyle(document.documentElement).getPropertyValue('--background'),
                    borderWidth: 2
                }]
            };

            // Daily Trend Data
            const trendData = {
                labels: [
                    <?php
                    $dailyData = [];
                    while ($day = $daily_requests->fetch_assoc()) {
                        $dailyData[$day['date']] = $day['count'];
                    }
                    
                    // Generate last 30 days
                    $dates = [];
                    $counts = [];
                    for ($i = 29; $i >= 0; $i--) {
                        $date = date('Y-m-d', strtotime("-$i days", strtotime($end_date)));
                        $dates[] = $date;
                        $counts[] = $dailyData[$date] ?? 0;
                    }
                    
                    echo "'" . implode("','", array_map(function($date) {
                        return date('M j', strtotime($date));
                    }, $dates)) . "'";
                    ?>
                ],
                datasets: [{
                    label: 'Daily Requests',
                    data: [<?php echo implode(',', $counts); ?>],
                    borderColor: chartColors.total,
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            };

            // User Type Data
            const userTypeData = {
                labels: [
                    <?php
                    $userTypes = [];
                    $userTypeCounts = [];
                    while ($type = $requests_by_user_type->fetch_assoc()) {
                        $userTypes[] = $type['user_type'];
                        $userTypeCounts[] = $type['count'];
                    }
                    echo "'" . implode("','", $userTypes) . "'";
                    ?>
                ],
                datasets: [{
                    label: 'Requests by User Type',
                    data: [<?php echo implode(',', $userTypeCounts); ?>],
                    backgroundColor: [
                        '#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6'
                    ]
                }]
            };

            // Facilities Data
            const facilitiesData = {
                labels: [
                    <?php
                    $facilityNames = [];
                    $facilityCounts = [];
                    $top_facilities->data_seek(0); // Reset pointer
                    while ($facility = $top_facilities->fetch_assoc()) {
                        $facilityNames[] = $facility['facility_name'];
                        $facilityCounts[] = $facility['count'];
                    }
                    echo "'" . implode("','", array_map(function($name) {
                        return strlen($name) > 20 ? substr($name, 0, 20) + '...' : $name;
                    }, $facilityNames)) . "'";
                    ?>
                ],
                datasets: [{
                    label: 'Request Count',
                    data: [<?php echo implode(',', $facilityCounts); ?>],
                    backgroundColor: [
                        '#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6',
                        '#ec4899', '#06b6d4', '#84cc16', '#f97316', '#a855f7'
                    ]
                }]
            };

            // Create charts
            createStatusChart(statusData);
            createTrendChart(trendData);
            createUserTypeChart(userTypeData);
            createFacilitiesChart(facilitiesData);

            // Setup chart type toggles
            setupChartToggles();
        }

        function createStatusChart(data) {
            const ctx = document.getElementById('statusChart').getContext('2d');
            chartInstances.statusPie = new Chart(ctx, {
                type: 'pie',
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });

            // Create other chart types but keep them hidden
            chartInstances.statusBar = createChart('statusChart', 'bar', data);
            chartInstances.statusDoughnut = createChart('statusChart', 'doughnut', data);
        }

        function createTrendChart(data) {
            const ctx = document.getElementById('trendChart').getContext('2d');
            chartInstances.trendLine = new Chart(ctx, {
                type: 'line',
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });

            chartInstances.trendBar = createChart('trendChart', 'bar', data);
            chartInstances.trendArea = createChart('trendChart', 'line', {
                ...data,
                datasets: [{
                    ...data.datasets[0],
                    fill: true,
                    backgroundColor: 'rgba(59, 130, 246, 0.3)'
                }]
            });
        }

        function createUserTypeChart(data) {
            const ctx = document.getElementById('userTypeChart').getContext('2d');
            chartInstances.userTypeBar = new Chart(ctx, {
                type: 'bar',
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            chartInstances.userTypePie = createChart('userTypeChart', 'pie', data);
            chartInstances.userTypeHorizontal = createChart('userTypeChart', 'bar', data, {
                indexAxis: 'y'
            });
        }

        function createFacilitiesChart(data) {
            const ctx = document.getElementById('facilitiesChart').getContext('2d');
            chartInstances.facilitiesBar = new Chart(ctx, {
                type: 'bar',
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            chartInstances.facilitiesHorizontal = createChart('facilitiesChart', 'bar', data, {
                indexAxis: 'y'
            });
            chartInstances.facilitiesPie = createChart('facilitiesChart', 'pie', data);
        }

        function createChart(canvasId, type, data, options = {}) {
            const ctx = document.getElementById(canvasId).getContext('2d');
            return new Chart(ctx, {
                type: type,
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    ...options
                }
            });
        }

        function setupChartToggles() {
            document.querySelectorAll('.chart-type-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const container = this.closest('.card');
                    const chartType = this.dataset.chart;
                    const chartName = chartType.replace(/([A-Z])/g, '$1').split(/(?=[A-Z])/)[0];
                    
                    // Update active button
                    container.querySelectorAll('.chart-type-btn').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Switch chart
                    Object.keys(chartInstances).forEach(key => {
                        if (key.startsWith(chartName)) {
                            chartInstances[key].destroy();
                        }
                    });
                    
                    // Recreate the selected chart type
                    const canvas = container.querySelector('canvas');
                    const ctx = canvas.getContext('2d');
                    
                    // Get original data from the active chart
                    const activeChart = Object.values(chartInstances).find(chart => 
                        chart.canvas === canvas && !chart.destroyed
                    );
                    
                    if (activeChart) {
                        const data = activeChart.data;
                        chartInstances[chartType] = new Chart(ctx, {
                            type: getChartTypeFromName(chartType),
                            data: data,
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                ...getChartOptions(chartType)
                            }
                        });
                    }
                });
            });
        }

        function getChartTypeFromName(chartType) {
            if (chartType.includes('Pie')) return 'pie';
            if (chartType.includes('Doughnut')) return 'doughnut';
            if (chartType.includes('Line')) return 'line';
            if (chartType.includes('Area')) return 'line';
            if (chartType.includes('Bar') || chartType.includes('Horizontal')) return 'bar';
            return 'bar';
        }

        function getChartOptions(chartType) {
            const options = {};
            
            if (chartType.includes('Horizontal')) {
                options.indexAxis = 'y';
            }
            
            if (chartType.includes('Area')) {
                options.datasets = {
                    line: {
                        fill: true
                    }
                };
            }
            
            return options;
        }

        function updateChartsForTheme() {
            // Charts will automatically update due to CSS variables
            Object.values(chartInstances).forEach(chart => {
                if (chart && !chart.destroyed) {
                    chart.update();
                }
            });
        }

        // Export functionality
        document.getElementById('downloadReportBtn').addEventListener('click', function() {
            const format = document.getElementById('export_format').value;
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            
            if (!startDate || !endDate) {
                alert('Please select date range first');
                return;
            }
            
            // Show loading state
            const btn = this;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<span class="spinner me-2"></span>Generating...';
            btn.disabled = true;
            
            // Generate and download report
            generateReport(format, startDate, endDate);
            
            // Reset button after 3 seconds
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }, 3000);
        });

        function generateReport(format, startDate, endDate) {
            const reportData = {
                period: `${startDate} to ${endDate}`,
                stats: {
                    total: <?php echo $stats['total']; ?>,
                    approved: <?php echo $stats['approved']; ?>,
                    rejected: <?php echo $stats['rejected']; ?>,
                    pending: <?php echo $stats['pending']; ?>
                },
                topFacilities: [
                    <?php
                    $top_facilities->data_seek(0);
                    while ($facility = $top_facilities->fetch_assoc()) {
                        echo "{name: '" . addslashes($facility['facility_name']) . "', count: " . $facility['count'] . "},";
                    }
                    ?>
                ]
            };

            switch (format) {
                case 'excel':
                    exportToExcel(reportData);
                    break;
                case 'csv':
                    exportToCSV(reportData);
                    break;
                case 'pdf':
                    exportToPDF(reportData);
                    break;
            }
        }

        function exportToExcel(data) {
            const wb = XLSX.utils.book_new();
            
            // Summary sheet
            const summaryData = [
                ['Facility Booking System - Report'],
                ['Period:', data.period],
                [],
                ['Statistics'],
                ['Total Requests', data.stats.total],
                ['Approved Requests', data.stats.approved],
                ['Rejected Requests', data.stats.rejected],
                ['Pending Requests', data.stats.pending],
                [],
                ['Top Facilities']
            ];
            
            data.topFacilities.forEach(facility => {
                summaryData.push([facility.name, facility.count]);
            });
            
            const ws = XLSX.utils.aoa_to_sheet(summaryData);
            XLSX.utils.book_append_sheet(wb, ws, 'Summary');
            
            // Generate and download
            XLSX.writeFile(wb, `facility_report_${data.period.replace(/ /g, '_')}.xlsx`);
        }

        function exportToCSV(data) {
            let csv = 'Facility Booking System Report\n';
            csv += `Period: ${data.period}\n\n`;
            csv += 'Statistics\n';
            csv += `Total Requests,${data.stats.total}\n`;
            csv += `Approved Requests,${data.stats.approved}\n`;
            csv += `Rejected Requests,${data.stats.rejected}\n`;
            csv += `Pending Requests,${data.stats.pending}\n\n`;
            csv += 'Top Facilities\n';
            csv += 'Facility Name,Request Count\n';
            
            data.topFacilities.forEach(facility => {
                csv += `${facility.name},${facility.count}\n`;
            });
            
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `facility_report_${data.period.replace(/ /g, '_')}.csv`;
            a.click();
            window.URL.revokeObjectURL(url);
        }

        function exportToPDF(data) {
            const docDefinition = {
                content: [
                    { text: 'Facility Booking System - Report', style: 'header' },
                    { text: `Period: ${data.period}`, style: 'subheader' },
                    { text: '\nStatistics\n', style: 'sectionHeader' },
                    {
                        table: {
                            body: [
                                ['Total Requests', data.stats.total],
                                ['Approved Requests', data.stats.approved],
                                ['Rejected Requests', data.stats.rejected],
                                ['Pending Requests', data.stats.pending]
                            ]
                        }
                    },
                    { text: '\nTop Facilities\n', style: 'sectionHeader' },
                    {
                        table: {
                            headerRows: 1,
                            body: [
                                ['Facility Name', 'Request Count'],
                                ...data.topFacilities.map(f => [f.name, f.count])
                            ]
                        }
                    }
                ],
                styles: {
                    header: {
                        fontSize: 18,
                        bold: true,
                        margin: [0, 0, 0, 10]
                    },
                    subheader: {
                        fontSize: 14,
                        margin: [0, 0, 0, 10]
                    },
                    sectionHeader: {
                        fontSize: 14,
                        bold: true,
                        margin: [0, 10, 0, 5]
                    }
                }
            };
            
            pdfMake.createPdf(docDefinition).download(`facility_report_${data.period.replace(/ /g, '_')}.pdf`);
        }
    </script>
</body>
</html>