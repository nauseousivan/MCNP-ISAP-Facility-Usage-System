<?php
function add_notification($conn, $user_id, $title, $message, $type = 'general') {
    $notif_sql = "INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)";
    $notif_stmt = $conn->prepare($notif_sql);
    $notif_stmt->bind_param("isss", $user_id, $title, $message, $type);
    $notif_stmt->execute();
}
?>