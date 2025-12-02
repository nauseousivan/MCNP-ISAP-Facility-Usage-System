<?php
session_start();
require_once 'theme_loader.php';
require_once 'config.php';
require_once 'functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$user_id = $_SESSION['user_id'];

// Get theme directly from database as fallback
$theme = 'light';
$sql = "SELECT theme FROM user_preferences WHERE user_id = ?";
$border_options = [
    'color' => '#667eea',
    'width' => 4,
    'style' => 'solid'
];

$profile_song_url = '';

$sql = "SELECT theme, profile_border_color, profile_border_width, profile_border_style, profile_song_url FROM user_preferences WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $prefs = $result->fetch_assoc();
    $theme = $prefs['theme'] ?? 'light';
    $border_options['color'] = $prefs['profile_border_color'] ?? $border_options['color'];
    $border_options['width'] = $prefs['profile_border_width'] ?? $border_options['width'];
    $border_options['style'] = $prefs['profile_border_style'] ?? $border_options['style'];
    $profile_song_url = $prefs['profile_song_url'] ?? '';
}

// Get user details
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$success = '';
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['update_profile'])) {
        // Handle border options update
        $border_color = $_POST['border_color'];
        $border_width = $_POST['border_width'];
        $border_style = $_POST['border_style'];

        $pref_sql = "INSERT INTO user_preferences (user_id, profile_border_color, profile_border_width, profile_border_style)
                     VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE 
                     profile_border_color = VALUES(profile_border_color), 
                     profile_border_width = VALUES(profile_border_width), 
                     profile_border_style = VALUES(profile_border_style)";
        $pref_stmt = $conn->prepare($pref_sql);
        $pref_stmt->bind_param("isis", $user_id, $border_color, $border_width, $border_style);
        $pref_stmt->execute();

        $name = $_POST['name'];
        $phone = $_POST['phone'];
        $bio = $_POST['bio'];
        
        // Check what specifically changed for more specific notifications
        $changed_fields = [];
        if ($name != $user['name']) $changed_fields[] = 'name';
        if ($phone != $user['phone_number']) $changed_fields[] = 'phone';
        if ($bio != $user['bio']) $changed_fields[] = 'bio';
        
        $sql = "UPDATE users SET name = ?, phone_number = ?, bio = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssi", $name, $phone, $bio, $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['user_name'] = $name;
            
            // Add specific notifications based on what changed
            if (in_array('phone', $changed_fields)) {
                add_action_notification($conn, $user_id, 'phone_updated');
            }
            
            // General profile update notification
            add_action_notification($conn, $user_id, 'profile_update');
            
            $success = "Profile updated successfully!";
            
            // Refresh user data
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            // Refresh border options
            $border_options['color'] = $border_color;
            $border_options['width'] = $border_width;
            $border_options['style'] = $border_style;

        } else {
            $error = "Error updating profile.";
        }
    }

    if (isset($_POST['update_song'])) {
        $song_url = trim($_POST['song_url']);
        $embed_url = '';

        if (!empty($song_url)) {
            // Extract YouTube video ID from URL
            $video_id = '';
            $pattern = '/(?:https?:\/\/)?(?:www\.)?(?:youtube\.com\/(?:[^\/\n\s]+\/\S+\/|(?:v|e(?:mbed)?)\/|\S*?[?&]v=)|youtu\.be\/)([a-zA-Z0-9_-]{11})/';
            if (preg_match($pattern, $song_url, $matches)) {
                $video_id = $matches[1];
            }

            if ($video_id) {
                $embed_url = 'https://www.youtube.com/embed/' . $video_id;
            } else {
                $error = "Invalid YouTube URL. Please provide a valid link.";
            }
        }

        if (!$error) {
            $pref_sql = "INSERT INTO user_preferences (user_id, profile_song_url)
                         VALUES (?, ?)
                         ON DUPLICATE KEY UPDATE profile_song_url = VALUES(profile_song_url)";
            $pref_stmt = $conn->prepare($pref_sql);
            $pref_stmt->bind_param("is", $user_id, $embed_url);
            
            if ($pref_stmt->execute()) {
                $success = "Profile song updated successfully!";
                $profile_song_url = $embed_url;
            } else {
                $error = "Error saving profile song.";
            }
        }
    }
    
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        $file_type = $_FILES['profile_picture']['type'];
        $file_size = $_FILES['profile_picture']['size'];
        
        // Validate file type
        if (!in_array($file_type, $allowed_types)) {
            $error = "Only JPG, JPEG, PNG, GIF, and WebP images are allowed.";
        }
        // Validate file size
        elseif ($file_size > $max_size) {
            $error = "Image size must be less than 5MB.";
        }
        else {
            // Create uploads directory if it doesn't exist
            $upload_dir = 'uploads/profiles/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Generate unique filename
            $file_extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
            $filename = 'profile_' . $user_id . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $filename;
            
            // Delete old profile picture if exists
            if (!empty($user['profile_picture']) && file_exists($user['profile_picture'])) {
                unlink($user['profile_picture']);
            }
            
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                $sql = "UPDATE users SET profile_picture = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $upload_path, $user_id);
                
                if ($stmt->execute()) {
                    // Add specific notification for profile picture update
                    add_action_notification($conn, $user_id, 'profile_picture_update');
                    
                    $success = "Profile picture updated successfully!";
                    $user['profile_picture'] = $upload_path;
                    
                    // Update session with new profile picture path
                    $_SESSION['profile_picture'] = $upload_path;
                } else {
                    $error = "Error saving profile picture.";
                }
            } else {
                $error = "Error uploading file.";
            }
        }
    }

    if (isset($_FILES['cover_photo']) && $_FILES['cover_photo']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 5 * 1024 * 1024; // 5MB

        $file_type = $_FILES['cover_photo']['type'];
        $file_size = $_FILES['cover_photo']['size'];

        if (!in_array($file_type, $allowed_types)) {
            $error = "Only JPG, JPEG, PNG, GIF, and WebP images are allowed for cover photos.";
        } elseif ($file_size > $max_size) {
            $error = "Cover photo size must be less than 5MB.";
        } else {
            $upload_dir = 'uploads/covers/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_extension = pathinfo($_FILES['cover_photo']['name'], PATHINFO_EXTENSION);
            $filename = 'cover_' . $user_id . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $filename;

            if (!empty($user['cover_photo']) && file_exists($user['cover_photo'])) {
                unlink($user['cover_photo']);
            }

            if (move_uploaded_file($_FILES['cover_photo']['tmp_name'], $upload_path)) {
                $sql = "UPDATE users SET cover_photo = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $upload_path, $user_id);

                if ($stmt->execute()) {
                    $success = "Cover photo updated successfully!";
                } else {
                    $error = "Error saving cover photo to database.";
                }
            } else {
                $error = "Error uploading cover photo.";
            }
        }
    }

    if (isset($_POST['update_cover_photo'])) {
        // This block is intentionally left empty for now.
        // The cover photo is handled by its own form submission via JavaScript.
        // This ensures that when the main profile form is submitted,
        // it doesn't interfere with other uploads.
    }

    // Re-fetch user data after all potential updates to ensure the page has the latest info
    $sql = "SELECT * FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

}

