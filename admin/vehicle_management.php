<?php
session_start();
require_once '../config.php';
require_once '../functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'Admin') {
    header("Location: ../index.php");
    exit();
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'light';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_vehicle'])) {
        $name = trim($_POST['name']);
        $type = trim($_POST['type']);
        $capacity = (int)$_POST['capacity'];
        $driver_name = trim($_POST['driver_name']);
        $driver_contact = trim($_POST['driver_contact']);
        $amenities = json_encode(array_map('trim', explode(',', $_POST['amenities'])));

        $sql = "INSERT INTO transportation_vehicles (name, type, capacity, driver_name, driver_contact, amenities) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssisss", $name, $type, $capacity, $driver_name, $driver_contact, $amenities);
        $stmt->execute();
        $_SESSION['success'] = "Vehicle added successfully.";
    }

    if (isset($_POST['edit_vehicle'])) {
        $id = $_POST['id'];
        $name = trim($_POST['name']);
        $type = trim($_POST['type']);
        $capacity = (int)$_POST['capacity'];
        $driver_name = trim($_POST['driver_name']);
        $driver_contact = trim($_POST['driver_contact']);
        $amenities = json_encode(array_map('trim', explode(',', $_POST['amenities'])));
        $availability = $_POST['availability'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        $sql = "UPDATE transportation_vehicles SET name=?, type=?, capacity=?, driver_name=?, driver_contact=?, amenities=?, availability=?, is_active=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssissssii", $name, $type, $capacity, $driver_name, $driver_contact, $amenities, $availability, $is_active, $id);
        $stmt->execute();
        $_SESSION['success'] = "Vehicle updated successfully.";
    }

    if (isset($_POST['delete_vehicle'])) {
        $id = $_POST['id'];
        $sql = "UPDATE transportation_vehicles SET is_active = 0 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $_SESSION['success'] = "Vehicle deactivated successfully.";
    }
    
    header("Location: vehicle_management.php");
    exit();
}

