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
?>
<!DOCTYPE html>
<html lang="en">
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
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f7fa;
        }
        
        .header {
            background: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
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
            color: #1a1a1a;
        }
        
        .header-brand .brand-subtitle {
            font-size: 12px;
            color: #6b7280;
        }
        
        .btn-back {
            padding: 8px 16px;
            background: white;
            color: #1a1a1a;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            transition: background 0.2s;
        }
        
        .btn-back:hover {
            background: #f9fafb;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 16px 32px;
        }
        
        .page-header {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .page-header h2 {
            font-size: 24px;
            color: #1a1a1a;
            margin-bottom: 8px;
        }
        
        .filters {
            display: flex;
            gap: 12px;
            margin-top: 20px;
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
            font-size: 14px;
        }
        
        .filter-btn:hover,
        .filter-btn.active {
            background: #1a1a1a;
            color: white;
            border-color: #1a1a1a;
        }
        
        .search-box {
            flex: 1;
            min-width: 200px;
        }
        
        .search-box input {
            width: 100%;
            padding: 8px 16px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .requests-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow-x: auto;
        }
        
        .requests-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
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
        
        .btn-view {
            padding: 8px 16px;
            background: #f3f4f6;
            color: #1a1a1a;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
        }
        
        .btn-view:hover {
            background: #e5e7eb;
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
            color: #6b7280;
        }
        
        .no-requests p {
            font-size: 16px;
            margin-bottom: 16px;
        }
        
        .btn-create {
            display: inline-block;
            padding: 12px 24px;
            background: #1a1a1a;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            transition: all 0.2s;
        }
        
        .btn-create:hover {
            background: #000;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-brand">
            <img src="<?php echo htmlspecialchars($logo_file); ?>" alt="Logo">
            <div class="brand-text">
                <div class="brand-title"><?php echo htmlspecialchars($portal_name); ?></div>
                <div class="brand-subtitle">Your Requests</div>
            </div>
        </div>
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