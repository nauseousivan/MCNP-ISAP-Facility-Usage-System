<?php
session_start();
require_once 'config.php';
require_once 'send_email.php';

header('Content-Type: application/json');

if (!isset($_SESSION['verify_email'])) {
    echo json_encode(['success' => false, 'message' => 'Session expired or invalid. Please start over.']);
    exit();
}

$email = $_SESSION['verify_email'];

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit();
}

// Get user's name
$stmt = $conn->prepare("SELECT name FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'User not found.']);
    exit();
}
$user = $result->fetch_assoc();
$full_name = $user['name'];

// Generate a new verification code
$new_code = sprintf("%06d", mt_rand(1, 999999));

// Update the user's verification code in the database
$update_stmt = $conn->prepare("UPDATE users SET verification_code = ? WHERE email = ?");
$update_stmt->bind_param("ss", $new_code, $email);

if ($update_stmt->execute()) {
    // Send the new verification email
    $emailSent = sendVerificationEmail($email, $full_name, $new_code);
    if ($emailSent) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send new verification email.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update verification code.']);
}
?>