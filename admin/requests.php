<?php
session_start();
require_once '../config.php';
require_once '../functions.php'; // ADD THIS LINE

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'Admin') {
    header("Location: ../index.php");
    exit();
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $request_id = $_POST['request_id'];
    $action = $_POST['action'];
    $admin_notes = $_POST['admin_notes'] ?? '';
    
    $new_status = ($action === 'approve') ? 'approved' : 'rejected';
    
    // First get the request details to know the user_id
    $request_sql = "SELECT user_id, control_number FROM facility_requests WHERE id = ?";
    $request_stmt = $conn->prepare($request_sql);
    $request_stmt->bind_param("i", $request_id);
    $request_stmt->execute();
    $request_data = $request_stmt->get_result()->fetch_assoc();
    
    if ($request_data) {
        $user_id = $request_data['user_id'];
        $control_number = $request_data['control_number'];
        
        // Update the request status (FIXED - using lowercase status)
        $sql = "UPDATE facility_requests SET status = ?, admin_notes = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $new_status, $admin_notes, $request_id);
        
        if ($stmt->execute()) {
            // Add notification to the user
            $notification_type = ($action === 'approve') ? 'request_approved' : 'request_rejected';
            add_action_notification($conn, $user_id, $notification_type, ['control_number' => $control_number]);
            
            header("Location: requests.php?success=1");
            exit();
        }
    }
}

// Get filter
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query (FIXED - handle both 'Pending' and 'pending' cases)
$sql = "SELECT fr.*, u.name as user_name, u.email as user_email 
        FROM facility_requests fr 
        JOIN users u ON fr.user_id = u.id WHERE 1=1";
$params = [];
$types = "";

if ($filter !== 'all') {
    // Handle both uppercase and lowercase status values
    if ($filter === 'pending') {
        $sql .= " AND (fr.status = 'pending' OR fr.status = 'Pending')";
    } else {
        $sql .= " AND fr.status = ?";
        $params[] = $filter;
        $types .= "s";
    }
}

if ($search) {
    $sql .= " AND (fr.control_number LIKE ? OR fr.event_type LIKE ? OR u.name LIKE ?)";
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
?>
<!DOCTYPE html>
<html lang="en">
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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .top-bar h1 {
            font-size: 28px;
            color: #1a1a1a;
        }
        
        .filters {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .filters-row {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 8px 16px;
            border: 1px solid #d1d5db;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            color: #6b7280;
        }
        
        .filter-btn:hover,
        .filter-btn.active {
            background: #1a1a1a;
            color: white;
            border-color: #1a1a1a;
        }
        
        .search-box {
            flex: 1;
            min-width: 250px;
        }
        
        .search-box input {
            width: 100%;
            padding: 8px 16px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 15px;
        }
        
        .requests-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .requests-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .requests-table th {
            text-align: left;
            padding: 12px;
            border-bottom: 2px solid #e5e7eb;
            color: #6b7280;
            font-weight: 600;
            font-size: 14px;
        }
        
        .requests-table td {
            padding: 16px 12px;
            border-bottom: 1px solid #e5e7eb;
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
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-badge.approved {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-badge.rejected {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .btn-action {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            font-size: 13px;
            margin-right: 8px;
        }
        
        .btn-view {
            background: #f3f4f6;
            color: #1a1a1a;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-approve {
            background: #10b981;
            color: white;
        }
        
        .btn-reject {
            background: #ef4444;
            color: white;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        
        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            border-radius: 12px;
            padding: 32px;
            max-width: 500px;
            width: 90%;
        }
        
        .modal-content h3 {
            font-size: 20px;
            margin-bottom: 16px;
        }
        
        .modal-content textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-family: inherit;
            margin-bottom: 16px;
            min-height: 100px;
        }
        
        .modal-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }
        
        .btn-cancel {
            padding: 10px 20px;
            background: #f3f4f6;
            color: #1a1a1a;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
        }
        
        .btn-confirm {
            padding: 10px 20px;
            background: #1a1a1a;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 24px;
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
                <a href="requests.php" class="active">
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
            <h1>Manage Requests</h1>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert-success">
                Request status updated successfully!
            </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="filters">
            <form method="GET" class="filters-row">
                <a href="?filter=all" class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">All</a>
                <a href="?filter=pending" class="filter-btn <?php echo $filter === 'pending' ? 'active' : ''; ?>">Pending</a>
                <a href="?filter=approved" class="filter-btn <?php echo $filter === 'approved' ? 'active' : ''; ?>">Approved</a>
                <a href="?filter=rejected" class="filter-btn <?php echo $filter === 'rejected' ? 'active' : ''; ?>">Rejected</a>
                <div class="search-box">
                    <input type="text" name="search" placeholder="Search requests..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </form>
        </div>

        <!-- Requests Table -->
        <div class="requests-card">
            <table class="requests-table">
                <thead>
                    <tr>
                        <th>Control Number</th>
                        <th>Requestor</th>
                        <th>Event Type</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($request = $requests->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($request['control_number']); ?></strong></td>
                            <td><?php echo htmlspecialchars($request['user_name']); ?></td>
                            <td><?php echo htmlspecialchars($request['event_type']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($request['created_at'])); ?></td>
                            <td>
                               <span class="status-badge <?php echo strtolower($request['status']); ?>">
                                    <?php echo ucfirst($request['status']); ?>
                                </span>
                            </td>
                      <td>
    <a href="view_request_admin.php?id=<?php echo $request['id']; ?>" class="btn-action btn-view">View</a>
    
    <!-- DEBUG: Show status and condition -->
    <div style="font-size: 10px; color: red;">
        Status: <?php echo $request['status']; ?><br>
        Condition: <?php echo (strtolower($request['status']) === 'pending') ? 'TRUE' : 'FALSE'; ?>
    </div>
    
    <?php if (strtolower($request['status']) === 'pending'): ?>
        <button class="btn-action btn-approve" onclick="openModal(<?php echo $request['id']; ?>, 'approve')">Approve</button>
        <button class="btn-action btn-reject" onclick="openModal(<?php echo $request['id']; ?>, 'reject')">Reject</button>
    <?php endif; ?>
</td>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </main>

    <!-- Modal -->
    <div id="actionModal" class="modal">
        <div class="modal-content">
            <h3 id="modalTitle">Confirm Action</h3>
            <form method="POST" action="">
                <input type="hidden" name="request_id" id="modalRequestId">
                <input type="hidden" name="action" id="modalAction">
                <label>Admin Notes (Optional)</label>
                <textarea name="admin_notes" placeholder="Add notes for the requestor..."></textarea>
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-confirm">Confirm</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(requestId, action) {
            document.getElementById('modalRequestId').value = requestId;
            document.getElementById('modalAction').value = action;
            document.getElementById('modalTitle').textContent = action === 'approve' ? 'Approve Request' : 'Reject Request';
            document.getElementById('actionModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('actionModal').classList.remove('active');
        }
    </script>
</body>
</html>