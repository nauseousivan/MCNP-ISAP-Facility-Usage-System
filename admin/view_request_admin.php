<?php
session_start();
require_once '../config.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'Admin') {
    header("Location: ../index.php");
    exit();
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Get request ID
if (!isset($_GET['id'])) {
    header("Location: requests.php");
    exit();
}

$request_id = $_GET['id'];

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $admin_notes = $_POST['admin_notes'] ?? '';

    if ($action === 'approve') {
        $status = 'approved';
    } elseif ($action === 'reject') {
        $status = 'rejected';
    } else { // 'cancel'
        $status = 'cancelled';
        $admin_notes = 'Request cancelled by administrator.';
    }

    $sql = "UPDATE facility_requests SET status = ?, admin_notes = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $status, $admin_notes, $request_id);
    $stmt->execute();
    
    header("Location: view_request_admin.php?id=$request_id&success=1");
    exit();
}

// Get request details
$sql = "SELECT fr.*, u.name as user_name, u.email as user_email 
        FROM facility_requests fr 
        JOIN users u ON fr.user_id = u.id 
        WHERE fr.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();

if (!$request) {
    header("Location: requests.php");
    exit();
}

// Get facility details
$facility_sql = "SELECT * FROM facility_request_details WHERE request_id = ?";
$facility_stmt = $conn->prepare($facility_sql);
$facility_stmt->bind_param("i", $request_id);
$facility_stmt->execute();
$facilities = $facility_stmt->get_result();

// Get theme preference
$theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'light';
?>
<!DOCTYPE html>
<html lang="en" class="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Request - Admin</title>
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
            border-radius: 12px;
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
        
        .btn-back {
            padding: 8px 16px;
            background: var(--muted);
            color: var(--foreground);
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-back:hover {
            background: var(--accent);
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
        
        .alert svg {
            flex-shrink: 0;
        }
        
        /* Card */
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.03);
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
        
        /* Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .info-item label {
            font-size: 12px;
            color: var(--muted-foreground);
            margin-bottom: 6px;
            display: block;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        
        .info-item .value {
            font-weight: 500;
            color: var(--foreground);
            font-size: 14px;
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
        .badge.cancelled {
            background: var(--muted);
            color: var(--muted-foreground);
        }
        
        /* Facility Item */
        .facility-item {
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 16px;
            background: var(--card);
        }
        
        .facility-item h4 {
            font-size: 16px;
            color: var(--foreground);
            margin-bottom: 16px;
            font-weight: 600;
        }
        
        .facility-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }
        
        /* Action Form */
        .action-form {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: var(--foreground);
            margin-bottom: 8px;
        }
        
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            background: var(--background);
            color: var(--foreground);
            font-family: inherit;
            resize: vertical;
            min-height: 100px;
        }
        
        .action-buttons {
            display: flex;
            gap: 12px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .btn-approve {
            background: var(--success);
            color: white;
        }
        
        .btn-reject {
            background: var(--danger);
            color: white;
        }
        
        .btn-cancel {
            background: var(--warning);
            color: white;
        }
        
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
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
            
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 768px) {
    .info-grid {
        grid-template-columns: 1fr;
        gap: 16px;
    }
    
    .facility-details {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    
    .action-buttons {
        flex-direction: column;
        gap: 12px;
    }
    
    .btn {
        width: 100%;
    }
    
    .top-nav-left {
        gap: 12px;
    }
}
/* Add to the existing responsive section */
@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
        width: 280px;
        z-index: 1000;
    }
    
    .sidebar.mobile-open {
        transform: translateX(0);
    }
    
    .main-content {
        margin-left: 0;
    }
    
    .mobile-menu-btn {
        display: flex;
        align-items: center;
        justify-content: center;
    }
}

/* Add to ensure mobile menu button is visible */
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

@media (max-width: 768px) {
    .mobile-menu-btn {
        display: flex;
    }
}
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <img src="../combined-logo.png" alt="Logo">
            <span>Admin Panel</span>
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
                <a href="requests.php" class="active">
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
            <li class="sidebar-divider">
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
    <div class="main-content">
        <!-- Top Nav -->
        <nav class="top-nav">
            <div class="top-nav-left">
                <a href="requests.php" class="btn-back">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="16" height="16">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Back
                </a>
                <h1>Request Details</h1>
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
                <button class="mobile-menu-btn" onclick="toggleSidebar()">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
    </svg>
