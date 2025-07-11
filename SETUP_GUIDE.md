# ARMIS Staff Authentication Setup Guide

## Overview
The ARMIS system has been successfully refactored to use Staff table-based authentication, removing all UserSpice dependencies. This guide will help you complete the setup and test the new authentication system.

## Setup Steps

### 1. Database Setup
First, run the database schema updates to add the required authentication fields:

```sql
-- Run this in your MySQL database
SOURCE update_staff_table.sql;
```

Or manually execute the SQL commands from `update_staff_table.sql` in your database management tool.

### 2. Database Configuration
Update the database configuration in `staff_auth_standalone.php` (lines 8-15):

```php
$DB_CONFIG = [
    'host' => 'localhost',          // Your database host
    'port' => '3306',               // Your database port
    'dbname' => 'armis',            // Your database name
    'username' => 'root',           // Your database username
    'password' => '',               // Your database password
    'charset' => 'utf8mb4'
];
```

### 3. Create Initial Admin User
Since UserSpice users are no longer used, you'll need to create an initial admin user in the Staff table. Run this SQL to create a test admin:

```sql
INSERT INTO staff (
    svcNo, username, password, role, email, fname, lname, 
    accStatus, must_reset_password
) VALUES (
    'ADMIN001',                                              -- Service Number
    'admin',                                                 -- Username
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',  -- Password: 'password'
    'Admin',                                                 -- Role
    'admin@armis.mil',                                       -- Email
    'System',                                                -- First Name
    'Administrator',                                         -- Last Name
    'Active',                                                -- Account Status
    1                                                        -- Must reset password
);
```

### 4. Test Login
1. Navigate to your ARMIS installation
2. Go to `login.php`
3. Use credentials:
   - Username: `admin`
   - Password: `password`
4. You should be forced to reset the password on first login

## Key Features

### Security Features
- **Account Lockout**: 5 failed attempts locks account for 15 minutes
- **Password Requirements**: 8+ characters with uppercase, lowercase, numbers, and special characters
- **Session Security**: 30-minute timeout, secure session management
- **CSRF Protection**: All forms protected against cross-site request forgery
- **Password Reset**: Secure tokens with 1-hour expiry

### User Roles
- **Admin**: Full system access
- **Admin Branch**: Administrative branch management
- **User**: Standard user access

### Authentication Flow
1. Login with username/password
2. If new user (must_reset_password = 1), forced to reset password
3. Session created with role-based permissions
4. Access control based on user role

## File Structure

### Core Authentication Files
- `staff_auth_standalone.php` - Main authentication system
- `login.php` - Login page and processing
- `logout.php` - Logout functionality
- `reset_password.php` - Password reset page

### Updated Application Files
- `users/admin.php` - Admin dashboard
- `users/admin_branch.php` - Admin Branch dashboard  
- `users/account_staff.php` - User account page
- `users/admin_branch/create_staff.php` - Staff creation

### Database Files
- `update_staff_table.sql` - Schema updates
- `armis.sql` - Complete database schema

## Usage Examples

### Creating New Staff Members
Admin users can create new staff through `users/admin_branch/create_staff.php`:
1. Fill out staff information form
2. System generates temporary password
3. New staff member must reset password on first login

### Protecting Pages
Add this to the top of any page that requires authentication:

```php
<?php
require_once 'staff_auth_standalone.php';
requireLogin();  // Requires any authenticated user

// Or for specific roles:
requireAdmin();        // Admin only
requireAdminBranch();  // Admin Branch or higher
?>
```

### Getting Current User Info
```php
$user = getCurrentUser();
echo "Welcome " . $user['fname'] . " " . $user['lname'];
echo "Role: " . $user['role'];
```

## Troubleshooting

### Common Issues
1. **Database Connection Errors**
   - Check database credentials in `staff_auth_standalone.php`
   - Verify database server is running
   - Ensure `armis` database exists

2. **Login Fails**
   - Verify staff record exists in database
   - Check `accStatus` is 'Active'
   - Reset failed login attempts: `UPDATE staff SET failed_login_attempts = 0 WHERE username = 'admin'`

3. **Permission Denied**
   - Check user role in staff table
   - Verify page protection requirements match user role

4. **Session Issues**
   - Clear browser cookies
   - Check PHP session configuration
   - Verify session directory is writable

### Maintenance
- Monitor failed login attempts regularly
- Clean expired reset tokens: `UPDATE staff SET reset_token = NULL WHERE reset_token_expiry < NOW()`
- Archive old logs periodically
- Review user roles and permissions

## Migration Notes
This system completely replaces UserSpice authentication. The original UserSpice files in the `users/` directory are preserved but no longer used for authentication. You may remove them once you've verified the new system works correctly.

## Support
- Check `README.md` for complete API documentation
- Review application logs for debugging information
- All authentication activities are logged in the `logs` table