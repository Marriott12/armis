# ARMIS - Army Resource Management Information System

## Overview
ARMIS (Army Resource Management Information System) is a comprehensive web-based platform designed for military personnel management, training coordination, operations planning, and administrative tasks.

## System Structure
The ARMIS system is organized into several functional modules:

- **Admin**: Central administration and system management
- **Command**: Command-level personnel and operation management
- **Training**: Course management and training record tracking
- **Operations**: Operation planning and coordination
- **Finance**: Financial management and reporting
- **Ordinance**: Equipment and asset tracking

## Technical Information

### Requirements
- PHP 7.4+
- MySQL 5.7+
- Web server (Apache/Nginx)
- Modern web browser

### Installation
1. Clone the repository to your web server directory
2. Import the database schema from `/database/`
3. Configure database credentials in `/config/database.php`
4. Navigate to the system URL and log in with admin credentials

## Security Features

### Authentication
The system uses role-based access control (RBAC) to manage permissions across different modules and user types.

### File Management
- Secure file upload validation
- Prevention of deleted file restoration
- Automatic blocking of security-sensitive files

### Recent Updates

#### System Cleanup (August 2025)
- Removed unused test files, debug files, and documentation
- Implemented prevention mechanisms for file restoration
- Enhanced security validation in file uploads
- For details on removed files, see `CLEANUP_DOCUMENTATION.md`

## Documentation
For detailed documentation on specific components:

- Database Schema: See `/database/README.md`
- System Cleanup: See `/CLEANUP_DOCUMENTATION.md`
- Dashboard Structure: See `/admin_branch/DASHBOARD_STRUCTURE_ANALYSIS.md`

## Support
For technical assistance, contact the system administrator at admin@yourunit.mil.
