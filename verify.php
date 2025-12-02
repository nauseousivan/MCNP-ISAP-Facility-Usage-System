<?php
session_start();
require_once 'db_connection.php';

$message = '';
$messageType = '';
$department = isset($_SESSION['registration_department']) ? $_SESSION['registration_department'] : '';
$email = isset($_GET['email']) ? $_GET['email'] : (isset($_SESSION['verify_email']) ? $_SESSION['verify_email'] : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code']);
    
    if (empty($code)) {
        $message = 'Please enter the verification code.';
        $messageType = 'error';
    } else {
        // First, check if the code matches and is not expired
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND verification_code = ?");
        $stmt->bind_param("ss", $email, $code);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Check if already verified
            if ($user['verified'] == 1) {
                $message = 'This account is already verified. Please login.';
                $messageType = 'error';
                unset($_SESSION['verify_email']);
            } else {
                // Update to mark as verified
                $updateStmt = $conn->prepare("UPDATE users SET verified = 1, verification_code = NULL WHERE email = ? AND verification_code = ?");
                $updateStmt->bind_param("ss", $email, $code);
                
                if ($updateStmt->execute() && $updateStmt->affected_rows > 0) {
                    $_SESSION['verification_success'] = true;
                    unset($_SESSION['verify_email']);
                    header("Location: verify.php?success=1");
                    exit();
                } else {
                    $message = 'Verification failed. Please try again.';
                    $messageType = 'error';
                }
            }
        } else {
            $message = 'Invalid verification code. Please check and try again.';
            $messageType = 'error';
        }
    }
}