</button>
            </div>
        </nav>

        <!-- Content -->
        <main class="content">
            <?php if (isset($_GET['success'])): ?>
                <div class="alert success">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 01-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Request updated successfully!
                </div>
            <?php endif; ?>

            <!-- Request Information -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Request Information</h2>
                    <span class="badge <?php echo strtolower($request['status']); ?>">
                        <?php echo ucfirst($request['status']); ?>
                    </span>
                </div>
                <div class="info-grid">
                    <div class="info-item">
                        <label>Control Number</label>
                        <div class="value"><?php echo htmlspecialchars($request['control_number']); ?></div>
                    </div>
                    <div class="info-item">
                        <label>Requestor</label>
                        <div class="value"><?php echo htmlspecialchars($request['user_name']); ?></div>
                    </div>
                    <div class="info-item">
                        <label>Email</label>
                        <div class="value"><?php echo htmlspecialchars($request['user_email']); ?></div>
                    </div>
                    <div class="info-item">
                        <label>Department</label>
                        <div class="value"><?php echo htmlspecialchars($request['department']); ?></div>
                    </div>
                    <div class="info-item">
                        <label>Phone</label>
                        <div class="value"><?php echo htmlspecialchars($request['phone_number'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="info-item">
                        <label>Event Type</label>
                        <div class="value"><?php echo htmlspecialchars($request['event_type']); ?></div>
                    </div>
                    <div class="info-item">
                        <label>Submitted</label>
                        <div class="value"><?php echo date('M d, Y g:i A', strtotime($request['created_at'])); ?></div>
                    </div>
                </div>
                
                <?php if ($request['admin_notes']): ?>
                <div class="info-item" style="margin-top: 20px;">
                    <label>Admin Notes</label>
                    <div class="value" style="background: var(--muted); padding: 12px; border-radius: 8px; margin-top: 8px; border: 1px solid var(--border);">
                        <?php echo nl2br(htmlspecialchars($request['admin_notes'])); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Facility Details -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Requested Facilities</h2>
                </div>
                <?php while ($facility = $facilities->fetch_assoc()): ?>
                    <div class="facility-item">
                        <h4><?php echo htmlspecialchars($facility['facility_name']); ?></h4>
                        <div class="facility-details">
                            <div class="info-item">
                                <label>Date Needed</label>
                                <div class="value"><?php echo date('M d, Y', strtotime($facility['date_needed'])); ?></div>
                            </div>
                            <div class="info-item">
                                <label>Time Needed</label>
                                <div class="value"><?php echo htmlspecialchars($facility['time_needed']); ?></div>
                            </div>
                            <div class="info-item">
                                <label>Total Hours</label>
                                <div class="value"><?php echo $facility['total_hours']; ?> hours</div>
                            </div>
                            <div class="info-item">
                                <label>Participants</label>
                                <div class="value"><?php echo $facility['total_participants']; ?></div>
                            </div>
                        </div>
                        <?php if ($facility['remarks']): ?>
                        <div class="info-item" style="margin-top: 12px;">
                            <label>Remarks</label>
                            <div class="value"><?php echo htmlspecialchars($facility['remarks']); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            </div>

            <!-- Action Form -->
            <?php if (in_array($request['status'], ['pending', 'approved'])): ?>
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Take Action</h2>
                </div>
                <form method="POST" class="action-form">
                    <div class="form-group">
                        <label>Admin Notes (Optional)</label>
                        <textarea name="admin_notes" placeholder="Add notes about this decision..."></textarea>
                    </div>
                    <div class="action-buttons">
                        <?php if ($request['status'] === 'pending'): ?>
                            <button type="submit" name="action" value="approve" class="btn btn-approve">Approve Request</button>
                            <button type="submit" name="action" value="reject" class="btn btn-reject" onclick="return confirm('Are you sure you want to reject this request? Any notes you have entered will be sent to the user.')">Reject Request</button>
                        <?php endif; ?>
                        <button type="submit" name="action" value="cancel" class="btn btn-cancel" onclick="return confirm('Are you sure you want to CANCEL this request? This action cannot be undone.')">Cancel Request</button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
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
    </script>
</body>
</html>
