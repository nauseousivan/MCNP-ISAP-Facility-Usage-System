<?php
session_start();
require_once '../functions.php';
require_once '../theme_loader.php';
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Use the theme
$logo_file = $GLOBALS['logo_file'];
$portal_name = $GLOBALS['portal_name'];

?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($theme); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar - MCNP Service Portal</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/main.min.css">
    <style>
        /* General Styles (Copied from facilities.php for consistency) */
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

        /* FullCalendar specific styling adjustments */
        .fc {
            background-color: var(--bg-primary);
            border-radius: 16px;
            border: 1px solid var(--border-color);
            padding: 20px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.03);
        }
        .fc .fc-button-primary {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
            color: var(--bg-primary); /* Text color for buttons */
        }
        .fc .fc-button-primary:hover {
            background-color: var(--text-primary); /* Darker accent on hover */
            border-color: var(--text-primary);
        }
        .fc .fc-daygrid-day.fc-day-today {
            background-color: rgba(var(--accent-color-rgb), 0.1); /* Light accent for today */
        }
        .fc-event {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
            color: white;
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 0.85em;
        }
        .fc-event-title {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
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
                    <div class="brand-subtitle">Calendar</div>
                </div>
            </div>
        </a>
        <a href="dashboard.php" class="btn-back">Back</a>
    </header>

    <div class="container">
        <div class="page-header">
            <h1>Event Calendar</h1>
            <p>View and manage facility bookings and other events.</p>
        </div>

        <div id='calendar'></div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                editable: true,
                selectable: true,
                selectMirror: true,
                dayMaxEvents: true,
                events: 'events.php', // PHP endpoint to fetch events
                
                select: function(info) {
                    let title = prompt('Please enter a new title for your event:');
                    if (title) {
                        let eventData = {
                            title: title,
                            start: info.startStr,
                            end: info.endStr,
                            allDay: info.allDay
                        };
                        fetch('add_event.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify(eventData)
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                eventData.id = data.id; // Get the new ID from the server
                                calendar.addEvent(eventData);
                            } else {
                                alert('Error adding event: ' + data.message);
                            }
                        })
                        .catch(error => console.error('Error:', error));
                    }
                    calendar.unselect();
                },
                
                eventClick: function(clickInfo) {
                    if (confirm('Are you sure you want to delete the event "' + clickInfo.event.title + '"?')) {
                        fetch('delete_event.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({ id: clickInfo.event.id })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                clickInfo.event.remove();
                            } else {
                                alert('Error deleting event: ' + data.message);
                            }
                        })
                        .catch(error => console.error('Error:', error));
                    }
                },

                eventDrop: function(info) { // called when an event is dragged and dropped
                    let eventData = {
                        id: info.event.id,
                        start: info.event.startStr,
                        end: info.event.endStr,
                        allDay: info.event.allDay
                    };
                    fetch('update_event.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(eventData)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success) {
                            alert('Error updating event: ' + data.message);
                            info.revert(); // Revert the event's position if update fails
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        info.revert();
                    });
                },

                eventResize: function(info) { // called when an event is resized
                    let eventData = {
                        id: info.event.id,
                        start: info.event.startStr,
                        end: info.event.endStr,
                        allDay: info.event.allDay
                    };
                    fetch('update_event.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(eventData)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success) {
                            alert('Error updating event: ' + data.message);
                            info.revert(); // Revert the event's size if update fails
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        info.revert();
                    });
                }
            });
            calendar.render();
        });
    </script>
</body>
</html>