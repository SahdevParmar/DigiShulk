<?php
// Shared Responsive App Navigation
// Replaces admin_sidebar.php and inspector_nav.php
// Desktop: Left sidebar for both roles
// Mobile: Top bar + Bottom nav for both roles
// Role only changes menu items, not layout

$user_role = $_SESSION['role'] ?? 'guest';
$user_name = htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User');
?>

<!-- Desktop Sidebar (both roles) -->
<aside class="app-sidebar" id="appSidebar" aria-label="Main navigation">
    <a href="<?php echo $user_role === 'admin' ? 'admin_dashboard.php' : 'dashboard.php'; ?>" class="sidebar-brand" aria-label="DigiShulk Home">
        <div class="logo" aria-hidden="true"></div>
    </a>

    <nav class="sidebar-nav" role="navigation" aria-label="Primary">
        <ul class="nav-list">
            <?php if ($user_role === 'admin'): ?>
                <li>
                    <a href="admin_dashboard.php" class="nav-link" data-page="dashboard">
                        <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="add_inspector.php" class="nav-link" data-page="inspectors">
                        <i class="fa-solid fa-users-gear" aria-hidden="true"></i>
                        <span>Manage Inspectors</span>
                    </a>
                </li>
                <li>
                    <a href="history.php" class="nav-link" data-page="history">
                        <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                        <span>History</span>
                    </a>
                </li>
            <?php elseif ($user_role === 'inspector'): ?>
                <li>
                    <a href="dashboard.php" class="nav-link" data-page="dashboard">
                        <i class="fa-solid fa-house" aria-hidden="true"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="spot_tax.php" class="nav-link" data-page="spot-tax">
                        <i class="fa-solid fa-receipt" aria-hidden="true"></i>
                        <span>New Spot Tax</span>
                    </a>
                </li>
                <li>
                    <a href="seizure_form.php" class="nav-link" data-page="seizure">
                        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                        <span>New Seizure</span>
                    </a>
                </li>
                <li>
                    <a href="history.php" class="nav-link" data-page="history">
                        <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                        <span>History</span>
                    </a>
                </li>
            <?php endif; ?>
            <li>
                <a href="settings.php" class="nav-link" data-page="profile">
                    <i class="fa-solid fa-user" aria-hidden="true"></i>
                    <span>Profile</span>
                </a>
            </li>
        </ul>
    </nav>

    <div class="sidebar-footer">
        <div class="user-profile" role="region" aria-label="User profile">
            <a href="settings.php" class="profile-link" aria-label="Profile settings">
                <img src="<?php echo $userPhoto; ?>" class="nav-profile-photo" alt="" aria-hidden="true">
                <div class="profile-info">
                    <span class="profile-name"><?php echo $user_name; ?></span>
                    <span class="profile-role"><?php echo ucfirst($user_role); ?></span>
                </div>
            </a>
        </div>
        <a href="logout.php" class="nav-link logout-link" aria-label="Sign out">
            <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
            <span>Logout</span>
        </a>
    </div>
</aside>

<!-- Desktop Top Bar (both roles) -->
<header class="app-topbar desktop" role="banner">
    <div class="topbar-start">
        <a href="<?php echo $user_role === 'admin' ? 'admin_dashboard.php' : 'dashboard.php'; ?>" class="topbar-brand" aria-label="DigiShulk Home">
            <div class="logo" aria-hidden="true"></div>
        </a>
    </div>

    <div class="topbar-center">
        <div class="topbar-search">
            <button class="search-btn" id="openSearch" aria-label="Search (Ctrl+K)" type="button">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            </button>
            <input type="text" id="spotlightDesktop" placeholder="Search shops, transactions, inspectors..." class="form-input" autocomplete="off" aria-label="Search" style="display: none;">
        </div>
    </div>

    <div class="topbar-end">
        <div class="topbar-profile">
            <button class="profile-trigger" id="profileTrigger" aria-label="Profile menu" aria-expanded="false" aria-haspopup="true" type="button">
                <img src="<?php echo $userPhoto; ?>" class="nav-profile-photo" alt="" aria-hidden="true">
                <span class="profile-name"><?php echo $user_name; ?></span>
                <i class="fa-solid fa-chevron-down caret" aria-hidden="true"></i>
            </button>
            <div class="profile-dropdown" id="profileDropdown" role="menu" aria-label="Profile menu">
                <div class="dropdown-header">
                    <div class="dropdown-user-info">
                        <img src="<?php echo $userPhoto; ?>" class="nav-profile-photo" alt="" aria-hidden="true">
                        <div class="dropdown-user-details">
                            <div class="dropdown-user-name"><?php echo $user_name; ?></div>
                            <div class="dropdown-user-role"><?php echo ucfirst($user_role); ?></div>
                        </div>
                    </div>
                </div>
                <div class="dropdown-divider"></div>
                <a href="settings.php" class="dropdown-item" role="menuitem">
                    <i class="fa-solid fa-user" aria-hidden="true"></i>
                    Profile
                </a>
                <a href="settings.php" class="dropdown-item" role="menuitem">
                    <i class="fa-solid fa-lock" aria-hidden="true"></i>
                    Account
                </a>
                <div class="dropdown-divider"></div>
                <a href="logout.php" class="dropdown-item danger" role="menuitem">
                    <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
                    Logout
                </a>
            </div>
        </div>
    </div>
