<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

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
?>
<!DOCTYPE html>
<html lang="en">
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
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f7fa;
            line-height: 1.6;
        }
        
        .navbar {
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 16px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .navbar-brand img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
        }
        
        .navbar-brand h1 {
            font-size: 18px;
            font-weight: 700;
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
            white-space: nowrap;
        }
        
        .container {
            max-width: 1000px;
            margin: 24px auto;
            padding: 0 16px;
        }
        
        .request-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
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
            border-bottom: 2px solid #e5e7eb;
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
            color: #1a1a1a;
            margin-bottom: 8px;
        }
        
        @media (min-width: 768px) {
            .request-header h2 {
                font-size: 24px;
            }
        }
        
        .control-number {
            font-size: 14px;
            color: #6b7280;
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
        
        .info-section {
            margin-bottom: 28px;
        }
        
        .info-section h3 {
            font-size: 18px;
            color: #1a1a1a;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 1px solid #e5e7eb;
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
            background: #f9fafb;
            border-radius: 8px;
        }
        
        .info-label {
            font-size: 12px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        
        .info-value {
            font-size: 16px;
            color: #1a1a1a;
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
            background: #f9fafb;
            border-bottom: 2px solid #e5e7eb;
            font-size: 14px;
            color: #6b7280;
            font-weight: 600;
        }
        
        .facilities-table td {
            padding: 16px 12px;
            border-bottom: 1px solid #e5e7eb;
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
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 16px;
            border-radius: 8px;
            margin-top: 24px;
        }
        
        .admin-notes h4 {
            font-size: 14px;
            color: #92400e;
            margin-bottom: 8px;
        }
        
        .admin-notes p {
            color: #78350f;
            line-height: 1.5;
        }
        
        /* Mobile-specific improvements */
        @media (max-width: 480px) {
            .navbar {
                padding: 12px 16px;
            }
            
            .navbar-brand h1 {
                font-size: 16px;
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
        @media print {
            .navbar, .btn-back {
                display: none;
            }
            
            body {
                background: white;
            }
            
            .request-card {
                box-shadow: none;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-brand">
            <img src="combined-logo.png" alt="Logo">
            <h1>MCNP-ISAP Facility Portal</h1>
        </div>
        <a href="dashboard.php" class="btn-back">Back</a>
    </nav>

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