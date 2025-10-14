<?php
session_start();
require_once 'config.php';
require_once 'theme_loader.php';
require_once 'functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'Admin') {
    header("Location: admin/index.php");
    exit();
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_type = $_SESSION['user_type'];

// Get user details
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$logo_file = $GLOBALS['logo_file'];
$department_lower = strtolower($user['department']);
if (strpos($department_lower, 'international') !== false) {
    $logo_file = 'isap-logo2.png';
} elseif (strpos($department_lower, 'medical') !== false) {
    $logo_file = 'medical-logo2.png';
}

$profile_picture = !empty($user['profile_picture']) ? $user['profile_picture'] : 'https://ui-avatars.com/api/?name=' . urlencode($user['name']) . '&background=6366f1&color=fff&size=128';

// Get user's recent requests
$sql = "SELECT * FROM facility_requests WHERE user_id = ? ORDER BY created_at DESC LIMIT 3";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_requests = $stmt->get_result();

// Get upcoming approved requests
$sql = "SELECT fr.*, frd.date_needed, frd.time_needed, frd.facility_name 
        FROM facility_requests fr 
        LEFT JOIN facility_request_details frd ON fr.id = frd.request_id 
        WHERE fr.user_id = ? AND fr.status = 'approved' AND frd.date_needed >= CURDATE() 
        ORDER BY frd.date_needed ASC LIMIT 2";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$upcoming_requests = $stmt->get_result();

