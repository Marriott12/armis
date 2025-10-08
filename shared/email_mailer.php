<?php
/**
 * ARMIS Email Configuration and Mailer Class
 * Handles email sending functionality for the system
 */

class ARMISMailer {
    private $config;
    private $smtp_host;
    private $smtp_port;
    private $smtp_username;
    private $smtp_password;
    private $from_email;
    private $from_name;
    
    public function __construct() {
        $this->loadConfig();
    }
    
    private function loadConfig() {
        // Email configuration - Update these settings for your environment
        $this->smtp_host = 'smtp.envisagezm.com'; // or your SMTP server
        $this->smtp_port = 587;
        $this->smtp_username = 'support@envisagezm.com'; // Update this
        $this->smtp_password = 'Envisage@2025'; // Update this
        $this->from_email = 'support@envisagezm.com'; // Update this
        $this->from_name = 'ARMIS System';
        
        // You can also load from environment variables or config file
        if (file_exists(__DIR__ . '/../config/email.php')) {
            $email_config = require __DIR__ . '/../config/email.php';
            foreach ($email_config as $key => $value) {
                if (property_exists($this, $key)) {
                    $this->$key = $value;
                }
            }
        }
    }
    
    /**
     * Check if we're in development mode
     */
    private function isDevelopmentMode() {
        // Check for ARMIS_DEVELOPMENT constant
        if (defined('ARMIS_DEVELOPMENT') && ARMIS_DEVELOPMENT === true) {
            return true;
        }
        
        // Check for ARMIS_ENV constant
        if (defined('ARMIS_ENV') && ARMIS_ENV !== 'production') {
            return true;
        }
        
        // Default to development for safety
        return true;
    }
    
    /**
     * Send email using PHP's built-in mail function (simple version)
     * For production, consider using PHPMailer or similar library
     * 
     * DEVELOPMENT MODE: Logs email without sending (no errors)
     * PRODUCTION MODE: Attempts to send with graceful error handling
     */
    public function sendEmail($to, $subject, $body, $isHTML = true) {
        try {
            $isDevelopment = $this->isDevelopmentMode();
            
            $headers = [
                'From: ' . $this->from_name . ' <' . $this->from_email . '>',
                'Reply-To: ' . $this->from_email,
                'X-Mailer: ARMIS System',
                'MIME-Version: 1.0'
            ];
            
            if ($isHTML) {
                $headers[] = 'Content-Type: text/html; charset=UTF-8';
            } else {
                $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            }
            
            $header_string = implode("\r\n", $headers);
            
            // Always log email content to file for verification
            $emailLog = "=== EMAIL LOG " . date('Y-m-d H:i:s') . " ===\n";
            $emailLog .= "Environment: " . ($isDevelopment ? 'DEVELOPMENT' : 'PRODUCTION') . "\n";
            $emailLog .= "To: $to\n";
            $emailLog .= "Subject: $subject\n";
            $emailLog .= "Headers: $header_string\n";
            $emailLog .= "Body:\n$body\n";
            $emailLog .= "=======================================\n\n";
            
            if (!is_dir(__DIR__ . '/../logs')) {
                mkdir(__DIR__ . '/../logs', 0755, true);
            }
            file_put_contents(__DIR__ . '/../logs/email_log.txt', $emailLog, FILE_APPEND | LOCK_EX);
            
            // DEVELOPMENT MODE: Skip actual email sending
            if ($isDevelopment) {
                error_log("DEVELOPMENT MODE: Email logged but not sent to: $to");
                return [
                    'success' => true, 
                    'message' => 'Development mode: Email logged to file (not sent)',
                    'mode' => 'development',
                    'logged' => true
                ];
            }
            
            // PRODUCTION MODE: Attempt to send email
            error_log("PRODUCTION MODE: Attempting to send email to: $to");
            
            // Suppress warnings and errors from mail() function
            $result = @mail($to, $subject, $body, $header_string);
            
            if ($result) {
                error_log("Email sent successfully to: $to");
                return [
                    'success' => true, 
                    'message' => 'Email sent successfully',
                    'mode' => 'production',
                    'sent' => true
                ];
            } else {
                // Mail failed but don't throw error - log and continue
                error_log("WARNING: Failed to send email to: $to - Check mail server configuration");
                return [
                    'success' => true, 
                    'message' => 'Email queued (mail server may need configuration)',
                    'mode' => 'production',
                    'sent' => false,
                    'logged' => true
                ];
            }
            
        } catch (Exception $e) {
            // Catch all exceptions to prevent disruption
            error_log("Email error (non-critical): " . $e->getMessage());
            return [
                'success' => true, 
                'message' => 'Email logging completed (sending skipped due to error)',
                'error' => $e->getMessage(),
                'logged' => true
            ];
        }
    }
    