$logo_file = $GLOBALS['logo_file'];
$portal_name = $GLOBALS['portal_name'];
$portal_subtitle = $GLOBALS['portal_subtitle'];
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($theme); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - MCNP Service Portal</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --bg-primary: #ffffff; /* Card and Header background */
            --bg-secondary: #fdfaf6; /* Main page background */
            --text-primary: #1a1a1a;
            --text-secondary: #71717a;
            --border-color: #e5e7eb; 
            --card-bg: #ffffff;
            --input-bg: #ffffff;
            --input-border: #d1d5db;
            --input-text: #1a1a1a;
            --btn-bg: #1a1a1a;
            --btn-text: #ffffff;
            --btn-hover: #000000;
            --alert-success-bg: #d1fae5;
            --alert-success-text: #065f46;
            --alert-success-border: #a7f3d0;
            --alert-danger-bg: #fee2e2;
            --alert-danger-text: #991b1b;
            --alert-danger-border: #fecaca;
            --stat-bg: #f3f4f6;
            --bg-muted: #f3f4f6; /* For disabled inputs */
            --stat-text: #6b7280;
        }

        [data-theme="dark"] {
            --bg-primary: #171717; /* Card and Header background */
            --bg-secondary: #0a0a0a; /* Main page background */
            --text-primary: #ffffff;
            --text-secondary: #9ca3af;
            --border-color: #404040; 
            --card-bg: #2d2d2d;
            --input-bg: #404040;
            --input-border: #4b5563;
            --input-text: #ffffff;
            --btn-bg: #ffffff;
            --btn-text: #1a1a1a;
            --btn-hover: #e5e7eb;
            --alert-success-bg: #064e3b;
            --alert-success-text: #a7f3d0;
            --alert-success-border: #065f46;
            --alert-danger-bg: #7f1d1d;
            --alert-danger-text: #fecaca;
            --alert-danger-border: #991b1b;
            --stat-bg: #404040;
            --bg-muted: #2d2d2d; /* For disabled inputs */
            --stat-text: #d1d5db;
        }

        @font-face {
            font-family: 'Geist Sans';
            src: url('node_modules/geist/dist/fonts/geist-sans/Geist-Variable.woff2') format('woff2');
            font-weight: 100 900;
            font-style: normal;
        }

        body {
            font-family: 'Geist Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-secondary);
            color: var(--text-primary);
        }
        
        .header {
            background: var(--bg-primary);
            padding: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
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
        
        .header-brand .brand-text {
            display: flex;
            flex-direction: column;
        }
        
        .header-brand .brand-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .header-brand .brand-subtitle {
            font-size: 12px;
            color: var(--text-secondary);
        }
        
        .btn-back {
            padding: 8px 16px;
            background: var(--bg-primary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .btn-back:hover {
            background: var(--bg-secondary);
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 24px 16px;
        }
        
        /* Improved profile card layout */
        .profile-layout {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 24px;
        }
        
        .profile-sidebar {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 32px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            text-align: center;
            border: 1px solid var(--border-color);
        }
        
        .profile-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 32px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: 1px solid var(--border-color);
        }
        
        /* Cover Photo Styles */
        .profile-cover-container {
            position: relative;
            height: 200px;
            background: var(--bg-muted);
            border-radius: 12px 12px 0 0;
            margin: -32px -32px 0 -32px;
            overflow: hidden;
        }

        .cover-photo {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .cover-photo-upload {
            position: absolute;
            bottom: 12px;
            right: 12px;
            width: 36px;
            height: 36px;
            background: rgba(0,0,0,0.5);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        .cover-photo-upload:hover { background: rgba(0,0,0,0.7); }
        .cover-photo-upload svg { width: 18px; height: 18px; color: white; }
        .cover-photo-upload input { display: none; }

        /* Enhanced profile avatar with image support */
        .profile-avatar-container {
            position: relative;
            width: 120px;
            height: 120px;
            margin: 0 auto 20px;
        }
        .profile-sidebar .profile-avatar-container { margin-top: -60px; z-index: 2; }

        
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 48px;
            font-weight: 700;
            overflow: hidden;
            border: 4px solid var(--border-color);
            border: <?php echo $border_options['width']; ?>px <?php echo $border_options['style']; ?> <?php echo $border_options['color']; ?>;
        }
        
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .profile-avatar-upload {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 36px;
            height: 36px;
            background: var(--btn-bg);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: 3px solid var(--card-bg);
            transition: all 0.2s;
        }
        
        .profile-avatar-upload:hover {
            background: var(--btn-hover);
            transform: scale(1.1);
        }
        
        .profile-avatar-upload svg {
            width: 18px;
            height: 18px;
            color: var(--btn-text);
        }
        
        .profile-avatar-upload input {
            display: none;
        }
        
        .profile-header h2 {
            font-size: 24px;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        
        .profile-header p {
            color: var(--text-secondary);
            font-size: 14px;
            margin-bottom: 16px;
        }
        
        /* Added profile stats */
        .profile-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid var(--border-color);
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-item .number {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .stat-item .label {
            font-size: 12px;
            color: var(--stat-text);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .section-title {
            font-size: 18px;
            color: var(--text-primary);
            margin-bottom: 20px;
            font-weight: 700;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        
        input[type="text"],
        input[type="email"],
        input[type="tel"],
        textarea,
        select {
            width: 100%;
            padding: 12px 16px;
            font-size: 15px;
            background: var(--input-bg);
            color: var(--input-text);
            border: 1px solid var(--input-border);
            border-radius: 8px;
            font-family: inherit;
            transition: all 0.2s;
        }
        
        textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        input:focus,
        textarea:focus,
        select:focus {
            outline: none;
            border-color: var(--text-primary);
            box-shadow: 0 0 0 3px rgba(0,0,0,0.05);
        }
        
        input:disabled {
            background: var(--bg-muted);
            color: var(--text-secondary);
            cursor: not-allowed;
        }
        
        .btn-submit {
            width: 100%;
            padding: 14px;
            background: var(--btn-bg);
            color: var(--btn-text);
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-submit:hover {
            background: var(--btn-hover);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            animation: slideDown 0.3s ease;
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
        
        .alert-success {
            background: var(--alert-success-bg);
            color: var(--alert-success-text);
            border: 1px solid var(--alert-success-border);
        }
        
        .alert-danger {
            background: var(--alert-danger-bg);
            color: var(--alert-danger-text);
            border: 1px solid var(--alert-danger-border);
        }
        
        /* Mobile Responsive Styles */
        @media (max-width: 768px) {
            .header {
                padding: 12px 16px;
            }
            
            .header-brand img {
                height: 36px;
            }
            
            .header-brand .brand-title {
                font-size: 14px;
            }
            
            .header-brand .brand-subtitle {
                font-size: 11px;
            }
            
            .btn-back {
                padding: 6px 12px;
                font-size: 13px;
            }
            
            .container {
                padding: 16px;
            }
            
            .profile-layout {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            
            .profile-sidebar {
                padding: 24px 20px;
            }
            
            .profile-card {
                padding: 24px 20px;
            }
            
            .profile-avatar-container {
                width: 100px;
                height: 100px;
                margin-bottom: 16px;
            }
            
            .profile-avatar {
                width: 100px;
                height: 100px;
                font-size: 36px;
            }
            
            .profile-avatar-upload {
                width: 32px;
                height: 32px;
            }
            
            .profile-avatar-upload svg {
                width: 16px;
                height: 16px;
            }
            
            .profile-header h2 {
                font-size: 20px;
            }
            
            .profile-header p {
                font-size: 13px;
            }
            
            .profile-stats {
                margin-top: 20px;
                padding-top: 20px;
                gap: 8px;
            }
            
            .stat-item .number {
                font-size: 20px;
            }
            
            .stat-item .label {
                font-size: 11px;
            }
            
            .section-title {
                font-size: 16px;
                margin-bottom: 16px;
            }
            
            .form-group {
                margin-bottom: 16px;
            }
            
            label {
                font-size: 13px;
                margin-bottom: 6px;
            }
            
            input[type="text"],
            input[type="email"],
            input[type="tel"],
            textarea,
            select {
                padding: 10px 14px;
                font-size: 14px;
                border-radius: 6px;
            }
            
            textarea {
                min-height: 80px;
            }
            
            .btn-submit {
                padding: 12px;
                font-size: 15px;
                border-radius: 6px;
            }
            
            .alert {
                padding: 10px 14px;
                margin-bottom: 16px;
                font-size: 14px;
            }
        }
        
        @media (max-width: 480px) {
            .header {
                padding: 10px 12px;
            }
            
            .header-brand {
                gap: 8px;
            }
            
            .header-brand img {
                height: 32px;
            }
            
            .header-brand .brand-title {
                font-size: 13px;
            }
            
            .header-brand .brand-subtitle {
                font-size: 10px;
            }
            
            .btn-back {
                padding: 6px 10px;
                font-size: 12px;
            }
            
            .container {
                padding: 12px;
            }
            
            .profile-sidebar {
                padding: 20px 16px;
                border-radius: 10px;
            }
            
            .profile-card {
                padding: 20px 16px;
                border-radius: 10px;
            }
            
            .profile-avatar-container {
                width: 80px;
                height: 80px;
            }
            
            .profile-avatar {
                width: 80px;
                height: 80px;
                font-size: 28px;
                border-width: 3px;
            }
            
            .profile-avatar-upload {
                width: 28px;
                height: 28px;
                border-width: 2px;
            }
            
            .profile-avatar-upload svg {
                width: 14px;
                height: 14px;
            }
            
            .profile-header h2 {
                font-size: 18px;
            }
            
            .profile-stats {
                grid-template-columns: repeat(2, 1fr);
                gap: 6px;
            }
            
            .stat-item .number {
                font-size: 18px;
            }
            
            .stat-item .label {
                font-size: 10px;
            }
            
            input[type="text"],
            input[type="email"],
            input[type="tel"],
            textarea,
            select {
                padding: 8px 12px;
                font-size: 13px;
            }
            
            .btn-submit {
                padding: 10px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
<header class="header">
    <a href="dashboard.php" style="text-decoration: none; color: inherit;">
        <div class="header-brand">
            <img src="<?php echo htmlspecialchars($logo_file); ?>" alt="Logo">
            <div class="brand-text">
                <div class="brand-title"><?php echo htmlspecialchars($portal_name); ?></div>
                <div class="brand-subtitle">Profile Settings</div>
            </div>
        </div>
    </a>
    <a href="dashboard.php" class="btn-back">Back</a>
</header>

    <div class="container">
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- New profile layout with sidebar -->
        <div class="profile-layout">
            <!-- Profile Sidebar -->
            <div class="profile-sidebar">
                <div class="profile-cover-container">
                    <img src="<?php echo !empty($user['cover_photo']) && file_exists($user['cover_photo']) ? htmlspecialchars($user['cover_photo']) : 'https://via.placeholder.com/400x200/e0e0e0/9ca3af?text=Upload+Cover'; ?>" alt="Cover Photo" class="cover-photo">
                    <form method="POST" enctype="multipart/form-data" id="coverForm">
                        <label class="cover-photo-upload" title="Upload Cover Photo">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <input type="file" name="cover_photo" accept="image/*" onchange="document.getElementById('coverForm').submit()">
                        </label>
                    </form>
                </div>

                <div class="profile-avatar-container">
                    <div class="profile-avatar">
                        <?php if (!empty($user['profile_picture']) && file_exists($user['profile_picture'])): ?>
                            <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Profile Picture">
                        <?php else: ?>
                            <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <form method="POST" enctype="multipart/form-data" id="avatarForm" title="Upload Profile Picture">
                        <label class="profile-avatar-upload">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <input type="file" name="profile_picture" accept="image/*" onchange="document.getElementById('avatarForm').submit()">
                        </label>
                    </form>
                </div>
                <div class="profile-header">
                    <h2><?php echo htmlspecialchars($user['name']); ?></h2>
                    <p><?php echo htmlspecialchars($user['user_type']); ?></p>
                </div>
                
                <!-- Added profile stats -->
                <?php
                $stats_sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved
                    FROM facility_requests WHERE user_id = ?";
                $stats_stmt = $conn->prepare($stats_sql);
                $stats_stmt->bind_param("i", $user_id);
                $stats_stmt->execute();
                $stats = $stats_stmt->get_result()->fetch_assoc();
                ?>
                <div class="profile-stats">
                    <div class="stat-item">
                        <div class="number"><?php echo $stats['total']; ?></div>
                        <div class="label">Requests</div>
                    </div>
                    <div class="stat-item">
                        <div class="number"><?php echo $stats['approved']; ?></div>
                        <div class="label">Approved</div>
                    </div>
                </div>

                <!-- Profile Song Player -->
                <?php if ($profile_song_url): ?>
                <div style="margin-top: 24px; padding-top: 24px; border-top: 1px solid var(--border-color);">
                    <iframe 
                        width="100%" 
                        height="150" 
                        src="<?php echo htmlspecialchars($profile_song_url); ?>" 
                        title="YouTube video player" 
                        frameborder="0" 
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                        allowfullscreen
                        style="border-radius: 8px;"></iframe>
                </div>
                <?php endif; ?>
            </div>

            <!-- Profile Form -->
            <div class="profile-card">
                <h3 class="section-title">Profile Information</h3>
                <form method="POST" action="">
                    <input type="hidden" name="update_profile" value="1">
                    
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                    </div>

                    <div class="form-group">
                        <label>Department/Office</label>
                        <input type="text" value="<?php echo htmlspecialchars($user['department']); ?>" disabled>
                    </div>

                    <div class="form-group">
                        <label>Program/Course</label>
                        <input type="text" value="<?php echo htmlspecialchars($user['program']); ?>" disabled>
                    </div>

                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone_number'] ?? ''); ?>" placeholder="+63 XXX XXX XXXX">
                    </div>

                    <div class="form-group">
                        <label>Bio</label>
                        <textarea name="bio" placeholder="Tell us about yourself..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>User Type</label>
                        <input type="text" value="<?php echo htmlspecialchars($user['user_type']); ?>" disabled>
                    </div>

                    <button type="submit" class="btn-submit">Update Profile</button>
                </form>
            </div>

            <!-- Border Options Card -->
            <div class="profile-card">
                <h3 class="section-title">Border Options</h3>
                <form method="POST" action="">
                    <input type="hidden" name="update_profile" value="1">
                    <!-- Pass other profile data to avoid clearing them on save -->
                    <input type="hidden" name="name" value="<?php echo htmlspecialchars($user['name']); ?>">
                    <input type="hidden" name="phone" value="<?php echo htmlspecialchars($user['phone_number'] ?? ''); ?>">
                    <input type="hidden" name="bio" value="<?php echo htmlspecialchars($user['bio'] ?? ''); ?>">

                    <div class="form-group">
                        <label for="border_color">Border Color</label>
                        <input type="color" id="border_color" name="border_color" value="<?php echo htmlspecialchars($border_options['color']); ?>" oninput="updateBorderPreview()">
                    </div>
                    <div class="form-group">
                        <label for="border_width">Border Width (<?php echo htmlspecialchars($border_options['width']); ?>px)</label>
                        <input type="range" id="border_width" name="border_width" min="0" max="20" value="<?php echo htmlspecialchars($border_options['width']); ?>" oninput="updateBorderPreview()">
                    </div>
                    <div class="form-group">
                        <label for="border_style">Border Style</label>
                        <select id="border_style" name="border_style" onchange="updateBorderPreview()">
                            <?php $styles = ['solid', 'dashed', 'dotted', 'double', 'groove', 'ridge']; ?>
                            <?php foreach ($styles as $style): ?>
                                <option value="<?php echo $style; ?>" <?php echo $border_options['style'] === $style ? 'selected' : ''; ?>><?php echo ucfirst($style); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn-submit">Save Border Options</button>
                </form>
            </div>

            <!-- Profile Song Card -->
            <div class="profile-card">
                <h3 class="section-title">Profile Song</h3>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="song_url">YouTube URL</label>
                        <input type="text" id="song_url" name="song_url" placeholder="Paste a YouTube link here..." value="<?php echo htmlspecialchars($profile_song_url ? 'https://www.youtube.com/watch?v=' . basename($profile_song_url) : ''); ?>">
                        <p style="font-size: 12px; color: var(--text-secondary); margin-top: 8px;">
                            To remove your song, clear this field and click save.
                        </p>
                    </div>
                    <button type="submit" name="update_song" class="btn-submit">Save Song</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function updateBorderPreview() {
            const color = document.getElementById('border_color').value;
            const width = document.getElementById('border_width').value;
            const style = document.getElementById('border_style').value;
            const avatar = document.querySelector('.profile-avatar');
            avatar.style.border = `${width}px ${style} ${color}`;
        }
    </script>
</body>
</html>