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
?>
<!DOCTYPE html>
<html lang="en">
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
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f7fa;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .btn-back {
            padding: 10px 20px;
            background: #1a1a1a;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .card h2 {
            font-size: 20px;
            color: #1a1a1a;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .info-item label {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 4px;
            display: block;
        }
        
        .info-item .value {
            font-weight: 600;
            color: #1a1a1a;
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
        
        .facility-item {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 12px;
        }
        
        .facility-item h4 {
            font-size: 16px;
            color: #1a1a1a;
            margin-bottom: 8px;
        }
        
        .facility-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Request Details</h1>
            <a href="requests.php" class="btn-back">Back to Requests</a>
        </div>

        <!-- Request Information -->
        <div class="card">
            <h2>Request Information</h2>
            <div class="info-grid">
                <div class="info-item">
                    <label>Control Number</label>
                    <div class="value"><?php echo htmlspecialchars($request['control_number']); ?></div>
                </div>
                <div class="info-item">
                    <label>Status</label>
                    <span class="status-badge <?php echo strtolower($request['status']); ?>">
                        <?php echo ucfirst($request['status']); ?>
                    </span>
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
            <div class="info-item">
                <label>Admin Notes</label>
                <div class="value" style="background: #f9fafb; padding: 12px; border-radius: 6px; margin-top: 8px;">
                    <?php echo nl2br(htmlspecialchars($request['admin_notes'])); ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Facility Details -->
        <div class="card">
            <h2>Requested Facilities</h2>
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
                    <div class="info-item" style="margin-top: 8px;">
                        <label>Remarks</label>
                        <div class="value"><?php echo htmlspecialchars($facility['remarks']); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</body>
</html>