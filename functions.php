<?php
function add_notification($conn, $user_id, $title, $message, $type = 'general') {
    $notif_sql = "INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)";
    $notif_stmt = $conn->prepare($notif_sql);
    $notif_stmt->bind_param("isss", $user_id, $title, $message, $type);
    return $notif_stmt->execute();
}

// New function to add notifications for specific actions
function add_action_notification($conn, $user_id, $action_type, $action_data = []) {
    $notifications = [
        'profile_update' => [
            'title' => 'Profile Updated',
            'message' => 'Your profile information has been updated successfully.',
            'type' => 'profile'
        ],
        'profile_picture_update' => [
            'title' => 'Profile Picture Updated',
            'message' => 'Your profile picture has been changed successfully.',
            'type' => 'profile'
        ],
        'request_submitted' => [
            'title' => 'Facility Request Submitted',
            'message' => 'Your facility request has been submitted. Control Number: ' . ($action_data['control_number'] ?? ''),
            'type' => 'request'
        ],
        'request_approved' => [
            'title' => 'Request Approved',
            'message' => 'Your facility request has been approved. Control Number: ' . ($action_data['control_number'] ?? ''),
            'type' => 'request'
        ],
        'request_rejected' => [
            'title' => 'Request Rejected',
            'message' => 'Your facility request has been rejected. Control Number: ' . ($action_data['control_number'] ?? ''),
            'type' => 'request'
        ],
        'phone_updated' => [
            'title' => 'Phone Number Updated',
            'message' => 'Your phone number has been updated successfully.',
            'type' => 'profile'
        ]
    ];
    
    if (isset($notifications[$action_type])) {
        $notification = $notifications[$action_type];
        return add_notification($conn, $user_id, $notification['title'], $notification['message'], $notification['type']);
    }
    
    return false;
}
?>