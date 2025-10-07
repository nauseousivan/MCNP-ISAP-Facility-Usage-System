<?php
/**
 * Theme Loader - Determines logo and portal name based on user's department
 * Include this file at the top of any page that needs dynamic branding
 */

// Default values (for pages without login)
$logo_file = 'combined-logo.png';
$portal_name = 'MCNP-ISAP Facility Usage Portal';
$portal_subtitle = 'Service Portal';

// If user is logged in, determine their department-specific branding
if (isset($_SESSION['user_id'])) {
    // Get user's department from session or database
    if (isset($_SESSION['user_department'])) {
        $user_department = $_SESSION['user_department'];
    } else {
        // Fetch from database if not in session
        require_once 'config.php';
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if (!$conn->connect_error) {
            $user_id = $_SESSION['user_id'];
            $sql = "SELECT department FROM users WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                $user_department = $user['department'];
                $_SESSION['user_department'] = $user_department; // Cache in session
            }
            
            $conn->close();
        }
    }
    
    // Determine logo and portal name based on department
    if (isset($user_department)) {
        $department_lower = strtolower($user_department);
        
        if (strpos($department_lower, 'international') !== false) {
            // International School of Asia and the Pacific
            $logo_file = 'isap-logo2.png';
            $portal_name = 'ISAP Facility Portal';
            $portal_subtitle = 'International School of Asia and the Pacific';
        } elseif (strpos($department_lower, 'medical') !== false) {
            // Medical Colleges of Northern Philippines
            $logo_file = 'medical-logo2.png';
            $portal_name = 'MCNP Facility Portal'; // <-- CHANGE THIS LINE
            $portal_subtitle = 'Medical Colleges of Northern Philippines';
        }
    }
}

// Make variables available globally
$GLOBALS['logo_file'] = $logo_file;
$GLOBALS['portal_name'] = $portal_name;
$GLOBALS['portal_subtitle'] = $portal_subtitle;
?>
