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
    $control_number = 'MCNP-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
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
        $times = $_POST['times'] ?? [];
        $hours = $_POST['hours'] ?? [];
        $participants = $_POST['participants'] ?? [];
        $remarks = $_POST['remarks'] ?? [];
        
        $success_data['facilities'] = [];

        $detail_sql = "INSERT INTO facility_request_details (request_id, facility_name, date_needed, time_needed, total_hours, total_participants, remarks) 
                       VALUES (?, ?, ?, ?, ?, ?, ?)";
        $detail_stmt = $conn->prepare($detail_sql);
        
        foreach ($facilities as $index => $facility) {
            if (!empty($facility) && !empty($dates[$index])) {
                $detail_stmt->bind_param("isssdis", 
                    $request_id, 
                    $facility, 
                    $dates[$index], 
                    $times[$index], 
                    $hours[$index], 
                    $participants[$index], 
                    $remarks[$index]
                );
                $detail_stmt->execute();
                
                // Store facility details for printing
                $success_data['facilities'][] = [
                    'name' => $facility,
                    'date' => $dates[$index],
                    'time' => $times[$index],
                    'hours' => $hours[$index],
                    'participants' => $participants[$index],
                    'remarks' => $remarks[$index]
                ];
            }
        }
        
        $success = "Request submitted successfully! Control Number: <strong>$control_number</strong>";
    } else {
        $error = "Error submitting request: " . $conn->error;
    }
}

