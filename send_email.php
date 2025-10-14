<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';
require_once 'config.php';

function sendVerificationEmail($toEmail, $toName, $verificationCode) {
    $mail = new PHPMailer(true);
    
    try {
        // $mail->SMTPDebug = 2; // Uncomment this line to see detailed SMTP errors
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Verify Your Email - MCNP-ISAP Service Portal';
        
        // Create verification link with both email and code
        $verification_link = SITE_URL . "/verify.php?email=" . urlencode($toEmail) . "&code=" . $verificationCode;
        
        // Email body with nice styling
        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .code-box { background: white; border: 2px dashed #667eea; padding: 20px; text-align: center; margin: 20px 0; border-radius: 8px; }
                .code { font-size: 32px; font-weight: bold; letter-spacing: 8px; color: #667eea; }
                .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
                .button { display: inline-block; padding: 12px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .or-divider { text-align: center; margin: 20px 0; font-weight: bold; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Email Verification</h1>
                    <p>MCNP-ISAP Service Portal</p>
                </div>
                <div class='content'>
                    <h2>Hello, $toName!</h2>
                    <p>Thank you for registering with the MCNP-ISAP Service Portal. To complete your registration, please verify your email address.</p>
                    
                    <div class='code-box'>
                        <p style='margin: 0; font-size: 14px; color: #666;'>Your Verification Code:</p>
                        <div class='code'>$verificationCode</div>
                    </div>
                    
                    <p>Enter this code on the verification page to activate your account.</p>
                    
                    <div class='or-divider'>OR</div>
                    
                    <p style='text-align: center;'>
                        <a href='$verification_link' class='button'>Click here to verify automatically</a>
                    </p>
                    
                    <p><strong>This code will expire in 10 minutes.</strong></p>
                    
                    <p style='font-size: 12px; color: #666; margin-top: 30px;'>
                        If you didn't create an account, please ignore this email.
                    </p>
                </div>
                <div class='footer'>
                    <p>Medical Colleges of Northern Philippines<br>
                    International School of Asia and the Pacific</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        // Plain text version with the link
        $mail->AltBody = "Hello $toName,\n\nYour verification code is: $verificationCode\n\nOr click this link to verify automatically: $verification_link\n\nThis code will expire in 10 minutes.\n\nThank you,\nMCNP-ISAP Service Portal";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: {$mail->ErrorInfo}");
        error_log("Exception message: " . $e->getMessage());
        return false;
    }
}

function sendWelcomeEmail($toEmail, $toName) {
    $mail = new PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        
        $mail->isHTML(true);
        $mail->Subject = 'Welcome to MCNP-ISAP Service Portal';
        
        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Welcome!</h1>
                </div>
                <div class='content'>
                    <h2>Hello, $toName!</h2>
                    <p>Your email has been successfully verified. Your account is now pending admin approval.</p>
                    <p>You will receive another email notification once your account has been approved by the administrator.</p>
                    <p>Thank you for your patience!</p>
                </div>
                <div class='footer'>
                    <p>Medical Colleges of Northern Philippines<br>
                    International School of Asia and the Pacific</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Welcome email failed: {$mail->ErrorInfo}");
        return false;
    }
}
// Add this function to your existing send_email.php file
function sendPasswordResetEmail($toEmail, $toName, $verificationCode) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset - MCNP-ISAP Service Portal';
        
        // Email body with nice styling
        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .code-box { background: white; border: 2px dashed #667eea; padding: 20px; text-align: center; margin: 20px 0; border-radius: 8px; }
                .code { font-size: 32px; font-weight: bold; letter-spacing: 8px; color: #667eea; }
                .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
                .button { display: inline-block; padding: 12px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Password Reset</h1>
                    <p>MCNP-ISAP Service Portal</p>
                </div>
                <div class='content'>
                    <h2>Hello, $toName!</h2>
                    <p>We received a request to reset your password for the MCNP-ISAP Service Portal.</p>
                    
                    <div class='code-box'>
                        <p style='margin: 0; font-size: 14px; color: #666;'>Your Password Reset Code:</p>
                        <div class='code'>$verificationCode</div>
                    </div>
                    
                    <p>Enter this code on the password reset page to set a new password.</p>
                    <p><strong>This code will expire in 10 minutes.</strong></p>
                    
                    <p style='text-align: center;'>
                        <a href='" . SITE_URL . "/reset_password.php?email=" . urlencode($toEmail) . "' class='button'>Reset Password Now</a>
                    </p>
                    
                    <p style='font-size: 12px; color: #666; margin-top: 30px;'>
                        If you didn't request a password reset, please ignore this email.
                    </p>
                </div>
                <div class='footer'>
                    <p>Medical Colleges of Northern Philippines<br>
                    International School of Asia and the Pacific</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        // Plain text version
        $mail->AltBody = "Hello $toName,\n\nYour password reset code is: $verificationCode\n\nThis code will expire in 10 minutes.\n\nThank you,\nMCNP-ISAP Service Portal";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Password reset email failed: {$mail->ErrorInfo}");
        return false;
    }
}
?>
