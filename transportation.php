<?php
session_start();
require_once 'functions.php';
require_once 'theme_loader.php';
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Get user details
$user_id = $_SESSION['user_id'];
$sql = "SELECT department, program FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Use the theme
$logo_file = $GLOBALS['logo_file'];
$portal_name = $GLOBALS['portal_name'];

// Get vehicles from database
$vehicles = [];
$all_amenities = [];
$sql = "SELECT * FROM transportation_vehicles WHERE is_active = TRUE ORDER BY name";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $vehicles[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'type' => $row['type'],
            'capacity' => $row['capacity'],
            'driver_name' => $row['driver_name'],
            'driver_contact' => $row['driver_contact'],
            'availability' => $row['availability'],
            'amenities' => json_decode($row['amenities'], true) ?: [],
            'image' => $row['image_path'] ?: 'vehicles/default.jpg'
        ];
        $all_amenities = array_merge($all_amenities, $vehicles[count($vehicles) - 1]['amenities']);
    }
}
$all_amenities = array_unique($all_amenities);
sort($all_amenities);

?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($theme); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transportation Services - MCNP Service Portal</title>
    <!-- <link rel="stylesheet" href="css/facilities_styles.css"> --> <!-- Using inline styles for consistency -->
    <style>
        /* General Styles from facilities.php */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        @font-face {
            font-family: 'Geist Sans';
            src: url('node_modules/geist/dist/fonts/geist-sans/Geist-Variable.woff2') format('woff2');
            font-weight: 100 900;
            font-style: normal;
        }

        body {
            font-family: 'Geist Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: var(--text-primary);
            background: var(--bg-secondary);
        }
        
        :root {
            --bg-primary: #ffffff; /* Card and Header background */
            --bg-secondary: #fdfaf6; /* Main page background */
            --text-primary: #1a1a1a;
            --text-secondary: #71717a;
            --border-color: #e5e7eb;
            --accent-color: #6366f1;
        }

        [data-theme="dark"] {
            --bg-primary: #171717; /* Card and Header background */
            --bg-secondary: #0a0a0a; /* Main page background */
            --text-primary: #ffffff;
            --text-secondary: #9ca3af;
            --border-color: #404040;
            --accent-color: #818cf8;
        }

        [data-theme="blue"] {
            --bg-primary: #ffffff;
            --bg-secondary: #f0f9ff;
            --text-primary: #0c4a6e;
            --text-secondary: #38bdf8;
            --border-color: #e0f2fe;
            --accent-color: #0ea5e9;
        }

        [data-theme="pink"] {
            --bg-primary: #ffffff;
            --bg-secondary: #fdf2f8;
            --text-primary: #831843;
            --text-secondary: #f472b6;
            --border-color: #fce7f3;
            --accent-color: #ec4899;
        }

        [data-theme="green"] {
            --bg-primary: #ffffff;
            --bg-secondary: #f0fdf4;
            --text-primary: #14532d;
            --text-secondary: #4ade80;
            --border-color: #dcfce7;
            --accent-color: #22c55e;
        }

        [data-theme="purple"] {
            --bg-primary: #ffffff;
            --bg-secondary: #f5f3ff;
            --text-primary: #4c1d95;
            --text-secondary: #a78bfa;
            --border-color: #ede9fe;
            --accent-color: #8b5cf6;
        }

        .header {
            background: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: var(--bg-primary);
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
            background: white;
            color: #1a1a1a;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            background-color: var(--bg-primary);
            border-color: var(--border-color);
            color: var(--text-primary);
            font-size: 14px;
        }
        
        .btn-back:hover {
            background: var(--bg-secondary);
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 24px 16px;
        }
        
        .page-header {
            margin-bottom: 24px;
        }
        
        .page-header h1 {
            font-size: 28px;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        
        .page-header p {
            color: var(--text-secondary);
            font-size: 15px;
        }

        .controls-container {
            background: var(--bg-primary);
            padding: 16px;
            border-radius: 16px;
            border: 1px solid var(--border-color);
            margin-bottom: 24px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .search-container { position: relative; flex-grow: 1; }
        #vehicleSearch, #facilitySearch {
            width: 100%;
            padding: 14px 20px 14px 48px;
            font-size: 16px;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            background-color: var(--bg-primary);
            color: var(--text-primary);
            transition: all 0.3s;
        }

        #vehicleSearch:focus, #facilitySearch:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(0,0,0,0.05);
        }

        .search-container svg {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
        }

        .filter-group { display: flex; gap: 12px; flex-wrap: wrap; }

        .filter-select {
            padding: 14px 16px;
            font-size: 15px;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            background-color: var(--bg-primary);
            color: var(--text-primary);
            cursor: pointer;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.7rem center;
            background-repeat: no-repeat;
            background-size: 1.2em;
            padding-right: 2.5rem;
        }
        .filter-select:focus {
            outline: none;
            border-color: var(--accent-color);
        }

        .facilities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .facility-card {
            background: var(--bg-primary);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0,0,0,0.03);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            border: 1px solid var(--border-color); 
            animation: fadeInUp 0.5s ease-out forwards;
            opacity: 0;
        }

        .facility-card.hidden {
            opacity: 0;
            transform: scale(0.95);
            height: 0;
            padding: 0;
            margin: 0;
            overflow: hidden;
        }
        
        .facility-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.07);
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .facility-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .facility-card:hover .facility-image { transform: scale(1.05); }
        
        .facility-content { padding: 20px; }
        .facility-content h3 { font-size: 18px; color: var(--text-primary); margin-bottom: 8px; }
        .facility-capacity { display: inline-block; padding: 6px 12px; background: var(--bg-secondary); border-radius: 12px; font-size: 12px; color: var(--text-secondary); margin-bottom: 12px; }
        .facility-description { color: var(--text-secondary); font-size: 14px; line-height: 1.6; margin-bottom: 16px; }
        .facility-amenities { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; }
        
        .amenity-tag {
            padding: 6px 12px;
            background: #e0f2fe;
            color: #075985;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
        }
        
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(4px); animation: fadeIn 0.3s ease forwards; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .modal-content { background: var(--bg-primary); margin: 8% auto; padding: 0; border-radius: 20px; max-width: 700px; border: 1px solid var(--border-color); width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.3); animation: slideUp 0.3s ease; overflow: hidden; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        .modal-image { width: 100%; height: 300px; object-fit: cover; }
        .modal-body { padding: 24px; }
        .modal-header { display: flex; justify-content: flex-end; align-items: flex-start; margin-bottom: 16px; }
        .modal-header h2 { font-size: 24px; color: var(--text-primary); margin: 0 auto 0 0; }
        .close-modal { background: var(--bg-secondary); border: none; width: 36px; height: 36px; color: var(--text-secondary); border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
        .close-modal:hover { background: var(--border-color); color: var(--text-primary); transform: rotate(90deg); }
        .modal-capacity { display: inline-block; padding: 6px 12px; background: var(--bg-secondary); border-radius: 999px; font-size: 14px; color: var(--text-secondary); margin-bottom: 16px; }
        .modal-description { color: var(--text-secondary); font-size: 16px; line-height: 1.7; margin-bottom: 24px; }
        .modal-amenities { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 24px; }
        .modal-amenity-tag { padding: 8px 14px; background: #e0f2fe; color: #075985; border-radius: 12px; font-size: 13px; font-weight: 500; }
        .modal-book-btn { width: 100%; padding: 14px; background: var(--text-primary); color: var(--bg-primary); border: none; border-radius: 12px; font-weight: 600; font-size: 16px; cursor: pointer; transition: all 0.2s; min-height: 48px; }
        .modal-book-btn:hover { opacity: 0.8; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.2); }

        /* Copy all the CSS from facilities.php and modify as needed */
        /* I'll include the key differences only to save space */
        
        .availability-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 8px;
        }
        
        .availability-badge.available {
            background: #d1fae5;
            color: #065f46;
        }
        
        .availability-badge.not-available {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .availability-badge.maintenance {
            background: #fef3c7;
            color: #92400e;
        }
        
        .driver-info {
            background: var(--bg-secondary);
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 12px;
            border-left: 4px solid var(--accent-color);
        }
        
        .driver-info h4 {
            font-size: 14px;
            margin-bottom: 4px;
            color: var(--text-primary);
        }
        
        .driver-info p {
            font-size: 12px;
            color: var(--text-secondary);
            margin: 0;
        }
        
        /* Animation for availability options */
        .availability-options {
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .availability-options.hidden {
            height: 0;
            opacity: 0;
            margin: 0;
            padding: 0;
        }
        
        .availability-options.visible {
            height: auto;
            opacity: 1;
            margin-bottom: 16px;
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
                    <div class="brand-subtitle">Transportation Services</div>
                </div>
            </div>
        </a>
        <a href="dashboard.php" class="btn-back">Back to Dashboard</a>
    </header>

    <div class="container">
        <div class="page-header">
            <h1>Available Vehicles</h1>
            <p>Request transportation for your official trips and activities.</p>
        </div>

        <div class="controls-container">
            <div class="search-container">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                <input type="text" id="vehicleSearch" placeholder="Search by vehicle name or type...">
            </div>
            <div class="filter-group">
                <select id="capacityFilter" class="filter-select">
                    <option value="any">Any Capacity</option>
                    <option value="1-4">1-4 People</option>
                    <option value="5-8">5-8 People</option>
                    <option value="9-15">9-15 People</option>
                    <option value="16+">16+ People</option>
                </select>
                <select id="typeFilter" class="filter-select">
                    <option value="any">All Types</option>
                    <option value="Van">Van</option>
                    <option value="SUV">SUV</option>
                    <option value="Sedan">Sedan</option>
                    <option value="MPV">MPV</option>
                    <option value="Light Truck">Light Truck</option>
                </select>
                <select id="amenityFilter" class="filter-select">
                    <option value="any">All Amenities</option>
                    <?php foreach ($all_amenities as $amenity): ?>
                        <option value="<?php echo htmlspecialchars($amenity); ?>"><?php echo htmlspecialchars($amenity); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="facilities-grid" id="vehicles-grid">
            <?php if (isset($vehicles) && is_array($vehicles) && count($vehicles) > 0): ?>
                <?php foreach ($vehicles as $index => $vehicle): ?>
                    <div class="facility-card" data-index="<?php echo $vehicle['id']; ?>" 
                         data-name="<?php echo htmlspecialchars(strtolower($vehicle['name'])); ?>" 
                         data-capacity="<?php echo htmlspecialchars($vehicle['capacity']); ?>"
                         data-type="<?php echo htmlspecialchars(strtolower($vehicle['type'])); ?>"
                         data-availability="<?php echo htmlspecialchars($vehicle['availability']); ?>"
                         style="animation-delay: <?php echo $index * 0.05; ?>s" 
                         onclick="openModal(<?php echo $vehicle['id']; ?>)">
                        
                        <div class="facility-content" style="padding-top: 20px;">
                            <h3>
                                <?php echo htmlspecialchars($vehicle['name']); ?>
                                <span class="availability-badge <?php echo $vehicle['availability']; ?>">
                                    <?php echo ucfirst($vehicle['availability']); ?>
                                </span>
                            </h3>
                            <span class="facility-capacity">
                                <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: inline-block; vertical-align: middle; margin-right: 4px;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                                <?php echo htmlspecialchars($vehicle['capacity']); ?> People • <?php echo htmlspecialchars($vehicle['type']); ?>
                            </span>
                            
                            <?php if ($vehicle['driver_name']): ?>
                                <div class="driver-info">
                                    <h4>Driver: <?php echo htmlspecialchars($vehicle['driver_name']); ?></h4>
                                    <p>Contact: <?php echo htmlspecialchars($vehicle['driver_contact']); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <div class="facility-amenities">
                                <?php if (!empty($vehicle['amenities'])): ?>
                                    <?php foreach (array_slice($vehicle['amenities'], 0, 3) as $amenity): ?>
                                        <span class="amenity-tag"><?php echo htmlspecialchars($amenity); ?></span>
                                    <?php endforeach; ?>
                                    <?php if (count($vehicle['amenities']) > 3): ?>
                                        <span class="amenity-tag">+<?php echo count($vehicle['amenities']) - 3; ?> more</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-facilities">
                    <p>No vehicles available at the moment.</p>
                </div>
            <?php endif; ?>
        </div>

        <div id="noResultsMessage" style="display: none; text-align: center; padding: 60px 20px; color: var(--text-secondary);">
            <p style="font-size: 18px; font-weight: 500;">No vehicles found</p>
            <p style="font-size: 14px;">Try adjusting your search terms.</p>
        </div>
    </div>

    <!-- Vehicle Modal -->
    <div id="vehicleModal" class="modal">
        <div class="modal-content">
            <div class="modal-body">
                <div class="modal-header">
                    <h2 id="modalTitle"></h2>
                    <button class="close-modal" onclick="closeModal()">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <span id="modalCapacity" class="modal-capacity"></span>
                
                <div id="modalDriverInfo" class="driver-info"></div>
                
                <div id="modalAmenities" class="modal-amenities"></div>
                
                <button class="modal-book-btn" onclick="requestVehicle()">Request This Vehicle</button>
            </div>
        </div>
    </div>

    <script>
        const allVehicles = <?php echo json_encode($vehicles); ?>;
        let selectedVehicleId = null;
        
        function openModal(vehicleId) {
            const vehicle = allVehicles.find(v => v.id == vehicleId);
            selectedVehicleId = vehicleId;

            if (!vehicle) {
                console.error("Vehicle not found for ID:", vehicleId);
                return;
            }

            const modal = document.getElementById('vehicleModal');
            document.getElementById('modalTitle').textContent = vehicle.name;
            document.getElementById('modalCapacity').innerHTML = `
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: inline-block; vertical-align: middle; margin-right: 4px;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
                ${vehicle.capacity} People • ${vehicle.type}
                <span class="availability-badge ${vehicle.availability}">
                    ${vehicle.availability.charAt(0).toUpperCase() + vehicle.availability.slice(1)}
                </span>
            `;
            
            // Driver info
            const driverInfo = document.getElementById('modalDriverInfo');
            if (vehicle.driver_name) {
                driverInfo.innerHTML = `
                    <h4>Assigned Driver: ${vehicle.driver_name}</h4>
                    <p>Contact: ${vehicle.driver_contact}</p>
                `;
                driverInfo.style.display = 'block';
            } else {
                driverInfo.style.display = 'none';
            }
            
            // Amenities
            const amenitiesContainer = document.getElementById('modalAmenities');
            amenitiesContainer.innerHTML = '';
            vehicle.amenities.forEach(amenity => {
                const tag = document.createElement('span');
                tag.className = 'modal-amenity-tag';
                tag.textContent = amenity;
                amenitiesContainer.appendChild(tag);
            });
            
            modal.style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('vehicleModal').style.display = 'none';
            selectedVehicleId = null;
        }
        
        function requestVehicle() {
            if (selectedVehicleId) {
                window.location.href = `create_transport_request.php?vehicle_id=${selectedVehicleId}`;
            }
        }
        
        // Filtering logic (similar to facilities but with vehicle-specific filters)
        const searchInput = document.getElementById('vehicleSearch');
        const capacityFilter = document.getElementById('capacityFilter');
        const typeFilter = document.getElementById('typeFilter');
        const amenityFilter = document.getElementById('amenityFilter');
        const noResultsMessage = document.getElementById('noResultsMessage');
        const vehiclesGrid = document.getElementById('vehicles-grid');

        function filterVehicles() {
            const searchTerm = searchInput.value.toLowerCase().trim();
            const selectedCapacity = capacityFilter.value; 
            const selectedType = typeFilter.value;
            const selectedAmenity = amenityFilter.value;
            let visibleCount = 0;

            const allCards = document.querySelectorAll('.facility-card');

            allCards.forEach(card => {
                const vehicleId = card.dataset.index;
                const vehicle = allVehicles.find(v => v.id == vehicleId);

                if (!vehicle) return;

                // Search term match
                const name = vehicle.name.toLowerCase();
                const type = vehicle.type.toLowerCase();
                const searchMatch = name.includes(searchTerm) || type.includes(searchTerm);

                // Capacity match
                let capacityMatch = true;
                if (selectedCapacity !== 'any') {
                    const capacity = parseInt(vehicle.capacity, 10);
                    if (selectedCapacity === '1-4') {
                        capacityMatch = capacity >= 1 && capacity <= 4;
                    } else if (selectedCapacity === '5-8') {
                        capacityMatch = capacity >= 5 && capacity <= 8;
                    } else if (selectedCapacity === '9-15') {
                        capacityMatch = capacity >= 9 && capacity <= 15;
                    } else if (selectedCapacity === '16+') {
                        capacityMatch = capacity >= 16;
                    }
                }

                // Type match
                let typeMatch = true;
                if (selectedType !== 'any') {
                    typeMatch = vehicle.type === selectedType;
                }

                // Amenity match
                let amenityMatch = true;
                if (selectedAmenity !== 'any') {
                    amenityMatch = vehicle.amenities.includes(selectedAmenity);
                }
                
                if (searchMatch && capacityMatch && typeMatch && amenityMatch) {
                    card.classList.remove('hidden');
                    card.style.display = 'block';
                    visibleCount++;
                } else {
                    card.classList.add('hidden');
                    card.style.display = 'none';
                }
            });

            noResultsMessage.style.display = visibleCount === 0 ? 'block' : 'none';
        }

        searchInput.addEventListener('input', filterVehicles);
        capacityFilter.addEventListener('change', filterVehicles);
        typeFilter.addEventListener('change', filterVehicles);
        amenityFilter.addEventListener('change', filterVehicles);

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('vehicleModal');
            if (event.target === modal) {
                closeModal();
            }
        }
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</body>
</html>