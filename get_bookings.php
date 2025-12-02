<?php
header('Content-Type: application/json');
session_start();
require_once '../config.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

// Fetch all approved facility request details
$sql = "SELECT frd.facility_name, frd.date_needed, frd.time_needed 
        FROM facility_request_details frd
        JOIN facility_requests fr ON frd.request_id = fr.id
        WHERE fr.status = 'approved'";

$result = $conn->query($sql);
$bookings = [];
while ($row = $result->fetch_assoc()) {
    $bookings[] = $row;
}

echo json_encode($bookings);
$conn->close();