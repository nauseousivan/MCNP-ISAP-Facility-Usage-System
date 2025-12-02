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

// Get user details
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Shorten department name and combine with program
$department_display = '';
$department_lower = strtolower($user['department']);
if (strpos($department_lower, 'international') !== false) {
    $department_display = 'ISAP';
} elseif (strpos($department_lower, 'medical') !== false) {
    $department_display = 'MCNP';
} else {
    $department_display = $user['department'];
}

$department_program = $department_display . ' - ' . $user['program'];

// Get vehicle details if vehicle_id is provided
$vehicle = null;
if (isset($_GET['vehicle_id'])) {
    $vehicle_id = $_GET['vehicle_id'];
    $sql = "SELECT * FROM transportation_vehicles WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $vehicle_id);
    $stmt->execute();
    $vehicle = $stmt->get_result()->fetch_assoc();
}

// Get all available vehicles for dropdown
$vehicles = [];
$sql = "SELECT id, name, type, capacity FROM transportation_vehicles WHERE availability = 'available' AND is_active = TRUE ORDER BY name";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $vehicles[] = $row;
    }
}

// Prepare data for success/print view
$success_data = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Generate control number
    $control_no = 'TRP-' . date('Ymd') . '-' . str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
    
    $department_office = $_POST['department_office'];
    $requestor_name = $user['name']; // Get from session/user data
    $authorized_passengers = $_POST['authorized_passengers'];
    $no_of_passengers = $_POST['no_of_passengers'];
    $places_to_visit = $_POST['places_to_visit'];
    $purpose = $_POST['purpose'];
    $vehicle_requested = $_POST['vehicle_requested'];
    $date_vehicle_used = $_POST['date_vehicle_used'];
    $time_departure = $_POST['time_departure'];
    $time_return = $_POST['time_return'];

    // Store for printable view
    $success_data = $_POST;
    $success_data['requestor_name'] = $requestor_name;
    $success_data['control_no'] = $control_no;
    
    $sql = "INSERT INTO transportation_requests (
        user_id, control_no, department_office, authorized_passengers, 
        no_of_passengers, places_to_visit, purpose, vehicle_requested,
        date_vehicle_used, time_departure, time_return, availability_option,
        reschedule_date, reschedule_time
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $sql = "INSERT INTO transportation_requests (user_id, control_no, department_office, authorized_passengers, no_of_passengers, places_to_visit, purpose, vehicle_requested, date_vehicle_used, time_departure, time_return) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isssissssss", 
        $user_id, $control_no, $department_office, $authorized_passengers, $no_of_passengers, 
        $places_to_visit, $purpose, $vehicle_requested, $date_vehicle_used, $time_departure, $time_return
    );    
    
    if ($stmt->execute()) {
        $success_message = "Transportation request submitted successfully! Control No: " . $control_no;
        // Don't redirect, show the success message and print options on the same page.
    } else {
        $error_message = "Error submitting request: " . $conn->error;
    }
}

