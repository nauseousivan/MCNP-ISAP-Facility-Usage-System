<?php
session_start();
require_once 'theme_loader.php';
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Get current month and year
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$facility_filter = isset($_GET['facility']) ? $_GET['facility'] : '';

$sql_year = "SELECT frd.date_needed, frd.facility_name, COUNT(*) as booking_count 
        FROM facility_request_details frd 
        JOIN facility_requests fr ON frd.request_id = fr.id 
        WHERE fr.status = 'approved' 
        AND YEAR(frd.date_needed) = ?";

if ($facility_filter) {
    $sql_year .= " AND frd.facility_name = ?";
}

$sql_year .= " GROUP BY frd.date_needed, frd.facility_name";

$stmt_year = $conn->prepare($sql_year);
if ($facility_filter) {
    $stmt_year->bind_param("is", $year, $facility_filter);
} else {
    $stmt_year->bind_param("i", $year);
}
$stmt_year->execute();
$year_bookings_result = $stmt_year->get_result();

$year_bookings = [];
while ($row = $year_bookings_result->fetch_assoc()) {
    if (!isset($year_bookings[$row['date_needed']])) {
        $year_bookings[$row['date_needed']] = 0;
    }
    $year_bookings[$row['date_needed']] += $row['booking_count'];
}

$sql = "SELECT frd.date_needed, frd.facility_name, COUNT(*) as booking_count 
        FROM facility_request_details frd 
        JOIN facility_requests fr ON frd.request_id = fr.id 
        WHERE fr.status = 'approved' 
        AND MONTH(frd.date_needed) = ? 
        AND YEAR(frd.date_needed) = ?";

if ($facility_filter) {
    $sql .= " AND frd.facility_name = ?";
}

$sql .= " GROUP BY frd.date_needed, frd.facility_name";

$stmt = $conn->prepare($sql);
if ($facility_filter) {
    $stmt->bind_param("iis", $month, $year, $facility_filter);
} else {
    $stmt->bind_param("ii", $month, $year);
}
$stmt->execute();
$bookings_result = $stmt->get_result();

$bookings = [];
while ($row = $bookings_result->fetch_assoc()) {
    if (!isset($bookings[$row['date_needed']])) {
        $bookings[$row['date_needed']] = 0;
    }
    $bookings[$row['date_needed']] += $row['booking_count'];
}

// Calculate calendar data
$first_day = mktime(0, 0, 0, $month, 1, $year);
$days_in_month = date('t', $first_day);
$day_of_week = date('w', $first_day);
$month_name = date('F Y', $first_day);

// Previous and next month
$prev_month = $month - 1;
$prev_year = $year;
if ($prev_month < 1) {
    $prev_month = 12;
    $prev_year--;
}

$next_month = $month + 1;
$next_year = $year;
if ($next_month > 12) {
    $next_month = 1;
    $next_year++;
}

$holidays = [
    '2025-01-01' => 'New Year\'s Day',
    '2025-04-09' => 'Araw ng Kagitingan',
    '2025-04-17' => 'Maundy Thursday',
    '2025-04-18' => 'Good Friday',
    '2025-05-01' => 'Labor Day',
    '2025-06-12' => 'Independence Day',
    '2025-08-25' => 'National Heroes Day',
    '2025-11-01' => 'All Saints\' Day',
    '2025-11-30' => 'Bonifacio Day',
    '2025-12-25' => 'Christmas Day',
    '2025-12-30' => 'Rizal Day',
    '2025-12-31' => 'New Year\'s Eve'
];

$facilities_list = [
    'HM Laboratory',
    'Function Hall',
    'Conference Hall',
    'Gymnasium',
    'AVR 1',
    'AVR 2',
    'AVR 3',
    'AMPHI 1',
    'AMPHI 2',
    'AMPHI 3',
    'Reading Area',
    'Studio Room'
];

