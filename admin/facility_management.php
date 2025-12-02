<?php
session_start();
require_once '../config.php';
require_once '../functions.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'Admin') {
    header("Location: ../index.php");
    exit();
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Get theme preference
$theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'light';

// Get search term
$search = $_GET['search'] ?? '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_facility'])) {
        $name = trim($_POST['name']);
        $capacity = trim($_POST['capacity']);
        $description = trim($_POST['description']);
        $amenities = json_encode(array_map('trim', explode(',', $_POST['amenities'])));
        
        // Handle image upload
        $image_path = 'img/default-facility.jpg';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
            $upload_dir = '../img/';
            $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '.' . $file_extension;
            $target_path = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                $image_path = 'img/' . $filename;
            }
        }
        
        $sql = "INSERT INTO facilities (name, capacity, description, amenities, image_path) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssss", $name, $capacity, $description, $amenities, $image_path);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Facility added successfully";
            // Log this action
            $details = "Admin {$_SESSION['user_name']} added a new facility: {$name}.";
            log_admin_action($conn, $_SESSION['user_id'], $_SESSION['user_name'], 'facility_added', null, null, $details);
        } else {
            $_SESSION['error'] = "Error adding facility: " . $conn->error;
        }
        
        header("Location: facility_management.php");
        exit();
    }

    if (isset($_POST['edit_facility'])) {
        $facility_id = $_POST['facility_id'];
        $name = trim($_POST['name']);
        $capacity = trim($_POST['capacity']);
        $description = trim($_POST['description']);
        $amenities = json_encode(array_map('trim', explode(',', $_POST['amenities'])));

        // Get current image path
        $stmt = $conn->prepare("SELECT image_path FROM facilities WHERE id = ?");
        $stmt->bind_param("i", $facility_id);
        $stmt->execute();
        $image_path = $stmt->get_result()->fetch_assoc()['image_path'] ?? 'img/default-facility.jpg';

        // Handle new image upload
        if (isset($_FILES['image']) && $_FILES['image']['error'] === 0 && $_FILES['image']['size'] > 0) {
            $upload_dir = '../img/';
            $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '.' . $file_extension;
            $target_path = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                $image_path = 'img/' . $filename;
            }
        }

        $sql = "UPDATE facilities SET name = ?, capacity = ?, description = ?, amenities = ?, image_path = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssi", $name, $capacity, $description, $amenities, $image_path, $facility_id);
        if ($stmt->execute()) {
            $_SESSION['success'] = "Facility updated successfully";
            $details = "Admin {$_SESSION['user_name']} updated facility: {$name} (ID: {$facility_id}).";
            log_admin_action($conn, $_SESSION['user_id'], $_SESSION['user_name'], 'facility_updated', null, null, $details);
        } else {
            $_SESSION['error'] = "Error updating facility: " . $conn->error;
        }
        header("Location: facility_management.php");
        exit();
    }
    
    if (isset($_POST['delete_facility'])) {
        $facility_id = $_POST['facility_id'];
        
        // Get facility name for logging
        $name_stmt = $conn->prepare("SELECT name FROM facilities WHERE id = ?");
        $name_stmt->bind_param("i", $facility_id);
        $name_stmt->execute();
        $facility_name = $name_stmt->get_result()->fetch_assoc()['name'] ?? 'N/A';

        // Soft delete (set is_active to FALSE)
        $sql = "UPDATE facilities SET is_active = FALSE WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $facility_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Facility deleted successfully";
            $details = "Admin {$_SESSION['user_name']} deactivated facility: {$facility_name} (ID: {$facility_id}).";
            log_admin_action($conn, $_SESSION['user_id'], $_SESSION['user_name'], 'facility_deactivated', null, null, $details);
        } else {
            $_SESSION['error'] = "Error deleting facility: " . $conn->error;
        }
        
        header("Location: facility_management.php");
        exit();
    }

    if (isset($_POST['restore_facility'])) {
        $facility_id = $_POST['facility_id'];
        
        // Get facility name for logging
        $name_stmt = $conn->prepare("SELECT name FROM facilities WHERE id = ?");
        $name_stmt->bind_param("i", $facility_id);
        $name_stmt->execute();
        $facility_name = $name_stmt->get_result()->fetch_assoc()['name'] ?? 'N/A';

        // Restore by setting is_active to TRUE
        $sql = "UPDATE facilities SET is_active = TRUE WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $facility_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Facility restored successfully";
            $details = "Admin {$_SESSION['user_name']} restored facility: {$facility_name} (ID: {$facility_id}).";
            log_admin_action($conn, $_SESSION['user_id'], $_SESSION['user_name'], 'facility_restored', null, null, $details);
        } else {
            $_SESSION['error'] = "Error restoring facility: " . $conn->error;
        }
        
        header("Location: facility_management.php");
        exit();
    }
}