// Get statistics
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM facility_requests WHERE user_id = ?";
$stmt = $conn->prepare($stats_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Get theme from session or default to 'light'
$theme = isset($_SESSION['theme']) ? $_SESSION['theme'] : 'light';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($theme); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - MCNP Service Portal</title>
<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    
    :root {
        --bg-primary: #ffffff;
        --bg-secondary: #f8f9fa;
        --text-primary: #1a1a1a;
        --text-secondary: #6b7280;
        --border-color: #e5e7eb;
        --accent-color: #6366f1;
        --card-bg: #ffffff;
        --header-bg: #ffffff;
    }

    [data-theme="dark"] {
        --bg-primary: #1a1a1a;
        --bg-secondary: #2d2d2d;
        --text-primary: #ffffff;
        --text-secondary: #9ca3af;
        --border-color: #404040;
        --accent-color: #818cf8;
        --card-bg: #2d2d2d;
        --header-bg: #1a1a1a;
    }
    
    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background: var(--bg-secondary);
        color: var(--text-primary);
        transition: background 0.3s, color 0.3s;
    }
    
    /* New header design matching reference image */
    .header {
        background: var(--header-bg);
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        padding: 16px 32px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: background 0.3s;
    }
    
    .header-logo {
        background: var(--card-bg);
        border-radius: 12px;
        padding: 6px;
        height: 73px;
        width: auto;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        display: block;
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

    .brand-text {
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .brand-title {
        font-size: 16px;
        font-weight: 700;
        color: var(--text-primary);
    }

    .brand-subtitle {
        font-size: 12px;
        color: var(--text-secondary);
    }
    
    .header-actions {
        display: flex;
        align-items: center;
        gap: 16px;
    }
    
    .btn-settings,
    .btn-logout {
        padding: 8px 16px;
        border-radius: 6px;
        text-decoration: none;
        font-weight: 500;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 6px;
        transition: all 0.2s;
    }
    
    .btn-settings {
        background: var(--bg-secondary);
        color: var(--text-primary);
        border: 1px solid var(--border-color);
    }
    
    .btn-logout {
        background: var(--card-bg);
        color: var(--text-primary);
        border: 1px solid var(--border-color);
    }
    
    .btn-settings:hover {
        background: var(--border-color);
    }
    
    .btn-logout:hover {
        background: var(--bg-secondary);
    }
    
    /* Notification button styles */
    .btn-notification {
        position: relative;
        width: 40px;
        height: 40px;
        background: var(--bg-secondary);
        border: none;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .btn-notification:hover {
        background: var(--border-color);
    }
    
    .btn-notification svg {
        width: 20px;
        height: 20px;
        color: var(--text-primary);
    }
    
    .notification-badge {
        position: absolute;
        top: -4px;
        right: -4px;
        background: #ef4444;
        color: white;
        font-size: 11px;
        font-weight: 700;
        padding: 2px 6px;
        border-radius: 10px;
        min-width: 18px;
        text-align: center;
    }
    
    .notification-dropdown {
        display: none;
        position: absolute;
        top: 60px;
        right: 32px;
        width: 380px;
        max-height: 500px;
        background: var(--card-bg);
        border-radius: 12px;
        box-shadow: 0 8px 32px rgba(0,0,0,0.15);
        z-index: 1000;
        overflow: hidden;
        animation: slideDown 0.3s ease;
        border: 1px solid var(--border-color);
    }
    
    .notification-dropdown.show {
        display: block;
    }
    
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .notification-header {
        padding: 16px 20px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .notification-header h3 {
        font-size: 16px;
        color: var(--text-primary);
        font-weight: 700;
    }
    
    .btn-mark-all-read {
        font-size: 13px;
        color: var(--text-secondary);
        background: none;
        border: none;
        cursor: pointer;
        font-weight: 500;
    }
    
    .btn-mark-all-read:hover {
        color: var(--text-primary);
    }
    
    .notification-list {
        max-height: 400px;
        overflow-y: auto;
    }
    
    .notification-item {
        padding: 16px 20px;
        border-bottom: 1px solid var(--border-color);
        cursor: pointer;
        transition: all 0.2s;
        position: relative;
    }
    
    .notification-item:hover {
        background: var(--bg-secondary);
    }
    
    .notification-item.unread {
        background: rgba(59, 130, 246, 0.1);
    }
    
    .notification-item.unread::before {
        content: '';
        position: absolute;
        left: 8px;
        top: 50%;
        transform: translateY(-50%);
        width: 8px;
        height: 8px;
        background: #3b82f6;
        border-radius: 50%;
    }
    
    .notification-item-title {
        font-size: 14px;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 4px;
    }
    
    .notification-item-message {
        font-size: 13px;
        color: var(--text-secondary);
        margin-bottom: 4px;
    }
    
    .notification-item-time {
        font-size: 11px;
        color: var(--text-secondary);
    }
    
    .notification-empty {
        padding: 40px 20px;
        text-align: center;
        color: var(--text-secondary);
    }
    
    .notification-empty svg {
        width: 48px;
        height: 48px;
        margin-bottom: 12px;
        opacity: 0.5;
    }
    
    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 32px;
    }
    
    /* Welcome section matching reference design */
    .welcome-section {
        margin-bottom: 32px;
        display: flex;
        align-items: center;
    }
    
    .welcome-section img {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        margin-right: 24px;
    }
    
    .welcome-section h1 {
        font-size: 32px;
        color: var(--text-primary);
        margin-bottom: 4px;
    }
    
    .welcome-section p {
        color: var(--text-secondary);
        font-size: 15px;
    }
    
    /* Action cards grid matching reference image */
    .action-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
        margin-bottom: 40px;
    }
    
    .action-card {
        background: var(--card-bg);
        border-radius: 12px;
        padding: 32px 24px;
        text-align: center;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        text-decoration: none;
        transition: all 0.2s;
        cursor: pointer;
        border: 1px solid var(--border-color);
    }
    
    .action-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    
    .action-card-icon {
        width: 48px;
        height: 48px;
        margin: 0 auto 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        background: var(--bg-secondary);
    }
    
    .action-card-icon svg {
        width: 24px;
        height: 24px;
        color: var(--text-primary);
    }
    
    .action-card h3 {
        font-size: 16px;
        color: var(--text-primary);
        margin-bottom: 4px;
    }
    
    .action-card p {
        font-size: 13px;
        color: var(--text-secondary);
    }
    
    /* Content sections matching reference design */
    .content-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 24px;
        margin-bottom: 24px;
    }
    
    .section-card {
        background: var(--card-bg);
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        border: 1px solid var(--border-color);
    }
    
    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }
    
    .section-header h2 {
        font-size: 18px;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .section-header h2 svg {
        width: 20px;
        height: 20px;
    }
    
    .section-header p {
        font-size: 13px;
        color: var(--text-secondary);
    }
    
    .view-all-link {
        color: var(--text-primary);
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
    }
    
    .view-all-link:hover {
        text-decoration: underline;
    }
    
    /* Request items matching reference design */
    .request-item {
        padding: 16px;
        border-radius: 8px;
        background: var(--bg-secondary);
        margin-bottom: 12px;
        border: 1px solid var(--border-color);
    }
    
    .request-item:last-child {
        margin-bottom: 0;
    }
    
    .request-item-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 8px;
    }
    
    .request-item h4 {
        font-size: 15px;
        color: var(--text-primary);
        margin-bottom: 4px;
    }
    
    .request-item p {
        font-size: 13px;
        color: var(--text-secondary);
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 8px;
    }
    
    .request-item p svg {
        width: 14px;
        height: 14px;
    }
    
    .status-badge {
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        text-transform: lowercase;
        display: inline-block;
    }
    
    .status-badge.pending {
        background: #fef3c7;
        color: #92400e;
    }
    
    .status-badge.approved {
        background: #d1fae5;
        color: #065f46;
    }
    
    .status-badge.rejected {
        background: #fee2e2;
        color: #991b1b;
    }
    
    .btn-view {
        padding: 6px 12px;
        background: var(--card-bg);
        color: var(--text-primary);
        border: 1px solid var(--border-color);
        border-radius: 6px;
        text-decoration: none;
        font-size: 13px;
        font-weight: 500;
        display: inline-block;
        margin-top: 8px;
        transition: all 0.2s;
    }
    .btn-scan {
    padding: 8px 16px;
    background: var(--btn-bg);
    color: var(--btn-text);
    border: 1px solid var(--btn-border);
    border-radius: 6px;
    text-decoration: none;
    font-weight: 500;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    transition: all 0.2s;
}

 
    /* Stats card matching reference design */
    .stats-card {
        background: var(--card-bg);
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        border: 1px solid var(--border-color);
    }
    
    .stats-card h2 {
        font-size: 18px;
        color: var(--text-primary);
        margin-bottom: 20px;
    }
    
    .stats-list {
        list-style: none;
    }
    
    .stats-list li {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid var(--border-color);
    }
    
    .stats-list li:last-child {
        border-bottom: none;
    }
    
    .stats-list .label {
        color: var(--text-secondary);
        font-size: 14px;
    }
    
    .stats-list .value {
        font-size: 20px;
        font-weight: 700;
        color: var(--text-primary);
    }
    
    .stats-list .value.approved {
        color: #10b981;
    }
    
    .stats-list .value.pending {
        color: #f59e0b;
    }
    
    .stats-list .value.rejected {
        color: #ef4444;
    }
    
    .empty-state {
        text-align: center;
        padding: 32px 16px;
        color: var(--text-secondary);
    }
    
    .empty-state svg {
        width: 48px;
        height: 48px;
        margin-bottom: 12px;
        opacity: 0.5;
    }
    
    /* Added profile button styles */
    .profile-button {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        overflow: hidden;
        cursor: pointer;
        border: 2px solid var(--border-color);
        transition: all 0.2s;
        display: block;
    }
    
    .profile-button:hover {
        border-color: var(--accent-color);
        transform: scale(1.05);
    }
    
    .profile-button img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    /* Mobile Responsive Styles */
    @media (max-width: 1024px) {
        .container {
            padding: 24px;
        }
        
        .content-grid {
            gap: 20px;
        }
    }
    
    @media (max-width: 768px) {
        .header {
            padding: 12px 16px;
        }
        
        .header-brand img {
            height: 32px;
        }
        
        .brand-text {
            display: none;
        }
        
        .header-actions {
            gap: 12px;
        }
        
        .btn-settings, .btn-logout {
            padding: 6px 12px;
            font-size: 13px;
        }
        
        .btn-settings span, .btn-logout span {
            display: none;
        }
        
        .btn-settings svg, .btn-logout svg {
            margin-right: 0;
        }
        
        .container {
            padding: 16px;
        }
        
        .welcome-section {
            flex-direction: column;
            align-items: flex-start;
            margin-bottom: 24px;
        }
        
        .welcome-section img {
            margin-right: 0;
            margin-bottom: 16px;
            width: 56px;
            height: 56px;
        }
        
        .welcome-section h1 {
            font-size: 24px;
        }
        
        .action-cards {
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin-bottom: 32px;
        }
        
        .action-card {
            padding: 24px 16px;
        }
        
        .content-grid {
            grid-template-columns: 1fr;
            gap: 16px;
        }
        
        .section-card {
            padding: 20px;
        }
        
        .section-header {
            margin-bottom: 16px;
        }
        
        .section-header h2 {
            font-size: 16px;
        }
        
        .request-item {
            padding: 12px;
        }
        
        .request-item-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 8px;
        }
        
        .request-item p {
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .stats-card {
            padding: 20px;
        }
        
        .stats-card h2 {
            font-size: 16px;
            margin-bottom: 16px;
        }
        
        .stats-list .value {
            font-size: 18px;
        }
        
        .notification-dropdown {
            right: 16px;
            width: calc(100vw - 32px);
            max-width: 380px;
        }
    }
    
    @media (max-width: 480px) {
        .action-cards {
            grid-template-columns: 1fr;
        }
        
        .action-card {
            padding: 20px 16px;
        }
        
        .action-card-icon {
            width: 40px;
            height: 40px;
        }
        
        .action-card-icon svg {
            width: 20px;
            height: 20px;
        }
        
        .header-actions {
            gap: 8px;
        }
        
        .btn-notification {
            width: 36px;
            height: 36px;
        }
        
        .profile-button {
            width: 36px;
            height: 36px;
        }
        
        .btn-settings, .btn-logout {
            padding: 6px;
        }
        
        .notification-dropdown {
            right: 8px;
            width: calc(100vw - 16px);
        }
    }
    
    /* Additional mobile-specific improvements */
    @media (max-width: 768px) {
        .request-item p {
            font-size: 12px;
        }
        
        .request-item h4 {
            font-size: 14px;
        }
        
        .btn-view {
            font-size: 12px;
            padding: 5px 10px;
        }
        
        .status-badge {
            font-size: 10px;
            padding: 3px 8px;
        }
    }
    
    /* Improve touch targets for mobile */
    @media (max-width: 768px) {
        .action-card, .btn-view, .btn-settings, .btn-logout, .btn-notification {
            min-height: 44px; /* Apple's recommended minimum touch target size */
        }
        
        .request-item {
            padding: 16px; /* More padding for easier tapping */
        }
    }
    
    /* Prevent horizontal scrolling */
    body {
        overflow-x: hidden;
    }
