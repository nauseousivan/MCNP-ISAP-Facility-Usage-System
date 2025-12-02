<?php
session_start();
require_once 'config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['request_reset'])) {
        $email = $_POST['email'];
        
        // Check if email exists
        $sql = "SELECT * FROM users WHERE email='$email'";
        $result = $conn->query($sql);
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Generate OTP
            $reset_code = sprintf("%06d", mt_rand(1, 999999));
            $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            
            // Store reset code in database
            $sql = "UPDATE users SET reset_code='$reset_code', reset_code_expires='$expiry' WHERE email='$email'";
            
            if ($conn->query($sql) === TRUE) {
                // Send reset email
                if (!file_exists('vendor/autoload.php')) {
                    $error = "PHPMailer is not installed! Please run: composer require phpmailer/phpmailer";
                } else {
                    require_once 'send_email.php';
                    
                    // Try to send email
                    $emailSent = sendPasswordResetEmail($email, $user['name'], $reset_code);
                    
                    if ($emailSent) {
                        $_SESSION['reset_email'] = $email;
                        $_SESSION['reset_code_sent'] = true;
                        header("Location: reset_password.php");
                        exit();
                    } else {
                        $error = "We couldn't send the password reset email. Please try again later.";
                    }
                }
            } else {
                $error = "Error: " . $conn->error;
            }
        } else {
            $error = "No account found with that email.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - MCNP-ISAP Facility Usage Portal</title>
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
        
        input[type="email"] {
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
        <!-- Logo Section -->
        <div class="logo-section">
            <img src="combined-logo.png" alt="MCNP-ISAP Logo">
            <h1>MCNP-ISAP Facility Usage Portal</h1>
            <p>Medical Colleges of Northern Philippines<br>International School of Asia and the Pacific</p>
        </div>
        
        <!-- Auth Card -->
        <div class="auth-card">
            <div class="tab-header">
                <h2>Reset Password</h2>
                <p>Enter your email to receive a password reset code</p>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" placeholder="student@mcnp.edu.ph or student@isap.edu.ph" required>
                </div>
                
                <button type="submit" name="request_reset" class="btn-primary">Send Reset Code</button>
            </form>
            
            <div class="back-to-login">
                <a href="index.php">← Back to Sign In</a>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            Medical Colleges of Northern Philippines<br>
            International School of Asia and the Pacific
        </div>
    </div>
</body>
</html>