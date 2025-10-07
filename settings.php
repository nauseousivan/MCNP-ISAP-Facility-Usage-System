<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];

// Get user details
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Handle settings update
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (password_verify($current_password, $user['password'])) {
            if ($new_password === $confirm_password) {
                if (strlen($new_password) >= 6) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $sql = "UPDATE users SET password = ? WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("si", $hashed_password, $user_id);
                    
                    if ($stmt->execute()) {
                        $success_message = "Password updated successfully!";
                        
                        // Create notification
                        $notif_sql = "INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)";
                        $notif_stmt = $conn->prepare($notif_sql);
                        $title = "Password Changed";
                        $message = "Your password was successfully updated.";
                        $type = "security";
                        $notif_stmt->bind_param("isss", $user_id, $title, $message, $type);
                        $notif_stmt->execute();
                    } else {
                        $error_message = "Failed to update password.";
                    }
                } else {
                    $error_message = "Password must be at least 6 characters long.";
                }
            } else {
                $error_message = "New passwords do not match.";
            }
        } else {
            $error_message = "Current password is incorrect.";
        }
    }
    
    if (isset($_POST['update_preferences'])) {
        $theme = $_POST['theme'] ?? 'light';
        $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
        $request_notifications = isset($_POST['request_notifications']) ? 1 : 0;
        
        // Check if preferences exist
        $check_sql = "SELECT id FROM user_preferences WHERE user_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $user_id);
        $check_stmt->execute();
        $pref_exists = $check_stmt->get_result()->num_rows > 0;
        
        if ($pref_exists) {
            // Update existing preferences
            $update_sql = "UPDATE user_preferences SET theme = ?, email_notifications = ?, request_notifications = ? WHERE user_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("siii", $theme, $email_notifications, $request_notifications, $user_id);
            $update_stmt->execute();
        } else {
            // Insert new preferences
            $insert_sql = "INSERT INTO user_preferences (user_id, theme, email_notifications, request_notifications) VALUES (?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("isii", $user_id, $theme, $email_notifications, $request_notifications);
            $insert_stmt->execute();
        }
        
        // Update session
        $_SESSION['theme'] = $theme;
        
        $success_message = "Preferences saved successfully! The theme will apply across all pages.";
    }
}

$pref_sql = "SELECT * FROM user_preferences WHERE user_id = ?";
$pref_stmt = $conn->prepare($pref_sql);
$pref_stmt->bind_param("i", $user_id);
$pref_stmt->execute();
$pref_result = $pref_stmt->get_result();

if ($pref_result->num_rows > 0) {
    $prefs = $pref_result->fetch_assoc();
    $theme = $prefs['theme'];
    $email_notifications = $prefs['email_notifications'];
    $request_notifications = $prefs['request_notifications'];
} else {
    // Default values if no preferences exist
    $theme = 'light';
    $email_notifications = 1;
    $request_notifications = 1;
}

// Determine logo based on department
$logo_file = 'combined-logo.png';
$department_lower = strtolower($user['department']);
if (strpos($department_lower, 'international') !== false) {
    $logo_file = 'isap-logo.png';
} elseif (strpos($department_lower, 'medical') !== false) {
    $logo_file = 'medical-logo.png';
}

