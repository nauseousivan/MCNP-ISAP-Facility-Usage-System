<?php
session_start();
require_once 'theme_loader.php';
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Handle cache clearing
if (isset($_GET['clear_cache'])) {
    $cache_file = 'google_calendar_cache.json';
    if (file_exists($cache_file)) {
        unlink($cache_file);
    }
    header('Location: calendar.php'); // Redirect back to the calendar
    exit();
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Get current month and year
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$facility_filter = isset($_GET['facility']) ? $_GET['facility'] : '';

// Function to parse iCal events with better parsing
function parseICalEvents($ical_url) {
    $events = [];
    
    try {
        $ical_content = file_get_contents($ical_url);
        if ($ical_content === false) {
            error_log("Failed to fetch iCal content from: " . $ical_url);
            return $events;
        }
        
        $event = [];
        $in_event = false;
        
        // Handle multi-line descriptions
        $ical_content = preg_replace('/(DESCRIPTION):((?:[^\r\n]|\r\n\s)+)/', '$1:' . str_replace(["\r\n ", "\r\n"], ' ', '$2'), $ical_content);
        $lines = explode("\n", $ical_content);

        foreach ($lines as $line) { 
            $line = trim($line); 
            
            if ($line === 'BEGIN:VEVENT') {
                $in_event = true;
                $event = [];
                continue;
            }

            if ($line === 'END:VEVENT') {
                $in_event = false;
                if (isset($event['DTSTART']) && isset($event['SUMMARY'])) {
                    // New time parsing logic
                    $is_all_day = strpos($event['DTSTART'], 'T') === false;

                    if (isset($event['DTSTART'])) {
                        $dtstart_obj = new DateTime($event['DTSTART']);
                        $dtstart_obj->setTimezone(new DateTimeZone('Asia/Manila'));
                        $event['START_TIME'] = $is_all_day ? '00:00' : $dtstart_obj->format('H:i');
                    }
                    
                    if (isset($event['DTEND'])) {
                        $dtend_obj = new DateTime($event['DTEND']);
                        $dtend_obj->setTimezone(new DateTimeZone('Asia/Manila'));
                        $event['END_TIME'] = $is_all_day ? '23:59' : $dtend_obj->format('H:i');
                    }
                    
                    if (isset($event['START_TIME']) && isset($event['END_TIME'])) {
                        $start_formatted = date("g:i A", strtotime($event['START_TIME']));
                        $end_formatted = date("g:i A", strtotime($event['END_TIME']));
                        $event['TIME_DISPLAY'] = $is_all_day 
                            ? 'All day' 
                            : $start_formatted . ' to ' . $end_formatted;
                    } else {
                        $event['TIME_DISPLAY'] = 'All day';
                    }
                    
                    $events[] = $event;
                }
                continue;
            }
            
            if ($in_event) {
                if (strpos($line, 'DTSTART:') === 0) {
                    $event['DTSTART'] = substr($line, 8);
                } else if (strpos($line, 'DTEND:') === 0) {
                    $event['DTEND'] = substr($line, 6);
                } else if (strpos($line, 'SUMMARY:') === 0) {
                    $event['SUMMARY'] = substr($line, 8);
                } elseif (strpos($line, 'DESCRIPTION:') === 0) {
                    $event['DESCRIPTION'] = substr($line, 12);
                } elseif (strpos($line, 'LOCATION:') === 0) {
                    $event['LOCATION'] = substr($line, 9);
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching or parsing iCal: " . $e->getMessage());
    }
    
    return $events;
}

// Function to get Google Calendar events with caching
function getGoogleCalendarEvents() {
    $cache_file = 'google_calendar_cache.json';
    $cache_time = 2 * 60; // 2 mins
    $google_calendar_url = 'https://calendar.google.com/calendar/ical/cd477b19defcb0f4ea254186310b038c9fca36d0b07362babd5521b22d9a29b9%40group.calendar.google.com/public/basic.ics';
    
    // Check if cache exists and is fresh
    if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_time) {
        return json_decode(file_get_contents($cache_file), true);
    }
    
    // Fetch fresh data
    $events = parseICalEvents($google_calendar_url);
    
    // Save to cache
    file_put_contents($cache_file, json_encode($events));
    
    return $events;
}

// Get Google Calendar events
$google_events = getGoogleCalendarEvents();

// Convert Google Calendar events to the same format as facility bookings
$google_calendar_bookings = [];
foreach ($google_events as $event) {
    if (isset($event['DTSTART'])) {
        $date = substr($event['DTSTART'], 0, 8); // Get YYYYMMDD part
        $formatted_date = substr($date, 0, 4) . '-' . substr($date, 4, 2) . '-' . substr($date, 6, 2);
        
        if (!isset($google_calendar_bookings[$formatted_date])) {
            $google_calendar_bookings[$formatted_date] = [];
        }
        $google_calendar_bookings[$formatted_date][] = [
            'type' => 'google_event',
            'summary' => $event['SUMMARY'] ?? 'Google Calendar Event',
            'description' => $event['DESCRIPTION'] ?? '',
            'location' => $event['LOCATION'] ?? '',
            'time_display' => $event['TIME_DISPLAY'] ?? 'All day',
            'start_time' => $event['START_TIME'] ?? '00:00',
            'end_time' => $event['END_TIME'] ?? '23:59'
        ];
    }
}

// Get detailed facility bookings with time information
$sql_year = "SELECT frd.date_needed, frd.facility_name, frd.time_needed, frd.total_hours, frd.total_participants, frd.remarks, fr.event_type
        FROM facility_request_details frd 
        JOIN facility_requests fr ON frd.request_id = fr.id 
        WHERE fr.status = 'approved' 
        AND YEAR(frd.date_needed) = ?";

if ($facility_filter) {
    $sql_year .= " AND frd.facility_name = ?";
}

$sql_year .= " ORDER BY frd.date_needed, frd.facility_name";

$stmt_year = $conn->prepare($sql_year);
if ($facility_filter) {
    $stmt_year->bind_param("is", $year, $facility_filter);
} else {
    $stmt_year->bind_param("i", $year);
}
$stmt_year->execute();
$year_bookings_result = $stmt_year->get_result();

$year_bookings = [];
$year_bookings_details = [];
while ($row = $year_bookings_result->fetch_assoc()) {
    $date = $row['date_needed'];
    
    if (!isset($year_bookings[$date])) {
        $year_bookings[$date] = 0;
    }
    $year_bookings[$date]++;
    
    if (!isset($year_bookings_details[$date])) {
        $year_bookings_details[$date] = [];
    }
    
    $year_bookings_details[$date][] = [
        'type' => 'facility_booking',
        'facility_name' => $row['facility_name'],
        'time_needed' => $row['time_needed'],
        'total_hours' => $row['total_hours'],
        'total_participants' => $row['total_participants'],
        'remarks' => $row['remarks'],
        'event_type' => $row['event_type']
    ];
}

// Get detailed bookings for current month
$sql = "SELECT frd.date_needed, frd.facility_name, frd.time_needed, frd.total_hours, frd.total_participants, frd.remarks, fr.event_type
        FROM facility_request_details frd 
        JOIN facility_requests fr ON frd.request_id = fr.id 
        WHERE fr.status = 'approved' 
        AND MONTH(frd.date_needed) = ? 
        AND YEAR(frd.date_needed) = ?";

if ($facility_filter) {
    $sql .= " AND frd.facility_name = ?";
}

$sql .= " ORDER BY frd.date_needed, frd.facility_name";

$stmt = $conn->prepare($sql);
if ($facility_filter) {
    $stmt->bind_param("iis", $month, $year, $facility_filter);
} else {
    $stmt->bind_param("ii", $month, $year);
}
$stmt->execute();
$bookings_result = $stmt->get_result();

$bookings = [];
$bookings_details = [];
while ($row = $bookings_result->fetch_assoc()) {
    $date = $row['date_needed'];
    
    if (!isset($bookings[$date])) {
        $bookings[$date] = 0;
    }
    $bookings[$date]++;
    
    if (!isset($bookings_details[$date])) {
        $bookings_details[$date] = [];
    }
    
    $bookings_details[$date][] = [
        'type' => 'facility_booking',
        'facility_name' => $row['facility_name'],
        'time_needed' => $row['time_needed'],
        'total_hours' => $row['total_hours'],
        'total_participants' => $row['total_participants'],
        'remarks' => $row['remarks'],
        'event_type' => $row['event_type']
    ];
}

// Add Google Calendar events
foreach ($google_calendar_bookings as $date => $details) {
    if (!isset($combined_bookings[$date])) {
        $combined_bookings[$date] = 0;
    }
    $combined_bookings[$date] += count($details);
    
    if (!isset($combined_bookings_details[$date])) {
        $combined_bookings_details[$date] = [];
    }
    $combined_bookings_details[$date] = array_merge($combined_bookings_details[$date], $details);
}

// Combine facility bookings with Google Calendar events
$combined_bookings = [];
$combined_bookings_details = [];

// Add facility bookings
foreach ($bookings_details as $date => $details) {
    if (!isset($combined_bookings[$date])) {
        $combined_bookings[$date] = 0;
    }
    $combined_bookings[$date] += count($details);
    
    if (!isset($combined_bookings_details[$date])) {
        $combined_bookings_details[$date] = [];
    }
    $combined_bookings_details[$date] = array_merge($combined_bookings_details[$date], $details);
}

// Add Google Calendar events
foreach ($google_calendar_bookings as $date => $details) {
    if (!isset($combined_bookings[$date])) {
        $combined_bookings[$date] = 0;
    }
    $combined_bookings[$date] += count($details);
    
    if (!isset($combined_bookings_details[$date])) {
        $combined_bookings_details[$date] = [];
    }
    $combined_bookings_details[$date] = array_merge($combined_bookings_details[$date], $details);
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

function getGoogleHolidays($year) {
    $cache_file = 'google_holidays_cache_' . $year . '.json';
    $cache_time = 24 * 60 * 60; // 24 hours
    $holiday_calendar_url = 'https://calendar.google.com/calendar/ical/en.philippines%23holiday%40group.v.calendar.google.com/public/basic.ics';

    if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_time) {
        return json_decode(file_get_contents($cache_file), true);
    }

    $holidays = [];
    $ical_content = @file_get_contents($holiday_calendar_url);

    if ($ical_content) {
        $lines = explode("\n", $ical_content);
        $in_event = false;
        $current_summary = '';
        foreach ($lines as $line) {
            if (strpos($line, 'BEGIN:VEVENT') === 0) {
                $in_event = true;
            } elseif (strpos($line, 'END:VEVENT') === 0) {
                $in_event = false;
            } elseif ($in_event) {
                if (strpos($line, 'DTSTART;VALUE=DATE:') === 0) {
                    $date_str = substr($line, 19, 8);
                    $date = DateTime::createFromFormat('Ymd', $date_str)->format('Y-m-d');
                    if (substr($date, 0, 4) == $year) {
                        $holidays[$date] = $current_summary;
                    }
                } elseif (strpos($line, 'SUMMARY:') === 0) {
                    $current_summary = trim(substr($line, 8));
                }
            }
        }
    }
    file_put_contents($cache_file, json_encode($holidays));
    return $holidays;
}

$holidays = getGoogleHolidays($year);

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
$current_view = $_GET['view'] ?? 'month';
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
        
        :root {
            --bg-primary: #ffffff; /* Card and Header background */
            --bg-secondary: #fdfaf6; /* Main page background */
            --text-primary: #1a1a1a;
            --text-secondary: #71717a;
            --border-color: #e5e7eb;
            --accent-color: #6366f1;
        }

        [data-theme="dark"] {
            --bg-primary: #171717; /* Card and Header background */
            --bg-secondary: #0a0a0a; /* Main page background */
            --text-primary: #ffffff;
            --text-secondary: #9ca3af;
            --border-color: #404040;
            --accent-color: #818cf8;
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
            transition: background-color 0.3s, color 0.3s;
        }
        
        /* Header matching reference design */
        .header {
            background: var(--bg-primary);
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
            color: var(--text-primary);
        }
        
        .header-brand .brand-subtitle {
            font-size: 12px;
            color: var(--text-secondary);
        }
        
        .btn-back {
            padding: 8px 16px;
            background: var(--bg-primary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
        }
        
        .btn-back:hover {
            background: var(--bg-secondary);
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
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            background: var(--bg-primary);
            cursor: pointer;
            min-width: 200px;
        }
        
        .calendar-controls select:focus {
            outline: none;
            border-color: var(--text-primary);
            box-shadow: 0 0 0 3px rgba(0,0,0,0.05);
        }
        
        .view-toggle {
            display: flex;
            gap: 8px;
        }
        
        .view-toggle button {
            padding: 8px 16px;
            border: 1px solid var(--border-color);
            background: var(--bg-primary);
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            font-size: 14px;
        }
        
        .view-toggle button.active {
            background: var(--text-primary);
            color: var(--bg-primary);
            border-color: var(--text-primary);
        }
        
        .calendar-layout {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
        }
        
        /* Calendar grid matching reference design */
        .calendar-card {
            background: var(--bg-primary);
            border-radius: 12px;
            padding: 20px;
            border: 1px solid var(--border-color);
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
            color: var(--text-primary);
        }
        
        .calendar-nav {
            display: flex;
            gap: 8px;
        }
        
        .calendar-nav button {
            width: 32px;
            height: 32px;
            border: 1px solid var(--border-color);
            background: var(--bg-primary);
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .calendar-nav button:hover {
            background: var(--bg-secondary);
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
            color: var(--text-secondary);
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
            border-color: var(--text-primary);
            background: var(--bg-secondary);
        }
        
        .calendar-day.empty {
            background: var(--bg-secondary);
            cursor: default;
        }
        
        .calendar-day.empty:hover {
            border-color: var(--border-color);
            background: var(--bg-secondary);
        }
        
        .calendar-day.closed {
            background: #f3f4f6;
            color: #9ca3af;
            cursor: not-allowed;
            pointer-events: none;
        }
        
        .calendar-day.closed:hover {
            border-color: var(--border-color);
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
            background: #dbeafe;
            border-color: #93c5fd;
        }
        
        .calendar-day.special {
            background: #fef3c7;
            border-color: #fcd34d;
        }
        
        .calendar-day.selected {
            border-color: var(--text-primary);
            border-width: 2px;
        }
        
        .calendar-day-number {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
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
            background: var(--bg-primary);
            border-radius: 12px;
            padding: 20px;
            border: 1px solid var(--border-color);
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .sidebar-card h3 {
            font-size: 16px;
            color: var(--text-primary);
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
            background: #dbeafe;
            border: 1px solid #93c5fd;
        }
        
        .legend-color.special {
            background: #fef3c7;
            border: 1px solid #fcd34d;
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
            color: var(--text-secondary);
        }
        
        .selected-date-info {
            padding: 16px;
            background: var(--bg-secondary);
            border-radius: 8px;
            margin-bottom: 16px;
        }
        
        .selected-date-info h4 {
            font-size: 16px;
            color: var(--text-primary);
            margin-bottom: 12px;
        }
        
        .selected-date-info p {
            font-size: 13px;
            color: var(--text-secondary);
            margin-bottom: 16px;
        }
        
        .btn-book {
            width: 100%;
            padding: 14px;
            background: var(--text-primary);
            color: var(--bg-primary);
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            text-decoration: none;
            display: block;
            text-align: center;
            transition: all 0.2s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .btn-book:hover {
            opacity: 0.9;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }
        
        .time-slots {
            margin-top: 16px;
        }
        
        .time-slots h5 {
            font-size: 14px;
            color: var(--text-primary);
            margin-bottom: 12px;
        }
        
        .time-slot-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 6px;
        }
        
        .time-slot {
            padding: 8px;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            text-align: center;
            font-size: 12px;
            color: var(--text-primary);
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .time-slot:hover {
            background: var(--bg-secondary);
            border-color: var(--text-primary);
        }
        
        .time-slot.occupied {
            background: #f3f4f6;
            border-color: #d1d5db;
            color: #9ca3af;
            cursor: not-allowed;
            text-decoration: line-through;
        }
        
        .time-slot.occupied:hover {
            background: #f3f4f6;
            border-color: #d1d5db;
            transform: none;
        }
        
        .booking-details {
            margin-top: 16px;
            max-height: 200px;
            overflow-y: auto;
        }
        
        .booking-item {
            padding: 12px;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            margin-bottom: 8px;
        }
        
        .booking-item.facility {
            border-left: 4px solid #3b82f6;
        }
        
        .booking-item.special {
            border-left: 4px solid #f59e0b;
        }
        
        .booking-title {
            font-weight: 600;
            font-size: 13px;
            margin-bottom: 4px;
        }
        
        .booking-meta {
            font-size: 11px;
            color: var(--text-secondary);
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
            background: var(--bg-primary);
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .mini-month-header {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
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
            color: var(--text-secondary);
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
            border-color: var(--text-primary);
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
            background: #dbeafe;
            border-color: #93c5fd;
            color: #1e40af;
        }
        
        .mini-day.special {
            background: #fef3c7;
            border-color: #fcd34d;
            color: #92400e;
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
        
        .loader-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            transition: opacity 0.3s;
        }

        [data-theme="dark"] .loader-overlay {
            background: rgba(0, 0, 0, 0.7);
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid var(--border-color);
            border-top-color: var(--text-primary);
            border-radius: 50%;
            animation: spin 1.5s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
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
        <a href="dashboard.php" style="text-decoration: none; color: inherit;">
    <div class="header-brand">
        <img src="<?php echo htmlspecialchars($logo_file); ?>" alt="Logo">
        <div class="brand-text">
            <div class="brand-title"><?php echo htmlspecialchars($portal_name); ?></div>
            <div class="brand-subtitle">Calendar</div>
        </div>
    </div>
</a>
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
        <div class="month-view-container <?php echo $current_view === 'year' ? 'hidden' : ''; ?>" id="month-view">
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

                        <!-- Empty days for first week -->
                        <?php for ($i = 0; $i < $day_of_week; $i++): ?>
                            <div class="calendar-day empty"></div>
                        <?php endfor; ?>

                        <!-- Calendar days -->
                        <?php for ($day = 1; $day <= $days_in_month; $day++): ?>
                            <?php
                            $current_date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                            $day_of_week_num = date('w', strtotime($current_date));
                            $is_sunday = ($day_of_week_num == 0);
                            $is_saturday = ($day_of_week_num == 6);
                            $is_past = strtotime($current_date) < strtotime(date('Y-m-d'));
                            $is_holiday = isset($holidays[$current_date]);
                            
                            $day_class = 'calendar-day';
                            $day_label = '';
                            
                            if ($is_holiday) {
                                $day_class .= ' holiday';
                                $day_label = 'Holiday';
                            } elseif ($is_past) {
                                $day_class .= ' closed';
                                $day_label = 'Closed';
                            } elseif ($is_sunday) {
                                $day_class .= ' closed';
                                $day_label = 'Closed';
                            } else {
                                $day_class .= ' available';
                                $day_label = 'Available';
                            }
                            
                            // Check for bookings
                            if (isset($combined_bookings[$current_date])) {
                                $has_facility_booking = false;
                                $has_google_event = false;
                                
                                if (isset($combined_bookings_details[$current_date])) {
                                    foreach ($combined_bookings_details[$current_date] as $booking) {
                                        if ($booking['type'] === 'facility_booking') {
                                            $has_facility_booking = true;
                                        } elseif ($booking['type'] === 'google_event') {
                                            $has_google_event = true;
                                        }
                                    }
                                }
                                
                                if ($has_google_event) {
                                    $day_class .= ' special';
                                    $day_label = 'Special';
                                } elseif ($has_facility_booking) {
                                    $day_class .= ' occupied';
                                    $day_label = 'Occupied';
                                }
                            }
                            
                            $is_selected = isset($_GET['date']) && $_GET['date'] == $current_date;
                            if ($is_selected) {
                                $day_class .= ' selected';
                            }
                            ?>
                            
                            <div class="<?php echo $day_class; ?>" 
                                 onclick="selectDate('<?php echo $current_date; ?>')"
                                 data-date="<?php echo $current_date; ?>">
                                <div class="calendar-day-number"><?php echo $day; ?></div>
                                <?php if (isset($combined_bookings[$current_date]) && $combined_bookings[$current_date] > 0): ?>
                                    <div class="calendar-day-bookings">
                                        <?php echo $combined_bookings[$current_date]; ?> <?php echo $combined_bookings[$current_date] == 1 ? 'booking' : 'bookings'; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="calendar-day-label"><?php echo $day_label; ?></div>
                            </div>
                        <?php endfor; ?>

                        <!-- Empty days for last week -->
                        <?php
                        $total_cells = $day_of_week + $days_in_month;
                        $remaining_cells = 42 - $total_cells; // 6 rows * 7 days
                        if ($remaining_cells > 0) {
                            for ($i = 0; $i < $remaining_cells; $i++) {
                                echo '<div class="calendar-day empty"></div>';
                            }
                        }
                        ?>
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
                            <span>Occupied (Booking)</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color special"></div>
                            <span>Google Event</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color closed"></div>
                            <span>Closed/Weekend</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color holiday"></div>
                            <span>Holiday</span>
                        </div>
                    </div>

                    <?php if (isset($_GET['date'])): ?>
                        <?php
                        $selected_date = $_GET['date'];
                        $day_of_week_selected = date('w', strtotime($selected_date));
                        $is_sunday_selected = ($day_of_week_selected == 0);
                        $is_saturday_selected = ($day_of_week_selected == 6);
                        $is_past_selected = strtotime($selected_date) < strtotime(date('Y-m-d'));
                        $is_holiday_selected = isset($holidays[$selected_date]);
                        $has_bookings = isset($combined_bookings_details[$selected_date]);
                        ?>
                        
                        <div class="selected-date-info">
                            <h4><?php echo date('F j, Y', strtotime($selected_date)); ?></h4>
                            <p>
                                <?php if ($is_holiday_selected): ?>
                                    Holiday: <?php echo $holidays[$selected_date]; ?>
                                <?php elseif ($is_past_selected): ?>
                                    This date has passed and is no longer available for booking.
                                <?php elseif ($is_sunday_selected): ?>
                                    Facility is closed on Sundays.
                                <?php elseif ($has_bookings): ?>
                                    <?php
                                    $booking_count = count($combined_bookings_details[$selected_date]);
                                    echo $booking_count . ' ' . ($booking_count == 1 ? 'booking' : 'bookings') . ' scheduled';
                                    ?>
                                <?php else: ?>
                                    Available for booking
                                <?php endif; ?>
                            </p>
                            
                            <?php if (!$is_past_selected && !$is_sunday_selected && !$is_holiday_selected): ?>
                                <div class="time-slots">
                                    <h5>Available Time Slots:</h5>
                                    <div class="time-slot-grid" id="time-slots">
                                        <!-- Time slots will be populated by JavaScript -->
                                    </div>
                                    <p class="booking-details-note">
                                        For specific occupied time ranges, please refer to the "Not Available"
                                        section below.
                                    </p>
                                </div>
    
                                <a href="create_request.php?date=<?php echo $selected_date; ?>" class="btn-book">Book a Facility for this Date</a>
    
                            <?php endif; ?>
                        </div>

                        <?php if ($has_bookings): ?>
                            <div class="booking-details">
                                <h5>Not Available:</h5>
                                <?php foreach ($combined_bookings_details[$selected_date] as $booking): ?>
                                    <div class="booking-item <?php echo $booking['type'] === 'google_event' ? 'special' : 'facility'; ?>">
                                        <div class="booking-title">
                                            <?php if ($booking['type'] === 'facility_booking'): ?>
                                                <?php echo htmlspecialchars($booking['facility_name']); ?>
                                            <?php else: ?>
                                                <?php echo htmlspecialchars($booking['summary']); ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="booking-meta">
                                            <?php if ($booking['type'] === 'facility_booking'): ?>
                                                <?php
                                                    // Format time to AM/PM
                                                    $time_parts = explode(' to ', $booking['time_needed']);
                                                    $start_time_formatted = date("g:i A", strtotime($time_parts[0]));
                                                    $end_time_formatted = date("g:i A", strtotime($time_parts[1]));
                                                ?>
                                                Time: <?php echo $start_time_formatted . ' to ' . $end_time_formatted; ?>
                                            <?php else: ?>
                                                Time: <?php echo htmlspecialchars($booking['time_display']); ?>
                                                <?php if ($booking['location']): ?>
                                                    <br>Location: <?php echo htmlspecialchars($booking['location']); ?>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="selected-date-info">
                            <h4>Select a Date</h4>
                            <p>Click on any available date to view details and available time slots.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Year view container -->
        <div class="year-view-container <?php echo $current_view === 'year' ? 'active' : ''; ?>" id="year-view">
            <div class="calendar-card">
                <div class="calendar-header">
                    <h2><?php echo $year; ?></h2>
                    <div class="calendar-nav">
                        <button onclick="navigateYear(<?php echo $year - 1; ?>)">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </button>
                        <button onclick="navigateYear(<?php echo date('Y'); ?>)" style="padding: 0 12px; font-weight: 500;">
                            Today
                        </button>
                        <button onclick="navigateYear(<?php echo $year + 1; ?>)">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="year-grid">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <?php
                        $month_first_day = mktime(0, 0, 0, $m, 1, $year);
                        $month_days = date('t', $month_first_day);
                        $month_start_day = date('w', $month_first_day);
                        $month_name_short = date('F', $month_first_day);
                        ?>
                        <div class="mini-month">
                            <div class="mini-month-header"><?php echo $month_name_short; ?></div>
                            <div class="mini-calendar-grid">
                                <!-- Mini day headers -->
                                <div class="mini-day-header">S</div>
                                <div class="mini-day-header">M</div>
                                <div class="mini-day-header">T</div>
                                <div class="mini-day-header">W</div>
                                <div class="mini-day-header">T</div>
                                <div class="mini-day-header">F</div>
                                <div class="mini-day-header">S</div>

                                <!-- Empty days -->
                                <?php for ($i = 0; $i < $month_start_day; $i++): ?>
                                    <div class="mini-day empty"></div>
                                <?php endfor; ?>

                                <!-- Month days -->
                                <?php for ($d = 1; $d <= $month_days; $d++): ?>
                                    <?php
                                    $current_date = sprintf('%04d-%02d-%02d', $year, $m, $d);
                                    $day_of_week_num = date('w', strtotime($current_date));
                                    $is_sunday = ($day_of_week_num == 0);
                                    $is_saturday = ($day_of_week_num == 6);
                                    $is_past = strtotime($current_date) < strtotime(date('Y-m-d'));
                                    $is_holiday = isset($holidays[$current_date]);
                                    
                                    $mini_day_class = 'mini-day';
                                    
                                    if ($is_holiday) {
                                        $mini_day_class .= ' holiday';
                                    } elseif ($is_past) {
                                        $mini_day_class .= ' closed';
                                    } elseif ($is_sunday) {
                                        $mini_day_class .= ' closed';
                                    } else {
                                        $mini_day_class .= ' available';
                                    }
                                    
                                    // Check for bookings
                                    if (isset($year_bookings[$current_date])) {
                                        $mini_day_class .= ' occupied';
                                    }
                                    
                                    // Check for Google Calendar events
                                    if (isset($google_calendar_bookings[$current_date])) {
                                        $mini_day_class .= ' special';
                                    }
                                    ?>
                                    
                                    <div class="<?php echo $mini_day_class; ?>" 
                                         onclick="selectDateFromYear('<?php echo $current_date; ?>')"
                                         title="<?php echo date('M j, Y', strtotime($current_date)); ?>">
                                        <?php echo $d; ?>
                                        <?php if (isset($year_bookings[$current_date]) || isset($google_calendar_bookings[$current_date])): ?>
                                            <div class="mini-day-dot"></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endfor; ?>

                                <!-- Remaining empty days -->
                                <?php
                                $total_mini_cells = $month_start_day + $month_days;
                                $remaining_mini_cells = 42 - $total_mini_cells;
                                if ($remaining_mini_cells > 0) {
                                    for ($i = 0; $i < $remaining_mini_cells; $i++) {
                                        echo '<div class="mini-day empty"></div>';
                                    }
                                }
                                ?>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </div>

    <div id="loader" class="loader-overlay">
        <div class="spinner"></div>
    </div>

    <script>
        function showLoader() {
            document.getElementById('loader').style.display = 'flex';
        }

        function selectDate(date) {
            showLoader();
            const url = new URL(window.location.href);
            url.searchParams.set('date', date);
            window.location.href = url.toString();
        }

        function selectDateFromYear(date) {
            showLoader();
            const url = new URL(window.location.href);
            const dateObj = new Date(date);
            url.searchParams.set('month', dateObj.getMonth() + 1);
            url.searchParams.set('year', dateObj.getFullYear());
            url.searchParams.set('date', date);
            window.location.href = url.toString();
        }

        function navigateMonth(month, year) {
            showLoader();
            const url = new URL(window.location.href);
            url.searchParams.set('view', 'month');
            url.searchParams.set('month', month);
            url.searchParams.set('year', year);
            if (url.searchParams.has('date')) {
                url.searchParams.delete('date');
            }
            window.location.href = url.toString();
        }

        function navigateYear(year) {
            showLoader();
            const url = new URL(window.location.href);
            url.searchParams.set('view', 'year');
            url.searchParams.set('year', year);
            if (url.searchParams.has('month')) {
                url.searchParams.delete('month');
            }
            if (url.searchParams.has('date')) {
                url.searchParams.delete('date');
            }
            window.location.href = url.toString();
        }

        function filterByFacility(facility) {
            showLoader();
            const url = new URL(window.location.href);
            url.searchParams.set('view', '<?php echo $current_view; ?>');
            if (facility) {
                url.searchParams.set('facility', facility);
            } else {
                url.searchParams.delete('facility');
            }
            window.location.href = url.toString();
        }

        function switchView(view) {
            const monthView = document.getElementById('month-view');
            const yearView = document.getElementById('year-view');
            const buttons = document.querySelectorAll('.view-toggle button');
            const url = new URL(window.location.href);
            
            buttons.forEach(btn => btn.classList.remove('active'));
            
            if (view === 'month') {
                monthView.classList.remove('hidden');
                yearView.classList.remove('active');
                document.querySelector('.view-toggle button[onclick="switchView(\'month\')"]').classList.add('active');
                url.searchParams.set('view', 'month');
            } else {
                monthView.classList.add('hidden');
                yearView.classList.add('active');
                document.querySelector('.view-toggle button[onclick="switchView(\'year\')"]').classList.add('active');
                url.searchParams.set('view', 'year');
            }
            // Update URL without reloading for a smoother experience
            history.pushState({}, '', url);
        }

        // Initialize time slots for selected date
        document.addEventListener('DOMContentLoaded', function() {
            const selectedDate = '<?php echo isset($_GET['date']) ? $_GET['date'] : ''; ?>';
            const currentView = '<?php echo $current_view; ?>';
            switchView(currentView);
            if (selectedDate) {
                updateTimeSlots(selectedDate);
            }
        });

        function updateTimeSlots(date) {
            const timeSlotGrid = document.getElementById('time-slots');
            if (!timeSlotGrid) return;
            
            const timeSlots = [];
            for (let hour = 7; hour <= 21; hour++) { // 7 AM to 9 PM
                for (let minute of ['00', '30']) {
                    if (hour === 21 && minute === '30') continue; // Last slot is 9:00 PM
                    timeSlots.push(`${hour.toString().padStart(2, '0')}:${minute}`);
                }
            }
            
            // Get occupied times for this date
            const occupiedRanges = [];
            <?php if (isset($_GET['date']) && isset($combined_bookings_details[$_GET['date']])): ?>
                <?php foreach ($combined_bookings_details[$_GET['date']] as $booking): ?>
                    <?php if ($booking['type'] === 'facility_booking'):
                        $time_parts = explode(' to ', $booking['time_needed']);
                        $start_time_24hr = date("H:i", strtotime($time_parts[0]));
                        $end_time_24hr = date("H:i", strtotime($time_parts[1]));
                    ?>
                        occupiedRanges.push({start: '<?php echo $start_time_24hr; ?>', end: '<?php echo $end_time_24hr; ?>'});
                    <?php elseif ($booking['type'] === 'google_event'): 
                        // Use the new start_time and end_time fields for Google events
                        $start_time_24hr = $booking['start_time'];
                        $end_time_24hr = $booking['end_time'];
                    ?>
                        occupiedRanges.push({
                            start: '<?php echo $start_time_24hr; ?>', 
                            end: '<?php echo $end_time_24hr; ?>'
                        });
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>

            // Create time slot elements
            timeSlotGrid.innerHTML = '';
            timeSlots.forEach(slotTime => {
                let isOccupied = false;
                for (const range of occupiedRanges) {
                    // A slot is occupied if it starts at or after a booking starts, AND strictly before that booking ends.
                    if (slotTime >= range.start && slotTime < range.end) {
                        isOccupied = true;
                        break;
                    }
                }

                const timeSlot = document.createElement('div');
                timeSlot.className = `time-slot ${isOccupied ? 'occupied' : ''}`;
                
                // Format for display (e.g., 8:00 AM)
                const d = new Date(`1970-01-01T${slotTime}:00`);
                timeSlot.textContent = d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });

                timeSlot.title = isOccupied ? 'This time slot is occupied' : 'Click to book this time slot';
                
                if (!isOccupied) {
                    timeSlot.onclick = function() {
                        window.location.href = `create_request.php?date=${date}&time=${slotTime}`;
                    };
                }
                
                timeSlotGrid.appendChild(timeSlot);
            });
        }
    </script>
</body>
</html>