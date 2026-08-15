<?php
// Admin Sidebar Navigation
?>
<div class="admin-sidebar">
    <a href="admin_dashboard.php" class="logo-link">
        <div class="logo"></div>
    </a>
    <nav class="sidebar-nav">
        <a href="admin_dashboard.php"><i class="fa-solid fa-chart-line" aria-hidden="true"></i> Dashboard</a>
        <a href="add_inspector.php"><i class="fa-solid fa-users-gear" aria-hidden="true"></i> Manage Inspectors</a>
        <a href="history.php"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> History</a>
        <a href="settings.php"><i class="fa-solid fa-gear" aria-hidden="true"></i> Settings</a>
    </nav>
    <div class="sidebar-footer">
        <div class="profile-row">
            <a href="settings.php" class="profile-link">
                <img src="<?php echo $userPhoto; ?>" class="nav-profile-photo" alt="Profile">
                <span><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?></span>
            </a>
        </div>
        <a href="logout.php" class="logout-btn"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i> Logout</a>
    </div>
</div>
<div class="admin-mobile-topbar">
    <div class="logo"></div>
    <!-- In the future, we can add a hamburger menu icon here -->
</div>
