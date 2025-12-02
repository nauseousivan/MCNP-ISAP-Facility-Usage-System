<?php
session_start();
require_once 'functions.php';
require_once 'theme_loader.php';
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$user_id = $_SESSION['user_id'];

// Get user's transportation requests
$sql = "SELECT * FROM transportation_requests WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
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
    <title>My Transportation Requests - MCNP Service Portal</title>
    <!-- <link rel="stylesheet" href="css/my_requests_styles.css"> --> <!-- Using inline styles for consistency -->
    <style>
        /* Styles from my_requests.php */
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
        }

        [data-theme="dark"] {
            --bg-primary: #1a1a1a;
            --bg-secondary: #2d2d2d;
            --text-primary: #ffffff;
            --text-secondary: #9ca3af;
            --border-color: #404040;
            --card-bg: #2d2d2d;
            --navbar-bg: #2d2d2d;
        }

        @font-face {
            font-family: 'Geist Sans';
            src: url('node_modules/geist/dist/fonts/geist-sans/Geist-Variable.woff2') format('woff2');
            font-weight: 100 900;
            font-style: normal;
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
            background: var(--btn-bg, #ffffff);
            color: var(--btn-text, #1a1a1a);
            border: 1px solid var(--btn-border, #e5e7eb);
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            transition: background 0.2s;
        }
        
        .btn-back:hover {
            background: var(--btn-hover, #f9fafb);
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
        
        .page-header h1 {
            font-size: 24px;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        
        .page-header p {
            color: var(--text-secondary);
        }

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
            background: var(--btn-primary-bg, #1a1a1a);
            color: var(--btn-primary-text, #ffffff);
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            transition: all 0.2s;
        }
        
        .btn-create:hover {
            background: var(--btn-primary-hover, #000000);
            transform: translateY(-2px);
        }

        /* Use similar styles as my_requests.php */
        .requests-container {
            background: var(--bg-primary);
            padding: 24px;
            border-radius: 20px;
            border: 1px solid var(--border-color);
        }
        
        .request-item {
            background: var(--bg-secondary);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 16px;
            border: 1px solid var(--border-color);
        }
        
        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        
        .request-title {
            font-size: 18px;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        
        .request-meta {
            display: flex;
            gap: 16px;
            color: var(--text-secondary);
            font-size: 14px;
            margin-bottom: 12px;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-approved { background: #d1fae5; color: #065f46; }
        .status-rejected { background: #fee2e2; color: #991b1b; }
        .status-completed { background: #e0f2fe; color: #075985; }
    </style>
</head>
<body>
    <header class="header">
        <a href="dashboard.php" style="text-decoration: none; color: inherit;">
            <div class="header-brand">
                <img src="<?php echo htmlspecialchars($logo_file); ?>" alt="Logo">
                <div class="brand-text">
                    <div class="brand-title"><?php echo htmlspecialchars($portal_name); ?></div>
                    <div class="brand-subtitle">My Transportation Requests</div>
                </div>
            </div>
        </a>
        <a href="dashboard.php" class="btn-back">Back to Dashboard</a>
    </header>

    <div class="container">
        <div class="page-header">
            <h1>My Transportation Requests</h1>
            <p>View and manage your vehicle booking requests</p>
        </div>

        <div class="requests-container">
            <?php if (isset($_SESSION['success_message'])): ?>
                <div style="background: #d1fae5; color: #065f46; padding: 12px; border-radius: 8px; margin-bottom: 20px;">
                    <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
                </div>
            <?php endif; ?>

            <?php if ($requests->num_rows > 0): ?>
                <?php while ($request = $requests->fetch_assoc()): ?>
                    <div class="request-item">
                        <div class="request-header">
                            <div>
                                <div class="request-title">Control No: <?php echo htmlspecialchars($request['control_no']); ?></div>
                                <div class="request-meta">
                                    <span>Vehicle: <?php echo htmlspecialchars($request['vehicle_requested']); ?></span>
                                    <span>Date: <?php echo date('M j, Y', strtotime($request['date_vehicle_used'])); ?></span>
                                    <span>Time: <?php echo htmlspecialchars($request['time_departure']); ?> - <?php echo htmlspecialchars($request['time_return']); ?></span>
                                </div>
                            </div>
                            <span class="status-badge status-<?php echo $request['status']; ?>">
                                <?php echo ucfirst($request['status']); ?>
                            </span>
                        </div>
                        
                        <div style="color: var(--text-secondary); font-size: 14px; margin-bottom: 12px;">
                            <strong>Purpose:</strong> <?php echo htmlspecialchars($request['purpose']); ?>
                        </div>
                        
                        <div style="color: var(--text-secondary); font-size: 14px;">
                            <strong>Places to visit:</strong> <?php echo htmlspecialchars($request['places_to_visit']); ?>
                        </div>
                        
                        <?php if ($request['driver_assigned']): ?>
                            <div style="color: var(--text-primary); font-size: 14px; margin-top: 8px;">
                                <strong>Driver Assigned:</strong> <?php echo htmlspecialchars($request['driver_assigned']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-requests">
                    <p>No transportation requests found.</p>
                    <a href="transportation.php" style="color: var(--accent-color); text-decoration: none;">Request a vehicle now</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>