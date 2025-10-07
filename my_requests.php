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
        
        .navbar {
            background: #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            padding: 16px 32px;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            margin-bottom: 32px;
            border-radius: 0 0 12px 12px;
        }
        
        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .navbar-brand img {
            height: 44px;
            width: auto;
            border-radius: 0;
            background: #fff;
            box-shadow: 0 1px 4px rgba(0,0,0,0.04);
            padding: 4px;
            transition: box-shadow 0.2s;
        }
        
        .navbar-brand img:hover {
            box-shadow: 0 4px 16px rgba(102,126,234,0.12);
        }
        
        .brand-text {
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .brand-title {
            font-size: 18px;
            font-weight: 700;
            color: #1a1a1a;
            letter-spacing: 0.01em;
        }
        
        .brand-subtitle {
            font-size: 13px;
            color: #6b7280;
            margin-top: 2px;
            letter-spacing: 0.01em;
        }
        
        .navbar-menu {
            display: flex;
            gap: 16px;
        }
        
        .navbar-menu a {
            padding: 8px 16px;
            text-decoration: none;
            color: #6b7280;
            font-weight: 500;
            border-radius: 6px;
        }
        
        .navbar-menu a:hover,
        .navbar-menu a.active {
            background: #f3f4f6;
            color: #1a1a1a;
        }
        
        .container {
            max-width: 1200px;
            margin: 32px auto;
            padding: 0 32px;
        }
        
        .page-header {
            background: white;
            border-radius: 12px;
            padding: 32px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .page-header h2 {
            font-size: 28px;
            color: #1a1a1a;
            margin-bottom: 8px;
        }
        
        .filters {
            display: flex;
            gap: 16px;
            margin-top: 24px;
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
            padding: 32px;
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
        
        .btn-view {
            padding: 8px 16px;
            background: #f3f4f6;
            color: #1a1a1a;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
        }
        
        .btn-view:hover {
            background: #e5e7eb;
        }

        .header {
            background: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 16px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 0 0 12px 12px;
            margin-bottom: 32px;
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
        <a href="dashboard.php" class="btn-back">Back to Dashboard</a>
    </header>

    <div class="container">
        <div class="page-header">
            <h2>My Requests</h2>
            <p>View and manage all your facility requests</p>
            
            <form method="GET" class="filters">
                <a href="?filter=all" class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">All</a>
                <a href="?filter=pending" class="filter-btn <?php echo $filter === 'pending' ? 'active' : ''; ?>">Pending</a>
                <a href="?filter=approved" class="filter-btn <?php echo $filter === 'approved' ? 'active' : ''; ?>">Approved</a>
                <a href="?filter=rejected" class="filter-btn <?php echo $filter === 'rejected' ? 'active' : ''; ?>">Rejected</a>
                <div class="search-box">
                    <input type="text" name="search" placeholder="Search by control number or event type..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </form>
        </div>

        <div class="requests-card">
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
        </div>
    </div>
    <?php include 'chat_bot.php'; ?>
</body>
</html>
