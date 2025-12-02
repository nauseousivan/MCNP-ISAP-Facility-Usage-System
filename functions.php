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
        'transportation_request_approved' => [
            'title' => 'Transportation Request Approved',
            'message' => 'Your transportation request has been approved. Control No: ' . ($action_data['control_number'] ?? ''),
            'type' => 'transportation_request_approved'
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

/**
 * Checks for booking conflicts for a given facility and time.
 * @return bool True if there is a conflict, false otherwise.
 */
function check_for_booking_conflict($conn, $facility_name, $date, $start_time, $end_time) {
    $sql = "SELECT COUNT(*) as conflict_count 
            FROM facility_request_details frd
            JOIN facility_requests fr ON frd.request_id = fr.id
            WHERE fr.status = 'approved'
            AND frd.facility_name = ?
            AND frd.date_needed = ?
            AND (
                (? < SUBSTRING_INDEX(frd.time_needed, ' to ', -1) AND ? > SUBSTRING_INDEX(frd.time_needed, ' to ', 1))
            )";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        // Handle prepare error
        return true; // Assume conflict to be safe
    }
    
    $stmt->bind_param("ssss", $facility_name, $date, $start_time, $end_time);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $result['conflict_count'] > 0;
}

/**
 * Logs an action performed by an administrator.
 */
function log_admin_action($conn, $admin_id, $admin_name, $action, $target_user_id, $target_user_name, $details) {
    $sql = "INSERT INTO admin_logs (admin_id, admin_name, action, target_user_id, target_user_name, details) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    // The target_user_id can be null for actions not related to a user (e.g., facility management)
    $stmt->bind_param("isssss", $admin_id, $admin_name, $action, $target_user_id, $target_user_name, $details);
    $stmt->execute();
}

function getAvailableVehicles($conn) {
    $sql = "SELECT COUNT(*) as available FROM transportation_vehicles WHERE availability = 'available' AND is_active = TRUE";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['available'];
    }
    return 0;
}
