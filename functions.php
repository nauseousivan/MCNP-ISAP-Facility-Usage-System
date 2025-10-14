<?php
function add_notification($conn, $user_id, $title, $message, $type = 'general', $request_id = null) {
    $sql = "INSERT INTO notifications (user_id, title, message, request_id, notification_type, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issis", $user_id, $title, $message, $request_id, $type);
    return $stmt->execute();
}

// New function to add notifications for specific actions
function add_action_notification($conn, $user_id, $action_type, $action_data = []) {
    $notifications = [
        'profile_update' => [
            'title' => 'Profile Updated',
            'message' => 'Your profile information has been updated successfully.',
            'type' => 'profile_update'  // Changed to match notification_type
        ],
        'profile_picture_update' => [
            'title' => 'Profile Picture Updated',
            'message' => 'Your profile picture has been changed successfully.',
            'type' => 'profile_picture_update'  // Changed to match notification_type
        ],
        'request_submitted' => [
            'title' => 'Facility Request Submitted',
            'message' => 'Your facility request has been submitted. Control Number: ' . ($action_data['control_number'] ?? ''),
            'type' => 'request_submitted'  // Changed to match notification_type
        ],
        'request_approved' => [
            'title' => 'Request Approved',
            'message' => 'Your facility request has been approved. Control Number: ' . ($action_data['control_number'] ?? ''),
            'type' => 'request_approved'  // Changed to match notification_type
        ],
        'request_rejected' => [
            'title' => 'Request Rejected',
            'message' => 'Your facility request has been rejected. Control Number: ' . ($action_data['control_number'] ?? ''),
            'type' => 'request_rejected'  // Changed to match notification_type
        ],
        'phone_updated' => [
            'title' => 'Phone Number Updated',
            'message' => 'Your phone number has been updated successfully.',
            'type' => 'phone_updated'  // Changed to match notification_type
        ]
    ];
    
    if (isset($notifications[$action_type])) {
        $notification = $notifications[$action_type];
        
        // Use the new notification structure with request_id and notification_type
        $request_id = $action_data['request_id'] ?? null;
        $sql = "INSERT INTO notifications (user_id, title, message, request_id, notification_type, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issis", $user_id, $notification['title'], $notification['message'], $request_id, $notification['type']);
        return $stmt->execute();
    }
    
    return false;
}
?>