$logo_file = $GLOBALS['logo_file'];
$portal_name = $GLOBALS['portal_name'];
$theme = isset($_SESSION['theme']) ? $_SESSION['theme'] : 'light';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($theme); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facility Calendar - <?php echo htmlspecialchars($portal_name); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8f9fa;
        }
        
        /* Header matching reference design */
        .header {
            background: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
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
        }
        
        .btn-back:hover {
            background: #f9fafb;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 24px 16px;
        }
        
        /* Calendar controls matching reference design */
        .calendar-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .calendar-controls select {
            padding: 10px 16px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            background: white;
            cursor: pointer;
            min-width: 200px;
        }
        
        .calendar-controls select:focus {
            outline: none;
            border-color: #1a1a1a;
            box-shadow: 0 0 0 3px rgba(0,0,0,0.05);
        }
        
        .view-toggle {
            display: flex;
            gap: 8px;
        }
        
        .view-toggle button {
            padding: 8px 16px;
            border: 1px solid #e5e7eb;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            font-size: 14px;
        }
        
        .view-toggle button.active {
            background: #1a1a1a;
            color: white;
            border-color: #1a1a1a;
        }
        
        .calendar-layout {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
        }
        
        /* Calendar grid matching reference design */
        .calendar-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .calendar-header h2 {
            font-size: 18px;
            color: #1a1a1a;
        }
        
        .calendar-nav {
            display: flex;
            gap: 8px;
        }
        
        .calendar-nav button {
            width: 32px;
            height: 32px;
            border: 1px solid #e5e7eb;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .calendar-nav button:hover {
            background: #f9fafb;
        }
        
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 6px;
        }
        
        .calendar-day-header {
            text-align: center;
            font-size: 12px;
            font-weight: 600;
            color: #6b7280;
            padding: 8px 4px;
        }
        
        .calendar-day {
            aspect-ratio: 1;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
            padding: 4px;
        }
        
        .calendar-day:hover {
            border-color: #1a1a1a;
            background: #f9fafb;
        }
        
        .calendar-day.empty {
            background: #f9fafb;
            cursor: default;
        }
        
        .calendar-day.empty:hover {
            border-color: #e5e7eb;
            background: #f9fafb;
        }
        
        .calendar-day.closed {
            background: #f3f4f6;
            color: #9ca3af;
            cursor: not-allowed;
            pointer-events: none;
        }
        
        .calendar-day.closed:hover {
            border-color: #e5e7eb;
            background: #f3f4f6;
            transform: none;
        }
        
        .calendar-day.holiday {
            background: #fee2e2;
            color: #991b1b;
            cursor: not-allowed;
            pointer-events: none;
            border-color: #fecaca;
        }
        
        .calendar-day.holiday:hover {
            background: #fee2e2;
            border-color: #fecaca;
            transform: none;
        }
        
        .calendar-day.available {
            background: #d1fae5;
            border-color: #a7f3d0;
        }
        
        .calendar-day.occupied {
            background: #fee2e2;
            border-color: #fecaca;
        }
        
        .calendar-day.selected {
            border-color: #1a1a1a;
            border-width: 2px;
        }
        
        .calendar-day-number {
            font-size: 14px;
            font-weight: 600;
            color: #1a1a1a;
        }
        
        .calendar-day.closed .calendar-day-number {
            color: #9ca3af;
        }
        
        .calendar-day-bookings {
            font-size: 9px;
            color: #dc2626;
            margin-top: 2px;
            text-align: center;
        }
        
        .calendar-day-label {
            position: absolute;
            bottom: 2px;
            font-size: 8px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .calendar-day.closed .calendar-day-label,
        .calendar-day.holiday .calendar-day-label {
            color: #9ca3af;
        }
        
        /* Sidebar matching reference design */
        .sidebar-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .sidebar-card h3 {
            font-size: 16px;
            color: #1a1a1a;
            margin-bottom: 16px;
        }
        
        .legend {
            margin-bottom: 20px;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
        }
        
        .legend-color {
            width: 16px;
            height: 16px;
            border-radius: 4px;
        }
        
        .legend-color.available {
            background: #d1fae5;
            border: 1px solid #a7f3d0;
        }
        
        .legend-color.occupied {
            background: #fee2e2;
            border: 1px solid #fecaca;
        }
        
        .legend-color.closed {
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
        }
        
        .legend-color.holiday {
            background: #fee2e2;
            border: 1px solid #fecaca;
        }
        
        .legend-item span {
            font-size: 13px;
            color: #6b7280;
        }
        
        .selected-date-info {
            padding: 16px;
            background: #f9fafb;
            border-radius: 8px;
            margin-bottom: 16px;
        }
        
        .selected-date-info h4 {
            font-size: 16px;
            color: #1a1a1a;
            margin-bottom: 12px;
        }
        
        .selected-date-info p {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 16px;
        }
        
        .btn-book {
            width: 100%;
            padding: 12px;
            background: #1a1a1a;
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: block;
            text-align: center;
        }
        
        .btn-book:hover {
            background: #000;
        }
        
        .time-slots {
            margin-top: 16px;
        }
        
        .time-slots h5 {
            font-size: 14px;
            color: #1a1a1a;
            margin-bottom: 12px;
        }
        
        .time-slot-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 6px;
        }
        
        .time-slot {
            padding: 8px;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            text-align: center;
            font-size: 12px;
            color: #1a1a1a;
        }
        
        /* Year view styles */
        .year-view-container {
            display: none;
            opacity: 0;
            transform: scale(0.95);
            transition: all 0.3s ease;
        }
        
        .year-view-container.active {
            display: block;
            opacity: 1;
            transform: scale(1);
        }
        
        .year-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
        }
        
        .mini-month {
            background: white;
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .mini-month-header {
            font-size: 14px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 12px;
            text-align: center;
        }
        
        .mini-calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 3px;
        }
        
        .mini-day-header {
            text-align: center;
            font-size: 10px;
            font-weight: 600;
            color: #6b7280;
            padding: 4px;
        }
        
        .mini-day {
            aspect-ratio: 1;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
        }
        
        .mini-day:hover {
            border-color: #1a1a1a;
            transform: scale(1.1);
        }
        
        .mini-day.empty {
            background: #f9fafb;
            cursor: default;
            border: none;
        }
        
        .mini-day.empty:hover {
            transform: none;
        }
        
        .mini-day.closed {
            background: #f3f4f6;
            color: #9ca3af;
            cursor: not-allowed;
        }
        
        .mini-day.closed:hover {
            border-color: #e5e7eb;
            transform: none;
        }
        
        .mini-day.holiday {
            background: #fee2e2;
            color: #991b1b;
            cursor: not-allowed;
            border-color: #fecaca;
        }
        
        .mini-day.holiday:hover {
            transform: none;
        }
        
        .mini-day.available {
            background: #d1fae5;
            border-color: #a7f3d0;
            color: #065f46;
        }
        
        .mini-day.occupied {
            background: #fee2e2;
            border-color: #fecaca;
            color: #991b1b;
        }
        
        .mini-day-dot {
            position: absolute;
            bottom: 2px;
            width: 3px;
            height: 3px;
            background: #dc2626;
            border-radius: 50%;
        }
        
        .month-view-container {
            opacity: 1;
            transform: scale(1);
            transition: all 0.3s ease;
        }
        
        .month-view-container.hidden {
            display: none;
            opacity: 0;
            transform: scale(0.95);
        }
        
        [data-theme="dark"] {
            --bg-primary: #1a1a1a;
            --bg-secondary: #2d2d2d;
            --text-primary: #ffffff;
            --text-secondary: #9ca3af;
            --border-color: #404040;
            --accent-color: #818cf8;
        }

        /* Mobile Responsive Styles */
        @media (max-width: 768px) {
            .header {
                padding: 12px 16px;
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
                padding: 20px 12px;
            }
            
            .calendar-controls {
                flex-direction: column;
                align-items: stretch;
                gap: 12px;
            }
            
            .calendar-controls select {
                min-width: 100%;
                order: -1;
            }
            
            .view-toggle {
                justify-content: center;
            }
            
            .calendar-card {
                padding: 16px;
            }
            
            .calendar-header {
                flex-direction: column;
                gap: 12px;
                align-items: flex-start;
            }
            
            .calendar-header h2 {
                font-size: 16px;
            }
            
            .calendar-grid {
                gap: 4px;
            }
            
            .calendar-day-header {
                font-size: 11px;
                padding: 6px 2px;
            }
            
            .calendar-day-number {
                font-size: 12px;
            }
            
            .calendar-day-bookings {
                font-size: 8px;
            }
            
            .calendar-day-label {
                font-size: 7px;
            }
            
            .sidebar-card {
                padding: 16px;
            }
            
            .time-slot-grid {
                grid-template-columns: repeat(4, 1fr);
            }
            
            .year-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }
            
            .mini-month {
                padding: 12px;
            }
            
            .mini-month-header {
                font-size: 13px;
            }
            
            .mini-day {
                font-size: 10px;
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
                padding: 16px 8px;
            }
            
            .calendar-card {
                padding: 12px;
                border-radius: 10px;
            }
            
            .calendar-header h2 {
                font-size: 15px;
            }
            
            .calendar-grid {
                gap: 3px;
            }
            
            .calendar-day-header {
                font-size: 10px;
                padding: 4px 1px;
            }
            
            .calendar-day {
                border-radius: 6px;
            }
            
            .calendar-day-number {
                font-size: 11px;
            }
            
            .sidebar-card {
                padding: 12px;
                border-radius: 10px;
            }
            
            .sidebar-card h3 {
                font-size: 15px;
            }
            
            .time-slot-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 4px;
            }
            
            .time-slot {
                padding: 6px;
                font-size: 11px;
            }
            
            .year-grid {
                grid-template-columns: 1fr;
            }
            
            .mini-month {
                padding: 10px;
            }
        }

        /* For very small screens */
        @media (max-width: 360px) {
            .header-brand .brand-text {
                max-width: 120px;
            }
            
            .calendar-day-number {
                font-size: 10px;
            }
            
            .calendar-day-bookings {
                display: none;
            }
            
            .time-slot-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        /* Desktop styles */
        @media (min-width: 769px) {
            .calendar-layout {
                grid-template-columns: 1fr 300px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-brand">
            <img src="<?php echo htmlspecialchars($logo_file); ?>" alt="Logo">
            <div class="brand-text">
                <div class="brand-title"><?php echo htmlspecialchars($portal_name); ?></div>
                <div class="brand-subtitle">Calendar</div>
            </div>
        </div>
        <a href="dashboard.php" class="btn-back">Back</a>
    </header>

    <div class="container">
        <!-- Calendar controls -->
        <div class="calendar-controls">
            <div>
                <select id="facility-filter" onchange="filterByFacility(this.value)">
                    <option value="">All Facilities</option>
                    <?php foreach ($facilities_list as $facility): ?>
                        <option value="<?php echo htmlspecialchars($facility); ?>" <?php echo $facility_filter === $facility ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($facility); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="view-toggle">
                <button class="active" onclick="switchView('month')">Month View</button>
                <button onclick="switchView('year')">Year View</button>
            </div>
        </div>

        <!-- Wrap month view in container for animation -->
        <div class="month-view-container" id="month-view">
            <div class="calendar-layout">
                <!-- Calendar grid -->
                <div class="calendar-card">
                    <div class="calendar-header">
                        <h2>
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: inline-block; vertical-align: middle; margin-right: 8px;">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <?php echo $month_name; ?>
                        </h2>
                        <div class="calendar-nav">
                            <button onclick="navigateMonth(<?php echo $prev_month; ?>, <?php echo $prev_year; ?>)">
                                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                                </svg>
                            </button>
                            <button onclick="navigateMonth(<?php echo $next_month; ?>, <?php echo $next_year; ?>)">
                                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="calendar-grid">
                        <!-- Day headers -->
                        <div class="calendar-day-header">Sun</div>
                        <div class="calendar-day-header">Mon</div>
                        <div class="calendar-day-header">Tue</div>
                        <div class="calendar-day-header">Wed</div>
                        <div class="calendar-day-header">Thu</div>
                        <div class="calendar-day-header">Fri</div>
                        <div class="calendar-day-header">Sat</div>

                        <!-- Empty cells for days before month starts -->
                        <?php for ($i = 0; $i < $day_of_week; $i++): ?>
                            <div class="calendar-day empty"></div>
                        <?php endfor; ?>

                        <!-- Days of the month -->
                        <?php for ($day = 1; $day <= $days_in_month; $day++): 
                            $current_date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                            $day_of_week_num = date('w', strtotime($current_date));
                            $is_sunday = ($day_of_week_num == 0);
                            $is_holiday = isset($holidays[$current_date]);
                            $holiday_name = $is_holiday ? $holidays[$current_date] : '';
                            $booking_count = isset($bookings[$current_date]) ? $bookings[$current_date] : 0;
                            
                            $class = 'calendar-day';
                            if ($is_sunday) {
                                $class .= ' closed';
                            } elseif ($is_holiday) {
                                $class .= ' holiday';
                            } elseif ($booking_count > 0) {
                                $class .= ' occupied';
                            } else {
                                $class .= ' available';
                            }
                            
                            $onclick = ($is_sunday || $is_holiday) ? '' : "onclick=\"selectDate('$current_date', $booking_count, false, '$holiday_name')\""; ?>
                            <div class="<?php echo $class; ?>" <?php echo $onclick; ?> title="<?php echo $holiday_name; ?>">
                                <span class="calendar-day-number"><?php echo $day; ?></span>
                                <?php if ($booking_count > 0 && !$is_sunday && !$is_holiday): ?>
                                    <span class="calendar-day-bookings"><?php echo $booking_count; ?> booking<?php echo $booking_count > 1 ? 's' : ''; ?></span>
                                <?php endif; ?>
                                <?php if ($is_sunday): ?>
                                    <span class="calendar-day-label">Closed</span>
                                <?php elseif ($is_holiday): ?>
                                    <span class="calendar-day-label">Holiday</span>
                                <?php endif; ?>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="sidebar-card">
                    <h3>Legend</h3>
                    <div class="legend">
                        <div class="legend-item">
                            <div class="legend-color available"></div>
                            <span>Available</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color occupied"></div>
                            <span>Occupied</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color closed"></div>
                            <span>Sunday (Closed)</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color holiday"></div>
                            <span>Holiday (Closed)</span>
                        </div>
                    </div>

                    <div id="date-info" style="display: none;">
                        <div class="selected-date-info">
                            <h4 id="selected-date-title">Monday, January 13, 2025</h4>
                            <p id="selected-date-status">No bookings for this date</p>
                            <a href="create_request.php" class="btn-book">Book Facility</a>
                        </div>

                        <div class="time-slots">
                            <h5>Available Time Slots</h5>
                            <div class="time-slot-grid">
                                <div class="time-slot">07:00</div>
                                <div class="time-slot">08:00</div>
                                <div class="time-slot">09:00</div>
                                <div class="time-slot">10:00</div>
                                <div class="time-slot">11:00</div>
                                <div class="time-slot">12:00</div>
                                <div class="time-slot">13:00</div>
                                <div class="time-slot">14:00</div>
                                <div class="time-slot">15:00</div>
                                <div class="time-slot">16:00</div>
                                <div class="time-slot">17:00</div>
                                <div class="time-slot">18:00</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add year view container -->
        <div class="year-view-container" id="year-view">
            <div class="calendar-card">
                <div class="calendar-header">
                    <h2>
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: inline-block; vertical-align: middle; margin-right: 8px;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <?php echo $year; ?>
                    </h2>
                    <div class="calendar-nav">
                        <button onclick="navigateYear(-1)">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </button>
                        <button onclick="navigateYear(1)">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="year-grid">
                    <?php 
                    $month_names = ['January', 'February', 'March', 'April', 'May', 'June', 
                                   'July', 'August', 'September', 'October', 'November', 'December'];
                    
                    for ($m = 1; $m <= 12; $m++): 
                        $first_day_of_month = mktime(0, 0, 0, $m, 1, $year);
                        $days_in_current_month = date('t', $first_day_of_month);
                        $day_of_week_start = date('w', $first_day_of_month);
                    ?>
                        <div class="mini-month">
                            <div class="mini-month-header"><?php echo $month_names[$m - 1]; ?></div>
                            <div class="mini-calendar-grid">
                                <!-- Day headers -->
                                <div class="mini-day-header">S</div>
                                <div class="mini-day-header">M</div>
                                <div class="mini-day-header">T</div>
                                <div class="mini-day-header">W</div>
                                <div class="mini-day-header">T</div>
                                <div class="mini-day-header">F</div>
                                <div class="mini-day-header">S</div>

                                <!-- Empty cells -->
                                <?php for ($i = 0; $i < $day_of_week_start; $i++): ?>
                                    <div class="mini-day empty"></div>
                                <?php endfor; ?>

                                <!-- Days -->
                                <?php for ($d = 1; $d <= $days_in_current_month; $d++): 
                                    $date_str = sprintf('%04d-%02d-%02d', $year, $m, $d);
                                    $day_num = date('w', strtotime($date_str));
                                    $is_sunday = ($day_num == 0);
                                    $is_holiday = isset($holidays[$date_str]);
                                    $has_booking = isset($year_bookings[$date_str]) && $year_bookings[$date_str] > 0;
                                    
                                    $mini_class = 'mini-day';
                                    if ($is_sunday) {
                                        $mini_class .= ' closed';
                                    } elseif ($is_holiday) {
                                        $mini_class .= ' holiday';
                                    } elseif ($has_booking) {
                                        $mini_class .= ' occupied';
                                    } else {
                                        $mini_class .= ' available';
                                    }
                                    
                                    $onclick_attr = ($is_sunday || $is_holiday) ? '' : "onclick=\"jumpToMonth($m, $year)\"";
                                ?>
                                    <div class="<?php echo $mini_class; ?>" <?php echo $onclick_attr; ?>>
                                        <?php echo $d; ?>
                                        <?php if ($has_booking && !$is_sunday && !$is_holiday): ?>
                                            <span class="mini-day-dot"></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentView = 'month';
        
        function switchView(view) {
            const monthView = document.getElementById('month-view');
            const yearView = document.getElementById('year-view');
            const buttons = document.querySelectorAll('.view-toggle button');
            
            if (view === 'year' && currentView === 'month') {
                // Switch to year view
                monthView.classList.add('hidden');
                setTimeout(() => {
                    yearView.classList.add('active');
                }, 50);
                buttons[0].classList.remove('active');
                buttons[1].classList.add('active');
                currentView = 'year';
            } else if (view === 'month' && currentView === 'year') {
                // Switch to month view
                yearView.classList.remove('active');
                setTimeout(() => {
                    monthView.classList.remove('hidden');
                }, 50);
                buttons[1].classList.remove('active');
                buttons[0].classList.add('active');
                currentView = 'month';
            }
        }
        
        function jumpToMonth(month, year) {
            const url = new URL(window.location.href);
            url.searchParams.set('month', month);
            url.searchParams.set('year', year);
            window.location.href = url.toString();
        }
        
        function navigateYear(direction) {
            const currentYear = <?php echo $year; ?>;
            const newYear = currentYear + direction;
            const url = new URL(window.location.href);
            url.searchParams.set('year', newYear);
            window.location.href = url.toString();
        }
        
        function navigateMonth(month, year) {
            const url = new URL(window.location.href);
            url.searchParams.set('month', month);
            url.searchParams.set('year', year);
            window.location.href = url.toString();
        }
        
        function filterByFacility(facility) {
            const url = new URL(window.location.href);
            if (facility) {
                url.searchParams.set('facility', facility);
            } else {
                url.searchParams.delete('facility');
            }
            window.location.href = url.toString();
        }
        
        function selectDate(date, bookingCount, isClosed, holidayName) {
            document.querySelectorAll('.calendar-day').forEach(day => {
                day.classList.remove('selected');
            });
            
            event.currentTarget.classList.add('selected');
            
            const dateInfo = document.getElementById('date-info');
            const dateTitle = document.getElementById('selected-date-title');
            const dateStatus = document.getElementById('selected-date-status');
            
            const dateObj = new Date(date);
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            const formattedDate = dateObj.toLocaleDateString('en-US', options);
            
            dateTitle.textContent = formattedDate;
            
            if (holidayName) {
                dateStatus.textContent = `Holiday: ${holidayName}`;
            } else if (bookingCount > 0) {
                dateStatus.textContent = bookingCount + ' booking' + (bookingCount > 1 ? 's' : '') + ' for this date';
            } else {
                dateStatus.textContent = 'No bookings for this date';
            }
            
            dateInfo.style.display = 'block';
        }
    </script>
</body>
</html>