<?php
session_start();
$_SESSION['splash_seen'] = true;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MCNP-ISAP Service Portal</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #fdfaf6; /* A beige color to match the main app background */
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .splash-container {
            text-align: center;
            animation: fadeIn 1.5s ease-in-out;
        }

        .logo-wrapper {
            position: relative;
            display: inline-block;
        }

        .logo {
            width: 200px;
            height: 200px;
            border-radius: 50%;
            object-fit: cover;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: logoFadeIn 2s ease-in-out, logoPulse 2s ease-in-out infinite 2s;
        }

        .logo-glow {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 220px;
            height: 220px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.3) 0%, transparent 70%);
            animation: glowPulse 2s ease-in-out infinite;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: scale(0.9);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        @keyframes logoFadeIn {
            0% {
                opacity: 0;
                transform: scale(0.5) rotate(-10deg);
            }
            50% {
                transform: scale(1.05) rotate(5deg);
            }
            100% {
                opacity: 1;
                transform: scale(1) rotate(0deg);
            }
        }

        @keyframes logoPulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
        }

        @keyframes glowPulse {
            0%, 100% {
                opacity: 0.5;
                transform: translate(-50%, -50%) scale(1);
            }
            50% {
                opacity: 0.8;
                transform: translate(-50%, -50%) scale(1.1);
            }
        }

        /* Added transition animation where logo shrinks and moves to top */
        .splash-container.transitioning .logo {
            animation: logoToPosition 1s ease-in-out forwards;
        }

        .splash-container.transitioning .logo-glow {
            animation: glowFadeOut 1s ease-in-out forwards;
        }

        @keyframes logoToPosition {
            to {
                width: 120px;
                height: 120px;
                transform: translateY(calc(-50vh + 120px));
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            }
        }

        @keyframes glowFadeOut {
            to {
                opacity: 0;
            }
        }

        /* Fade out animation when redirecting */
        .fade-out {
            animation: fadeOut 0.5s ease-in-out forwards;
        }

        @keyframes fadeOut {
            to {
                opacity: 0;
            }
        }
    </style>
</head>
<body>
    <div class="splash-container" id="splashContainer">
        <div class="logo-wrapper">
            <div class="logo-glow"></div>
            <img src="combined-logo.png" alt="MCNP-ISAP Logo" class="logo">
        </div>
    </div>

    <script>
        setTimeout(() => {
            document.getElementById('splashContainer').classList.add('transitioning');
            setTimeout(() => {
                document.body.classList.add('fade-out');
                setTimeout(() => {
                    window.location.href = 'index.php';
                }, 500);
            }, 1000);
        }, 2500);
    </script>
</body>
</html>
