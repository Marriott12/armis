# ARMIS - Army Resource Management Information System

**ALWAYS follow these instructions first and only fallback to additional search or context gathering if the information here is incomplete or found to be in error.**

## Working Effectively

### Bootstrap and Build (NEVER CANCEL - Set 60+ minute timeouts)
```bash
# Required software installation
sudo systemctl start mysql
docker --version  # Docker 28.0.4+ required
docker compose version  # v2.38.2+ required  
php -v  # PHP 8.3+ required
mysql --version  # MySQL 8.0+ required

# Database setup (takes 60 seconds total - NEVER CANCEL)
time docker run -d --name armis-mysql -e MYSQL_ROOT_PASSWORD=root123 -e MYSQL_DATABASE=armis1 -p 3306:3306 mysql:8.0
# Wait 30 seconds for MySQL initialization - NEVER CANCEL
sleep 30

# Create database structure (takes 0.1 seconds)
time mysql -h 127.0.0.1 -P 3306 -u root -proot123 armis1 -e "
CREATE TABLE IF NOT EXISTS staff (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    role ENUM('admin', 'user', 'manager') DEFAULT 'user',
    accStatus ENUM('active', 'inactive') DEFAULT 'active',
    service_number VARCHAR(50),
    rank_id INT,
    unit_id INT,
    corps VARCHAR(100),
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS ranks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    abbreviation VARCHAR(10)
);
CREATE TABLE IF NOT EXISTS units (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL,
    code VARCHAR(20)
);
CREATE TABLE IF NOT EXISTS corps (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    abbreviation VARCHAR(10)
);
INSERT IGNORE INTO staff (username, password, first_name, last_name, email, role, accStatus) 
VALUES ('admin', '\$2y\$10\$.T/BwYsngu9zCjwIUj3R6uO1ga6yRHCcxNFaf7zIpqw4hAeIcGeHC', 'Admin', 'User', 'admin@example.com', 'admin', 'active');
"
```

### Configuration Setup
```bash
# Update database configuration to use TCP instead of socket
# Edit /config.php and /config/enhanced_config.php:
# Change DB_HOST from 'localhost' to '127.0.0.1'
# Set DB_NAME to 'armis1'
# Set DB_USER to 'root'  
# Set DB_PASS to 'root123'

# Fix syntax error in enhanced_config.php line 262:
# Change: self::$config['features'] => [
# To:     self::$config['features'] = [

# Update shared/database_connection.php line 29:
# Change: $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
# To:     $dsn = "mysql:host=" . DB_HOST . ";port=3306;dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
```

### Run the Application (takes 3 seconds - NEVER CANCEL)
```bash
# Start PHP development server (timeout: 180 seconds)
php -S localhost:8080 -t . > /tmp/php_server.log 2>&1 &

# Wait for server startup (3 seconds)
sleep 3

# Verify application is running
curl -I http://localhost:8080
# Should return HTTP/1.1 302 Found (redirect to login)
```

## Validation Scenarios

### CRITICAL: Complete End-to-End User Validation
**ALWAYS test these scenarios after making changes:**

1. **Login Flow Test:**
   ```bash
   # Test login page loads
   curl -s http://localhost:8080/login.php | grep -q "ARMIS" && echo "✓ Login page loads"
   
   # Test admin authentication (credentials: admin / armis2025)
   curl -X POST -d "username=admin&password=armis2025" -c /tmp/cookies.txt -L -s http://localhost:8080/login.php | grep -q "admin" && echo "✓ Login successful"
   ```

2. **Module Access Test:**
   ```bash
   # Test admin dashboard access
   curl -s -b /tmp/cookies.txt http://localhost:8080/admin/index.php | grep -q "DOCTYPE html" && echo "✓ Admin module loads"
   
   # Test command module
   curl -s -b /tmp/cookies.txt http://localhost:8080/command/index.php | grep -q "DOCTYPE html" && echo "✓ Command module loads"
   
   # Test training module  
   curl -s -b /tmp/cookies.txt http://localhost:8080/training/index.php | grep -q "DOCTYPE html" && echo "✓ Training module loads"
   
   # Test operations module
   curl -s -b /tmp/cookies.txt http://localhost:8080/operations/index.php | grep -q "DOCTYPE html" && echo "✓ Operations module loads"
   ```

