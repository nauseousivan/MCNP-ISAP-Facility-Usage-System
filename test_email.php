<?php
require_once 'config.php';

echo "<h2>Email Configuration Test</h2>";
echo "<pre>";

// Check if PHPMailer is installed
if (file_exists('vendor/autoload.php')) {
    echo "✓ PHPMailer is installed\n\n";
    
    require 'vendor/autoload.php';
    require_once 'send_email.php';
    
    // Display current configuration (hide password)
    echo "Current Email Configuration:\n";
    echo "SMTP Host: " . SMTP_HOST . "\n";
    echo "SMTP Port: " . SMTP_PORT . "\n";
    echo "SMTP Username: " . SMTP_USERNAME . "\n";
    echo "SMTP Password: " . (SMTP_PASSWORD ? str_repeat('*', strlen(SMTP_PASSWORD)) : 'NOT SET') . "\n";
    echo "From Email: " . SMTP_FROM_EMAIL . "\n";
    echo "From Name: " . SMTP_FROM_NAME . "\n\n";
    
    // Check if config is set
    if (SMTP_USERNAME === 'your-email@gmail.com' || SMTP_PASSWORD === 'your-app-password') {
        echo "❌ ERROR: You need to update config.php with your actual Gmail credentials!\n";
        echo "\nSteps to fix:\n";
        echo "1. Open config.php\n";
        echo "2. Replace 'your-email@gmail.com' with your actual Gmail address\n";
        echo "3. Replace 'your-app-password' with your Gmail App Password\n";
        echo "4. Save the file and try again\n";
    } else {
        echo "✓ Configuration appears to be set\n\n";
        
        // Test sending email
        if (isset($_GET['test']) && isset($_GET['email'])) {
            echo "Attempting to send test email to: " . $_GET['email'] . "\n\n";
            
            $result = sendVerificationEmail($_GET['email'], 'Test User', '123456');
            
            if ($result) {
                echo "✓ SUCCESS! Email sent successfully!\n";
                echo "Check your inbox (and spam folder) for the verification email.\n";
            } else {
                echo "❌ FAILED! Could not send email.\n";
                echo "Check the error log above for details.\n";
            }
        } else {
            echo "To test sending an email, add ?test=1&email=your-email@gmail.com to the URL\n";
            echo "Example: test_email.php?test=1&email=ivan.manalo205@gmail.com\n";
        }
    }
} else {
    echo "❌ PHPMailer is NOT installed!\n\n";
    echo "To install PHPMailer:\n";
    echo "1. Open Command Prompt (CMD) or Terminal\n";
    echo "2. Navigate to your project folder:\n";
    echo "   cd C:\\xampp\\htdocs\\MCNP-ISAP-Facility-Usage-System\n";
    echo "3. Run: composer require phpmailer/phpmailer\n";
    echo "4. Wait for installation to complete\n";
    echo "5. Refresh this page\n";
}

echo "</pre>";
?>
