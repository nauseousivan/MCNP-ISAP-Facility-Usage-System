<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$user_id = $_SESSION['user_id'];

// Get action
$action = $_GET['action'] ?? 'get';

if ($action === 'get') {
    // Get all notifications for user
    $sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 20";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        // Add action URL to each notification
        $row['action_url'] = getNotificationAction($row);
        $notifications[] = $row;
    }
    
    // Get unread count
    $count_sql = "SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = 0";
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param("i", $user_id);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $unread_count = $count_result->fetch_assoc()['unread'];
    
    echo json_encode([
        'notifications' => $notifications,
        'unread_count' => $unread_count
    ]);
} elseif ($action === 'mark_read') {
    // Mark notification as read
    $notif_id = $_POST['id'] ?? 0;
    
    $sql = "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $notif_id, $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Failed to mark as read']);
    }
    
} elseif ($action === 'mark_all_read') {
    // Mark all notifications as read
    $sql = "UPDATE notifications SET is_read = 1 WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Failed to mark all as read']);
    }
    
} elseif ($action === 'delete') {
    // Delete notification
    $notif_id = $_POST['id'] ?? 0;
    
    $sql = "DELETE FROM notifications WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $notif_id, $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Failed to delete notification']);
    }
}

$conn->close();
function getNotificationAction($notification) {
    // Priority 1: Direct request_id linking (most reliable)
    if (!empty($notification['request_id'])) {
        return 'view_request.php?id=' . $notification['request_id'];
    }
    
    // Priority 2: Type-based routing
    switch ($notification['notification_type']) {
        case 'request_approved':
        case 'request_rejected':
            return 'my_requests.php';
        case 'profile_update':
        case 'profile_picture_update':
        case 'phone_updated':
            return 'profile.php';  // This should go to profile.php, NOT my_requests.php
        default:
            // Priority 3: Fallback to control number parsing
            preg_match('/#([A-Z0-9]+)/', $notification['message'], $matches);
            if (isset($matches[1])) {
                // Try to find request by control number
                global $conn;
                $sql = "SELECT id FROM facility_requests WHERE control_number = ? AND user_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $matches[1], $notification['user_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $request = $result->fetch_assoc();
                    return 'view_request.php?id=' . $request['id'];
                }
            }
            return 'profile.php';  // Changed default to profile.php instead of my_requests.php
    }
}
?>