// Available facilities
$facilities_list = [
    'HM Laboratory',
    'Function Hall',
    'Conference Hall',
    'Hotel Room',
    'TM Laboratory',
    'Gymnasium',
    'AVR 1',
    'AVR 2',
    'AVR 3',
    'AMPHI 1',
    'AMPHI 2',
    'AMPHI 3',
    'Quadrangle',
    'Reading Area',
    'Studio Room',
    'Cabbo La Vista',
    'Pamplona La Vista',
    'ISAP-Tug Retreat House',
    'Other'
];
$theme = isset($_SESSION['theme']) ? $_SESSION['theme'] : 'light';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($theme); ?>">
<head>
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
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f7fa;
        }
        
        .navbar {
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 16px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
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
            background: #f3f4f6;
            color: #1a1a1a;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
        }
        
        .btn-back:hover {
            background: #e5e7eb;
        }
        
        .container {
            max-width: 1000px;
            margin: 32px auto;
            padding: 0 32px;
        }
        
        .form-card {
            background: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .form-header {
            text-align: center;
            margin-bottom: 32px;
            padding-bottom: 24px;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .form-header h2 {
            font-size: 28px;
            color: #1a1a1a;
            margin-bottom: 8px;
        }
        
        .form-header p {
            color: #6b7280;
        }
        
        /* Multi-step form styles */
        .form-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 32px;
            position: relative;
        }
        
        .form-steps::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 2px;
            background: #e5e7eb;
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
            background: white;
            border: 2px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .step.active .step-circle {
            background: #1a1a1a;
            border-color: #1a1a1a;
            color: white;
        }
        
        .step.completed .step-circle {
            background: #10b981;
            border-color: #10b981;
            color: white;
        }
        
        .step-label {
            font-size: 14px;
            font-weight: 500;
            color: #6b7280;
        }
        
        .step.active .step-label {
            color: #1a1a1a;
        }
        
        .form-section {
            margin-bottom: 32px;
            display: none;
        }
        
        .form-section.active {
            display: block;
        }
        
        .form-section h3 {
            font-size: 18px;
            color: #1a1a1a;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #374151;
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
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-family: inherit;
        }
        
        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: #1a1a1a;
            box-shadow: 0 0 0 3px rgba(0,0,0,0.05);
        }
        
        .facilities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .facility-card {
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .facility-card:hover {
            border-color: #1a1a1a;
        }
        
        .facility-card.selected {
            border-color: #1a1a1a;
            background-color: #f9fafb;
        }
        
        .facility-card h4 {
            font-size: 14px;
            margin-bottom: 8px;
        }
        
        .facility-card p {
            font-size: 12px;
            color: #6b7280;
        }
        
        .facility-details {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 16px;
        }
        
        .facility-details-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        
        .facility-details-header h4 {
            font-size: 16px;
            color: #1a1a1a;
        }
        
        .btn-remove {
            padding: 6px 12px;
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .btn-remove:hover {
            background: #dc2626;
        }
        
        .btn-add {
            padding: 10px 20px;
            background: #10b981;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            margin-bottom: 20px;
        }
        
        .btn-add:hover {
            background: #059669;
        }
        
        .form-navigation {
            display: flex;
            justify-content: space-between;
            margin-top: 32px;
        }
        
        .btn-prev, .btn-next {
            padding: 12px 24px;
            background: #f3f4f6;
            color: #1a1a1a;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
        }
        
        .btn-next {
            background: #1a1a1a;
            color: white;
        }
        
        .btn-next:hover {
            background: #000;
        }
        
        .btn-prev:hover {
            background: #e5e7eb;
        }
        
        .btn-submit {
            width: 100%;
            padding: 16px;
            background: #1a1a1a;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
        }
        
        .btn-submit:hover {
            background: #000;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .success-actions {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }
        
        .btn-print, .btn-download {
            padding: 12px 24px;
            background: #1a1a1a;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-print:hover, .btn-download:hover {
            background: #000;
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
                /* Add margin for clean print look */
                margin: 0; 
            }
            .no-print {
                display: none !important;
            }
        }
        
        [data-theme="dark"] {
    --bg-primary: #1a1a1a;
    --bg-secondary: #2d2d2d;
    --text-primary: #ffffff;
    --text-secondary: #9ca3af;
    --border-color: #404040;
    --accent-color: #818cf8;
}
    </style>
</head>
<body>
    <nav class="navbar no-print">
        <div class="navbar-brand">
            <img src="<?php echo htmlspecialchars($logo_file); ?>" alt="Logo">
            <h1><?php echo htmlspecialchars($portal_name); ?></h1>
        </div>
        <a href="dashboard.php" class="btn-back">← Back to Dashboard</a>
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

                <div class="alert no-print" style="background: #fffbe6; color: #78350f; border: 1px solid #fde68a; margin-top: 20px;">
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
                    <div class="step-label">Requestor Info</div>
                </div>
                <div class="step" id="step-2">
                    <div class="step-circle">2</div>
                    <div class="step-label">Select Facilities</div>
                </div>
                <div class="step" id="step-3">
                    <div class="step-circle">3</div>
                    <div class="step-label">Details & Submit</div>
                </div>
            </div>

            <form method="POST" action="" id="request-form">
                <div class="form-section active" id="section-1">
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
                        <div></div> <button type="button" class="btn-next" onclick="nextStep(1)">Next</button>
                    </div>
                </div>

                <div class="form-section" id="section-2">
                    <h3>Select Facilities</h3>
                    <p>Click on the facilities you need for your event:</p>
                    
                    <div class="facilities-grid" id="facilities-grid">
                        <?php foreach ($facilities_list as $facility): ?>
                            <div class="facility-card" data-facility="<?php echo $facility; ?>">
                                <h4><?php echo $facility; ?></h4>
                                <p>Click to select</p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="form-navigation">
                        <button type="button" class="btn-prev" onclick="prevStep(2)">Previous</button>
                        <button type="button" class="btn-next" onclick="nextStep(2)">Next</button>
                    </div>
                </div>

                <div class="form-section" id="section-3">
                    <h3>Details of Selected Facilities</h3>
                    
                    <div id="facilities-container">
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
            <div style="text-align: center; margin-bottom: 30px; border-bottom: 1px solid #000; padding-bottom: 20px;">
                <h1 style="font-size: 24px; margin-bottom: 5px; font-weight: bold;">FACILITY USAGE FORM REQUEST</h1>
                <p style="font-size: 14px; color: #555; margin-bottom: 10px;">General Services Office (Property Custodian)</p>
                <p id="print-control-number" style="font-size: 16px; font-weight: bold; display: inline-block; padding: 5px 15px;"></p>
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

        // Step navigation functions (UNCHANGED)
        function nextStep(step) {
            if (step === 1) {
                // Validate step 1
                const requiredFields = document.querySelectorAll('#section-1 [required]');
                let valid = true;
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        valid = false;
                        field.style.borderColor = '#ef4444';
                    } else {
                        field.style.borderColor = '#d1d5db';
                    }
                });
                
                if (!valid) {
                    alert('Please fill in all required fields.');
                    return;
                }
            } else if (step === 2) {
                // Validate step 2
                if (selectedFacilities.length === 0) {
                    alert('Please select at least one facility.');
                    return;
                }
                
                // Generate facility details forms
                generateFacilityDetails();
            }
            
            // Hide current step
            document.getElementById(`section-${currentStep}`).classList.remove('active');
            document.getElementById(`step-${currentStep}`).classList.remove('active');
            
            // Show next step
            currentStep = step + 1;
            document.getElementById(`section-${currentStep}`).classList.add('active');
            document.getElementById(`step-${currentStep}`).classList.add('active');
        }
        
        function prevStep(step) {
            // Hide current step
            document.getElementById(`section-${currentStep}`).classList.remove('active');
            document.getElementById(`step-${currentStep}`).classList.remove('active');
            
            // Show previous step
            currentStep = step - 1;
            document.getElementById(`section-${currentStep}`).classList.add('active');
            document.getElementById(`step-${currentStep}`).classList.add('active');
        }
        
        // Facility selection (UNCHANGED)
        document.addEventListener('DOMContentLoaded', function() {
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

            // NEW: If on success page, immediately prepare the print form data
            if (typeof submittedData !== 'undefined' && submittedData.facilities.length > 0) {
                preparePrintForm(submittedData, controlNumber);
            }
        });
        
        // Generate facility details forms (MODIFIED TO REMOVE PRINT-SPECIFIC TABLE LOGIC)
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
                            <input type="date" name="dates[]" class="detail-date" required>
                        </div>
                        <div class="form-group">
                            <label>Time Needed *</label>
                            <input type="text" name="times[]" class="detail-time" placeholder="e.g., 8:00 AM - 5:00 PM" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Total Hours *</label>
                            <input type="number" name="hours[]" class="detail-hours" step="0.5" min="0.5" placeholder="e.g., 8" required>
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

        // NEW FUNCTION: Populates the print form structure with data
        function preparePrintForm(data, controlNum) {
            const printFacilityContainer = document.getElementById('print-facilities-container');
            const requestor = data.requestor;
            const facilities = data.facilities;

            // 1. Populate Requestor Info
            document.getElementById('print-control-number').textContent = `Control Number: ${controlNum}`;
            document.getElementById('print-requestor-name').textContent = requestor.name;
            document.getElementById('print-requestor-dept').textContent = requestor.department;
            document.getElementById('print-requestor-email').textContent = requestor.email;
            document.getElementById('print-requestor-phone').textContent = requestor.phone_number || 'N/A';
            document.getElementById('print-requestor-event').textContent = requestor.event_type;

            // 2. Clear and Populate Facility Details (matching the image block style)
            printFacilityContainer.innerHTML = '';

            if (facilities.length === 0) {
                printFacilityContainer.innerHTML = '<p style="padding: 10px; text-align: center; border: 1px solid #ddd;">No facility details submitted.</p>';
                return;
            }

            facilities.forEach((item, index) => {
                const formattedDate = item.date ? new Date(item.date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A';

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
                            <p style="font-weight: 600;">${item.time || 'N/A'}</p>
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
        
        // Print form function (MODIFIED to call preparePrintForm if on the submission page)
        function printForm() {
            if (typeof submittedData !== 'undefined' && submittedData.facilities.length > 0) {
                // We are on the success page, the data is in the PHP variable
                preparePrintForm(submittedData, controlNumber);
            } else {
                // If not on success page, try to pull from live form (for testing/drafts)
                const liveFacilityDetails = document.querySelectorAll('#facilities-container .facility-details');
                
                if (liveFacilityDetails.length === 0) {
                    alert("Cannot print: No facility details have been entered.");
                    return;
                }
                
                // --- Reconstruct Data from Live Fields (Fallback for non-submission print) ---
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
                    time: item.querySelector('.detail-time').value,
                    hours: item.querySelector('.detail-hours').value,
                    participants: item.querySelector('.detail-participants').value,
                    remarks: item.querySelector('.detail-remarks').value,
                }));

                const liveData = { requestor: requestor, facilities: facilities };
                const tempControl = document.querySelector('#request-form').getAttribute('data-control') || 'N/A-Draft'; // You might need to set a data-control attribute on the form for this to work perfectly outside of submission.
                
                // Do basic validation for live draft printing
                let allDetailsValid = facilities.every(f => f.date && f.time && f.hours && f.participants);
                if (!allDetailsValid) {
                    alert("Please fill in all required facility details (Date, Time, Hours, Participants) before printing a draft.");
                    return;
                }
                
                preparePrintForm(liveData, tempControl);
            }
            
            // Execute print
            const printableForm = document.getElementById('printable-form');
            printableForm.style.display = 'block';
            
            window.print();
            
            // Hide printable form after printing
            setTimeout(() => {
                printableForm.style.display = 'none';
            }, 500);
        }
        
        // Download PDF function (placeholder)
        function downloadPDF() {
            alert('PDF download functionality would be implemented here. For now, please use the print function and choose "Save as PDF" from your browser\'s print dialog.');
        }
    </script>
    <?php include 'chat_bot.php'; ?>
</body>
</html>