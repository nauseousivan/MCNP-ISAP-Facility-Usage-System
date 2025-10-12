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

// Get theme preference
$theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'light';
?>
<!DOCTYPE html>
<html lang="en" class="<?php echo $theme; ?>">
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
        
        /* Facilities Grid */
        .facilities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 24px;
        }
        
        .facility-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 24px;
            transition: all 0.3s;
        }
        
        .facility-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
        }
        
        .facility-card h3 {
            font-size: 18px;
            color: var(--foreground);
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .facility-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        
        .stat-item {
            padding: 16px;
            background: var(--muted);
            border-radius: 10px;
            text-align: center;
        }
        
        .stat-label {
            font-size: 12px;
            color: var(--muted-foreground);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--foreground);
        }
        
        .stat-value.approved {
            color: var(--success);
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .facilities-grid {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                gap: 20px;
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
            
            .facilities-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            
            .facility-card {
                padding: 20px;
            }
            
            .facility-stats {
                gap: 12px;
            }
            
            .stat-value {
                font-size: 24px;
            }
        }

        @media (max-width: 480px) {
            .content {
                padding: 12px;
            }
            
            .top-nav {
                padding: 0 12px;
            }
            
            .facility-card {
                padding: 16px;
            }
            
            .facility-stats {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            
            .stat-item {
                padding: 12px;
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
                <h1>Facilities Overview</h1>
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
            <div class="facilities-grid">
                <?php foreach ($facility_stats as $facility => $stats): ?>
                    <div class="facility-card">
                        <h3><?php echo htmlspecialchars($facility); ?></h3>
                        <div class="facility-stats">
                            <div class="stat-item">
                                <div class="stat-label">Total</div>
                                <div class="stat-value"><?php echo $stats['total_bookings']; ?></div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-label">Approved</div>
                                <div class="stat-value approved"><?php echo $stats['approved_bookings']; ?></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
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
    </script>
</body>
</html>