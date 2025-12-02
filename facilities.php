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

// Get user details for recommendations
$user_id = $_SESSION['user_id'];
$sql = "SELECT department, program FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Use the theme
$logo_file = $GLOBALS['logo_file'];
$portal_name = $GLOBALS['portal_name'];

// Get facilities from database
$facilities = [];
$all_amenities = [];
$sql = "SELECT * FROM facilities WHERE is_active = TRUE ORDER BY name";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $facilities[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'capacity' => $row['capacity'],
            'description' => $row['description'],
            'amenities' => json_decode($row['amenities'], true) ?: [],
            'image' => $row['image_path']
        ];
        $all_amenities = array_merge($all_amenities, $facilities[count($facilities) - 1]['amenities']);
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
    <title>Browse Facilities - MCNP Service Portal</title>
    <style>
        /* General Styles */
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

        /* New Theme Palettes */
        [data-theme="blue"] {
            --bg-primary: #ffffff;
            --bg-secondary: #f0f9ff; /* sky-50 */
            --text-primary: #0c4a6e; /* sky-900 */
            --text-secondary: #38bdf8; /* sky-400 */
            --border-color: #e0f2fe; /* sky-100 */
            --accent-color: #0ea5e9; /* sky-500 */
        }

        [data-theme="pink"] {
            --bg-primary: #ffffff;
            --bg-secondary: #fdf2f8; /* pink-50 */
            --text-primary: #831843; /* pink-900 */
            --text-secondary: #f472b6; /* pink-400 */
            --border-color: #fce7f3; /* pink-100 */
            --accent-color: #ec4899; /* pink-500 */
        }

        [data-theme="green"] {
            --bg-primary: #ffffff;
            --bg-secondary: #f0fdf4; /* green-50 */
            --text-primary: #14532d; /* green-900 */
            --text-secondary: #4ade80; /* green-400 */
            --border-color: #dcfce7; /* green-100 */
            --accent-color: #22c55e; /* green-500 */
        }

        [data-theme="purple"] {
            --bg-primary: #ffffff;
            --bg-secondary: #f5f3ff; /* violet-50 */
            --text-primary: #4c1d95; /* violet-900 */
            --text-secondary: #a78bfa; /* violet-400 */
            --border-color: #ede9fe; /* violet-100 */
            --accent-color: #8b5cf6; /* violet-500 */
        }
        /* Header */
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

        /* Search and Filters */
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
        #facilitySearch {
            width: 100%;
            padding: 14px 20px 14px 48px;
            font-size: 16px;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            background-color: var(--bg-primary);
            color: var(--text-primary);
            transition: all 0.3s;
        }

        #facilitySearch:focus {
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

        /* Section Headers */
        .section-title {
            font-size: 22px;
            font-weight: 600;
            color: var(--text-primary);
            margin-top: 40px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-color);
        }

        /* Facilities Grid */
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
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .facility-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .facility-card:hover .facility-image {
            transform: scale(1.05);
        }
        
        .facility-content {
            padding: 20px;
        }
        
        .facility-content h3 {
            font-size: 18px;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        
        .facility-capacity {
            display: inline-block;
            padding: 6px 12px;
            background: var(--bg-secondary);
            border-radius: 12px;
            font-size: 12px;
            color: var(--text-secondary);
            margin-bottom: 12px;
        }
        
        .facility-description {
            color: var(--text-secondary);
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 16px;
        }
        
        .facility-amenities {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 16px;
        }
        
        .amenity-tag {
            padding: 6px 12px;
            background: #e0f2fe; /* sky-100 */
            color: #075985; /* sky-800 */
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
        }
        
        .btn-book-facility {
            width: 100%;
            padding: 12px;
            background: var(--text-primary);
            color: var(--bg-primary);
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: block;
            min-height: 44px;
            text-align: center;
            transition: all 0.2s;
        }
        
        .btn-book-facility:hover {
            opacity: 0.8;
            transform: translateY(-2px);
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(4px);
            animation: fadeIn 0.3s ease forwards;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .modal-content {
            background: var(--bg-primary);
            margin: 8% auto;
            padding: 0;
            border-radius: 20px;
            max-width: 700px;
            border: 1px solid var(--border-color);
            width: 90%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideUp 0.3s ease;
            overflow: hidden;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .modal-image {
            width: 100%;
            height: 300px;
            object-fit: cover;
        }
        
        .modal-body {
            padding: 24px;
        }
        
        .modal-header {
            display: flex;
            justify-content: flex-end;
            align-items: flex-start;
            margin-bottom: 16px;
        }
        
        .modal-header h2 {
            font-size: 24px;
            color: var(--text-primary);
            margin: 0 auto 0 0; /* Center title, push close button right */
        }
        
        .close-modal {
            background: var(--bg-secondary);
            border: none;
            width: 36px;
            height: 36px;
            color: var(--text-secondary);
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        
        .close-modal:hover {
            background: var(--border-color);
            color: var(--text-primary);
            transform: rotate(90deg);
        }
        
        .modal-capacity {
            display: inline-block;
            padding: 6px 12px;
            background: var(--bg-secondary);
            border-radius: 999px; /* Pill shape */
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 16px;
        }
        
        .modal-description {
            color: var(--text-secondary);
            font-size: 16px;
            line-height: 1.7;
            margin-bottom: 24px;
        }
        
        .modal-amenities {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 24px;
        }
        
        .modal-amenity-tag {
            padding: 8px 14px;
            background: #e0f2fe; /* sky-100 */
            color: #075985; /* sky-800 */
            border-radius: 12px;
            font-size: 13px;
            font-weight: 500;
        }
        
        .modal-book-btn {
            width: 100%;
            padding: 14px;
            background: var(--text-primary);
            color: var(--bg-primary);
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.2s;
            min-height: 48px;
        }
        
        .modal-book-btn:hover {
            opacity: 0.8;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        
        /* Mobile Responsive Styles */
        @media (max-width: 768px) {
            .header {
                position: sticky;
                top: 0;
                z-index: 10;
            }
            .controls-container {
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
            
            .page-header h1 {
                font-size: 24px;
            }
            
            .page-header p {
                font-size: 14px;
            }
            
            .facilities-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }
            
            .facility-card {
                border-radius: 18px;
            }
            
            .facility-image {
                height: 120px;
            }
            
            .facility-content {
                padding: 12px;
            }
            
            .facility-content h3 {
                font-size: 14px;
                margin-bottom: 6px;
            }
            
            .facility-capacity {
                font-size: 10px;
                padding: 4px 10px;
                margin-bottom: 8px;
            }
            
            .facility-description {
                font-size: 11px;
                line-height: 1.4;
                margin-bottom: 10px;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }
            
            .facility-amenities {
                gap: 4px;
                margin-bottom: 10px;
            }
            
            .amenity-tag {
                font-size: 10px;
                padding: 2px 6px;
            }
            
            .btn-book-facility {
                padding: 8px;
                font-size: 12px;
                border-radius: 4px;
            }
            
            .modal-content {
                width: 95%;
                margin: 10% auto;
            }
            
            .modal-image {
                height: 200px;
            }
            
            .modal-body {
                padding: 20px;
            }
            
            .modal-header h2 {
                font-size: 20px;
            }
            
            .modal-description {
                font-size: 14px;
            }
        }
        
        @media (max-width: 480px) {
            .header-brand {
                gap: 8px;
            }
            
            .header-brand img {
                height: 32px;
            }
            
            .btn-back {
                padding: 6px 10px;
                font-size: 12px;
            }
            
            .page-header h1 {
                font-size: 22px;
            }
            
            .facilities-grid {
                grid-template-columns: repeat(2, 1fr); /* Changed to 2 columns for mobile */
                gap: 16px;
            }
            
            .facility-image {
                height: 140px;
            }
            
            .facility-content h3 {
                font-size: 14px; /* Adjusted for smaller cards */
            }
            
            .facility-description {
                font-size: 12px; /* Adjusted for smaller cards */
                -webkit-line-clamp: 3;
            }

            .facilities-grid {
                grid-template-columns: 1fr; /* Use a single column on small phones */
                gap: 16px;
            }
            
            .modal-content {
                width: 98%;
                margin: 5% auto;
            }
            
            .modal-image {
                height: 180px;
            }
            
            .modal-body {
                padding: 16px;
            }
            
            .modal-header h2 {
                font-size: 20px;
            }
            
            .modal-description {
                font-size: 14px;
            }
            
            .modal-amenity-tag {
                font-size: 12px;
                padding: 6px 10px;
            }

            .controls-container {
                padding: 12px;
            }

            .filter-group {
                flex-direction: column; /* Stack filters vertically */
                width: 100%;
            }

            .filter-select {
                width: 100%; /* Make selects full-width */
            }
        }

        @media (min-width: 481px) and (max-width: 768px) {
            .facilities-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .facility-image {
                height: 150px;
            }
        }

        @media (min-width: 769px) and (max-width: 1024px) {
            .facilities-grid {
                grid-template-columns: repeat(3, 1fr);
            }
            
            .facility-image { 
                height: 180px;
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
                    <div class="brand-subtitle">Browse Facilities</div>
                </div>
            </div>
        </a>
        <a href="dashboard.php" class="btn-back">Back</a>
    </header>

    <div class="container">
        <div class="page-header">
            <h1>Available Facilities</h1>
            <p>Find the perfect space for your events and activities.</p>
        </div>

        <div class="controls-container">
            <div class="search-container">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                <input type="text" id="facilitySearch" placeholder="Search by name or description...">
            </div>
            <div class="filter-group">
                <select id="capacityFilter" class="filter-select">
                    <option value="any">Any Capacity</option>
                    <option value="1-50">1-50 People</option>
                    <option value="51-100">51-100 People</option>
                    <option value="101-200">101-200 People</option>
                    <option value="201+">200+ People</option>
                </select>
                <select id="sortFilter" class="filter-select">
                    <option value="name-asc">Sort by Name (A-Z)</option>
                    <option value="name-desc">Sort by Name (Z-A)</option>
                    <option value="cap-asc">Capacity (Low-High)</option>
                    <option value="cap-desc">Capacity (High-Low)</option>
                </select>
                <select id="amenityFilter" class="filter-select">
                    <option value="any">All Amenities</option>
                    <?php foreach ($all_amenities as $amenity): ?>
                        <option value="<?php echo htmlspecialchars($amenity); ?>"><?php echo htmlspecialchars($amenity); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="facilities-grid" id="facilities-grid">
            <?php if (isset($facilities) && is_array($facilities) && count($facilities) > 0): ?>
                <?php foreach ($facilities as $index => $facility): ?>
                    <div class="facility-card" data-index="<?php echo $facility['id']; ?>" 
                         data-name="<?php echo htmlspecialchars(strtolower($facility['name'])); ?>" 
                         data-capacity="<?php echo htmlspecialchars($facility['capacity']); ?>" style="animation-delay: <?php echo $index * 0.05; ?>s" onclick="openModal(<?php echo $facility['id']; ?>)">
                        <img src="<?php echo htmlspecialchars($facility['image']); ?>" alt="<?php echo htmlspecialchars($facility['name']); ?>" class="facility-image">
                        <div class="facility-content">
                            <h3><?php echo htmlspecialchars($facility['name']); ?></h3>
                            <span class="facility-capacity">
                                <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: inline-block; vertical-align: middle; margin-right: 4px;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                                <?php echo htmlspecialchars($facility['capacity']); ?> People
                            </span>
                            <p class="facility-description"><?php echo htmlspecialchars(substr($facility['description'], 0, 80)) . '...'; ?></p>
                            <div class="facility-amenities">
                                <?php if (!empty($facility['amenities'])): ?>
                                    <?php foreach (array_slice($facility['amenities'], 0, 2) as $amenity): ?>
                                        <span class="amenity-tag"><?php echo htmlspecialchars($amenity); ?></span>
                                    <?php endforeach; ?>
                                    <?php if (count($facility['amenities']) > 2): ?>
                                        <span class="amenity-tag">+<?php echo count($facility['amenities']) - 2; ?> more</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-facilities">
                    <p>No facilities available at the moment.</p>
                </div>
            <?php endif; ?>
        </div>

        <div id="noResultsMessage" style="display: none; text-align: center; padding: 60px 20px; color: var(--text-secondary);">
            <p style="font-size: 18px; font-weight: 500;">No facilities found</p>
            <p style="font-size: 14px;">Try adjusting your search terms.</p>
        </div>
    </div>

    <div id="facilityModal" class="modal">
        <div class="modal-content">
            <img id="modalImage" src="/placeholder.svg" alt="" class="modal-image">
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
                <p id="modalDescription" class="modal-description"></p>
                <div id="modalAmenities" class="modal-amenities"></div>
                <button class="modal-book-btn" onclick="window.location.href='create_request.php'">Book This Facility</button>
            </div>
        </div>
    </div>

    <script>
        const allFacilities = <?php echo json_encode($facilities); ?>;
        
        function openModal(facilityId) {
            const facility = allFacilities.find(f => f.id == facilityId);

            if (!facility) {
                console.error("Facility not found for ID:", facilityId);
                return;
            }

            const modal = document.getElementById('facilityModal');
            
            document.getElementById('modalImage').src = facility.image;
            document.getElementById('modalImage').alt = facility.name;
            document.getElementById('modalTitle').textContent = facility.name;
            document.getElementById('modalCapacity').innerHTML = `
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: inline-block; vertical-align: middle; margin-right: 4px;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
                ${facility.capacity}
            `;
            document.getElementById('modalDescription').textContent = facility.description;
            
            const amenitiesContainer = document.getElementById('modalAmenities');
            amenitiesContainer.innerHTML = '';
            facility.amenities.forEach(amenity => {
                const tag = document.createElement('span');
                tag.className = 'modal-amenity-tag';
                tag.textContent = amenity;
                amenitiesContainer.appendChild(tag);
            });
            
            modal.style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('facilityModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('facilityModal');
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

        // --- NEW FILTERING LOGIC ---
        const searchInput = document.getElementById('facilitySearch');
        const capacityFilter = document.getElementById('capacityFilter');
        const amenityFilter = document.getElementById('amenityFilter');
        const sortFilter = document.getElementById('sortFilter');
        const noResultsMessage = document.getElementById('noResultsMessage');
        const facilitiesGrid = document.getElementById('facilities-grid');

        function filterFacilities() {
            const searchTerm = searchInput.value.toLowerCase().trim();
            const selectedCapacity = capacityFilter.value; 
            const selectedAmenity = amenityFilter.value;
            let visibleCount = 0;

            const allCards = document.querySelectorAll('.facility-card');

            allCards.forEach(card => {
                const facilityId = card.dataset.index;
                const facility = allFacilities.find(f => f.id == facilityId);

                if (!facility) return;

                // Search term match
                const name = facility.name.toLowerCase();
                const description = facility.description.toLowerCase();
                const searchMatch = name.includes(searchTerm) || description.includes(searchTerm);

                // Capacity match
                let capacityMatch = true;
                if (selectedCapacity !== 'any') {
                    const capacity = parseInt(facility.capacity, 10);
                    if (selectedCapacity === '1-50') {
                        capacityMatch = capacity >= 1 && capacity <= 50;
                    } else if (selectedCapacity === '51-100') {
                        capacityMatch = capacity >= 51 && capacity <= 100;
                    } else if (selectedCapacity === '101-200') {
                        capacityMatch = capacity >= 101 && capacity <= 200;
                    } else if (selectedCapacity === '201+') {
                        capacityMatch = capacity >= 201;
                    }
                }

                // Amenity match
                let amenityMatch = true;
                if (selectedAmenity !== 'any') {
                    amenityMatch = facility.amenities.includes(selectedAmenity);
                }
                
                if (searchMatch && capacityMatch && amenityMatch) {
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
        
        function sortFacilities() {
            const sortValue = sortFilter.value;
            const cards = Array.from(facilitiesGrid.children);

            cards.sort((a, b) => {
                const nameA = a.dataset.name;
                const nameB = b.dataset.name;
                const capA = parseInt(a.dataset.capacity, 10);
                const capB = parseInt(b.dataset.capacity, 10);

                switch (sortValue) {
                    case 'name-asc':
                        return nameA.localeCompare(nameB);
                    case 'name-desc':
                        return nameB.localeCompare(nameA);
                    case 'cap-asc':
                        return capA - capB;
                    case 'cap-desc':
                        return capB - capA;
                    default:
                        return 0;
                }
            });

            cards.forEach(card => facilitiesGrid.appendChild(card));
        }

        searchInput.addEventListener('input', filterFacilities);
        capacityFilter.addEventListener('change', filterFacilities);
        amenityFilter.addEventListener('change', filterFacilities);
        sortFilter.addEventListener('change', sortFacilities);
    </script>
</body>
</html>