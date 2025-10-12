<?php
// generate_report.php
session_start();
require_once '../config.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'Admin') {
    header("HTTP/1.1 403 Forbidden");
    exit();
}

// Get parameters from GET
$format = $_GET['format'] ?? 'excel';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// Validate date format
if (!validateDate($start_date) || !validateDate($end_date)) {
    header("HTTP/1.1 400 Bad Request");
    echo "Invalid date format";
    exit();
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    header("HTTP/1.1 500 Internal Server Error");
    echo "Database connection failed";
    exit();
}

// Generate report data based on format
switch($format) {
    case 'pdf':
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="facility_report_'.$start_date.'_to_'.$end_date.'.pdf"');
        generatePDFReport($conn, $start_date, $end_date);
        break;
        
    case 'csv':
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="facility_report_'.$start_date.'_to_'.$end_date.'.csv"');
        generateCSVReport($conn, $start_date, $end_date);
        break;
        
    case 'excel':
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="facility_report_'.$start_date.'_to_'.$end_date.'.xls"');
        generateExcelReport($conn, $start_date, $end_date);
        break;
        
    default:
        header("HTTP/1.1 400 Bad Request");
        echo "Invalid format specified";
        exit();
}

$conn->close();

function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

function generatePDFReport($conn, $start_date, $end_date) {
    $data = getReportData($conn, $start_date, $end_date);
    
    $output = "FACILITY USAGE REPORT\n";
    $output .= "Period: $start_date to $end_date\n\n";
    $output .= "Date | Facility | Requester | Status | Purpose | Created At\n";
    $output .= "-----------------------------------------------------------------\n";
    
    foreach ($data as $row) {
        $output .= "{$row['date']} | {$row['facility_name']} | {$row['requested_by']} | {$row['status']} | {$row['purpose']} | {$row['created_at']}\n";
    }
    
    echo $output;
}

function generateCSVReport($conn, $start_date, $end_date) {
    $data = getReportData($conn, $start_date, $end_date);
    
    // Output CSV directly
    echo "Date,Facility,Requester,Status,Purpose,Created At\n";
    
    foreach ($data as $row) {
        echo '"' . $row['date'] . '","' . 
             $row['facility_name'] . '","' . 
             $row['requested_by'] . '","' . 
             $row['status'] . '","' . 
             $row['purpose'] . '","' . 
             $row['created_at'] . "\"\n";
    }
}

function generateExcelReport($conn, $start_date, $end_date) {
    $data = getReportData($conn, $start_date, $end_date);
    
    // Excel headers with tab separation
    $output = "Date\tFacility\tRequester\tStatus\tPurpose\tCreated At\n";
    
    foreach ($data as $row) {
        $output .= "{$row['date']}\t{$row['facility_name']}\t{$row['requested_by']}\t{$row['status']}\t{$row['purpose']}\t{$row['created_at']}\n";
    }
    
    echo $output;
}

function getReportData($conn, $start_date, $end_date) {
    $sql = "SELECT 
                fr.id,
                DATE(fr.created_at) as date,
                frd.facility_name,
                u.name as requested_by,
                fr.status,
                fr.purpose,
                fr.created_at
            FROM facility_requests fr
            JOIN facility_request_details frd ON frd.request_id = fr.id
            JOIN users u ON fr.user_id = u.id
            WHERE DATE(fr.created_at) BETWEEN ? AND ?
            ORDER BY fr.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    $stmt->close();
    return $data;
}
?>