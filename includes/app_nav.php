<?php
/**
 * app_nav.php — Shared responsive navigation for DigiShulk.
 * Loaded by header.php. Handles both admin and inspector roles.
 */

require_once __DIR__ . '/helpers/csrf.php';

$user_role = $_SESSION['role'] ?? 'guest';
$user_name = htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User');

if (!isset($userPhoto) || $userPhoto === '') {
    $userPhoto = 'uploads/profile/default.jpg';
}

$is_admin  = ($user_role === 'admin');
$is_insp   = ($user_role === 'inspector');
$home_link = $is_admin ? 'admin_dashboard.php' : 'dashboard.php';

$logo_url = 'css/layout/logo.png';
?>

<style>
:root {
    --nav-navy:     #14285a;
    --nav-navy-2:   #0e1e42;
    --nav-navy-3:   #0a1730;
    --nav-blue:     #1e50a2;
    --nav-blue-2:   #3b82f6;
    --nav-green:    #3ba55c;
    --nav-gold:     #f0a020;
    --nav-ink:      #0f172a;
    --nav-ink-2:    #475569;
    --nav-ink-3:    #94a3b8;
    --nav-line:     #e5e7eb;
    --nav-paper:    #ffffff;
    --nav-bg:       #f8fafc;
    --nav-ease: cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes navFadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes navIconBounce {
    0%   { transform: translateY(0); }
    40%  { transform: translateY(-3px); }
    100% { transform: translateY(0); }
}
@keyframes navShimmer {
    0%   { background-position: -200% 0; }
    100% { background-position:  200% 0; }
}
@keyframes navLogoGlow {
    0%, 100% { opacity: 0.45; transform: scale(1); }
    50%      { opacity: 0.8;  transform: scale(1.06); }
}

@media (prefers-reduced-motion: reduce) {
    .app-sidebar *, .app-topbar *, .app-bottom-nav *, .profile-dropdown * {
        animation: none !important;
        transition: none !important;
    }
}

/* ================= SIDEBAR ================= */
.app-sidebar {
    position: fixed;
    top: 0; left: 0;
    width: 260px;
    height: 100vh;
    background: linear-gradient(180deg,
        var(--nav-navy) 0%,
        var(--nav-navy-2) 55%,
        var(--nav-navy-3) 100%);
    display: flex;
    flex-direction: column;
    z-index: 100;
    overflow-y: auto;
    overflow-x: hidden;
    border-right: 1px solid rgba(148, 163, 184, 0.08);
    animation: navFadeIn 0.35s ease-out both;
}
.app-sidebar .sidebar-nav { padding: 22px 12px 14px; flex: 1; }
.app-sidebar .nav-list {
    list-style: none; margin: 0; padding: 0;
    display: flex; flex-direction: column; gap: 4px;
}
.app-sidebar .nav-list li { margin: 0; }
.app-sidebar .nav-link {
    position: relative;
    display: flex; align-items: center; gap: 12px;
    padding: 11px 14px;
    border-radius: 10px;
    color: rgba(226, 232, 240, 0.7);
    text-decoration: none;
    font-weight: 500; font-size: 0.9rem;
    transition: background 0.22s var(--nav-ease), color 0.22s var(--nav-ease);
    overflow: hidden;
}
.app-sidebar .nav-link::before {
    content: '';
    position: absolute; left: 0; top: 50%;
    transform: translateY(-50%) scaleY(0);
    width: 3px; height: 60%;
    background: linear-gradient(180deg, var(--nav-blue-2), var(--nav-green));
    border-radius: 0 4px 4px 0;
    transition: transform 0.3s var(--nav-ease);
}
.app-sidebar .nav-link i {
    width: 20px; text-align: center; font-size: 0.95rem;
    transition: transform 0.3s var(--nav-ease), color 0.22s ease;
}
.app-sidebar .nav-link:hover {
    background: rgba(255, 255, 255, 0.05);
    color: #ffffff;
}
.app-sidebar .nav-link:hover::before { transform: translateY(-50%) scaleY(1); }
.app-sidebar .nav-link:hover i { transform: scale(1.12); color: var(--nav-blue-2); }
.app-sidebar .nav-link.active {
    background: linear-gradient(135deg, rgba(30, 80, 162, 0.45), rgba(59, 165, 92, 0.20));
    color: #ffffff;
    box-shadow:
        0 8px 20px -10px rgba(30, 80, 162, 0.7),
        inset 0 0 0 1px rgba(59, 165, 92, 0.28);
}
.app-sidebar .nav-link.active::before { transform: translateY(-50%) scaleY(1); }
.app-sidebar .nav-link.active i { color: #7dd3a8; }

.app-sidebar .sidebar-footer {
    padding: 14px 12px 18px;
    border-top: 1px solid rgba(148, 163, 184, 0.10);
    display: flex; flex-direction: column; gap: 6px;
}
.app-sidebar .profile-link {
    display: flex; align-items: center; gap: 12px;
    padding: 10px 12px; border-radius: 12px;
    text-decoration: none;
    background: rgba(255, 255, 255, 0.04);
    transition: background 0.22s ease, transform 0.22s ease;
}
.app-sidebar .profile-link:hover {
    background: rgba(255, 255, 255, 0.07);
    transform: translateY(-1px);
}
.app-sidebar .nav-profile-photo {
    width: 38px; height: 38px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid rgba(59, 165, 92, 0.45);
    background: var(--nav-navy-2);
    flex-shrink: 0;
    transition: border-color 0.25s ease;
}
.app-sidebar .profile-link:hover .nav-profile-photo { border-color: var(--nav-green); }
.app-sidebar .profile-info { display: flex; flex-direction: column; min-width: 0; }
.app-sidebar .profile-name {
    font-size: 0.85rem; font-weight: 600; color: #ffffff;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.app-sidebar .profile-role {
    font-size: 0.72rem; color: rgba(226, 232, 240, 0.55);
    text-transform: capitalize; margin-top: 1px;
}
.app-sidebar button.nav-link.logout-link {
    width: 100%; background: transparent; border: none;
    cursor: pointer; font-family: inherit; text-align: left;
    color: rgba(226, 232, 240, 0.7);
    padding: 11px 14px; border-radius: 10px;
    display: flex; align-items: center; gap: 12px;
    font-size: 0.9rem; font-weight: 500;
    transition: background 0.22s ease, color 0.22s ease;
}
.app-sidebar button.nav-link.logout-link:hover {
    background: rgba(239, 68, 68, 0.14);
    color: #fca5a5;
}
.app-sidebar button.nav-link.logout-link:hover i {
    transform: translateX(2px); color: #f87171;
}

/* ================= DESKTOP TOPBAR ================= */
.app-topbar.desktop {
    position: fixed;
    top: 0; left: 260px; right: 0;
    height: 64px;
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    align-items: center;
    gap: 16px;
    padding: 0 24px;
    background: var(--nav-paper);
    border-bottom: 1px solid var(--nav-line);
    z-index: 90;
    animation: navFadeIn 0.35s ease-out 0.08s both;
}
.app-topbar.desktop .topbar-start { display: flex; align-items: center; justify-self: start; }
.app-topbar.desktop .topbar-center { display: flex; justify-content: center; min-width: 0; }
.app-topbar.desktop .topbar-end { display: flex; align-items: center; justify-self: end; }

.app-topbar.desktop .topbar-brand {
    display: inline-flex; align-items: center;
    position: relative; text-decoration: none;
    padding: 4px 2px;
}
.app-topbar.desktop .topbar-brand::before {
    content: '';
    position: absolute;
    inset: -10px -24px;
    background: radial-gradient(ellipse at center,
        rgba(59, 165, 92, 0.18) 0%,
        rgba(30, 80, 162, 0.14) 45%,
        transparent 72%);
    filter: blur(20px);
    z-index: -1;
    pointer-events: none;
    animation: navLogoGlow 5s ease-in-out infinite;
}
.app-topbar.desktop .topbar-brand img {
    height: 42px; width: auto; display: block;
    filter: drop-shadow(0 6px 14px rgba(20, 40, 90, 0.12));
    transition: filter 0.28s var(--nav-ease), transform 0.28s var(--nav-ease);
}
.app-topbar.desktop .topbar-brand:hover img {
    filter: drop-shadow(0 10px 22px rgba(20, 40, 90, 0.22));
    transform: translateY(-1px);
}

/* Search icon button (desktop) */
.topbar-search {
    position: relative;
    display: flex; align-items: center; gap: 8px;
    max-width: 560px; width: 100%;
    justify-content: center;
}
.search-btn {
    width: 40px; height: 40px;
    border-radius: 10px;
    background: var(--nav-bg);
    border: 1px solid var(--nav-line);
    color: var(--nav-ink-2);
    cursor: pointer;
    display: inline-flex; align-items: center; justify-content: center;
    transition: background 0.2s ease, transform 0.2s ease,
                border-color 0.2s ease, color 0.2s ease;
    flex-shrink: 0;
}
.search-btn:hover {
    background: rgba(30, 80, 162, 0.08);
    border-color: rgba(30, 80, 162, 0.4);
    color: var(--nav-blue);
    transform: translateY(-1px);
}
.search-btn:hover i { animation: navIconBounce 0.5s var(--nav-ease); }

.topbar-profile { position: relative; }
.profile-trigger {
    display: flex; align-items: center; gap: 10px;
    padding: 5px 12px 5px 5px;
    background: var(--nav-bg);
    border: 1px solid var(--nav-line);
    border-radius: 999px;
    cursor: pointer;
    color: var(--nav-ink);
    font-family: inherit;
    font-size: 0.85rem; font-weight: 600;
    transition: background 0.2s ease, border-color 0.2s ease;
}
.profile-trigger:hover {
    background: rgba(30, 80, 162, 0.06);
    border-color: rgba(30, 80, 162, 0.35);
}
.profile-trigger .nav-profile-photo {
    width: 32px; height: 32px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid rgba(30, 80, 162, 0.28);
    background: var(--nav-bg);
}
.profile-trigger .caret {
    font-size: 0.7rem; color: var(--nav-ink-2);
    transition: transform 0.25s var(--nav-ease);
}
.profile-trigger[aria-expanded="true"] .caret { transform: rotate(180deg); }

/* Dropdown */
.profile-dropdown {
    position: absolute;
    top: calc(100% + 10px); right: 0;
    min-width: 260px;
    background: var(--nav-paper);
    border: 1px solid var(--nav-line);
    border-radius: 14px;
    padding: 8px;
    box-shadow:
        0 20px 48px -16px rgba(15, 23, 42, 0.22),
        0 4px 12px rgba(15, 23, 42, 0.05);
    opacity: 0;
    transform: translateY(-8px) scale(0.98);
    pointer-events: none;
    transition: opacity 0.2s ease, transform 0.2s var(--nav-ease);
    z-index: 200;
}
.profile-dropdown.open {
    opacity: 1;
    transform: translateY(0) scale(1);
    pointer-events: auto;
}
.dropdown-header {
    padding: 12px 12px 10px;
    border-bottom: 1px solid var(--nav-line);
    margin-bottom: 6px;
}
.dropdown-user-info { display: flex; align-items: center; gap: 12px; }
.dropdown-user-info .nav-profile-photo {
    width: 42px; height: 42px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid rgba(30, 80, 162, 0.28);
    background: var(--nav-bg);
}
.dropdown-user-name {
    font-weight: 700; color: var(--nav-ink);
    font-size: 0.9rem;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.dropdown-user-role {
    font-size: 0.72rem; color: var(--nav-ink-2);
    text-transform: capitalize;
}
.dropdown-divider {
    height: 1px; background: var(--nav-line); margin: 6px 4px;
}
.dropdown-item {
    display: flex; align-items: center; gap: 12px;
    padding: 10px 12px; border-radius: 10px;
    color: var(--nav-ink-2);
    text-decoration: none;
    font-size: 0.88rem; font-weight: 500;
    transition: background 0.15s ease, color 0.15s ease;
    cursor: pointer; width: 100%;
    background: transparent; border: none;
    font-family: inherit; text-align: left;
}
.dropdown-item i {
    width: 18px; text-align: center;
    color: var(--nav-ink-3);
    transition: color 0.15s ease, transform 0.2s ease;
}
.dropdown-item:hover {
    background: rgba(30, 80, 162, 0.08);
    color: var(--nav-blue);
}
.dropdown-item:hover i { color: var(--nav-blue); transform: scale(1.1); }
.dropdown-item.danger { color: #dc2626; }
.dropdown-item.danger i { color: #ef4444; }
.dropdown-item.danger:hover {
    background: rgba(239, 68, 68, 0.08);
    color: #b91c1c;
}
.dropdown-item.danger:hover i { color: #dc2626; }
.profile-dropdown form { margin: 0; }

/* ================= MOBILE ================= */
.app-topbar.mobile { display: none; }
.app-bottom-nav   { display: none; }

@media (max-width: 900px) {
    .app-sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s var(--nav-ease);
    }
    .app-sidebar.is-open { transform: translateX(0); }

    .app-topbar.desktop { display: none; }

    .app-topbar.mobile {
        display: grid;
        grid-template-columns: 44px 1fr 44px;
        align-items: center;
        gap: 8px;
        position: fixed;
        top: 0; left: 0; right: 0;
        height: 56px;
        padding: 0 12px;
        background: var(--nav-paper);
        border-bottom: 1px solid var(--nav-line);
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
        z-index: 95;
    }
    .app-topbar.mobile .topbar-start { justify-self: start; display: flex; align-items: center; }
    .app-topbar.mobile .topbar-center { justify-self: center; display: flex; align-items: center; }
    .app-topbar.mobile .topbar-end { justify-self: end; display: flex; align-items: center; }

    .app-topbar.mobile .topbar-brand {
        display: flex; align-items: center;
        text-decoration: none;
        width: 44px; height: 44px;
        border-radius: 10px;
        overflow: hidden;
        background: var(--nav-paper);
    }
    .app-topbar.mobile .topbar-brand img {
        width: 100%; height: 100%;
        object-fit: contain;
        object-position: left center;
        filter: drop-shadow(0 3px 8px rgba(20, 40, 90, 0.10));
    }
    .app-topbar.mobile .search-btn { width: 40px; height: 40px; }

    /* --- FIX: clean circular avatar --- */
    .app-topbar.mobile .topbar-end .profile-avatar {
        width: 40px !important;
        height: 40px !important;
        flex: 0 0 40px !important;
        border-radius: 50% !important;
        overflow: hidden !important;
        background: transparent !important;
        padding: 0 !important;
        margin: 0 !important;
        border: 2px solid rgba(30, 80, 162, 0.28) !important;
        box-sizing: border-box !important;
        display: block !important;
        position: relative !important;
    }
    .app-topbar.mobile .topbar-end .profile-avatar img.nav-profile-photo {
        width: 100% !important;
        height: 100% !important;
        display: block !important;
        object-fit: cover !important;
        object-position: center center !important;
        border-radius: 50% !important;
        border: none !important;
        padding: 0 !important;
        margin: 0 !important;
        background: transparent !important;
        aspect-ratio: 1 / 1 !important;
    }

    /* Bottom nav */
    .app-bottom-nav {
        display: grid;
        grid-auto-flow: column;
        grid-auto-columns: 1fr;
        position: fixed;
        bottom: 0; left: 0; right: 0;
        min-height: 64px;
        padding: 6px 4px calc(env(safe-area-inset-bottom, 6px) + 4px);
        background: var(--nav-paper);
        border-top: 1px solid var(--nav-line);
        box-shadow: 0 -2px 12px -4px rgba(15, 23, 42, 0.06);
        z-index: 95;
        align-items: center;
    }
    .app-bottom-nav .bottom-nav-item {
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        gap: 3px; padding: 6px 2px; border-radius: 12px;
        color: var(--nav-ink-3);
        text-decoration: none;
        font-size: 0.68rem; font-weight: 600;
        letter-spacing: 0.01em;
        transition: color 0.2s ease;
        position: relative;
        text-align: center; line-height: 1.1; min-width: 0;
    }
    .app-bottom-nav .bottom-nav-item .nav-icon {
        display: inline-flex; align-items: center; justify-content: center;
        width: 32px; height: 32px; border-radius: 10px;
        font-size: 1rem;
        transition: background 0.25s ease, transform 0.25s var(--nav-ease), color 0.2s ease;
    }
    .app-bottom-nav .bottom-nav-item:hover { color: var(--nav-ink); }
    .app-bottom-nav .bottom-nav-item:hover .nav-icon {
        background: rgba(30, 80, 162, 0.08);
        transform: translateY(-2px);
    }
    .app-bottom-nav .bottom-nav-item.active { color: var(--nav-blue); }
    .app-bottom-nav .bottom-nav-item.active .nav-icon {
        background: linear-gradient(135deg, rgba(30, 80, 162, 0.16), rgba(59, 165, 92, 0.14));
        color: var(--nav-blue);
        transform: translateY(-2px);
        box-shadow: 0 6px 14px -6px rgba(30, 80, 162, 0.45);
    }
    .app-bottom-nav .bottom-nav-item.active::before {
        content: '';
        position: absolute;
        top: -6px; left: 50%;
        transform: translateX(-50%);
        width: 22px; height: 3px;
        border-radius: 0 0 4px 4px;
        background: linear-gradient(90deg, var(--nav-blue), var(--nav-green));
    }

    .sidebar-overlay {
        position: fixed; inset: 0;
        background: rgba(15, 23, 42, 0.55);
        z-index: 99;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.25s ease;
    }
    .sidebar-overlay.is-visible { opacity: 1; pointer-events: auto; }
}
</style>

<!-- ============ SIDEBAR ============ -->
<aside class="app-sidebar" id="appSidebar" aria-label="Main navigation">
    <nav class="sidebar-nav" role="navigation" aria-label="Primary">
        <ul class="nav-list">
            <?php if ($is_admin): ?>
                <li><a href="admin_dashboard.php" class="nav-link" data-page="dashboard">
                    <i class="fa-solid fa-chart-line" aria-hidden="true"></i><span>Dashboard</span>
                </a></li>
                <li><a href="add_inspector.php" class="nav-link" data-page="inspectors">
                    <i class="fa-solid fa-users-gear" aria-hidden="true"></i><span>Manage Inspectors</span>
                </a></li>
                <li><a href="undercharge_report.php" class="nav-link" data-page="undercharge">
                    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i><span>Undercharges</span>
                </a></li>
                <li><a href="history.php" class="nav-link" data-page="history">
                    <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i><span>History</span>
                </a></li>
            <?php elseif ($is_insp): ?>
                <li><a href="dashboard.php" class="nav-link" data-page="dashboard">
                    <i class="fa-solid fa-house" aria-hidden="true"></i><span>Dashboard</span>
                </a></li>
                <li><a href="spot_tax.php" class="nav-link" data-page="spot-tax">
                    <i class="fa-solid fa-receipt" aria-hidden="true"></i><span>New Spot Tax</span>
                </a></li>
                <li><a href="seizure_form.php" class="nav-link" data-page="seizure">
                    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i><span>New Seizure</span>
                </a></li>
                <li><a href="history.php" class="nav-link" data-page="history">
                    <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i><span>History</span>
                </a></li>
            <?php endif; ?>
            <li><a href="settings.php" class="nav-link" data-page="profile">
                <i class="fa-solid fa-user" aria-hidden="true"></i><span>Profile</span>
            </a></li>
        </ul>
    </nav>

    <div class="sidebar-footer">
        <div class="user-profile" role="region" aria-label="User profile">
            <a href="settings.php" class="profile-link" aria-label="Profile settings">
                <img src="<?= htmlspecialchars($userPhoto, ENT_QUOTES, 'UTF-8') ?>"
                     class="nav-profile-photo" alt="" aria-hidden="true"
                     onerror="this.src='uploads/profile/default.jpg'">
                <div class="profile-info">
                    <span class="profile-name"><?= $user_name ?></span>
                    <span class="profile-role"><?= htmlspecialchars(ucfirst($user_role), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </a>
        </div>

        <form method="POST" action="logout.php" style="margin:0;">
            <?= csrf_field() ?>
            <button type="submit" class="nav-link logout-link" aria-label="Sign out">
                <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
                <span>Logout</span>
            </button>
        </form>
    </div>
</aside>

<!-- ============ DESKTOP TOPBAR ============ -->
<header class="app-topbar desktop" role="banner">
    <div class="topbar-start">
        <a href="<?= $home_link ?>" class="topbar-brand" aria-label="DigiShulk — Home">
            <img src="<?= $logo_url ?>" alt="DigiShulk">
        </a>
    </div>

    <div class="topbar-center">
        <div class="topbar-search">
            <button class="search-btn js-open-search" aria-label="Search (Ctrl+K)" type="button">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            </button>
        </div>
    </div>

    <div class="topbar-end">
        <div class="topbar-profile">
            <button class="profile-trigger" id="profileTrigger"
                    aria-label="Profile menu" aria-expanded="false"
                    aria-haspopup="true" type="button">
                <img src="<?= htmlspecialchars($userPhoto, ENT_QUOTES, 'UTF-8') ?>"
                     class="nav-profile-photo" alt="" aria-hidden="true"
                     onerror="this.src='uploads/profile/default.jpg'">
                <span class="profile-name"><?= $user_name ?></span>
                <i class="fa-solid fa-chevron-down caret" aria-hidden="true"></i>
            </button>

            <div class="profile-dropdown" id="profileDropdown" role="menu" aria-label="Profile menu">
                <div class="dropdown-header">
                    <div class="dropdown-user-info">
                        <img src="<?= htmlspecialchars($userPhoto, ENT_QUOTES, 'UTF-8') ?>"
                             class="nav-profile-photo" alt="" aria-hidden="true"
                             onerror="this.src='uploads/profile/default.jpg'">
                        <div class="dropdown-user-details">
                            <div class="dropdown-user-name"><?= $user_name ?></div>
                            <div class="dropdown-user-role"><?= htmlspecialchars(ucfirst($user_role), ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                    </div>
                </div>
                <div class="dropdown-divider"></div>
                <a href="settings.php" class="dropdown-item" role="menuitem">
                    <i class="fa-solid fa-user" aria-hidden="true"></i> Profile
                </a>
                <a href="settings.php" class="dropdown-item" role="menuitem">
                    <i class="fa-solid fa-lock" aria-hidden="true"></i> Account
                </a>
                <div class="dropdown-divider"></div>
                <form method="POST" action="logout.php" style="margin:0;">
                    <?= csrf_field() ?>
                    <button type="submit" class="dropdown-item danger" role="menuitem">
                        <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i> Logout
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>

<!-- ============ MOBILE TOPBAR ============ -->
<header class="app-topbar mobile" role="banner">
    <div class="topbar-start">
        <a href="<?= $home_link ?>" class="topbar-brand" aria-label="DigiShulk — Home">
            <img src="<?= $logo_url ?>" alt="DigiShulk">
        </a>
    </div>

    <div class="topbar-center">
        <button class="search-btn js-open-search" aria-label="Search" type="button">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        </button>
    </div>

    <div class="topbar-end">
        <div class="profile-avatar" aria-hidden="true">
            <img src="<?= htmlspecialchars($userPhoto, ENT_QUOTES, 'UTF-8') ?>"
                 class="nav-profile-photo" alt=""
                 onerror="this.src='uploads/profile/default.jpg'">
        </div>
    </div>
</header>

<!-- ============ MOBILE BOTTOM NAV ============ -->
<nav class="app-bottom-nav" role="navigation" aria-label="Primary mobile navigation">
    <?php if ($is_admin): ?>
        <a href="admin_dashboard.php" class="bottom-nav-item" data-page="dashboard">
            <span class="nav-icon"><i class="fa-solid fa-chart-line" aria-hidden="true"></i></span><span>Home</span>
        </a>
        <a href="add_inspector.php" class="bottom-nav-item" data-page="inspectors">
            <span class="nav-icon"><i class="fa-solid fa-users-gear" aria-hidden="true"></i></span><span>Inspectors</span>
        </a>
        <a href="undercharge_report.php" class="bottom-nav-item" data-page="undercharge">
            <span class="nav-icon"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></span><span>Gaps</span>
        </a>
        <a href="history.php" class="bottom-nav-item" data-page="history">
            <span class="nav-icon"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i></span><span>History</span>
        </a>
        <a href="settings.php" class="bottom-nav-item" data-page="profile">
            <span class="nav-icon"><i class="fa-solid fa-user" aria-hidden="true"></i></span><span>Profile</span>
        </a>
    <?php elseif ($is_insp): ?>
        <a href="dashboard.php" class="bottom-nav-item" data-page="dashboard">
            <span class="nav-icon"><i class="fa-solid fa-house" aria-hidden="true"></i></span><span>Home</span>
        </a>
        <a href="spot_tax.php" class="bottom-nav-item" data-page="spot-tax">
            <span class="nav-icon"><i class="fa-solid fa-receipt" aria-hidden="true"></i></span><span>Collect</span>
        </a>
        <a href="seizure_form.php" class="bottom-nav-item" data-page="seizure">
            <span class="nav-icon"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></span><span>Seizure</span>
        </a>
        <a href="history.php" class="bottom-nav-item" data-page="history">
            <span class="nav-icon"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i></span><span>History</span>
        </a>
        <a href="settings.php" class="bottom-nav-item" data-page="profile">
            <span class="nav-icon"><i class="fa-solid fa-user" aria-hidden="true"></i></span><span>Profile</span>
        </a>
    <?php endif; ?>
</nav>

<div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>

<script>
(function () {
    'use strict';

    /* Sidebar overlay close */
    (function () {
        var sidebar = document.getElementById('appSidebar');
        var overlay = document.getElementById('sidebarOverlay');
        if (!sidebar || !overlay) return;
        function close() {
            sidebar.classList.remove('is-open');
            overlay.classList.remove('is-visible');
            document.body.style.overflow = '';
        }
        overlay.addEventListener('click', close);
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && sidebar.classList.contains('is-open')) close();
        });
    })();

    /* NOTE: Search toggle is handled entirely by search.js.
       Do not add another handler here — it will fight with the overlay. */

    /* Profile dropdown */
    (function () {
        var trigger  = document.getElementById('profileTrigger');
        var dropdown = document.getElementById('profileDropdown');
        if (!trigger || !dropdown) return;

        function toggle(open) {
            var shouldOpen = (typeof open === 'boolean') ? open : !dropdown.classList.contains('open');
            dropdown.classList.toggle('open', shouldOpen);
            trigger.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
        }
        trigger.addEventListener('click', function (e) { e.stopPropagation(); toggle(); });
        document.addEventListener('click', function (e) {
            if (!trigger.contains(e.target) && !dropdown.contains(e.target)) toggle(false);
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && dropdown.classList.contains('open')) {
                toggle(false); trigger.focus();
            }
        });
        dropdown.addEventListener('keydown', function (e) {
            if (e.key !== 'Tab') return;
            var items = dropdown.querySelectorAll('[role="menuitem"], button, a');
            if (!items.length) return;
            var first = items[0];
            var last  = items[items.length - 1];
            if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
            else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
        });
    })();

    /* Active page highlighting */
    (function () {
        var currentPath = window.location.pathname.split('/').pop() || 'dashboard.php';
        var pageMap = {
            'admin_dashboard.php':'dashboard','dashboard.php':'dashboard',
            'add_inspector.php':'inspectors','edit_inspector.php':'inspectors',
            'undercharge_report.php':'undercharge','spot_tax.php':'spot-tax',
            'seizure_form.php':'seizure','history.php':'history','settings.php':'profile',
            'payment.php':'spot-tax','confirm_cash.php':'spot-tax',
            'generate_receipt_pdf.php':'history','transaction_detail.php':'history',
            'export_tax_excel.php':'history','export_tax_pdf.php':'history',
            'export_seizures_excel.php':'history','export_seizures_pdf.php':'history'
        };
        var currentPage = pageMap[currentPath] || 'dashboard';
        document.querySelectorAll('[data-page]').forEach(function (el) {
            if (el.dataset.page === currentPage) {
                el.classList.add('active');
                el.setAttribute('aria-current', 'page');
            }
        });
    })();
})();
</script>