<?php
session_start();
require_once 'theme_loader.php';
require_once 'config.php';
require_once 'functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get theme directly from database as fallback
$theme = 'light';
$user_id = $_SESSION['user_id'];
$sql = "SELECT theme FROM user_preferences WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $prefs = $result->fetch_assoc();
    $theme = $prefs['theme'];
}
 
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Get user details
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$error = '';
$success = '';
$control_number = '';
$success_data = []; // Array to hold all submitted data for printing on success

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Generate control number
    $control_number = 'MCNP-ISAP-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    $requestor_name = $_POST['requestor_name'];
    $department = $_POST['department'];
    $email = $_POST['email'];
    $phone_number = $_POST['phone_number'];
    $event_type = $_POST['event_type'];
    
    // Store requestor details for printing
    $success_data['requestor'] = [
        'name' => $requestor_name,
        'department' => $department,
        'email' => $email,
        'phone_number' => $phone_number,
        'event_type' => $event_type
    ];

    // SERVER-SIDE VALIDATION FOR BOOKING CONFLICTS
    $has_conflict = false;
    $conflict_details = '';
    $facilities_to_check = $_POST['facilities'] ?? [];
    foreach ($facilities_to_check as $index => $facility_name) {
        $date_needed = $_POST['dates'][$index];
        $start_time = $_POST['start_times'][$index];
        $end_time = $_POST['end_times'][$index];

        if (check_for_booking_conflict($conn, $facility_name, $date_needed, $start_time, $end_time)) {
            $has_conflict = true;
            $conflict_details = "Conflict found for '$facility_name' on $date_needed between $start_time and $end_time. This slot may have been taken while you were filling out the form.";
            break; // Exit loop on first conflict
        }
    }

    if ($has_conflict) {
        $error = $conflict_details;
        // To prevent form submission but retain user's input, we will just set the error and let the page re-render.
        // We must avoid executing the database insertion logic below.
    } else {
        // NO CONFLICTS, PROCEED WITH INSERTION

    // Insert main request
    $sql = "INSERT INTO facility_requests (control_number, user_id, requestor_name, department, email, phone_number, event_type, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sisssss", $control_number, $user_id, $requestor_name, $department, $email, $phone_number, $event_type);
    
   if ($stmt->execute()) {
    $request_id = $conn->insert_id;
    
    // Insert facility details
    $facilities = $_POST['facilities'] ?? [];
    $dates = $_POST['dates'] ?? [];
    $start_times = $_POST['start_times'] ?? [];
    $end_times = $_POST['end_times'] ?? [];
    $hours = $_POST['hours'] ?? [];
    $participants = $_POST['participants'] ?? [];
    $remarks = $_POST['remarks'] ?? [];
    
    $success_data['facilities'] = [];

    // Use the existing time_needed column but store both times
    $detail_sql = "INSERT INTO facility_request_details (request_id, facility_name, date_needed, time_needed, total_hours, total_participants, remarks) 
                   VALUES (?, ?, ?, ?, ?, ?, ?)";
    $detail_stmt = $conn->prepare($detail_sql);
    
    foreach ($facilities as $index => $facility) {
        if (!empty($facility) && !empty($dates[$index])) {
            // Combine start and end times for display in time_needed column
            $time_display = $start_times[$index] . ' to ' . $end_times[$index];
            
            $detail_stmt->bind_param("isssdis", 
                $request_id, 
                $facility, 
                $dates[$index], 
                $time_display,
                $hours[$index], 
                $participants[$index], 
                $remarks[$index]
            );
            $detail_stmt->execute();
            
            // Store facility details for printing
            $success_data['facilities'][] = [
                'name' => $facility,
                'date' => $dates[$index],
                'start_time' => $start_times[$index],
                'end_time' => $end_times[$index],
                'hours' => $hours[$index],
                'participants' => $participants[$index],
                'remarks' => $remarks[$index]
            ];
        }
    }
    
    // ADD NOTIFICATION FOR REQUEST SUBMISSION
    add_action_notification($conn, $user_id, 'request_submitted', ['control_number' => $control_number]);
    
    $success = "Request submitted successfully! Control Number: <strong>$control_number</strong>";
} else {
    $error = "Error submitting request: " . $conn->error;
}
    } // End of the 'else' block for conflict check
}

// Available facilities (FROM DATABASE)
$facilities_list = [];
$sql = "SELECT name FROM facilities WHERE is_active = TRUE ORDER BY name";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $facilities_list[] = $row['name'];
    }
}

