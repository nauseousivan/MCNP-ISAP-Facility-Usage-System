<?php
session_start();
require_once 'config.php';
require_once 'theme_loader.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$theme = $GLOBALS['theme'];

$request_id = $_GET['id'] ?? 0;
$user_id = $_SESSION['user_id'];

// Get request details
$sql = "SELECT * FROM facility_requests WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $request_id, $user_id);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();

if (!$request) {
    header("Location: dashboard.php");
    exit();
}

// Get facility details
$sql = "SELECT * FROM facility_request_details WHERE request_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$facilities = $stmt->get_result();

$logo_file = $GLOBALS['logo_file'];
$portal_name = $GLOBALS['portal_name'];
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($theme); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Details - MCNP-ISAP</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --bg-primary: #ffffff;
            --bg-secondary: #fdfaf6;
            --bg-tertiary: #f9fafb;
            --text-primary: #1a1a1a;
            --text-secondary: #6b7280;
            --border-color: #e5e7eb;
            --card-bg: #ffffff;
            --navbar-bg: #ffffff;
            --info-item-bg: #f9fafb;
            --table-header-bg: #f9fafb;
            --table-border: #e5e7eb;
            --btn-bg: #ffffff;
            --btn-text: #1a1a1a;
            --btn-border: #e5e7eb;
            --btn-hover: #f9fafb;
            --status-pending-bg: #fef3c7;
            --status-pending-text: #92400e;
            --status-approved-bg: #d1fae5;
            --status-approved-text: #065f46;
            --status-rejected-bg: #fee2e2;
            --status-rejected-text: #991b1b;
            --status-cancelled-bg: #e5e7eb;
            --status-cancelled-text: #4b5563;
            --admin-notes-bg: #fef3c7;
            --admin-notes-border: #f59e0b;
            --admin-notes-title: #92400e;
            --admin-notes-text: #78350f;
        }

        [data-theme="dark"] {
            --bg-primary: #1a1a1a;
            --bg-secondary: #2d2d2d;
            --bg-tertiary: #404040;
            --text-primary: #ffffff;
            --text-secondary: #9ca3af;
            --border-color: #404040;
            --card-bg: #2d2d2d;
            --navbar-bg: #2d2d2d;
            --info-item-bg: #404040;
            --table-header-bg: #404040;
            --table-border: #4b5563;
            --btn-bg: #404040;
            --btn-text: #ffffff;
            --btn-border: #4b5563;
            --btn-hover: #4b5563;
            --status-pending-bg: #78350f;
            --status-pending-text: #fef3c7;
            --status-approved-bg: #065f46;
            --status-approved-text: #d1fae5;
            --status-rejected-bg: #991b1b;
            --status-rejected-text: #fee2e2;
            --status-cancelled-bg: #4b5563;
            --status-cancelled-text: #e5e7eb;
            --admin-notes-bg: #78350f;
            --admin-notes-border: #f59e0b;
            --admin-notes-title: #fef3c7;
            --admin-notes-text: #fef3c7;
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

        @font-face {
            font-family: 'Geist Sans';
            src: url('node_modules/geist/dist/fonts/geist-sans/Geist-Variable.woff2') format('woff2');
            font-weight: 100 900;
            font-style: normal;
        }

        body {
            font-family: 'Geist Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-secondary);
            line-height: 1.6;
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
            max-width: 1000px;
            margin: 24px auto;
            padding: 0 16px;
        }
        
        .request-card {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.03);
            border                 : 1px solid var(--border-color);
            transition: all 0.3s;
        }
        
        @media (min-width: 768px) {
            .request-card {
                padding: 40px;
            }
        }
        
        .request-header {
            display: flex;
            flex-direction: column;
            gap: 16px;
            margin-bottom: 24px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--border-color);
        }
        
        @media (min-width: 640px) {
            .request-header {
                flex-direction: row;
                justify-content: space-between;
                align-items: start;
            }
        }
        
        .request-header h2 {
            font-size: 22px;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        
        @media (min-width: 768px) {
            .request-header h2 {
                font-size: 24px;
            }
        }
        
        .control-number {
            font-size: 14px;
            color: var(--text-secondary);
        }
        
        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            align-self: flex-start;
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
        
        .info-section {
            margin-bottom: 28px;
        }
        
        .info-section h3 {
            font-size: 18px;
            color: var(--text-primary);
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
        }
        
        @media (min-width: 640px) {
            .info-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (min-width: 1024px) {
            .info-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        .info-item {
            padding: 16px;
            background: var(--info-item-bg);
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }
        
        .info-label {
            font-size: 12px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        
        .info-value {
            font-size: 16px;
            color: var(--text-primary);
            font-weight: 500;
            word-break: break-word;
        }
        
        .facilities-container {
            overflow-x: auto;
            margin-top: 16px;
        }
        
        .facilities-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }
        
        .facilities-table th {
            text-align: left;
            padding: 12px;
            background: var(--table-header-bg);
            border-bottom: 2px solid var(--table-border);
            font-size: 14px;
            color: var(--text-secondary);
            font-weight: 600;
        }
        
        .facilities-table td {
            padding: 16px 12px;
            border-bottom: 1px solid var(--table-border);
            color: var(--text-primary);
        }
        
        .facilities-table tr:last-child td {
            border-bottom: none;
        }
        
        @media (max-width: 767px) {
            .facilities-table th,
            .facilities-table td {
                padding: 10px 8px;
                font-size: 14px;
            }
            
            .facilities-table th:nth-child(4),
            .facilities-table th:nth-child(5),
            .facilities-table td:nth-child(4),
            .facilities-table td:nth-child(5) {
                display: none;
            }
        }
        
        .admin-notes {
            background: var(--admin-notes-bg);
            border-left: 4px solid var(--admin-notes-border);
            padding: 16px;
            border-radius: 8px;
            margin-top: 24px;
        }
        
        .admin-notes h4 {
            font-size: 14px;
            color: var(--admin-notes-title);
            margin-bottom: 8px;
        }
        
        .admin-notes p {
            color: var(--admin-notes-text);
            line-height: 1.5;
        }
        
        /* Mobile-specific improvements */
 @media (max-width: 480px) {
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
                margin: 16px auto;
                padding: 0 12px;
            }
            
            .request-card {
                padding: 20px;
                border-radius: 10px;
            }
            
            .request-header h2 {
                font-size: 20px;
            }
            
            .info-item {
                padding: 12px;
            }
            
            .info-value {
                font-size: 15px;
            }
        }
        
        /* Print styles */
@media (max-width: 360px) {
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
                <div class="brand-subtitle">Create Request</div>
            </div>
        </div>
    </a>
    <a href="dashboard.php" class="btn-back">Back</a>
</header>
    
    <div class="container">
        <div class="request-card">
            <div class="request-header">
                <div>
                    <h2>Request Details</h2>
                    <div class="control-number">Control Number: <strong><?php echo htmlspecialchars($request['control_number']); ?></strong></div>
                </div>
                <span class="status-badge <?php echo $request['status']; ?>">
                    <?php echo ucfirst($request['status']); ?>
                </span>
            </div>

            <!-- Requestor Information -->
            <div class="info-section">
                <h3>Requestor Information</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Name</div>
                        <div class="info-value"><?php echo htmlspecialchars($request['requestor_name']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Department</div>
                        <div class="info-value"><?php echo htmlspecialchars($request['department']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Email</div>
                        <div class="info-value"><?php echo htmlspecialchars($request['email']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Phone</div>
                        <div class="info-value"><?php echo htmlspecialchars($request['phone_number'] ?: 'N/A'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Event Type</div>
                        <div class="info-value"><?php echo htmlspecialchars($request['event_type']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Date Submitted</div>
                        <div class="info-value"><?php echo date('M d, Y', strtotime($request['created_at'])); ?></div>
                    </div>
                </div>
            </div>

            <!-- Facility Details -->
            <div class="info-section">
                <h3>Facility Details</h3>
                <div class="facilities-container">
                    <table class="facilities-table">
                        <thead>
                            <tr>
                                <th>Facility</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Hours</th>
                                <th>Participants</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($facility = $facilities->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($facility['facility_name']); ?></strong></td>
                                    <td><?php echo date('M d, Y', strtotime($facility['date_needed'])); ?></td>
                                    <td><?php echo htmlspecialchars($facility['time_needed']); ?></td>
                                    <td><?php echo $facility['total_hours']; ?> hrs</td>
                                    <td><?php echo $facility['total_participants']; ?></td>
                                    <td><?php echo htmlspecialchars($facility['remarks'] ?: '-'); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Admin Notes -->
            <?php if ($request['admin_notes']): ?>
                <div class="admin-notes">
                    <h4>Admin Notes</h4>
                    <p><?php echo nl2br(htmlspecialchars($request['admin_notes'])); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php include 'chat_bot.php'; ?>
</body>
</html>