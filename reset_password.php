<?php
session_start();
require_once 'config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error = '';
$success = '';
$email = isset($_SESSION['reset_email']) ? $_SESSION['reset_email'] : '';

// Check if user came from forgot password flow
if (empty($email) && !isset($_SESSION['reset_code_sent'])) {
    header("Location: forgot_password.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['verify_reset'])) {
        $code = trim($_POST['code']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($code)) {
            $error = 'Please enter the verification code.';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } else {
            // Verify reset code
            $sql = "SELECT email, reset_code, reset_code_expires FROM users WHERE email = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            error_log("DEBUG: email=$email, code=$code");
            error_log("DB: email={$row['email']}, code={$row['reset_code']}, expires={$row['reset_code_expires']}");
            
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Update password and clear reset code
                $sql = "UPDATE users SET password = ?, reset_code = NULL, reset_code_expires = NULL WHERE email = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ss", $hashed_password, $email);
                
                if ($stmt->execute()) {
                    // Clear session
                    unset($_SESSION['reset_email']);
                    unset($_SESSION['reset_code_sent']);
                    
                    $success = "Password reset successfully! You can now <a href='index.php' style='color: #166534; text-decoration: underline;'>login</a> with your new password.";
                } else {
                    $error = 'Error updating password. Please try again.';
                }
            } else {
                $error = 'Invalid or expired verification code.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - MCNP-ISAP Facility Usage Portal</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e8ecf1 100%);
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
        
        .logo-section {
            text-align: center;
            margin-bottom: 32px;
        }
        
        .logo-section img {
            width: 120px;
            height: 120px;
            object-fit: contain;
            margin-bottom: 16px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .logo-section h1 {
            font-size: 28px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 8px;
            line-height: 1.2;
        }
        
        .logo-section p {
            font-size: 15px;
            color: #6b7280;
            line-height: 1.5;
        }
        
        .auth-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            padding: 32px;
        }
        
        .tab-header {
            text-align: center;
            margin-bottom: 24px;
        }
        
        .tab-header h2 {
            font-size: 24px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 8px;
        }
        
        .tab-header p {
            font-size: 14px;
            color: #6b7280;
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
        input[type="password"] {
            width: 100%;
            padding: 12px 16px;
            font-size: 15px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            transition: all 0.2s;
            font-family: inherit;
        }
        
        input:focus {
            outline: none;
            border-color: #1a1a1a;
            box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.05);
        }
        
        .password-toggle {
            position: relative;
        }
        
        .password-toggle input {
            padding-right: 48px;
        }
        
        .password-toggle button {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6b7280;
            cursor: pointer;
            padding: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 4px;
            transition: all 0.2s;
        }
        
        .password-toggle button:hover {
            background: #f3f4f6;
            color: #1a1a1a;
        }
        
        .password-toggle button svg {
            width: 20px;
            height: 20px;
        }
        
        .btn-primary {
            width: 100%;
            padding: 14px;
            font-size: 16px;
            font-weight: 600;
            color: white;
            background: #1a1a1a;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-primary:hover {
            background: #000;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }
        
        .back-to-login {
            text-align: center;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #e5e7eb;
            font-size: 14px;
            color: #6b7280;
        }
        
        .back-to-login a {
            color: #1a1a1a;
            font-weight: 600;
            text-decoration: none;
        }
        
        .back-to-login a:hover {
            text-decoration: underline;
        }
        
        .footer {
            text-align: center;
            margin-top: 32px;
            font-size: 13px;
            color: #6b7280;
            line-height: 1.6;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-danger {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        
        .alert-success a {
            color: #166534;
            text-decoration: underline;
        }
        
        .timer {
            font-size: 13px;
            color: #9ca3af;
            margin-top: 8px;
            text-align: center;
        }
        
        @media (max-width: 640px) {
            .logo-section h1 {
                font-size: 22px;
            }
            
            .auth-card {
                padding: 24px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo-section">
            <img src="combined-logo.png" alt="MCNP-ISAP Logo">
            <h1>MCNP-ISAP Facility Usage Portal</h1>
            <p>Medical Colleges of Northern Philippines<br>International School of Asia and the Pacific</p>
        </div>
        
        <div class="auth-card">
            <div class="tab-header">
                <h2>Reset Password</h2>
                <p>Enter the 6-digit code sent to <?php echo htmlspecialchars($email); ?></p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php else: ?>
                <form method="POST" action="" id="reset-form">
                    <div class="form-group">
                        <label for="code">Verification Code</label>
                        <input 
                            type="text" 
                            id="code" 
                            name="code" 
                            maxlength="6" 
                            placeholder="000000"
                            required
                            autofocus
                        >
                        <div class="timer" id="timer">Code expires in 10:00</div>
                    </div>
                    
                    <div class="form-group">
                        <label>New Password</label>
                        <div class="password-toggle">
                            <input type="password" name="password" id="new-password" placeholder="Enter new password" required>
                            <button type="button" onclick="togglePassword('new-password', this)">
                                <svg class="eye-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <div class="password-toggle">
                            <input type="password" name="confirm_password" id="confirm-password" placeholder="Confirm new password" required>
                            <button type="button" onclick="togglePassword('confirm-password', this)">
                                <svg class="eye-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    
                    <button type="submit" name="verify_reset" class="btn-primary">Reset Password</button>
                </form>
            <?php endif; ?>
            
            <div class="back-to-login">
                <a href="index.php">← Back to Sign In</a>
            </div>
        </div>
        
        <div class="footer">
            Medical Colleges of Northern Philippines<br>
            International School of Asia and the Pacific
        </div>
    </div>

    <script>
        // Countdown timer - FIXED VERSION
        let timeLeft = 600;
        const timerElement = document.getElementById('timer');

        function updateTimer() {
            if (timeLeft <= 0) {
                timerElement.textContent = 'Code has expired';
                timerElement.style.color = '#ef4444';
                return;
            }
            
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            timerElement.textContent = `Code expires in ${minutes}:${seconds.toString().padStart(2, '0')}`;
            timeLeft--;
            
            setTimeout(updateTimer, 1000);
        }

        // Start timer only if element exists
        if (timerElement) {
            updateTimer();
        }

        // Password toggle - FIXED VERSION
        function togglePassword(inputId, button) {
            const input = document.getElementById(inputId);
            if (!input) return;
            
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            
            // Update icon
            if (isPassword) {
                button.innerHTML = `
                    <svg class="eye-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                    </svg>
                `;
            } else {
                button.innerHTML = `
                    <svg class="eye-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                `;
            }
        }

        // Form validation - FIXED VERSION
        const resetForm = document.getElementById('reset-form');
        if (resetForm) {
            resetForm.addEventListener('submit', function(e) {
                const password = this.querySelector('input[name="password"]').value;
                const confirmPassword = this.querySelector('input[name="confirm_password"]').value;
                
                if (password !== confirmPassword) {
                    e.preventDefault();
                    alert('Passwords do not match!');
                    return false;
                }
                
                if (password.length < 6) {
                    e.preventDefault();
                    alert('Password must be at least 6 characters long!');
                    return false;
                }
            });
        }

        // Auto-format verification code input - SIMPLIFIED
        const codeInput = document.getElementById('code');
        if (codeInput) {
            codeInput.addEventListener('input', function(e) {
                // Only allow numbers
                this.value = this.value.replace(/[^0-9]/g, '');
                // Limit to 6 characters
                if (this.value.length > 6) {
                    this.value = this.value.slice(0, 6);
                }
            });
        }
    </script>
    <?php include 'chat_bot.php'; ?>
</body>
</html>