// Add "Other" option at the end
$facilities_list[] = 'Other';
$logo_file = $GLOBALS['logo_file'];
$portal_name = $GLOBALS['portal_name'];
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($theme); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Facility Request - MCNP-ISAP</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --bg-primary: #ffffff;
            --bg-secondary: #f5f7fa;
            --bg-tertiary: #f9fafb;
            --text-primary: #1a1a1a;
            --text-secondary: #6b7280;
            --border-color: #e5e7eb;
            --card-bg: #ffffff;
            --navbar-bg: #ffffff;
            --info-item-bg: #f9fafb;
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
            --btn-success-bg: #10b981;
            --btn-success-text: #ffffff;
            --btn-success-hover: #059669;
            --btn-danger-bg: #ef4444;
            --btn-danger-text: #ffffff;
            --btn-danger-hover: #dc2626;
            --step-bg: #ffffff;
            --step-border: #e5e7eb;
            --step-active-bg: #1a1a1a;
            --step-active-text: #ffffff;
            --step-completed-bg: #10b981;
            --step-completed-text: #ffffff;
            --facility-card-bg: #ffffff;
            --facility-card-border: #e5e7eb;
            --facility-card-hover: #f9fafb;
            --facility-card-selected-bg: #f9fafb;
            --facility-card-selected-border: #1a1a1a;
            --alert-danger-bg: #fee2e2;
            --alert-danger-text: #991b1b;
            --alert-danger-border: #fecaca;
            --alert-success-bg: #d1fae5;
            --alert-success-text: #065f46;
            --alert-success-border: #a7f3d0;
            --alert-warning-bg: #fffbe6;
            --alert-warning-text: #78350f;
            --alert-warning-border: #fde68a;
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
            --btn-success-bg: #059669;
            --btn-success-text: #ffffff;
            --btn-success-hover: #047857;
            --btn-danger-bg: #dc2626;
            --btn-danger-text: #ffffff;
            --btn-danger-hover: #b91c1c;
            --step-bg: #404040;
            --step-border: #4b5563;
            --step-active-bg: #ffffff;
            --step-active-text: #1a1a1a;
            --step-completed-bg: #059669;
            --step-completed-text: #ffffff;
            --facility-card-bg: #404040;
            --facility-card-border: #4b5563;
            --facility-card-hover: #4b5563;
            --facility-card-selected-bg: #4b5563;
            --facility-card-selected-border: #ffffff;
            --alert-danger-bg: #7f1d1d;
            --alert-danger-text: #fecaca;
            --alert-danger-border: #991b1b;
            --alert-success-bg: #064e3b;
            --alert-success-text: #a7f3d0;
            --alert-success-border: #065f46;
            --alert-warning-bg: #78350f;
            --alert-warning-text: #fef3c7;
            --alert-warning-border: #92400e;
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
            overflow-x: hidden;
        }
        
        .navbar {
            background: var(--navbar-bg);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
        }
        
        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .navbar-brand .brand-text {
            display: flex;
            flex-direction: column;
        }

        .navbar-brand .brand-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .navbar-brand .brand-subtitle {
            font-size: 12px;
            color: var(--text-secondary);
        }
        
        .navbar-brand img {
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
        
        .form-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: 1px solid var(--border-color);
        }
        
        .form-header {
            text-align: center;
            margin-bottom: 24px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .form-header h2 {
            font-size: 24px;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        
        .form-header p {
            color: var(--text-secondary);
            font-size: 14px;
        }
        
        /* Multi-step form styles */
        .form-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 24px;
            position: relative;
        }
        
        .form-steps::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--border-color);
            z-index: 1;
        }
        
        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
        }
        
        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--step-bg);
            border: 2px solid var(--step-border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text-primary);
        }
        
        .step.active .step-circle {
            background: var(--step-active-bg);
            border-color: var(--step-active-bg);
            color: var(--step-active-text);
        }
        
        .step.completed .step-circle {
            background: var(--step-completed-bg);
            border-color: var(--step-completed-bg);
            color: var(--step-completed-text);
        }

        .step.completed .step-circle {
            background: var(--btn-success-bg);
            border-color: var(--btn-success-bg);
            color: var(--btn-success-text);
        }
        
        .step-label {
            font-size: 12px;
            font-weight: 500;
            color: var(--text-secondary);
            text-align: center;
        }
        
        .step.active .step-label {
            color: var(--text-primary);
        }
        
        .form-section {
            margin-bottom: 24px;
            display: none;
        }
        
        .form-section.active {
            display: block;
        }
        
        .form-section h3 {
            font-size: 18px;
            color: var(--text-primary);
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border-color);
        }
        
        /* Add space between instruction text and facility cards */
        .form-section p {
            margin-bottom: 24px;
            color: var(--text-secondary);
            font-size: 14px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        
        input[type="text"],
        input[type="email"],
        input[type="tel"],
        input[type="date"],
        input[type="time"],
        input[type="number"],
        select,
        textarea {
            width: 100%;
            padding: 12px 16px;
            font-size: 15px;
            background: var(--input-bg);
            color: var(--input-text);
            border: 1px solid var(--input-border);
            border-radius: 8px;
            font-family: inherit;
        }
        
        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: var(--text-primary);
            box-shadow: 0 0 0 3px rgba(0,0,0,0.05);
        }
        
        /* Updated facilities grid for 4 rows - no scrolling needed */
        .facilities-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 20px;
            max-height: none;
            overflow: visible;
        }
        
        .facility-card {
            border: 2px solid var(--facility-card-border);
            border-radius: 8px;
            padding: 12px 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            min-height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--facility-card-bg);
        }
        
        .facility-card:hover {
            border-color: var(--text-primary);
            background: var(--facility-card-hover);
        }
        
        .facility-card.selected {
            border-color: var(--facility-card-selected-border);
            background-color: var(--facility-card-selected-bg);
        }
        
        .facility-card h4 {
            font-size: 13px;
            margin-bottom: 0;
            font-weight: 600;
            line-height: 1.3;
            color: var(--text-primary);
        }
        
        .facility-details {
            background: var(--info-item-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
        }
        
        .facility-details-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        
        .facility-details-header h4 {
            font-size: 15px;
            color: var(--text-primary);
        }
        
        .btn-remove {
            padding: 6px 12px;
            background: var(--btn-danger-bg);
            color: var(--btn-danger-text);
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.2s;
        }
        
        .btn-remove:hover {
            background: var(--btn-danger-hover);
        }
        
        .btn-add {
            padding: 10px 20px;
            background: var(--btn-success-bg);
            color: var(--btn-success-text);
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            margin-bottom: 16px;
            transition: all 0.2s;
        }
        
        .btn-add:hover {
            background: var(--btn-success-hover);
        }
        
        .form-navigation {
            display: flex;
            justify-content: space-between;
            margin-top: 24px;
        }
        
        /* Ensure space-between on last step navigation */
        #section-3 .form-navigation {
            justify-content: space-between;
        }

        .btn-prev, .btn-next {
            padding: 12px 20px;
            background: var(--btn-bg);
            color: var(--btn-text);
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            min-height: 44px;
            border: 1px solid var(--btn-border);
            transition: all 0.2s;
        }
        
        .btn-next {
            background: var(--btn-primary-bg);
            color: var(--btn-primary-text);
            border: 1px solid var(--btn-primary-bg);
        }
        
        .btn-next:hover {
            background: var(--btn-primary-hover);
        }
        
        .btn-prev:hover {
            background: var(--btn-hover);
        }
        
        .btn-submit {
            width: 100%;
            padding: 16px;
            background: var(--btn-primary-bg);
            color: var(--btn-primary-text);
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            min-height: 44px;
            transition: all 0.2s;
        }
        
        .btn-submit:hover {
            background: var(--btn-primary-hover);
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
        }
        
        .alert-danger {
            background: var(--alert-danger-bg);
            color: var(--alert-danger-text);
            border: 1px solid var(--alert-danger-border);
        }
        
        .alert-success {
            background: var(--alert-success-bg);
            color: var(--alert-success-text);
            border: 1px solid var(--alert-success-border);
        }

        .alert-warning {
            background: var(--alert-warning-bg);
            color: var(--alert-warning-text);
            border: 1px solid var(--alert-warning-border);
        }
        
        .success-actions {
            display: flex;
            gap: 12px;
            margin-top: 16px;
            flex-direction: column;
        }
        
        .btn-print, .btn-download {
            padding: 12px 20px;
            background: var(--btn-primary-bg);
            color: var(--btn-primary-text);
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            min-height: 44px;
            transition: all 0.2s;
        }
        
        .btn-print:hover, .btn-download:hover {
            background: var(--btn-primary-hover);
        }
        
        /* Make the time input more prominent */
        .detail-time {
            font-weight: 500;
        }
        
        .detail-hours {
            background-color: var(--info-item-bg);
            font-weight: 600;
            color: var(--text-primary);
        }
        
        @media print {
            body * {
                visibility: hidden;
            }
            .printable, .printable * {
                visibility: visible;
            }
            .printable {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                margin: 0;
            }
            .no-print {
                display: none !important;
            }
        }
        
        /* Mobile Responsive Styles */
        @media (min-width: 768px) {
            .navbar {
                padding: 16px 32px;
            }
             .navbar-brand .brand-title {
                font-size: 16px;
            }
            
            .navbar-brand .brand-subtitle {
                font-size: 11px;
            }
            
            .container {
                padding: 32px;
            }
            
            .form-card {
                padding: 40px;
            }
            
            .form-row {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
            }
            
            .facilities-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 16px;
            }
            
            .success-actions {
                flex-direction: row;
            }
            
            .step-label {
                font-size: 14px;
            }
            
            .facility-card h4 {
                font-size: 14px;
            }
        }

        @media (min-width: 1024px) {
            .facilities-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }
        
        @media (max-width: 480px) {
            .navbar-brand .brand-title {
                font-size: 14px;
            }
            
            .navbar-brand .brand-subtitle {
                font-size: 10px;
            }
            
            .form-header h2 {
                font-size: 20px;
            }
            
            .facilities-grid {
                gap: 10px;
            }
            
            .step-circle {
                width: 32px;
                height: 32px;
                font-size: 14px;
            }
            
            .step-label {
                font-size: 10px;
            }
        }

        /* For very small screens */
        @media (max-width: 380px) {
            .facility-card h4 {
                font-size: 12px;
            }
        }

        /* Improve touch targets */
        .facility-card, .btn-remove, .btn-add {
            min-height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body>
<nav class="navbar no-print">
    <a href="dashboard.php" style="text-decoration: none; color: inherit;">
        <div class="navbar-brand">
            <img src="<?php echo htmlspecialchars($logo_file); ?>" alt="Logo">
            <div class="brand-text">
                <div class="brand-title"><?php echo htmlspecialchars($portal_name); ?></div>
                <div class="brand-subtitle">Create Request</div>
            </div>
        </div>
    </a>
    <a href="dashboard.php" class="btn-back">Back</a>
</nav>

    <div class="container">
        <div class="form-card">
            <div class="form-header">
                <h2>Facility Usage Form Request</h2>
                <p>General Services Office (Property Custodian)</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                    <div class="success-actions">
                        <button class="btn-print" onclick="printForm()">Print Form</button>
                        <button class="btn-download" onclick="downloadPDF()">Download PDF</button>
                    </div>
                </div>

                <div class="alert alert-warning no-print" style="margin-top: 20px;">
                    <h4 style="margin-bottom: 8px; font-weight: 700;">Important Next Steps and Reminders</h4>
                    <ul style="list-style: disc; margin-left: 20px;">
                        <li>**After Printing/Downloading:** Proceed to the **General Services Office (GSO)** for the Property Custodian's final approval and signature.</li>
                        <li>**Submit to GSO:** Ensure you submit the signed copy of this form at least **three (3) days** before the scheduled event date.</li>
                        <li>**Facility Care:** Please ensure the facility is left clean and in its original condition after use. Any damage to the property will be the responsibility of the requestor.</li>
                    </ul>
                </div>

                <script>
                    const submittedData = <?php echo json_encode($success_data); ?>;
                    const controlNumber = "<?php echo $control_number; ?>";
                </script>
            <?php endif; ?>

            <?php if (!$success): ?>
            <div class="form-steps">
                <div class="step active" id="step-1">
                    <div class="step-circle">1</div>
                    <div class="step-label">Select Facilities</div>
                </div>
                <div class="step" id="step-2">
                    <div class="step-circle">2</div>
                    <div class="step-label">Details & Schedule</div>
                </div>
                <div class="step" id="step-3">
                    <div class="step-circle">3</div>
                    <div class="step-label">Requestor Info</div>
                </div>
            </div>

            <form method="POST" action="" id="request-form">
                <div class="form-section active" id="section-1">
                    <h3>Select Facilities</h3>
                    <p>Click on the facilities you need for your event:</p>
                    
                    <div class="facilities-grid" id="facilities-grid">
                        <?php foreach ($facilities_list as $facility): ?>
                            <div class="facility-card" data-facility="<?php echo $facility; ?>">
                                <h4><?php echo $facility; ?></h4>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="form-navigation">
                        <div></div>
                        <button type="button" class="btn-next" onclick="nextStep(1)">Next</button>
                    </div>
                </div>

                <div class="form-section" id="section-2">
                    <h3>Details of Selected Facilities</h3>
                    
                    <div id="facilities-container">
                        <!-- Facility details will be generated here -->
                    </div>
                    
                    <div class="form-navigation">
                        <button type="button" class="btn-prev" onclick="prevStep(2)">Previous</button>
                        <button type="button" class="btn-next" onclick="nextStep(2)">Next</button>
                    </div>
                </div>

                <div class="form-section" id="section-3">
                    <h3>Requestor Information</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Name of Requestor *</label>
                            <input type="text" name="requestor_name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Department/Office *</label>
                            <input type="text" name="department" value="<?php echo htmlspecialchars($user['department']); ?>" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Email *</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="tel" name="phone_number" placeholder="+63 XXX XXX XXXX">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Type of Event *</label>
                        <input type="text" name="event_type" placeholder="e.g., Seminar, Workshop, Meeting, Conference" required>
                    </div>

                    <div class="form-navigation">
                        <button type="button" class="btn-prev" onclick="prevStep(3)">Previous</button>
                        <button type="submit" class="btn-submit">Submit Request</button>
                    </div>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>

<div id="printable-form" class="printable" style="display: none;">
    <div style="max-width: 800px; margin: 0 auto; padding: 40px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
        <!-- Header with Circular Logo and Text -->
        <div style="display: flex; align-items: center; justify-content: center; margin-bottom: 30px; border-bottom: 1px solid #000; padding-bottom: 20px;">
            <div style="flex-shrink: 0; margin-right: 20px;">
                <img src="combined-logo.png" alt="Logo" style="height: 80px; width: 80px; border-radius: 50%; object-fit: cover;">
            </div>
            <div style="text-align: center; flex-grow: 1;">
                <h1 style="font-size: 24px; margin-bottom: 5px; font-weight: bold;">FACILITY USAGE FORM REQUEST</h1>
                <p style="font-size: 14px; color: #555; margin-bottom: 10px;">General Services Office (Property Custodian)</p>
                <p id="print-control-number" style="font-size: 16px; font-weight: bold; display: inline-block; padding: 5px 15px; background: #f5f5f5; border-radius: 4px;"></p>
            </div>
        </div>
            
            <div style="margin-bottom: 30px;">
                <h2 style="font-size: 18px; margin-bottom: 15px; border-bottom: 1px solid #000; padding-bottom: 10px; font-weight: bold;">REQUESTOR INFORMATION</h2>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="width: 50%; padding: 5px 0;">
                            <p style="font-size: 11px; color: #555; margin-bottom: 2px;">NAME OF REQUESTOR</p>
                            <p id="print-requestor-name" style="font-weight: 600;"></p>
                        </td>
                        <td style="width: 50%; padding: 5px 0;">
                            <p style="font-size: 11px; color: #555; margin-bottom: 2px;">DEPARTMENT/OFFICE</p>
                            <p id="print-requestor-dept" style="font-weight: 600;"></p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 10px 0 5px 0;">
                            <p style="font-size: 11px; color: #555; margin-bottom: 2px;">EMAIL</p>
                            <p id="print-requestor-email" style="font-weight: 600;"></p>
                        </td>
                        <td style="padding: 10px 0 5px 0;">
                            <p style="font-size: 11px; color: #555; margin-bottom: 2px;">PHONE NUMBER</p>
                            <p id="print-requestor-phone" style="font-weight: 600;"></p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 10px 0 5px 0;" colspan="2">
                            <p style="font-size: 11px; color: #555; margin-bottom: 2px;">TYPE OF EVENT</p>
                            <p id="print-requestor-event" style="font-weight: 600;"></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div style="margin-bottom: 30px;">
                <h2 style="font-size: 18px; margin-bottom: 15px; border-bottom: 1px solid #000; padding-bottom: 10px; font-weight: bold;">DETAILS OF FACILITY NEEDED</h2>
                <div id="print-facilities-container">
                    <!-- Facility details will be populated here -->
                </div>
            </div>
            
            <div style="margin-top: 50px; border-top: 1px solid #ddd; padding-top: 20px;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="width: 50%; text-align: left;">
                            <p style="margin-bottom: 5px; font-weight: 600;">REQUESTOR SIGNATURE</p>
                            <p style="border-bottom: 1px dashed #555; width: 80%; padding-bottom: 5px; margin-top: 40px;"></p>
                            <p style="font-size: 14px;">Date: <?php echo date('m/d/Y'); ?></p>
                        </td>
                        <td style="width: 50%; text-align: left;">
                            <p style="margin-bottom: 5px; font-weight: 600;">APPROVED BY (Property Custodian)</p>
                            <p style="border-bottom: 1px dashed #555; width: 80%; padding-bottom: 5px; margin-top: 40px;"></p>
                            <p style="font-size: 14px;">Date: ____________</p>
                        </td>
                    </tr>
                </table>
            </div>

            <div style="text-align: center; margin-top: 40px; font-size: 10px; color: #888;">
                <p>This is an official document from MCNP-ISAP General Services Office</p>
                <p>Generated on <?php echo date('m/d/Y, h:i:s A'); ?></p>
            </div>
        </div>
    </div>

    <script>
        let currentStep = 1;
        let selectedFacilities = [];
        let facilityCount = 0;
        let allBookings = {};

        function generateTimeOptions() {
            let options = '<option value="">Select time</option>';
            for (let i = 7; i <= 22; i++) { // 7 AM to 10 PM
                for (let j = 0; j < 60; j += 30) {
                    if (i === 22 && j > 0) continue; // Stop at 10:00 PM

                    const hour = i.toString().padStart(2, '0');
                    const minute = j.toString().padStart(2, '0');
                    const time = `${hour}:${minute}`;
                    
                    const d = new Date(`1970-01-01T${time}:00`);
                    const displayTime = d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
                    
                    options += `<option value="${time}">${displayTime}</option>`;
                }
            }
            return options;
        }

        // Fetch all bookings when the page loads
        async function fetchAllBookings() {
            try {
                const response = await fetch('api/get_bookings.php');
                const bookings = await response.json();
                
                // Process bookings into a more usable format
                allBookings = {};
                bookings.forEach(booking => {
                    if (!allBookings[booking.facility_name]) {
                        allBookings[booking.facility_name] = {};
                    }
                    if (!allBookings[booking.facility_name][booking.date_needed]) {
                        allBookings[booking.facility_name][booking.date_needed] = [];
                    }
                    allBookings[booking.facility_name][booking.date_needed].push(booking.time_needed);
                });
            } catch (error) {
                console.error('Error fetching bookings:', error);
            }
        }

        function nextStep(step) {
            if (step === 1) {
                // Validate step 2
                if (selectedFacilities.length === 0) {
                    alert('Please select at least one facility.');
                    return;
                }
                
                // Generate facility details forms
                generateFacilityDetails();
            } else if (step === 2) {
                // Validate step 2 (now details)
                const requiredFields = document.querySelectorAll('#section-2 [required]');
                let valid = true;
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        valid = false;
                        field.style.borderColor = '#ef4444';
                    } else {
                        field.style.borderColor = '';
                    }
                });
                if (!valid) {
                    alert('Please fill in all required facility details.');
                    return;
                }
            }
            
            // Hide current step
            document.getElementById(`section-${currentStep}`).classList.remove('active');
            document.getElementById(`step-${currentStep}`).classList.remove('active');
            document.getElementById(`step-${currentStep}`).classList.add('completed');
            
            // Show next step
            currentStep = step + 1;
            document.getElementById(`section-${currentStep}`).classList.add('active');
            document.getElementById(`step-${currentStep}`).classList.add('active');
        }
        
        function prevStep(step) {
            // Hide current step
            document.getElementById(`section-${currentStep}`).classList.remove('active');
            document.getElementById(`step-${currentStep}`).classList.remove('active');
            document.getElementById(`step-${currentStep}`).classList.remove('completed');
            
            // Show previous step
            currentStep = step - 1;
            document.getElementById(`section-${currentStep}`).classList.add('active');
            document.getElementById(`step-${currentStep}`).classList.add('active');
        }
        
        // Facility selection
        document.addEventListener('DOMContentLoaded', function() {
            // Fetch bookings on page load
            fetchAllBookings();

            const facilityCards = document.querySelectorAll('.facility-card');
            
            facilityCards.forEach(card => {
                card.addEventListener('click', function() {
                    const facility = this.getAttribute('data-facility');
                    
                    if (this.classList.contains('selected')) {
                        // Remove facility
                        this.classList.remove('selected');
                        selectedFacilities = selectedFacilities.filter(f => f !== facility);
                    } else {
                        // Add facility
                        this.classList.add('selected');
                        selectedFacilities.push(facility);
                    }
                });
            });
            
            // Set minimum date to today
            const today = new Date().toISOString().split('T')[0];
            document.querySelectorAll('input[type="date"]').forEach(input => {
                input.min = today;
            });

            // If on success page, immediately prepare the print form data
            if (typeof submittedData !== 'undefined' && submittedData.facilities.length > 0) {
                preparePrintForm(submittedData, controlNumber);
            }
        });
        
        // Generate facility details forms with separate start and end time inputs
        function generateFacilityDetails() {
            const container = document.getElementById('facilities-container');
            container.innerHTML = '';
            facilityCount = 0;
            
            selectedFacilities.forEach((facility, index) => {
                facilityCount++;
                const facilityItem = document.createElement('div');
                facilityItem.className = 'facility-details';
                facilityItem.innerHTML = `
                    <div class="facility-details-header">
                        <h4>Facility #${index + 1}: ${facility}</h4>
                        <button type="button" class="btn-remove" onclick="removeFacility('${facility}')">Remove</button>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Date Needed *</label>
                            <input type="date" name="dates[]" class="detail-date" required onchange="updateAvailableTimes(this)">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Start Time *</label>
                            <select name="start_times[]" class="detail-start-time" required onchange="calculateHoursFromTime(this)"></select>
                        </div>
                        <div class="form-group">
                            <label>End Time *</label>
                            <select name="end_times[]" class="detail-end-time" required onchange="calculateHoursFromTime(this)"></select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Total Hours *</label>
                            <input type="number" name="hours[]" class="detail-hours" step="0.5" min="0.5" placeholder="Auto-calculated" readonly required>
                        </div>
                        <div class="form-group">
                            <label>Total Number of Participants *</label>
                            <input type="number" name="participants[]" class="detail-participants" min="1" placeholder="e.g., 50" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Remarks</label>
                        <textarea name="remarks[]" class="detail-remarks" rows="2" placeholder="Additional notes or requirements"></textarea>
                    </div>
                    <input type="hidden" name="facilities[]" value="${facility}">
                `;
                container.appendChild(facilityItem);
            });

            const timeOptions = generateTimeOptions();
            document.querySelectorAll('.detail-start-time, .detail-end-time').forEach(select => {
                select.innerHTML = timeOptions;
            });

        }
        
        function removeFacility(facility) {
            // Remove from selected facilities
            selectedFacilities = selectedFacilities.filter(f => f !== facility);
            
            // Update UI
            const card = document.querySelector(`.facility-card[data-facility="${facility}"]`);
            if (card) {
                card.classList.remove('selected');
            }
            
            // Regenerate facility details
            generateFacilityDetails();
        }

        function updateAvailableTimes(dateInput) {
            const facilityDetails = dateInput.closest('.facility-details');
            const facilityName = facilityDetails.querySelector('input[name="facilities[]"]').value;
            const selectedDate = dateInput.value;
            const startTimeSelect = facilityDetails.querySelector('.detail-start-time');
            const endTimeSelect = facilityDetails.querySelector('.detail-end-time');

            // Reset all options to be enabled
            [...startTimeSelect.options, ...endTimeSelect.options].forEach(opt => opt.disabled = false);

            if (!selectedDate || !allBookings[facilityName] || !allBookings[facilityName][selectedDate]) {
                return; // No bookings for this facility on this day
            }

            const bookedRanges = allBookings[facilityName][selectedDate].map(timeRange => {
                const [start, end] = timeRange.split(' to ');
                return { start, end };
            });

            function isTimeBooked(time) {
                for (const range of bookedRanges) {
                    // A time slot is considered booked if it's within a booked range (exclusive of the end time)
                    if (time >= range.start && time < range.end) {
                        return true;
                    }
                }
                return false;
            }

            [...startTimeSelect.options, ...endTimeSelect.options].forEach(option => {
                if (option.value && isTimeBooked(option.value)) {
                    option.disabled = true;
                    option.textContent = `${option.text} (Booked)`;
                } else if (option.value) {
                    // Reset text content if it was previously marked as booked
                    option.textContent = option.text.replace(' (Booked)', '');
                }
            });
        }

        // IMPROVED TIME CALCULATION FUNCTION with separate start/end times
        function calculateHoursFromTime(timeInput) {
            const facilityDetails = timeInput.closest('.facility-details');
            const startTimeInput = facilityDetails.querySelector('.detail-start-time');
            const endTimeInput = facilityDetails.querySelector('.detail-end-time');
            const hoursInput = facilityDetails.querySelector('.detail-hours');
            const dateInput = facilityDetails.querySelector('.detail-date');
            
            if (!startTimeInput.value || !endTimeInput.value || !dateInput.value) {
                hoursInput.value = '';
                return;
            }
            
            // Create date objects with the selected date
            const startDateTime = new Date(`${dateInput.value}T${startTimeInput.value}`);
            const endDateTime = new Date(`${dateInput.value}T${endTimeInput.value}`);
            
            // Handle overnight events (if end time is before start time, assume next day)
            if (endDateTime <= startDateTime) {
                endDateTime.setDate(endDateTime.getDate() + 1);
            }
            
            // Calculate difference in hours
            const diffMs = endDateTime - startDateTime;
            const totalHours = diffMs / (1000 * 60 * 60);
            
            if (totalHours > 0) {
                hoursInput.value = Math.round(totalHours * 2) / 2; // Round to nearest 0.5
            } else {
                hoursInput.value = '';
                alert('End time must be after start time.');
            }
        }

        function preparePrintForm(data, controlNum) {
            const printFacilityContainer = document.getElementById('print-facilities-container');
            const requestor = data.requestor;
            const facilities = data.facilities;

            document.getElementById('print-control-number').textContent = `Control Number: ${controlNum}`;
            document.getElementById('print-requestor-name').textContent = requestor.name;
            document.getElementById('print-requestor-dept').textContent = requestor.department;
            document.getElementById('print-requestor-email').textContent = requestor.email;
            document.getElementById('print-requestor-phone').textContent = requestor.phone_number || 'N/A';
            document.getElementById('print-requestor-event').textContent = requestor.event_type;

            printFacilityContainer.innerHTML = '';

            if (facilities.length === 0) {
                printFacilityContainer.innerHTML = '<p style="padding: 10px; text-align: center; border: 1px solid #ddd;">No facility details submitted.</p>';
                return;
            }

            facilities.forEach((item, index) => {
                const formattedDate = item.date ? new Date(item.date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A';
                
                // Ensure AM/PM format for start and end times
                const startTime = item.start_time 
                    ? new Date(`1970-01-01T${item.start_time}:00`).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }) 
                    : 'N/A';
                const endTime = item.end_time 
                    ? new Date(`1970-01-01T${item.end_time}:00`).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }) 
                    : 'N/A';

                const detailBlock = document.createElement('div');
                detailBlock.style.border = '1px solid #ddd';
                detailBlock.style.borderRadius = '4px';
                detailBlock.style.padding = '15px';
                detailBlock.style.marginBottom = '20px';
                detailBlock.style.backgroundColor = '#fff';
                
                detailBlock.innerHTML = `
                    <h4 style="font-size: 16px; margin-bottom: 10px; font-weight: bold;">Facility #${index + 1}: ${item.name}</h4>
                    <div style="display: flex; flex-wrap: wrap;">
                        <div style="width: 50%; padding-right: 15px; margin-bottom: 10px;">
                            <p style="font-size: 11px; color: #555; margin-bottom: 2px;">DATE NEEDED</p>
                            <p style="font-weight: 600;">${formattedDate}</p>
                        </div>
                        <div style="width: 50%; margin-bottom: 10px;">
                            <p style="font-size: 11px; color: #555; margin-bottom: 2px;">TIME NEEDED</p>
                            <p style="font-weight: 600;">${startTime} to ${endTime}</p>
                        </div>
                        <div style="width: 50%; padding-right: 15px; margin-bottom: 10px;">
                            <p style="font-size: 11px; color: #555; margin-bottom: 2px;">TOTAL HOURS</p>
                            <p style="font-weight: 600;">${item.hours ? item.hours + ' hours' : 'N/A'}</p>
                        </div>
                        <div style="width: 50%; margin-bottom: 10px;">
                            <p style="font-size: 11px; color: #555; margin-bottom: 2px;">TOTAL PARTICIPANTS</p>
                            <p style="font-weight: 600;">${item.participants ? item.participants + ' participants' : 'N/A'}</p>
                        </div>
                    </div>
                    <div style="margin-top: 10px; border-top: 1px solid #eee; padding-top: 10px;">
                        <p style="font-size: 11px; color: #555; margin-bottom: 2px;">REMARKS</p>
                        <p style="font-weight: 600;">${item.remarks || 'None'}</p>
                    </div>
                `;
                printFacilityContainer.appendChild(detailBlock);
            });
        }

        function printForm() {
            if (typeof submittedData !== 'undefined' && submittedData.facilities.length > 0) {
                preparePrintForm(submittedData, controlNumber);
            } else {
                const liveFacilityDetails = document.querySelectorAll('#facilities-container .facility-details');
                
                if (liveFacilityDetails.length === 0) {
                    alert("Cannot print: No facility details have been entered.");
                    return;
                }
                
                const requestor = {
                    name: document.querySelector('input[name="requestor_name"]').value,
                    department: document.querySelector('input[name="department"]').value,
                    email: document.querySelector('input[name="email"]').value,
                    phone_number: document.querySelector('input[name="phone_number"]').value,
                    event_type: document.querySelector('input[name="event_type"]').value,
                };

                const facilities = Array.from(liveFacilityDetails).map((item, index) => ({
                    name: item.querySelector('input[name="facilities[]"]').value,
                    date: item.querySelector('.detail-date').value,
                    start_time: item.querySelector('.detail-start-time').value,
                    end_time: item.querySelector('.detail-end-time').value,
                    hours: item.querySelector('.detail-hours').value,
                    participants: item.querySelector('.detail-participants').value,
                    remarks: item.querySelector('.detail-remarks').value,
                }));

                const liveData = { requestor: requestor, facilities: facilities };
                const tempControl = document.querySelector('#request-form').getAttribute('data-control') || 'N/A-Draft';
                
                let allDetailsValid = facilities.every(f => f.date && f.start_time && f.end_time && f.hours && f.participants);
                if (!allDetailsValid) {
                    alert("Please fill in all required facility details (Date, Start Time, End Time, Hours, Participants) before printing a draft.");
                    return;
                }
                
                preparePrintForm(liveData, tempControl);
            }
            
            const printableForm = document.getElementById('printable-form');
            printableForm.style.display = 'block';
            
            window.print();
            
            setTimeout(() => {
                printableForm.style.display = 'none';
            }, 500);
        }
        
        function downloadPDF() {
            alert('PDF download functionality would be implemented here. For now, please use the print function and choose "Save as PDF" from your browser\'s print dialog.');
        }
    </script>

</body>
</html>