<?php
session_start();
require_once 'theme_loader.php';
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$user_id = $_SESSION['user_id'];

// Handle request cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel') {
    $request_id_to_cancel = $_POST['request_id'];

    // Security check: ensure the user owns this request and it's pending or approved
    $sql = "SELECT id FROM facility_requests WHERE id = ? AND user_id = ? AND (status = 'pending' OR status = 'approved')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $request_id_to_cancel, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Proceed with cancellation
        $cancellation_note = "Request cancelled by user.";
        $update_sql = "UPDATE facility_requests SET status = 'cancelled', admin_notes = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("si", $cancellation_note, $request_id_to_cancel);
        if ($update_stmt->execute()) {
            $_SESSION['success_message'] = "Request successfully cancelled.";
        }
    }
    header("Location: my_requests.php");
    exit();
}

// Get theme directly from database as fallback
$theme = 'light';
$sql = "SELECT theme FROM user_preferences WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $prefs = $result->fetch_assoc();
    $theme = $prefs['theme'];
}

// Get filter
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query
$sql = "SELECT * FROM facility_requests WHERE user_id = ?";
$params = [$user_id];
$types = "i";

if ($filter !== 'all') {
    $sql .= " AND status = ?";
    $params[] = $filter;
    $types .= "s";
}

if ($search) {
    $sql .= " AND (control_number LIKE ? OR event_type LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

$sql .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$requests = $stmt->get_result();

$logo_file = $GLOBALS['logo_file'];
$portal_name = $GLOBALS['portal_name'];
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($theme); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Requests - MCNP-ISAP</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --bg-primary: #ffffff;
            --bg-secondary: #f5f7fa;
            --text-primary: #1a1a1a;
            --text-secondary: #6b7280;
            --border-color: #e5e7eb;
            --card-bg: #ffffff;
            --navbar-bg: #ffffff;
            --input-bg: #ffffff;
            --input-border: #d1d5db;
            --input-text: #1a1a1a;
            --btn-bg: #ffffff;
            --btn-text: #1a1a1a;
            --btn-border: #e5e7eb;
            --btn-hover: #f9fafb;
            --btn-primary-bg: #1a1a1a;
            --btn-primary-text: #ffffff;
            --btn-primary-hover: #000000;
            --filter-btn-bg: #ffffff;
            --filter-btn-text: #6b7280;
            --filter-btn-border: #d1d5db;
            --filter-btn-hover: #f9fafb;
            --filter-btn-active-bg: #1a1a1a;
            --filter-btn-active-text: #ffffff;
            --filter-btn-active-border: #1a1a1a;
            --table-header-bg: #ffffff;
            --table-header-text: #6b7280;
            --table-border: #e5e7eb;
            --table-row-bg: #ffffff;
            --table-row-hover: #f9fafb;
            --status-pending-bg: #fef3c7;
            --status-pending-text: #92400e;
            --status-approved-bg: #d1fae5;
            --status-approved-text: #065f46;
            --status-rejected-bg: #fee2e2;
            --status-rejected-text: #991b1b;
            --status-cancelled-bg: #e5e7eb;
            --status-cancelled-text: #4b5563;
        }

        [data-theme="dark"] {
            --bg-primary: #1a1a1a;
            --bg-secondary: #2d2d2d;
            --text-primary: #ffffff;
            --text-secondary: #9ca3af;
            --border-color: #404040;
            --card-bg: #2d2d2d;
            --navbar-bg: #2d2d2d;
            --input-bg: #404040;
            --input-border: #4b5563;
            --input-text: #ffffff;
            --btn-bg: #404040;
            --btn-text: #ffffff;
            --btn-border: #4b5563;
            --btn-hover: #4b5563;
            --btn-primary-bg: #ffffff;
            --btn-primary-text: #1a1a1a;
            --btn-primary-hover: #e5e7eb;
            --filter-btn-bg: #404040;
            --filter-btn-text: #9ca3af;
            --filter-btn-border: #4b5563;
            --filter-btn-hover: #4b5563;
            --filter-btn-active-bg: #ffffff;
            --filter-btn-active-text: #1a1a1a;
            --filter-btn-active-border: #ffffff;
            --table-header-bg: #404040;
            --table-header-text: #9ca3af;
            --table-border: #404040;
            --table-row-bg: #2d2d2d;
            --table-row-hover: #404040;
            --status-pending-bg: #78350f;
            --status-pending-text: #fef3c7;
            --status-approved-bg: #065f46;
            --status-approved-text: #d1fae5;
            --status-rejected-bg: #991b1b;
            --status-rejected-text: #fee2e2;
            --status-cancelled-bg: #4b5563;
            --status-cancelled-text: #e5e7eb;
        }

        @font-face {
            font-family: 'Geist Sans';
            src: url('node_modules/geist/dist/fonts/geist-sans/Geist-Variable.woff2') format('woff2');
            font-weight: 100 900;
            font-style: normal;
        }

        /* New Theme Palettes */
        [data-theme="blue"] {
            --bg-primary: #ffffff;
            --bg-secondary: #f0f9ff; /* sky-50 */
            --text-primary: #0c4a6e; /* sky-900 */
            --text-secondary: #38bdf8; /* sky-400 */
            --border-color: #e0f2fe; /* sky-100 */
            --accent-color: #0ea5e9; /* sky-500 */
        }

        [data-theme="pink"] {
            --bg-primary: #ffffff;
            --bg-secondary: #fdf2f8; /* pink-50 */
            --text-primary: #831843; /* pink-900 */
            --text-secondary: #f472b6; /* pink-400 */
            --border-color: #fce7f3; /* pink-100 */
            --accent-color: #ec4899; /* pink-500 */
        }

        [data-theme="green"] {
            --bg-primary: #ffffff;
            --bg-secondary: #f0fdf4; /* green-50 */
            --text-primary: #14532d; /* green-900 */
            --text-secondary: #4ade80; /* green-400 */
            --border-color: #dcfce7; /* green-100 */
            --accent-color: #22c55e; /* green-500 */
        }

        [data-theme="purple"] {
            --bg-primary: #ffffff;
            --bg-secondary: #f5f3ff; /* violet-50 */
            --text-primary: #4c1d95; /* violet-900 */
            --text-secondary: #a78bfa; /* violet-400 */
            --border-color: #ede9fe; /* violet-100 */
            --accent-color: #8b5cf6; /* violet-500 */
        }

        body {
            font-family: 'Geist Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-secondary);
            color: var(--text-primary);
        }
        
        .header {
            background: var(--navbar-bg);
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .header-brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .header-brand img {
            height: 40px;
            width: auto;
        }
        
        .header-brand .brand-text {
            display: flex;
            flex-direction: column;
        }
        
        .header-brand .brand-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .header-brand .brand-subtitle {
            font-size: 12px;
            color: var(--text-secondary);
        }
        
        .btn-back {
            padding: 8px 16px;
            background: var(--btn-bg);
            color: var(--btn-text);
            border: 1px solid var(--btn-border);
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            transition: background 0.2s;
        }
        
        .btn-back:hover {
            background: var(--btn-hover);
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 16px 32px;
        }
        
        .page-header {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: 1px solid var(--border-color);
        }
        
        .page-header h2 {
            font-size: 24px;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        
        .page-header p {
            color: var(--text-secondary);
        }
        
        .filters {
            display: flex;
            gap: 12px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 8px 16px;
            border: 1px solid var(--filter-btn-border);
            background: var(--filter-btn-bg);
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            color: var(--filter-btn-text);
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .filter-btn:hover,
        .filter-btn.active {
            background: var(--filter-btn-active-bg);
            color: var(--filter-btn-active-text);
            border-color: var(--filter-btn-active-border);
        }
        
        .search-box {
            flex: 1;
            min-width: 200px;
        }
        
        .search-box input {
            width: 100%;
            padding: 8px 16px;
            background: var(--input-bg);
            color: var(--input-text);
            border: 1px solid var(--input-border);
            border-radius: 6px;
            font-size: 14px;
        }
        
        .search-box input:focus {
            outline: none;
            border-color: var(--text-primary);
        }
        
        .requests-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow-x: auto;
            border: 1px solid var(--border-color);
        }
        
        .requests-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }
        
        .requests-table th {
            text-align: left;
            padding: 12px;
            border-bottom: 2px solid var(--table-border);
            color: var(--table-header-text);
            font-weight: 600;
            font-size: 14px;
            background: var(--table-header-bg);
        }
        
        .requests-table td {
            padding: 16px 12px;
            border-bottom: 1px solid var(--table-border);
            background: var(--table-row-bg);
        }
        
        .requests-table tr:hover td {
            background: var(--table-row-hover);
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
        }
        
        .status-badge.pending {
            background: var(--status-pending-bg);
            color: var(--status-pending-text);
        }
        
        .status-badge.approved {
            background: var(--status-approved-bg);
            color: var(--status-approved-text);
        }
        
        .status-badge.rejected {
            background: var(--status-rejected-bg);
            color: var(--status-rejected-text);
        }
        
        .status-badge.cancelled {
            background: var(--status-cancelled-bg);
            color: var(--status-cancelled-text);
        }
        
        .btn-view {
            padding: 8px 16px;
            background: var(--btn-bg);
            color: var(--btn-text);
            border: 1px solid var(--btn-border);
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .btn-view:hover {
            background: var(--btn-hover);
        }

        .btn-cancel {
            padding: 8px 16px;
            background: var(--status-rejected-bg);
            color: var(--status-rejected-text);
            border: 1px solid var(--status-rejected-bg);
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.2s;
            cursor: pointer;
            margin-left: 8px;
        }

        /* Mobile Responsive Styles */
        @media (max-width: 768px) {
            .header {
                padding: 12px 16px;
                margin-bottom: 20px;
            }
            
            .header-brand img {
                height: 36px;
            }
            
            .header-brand .brand-title {
                font-size: 14px;
            }
            
            .header-brand .brand-subtitle {
                font-size: 11px;
            }
            
            .btn-back {
                padding: 6px 12px;
                font-size: 13px;
            }
            
            .container {
                padding: 0 12px 24px;
            }
            
            .page-header {
                padding: 20px;
                margin-bottom: 16px;
            }
            
            .page-header h2 {
                font-size: 22px;
            }
            
            .filters {
                gap: 8px;
                margin-top: 16px;
            }
            
            .filter-btn {
                padding: 6px 12px;
                font-size: 13px;
            }
            
            .search-box {
                min-width: 100%;
                order: -1;
            }
            
            .requests-card {
                padding: 16px;
                border-radius: 10px;
            }
            
            .requests-table {
                min-width: 500px;
            }
            
            .requests-table th {
                padding: 10px 8px;
                font-size: 13px;
            }
            
            .requests-table td {
                padding: 12px 8px;
                font-size: 14px;
            }
            
            .status-badge {
                padding: 4px 10px;
                font-size: 11px;
            }
            
            .btn-view {
                padding: 6px 12px;
                font-size: 13px;
            }
        }
        
        @media (max-width: 480px) {
            .header {
                padding: 10px 12px;
            }
            
            .header-brand {
                gap: 8px;
            }
            
            .header-brand img {
                height: 32px;
            }
            
            .header-brand .brand-title {
                font-size: 13px;
            }
            
            .header-brand .brand-subtitle {
                font-size: 10px;
            }
            
            .btn-back {
                padding: 6px 10px;
                font-size: 12px;
            }
            
            .container {
                padding: 0 8px 20px;
            }
            
            .page-header {
                padding: 16px;
                border-radius: 10px;
            }
            
            .page-header h2 {
                font-size: 20px;
            }
            
            .filters {
                flex-direction: column;
                gap: 6px;
            }
            
            .filter-btn {
                text-align: center;
                padding: 8px;
            }
            
            .search-box input {
                padding: 8px 12px;
                font-size: 13px;
            }
            
            .requests-card {
                padding: 12px;
                border-radius: 8px;
            }
            
            .requests-table {
                min-width: 450px;
            }
            
            .requests-table th {
                padding: 8px 6px;
                font-size: 12px;
            }
            
            .requests-table td {
                padding: 10px 6px;
                font-size: 13px;
            }
        }

        /* For very small screens */
        @media (max-width: 360px) {
            .header-brand .brand-text {
                max-width: 120px;
            }
            
            .page-header h2 {
                font-size: 18px;
            }
            
            .requests-table {
                min-width: 400px;
            }
        }

        /* No requests message */
        .no-requests {
            text-align: center;
            padding: 40px;
            color: var(--text-secondary);
        }
        
        .no-requests p {
            font-size: 16px;
            margin-bottom: 16px;
        }
        
        .btn-create {
            display: inline-block;
            padding: 12px 24px;
            background: var(--btn-primary-bg);
            color: var(--btn-primary-text);
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            transition: all 0.2s;
        }
        
        .btn-create:hover {
            background: var(--btn-primary-hover);
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <header class="header">
        <a href="dashboard.php" style="text-decoration: none; color: inherit;">
            <div class="header-brand">
                <img src="<?php echo htmlspecialchars($logo_file); ?>" alt="Logo">
                <div class="brand-text">
                    <div class="brand-title"><?php echo htmlspecialchars($portal_name); ?></div>
                    <div class="brand-subtitle">Your Requests</div>
                </div>
            </div>
        </a>
        <a href="dashboard.php" class="btn-back">Back</a>
    </header>

    <div class="container">
        <div class="page-header">
            <h2>My Requests</h2>
            <p>View and manage all your facility requests</p>
            
            <form method="GET" class="filters">
                <div class="search-box">
                    <input type="text" name="search" placeholder="Search by control number or event type..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <a href="?filter=all" class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">All</a>
                <a href="?filter=pending" class="filter-btn <?php echo $filter === 'pending' ? 'active' : ''; ?>">Pending</a>
                <a href="?filter=approved" class="filter-btn <?php echo $filter === 'approved' ? 'active' : ''; ?>">Approved</a>
                <a href="?filter=cancelled" class="filter-btn <?php echo $filter === 'cancelled' ? 'active' : ''; ?>">Cancelled</a>
                <a href="?filter=rejected" class="filter-btn <?php echo $filter === 'rejected' ? 'active' : ''; ?>">Rejected</a>
            </form>
        </div>

        <div class="requests-card">
            <?php if ($requests->num_rows > 0): ?>
                <table class="requests-table">
                    <thead>
                        <tr>
                            <th>Control Number</th>
                            <th>Event Type</th>
                            <th>Date Submitted</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($request = $requests->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($request['control_number']); ?></strong></td>
                                <td><?php echo htmlspecialchars($request['event_type']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($request['created_at'])); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $request['status']; ?>">
                                        <?php echo ucfirst($request['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="view_request.php?id=<?php echo $request['id']; ?>" class="btn-view">View</a>
                                    <?php if (in_array($request['status'], ['pending', 'approved'])): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to cancel this request?');">
                                            <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                            <button type="submit" name="action" value="cancel" class="btn-cancel">Cancel</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-requests">
                    <p>No requests found.</p>
                    <?php if ($filter !== 'all' || $search): ?>
                        <a href="?filter=all" class="filter-btn">Show All Requests</a>
                    <?php else: ?>
                        <a href="create_request.php" class="btn-create">Create Your First Request</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php include 'chat_bot.php'; ?>
</body>
</html>