</header>

<!-- Mobile Top Bar (both roles) -->
<header class="app-topbar mobile" role="banner">
    <div class="topbar-start">
        <a href="<?php echo $user_role === 'admin' ? 'admin_dashboard.php' : 'dashboard.php'; ?>" class="topbar-brand" aria-label="DigiShulk Home">
            <div class="logo" aria-hidden="true"></div>
        </a>
    </div>

    <div class="topbar-center">
        <button class="search-btn" id="openSearch" aria-label="Search (Ctrl+K)" type="button">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        </button>
    </div>

    <div class="topbar-end">
        <div class="profile-avatar" aria-hidden="true">
            <img src="<?php echo $userPhoto; ?>" class="nav-profile-photo" alt="">
        </div>
    </div>
</header>

<!-- Mobile Bottom Navigation (both roles) -->
<nav class="app-bottom-nav" role="navigation" aria-label="Primary mobile navigation">
    <?php if ($user_role === 'admin'): ?>
        <a href="admin_dashboard.php" class="bottom-nav-item" data-page="dashboard">
            <span class="nav-icon"><i class="fa-solid fa-chart-line" aria-hidden="true"></i></span>
            <span>Dashboard</span>
        </a>
        <a href="add_inspector.php" class="bottom-nav-item" data-page="inspectors">
            <span class="nav-icon"><i class="fa-solid fa-users-gear" aria-hidden="true"></i></span>
            <span>Inspectors</span>
        </a>
        <a href="history.php" class="bottom-nav-item" data-page="history">
            <span class="nav-icon"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i></span>
            <span>History</span>
        </a>
        <a href="settings.php" class="bottom-nav-item" data-page="profile">
            <span class="nav-icon"><i class="fa-solid fa-user" aria-hidden="true"></i></span>
            <span>Profile</span>
        </a>
    <?php elseif ($user_role === 'inspector'): ?>
        <a href="dashboard.php" class="bottom-nav-item" data-page="dashboard">
            <span class="nav-icon"><i class="fa-solid fa-house" aria-hidden="true"></i></span>
            <span>Home</span>
        </a>
        <a href="spot_tax.php" class="bottom-nav-item" data-page="spot-tax">
            <span class="nav-icon"><i class="fa-solid fa-receipt" aria-hidden="true"></i></span>
            <span>Collect</span>
        </a>
        <a href="seizure_form.php" class="bottom-nav-item" data-page="seizure">
            <span class="nav-icon"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></span>
            <span>Seizure</span>
        </a>
        <a href="history.php" class="bottom-nav-item" data-page="history">
            <span class="nav-icon"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i></span>
            <span>History</span>
        </a>
        <a href="settings.php" class="bottom-nav-item" data-page="profile">
            <span class="nav-icon"><i class="fa-solid fa-user" aria-hidden="true"></i></span>
            <span>Profile</span>
        </a>
    <?php endif; ?>
</nav>

