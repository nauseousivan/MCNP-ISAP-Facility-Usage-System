<?php
session_start();

if (!isset($_SESSION['splash_seen'])) {
    header("Location: splash.php");
    exit();
}

require_once 'config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['register'])) {
        $first_name = $_POST['first_name'];
        $last_name = $_POST['last_name'];
        $email = $_POST['email'];
        $department = $_POST['department'];
        $program = $_POST['program'];
        $user_type = $_POST['user_type'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

        $full_name = $first_name . " " . $last_name;
        $verification_code = sprintf("%06d", mt_rand(1, 999999));
        
        $sql = "INSERT INTO users (name, email, department, program, password, user_type, verification_code, verified, created_at) 
                VALUES ('$full_name', '$email', '$department', '$program', '$password', '$user_type', '$verification_code', 0, NOW())";
        
        // In your registration section, replace the email sending part:
if ($conn->query($sql) === TRUE) {
    // Check if PHPMailer is installed
    if (!file_exists('vendor/autoload.php')) {
        $error = "PHPMailer is not installed! Please run: composer require phpmailer/phpmailer";
    } else {
        require_once 'send_email.php';
        
        // Try to send email and capture any errors
        $emailSent = sendVerificationEmail($email, $full_name, $verification_code);
        
        if ($emailSent) {
            $_SESSION['verification_code'] = $verification_code;
            $_SESSION['verify_email'] = $email;
            $_SESSION['registration_department'] = $department; // Save department for verify page
            $_SESSION['email_sent'] = true;
            header("Location: verify.php");
            exit();
        } else {
            // Show detailed error message
            $error = "Account created successfully! However, we couldn't send the verification email. ";
            $error .= "Please check your config.php file and make sure your Gmail App Password is correct. ";
            $error .= "Your verification code is: <strong>$verification_code</strong> (You can use this to verify manually)";
        }
    }
} else {
    $error = "Error: " . $conn->error;
}
    }
    if (isset($_POST['login'])) {
        $email = $_POST['email'];
        $password = $_POST['password'];
        
        $sql = "SELECT * FROM users WHERE email='$email'";
        $result = $conn->query($sql);
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                if ($user['verified'] == 1) {
                    if ($user['is_active'] == 1) { // Check if account is active
                        if ($user['approved'] == 1) {
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['user_name'] = $user['name'];
                            $_SESSION['user_type'] = $user['user_type'];
                            $_SESSION['user_department'] = $user['department'];
                            header("Location: dashboard.php");
                            exit();
                        } else {
                            $error = "Your account is pending admin approval.";
                        }
                    } else {
                        $error = "Your account has been deactivated. Please contact an administrator.";
                    }
                } else {
                    $error = "Please verify your email first.";
                }
            } else {
                $error = "Invalid password.";
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
    <title>MCNP-ISAP Facility Usage Portal</title>
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
            overflow: hidden;
        }
        
        .tabs {
            display: flex;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .tab {
            flex: 1;
            padding: 20px;
            text-align: center;
            font-size: 16px;
            font-weight: 600;
            color: #6b7280;
            background: none;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
        }
        
        .tab:hover {
            color: #1a1a1a;
            background: #f9fafb;
        }
        
        .tab.active {
            color: #1a1a1a;
        }
        
        .tab.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            right: 0;
            height: 2px;
            background: #1a1a1a;
        }
        
        .tab-content {
            display: none;
            padding: 32px;
        }
        
        .tab-content.active {
            display: block;
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
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
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
        input[type="email"],
        input[type="password"],
        select {
            width: 100%;
            padding: 12px 16px;
            font-size: 15px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            transition: all 0.2s;
            font-family: inherit;
        }
        
        input:focus,
        select:focus {
            outline: none;
            border-color: #1a1a1a;
            box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.05);
        }
        
        input::placeholder {
            color: #9ca3af;
        }
        
        select {
            cursor: pointer;
            background: white;
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
        
        .forgot-password {
            text-align: right;
            margin-top: -12px;
            margin-bottom: 20px;
        }
        
        .forgot-password a {
            font-size: 14px;
            color: #6b7280;
            text-decoration: none;
        }
        
        .forgot-password a:hover {
            color: #1a1a1a;
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
        
        .switch-auth {
            text-align: center;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #e5e7eb;
            font-size: 14px;
            color: #6b7280;
        }
        
        .switch-auth a {
            color: #1a1a1a;
            font-weight: 600;
            text-decoration: none;
        }
        
        .switch-auth a:hover {
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
        
        @media (max-width: 640px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .logo-section h1 {
                font-size: 22px;
            }
            
            .tab-content {
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
            <!-- Tabs -->
            <div class="tabs">
                <button class="tab active" onclick="switchTab('login')">Sign In</button>
                <button class="tab" onclick="switchTab('register')">Register</button>
            </div>
            
            <!-- Login Tab -->
            <div id="login-tab" class="tab-content active">
                <div class="tab-header">
                    <h2>Welcome back</h2>
                    <p>Sign in to your Service Portal account</p>
                </div>
                
                <?php if (isset($error) && isset($_POST['login'])): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" placeholder="student@mcnp.edu.ph or student@isap.edu.ph" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Password</label>
                        <div class="password-toggle">
                            <input type="password" name="password" id="login-password" placeholder="Enter your password" required>
                            <button type="button" onclick="togglePassword('login-password', this)">
                                <svg class="eye-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    
                    <div class="forgot-password">
                        <a href="forgot_password.php">Forgot password?</a>
                    </div>
                    
                    <button type="submit" name="login" class="btn-primary">Sign In</button>
                </form>
                
                <div class="switch-auth">
                    Don't have an account? <a href="#" onclick="switchTab('register'); return false;">Register here</a>
                </div>
            </div>
            
            <!-- Register Tab -->
            <div id="register-tab" class="tab-content">
                <div class="tab-header">
                    <h2>Create Account</h2>
                    <p>Join the MCNP-ISAP Facility Usage Portal community</p>
                </div>
                
                <?php if (isset($error) && isset($_POST['register'])): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="" id="register-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label>First Name</label>
                            <input type="text" name="first_name" placeholder="Juan" required>
                        </div>
                        <div class="form-group">
                            <label>Last Name</label>
                            <input type="text" name="last_name" placeholder="Dela Cruz" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" placeholder="student@mcnp.edu.ph or student@isap.edu.ph" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Department</label>
                            <select name="department" id="department" required>
                                <option value="">Select department</option>
                                <option value="Medical Colleges of Northern Philippines">Medical Colleges of Northern Philippines</option>
                                <option value="International School of Asia and the Pacific">International School of Asia and the Pacific</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Program/Position</label>
                            <select name="program" id="program" required>
                                <option value="">Select program</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>User Type</label>
                        <select name="user_type" required>
                            <option value="">Select user type</option>
                            <option value="Student">Student</option>
                            <option value="Faculty">Faculty</option>
                            <option value="Staff">Staff</option>
                            <option value="Admin">Admin</option>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Password</label>
                            <div class="password-toggle">
                                <input type="password" name="password" id="register-password" placeholder="Create password" required>
                                <button type="button" onclick="togglePassword('register-password', this)">
                                    <svg class="eye-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Confirm Password</label>
                            <div class="password-toggle">
                                <input type="password" name="confirm_password" id="confirm-password" placeholder="Confirm password" required>
                                <button type="button" onclick="togglePassword('confirm-password', this)">
                                    <svg class="eye-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" name="register" class="btn-primary">Create Account</button>
                </form>
                
                <div class="switch-auth">
                    Already have an account? <a href="#" onclick="switchTab('login'); return false;">Sign in here</a>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            Medical Colleges of Northern Philippines<br>
            International School of Asia and the Pacific
        </div>
    </div>

    <script>
        // Tab switching
        function switchTab(tabName) {
            // Update tab buttons
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // Update tab content
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            document.getElementById(tabName + '-tab').classList.add('active');
        }
        
        // Password toggle
        function togglePassword(inputId, button) {
            const input = document.getElementById(inputId);
            const isPassword = input.type === 'password';
            
            input.type = isPassword ? 'text' : 'password';
            
            // Update icon
            if (isPassword) {
                // Show eye-slash icon (password is visible)
                button.innerHTML = `
                    <svg class="eye-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                    </svg>
                `;
            } else {
                // Show eye icon (password is hidden)
                button.innerHTML = `
                    <svg class="eye-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                `;
            }
        }
        
        const programOptions = {
            "Medical Colleges of Northern Philippines": [
                "BS Radiologic Technology",
                "BS Nursing", 
                "BS Medical Technology",
                "BS Physical Therapy",
                "BS Pharmacy",
                "BS Midwifery",
                "BS 2-year Dental Technology",
                "BS 2-year Pharmacy Aide",
                "BS Caregiving and TVET Course"
            ],
            "International School of Asia and the Pacific": [
                "BS Information Technology",
                "BS Computer Engineering",
                "BS Business Administration",
                "BS Custom Administration",
                "BS Hospitality Management",
                "BS Tourism Management",
                "BS Accountancy",
                "BS Education",
                "BS Science Criminology",
                "BS Science in Social Work",
                "BS Secondary Education",
                "BS Science in Psychology",
                "BS Physical Education"
            ]
        };
        
        // Update program options when department changes
        document.getElementById('department').addEventListener('change', function() {
            const department = this.value;
            const programSelect = document.getElementById('program');
            
            programSelect.innerHTML = '<option value="">Select program</option>';
            
            if (department && programOptions[department]) {
                programOptions[department].forEach(program => {
                    const option = document.createElement('option');
                    option.value = program;
                    option.textContent = program;
                    programSelect.appendChild(option);
                });
            }
        });
        
        // Form validation
        document.getElementById('register-form').addEventListener('submit', function(e) {
            const password = this.querySelector('input[name="password"]').value;
            const confirmPassword = this.querySelector('input[name="confirm_password"]').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
        });
    </script>
    <?php include 'chat_bot.php'; ?>
</body>
</html>