</style>
</head>
<body>
    <header class="header">
        <div class="header-brand">
            <img src="<?php echo htmlspecialchars($logo_file); ?>" alt="Logo">
            <div class="brand-text">
                <span class="brand-title"><?php echo htmlspecialchars($portal_name); ?></span>
                <span class="brand-subtitle"><?php echo htmlspecialchars($portal_subtitle); ?></span>
            </div>
        </div>
        <div class="header-actions">
            <a href="profile.php" class="profile-button" title="View Profile" style="width: 40px; height: 40px; border-radius: 50%; overflow: hidden; border: 2px solid #e5e7eb; transition: all 0.2s; display: block;">
                <img src="<?php echo htmlspecialchars($profile_picture); ?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover;">
            </a>
            <button class="btn-notification" onclick="toggleNotifications()">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 00-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
                <span class="notification-badge" id="notificationBadge" style="display: none;">0</span>
            </button>
            <a href="settings.php" class="btn-settings">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="16" height="16">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <span>Settings</span>
            </a>
            <a href="logout.php" class="btn-logout">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="16" height="16">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
                <span>Logout</span>
            </a>
        </div>
    </header>

    <div class="notification-dropdown" id="notificationDropdown">
        <div class="notification-header">
            <h3>Notifications</h3>
            <button class="btn-mark-all-read" onclick="markAllAsRead()">Mark all as read</button>
        </div>
        <div class="notification-list" id="notificationList">
            <div class="notification-empty">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 00-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
                <p>No notifications</p>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="welcome-section">
            <div>
                <h1>Welcome back, <?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?>!</h1>
                <p><?php echo htmlspecialchars($user['department']); ?> • <?php echo htmlspecialchars($user['program']); ?></p>
            </div>
        </div>

        <div class="action-cards">
            <a href="create_request.php" class="action-card">
                <div class="action-card-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                </div>
                <h3>New Request</h3>
                <p>Book a facility</p>
            </a>
            
            <a href="facilities.php" class="action-card">
                <div class="action-card-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                </div>
                <h3>Facilities</h3>
                <p>Browse available</p>
            </a>
            
            <a href="calendar.php" class="action-card">
                <div class="action-card-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
                <h3>Calendar</h3>
                <p>View schedule</p>
            </a>
            
            <a href="profile.php" class="action-card">
                <div class="action-card-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                </div>
                <h3>Profile</h3>
                <p>Manage account</p>
            </a>
        </div>

        <div class="content-grid">
            <div class="section-card">
                <div class="section-header">
                    <h2>
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Recent Requests
                    </h2>
                    <a href="my_requests.php" class="view-all-link">View All Requests</a>
                </div>
                <p style="font-size: 13px; color: #6b7280; margin-bottom: 16px;">Your latest facility booking requests</p>
                
                <?php if ($recent_requests->num_rows > 0): ?>
                    <?php while ($request = $recent_requests->fetch_assoc()): ?>
                        <div class="request-item">
                            <div class="request-item-header">
                                <div>
                                    <h4><?php echo htmlspecialchars($request['event_type']); ?></h4>
                                    <p><?php echo htmlspecialchars($request['department']); ?></p>
                                </div>
                                <span class="status-badge <?php echo $request['status']; ?>">
                                    <?php echo $request['status']; ?>
                                </span>
                            </div>
                            <p>
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                <?php echo date('Y-m-d', strtotime($request['created_at'])); ?>
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <?php echo date('H:i', strtotime($request['created_at'])); ?>
                            </p>
                            <a href="view_request.php?id=<?php echo $request['id']; ?>" class="btn-view">View</a>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <p>No requests yet</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="section-card">
                <div class="section-header">
                    <h2>
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        Upcoming
                    </h2>
                    <a href="calendar.php" class="view-all-link">View Calendar</a>
                </div>
                <p style="font-size: 13px; color: #6b7280; margin-bottom: 16px;">Your confirmed bookings</p>
                
                <?php if ($upcoming_requests->num_rows > 0): ?>
                    <?php while ($upcoming = $upcoming_requests->fetch_assoc()): ?>
                        <div class="request-item">
                            <div class="request-item-header">
                                <div>
                                    <h4><?php echo htmlspecialchars($upcoming['facility_name']); ?></h4>
                                    <p><?php echo htmlspecialchars($upcoming['event_type']); ?></p>
                                </div>
                            </div>
                            <p>
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                <?php echo date('Y-m-d', strtotime($upcoming['date_needed'])); ?>
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <?php echo htmlspecialchars($upcoming['time_needed']); ?>
                            </p>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <p>No upcoming bookings</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="stats-card">
            <h2>Quick Stats</h2>
            <ul class="stats-list">
                <li>
                    <span class="label">Total Requests</span>
                    <span class="value"><?php echo $stats['total']; ?></span>
                </li>
                <li>
                    <span class="label">Approved</span>
                    <span class="value approved"><?php echo $stats['approved']; ?></span>
                </li>
                <li>
                    <span class="label">Pending</span>
                    <span class="value pending"><?php echo $stats['pending']; ?></span>
                </li>
                <li>
                    <span class="label">Rejected</span>
                    <span class="value rejected"><?php echo $stats['rejected']; ?></span>
                </li>
            </ul>
        </div>
    </div>

    <script>
        let notificationsOpen = false;
        
        // Load notifications on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadNotifications();
            // Refresh notifications every 30 seconds
            setInterval(loadNotifications, 30000);
        });
        
        function toggleNotifications() {
            const dropdown = document.getElementById('notificationDropdown');
            notificationsOpen = !notificationsOpen;
            
            if (notificationsOpen) {
                dropdown.classList.add('show');
                loadNotifications();
            } else {
                dropdown.classList.remove('show');
            }
        }
        
