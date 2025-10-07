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
        $name = $_POST['name'];
        $department = $_POST['department'];
        $program = $_POST['program'];
        $phone = $_POST['phone'];
        $bio = $_POST['bio'];
        
        $sql = "UPDATE users SET name = ?, department = ?, program = ?, phone_number = ?, bio = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssi", $name, $department, $program, $phone, $bio, $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['user_name'] = $name;
            $success = "Profile updated successfully!";
            add_notification($conn, $user_id, "Profile Updated", "Your profile information has been successfully updated.", "profile_update");
            
            // Refresh user data
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
        } else {
            $error = "Error updating profile.";
        }
    }
    
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['profile_picture']['name'];
        $filetype = pathinfo($filename, PATHINFO_EXTENSION);
        
        if (in_array(strtolower($filetype), $allowed)) {
            $new_filename = 'profile_' . $user_id . '_' . time() . '.' . $filetype;
            $upload_path = 'uploads/profiles/' . $new_filename;
            
            // Create directory if it doesn't exist
            if (!file_exists('uploads/profiles')) {
                mkdir('uploads/profiles', 0777, true);
            }
            
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                $sql = "UPDATE users SET profile_picture = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $upload_path, $user_id);
                
                if ($stmt->execute()) {
                    $success = "Profile picture updated successfully!";
                    $user['profile_picture'] = $upload_path;
                } else {
                    $error = "Error saving profile picture.";
                }
            } else {
                $error = "Error uploading file.";
            }
        } else {
            $error = "Invalid file type. Only JPG, JPEG, PNG & GIF files are allowed.";
        }
    }
}

$logo_file = $GLOBALS['logo_file'];
$portal_name = $GLOBALS['portal_name'];
$portal_subtitle = $GLOBALS['portal_subtitle'];
?>
<!DOCTYPE html>
<html lang="en">
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
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8f9fa;
        }
        
        .header {
            background: white;
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
            color: #1a1a1a;
        }
        
        .header-brand .brand-subtitle {
            font-size: 12px;
            color: #6b7280;
        }
        
        .btn-back {
            padding: 8px 16px;
            background: white;
            color: #1a1a1a;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
        }
        
        .btn-back:hover {
            background: #f9fafb;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 32px;
        }
        
        /* Improved profile card layout */
        .profile-layout {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 24px;
        }
        
        .profile-sidebar {
            background: white;
            border-radius: 12px;
            padding: 32px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .profile-card {
            background: white;
            border-radius: 12px;
            padding: 32px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        /* Enhanced profile avatar with image support */
        .profile-avatar-container {
            position: relative;
            width: 120px;
            height: 120px;
            margin: 0 auto 20px;
        }
        
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
            border: 4px solid #f3f4f6;
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
            background: #1a1a1a;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: 3px solid white;
            transition: all 0.2s;
        }
        
        .profile-avatar-upload:hover {
            background: #000;
            transform: scale(1.1);
        }
        
        .profile-avatar-upload svg {
            width: 18px;
            height: 18px;
            color: white;
        }
        
        .profile-avatar-upload input {
            display: none;
        }
        
        .profile-header h2 {
            font-size: 24px;
            color: #1a1a1a;
            margin-bottom: 4px;
        }
        
        .profile-header p {
            color: #6b7280;
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
            border-top: 1px solid #e5e7eb;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-item .number {
            font-size: 24px;
            font-weight: 700;
            color: #1a1a1a;
        }
        
        .stat-item .label {
            font-size: 12px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .section-title {
            font-size: 18px;
            color: #1a1a1a;
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
            color: #374151;
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
            border: 1px solid #d1d5db;
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
            border-color: #1a1a1a;
            box-shadow: 0 0 0 3px rgba(0,0,0,0.05);
        }
        
        input:disabled {
            background: #f9fafb;
            color: #9ca3af;
        }
        
        .btn-submit {
            width: 100%;
            padding: 14px;
            background: #1a1a1a;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-submit:hover {
            background: #000;
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
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        @media (max-width: 768px) {
            .profile-layout {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-brand">
            <img src="img/<?php echo htmlspecialchars($logo_file); ?>" alt="Logo">
            <div class="brand-text">
                <div class="brand-title"><?php echo htmlspecialchars($portal_name); ?></div>
                <div class="brand-subtitle">Profile Settings</div>
            </div>
        </div>
        <a href="dashboard.php" class="btn-back">Back to Dashboard</a>
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
                <div class="profile-avatar-container">
                    <div class="profile-avatar">
                        <?php if (!empty($user['profile_picture']) && file_exists($user['profile_picture'])): ?>
                            <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Profile Picture">
                        <?php else: ?>
                            <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <form method="POST" enctype="multipart/form-data" id="avatarForm">
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
                        <input type="text" name="department" value="<?php echo htmlspecialchars($user['department']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Program/Course</label>
                        <input type="text" name="program" value="<?php echo htmlspecialchars($user['program']); ?>" required>
                    </div>

                    <!-- Changed input name from phone_number to phone -->
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone_number'] ?? ''); ?>" placeholder="+63 XXX XXX XXXX">
                    </div>

                    <!-- Added bio field -->
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
        </div>
    </div>
    <?php include 'chat_bot.php'; ?>
</body>
</html>