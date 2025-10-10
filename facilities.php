<?php
session_start();
require_once 'theme_loader.php';
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Initialize variables to prevent undefined errors
$logo_file = isset($logo_file) ? $logo_file : 'img/default-logo.png';
$portal_name = isset($portal_name) ? $portal_name : 'MCNP Service Portal';

// Facilities list with descriptions
$facilities = [
    [
        'name' => 'HM Laboratory',
        'capacity' => '40 students',
        'description' => 'Fully equipped laboratory for medical and health sciences with state-of-the-art equipment and safety features.',
        'amenities' => ['Projector', 'Air Conditioning', 'Lab Equipment'],
        'image' => 'img/hm.webp?height=400&width=600'
    ],
    [
        'name' => 'Function Hall',
        'capacity' => '200 people',
        'description' => 'Large hall suitable for conferences, seminars, and events with professional audio-visual equipment and comfortable seating.',
        'amenities' => ['Sound System', 'Projector', 'Air Conditioning', 'Stage'],
        'image' => 'img/function.jpg?height=400&width=600'
    ],
    [
        'name' => 'Conference Hall',
        'capacity' => '100 people',
        'description' => 'Professional conference room with modern facilities perfect for business meetings and academic conferences.',
        'amenities' => ['Projector', 'Air Conditioning', 'Whiteboard', 'WiFi'],
        'image' => 'img/conference.jpg?height=400&width=600'
    ],
    [
        'name' => 'Gymnasium',
        'capacity' => '500 people',
        'description' => 'Multi-purpose gymnasium for sports and large gatherings with professional basketball court and bleacher seating.',
        'amenities' => ['Basketball Court', 'Sound System', 'Bleachers'],
        'image' => 'img/gym.jpg?height=400&width=600'
    ],
    [
        'name' => 'AVR 1',
        'capacity' => '50 people',
        'description' => 'Audio-visual room with multimedia equipment ideal for presentations and video conferences.',
        'amenities' => ['Projector', 'Sound System', 'Air Conditioning'],
        'image' => 'img/avr1.jpg?height=400&width=600'
    ],
    [
        'name' => 'AVR 2',
        'capacity' => '50 people',
        'description' => 'Audio-visual room with multimedia equipment ideal for presentations and video conferences.',
        'amenities' => ['Projector', 'Sound System', 'Air Conditioning'],
        'image' => 'img/avr2.jpg?height=400&width=600'
    ],
    [
        'name' => 'AVR 3',
        'capacity' => '50 people',
        'description' => 'Audio-visual room with multimedia equipment ideal for presentations and video conferences.',
        'amenities' => ['Projector', 'Sound System', 'Air Conditioning'],
        'image' => 'img/avr3.webp?height=400&width=600'
    ],
    [
        'name' => 'AMPHI 1',
        'capacity' => '150 people',
        'description' => 'Amphitheater-style classroom for lectures with tiered seating for optimal viewing and acoustics.',
        'amenities' => ['Projector', 'Sound System', 'Air Conditioning'],
        'image' => 'img/amph1.webp?height=400&width=600'
    ],
    [
        'name' => 'AMPHI 2',
        'capacity' => '150 people',
        'description' => 'Amphitheater-style classroom for lectures with tiered seating for optimal viewing and acoustics.',
        'amenities' => ['Projector', 'Sound System', 'Air Conditioning'],
        'image' => 'img/amph2.jpg?height=400&width=600'
    ],
    [
        'name' => 'AMPHI 3',
        'capacity' => '150 people',
        'description' => 'Amphitheater-style classroom for lectures with tiered seating for optimal viewing and acoustics.',
        'amenities' => ['Projector', 'Sound System', 'Air Conditioning'],
        'image' => 'img/amph3.jpg?height=400&width=600'
    ],
    [
        'name' => 'Reading Area',
        'capacity' => '30 people',
        'description' => 'Quiet study area for individual or group study with comfortable seating and excellent lighting.',
        'amenities' => ['WiFi', 'Air Conditioning', 'Study Tables'],
        'image' => 'img/ra.jpg?height=400&width=600'
    ],
    [
        'name' => 'Studio Room',
        'capacity' => '20 people',
        'description' => 'Recording and production studio with professional equipment and soundproofing for multimedia projects.',
        'amenities' => ['Recording Equipment', 'Soundproofing', 'Air Conditioning'],
        'image' => 'img/studio.jpg?height=400&width=600'
    ]
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Facilities - MCNP Service Portal</title>
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
            padding: 16px;
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 24px 16px;
        }
        
        .page-header {
            margin-bottom: 24px;
        }
        
        .page-header h1 {
            font-size: 28px;
            color: #1a1a1a;
            margin-bottom: 8px;
        }
        
        .page-header p {
            color: #6b7280;
            font-size: 15px;
        }
        
        .facilities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .facility-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
        }
        
        /* Added hover effect with smooth transform and shadow */
        .facility-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.15);
        }
        
        /* Added facility image container */
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
            color: #1a1a1a;
            margin-bottom: 8px;
        }
        
        .facility-capacity {
            display: inline-block;
            padding: 4px 10px;
            background: #f3f4f6;
            border-radius: 12px;
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 12px;
        }
        
        .facility-description {
            color: #6b7280;
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
            padding: 4px 10px;
            background: #e0f2fe;
            color: #075985;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
        }
        
        .btn-book-facility {
            width: 100%;
            padding: 12px;
            background: #1a1a1a;
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: block;
            text-align: center;
            transition: all 0.2s;
        }
        
        .btn-book-facility:hover {
            background: #000;
            transform: translateY(-2px);
        }
        
        /* Added modal styles for facility details popup */
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
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 16px;
            max-width: 700px;
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
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }
        
        .modal-header h2 {
            font-size: 24px;
            color: #1a1a1a;
            margin: 0;
        }
        
        .close-modal {
            background: #f3f4f6;
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        
        .close-modal:hover {
            background: #e5e7eb;
            transform: rotate(90deg);
        }
        
        .modal-capacity {
            display: inline-block;
            padding: 6px 12px;
            background: #f3f4f6;
            border-radius: 12px;
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 16px;
        }
        
        .modal-description {
            color: #4b5563;
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
            background: #e0f2fe;
            color: #075985;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 500;
        }
        
        .modal-book-btn {
            width: 100%;
            padding: 14px;
            background: #1a1a1a;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .modal-book-btn:hover {
            background: #000;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        
        /* Mobile Responsive Styles - COMPACT VERSION */
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
            
            .page-header h1 {
                font-size: 24px;
            }
            
            .page-header p {
                font-size: 14px;
            }
            
            /* COMPACT MOBILE GRID - 2 columns */
            .facilities-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }
            
            /* SMALLER CARDS */
            .facility-card {
                border-radius: 8px;
            }
            
            .facility-image {
                height: 120px; /* Much smaller images */
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
                padding: 3px 8px;
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
                font-size: 9px;
                padding: 2px 6px;
            }
            
            .btn-book-facility {
                padding: 8px;
                font-size: 12px;
                border-radius: 4px;
            }
            
            /* Modal adjustments for mobile */
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
            
            /* Single column on very small screens */
            .facilities-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            
            .facility-image {
                height: 140px;
            }
            
            .facility-content h3 {
                font-size: 16px;
            }
            
            .facility-description {
                font-size: 12px;
                -webkit-line-clamp: 3;
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
                font-size: 18px;
            }
            
            .modal-description {
                font-size: 14px;
            }
            
            .modal-amenity-tag {
                font-size: 12px;
                padding: 6px 10px;
            }
        }

        /* For tablets in landscape or larger phones */
        @media (min-width: 481px) and (max-width: 768px) {
            .facilities-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .facility-image {
                height: 130px;
            }
        }

        /* For larger tablets */
        @media (min-width: 769px) and (max-width: 1024px) {
            .facilities-grid {
                grid-template-columns: repeat(3, 1fr);
            }
            
            .facility-image {
                height: 160px;
            }
        }
    </style>
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
            <p>Browse and book our facilities for your events and activities</p>
        </div>

        <div class="facilities-grid">
            <?php if (isset($facilities) && is_array($facilities)): ?>
                <?php foreach ($facilities as $index => $facility): ?>
                    <div class="facility-card" onclick="openModal(<?php echo $index; ?>)">
                        <img src="<?php echo htmlspecialchars($facility['image']); ?>" alt="<?php echo htmlspecialchars($facility['name']); ?>" class="facility-image">
                        <div class="facility-content">
                            <h3><?php echo htmlspecialchars($facility['name']); ?></h3>
                            <span class="facility-capacity">
                                <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: inline-block; vertical-align: middle; margin-right: 4px;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                                <?php echo htmlspecialchars($facility['capacity']); ?>
                            </span>
                            <p class="facility-description"><?php echo htmlspecialchars(substr($facility['description'], 0, 80)) . '...'; ?></p>
                            <div class="facility-amenities">
                                <?php foreach (array_slice($facility['amenities'], 0, 2) as $amenity): ?>
                                    <span class="amenity-tag"><?php echo htmlspecialchars($amenity); ?></span>
                                <?php endforeach; ?>
                                <?php if (count($facility['amenities']) > 2): ?>
                                    <span class="amenity-tag">+<?php echo count($facility['amenities']) - 2; ?> more</span>
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
        const facilities = <?php echo isset($facilities) ? json_encode($facilities) : '[]'; ?>;
        
        function openModal(index) {
            if (!facilities || facilities.length === 0) return;
            
            const facility = facilities[index];
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
    </script>
</body>
</html>