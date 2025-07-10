<?php
// Sidebar component for ARMIS admin dashboard
// Includes navigation, user profile, and system information

$current_user = getCurrentUser();
$current_page = basename($_SERVER['PHP_SELF']);
?>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand">
            <i class="fas fa-shield-alt"></i>
            <span>ARMIS</span>
        </div>
        <button class="sidebar-toggle" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </button>
    </div>
    
    <div class="sidebar-user">
        <div class="user-avatar">
            <i class="fas fa-user-circle"></i>
        </div>
        <div class="user-info">
            <div class="user-name"><?php echo htmlspecialchars($current_user['username']); ?></div>
            <div class="user-role">Administrator</div>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        <ul class="nav-list">
            <li class="nav-item">
                <a href="admin.php" class="nav-link <?php echo $current_page === 'admin.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="employees.php" class="nav-link <?php echo $current_page === 'employees.php' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    <span>Employees</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="admin_branch.php" class="nav-link <?php echo $current_page === 'admin_branch.php' ? 'active' : ''; ?>">
                    <i class="fas fa-cogs"></i>
                    <span>Admin Branch</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="command_reports.php" class="nav-link <?php echo $current_page === 'command_reports.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="training/courses.php" class="nav-link <?php echo strpos($current_page, 'courses') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-graduation-cap"></i>
                    <span>Training</span>
                </a>
            </li>
            
            <li class="nav-divider"></li>
            
            <li class="nav-item">
                <a href="#" class="nav-link" onclick="toggleSubmenu('staffSubmenu')">
                    <i class="fas fa-user-tie"></i>
                    <span>Staff Management</span>
                    <i class="fas fa-chevron-down nav-arrow"></i>
                </a>
                <ul class="nav-submenu" id="staffSubmenu">
                    <li><a href="admin_branch/create_staff.php">Create Staff</a></li>
                    <li><a href="admin_branch/edit_staff.php">Edit Staff</a></li>
                    <li><a href="admin_branch/promote_staff.php">Promote Staff</a></li>
                    <li><a href="admin_branch/delete_staff.php">Delete Staff</a></li>
                </ul>
            </li>
            
            <li class="nav-item">
                <a href="#" class="nav-link" onclick="toggleSubmenu('reportsSubmenu')">
                    <i class="fas fa-file-alt"></i>
                    <span>Reports</span>
                    <i class="fas fa-chevron-down nav-arrow"></i>
                </a>
                <ul class="nav-submenu" id="reportsSubmenu">
                    <li><a href="admin_branch/reports_seniority.php">Seniority Roll</a></li>
                    <li><a href="admin_branch/reports_units.php">Unit List</a></li>
                    <li><a href="admin_branch/reports_rank.php">List by Ranks</a></li>
                    <li><a href="admin_branch/reports_corps.php">List by Corps</a></li>
                    <li><a href="admin_branch/reports_gender.php">List by Gender</a></li>
                    <li><a href="admin_branch/reports_appointment.php">List by Appointment</a></li>
                </ul>
            </li>
            
            <li class="nav-item">
                <a href="#" class="nav-link" onclick="toggleSubmenu('medalsSubmenu')">
                    <i class="fas fa-medal"></i>
                    <span>Awards & Medals</span>
                    <i class="fas fa-chevron-down nav-arrow"></i>
                </a>
                <ul class="nav-submenu" id="medalsSubmenu">
                    <li><a href="admin_branch/medals.php">Create Medals</a></li>
                    <li><a href="admin_branch/assign_medal.php">Assign Medals</a></li>
                    <li><a href="admin_branch/appointments.php">Appointments</a></li>
                </ul>
            </li>
            
            <li class="nav-divider"></li>
            
            <li class="nav-item">
                <a href="system_settings.php" class="nav-link <?php echo $current_page === 'system_settings.php' ? 'active' : ''; ?>">
                    <i class="fas fa-cog"></i>
                    <span>System Settings</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="user_profile.php" class="nav-link <?php echo $current_page === 'user_profile.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user"></i>
                    <span>Profile</span>
                </a>
            </li>
        </ul>
    </nav>
    
    <div class="sidebar-footer">
        <div class="system-info">
            <div class="info-item">
                <i class="fas fa-server"></i>
                <span>System Online</span>
            </div>
            <div class="info-item">
                <i class="fas fa-users"></i>
                <span><?php echo getTotalUsers(); ?> Users</span>
            </div>
        </div>
        <a href="../logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</div>

<script>
function toggleSubmenu(submenuId) {
    const submenu = document.getElementById(submenuId);
    const arrow = submenu.previousElementSibling.querySelector('.nav-arrow');
    
    if (submenu.classList.contains('active')) {
        submenu.classList.remove('active');
        arrow.style.transform = 'rotate(0deg)';
    } else {
        // Close all other submenus
        document.querySelectorAll('.nav-submenu').forEach(menu => {
            menu.classList.remove('active');
        });
        document.querySelectorAll('.nav-arrow').forEach(arr => {
            arr.style.transform = 'rotate(0deg)';
        });
        
        submenu.classList.add('active');
        arrow.style.transform = 'rotate(180deg)';
    }
}

document.getElementById('sidebarToggle').addEventListener('click', function() {
    document.getElementById('sidebar').classList.toggle('collapsed');
    document.querySelector('.main-content').classList.toggle('expanded');
});
</script>

<?php
// Helper function to get total users (placeholder)
function getTotalUsers() {
    // In a real application, this would query the database
    return 1; // For now, just return 1 (the admin user)
}
?>