async function loadNotifications() {
    try {
        const response = await fetch('api/notifications.php?action=get');
        const data = await response.json();
        
        const badge = document.getElementById('notificationBadge');
        const list = document.getElementById('notificationList');
        
        // Update badge
        if (data.unread_count > 0) {
            badge.textContent = data.unread_count;
            badge.style.display = 'block';
        } else {
            badge.style.display = 'none';
        }
        
        // Update list
        if (data.notifications.length === 0) {
            list.innerHTML = `
                <div class="notification-empty">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 00-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                    <p>No notifications</p>
                </div>
            `;
        } else {
            list.innerHTML = data.notifications.map(notif => `
                <div class="notification-item ${notif.is_read == 0 ? 'unread' : ''}" 
                     onclick="handleNotificationClick(${notif.id}, '${notif.action_url || 'my_requests.php'}')">
                    <div class="notification-item-title">${notif.title}</div>
                    <div class="notification-item-message">${notif.message}</div>
                    <div class="notification-item-time">${formatTime(notif.created_at)}</div>
                </div>
            `).join('');
        }
    } catch (error) {
        console.error('[v0] Error loading notifications:', error);
    }
}

// NEW FUNCTION: Handle notification click
async function handleNotificationClick(notificationId, actionUrl) {
    try {
        // First mark as read
        await markAsRead(notificationId);
        
        // Then navigate to the action URL
        window.location.href = actionUrl;
    } catch (error) {
        console.error('[v0] Error handling notification click:', error);
    }
}
        
        async function markAsRead(id) {
            try {
                const formData = new FormData();
                formData.append('id', id);
                
                await fetch('api/notifications.php?action=mark_read', {
                    method: 'POST',
                    body: formData
                });
                
                loadNotifications();
            } catch (error) {
                console.error('[v0] Error marking notification as read:', error);
            }
        }
        
        async function markAllAsRead() {
            try {
                await fetch('api/notifications.php?action=mark_all_read', {
                    method: 'POST'
                });
                
                loadNotifications();
            } catch (error) {
                console.error('[v0] Error marking all as read:', error);
            }
        }
        
        function formatTime(timestamp) {
            const date = new Date(timestamp);
            const now = new Date();
            const diff = Math.floor((now - date) / 1000); // seconds
            
            if (diff < 60) return 'Just now';
            if (diff < 3600) return Math.floor(diff / 60) + ' minutes ago';
            if (diff < 86400) return Math.floor(diff / 3600) + ' hours ago';
            if (diff < 604800) return Math.floor(diff / 86400) + ' days ago';
            
            return date.toLocaleDateString();
        }
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('notificationDropdown');
            const button = document.querySelector('.btn-notification');
            
            if (notificationsOpen && !dropdown.contains(event.target) && !button.contains(event.target)) {
                toggleNotifications();
            }
        });
</script>
      <?php include 'chat_bot.php'; ?>
</body>
</html>