$logo_file = $GLOBALS['logo_file'];
$portal_name = $GLOBALS['portal_name'];
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($theme); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Transportation Request - MCNP Service Portal</title>
    <style>
        /* Styles from create_request.php */
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
            overflow-x: hidden;
        }
        
        .header {
            background: var(--navbar-bg);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky; top: 0; z-index: 100;
            border-bottom: 1px solid var(--border-color);
        }
        
        .header-brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .header-brand .brand-text {
            display: flex;
            flex-direction: column;
        }

        .header-brand .brand-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .header-brand .brand-subtitle {
            font-size: 12px;
            color: var(--text-secondary);
        }
        
        .header-brand img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
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
            transition: all 0.2s;
        }
        
        .btn-back:hover {
            background: var(--btn-hover);
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 16px;
        }

        /* Copy all CSS from create_request.php and modify */
        .form-card {
            background: var(--bg-primary);
            padding: 32px;
            border-radius: 20px;
            border: 1px solid var(--border-color); /* Added border */
            box-shadow: 0 4px 10px rgba(0,0,0,0.03);
            max-width: 800px;
            margin: 0 auto;
        }
        
        .form-header {
            text-align: center;
            padding-bottom: 24px;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 32px;
        }
        
        .form-header h1 {
            font-size: 28px;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        
        .form-header p {
            color: var(--text-secondary);
            font-size: 15px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        label {
            display: block; /* Ensure label is block */
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-primary);
            font-size: 14px;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--input-border);
            border-radius: 8px;
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 15px;
            transition: all 0.3s;
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--text-primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        
        textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .btn-submit {
            width: 100%;
            padding: 14px;
            background: var(--btn-primary-bg);
            color: var(--btn-primary-text);
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            opacity: 0.8;
            transform: translateY(-2px);
        }
        
        .vehicle-card {
            background: var(--bg-secondary); /* Changed from bg-primary */
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
        }
        
        .vehicle-card h4 {
            margin-bottom: 8px;
            color: var(--text-primary);
        }
        
        .vehicle-card p {
            margin: 4px 0;
            color: var(--text-secondary);
            font-size: 14px;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-container {
                padding: 20px;
            }
            
            .radio-group {
                flex-direction: column;
                gap: 12px;
            }
        }

        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; }
        .alert-success { background: var(--alert-success-bg); color: var(--alert-success-text); border: 1px solid var(--alert-success-border); }
        .alert-danger { background: var(--alert-danger-bg); color: var(--alert-danger-text); border: 1px solid var(--alert-danger-border); }
        .success-actions { display: flex; gap: 12px; margin-top: 16px; flex-direction: column; }
        .btn-print, .btn-download { padding: 12px 20px; background: var(--btn-primary-bg); color: var(--btn-primary-text); border: none; border-radius: 8px; font-weight: 500; cursor: pointer; text-decoration: none; display: inline-block; text-align: center; min-height: 44px; transition: all 0.2s; }
        .btn-print:hover, .btn-download:hover { background: var(--btn-primary-hover); }
        @media print { body * { visibility: hidden; } .printable, .printable * { visibility: visible; } .printable { position: absolute; left: 0; top: 0; width: 100%; margin: 0; } .no-print { display: none !important; } }

        /* Styles for availability section */
        .availability-section {
            background: var(--bg-secondary);
            padding: 24px;
            border-radius: 12px;
            margin: 24px 0;
            border: 1px solid var(--border-color);
        }
        
        .availability-section h3 {
            color: var(--text-primary);
            margin-bottom: 16px;
            font-size: 18px;
        }
        
        .radio-options {
            display: flex;
            gap: 30px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .radio-option {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            padding: 12px 16px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .radio-option:hover {
            border-color: var(--text-primary);
        }
        
        .radio-option.selected {
            border-color: var(--text-primary);
            background: var(--bg-primary);
        }
        
        .radio-option input[type="radio"] {
            width: 18px;
            height: 18px;
        }
        
        .option-details {
            transition: all 0.3s ease;
            overflow: hidden;
            max-height: 0;
            opacity: 0;
        }
        
        .option-details.visible {
            max-height: 500px; /* Adjust as needed */
            opacity: 1;
            margin-top: 16px;
        }
    </style>
</head>
<body>
    <header class="header">
        <a href="transportation.php" style="text-decoration: none; color: inherit;">
            <div class="header-brand">
                <img src="<?php echo htmlspecialchars($logo_file); ?>" alt="Logo">
                <div class="brand-text">
                    <div class="brand-title"><?php echo htmlspecialchars($portal_name); ?></div>
                    <div class="brand-subtitle">Transportation Request</div>
                </div>
            </div>
        </a>
        <a href="transportation.php" class="btn-back">Back to Vehicles</a>
    </header>

    <div class="container">
        <div class="form-card">
            <div class="form-header">
                <h1>Vehicle Usage Request Form</h1>
                <p>Complete this form at least three (3) working days before your trip.</p>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <?php echo "<strong>" . htmlspecialchars($success_message) . "</strong>"; ?>
                    <div class="success-actions no-print">
                        <button class="btn-print" onclick="printForm()">Print Form</button>
                        <a href="my_transport_requests.php" class="btn-download">View My Requests</a>
                    </div>
                </div>
                <script>
                    const submittedData = <?php echo json_encode($success_data); ?>;
                </script>
            <?php else: ?>
            <form method="POST" id="transportForm">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="department_office">Department/Office requesting:</label>
                        <input type="text" id="department_office" name="department_office" value="<?php echo htmlspecialchars($department_program); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="no_of_passengers">No. of Passengers:</label>
                        <input type="number" id="no_of_passengers" name="no_of_passengers" min="1" required>
                    </div>
                </div>
                <div class="form-group full-width">
                    <label for="authorized_passengers">List all authorized passengers:</label>
                    <textarea id="authorized_passengers" name="authorized_passengers" placeholder="List all passengers including yourself..." required></textarea>
                </div>
                <div class="form-group full-width">
                    <label for="places_to_visit">Place(s) to be visited/inspected:</label>
                    <textarea id="places_to_visit" name="places_to_visit" placeholder="Specify all locations and addresses..." required></textarea>
                </div>
                <div class="form-group full-width">
                    <label for="purpose">Purpose of Trip:</label>
                    <textarea id="purpose" name="purpose" placeholder="Describe the purpose of your trip..." required></textarea>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="vehicle_requested">Vehicle Requested:</label>
                        <select id="vehicle_requested" name="vehicle_requested" required>
                            <option value="">Select a vehicle</option>
                            <?php foreach ($vehicles as $v): ?>
                                <option value="<?php echo htmlspecialchars($v['name']); ?>" <?php echo ($vehicle && $vehicle['id'] == $v['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($v['name']); ?> (<?php echo htmlspecialchars($v['type']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="date_vehicle_used">Date of Trip:</label>
                        <input type="date" id="date_vehicle_used" name="date_vehicle_used" min="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="time_departure">Time of Departure:</label>
                        <input type="time" id="time_departure" name="time_departure" required>
                    </div>
                    <div class="form-group">
                        <label for="time_return">Time of Return:</label>
                        <input type="time" id="time_return" name="time_return" required>
                    </div>
                </div>
                <div style="background: #f0f9ff; border: 1px solid #e0f2fe; padding: 16px; border-radius: 8px; margin-top: 24px; margin-bottom: 24px;">
                    <h4 style="color: #0369a1; margin-bottom: 8px;">Instructions & Reminders:</h4>
                    <ul style="color: #0c4a6e; font-size: 14px; margin: 0; padding-left: 20px;">
                        <li>This form serves as your trip ticket.</li>
                        <li>Vehicle requests are on a first come, first served basis.</li>
                        <li>After submission, please print this form and proceed to the General Services Office for final approval.</li>
                    </ul>
                </div>

                <button type="submit" class="btn-submit">Submit Request</button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Printable Form -->
    <div id="printable-form" class="printable" style="display: none; font-family: Arial, sans-serif;">
        <!-- Header -->
        <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #000; padding-bottom: 10px; margin-bottom: 20px;">
            <img src="combined-logo.png" alt="Logo" style="height: 60px; width: 60px;">
            <div style="text-align: center;">
                <p style="margin: 0; font-size: 12px;">MEDICAL COLLEGES OF NORTHERN PHILIPPINES</p>
                <p style="margin: 0; font-size: 12px;">Alimannao Hills, Penablanca, Cagayan 3502 Philippines</p>
                <h1 style="font-size: 16px; margin: 5px 0;">VEHICLE USAGE REQUEST FORM</h1>
            </div>
            <div style="font-size: 10px; text-align: right;">
                <p style="margin:0;">Document No. MCNP-0288-D00-480</p>
                <p style="margin:0;">Effective Date: November 2023</p>
            </div>
        </div>
        <!-- Request Details -->
        <table style="width: 100%; font-size: 12px; border-collapse: collapse; margin-bottom: 20px;">
            <tr>
                <td style="padding: 4px;"><strong>Date of Request:</strong> <span id="print-date_request"></span></td>
                <td style="padding: 4px; text-align: right;"><strong>Control No.:</strong> <span id="print-control_no"></span></td>
            </tr>
            <tr><td colspan="2" style="padding: 8px 4px;"><strong>Department/Office requesting:</strong> <span id="print-department_office"></span></td></tr>
            <tr><td colspan="2" style="padding: 4px;"><strong>List all authorized passengers:</strong> <span id="print-authorized_passengers" style="white-space: pre-wrap;"></span></td></tr>
            <tr><td colspan="2" style="padding: 4px;"><strong>No. of Passengers:</strong> <span id="print-no_of_passengers"></span></td></tr>
            <tr><td colspan="2" style="padding: 4px;"><strong>Place(s) to be visited/inspected:</strong> <span id="print-places_to_visit"></span></td></tr>
            <tr><td colspan="2" style="padding: 4px;"><strong>Purpose:</strong> <span id="print-purpose"></span></td></tr>
            <tr><td colspan="2" style="padding: 4px;"><strong>Vehicle Requested or Required:</strong> <span id="print-vehicle_requested"></span></td></tr>
            <tr><td colspan="2" style="padding: 4px;"><strong>Date vehicle will be used:</strong> <span id="print-date_vehicle_used"></span></td></tr>
            <tr>
                <td style="padding: 4px;"><strong>Time of Departure:</strong> <span id="print-time_departure"></span></td>
                <td style="padding: 4px;"><strong>Time of Return:</strong> <span id="print-time_return"></span></td>
            </tr>
        </table>
        <!-- Signatures -->
        <table style="width: 100%; font-size: 12px; margin-top: 40px; border-collapse: collapse;">
            <tr>
                <td style="width: 50%; padding: 10px;">
                    <p>Requested by:</p>
                    <div style="border-bottom: 1px solid #000; height: 40px; margin-bottom: 5px;"></div>
                    <p style="text-align: center;">(Signature over Printed Name)</p>
                    <p id="print-requestor_name" style="text-align: center; font-weight: bold;"></p>
                </td>
                <td style="width: 50%; padding: 10px;">
                    <p>Verified by (Transportation In-charge):</p>
                    <div style="border-bottom: 1px solid #000; height: 40px; margin-bottom: 5px;"></div>
                    <p style="text-align: center;">(Name and Signature)</p>
                </td>
            </tr>
             <tr>
                <td style="width: 50%; padding: 10px; padding-top: 30px;">
                    <p>Recommend Approval:</p>
                    <div style="border-bottom: 1px solid #000; height: 40px; margin-bottom: 5px;"></div>
                    <p style="text-align: center;">Head, General Service</p>
                </td>
                <td style="width: 50%; padding: 10px; padding-top: 30px;">
                    <p>Approved by:</p>
                    <div style="border-bottom: 1px solid #000; height: 40px; margin-bottom: 5px;"></div>
                    <p style="text-align: center;">CHRISTIAN R. GUZMAN, Pn. (President)</p>
                </td>
            </tr>
        </table>

        <div style="text-align: center; font-style: italic; font-size: 10px; margin: 5px 0;">*** continued at the back ***</div>

        <!-- Back Page Content -->
        <div style="page-break-before: always; margin-top: 30px;">
            <!-- FOR TRANSPORTATION DIVISION USE ONLY -->
            <div style="border: 2px solid #000; padding: 15px; font-size: 12px;">
                <strong style="text-align: center; display: block; font-size: 14px; margin-bottom: 15px;">FOR TRANSPORTATION DIVISION USE ONLY</strong>
                <div style="margin-bottom: 15px;"><strong>Availability of the requested vehicle:</strong></div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                    <div style="width: 48%;">
                        <div style="display: flex; align-items: center; margin-bottom: 10px;">
                            <div style="width: 20px; height: 20px; border: 2px solid #000; margin-right: 10px; display: flex; align-items: center; justify-content: center;">
                                <span id="print-available-check" style="font-size: 16px; display: none;">✓</span>
                            </div>
                            <strong>Available</strong>
                        </div>
                        <div style="margin-left: 30px; font-size: 11px;">
                            <p>Driver 1 Name: <span style="border-bottom: 1px solid #000; display: inline-block; min-width: 150px;">&nbsp;</span></p>
                            <p>Driver 1 Contact Number: <span style="border-bottom: 1px solid #000; display: inline-block; min-width: 100px;">&nbsp;</span></p>
                            <p style="margin-top: 10px;">Driver 2 Name: <span style="border-bottom: 1px solid #000; display: inline-block; min-width: 150px;">&nbsp;</span></p>
                            <p>Driver 2 Contact Number: <span style="border-bottom: 1px solid #000; display: inline-block; min-width: 100px;">&nbsp;</span></p>
                        </div>
                    </div>
                    <div style="width: 48%;">
                        <div style="display: flex; align-items: center; margin-bottom: 10px;">
                            <div style="width: 20px; height: 20px; border: 2px solid #000; margin-right: 10px; display: flex; align-items: center; justify-content: center;">
                                <span style="font-size: 16px; display: none;">✓</span>
                            </div>
                            <strong>Not Available (please re-schedule)</strong>
                        </div>
                        <div style="margin-left: 30px;">
                            <p>Re-schedule date: <span id="print-reschedule_date" style="border-bottom: 1px solid #000; display: inline-block; min-width: 100px;">&nbsp;</span></p>
                            <p>Re-schedule time: <span id="print-reschedule_time" style="border-bottom: 1px solid #000; display: inline-block; min-width: 80px;">&nbsp;</span></p>
                        </div>
                    </div>
                </div>
                <table style="width: 100%; margin-top: 30px; text-align: center; font-size: 11px; border-collapse: collapse;">
                    <tr>
                        <td style="width: 33%; padding: 10px;">
                            <div style="border-bottom: 1px solid black; margin: 0 20px 5px 20px; height: 30px;"></div>
                            (Name and Signature)<br><strong>Verified by (Transportation In-charge)</strong>
                        </td>
                        <td style="width: 33%; padding: 10px;">
                            <div style="border-bottom: 1px solid black; margin: 0 20px 5px 20px; height: 30px;"></div>
                            <strong>Head, General Service</strong><br>Recommend Approval
                        </td>
                        <td style="width: 33%; padding: 10px;">
                            <div style="border-bottom: 1px solid black; margin: 0 20px 5px 20px; height: 30px;"></div>
                            <strong>CHRISTIAN R. GUZMAN, Pn.</strong><br>Approved by
                        </td>
                    </tr>
                </table>
            </div>

            <!-- DRIVER'S ACKNOWLEDGEMENT -->
            <div style="border: 2px solid #000; padding: 15px; margin-top: 20px; font-size: 12px;">
                <strong style="text-align: center; display: block; font-size: 14px; margin-bottom: 10px;">DRIVER'S ACKNOWLEDGEMENT</strong>
                <p style="text-align: center; font-style: italic; margin: 10px 0 15px 0;">I hereby certify that I used this school vehicle on official business as stated.</p>
                <table style="width: 100%; font-size: 11px; border-collapse: collapse; margin-bottom: 15px;">
                    <tr>
                        <td style="padding: 4px; width: 50%;">Departure Date: _______________</td>
                        <td style="padding: 4px;">Return Date: _______________</td>
                    </tr>
                    <tr>
                        <td style="padding: 4px;">Departure Time: _______________</td>
                        <td style="padding: 4px;">Return Time: _______________</td>
                    </tr>
                    <tr><td colspan="2" style="padding: 4px;">Place(s) visited/Inspected: _________________________________________________</td></tr>
                </table>
                <div style="margin-bottom: 15px;">
                    <strong>Passengers:</strong>
                    <div style="margin-left: 20px;">
                        <p>1. _________________________</p>
                        <p>2. _________________________</p>
                        <p>3. _________________________</p>
                        <p>4. _________________________</p>
                    </div>
                </div>
                <table style="width: 100%; font-size: 11px; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 4px; width: 50%;">Driver 1 Name and Signature: ___________________</td>
                        <td style="padding: 4px;">Driver 2 Name and Signature: ___________________</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <script>
        function printForm() {
            if (typeof submittedData !== 'undefined') {
                // Populate front page
                document.getElementById('print-date_request').textContent = new Date().toLocaleDateString();
                document.getElementById('print-control_no').textContent = submittedData.control_no;
                document.getElementById('print-department_office').textContent = submittedData.department_office;
                document.getElementById('print-authorized_passengers').textContent = submittedData.authorized_passengers;
                document.getElementById('print-no_of_passengers').textContent = submittedData.no_of_passengers;
                document.getElementById('print-places_to_visit').textContent = submittedData.places_to_visit;
                document.getElementById('print-purpose').textContent = submittedData.purpose;
                document.getElementById('print-vehicle_requested').textContent = submittedData.vehicle_requested;
                document.getElementById('print-date_vehicle_used').textContent = new Date(submittedData.date_vehicle_used).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
                document.getElementById('print-time_departure').textContent = submittedData.time_departure ? new Date(`1970-01-01T${submittedData.time_departure}`).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }) : 'N/A';
                document.getElementById('print-time_return').textContent = submittedData.time_return ? new Date(`1970-01-01T${submittedData.time_return}`).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }) : 'N/A';
                document.getElementById('print-requestor_name').textContent = submittedData.requestor_name;
                
                window.print();
            }
        }

    </script>
</body>
</html>