// Fetch vehicles
$search = $_GET['search'] ?? '';
$sql = "SELECT * FROM transportation_vehicles WHERE name LIKE ? ORDER BY is_active DESC, name ASC";
$search_param = "%$search%";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $search_param);
$stmt->execute();
$vehicles = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en" class="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Management - Admin</title>
    <style>
        /* Using styles from facility_management.php for consistency */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root { --background: #fdfaf6; --foreground: #0a0a0a; --card: #ffffff; --card-foreground: #0a0a0a; --muted: #f8f5f1; --muted-foreground: #71717a; --border: #e5e5e5; --primary: #0a0a0a; --primary-foreground: #fafafa; --secondary: #f5f5f5; --secondary-foreground: #0a0a0a; --accent: #f5f5f5; --accent-foreground: #0a0a0a; --success: #22c55e; --warning: #f59e0b; --danger: #ef4444; --info: #3b82f6; --sidebar: #ffffff; --sidebar-foreground: #0a0a0a; --sidebar-border: #e5e5e5; }
        .dark { --background: #0a0a0a; --foreground: #fafafa; --card: #0a0a0a; --card-foreground: #fafafa; --muted: #262626; --muted-foreground: #a3a3a3; --border: #262626; --primary: #fafafa; --primary-foreground: #0a0a0a; --secondary: #262626; --secondary-foreground: #fafafa; --accent: #262626; --accent-foreground: #fafafa; --sidebar: #0a0a0a; --sidebar-foreground: #fafafa; --sidebar-border: #262626; }
        @font-face { font-family: 'Geist Sans'; src: url('../node_modules/geist/dist/fonts/geist-sans/Geist-Variable.woff2') format('woff2'); font-weight: 100 900; font-style: normal; }
        body { font-family: 'Geist Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background: var(--background); color: var(--foreground); display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background: var(--sidebar); border-right: 1px solid var(--sidebar-border); padding: 24px 16px; position: fixed; height: 100vh; overflow-y: auto; transition: all 0.3s ease; z-index: 1000; }
        .sidebar-brand { display: flex; align-items: center; gap: 12px; padding: 8px 12px; margin-bottom: 32px; font-size: 18px; font-weight: 700; color: var(--sidebar-foreground); }
        .sidebar-brand img { height: 32px; width: auto; }
        .sidebar-menu { list-style: none; }
        .sidebar-menu li { margin-bottom: 4px; }
        .sidebar-menu a { display: flex; align-items: center; gap: 12px; padding: 10px 12px; color: var(--muted-foreground); text-decoration: none; border-radius: 12px; transition: all 0.2s; font-size: 14px; font-weight: 500; }
        .sidebar-menu a:hover { background: var(--accent); color: var(--accent-foreground); }
        .sidebar-menu a.active { background: var(--primary); color: var(--primary-foreground); }
        .sidebar-menu svg { width: 20px; height: 20px; flex-shrink: 0; }
        .sidebar-divider { margin: 24px 0; padding-top: 24px; border-top: 1px solid var(--border); }
        .main-content { margin-left: 260px; flex: 1; display: flex; flex-direction: column; }
        .top-nav { height: 64px; border-bottom: 1px solid var(--border); padding: 0 32px; display: flex; align-items: center; justify-content: space-between; background: var(--background); position: sticky; top: 0; z-index: 10; }
        .top-nav-left { display: flex; align-items: center; gap: 16px; }
        .mobile-menu-btn { display: none; padding: 8px; background: transparent; border: none; color: var(--foreground); cursor: pointer; border-radius: 8px; }
        .mobile-menu-btn:hover { background: var(--muted); }
        .mobile-menu-btn svg { width: 20px; height: 20px; }
        .top-nav-actions { display: flex; align-items: center; gap: 12px; }
        .theme-toggle { padding: 8px; border-radius: 8px; border: none; background: transparent; cursor: pointer; color: var(--foreground); }
        .theme-toggle:hover { background: var(--muted); }
        .theme-toggle svg { width: 20px; height: 20px; }
        .top-nav h1 { font-size: 20px; font-weight: 600; }
        .content { flex: 1; padding: 32px; overflow-y: auto; }
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 20px; padding: 24px; box-shadow: 0 4px 10px rgba(0,0,0,0.03); margin-bottom: 24px; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid var(--border); }
        .card-title { font-size: 18px; font-weight: 600; }
        .btn-add { padding: 8px 16px; background: var(--primary); color: var(--primary-foreground); border: none; border-radius: 8px; cursor: pointer; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        .table { width: 100%; border-collapse: collapse; }
        .table thead th { text-align: left; padding: 12px; font-size: 12px; font-weight: 600; color: var(--muted-foreground); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--border); }
        .table tbody td { padding: 16px 12px; border-bottom: 1px solid var(--border); font-size: 14px; }
        .table tbody tr:hover { background: var(--muted); }
        .badge { display: inline-flex; align-items: center; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; text-transform: uppercase; }
        .badge.active, .badge.available { background: rgba(34, 197, 94, 0.1); color: var(--success); }
        .badge.inactive, .badge.not_available, .badge.maintenance { background: rgba(115, 115, 115, 0.1); color: var(--muted-foreground); }
        .action-buttons { display: flex; gap: 6px; flex-wrap: wrap; }
        .btn { padding: 8px 16px; border: 1px solid transparent; border-radius: 6px; cursor: pointer; font-weight: 500; font-size: 13px; text-decoration: none; display: inline-block; }
        .btn-primary { background: var(--primary); color: var(--primary-foreground); }
        .btn-secondary { background: var(--secondary); color: var(--secondary-foreground); border-color: var(--border); }
        .btn-danger { background: var(--danger); color: white; }
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 24px; border: 1px solid transparent; }
        .alert.alert-success { background: rgba(34, 197, 94, 0.1); color: var(--success); border-color: rgba(34, 197, 94, 0.2); }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); backdrop-filter: blur(4px); }
        .modal-content { background: var(--card); margin: 5% auto; padding: 0; border-radius: 20px; max-width: 600px; width: 90%; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border: 1px solid var(--border); overflow: hidden; }
        .modal-header { padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { font-size: 18px; font-weight: 600; }
        .modal-header button { background: none; border: none; cursor: pointer; font-size: 24px; color: var(--muted-foreground); }
        .modal-body { padding: 24px; }
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; margin-bottom: 8px; font-weight: 600; color: var(--foreground); }
        .form-input, .form-select { width: 100%; padding: 10px 12px; border: 1px solid var(--border); border-radius: 8px; background: var(--background); color: var(--foreground); font-size: 14px; }
        .form-input:focus, .form-select:focus { outline: none; border-color: var(--primary); }
        .form-actions { display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px; }
        .checkbox-group { display: flex; align-items: center; gap: 8px; }

        /* Sidebar overlay for mobile */
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.5); z-index: 999; }
        .sidebar-overlay.active { display: block; }
        body.sidebar-open { overflow: hidden; }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); width: 280px; }
            .sidebar.mobile-open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .content { padding: 16px; }
            .top-nav { padding: 0 16px; height: 60px; }
            .top-nav h1 { font-size: 18px; }
            .mobile-menu-btn { display: block; }
            .card-header { flex-direction: column; align-items: flex-start; gap: 12px; }
            .card-header div { width: 100%; }
            .table { display: block; overflow-x: auto; white-space: nowrap; }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <!-- Mobile Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="main-content">
        <nav class="top-nav">
            <div class="top-nav-left">
                <button class="mobile-menu-btn" onclick="toggleSidebar()"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg></button>
                <h1>Vehicle Management</h1>
            </div>
            <div class="top-nav-actions">
                <button class="theme-toggle" onclick="toggleTheme()"><svg class="sun-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg><svg class="moon-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg></button>
            </div>
        </nav>
        <main class="content">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">All Vehicles</h2>
                    <div style="display: flex; gap: 12px; align-items: center;">
                        <form method="GET" style="display: contents;">
                            <input type="text" name="search" class="form-input" placeholder="Search vehicles..." value="<?php echo htmlspecialchars($search); ?>" oninput="this.form.submit()">
                        </form>
                        <button class="btn-add" onclick="openAddModal()">Add Vehicle</button>
                    </div>
                </div>
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
                <?php endif; ?>
                <table class="table">
                    <thead><tr><th>Name</th><th>Type</th><th>Capacity</th><th>Driver</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php while ($vehicle = $vehicles->fetch_assoc()): ?>
                            <tr style="<?php echo !$vehicle['is_active'] ? 'opacity: 0.5;' : ''; ?>">
                                <td><strong><?php echo htmlspecialchars($vehicle['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($vehicle['type']); ?></td>
                                <td><?php echo htmlspecialchars($vehicle['capacity']); ?></td>
                                <td><?php echo htmlspecialchars($vehicle['driver_name'] ?: 'N/A'); ?></td>
                                <td>
                                    <span class="badge <?php echo $vehicle['is_active'] ? 'active' : 'inactive'; ?>"><?php echo $vehicle['is_active'] ? 'Active' : 'Inactive'; ?></span>
                                    <span class="badge <?php echo $vehicle['availability']; ?>"><?php echo str_replace('_', ' ', $vehicle['availability']); ?></span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-secondary" onclick='openEditModal(<?php echo json_encode($vehicle); ?>)'>Edit</button>
                                        <?php if ($vehicle['is_active']): ?>
                                            <form method="POST" onsubmit="return confirm('Deactivate this vehicle?');" style="display:inline;"><input type="hidden" name="id" value="<?php echo $vehicle['id']; ?>"><button type="submit" name="delete_vehicle" class="btn btn-danger">Deactivate</button></form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Add/Edit Modals -->
    <div id="vehicleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h3 id="modalTitle">Add Vehicle</h3><button onclick="closeModal()">&times;</button></div>
            <form method="POST" class="modal-body">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group"><label class="form-label">Name</label><input type="text" name="name" id="edit_name" class="form-input" required></div>
                <div class="form-group"><label class="form-label">Type</label><input type="text" name="type" id="edit_type" class="form-input" placeholder="e.g., Van, SUV, Sedan" required></div>
                <div class="form-group"><label class="form-label">Capacity</label><input type="number" name="capacity" id="edit_capacity" class="form-input" required></div>
                <div class="form-group"><label class="form-label">Driver Name</label><input type="text" name="driver_name" id="edit_driver_name" class="form-input"></div>
                <div class="form-group"><label class="form-label">Driver Contact</label><input type="text" name="driver_contact" id="edit_driver_contact" class="form-input"></div>
                <div class="form-group"><label class="form-label">Amenities (comma-separated)</label><input type="text" name="amenities" id="edit_amenities" class="form-input"></div>
                <div id="edit_only_fields" style="display:none;">
                    <div class="form-group"><label class="form-label">Availability</label><select name="availability" id="edit_availability" class="form-select"><option value="available">Available</option><option value="not_available">Not Available</option><option value="maintenance">Maintenance</option></select></div>
                    <div class="form-group checkbox-group"><input type="checkbox" name="is_active" id="edit_is_active" value="1"><label for="edit_is_active">Vehicle is Active</label></div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" id="modalSubmitButton" name="add_vehicle" class="btn btn-primary">Add Vehicle</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('vehicleModal');
        function openAddModal() {
            document.getElementById('modalTitle').innerText = 'Add New Vehicle';
            document.querySelector('#vehicleModal form').reset();
            document.getElementById('edit_id').value = '';
            document.getElementById('edit_only_fields').style.display = 'none';
            const submitBtn = document.getElementById('modalSubmitButton');
            submitBtn.name = 'add_vehicle';
            submitBtn.innerText = 'Add Vehicle';
            modal.style.display = 'block';
        }
        function openEditModal(vehicle) {
            document.getElementById('modalTitle').innerText = 'Edit Vehicle';
            document.querySelector('#vehicleModal form').reset();
            document.getElementById('edit_id').value = vehicle.id;
            document.getElementById('edit_name').value = vehicle.name;
            document.getElementById('edit_type').value = vehicle.type;
            document.getElementById('edit_capacity').value = vehicle.capacity;
            document.getElementById('edit_driver_name').value = vehicle.driver_name;
            document.getElementById('edit_driver_contact').value = vehicle.driver_contact;
            document.getElementById('edit_amenities').value = JSON.parse(vehicle.amenities || '[]').join(', ');
            document.getElementById('edit_availability').value = vehicle.availability;
            document.getElementById('edit_is_active').checked = vehicle.is_active == 1;
            document.getElementById('edit_only_fields').style.display = 'block';
            const submitBtn = document.getElementById('modalSubmitButton');
            submitBtn.name = 'edit_vehicle';
            submitBtn.innerText = 'Save Changes';
            modal.style.display = 'block';
        }
        function closeModal() { modal.style.display = 'none'; }
        window.onclick = function(event) { if (event.target == modal) closeModal(); }
    </script>
    <script>
        // This script block is for theme toggling and mobile sidebar, matching other admin pages.
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

        document.addEventListener('DOMContentLoaded', function() {
            const theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
            updateThemeIcon(theme);

            // Close sidebar when clicking overlay
            document.getElementById('sidebarOverlay').addEventListener('click', function() {
                toggleSidebar();
            });

            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(event) {
                const sidebar = document.querySelector('.sidebar');
                const mobileBtn = document.querySelector('.mobile-menu-btn');
                if (window.innerWidth <= 768 && !sidebar.contains(event.target) && !mobileBtn.contains(event.target) && sidebar.classList.contains('mobile-open')) {
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
        });
    </script>
</body>
</html>