3. **Database Connectivity Test:**
   ```bash
   # Test database connection
   php -r "
   require_once 'config.php';
   try {
       \$pdo = new PDO('mysql:host=' . DB_HOST . ';port=3306;dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS);
       echo '✓ Database connection successful\n';
   } catch (Exception \$e) {
       echo '✗ Database connection failed: ' . \$e->getMessage() . '\n';
       exit(1);
   }
   "
   ```

## System Architecture

### Core Structure
- **Backend:** PHP 8.3+ with PDO database connections
- **Database:** MySQL 8.0+ with structured schemas per module  
- **Frontend:** JavaScript, CSS, HTML with Bootstrap 5.3
- **Authentication:** Role-Based Access Control (RBAC) system
- **Deployment:** Docker Compose multi-container setup

### Modules
- **Admin** (`/admin/`): Central administration and system management
- **Command** (`/command/`): Command-level personnel and operation management  
- **Training** (`/training/`): Course management and training record tracking
- **Operations** (`/operations/`): Operation planning and coordination
- **Finance** (`/finance/`): Financial management and reporting
- **Ordinance** (`/ordinance/`): Equipment and asset tracking

### Key Files
- `/config.php` - Main configuration with database credentials
- `/config/enhanced_config.php` - Advanced configuration management
- `/shared/database_connection.php` - Centralized database connectivity
- `/shared/rbac.php` - Role-based access control system
- `/docker-compose.yml` - Multi-container orchestration
- `/scripts/backup.sh` - Automated backup system

## Development Guidelines

### Making Changes
- **ALWAYS test the complete login → module access flow after changes**
- **NEVER modify database credentials without updating both config files**
- **ALWAYS use prepared statements for database queries**
- **ALWAYS validate user input and sanitize output**

### Common Troubleshooting
- **Database connection fails:** Ensure MySQL container is running and use `127.0.0.1` not `localhost`
- **Login redirects to 404:** Check that URL paths match the application structure
- **Permission errors:** Verify user has appropriate role in staff table
- **Module not loading:** Check that all required database tables exist

### File Structure Reference
```
/
├── admin/           # Administration module
├── admin_branch/    # Enhanced admin functionality  
├── command/         # Command operations module
├── training/        # Training management module
├── operations/      # Operations planning module
├── finance/         # Financial management module
├── ordinance/       # Equipment tracking module
├── shared/          # Shared utilities and components
├── config/          # Configuration files
├── database/        # Database schemas and migrations
├── scripts/         # Maintenance and backup scripts
├── api/             # API endpoints and controllers
├── uploads/         # File upload directory
└── docker/          # Docker configuration files
```

### Security Considerations
- All passwords are hashed with PHP's `password_hash()`
- RBAC system controls module access by user role
- CSRF protection enabled for forms
- File upload validation and virus scanning
- Security audit logging for sensitive operations
- Session management with secure cookie settings

### Performance Notes
- Database queries use prepared statements for security and performance
- Static assets are cached with appropriate headers
- Compression enabled for text-based content
- Connection pooling for database connections
- Optimized indexes on frequently queried tables

## Demo Credentials
**System Administrator:** `admin` / `armis2025` (Full system access)
**Command Officers:** `commander` / `commander123`, `trainer` / `trainer123`  
**Staff Members:** `staff1` / `staff123`, `staff2` / `staff456`

## Timing Expectations (NEVER CANCEL)
- **Database container start:** 1 second
- **Database initialization:** 30 seconds - NEVER CANCEL
- **Schema loading:** 0.1 seconds  
- **PHP server startup:** 3 seconds
- **Total application ready time:** 35 seconds - NEVER CANCEL
- **Login flow test:** 2 seconds
- **Module access test:** 1 second per module

**CRITICAL REMINDER:** All build and startup operations complete within 60 seconds. NEVER cancel or timeout commands before this duration. Set timeouts to 180+ seconds for safety.