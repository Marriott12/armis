# ARMIS - Army Resource Management Information System

## Authentication System Overview

ARMIS has been refactored to use a Staff table-based authentication system, replacing the previous UserSpice dependency. This system provides secure, role-based access control with forced password resets and comprehensive audit logging.

## Database Schema

### Staff Table Structure

The `staff` table serves as the primary authentication source with the following key fields:

```sql
CREATE TABLE `staff` (
  `svcNo` varchar(10) NOT NULL PRIMARY KEY,       -- Service Number (unique identifier)
  `username` varchar(25) DEFAULT NULL,            -- Login username
  `password` varchar(255) NOT NULL,               -- Hashed password
  `role` varchar(25) NOT NULL,                    -- User role (Admin, Admin Branch, etc.)
  `email` varchar(25) DEFAULT NULL,               -- Email address
  `fname` varchar(50) DEFAULT NULL,               -- First name
  `lname` varchar(35) DEFAULT NULL,               -- Last name
  `accStatus` enum('Active','Inactive') DEFAULT 'Inactive',  -- Account status
  
  -- Authentication & Security Fields
  `must_reset_password` TINYINT(1) NOT NULL DEFAULT 0,      -- Force password reset flag
  `reset_token` VARCHAR(255) NULL DEFAULT NULL,             -- Password reset token
  `reset_token_expiry` DATETIME NULL DEFAULT NULL,          -- Token expiry time
  `last_login` DATETIME NULL DEFAULT NULL,                  -- Last login timestamp
  `failed_login_attempts` INT(11) NOT NULL DEFAULT 0,       -- Failed login counter
  `locked_until` DATETIME NULL DEFAULT NULL,                -- Account lockout time
  
  -- Audit Fields
  `createdBy` varchar(15) DEFAULT NULL,           -- Who created this record
  `dateCreated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `renewDate` date DEFAULT NULL,                  -- Password renewal date
  
  -- Staff Information Fields
  `rankID` varchar(10) DEFAULT NULL,              -- Military rank
  `gender` varchar(6) DEFAULT NULL,               -- Gender
  `DOB` date DEFAULT NULL,                        -- Date of birth
  `province` varchar(12) DEFAULT NULL,            -- Province
  `tel` varchar(10) DEFAULT NULL,                 -- Phone number
  `category` varchar(150) DEFAULT NULL,           -- Staff category
  `unitID` varchar(25) DEFAULT NULL,              -- Unit assignment
  `svcStatus` varchar(30) DEFAULT NULL,           -- Service status
  -- ... additional staff fields
);
```

## Authentication Workflow

### 1. User Login Process

1. User submits username/password via `login.php`
2. System validates credentials against `staff` table
3. Password verification using PHP's `password_verify()`
4. Account status and lockout checks performed
5. Session created with user data if successful
6. Failed attempts tracked and account locked after 5 failures

### 2. Password Reset Process

**For new staff members:**
1. Admin creates staff member with temporary password
2. `must_reset_password` flag set to 1
3. User forced to reset password on first login

**For forgotten passwords:**
1. User requests password reset
2. Secure token generated and stored in `reset_token`
3. Email sent with reset link (token expires in 1 hour)
4. User submits new password using token
5. Password updated and token cleared

### 3. Session Management

- Sessions timeout after 30 minutes of inactivity
- Session data includes: svcNo, username, role, permissions
- CSRF tokens protect against cross-site request forgery
- Session regeneration on login for security

## User Roles and Permissions

### Defined Roles

- **Admin**: Full system administration access
- **Admin Branch**: Administrative branch management
- **User**: Standard user access
- **[Custom roles as needed]**

### Permission Checking

```php
// Check if user is logged in
if (!isLoggedIn()) {
    redirectToLogin();
}

// Require specific role
requireAdmin();           // Admin role required
requireAdminBranch();     // Admin Branch role required
requireLogin();           // Any authenticated user

// Check permissions
if (isAdmin()) {
    // Admin-specific functionality
}

if (hasRole('Admin Branch')) {
    // Admin Branch functionality
}
```

## Key Authentication Files

### Core Files

- `staff_auth.php` - Main authentication system
- `login.php` - Login page and processing
- `logout.php` - Logout functionality  
- `reset_password.php` - Password reset for logged-in users
- `update_staff_table.sql` - Database schema updates

### API Functions

#### Authentication Functions

```php
// Login/Logout
loginUser($username, $password)          // Authenticate user
logoutUser()                            // Clear session and logout
getCurrentUser()                        // Get current user data
isLoggedIn()                           // Check if user authenticated

// Password Management
generateResetToken($svcNo)             // Create password reset token
validateResetToken($token)             // Validate reset token
resetPassword($token, $newPassword)    // Reset password with token
mustResetPassword()                    // Check if password reset required

// Staff Management
createStaff($staffData, $createdBy)    // Create new staff member
generateTempPassword($length)          // Generate temporary password

// Permission Checking
hasRole($role)                         // Check user role
isAdmin()                             // Check if admin
isAdminBranch()                       // Check if admin branch
canAccessPage($page)                  // Check page access