// Get all facilities
$facilities = [];
$sql = "SELECT * FROM facilities WHERE 1=1";
$params = [];
$types = "";

if ($search) {
    $sql .= " AND (name LIKE ? OR capacity LIKE ? OR amenities LIKE ?)";
    $search_param = "%$search%";
    $params = [$search_param, $search_param, $search_param];
    $types = "sss";
}

$sql .= " ORDER BY is_active DESC, name";

$stmt = $conn->prepare($sql);
if ($search) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $facilities[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en" class="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facility Management - Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --background: #fdfaf6;
            --foreground: #0a0a0a;
            --card: #ffffff;
            --card-foreground: #0a0a0a;
            --muted: #f8f5f1;
            --muted-foreground: #71717a;
            --border: #e5e5e5;
            --primary: #0a0a0a;
            --primary-foreground: #fafafa;
            --secondary: #f5f5f5;
            --secondary-foreground: #0a0a0a;
            --accent: #f5f5f5;
            --accent-foreground: #0a0a0a;
            --success: #22c55e;
            --warning: #f59e0b;
            --danger: #ef4444;
            --sidebar: #ffffff;
            --sidebar-foreground: #0a0a0a;
            --sidebar-border: #e5e5e5;
        }
        
        .dark {
            --background: #0a0a0a;
            --foreground: #fafafa;
            --card: #0a0a0a;
            --card-foreground: #fafafa;
            --muted: #262626;
            --muted-foreground: #a3a3a3;
            --border: #262626;
            --primary: #fafafa;
            --primary-foreground: #0a0a0a;
            --secondary: #262626;
            --secondary-foreground: #fafafa;
            --accent: #262626;
            --accent-foreground: #fafafa;
            --sidebar: #0a0a0a;
            --sidebar-foreground: #fafafa;
            --sidebar-border: #262626;
        }
        
        @font-face {
            font-family: 'Geist Sans';
            src: url('../node_modules/geist/dist/fonts/geist-sans/Geist-Variable.woff2') format('woff2');
            font-weight: 100 900;
            font-style: normal;
        }

        body {
            font-family: 'Geist Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: var(--background);
            color: var(--foreground);
            display: flex;
            min-height: 100vh;
            transition: background-color 0.3s, color 0.3s;
        }
        
        /* Sidebar */
        .sidebar {
            width: 260px;
            background: var(--sidebar);
            border-right: 1px solid var(--sidebar-border);
            padding: 24px 16px;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: all 0.3s ease;
            z-index: 1000;
        }
        
        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 12px;
            margin-bottom: 32px;
            font-size: 18px;
            font-weight: 700;
            color: var(--sidebar-foreground);
        }
        
        .sidebar-brand img {
            height: 32px;
            width: auto;
        }
        
        .sidebar-menu {
            list-style: none;
        }
        
        .sidebar-menu li {
            margin-bottom: 4px;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            color: var(--muted-foreground);
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.2s;
            font-size: 14px;
            font-weight: 500;
        }
        
        .sidebar-menu a:hover {
            background: var(--accent);
            color: var(--accent-foreground);
        }
        
        .sidebar-menu a.active {
            background: var(--primary);
            color: var(--primary-foreground);
        }
        
        .sidebar-menu svg {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }
        
        .sidebar-divider {
            margin: 24px 0;
            padding-top: 24px;
            border-top: 1px solid var(--border);
        }
        
        /* Main Content */
        .main-content {
            margin-left: 260px;
            flex: 1;
            display: flex;
            flex-direction: column;
            transition: margin-left 0.3s ease;
        }
        
        /* Top Nav */
        .top-nav {
            height: 64px;
            border-bottom: 1px solid var(--border);
            padding: 0 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--background);
            position: sticky;
            top: 0;
            z-index: 10;
            transition: background-color 0.3s, border-color 0.3s;
        }
        
        .top-nav-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .top-nav h1 {
            font-size: 20px;
            font-weight: 600;
        }
        
        .top-nav-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .theme-toggle {
            padding: 8px;
            border-radius: 8px;
            border: none;
            background: transparent;
            cursor: pointer;
            color: var(--foreground);
            transition: background-color 0.2s;
        }
        
        .theme-toggle:hover {
            background: var(--muted);
        }
        
        .theme-toggle svg {
            width: 20px;
            height: 20px;
        }

        /* Mobile menu button */
        .mobile-menu-btn {
            display: none;
            padding: 8px;
            background: transparent;
            border: none;
            color: var(--foreground);
            cursor: pointer;
            border-radius: 8px;
            transition: background-color 0.2s;
        }

        .mobile-menu-btn:hover {
            background: var(--muted);
        }

        .mobile-menu-btn svg {
            width: 20px;
            height: 20px;
        }
        
        /* Content Area */
        .content {
            flex: 1;
            padding: 32px;
            overflow-y: auto;
        }
        
        /* Management Specific Styles */
        .management-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        
        .btn-add {
            padding: 10px 20px;
            background: var(--primary);
            color: var(--primary-foreground);
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        
        .btn-add:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
        }
        
        .modal-content {
            background: var(--card);
            margin: 5% auto;
            padding: 0;
            border-radius: 20px;
            max-width: 600px;
            width: 90%;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border: 1px solid var(--border);
            overflow: hidden;
        }
        
        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-body {
            padding: 24px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--foreground);
        }
        
        .form-input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--background);
            color: var(--foreground);
            font-size: 14px;
            transition: border-color 0.2s;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 24px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: var(--primary);
            color: var(--primary-foreground);
        }
        
        .btn-secondary {
            background: var(--secondary);
            color: var(--secondary-foreground);
            border: 1px solid var(--border);
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-info {
            background: var(--info);
            color: white;
        }
        
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        
        /* Table styles from users.php */
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table thead th {
            text-align: left;
            padding: 12px;
            font-size: 12px;
            font-weight: 600;
            color: var(--muted-foreground);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border);
        }
        
        .table tbody td {
            padding: 16px 12px;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
        }
        
        .table tbody tr:hover {
            background: var(--muted);
        }

        .card {
            background: var(--card);
            border: 1px solid var(--border); 
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.03);
            margin-bottom: 24px;
        }

        .card-header {
            padding-bottom: 16px;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--border);
        }
        
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .badge.active {
            background: rgba(34, 197, 94, 0.1);
            color: var(--success);
        }
        
        .badge.inactive {
            background: rgba(115, 115, 115, 0.1);
            color: var(--muted-foreground);
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .alert-success {
            background: var(--success);
            color: white;
        }
        
        .alert-error {
            background: var(--danger);
            color: white;
        }

        /* New Alert Styles to match users.php */
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            border: 1px solid transparent;
        }
        .alert.alert-success {
            background: rgba(34, 197, 94, 0.1);
            color: var(--success);
            border-color: rgba(34, 197, 94, 0.2);
        }

        /* Search box styles from users.php */
        .search-box {
            margin-bottom: 24px;
        }

        .search-box input {
            width: 100%;
            max-width: 400px;
            padding: 10px 16px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            background: var(--card);
            color: var(--foreground);
        }

        .search-box input::placeholder {
            color: var(--muted-foreground);
        }

        /* Sidebar overlay for mobile */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }

        .sidebar-overlay.active {
            display: block;
        }

        body.sidebar-open {
            overflow: hidden;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }
            
            .sidebar.mobile-open {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .content {
                padding: 16px;
            }
            
            .top-nav {
                padding: 0 16px;
                height: 60px;
            }
            
            .top-nav h1 {
                font-size: 18px;
            }
            
            .mobile-menu-btn {
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .management-header {
                flex-direction: column;
                gap: 16px;
                align-items: flex-start;
            }
            
            .facility-table {
                font-size: 14px;
            }
            
            .facility-table th,
            .facility-table td {
                padding: 12px 8px;
            }
        }

        @media (max-width: 480px) {
            .content {
                padding: 12px;
            }
            
            .top-nav {
                padding: 0 12px;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 4px;
            }
            
            .btn {
                padding: 8px 12px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <!-- Include Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Mobile Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Nav -->
        <nav class="top-nav">
            <div class="top-nav-left">
                <button class="mobile-menu-btn" onclick="toggleSidebar()">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
                <h1>Facility Management</h1>
            </div>
            <div class="top-nav-actions">
                <button class="theme-toggle" onclick="toggleTheme()">
                    <svg class="sun-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                    <svg class="moon-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                    </svg>
                </button>
            </div>
        </nav>

        <!-- Content -->
        <main class="content">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success" role="alert">
                    <?php 
                    echo htmlspecialchars($_SESSION['success']); 
                    unset($_SESSION['success']);
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error" role="alert">
                    <?php 
                    echo htmlspecialchars($_SESSION['error']); 
                    unset($_SESSION['error']);
                    ?>
                </div>
            <?php endif; ?>

            <div class="management-header">
                <h2>All Facilities</h2>
                <button class="btn-add" onclick="openAddModal()">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add New Facility
                </button>
            </div>

            <div class="search-box">
                <form method="GET">
                    <input type="text" name="search" placeholder="Search by name, capacity, amenities..." value="<?php echo htmlspecialchars($search); ?>">
                </form>
            </div>

            <?php if (empty($facilities)): ?>
                <div style="text-align: center; padding: 40px; color: var(--muted-foreground);">
                    <p>No facilities found. Add your first facility to get started.</p>
                </div>
            <?php else: ?>
                <div class="card">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Capacity</th>
                                <th>Status</th>
                                <th>Amenities</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($facilities as $facility): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($facility['name']); ?></td>
                                    <td><?php echo htmlspecialchars($facility['capacity']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $facility['is_active'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $facility['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $amenities = json_decode($facility['amenities'], true);
                                        if (is_array($amenities)) {
                                            echo htmlspecialchars(implode(', ', array_slice($amenities, 0, 3)));
                                            if (count($amenities) > 3) echo '...';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-secondary" 
                                                    onclick='openEditModal(<?php echo json_encode($facility); ?>)'>
                                                Edit
                                            </button>
                                            <?php if ($facility['is_active']): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="facility_id" value="<?php echo $facility['id']; ?>">
                                                    <button type="submit" name="delete_facility" class="btn btn-danger" 
                                                            onclick="return confirm('Are you sure you want to deactivate this facility?')">
                                                        Deactivate
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="facility_id" value="<?php echo $facility['id']; ?>">
                                                    <button type="submit" name="restore_facility" class="btn btn-primary">
                                                        Restore
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Add Facility Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Facility</h3>
                <button onclick="closeAddModal()" style="background: none; border: none; cursor: pointer;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <form method="POST" enctype="multipart/form-data" class="modal-body">
                <div class="form-group">
                    <label class="form-label">Facility Name</label>
                    <input type="text" name="name" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Capacity</label>
                    <input type="text" name="capacity" class="form-input" placeholder="e.g., 40 students, 200 people" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-input" rows="3" required></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Amenities (comma separated)</label>
                    <input type="text" name="amenities" class="form-input" placeholder="e.g., Projector, Air Conditioning, WiFi" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Facility Image</label>
                    <input type="file" name="image" class="form-input" accept="image/*">
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Cancel</button>
                    <button type="submit" name="add_facility" class="btn btn-primary">Add Facility</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Facility Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Facility</h3>
                <button onclick="closeEditModal()" style="background: none; border: none; cursor: pointer;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <form method="POST" enctype="multipart/form-data" class="modal-body">
                <input type="hidden" name="facility_id" id="edit_facility_id">
                <div class="form-group">
                    <label class="form-label">Facility Name</label>
                    <input type="text" name="name" id="edit_name" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Capacity</label>
                    <input type="text" name="capacity" id="edit_capacity" class="form-input" placeholder="e.g., 40 students, 200 people" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="edit_description" class="form-input" rows="3" required></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Amenities (comma separated)</label>
                    <input type="text" name="amenities" id="edit_amenities" class="form-input" placeholder="e.g., Projector, Air Conditioning, WiFi" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Facility Image (leave blank to keep current)</label>
                    <input type="file" name="image" class="form-input" accept="image/*">
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" name="edit_facility" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('addModal').style.display = 'block';
        }
        
        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
        }
        
        function openEditModal(facility) {
            document.getElementById('edit_facility_id').value = facility.id;
            document.getElementById('edit_name').value = facility.name;
            document.getElementById('edit_capacity').value = facility.capacity;
            document.getElementById('edit_description').value = facility.description;
            
            let amenities = JSON.parse(facility.amenities || '[]');
            document.getElementById('edit_amenities').value = amenities.join(', ');

            document.getElementById('editModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        // Auto-submit search form on input
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput) {
            searchInput.addEventListener('input', () => searchInput.form.submit());
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const addModal = document.getElementById('addModal');
            const editModal = document.getElementById('editModal');
            if (event.target === addModal) {
                closeAddModal();
            } else if (event.target === editModal) {
                closeEditModal();
            }
        }
        
        function toggleTheme() {
            const html = document.documentElement;
            const currentTheme = html.classList.contains('dark') ? 'dark' : 'light';
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            html.classList.remove(currentTheme);
            html.classList.add(newTheme);
            
            document.cookie = `theme=${newTheme}; path=/; max-age=31536000`;
            updateThemeIcon(newTheme);
        }
        
        function updateThemeIcon(theme) {
            const sunIcon = document.querySelector('.sun-icon');
            const moonIcon = document.querySelector('.moon-icon');
            
            if (theme === 'dark') {
                sunIcon.style.display = 'block';
                moonIcon.style.display = 'none';
            } else {
                sunIcon.style.display = 'none';
                moonIcon.style.display = 'block';
            }
        }
        
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            sidebar.classList.toggle('mobile-open');
            overlay.classList.toggle('active');
            document.body.classList.toggle('sidebar-open');
        }

        // Close sidebar when clicking overlay
        document.getElementById('sidebarOverlay').addEventListener('click', function() {
            toggleSidebar();
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.querySelector('.sidebar');
            const mobileBtn = document.querySelector('.mobile-menu-btn');
            const overlay = document.getElementById('sidebarOverlay');
            
            if (window.innerWidth <= 768 && 
                !sidebar.contains(event.target) && 
                !mobileBtn.contains(event.target) &&
                sidebar.classList.contains('mobile-open')) {
                toggleSidebar();
            }
        });

        // Handle window resize
        window.addEventListener('resize', function() {
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            if (window.innerWidth > 768 && sidebar.classList.contains('mobile-open')) {
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('active');
                document.body.classList.remove('sidebar-open');
            }
        });
        
        document.addEventListener('DOMContentLoaded', function() {
            const theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
            updateThemeIcon(theme);
        });
    </script>
</body>
</html>