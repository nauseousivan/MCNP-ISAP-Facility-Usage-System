<?php
session_start();
require_once '../config.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'Admin') {
    header("Location: ../index.php");
    exit();
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Get theme preference
$theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'light';

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
        } else {
            $_SESSION['error'] = "Error adding facility: " . $conn->error;
        }
        
        header("Location: facility_management.php");
        exit();
    }
    
    if (isset($_POST['delete_facility'])) {
        $facility_id = $_POST['facility_id'];
        
        // Soft delete (set is_active to FALSE)
        $sql = "UPDATE facilities SET is_active = FALSE WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $facility_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Facility deleted successfully";
        } else {
            $_SESSION['error'] = "Error deleting facility: " . $conn->error;
        }
        
        header("Location: facility_management.php");
        exit();
    }
}

// Get all facilities
$facilities = [];
$sql = "SELECT * FROM facilities ORDER BY is_active DESC, name";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $facilities[] = $row;
    }
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
            --background: #ffffff;
            --foreground: #0a0a0a;
            --card: #ffffff;
            --card-foreground: #0a0a0a;
            --muted: #f5f5f5;
            --muted-foreground: #737373;
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
            --sidebar: #fafafa;
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
            --sidebar: #171717;
            --sidebar-foreground: #fafafa;
            --sidebar-border: #262626;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
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
            border-radius: 8px;
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
            border-radius: 12px;
            max-width: 600px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            border: 1px solid var(--border);
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
        
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        
        .facility-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--card);
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid var(--border);
        }
        
        .facility-table th,
        .facility-table td {
            padding: 16px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        
        .facility-table th {
            background: var(--muted);
            font-weight: 600;
            color: var(--foreground);
        }
        
        .status-active {
            color: var(--success);
            font-weight: 600;
        }
        
        .status-inactive {
            color: var(--muted-foreground);
            font-weight: 600;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert-success {
            background: var(--success);
            color: white;
        }
        
        .alert-error {
            background: var(--danger);
            color: white;
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
                <div class="alert alert-success">
                    <?php 
                    echo htmlspecialchars($_SESSION['success']); 
                    unset($_SESSION['success']);
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <?php 
                    echo htmlspecialchars($_SESSION['error']); 
                    unset($_SESSION['error']);
                    ?>
                </div>
            <?php endif; ?>

            <div class="management-header">
                <h2>Manage Facilities</h2>
                <button class="btn-add" onclick="openAddModal()">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add New Facility
                </button>
            </div>

            <?php if (empty($facilities)): ?>
                <div style="text-align: center; padding: 40px; color: var(--muted-foreground);">
                    <p>No facilities found. Add your first facility to get started.</p>
                </div>
            <?php else: ?>
                <table class="facility-table">
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
                                    <span class="<?php echo $facility['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo $facility['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    $amenities = json_decode($facility['amenities'], true);
                                    if (is_array($amenities)) {
                                        echo implode(', ', array_slice($amenities, 0, 2));
                                        if (count($amenities) > 2) echo '...';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($facility['is_active']): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="facility_id" value="<?php echo $facility['id']; ?>">
                                                <button type="submit" name="delete_facility" class="btn btn-danger" 
                                                        onclick="return confirm('Are you sure you want to delete this facility?')">
                                                    Delete
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
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

    <script>
        function openAddModal() {
            document.getElementById('addModal').style.display = 'block';
        }
        
        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('addModal');
            if (event.target === modal) {
                closeAddModal();
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