// Security Functions
requireLogin()                         // Enforce login requirement
requireAdmin()                         // Enforce admin role
requireAdminBranch()                   // Enforce admin branch role
checkSessionTimeout()                  // Validate session timeout
```

#### Utility Functions

```php
// Messaging
setMessage($message, $type)            // Set flash message
displayMessages()                      // Display and clear messages

// Navigation
redirect($url)                         // Redirect to URL
redirectToDashboard()                  // Smart dashboard redirect
getLoginUrl()                         // Get appropriate login URL

// CSRF Protection
CSRFToken::generate()                  // Generate CSRF token
CSRFToken::validate($token)            // Validate CSRF token
CSRFToken::getField()                  // Get hidden form field

// Logging
logActivity($svcNo, $type, $note)     // Log user activity
logFailedLogin($username, $reason)     // Log failed login attempt
```

## Security Features

### Password Security
- Minimum 8 characters with complexity requirements
- Uppercase, lowercase, numbers, and special characters required
- PHP `password_hash()` with default algorithm (bcrypt)
- Automatic password strength validation

### Account Security
- Account lockout after 5 failed login attempts (15-minute lockout)
- Failed login attempt logging and monitoring
- Password reset tokens expire after 1 hour
- Session timeout after 30 minutes of inactivity

### Data Protection
- CSRF token protection on all forms
- SQL injection protection with prepared statements
- XSS protection with proper input sanitization
- Session security with httpOnly and secure cookies

## Usage Examples

### Creating a New Staff Member

```php
$staffData = [
    'svcNo' => '12345',
    'fname' => 'John',
    'lname' => 'Doe', 
    'username' => 'jdoe',
    'email' => 'john.doe@military.com',
    'role' => 'User',
    'rankID' => '15',
    'gender' => 'Male',
    'category' => 'Officer'
];

$result = createStaff($staffData, $currentUser['svcNo']);

if ($result['success']) {
    echo "Staff created! Temp password: " . $result['tempPassword'];
}
```

### Protecting a Page

```php
<?php
require_once '../staff_auth.php';

// Require login for any access
requireLogin();

// Or require specific role
requireAdminBranch();

$user = getCurrentUser();
?>
<!DOCTYPE html>
<html>
<head><title>Protected Page</title></head>
<body>
    <h1>Welcome <?php echo htmlspecialchars($user['fname']); ?>!</h1>
    <?php displayMessages(); ?>
    <!-- Page content -->
</body>
</html>
```

### Password Reset Flow

```php
// 1. User requests reset (generate token)
$token = generateResetToken($svcNo);
// Send $token via email

// 2. User submits new password
if (resetPassword($_POST['token'], $_POST['new_password'])) {
    setMessage('Password reset successful', 'success');
}
```

## Migration Notes

### From UserSpice to Staff Authentication

1. **Database Changes**: Run `update_staff_table.sql` to add required fields
2. **File Updates**: Replace `require_once 'users/init.php'` with `require_once 'staff_auth.php'`
3. **Function Mapping**:
   - `$user->data()` → `getCurrentUser()`
   - `loggedIn()` → `isLoggedIn()`
   - `hasPerm()` → `hasRole()` or `isAdmin()`
   - `securePage()` → `requireLogin()`

### Removed Dependencies
- UserSpice user management system
- UserSpice session handling
- UserSpice permission system
- Complex UserSpice configuration files

## Troubleshooting

### Common Issues

1. **Database Connection Errors**
   - Verify database credentials in `users/init.php`
   - Ensure staff table exists with required fields

2. **Session Issues**
   - Check PHP session configuration
   - Verify session cookies are enabled
   - Clear browser cookies and try again

3. **Permission Denied**
   - Check user role assignments in staff table
   - Verify role-based permission logic
   - Check page protection requirements

4. **Password Reset Problems**
   - Verify email configuration for token delivery
   - Check token expiry times
   - Ensure reset_token fields exist in database

### Logging and Debugging

- Activity logs stored in `logs` table
- Failed login attempts logged automatically
- Enable error logging in PHP for detailed debugging
- Use `displayMessages()` to show user feedback

## Maintenance

### Regular Tasks

1. **Password Expiry**: Monitor `renewDate` field for password expiry
2. **Account Cleanup**: Remove inactive accounts periodically  
3. **Log Rotation**: Archive old entries in `logs` table
4. **Security Audit**: Review failed login patterns
5. **Token Cleanup**: Clear expired reset tokens

### Database Maintenance

```sql
-- Clean expired reset tokens
UPDATE staff SET reset_token = NULL, reset_token_expiry = NULL 
WHERE reset_token_expiry < NOW();

-- Reset failed login attempts for unlocked accounts
UPDATE staff SET failed_login_attempts = 0 
WHERE locked_until < NOW() OR locked_until IS NULL;

-- Archive old logs (example: older than 1 year)
DELETE FROM logs WHERE logdate < DATE_SUB(NOW(), INTERVAL 1 YEAR);
```

This authentication system provides a robust, secure foundation for ARMIS while maintaining simplicity and performance.