<!-- Sidebar Overlay (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>

<script>
// Sidebar toggle for mobile
(function() {
    const sidebar = document.getElementById('appSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const toggle = document.getElementById('sidebarToggle');
    
    if (!sidebar || !overlay || !toggle) return;
    
    function openSidebar() {
        sidebar.classList.add('is-open');
        overlay.classList.add('is-visible');
        toggle.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
        // Focus trap - focus first focusable element
        const firstLink = sidebar.querySelector('.nav-link');
        if (firstLink) firstLink.focus();
    }
    
    function closeSidebar() {
        sidebar.classList.remove('is-open');
        overlay.classList.remove('is-visible');
        toggle.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
        toggle.focus();
    }
    
    toggle.addEventListener('click', function() {
        if (sidebar.classList.contains('is-open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    });
    
    overlay.addEventListener('click', closeSidebar);
    
    // Close on Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar.classList.contains('is-open')) {
            closeSidebar();
        }
    });
    
    // Close sidebar when clicking a nav link on mobile
    sidebar.querySelectorAll('.nav-link').forEach(function(link) {
        link.addEventListener('click', function() {
            if (window.innerWidth < 769) {
                closeSidebar();
            }
        });
    });
    
    // Handle resize
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 769 && sidebar.classList.contains('is-open')) {
            closeSidebar();
        }
    });
})();

// Desktop Search Toggle
(function() {
    const searchBtn = document.getElementById('openSearch');
    const spotlightDesktop = document.getElementById('spotlightDesktop');
    
    if (searchBtn && spotlightDesktop) {
        searchBtn.addEventListener('click', function() {
            const isVisible = spotlightDesktop.style.display !== 'none';
            spotlightDesktop.style.display = isVisible ? 'none' : 'block';
            if (!isVisible) {
                spotlightDesktop.focus();
            }
        });
    }
    
    // Hide desktop search on Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && spotlightDesktop && spotlightDesktop.style.display !== 'none') {
            spotlightDesktop.style.display = 'none';
        }
    });
})();

// Profile Dropdown
(function() {
    const trigger = document.getElementById('profileTrigger');
    const dropdown = document.getElementById('profileDropdown');
    
    if (!trigger || !dropdown) return;
    
    function toggleDropdown() {
        const isOpen = dropdown.classList.contains('open');
        dropdown.classList.toggle('open');
        trigger.setAttribute('aria-expanded', !isOpen);
    }
    
    trigger.addEventListener('click', function(e) {
        e.stopPropagation();
        toggleDropdown();
    });
    
    // Close on click outside
    document.addEventListener('click', function(e) {
        if (!trigger.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.remove('open');
            trigger.setAttribute('aria-expanded', 'false');
        }
    });
    
    // Close on Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && dropdown.classList.contains('open')) {
            dropdown.classList.remove('open');
            trigger.setAttribute('aria-expanded', 'false');
            trigger.focus();
        }
    });
    
    // Keyboard navigation within dropdown
    dropdown.addEventListener('keydown', function(e) {
        if (e.key === 'Tab') {
            const focusableItems = dropdown.querySelectorAll('[role="menuitem"]');
            const firstItem = focusableItems[0];
            const lastItem = focusableItems[focusableItems.length - 1];
            
            if (e.shiftKey && document.activeElement === firstItem) {
                e.preventDefault();
                lastItem.focus();
            } else if (!e.shiftKey && document.activeElement === lastItem) {
                e.preventDefault();
                firstItem.focus();
            }
        }
    });
})();

// Notification badge demo (remove in production)
(function() {
    const badge = document.getElementById('notificationBadge');
    if (badge) {
        // Demo: show badge after 2 seconds
        setTimeout(() => {
            badge.style.display = 'flex';
        }, 2000);
    }
})();

// Active page highlighting
(function() {
    const currentPath = window.location.pathname.split('/').pop() || 'dashboard.php';
    const pageMap = {
        'admin_dashboard.php': 'dashboard',
        'dashboard.php': 'dashboard',
        'add_inspector.php': 'inspectors',
        'edit_inspector.php': 'inspectors',
        'spot_tax.php': 'spot-tax',
        'seizure_form.php': 'seizure',
        'history.php': 'history',
        'settings.php': 'profile',
        'payment.php': 'spot-tax',
        'confirm_cash.php': 'spot-tax',
        'generate_receipt_pdf.php': 'history',
        'export_tax_excel.php': 'history',
        'export_tax_pdf.php': 'history',
        'export_seizures_excel.php': 'history',
        'export_seizures_pdf.php': 'history'
    };
    const currentPage = pageMap[currentPath] || 'dashboard';
    
    document.querySelectorAll('[data-page]').forEach(function(el) {
        if (el.dataset.page === currentPage) {
            el.classList.add('active');
            el.setAttribute('aria-current', 'page');
        }
    });
})();
</script>