$showSuccess = isset($_GET['success']) && $_GET['success'] == 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email - MCNP-ISAP Service Portal</title>
    <style>
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
            font-family: 'Geist Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #fdfaf6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            width: 100%;
            max-width: 480px;
        }

        .logo-container {
            text-align: center;
            margin-bottom: 32px;
        }

        .logo-group {
            display: flex;
            justify-content: center;
            gap: 24px;
        }

        .logo {
            max-width: 90px;
            max-height: 90px;
            object-fit: contain;
        }

        .logo.circular {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            object-fit: cover;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
            padding: 48px 40px;
            text-align: center;
        }

        .icon-container {
            width: 80px;
            height: 80px;
            background: #fef3c7;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }

        .icon-container svg {
            width: 40px;
            height: 40px;
        }

        .checkmark-container {
            width: 48px;
            height: 48px;
            background: #f0fdf4;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            flex-shrink: 0;
        }

        .checkmark-container svg {
            width: 24px;
            height: 24px;
        }

        h1 {
            font-size: 28px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 12px;
        }

        .subtitle {
            font-size: 15px;
            color: #6b7280;
            margin-bottom: 32px;
            line-height: 1.5;
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
        }

        input[type="text"]:not(#chatInput) {
            width: 100%;
            padding: 14px 16px;
            font-size: 16px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            transition: all 0.2s;
            text-align: center;
            letter-spacing: 8px;
            font-weight: 600;
        }

        input[type="text"]:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .timer {
            font-size: 13px;
            color: #9ca3af;
            margin-top: 8px;
            text-align: center;
        }

        .btn {
            width: 100%;
            padding: 14px 24px;
            font-size: 16px;
            font-weight: 600;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 24px;
        }

        .btn-primary {
            background: #1a1a1a;
            color: white;
        }

        .btn-primary:hover {
            background: #2d2d2d;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .resend-container {
            text-align: center;
            margin-top: 24px;
            font-size: 14px;
            color: #6b7280;
        }

        #resendBtn {
            background: none;
            border: none;
            color: #3b82f6;
            font-weight: 600;
            cursor: pointer;
        }

        .back-to-login {
            text-align: center;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #e5e7eb;
            font-size: 14px;
        }

        .back-to-login a {
            color: #1a1a1a;
            font-weight: 600;
            text-decoration: none;
        }
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: left;
        }

        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .info-box {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px;
            display: flex;
            align-items: flex-start;
            text-align: left;
            margin-top: 24px;
        }

        .info-box p {
            font-size: 14px;
            color: #6b7280;
            line-height: 1.5;
            margin: 0;
        }

        .footer {
            text-align: center;
            margin-top: 32px;
            font-size: 13px;
            color: #6b7280;
        }

        @media (max-width: 640px) {
            .card {
                padding: 32px 24px;
            }

            h1 {
                font-size: 24px;
            }

            .logo {
                width: 80px;
                height: 80px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (!$showSuccess): ?>
            <div class="logo-container"> 
                <?php if (strpos($department, 'Medical') !== false): ?>
                    <img src="medical-logo2.png" alt="MCNP Logo" class="logo circular">
                <?php elseif (strpos($department, 'International') !== false): ?>
                    <img src="isap-logo2.png" alt="ISAP Logo" class="logo">
                <?php else: ?>
                    <div class="logo-group">
                        <img src="medical-logo2.png" alt="MCNP Logo" class="logo circular">
                        <img src="isap-logo2.png" alt="ISAP Logo" class="logo">
                    </div>
                <?php endif; ?>
            </div>

            <div class="card">
                <h1>Verify Your Email</h1>
                <p class="subtitle">Enter the 6-digit code sent to <?php echo htmlspecialchars($email); ?></p>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="code">Verification Code</label>
                        <input 
                            type="text" 
                            id="code" 
                            name="code" 
                            maxlength="6" 
                            placeholder="000000"
                            pattern="[0-9]{6}"
                            required
                            autofocus
                        >
                        <div class="timer" id="timer">Code expires in 10:00</div>
                    </div>

                    <button type="submit" class="btn btn-primary">Verify Email</button>
                </form>

                <div class="resend-container">
                    Didn't receive a code? 
                    <button id="resendBtn" onclick="resendCode()">Resend</button>
                </div>

                <div class="back-to-login">
                    <a href="index.php">← Back to Login</a>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="icon-container">
                    <svg fill="none" stroke="#eab308" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M12 6v6l4 2"/>
                    </svg>
                </div>

                <h1>Registration Submitted</h1>
                <p class="subtitle">Your account registration has been submitted successfully. Please wait for admin approval before you can access the system.</p>

                <div class="info-box">
                    <div class="checkmark-container">
                        <svg fill="none" stroke="#16a34a" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <p>You will receive an email notification once your account has been approved by the administrator.</p>
                </div>

                <a href="index.php" class="btn btn-primary" style="display: block; text-decoration: none; color: white;">Back to Login</a>
            </div>
        <?php endif; ?>

        <div class="footer">
            Medical Colleges of Northern Philippines<br>
            International School of Asia and the Pacific
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Countdown timer
            let timeLeft = 600; // 10 minutes in seconds
            const timerElement = document.getElementById('timer');

            if (timerElement) {
                function updateTimer() {
                    const minutes = Math.floor(timeLeft / 60);
                    const seconds = timeLeft % 60;
                    timerElement.textContent = `Code expires in ${minutes}:${seconds.toString().padStart(2, '0')}`;
                    
                    if (timeLeft > 0) {
                        timeLeft--;
                        setTimeout(updateTimer, 1000);
                    } else {
                        timerElement.textContent = 'Code has expired';
                        timerElement.style.color = '#ef4444';
                    }
                }
                
                updateTimer();
            }

            // Auto-format verification code input
            const codeInput = document.getElementById('code');
            if (codeInput) {
                codeInput.addEventListener('input', function(e) {
                    this.value = this.value.replace(/[^0-9]/g, '');
                });
            }
        });

        async function resendCode() {
            const resendBtn = document.getElementById('resendBtn');
            const originalText = resendBtn.textContent;
            resendBtn.disabled = true;
            resendBtn.textContent = 'Sending...';

            try { // Keep the try-catch block for the network request
                const response = await fetch('resend_code.php', {
                    method: 'POST'
                });
                const result = await response.json();

                if (result.success) {
                    alert('A new verification code has been sent to your email.');
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                alert('An error occurred. Please try again.');
            }

            // Start countdown
            let cooldown = 60;
            resendBtn.textContent = `Resend (${cooldown}s)`;

            const interval = setInterval(() => {
                cooldown--;
                resendBtn.textContent = `Resend (${cooldown}s)`;
                if (cooldown <= 0) {
                    clearInterval(interval);
                    resendBtn.disabled = false;
                    resendBtn.textContent = originalText;
                }
            }, 1000);
        }
    </script>
    <?php include 'chat_bot.php'; ?>
</body>
</html>