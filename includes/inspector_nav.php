<?php
// Inspector Navigation (Mobile Top and Bottom Bars)
?>
<div class="mobile-topbar">
    <a href="dashboard.php" class="logo-link">
        <div class="logo"></div>
    </a>
    <div class="topbar-actions">
        <button id="openSearch" class="search-btn">🔍</button>
        <a href="settings.php" class="profile-link">
            <img src="<?php echo $userPhoto; ?>" class="nav-profile-photo" alt="Profile">
        </a>
    </div>
</div>

<div class="bottom-nav">
    <a href="dashboard.php" class="active">
        <span class="nav-icon">🏠</span>
        <span>Home</span>
    </a>
    <a href="spot_tax.php">
        <span class="nav-icon">🧾</span>
        <span>New Tax</span>
    </a>
    <a href="seizure_form.php">
        <span class="nav-icon">🚨</span>
        <span>New Seizure</span>
    </a>
    <a href="history.php">
        <span class="nav-icon">📜</span>
        <span>History</span>
    </a>
</div>