// Get profile picture
$profile_picture = !empty($user['profile_picture']) ? $user['profile_picture'] : 'https://ui-avatars.com/api/?name=' . urlencode($user['name']) . '&background=6366f1&color=fff&size=128';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - MCNP Service Portal</title>
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
        }
        
        [data-theme="dark"] {
            --bg-primary: #1a1a1a;
            --bg-secondary: #2d2d2d;
            --text-primary: #ffffff;
            --text-secondary: #9ca3af;
            --border-color: #404040;
            --accent-color: #818cf8;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-secondary);
            color: var(--text-primary);
            transition: background 0.3s, color 0.3s;
        }
        
        .header {
            background: var(--bg-primary);
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 16px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .header-brand img {
            height: 48px;
            width: 48px;
            border-radius: 50%;
            object-fit: cover;
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
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .profile-button {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            overflow: hidden;
            cursor: pointer;
            border: 2px solid var(--border-color);
            transition: all 0.2s;
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
        
        .btn-nav {
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 6px;
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            transition: all 0.2s;
        }
        
        .btn-nav:hover {
            background: var(--border-color);
        }
        
        .btn-nav.active {
            background: var(--accent-color);
            color: white;
            border-color: var(--accent-color);
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 32px;
        }
        
        .page-header {
            margin-bottom: 32px;
        }
        
        .page-header h1 {
            font-size: 32px;
            margin-bottom: 8px;
        }
        
        .page-header p {
            color: var(--text-secondary);
            font-size: 15px;
        }
        
        .settings-section {
            background: var(--bg-primary);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .settings-section h2 {
            font-size: 18px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .settings-section h2 svg {
            width: 20px;
            height: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
            color: var(--text-primary);
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            background: var(--bg-secondary);
            color: var(--text-primary);
            transition: all 0.2s;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .checkbox-group label {
            margin: 0;
            cursor: pointer;
        }
        
        .btn-primary {
            background: var(--accent-color);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-primary:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #10b981;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #ef4444;
        }
        
        .theme-preview {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-top: 12px;
        }
        
        .theme-option {
            padding: 16px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
        }
        
        .theme-option:hover {
            border-color: var(--accent-color);
        }
        
        .theme-option.selected {
            border-color: var(--accent-color);
            background: rgba(99, 102, 241, 0.1);
        }
        
        .theme-option svg {
            width: 32px;
            height: 32px;
            margin-bottom: 8px;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 16px;
            }
            
            .header {
                padding: 16px;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-brand">
            <img src="<?php echo $logo_file; ?>" alt="Logo">
            <div class="brand-text">
                <div class="brand-title">MCNP Service Portal</div>
                <div class="brand-subtitle">Settings</div>
            </div>
        </div>
        <div class="header-actions">
            <a href="profile.php" class="profile-button" title="View Profile">
                <img src="<?php echo $profile_picture; ?>" alt="Profile">
            </a>
            <a href="dashboard.php" class="btn-nav">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="16" height="16">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                Dashboard
            </a>
            <a href="settings.php" class="btn-nav active">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="16" height="16">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                Settings
            </a>
            <a href="logout.php" class="btn-nav">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="16" height="16">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
                Logout
            </a>
        </div>
    </header>

    <div class="container">
        <div class="page-header">
            <h1>Settings</h1>
            <p>Manage your account preferences and security settings</p>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <div class="settings-section">
            <h2>
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/>
                </svg>
                Appearance
            </h2>
            <form method="POST">
                <div class="form-group">
                    <label>Theme</label>
                    <div class="theme-preview">
                        <div class="theme-option <?php echo $theme === 'light' ? 'selected' : ''; ?>" onclick="selectTheme('light')">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707A2 2 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                            </svg>
                            <div>Light</div>
                        </div>
                        <div class="theme-option <?php echo $theme === 'dark' ? 'selected' : ''; ?>" onclick="selectTheme('dark')">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                            </svg>
                            <div>Dark</div>
                        </div>
                    </div>
                    <input type="hidden" name="theme" id="themeInput" value="<?php echo $theme; ?>">
                </div>
                <button type="submit" name="update_preferences" class="btn-primary">Save Appearance</button>
            </form>
        </div>

        <div class="settings-section">
            <h2>
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
                Notifications
            </h2>
            <form method="POST">
                <div class="checkbox-group">
                    <input type="checkbox" id="email_notifications" name="email_notifications" <?php echo $email_notifications ? 'checked' : ''; ?>>
                    <label for="email_notifications">Email notifications for request updates</label>
                </div>
                <div class="checkbox-group">
                    <input type="checkbox" id="request_notifications" name="request_notifications" <?php echo $request_notifications ? 'checked' : ''; ?>>
                    <label for="request_notifications">In-app notifications</label>
                </div>
                <button type="submit" name="update_preferences" class="btn-primary">Save Preferences</button>
            </form>
        </div>

        <div class="settings-section">
            <h2>
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
                Security
            </h2>
            <form method="POST">
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="current_password" required>
                </div>
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" required minlength="6">
                </div>
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" required minlength="6">
                </div>
                <button type="submit" name="update_password" class="btn-primary">Update Password</button>
            </form>
        </div>
    </div>

    <script>
        function selectTheme(theme) {
            document.getElementById('themeInput').value = theme;
            document.querySelectorAll('.theme-option').forEach(el => el.classList.remove('selected'));
            event.currentTarget.classList.add('selected');
            document.documentElement.setAttribute('data-theme', theme);
        }
    </script>
    <?php include 'chat_bot.php'; ?>
</body>
</html>