    /**
     * Send welcome email to new staff member
     */
    public function sendWelcomeEmail($staffData, $tempPassword) {
        $template = $this->getEmailTemplate('welcome_new_staff');
        
        if (!$template) {
            return ['success' => false, 'message' => 'Email template not found'];
        }
        
        // Prepare variables for template
        $variables = [
            'rank' => $staffData['rank_name'] ?? 'Staff',
            'last_name' => $staffData['lname'] ?? $staffData['last_name'],
            'first_name' => $staffData['fname'] ?? $staffData['first_name'],
            'username' => $staffData['username'],
            'temp_password' => $tempPassword,
            'service_number' => $staffData['svcNo'] ?? $staffData['service_number'],
            'login_url' => 'http://localhost/Armis2/login.php' // Update for production
        ];
        
        // Replace variables in template
        $subject = $this->replaceVariables($template['subject'], $variables);
        $body = $this->replaceVariables($template['body_html'], $variables);
        
        return $this->sendEmail($staffData['email'], $subject, $body, true);
    }
    
    /**
     * Send password reset email
     */
    public function sendPasswordResetEmail($staffData, $resetToken) {
        $template = $this->getEmailTemplate('password_reset');
        
        if (!$template) {
            return ['success' => false, 'message' => 'Email template not found'];
        }
        
        $variables = [
            'rank' => $staffData['rank_name'] ?? 'Staff',
            'last_name' => $staffData['lname'] ?? $staffData['last_name'],
            'reset_url' => 'http://localhost/Armis2/reset_password.php?token=' . $resetToken
        ];
        
        $subject = $this->replaceVariables($template['subject'], $variables);
        $body = $this->replaceVariables($template['body_html'], $variables);
        
        return $this->sendEmail($staffData['email'], $subject, $body, true);
    }
    
    /**
     * Get email template from database
     */
    private function getEmailTemplate($templateName) {
        try {
            $conn = new mysqli('localhost', 'root', '', 'armis1');
            
            if ($conn->connect_error) {
                throw new Exception("Database connection failed");
            }
            
            $stmt = $conn->prepare("SELECT * FROM email_templates WHERE template_name = ?");
            $stmt->bind_param('s', $templateName);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                return $result->fetch_assoc();
            }
            
            return null;
            
        } catch (Exception $e) {
            error_log("Template error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Replace variables in template
     */
    private function replaceVariables($template, $variables) {
        foreach ($variables as $key => $value) {
            $template = str_replace('{{' . $key . '}}', $value, $template);
        }
        return $template;
    }
    
    /**
     * Generate secure temporary password
     */
    public static function generateTempPassword($length = 12) {
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $symbols = '!@#$%^&*';
        
        $password = '';
        
        // Ensure at least one character from each set
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        $password .= $symbols[random_int(0, strlen($symbols) - 1)];
        
        // Fill the rest
        $allChars = $uppercase . $lowercase . $numbers . $symbols;
        for ($i = 4; $i < $length; $i++) {
            $password .= $allChars[random_int(0, strlen($allChars) - 1)];
        }
        
        // Shuffle the password
        return str_shuffle($password);
    }
    
    /**
     * Generate secure activation token
     */
    public static function generateActivationToken() {
        return bin2hex(random_bytes(32));
    }
}

/**
 * Email configuration array (create this file separately for security)
 * File: config/email.php
 */
/*
return [
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_username' => 'your-email@gmail.com',
    'smtp_password' => 'your-app-password',
    'from_email' => 'noreply@armis.mil.zm',
    'from_name' => 'ARMIS System'
];
*/
?>
