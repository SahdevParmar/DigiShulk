<?php
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_connect.php';
include 'lang_engine.php'; // Injects the zero-lag system hooks
$userPhoto = "uploads/profile/default.jpg";

if(isset($_SESSION['user_id'])){

    $stmt = $conn->prepare("
    SELECT profile_photo
    FROM users
    WHERE user_id=?
    LIMIT 1
    ");

    if (!$stmt) {
        die("Database error in header.php. Did you run the database migration (migrate_v1.sql)? Error: " . $conn->error);
    }

    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();

    $row = $stmt->get_result()->fetch_assoc();

    if(!empty($row['profile_photo']) &&
       file_exists("uploads/profile/".$row['profile_photo'])){

        $userPhoto = "uploads/profile/".$row['profile_photo'];

    }

}

// Keep inspector status updated in real-time
if (isset($_SESSION['user_id'])) {
    $update_status = $conn->prepare("UPDATE users SET last_active = NOW() WHERE user_id = ?");
    $update_status->bind_param("i", $_SESSION['user_id']);
    $update_status->execute();
}
?>
<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DigiShulk Portal</title>
    <link rel="stylesheet" href="style2.css">
    <style>
        .lang-form-wrapper {
            display: inline-block;
            margin: 0 8px;
        }
        .server-lang-select {
            background: #ffffff !important;
            color: var(--text) !important;
            font-family: Inter, sans-serif !important;
            font-weight: 600 !important;
            font-size: 0.9rem !important;
            padding: 6px 14px !important;
            border: 1px solid var(--border) !important;
            border-radius: 999px !important;
            cursor: pointer !important;
            width: auto !important;
            min-height: auto !important;
        }
    </style>
</head>
<body>

<div class="navarea">
    <header class="navbar">
        <?php
            $homePage = "dashboard.php";

            if (isset($_SESSION['role']) && $_SESSION['role'] == "admin") {
                 $homePage = "admin_dashboard.php";
                    }
            ?>

            <a href="<?php echo $homePage; ?>" class="logo-link">
                <div class="logo"></div>
            </a>
        <div class="emptySpace"></div>
        <nav>
            <button id="openSearch" class="search-btn">

                🔍 Search

            </button>
            <?php if(isset($_SESSION['role']) && $_SESSION['role']=='admin'): ?>
                <a href="add_inspector.php"><?php echo __('manage'); ?></a>
            <?php endif; ?>
            <a href="history.php"><?php echo __('history'); ?></a>
            
            <!-- Pure Server-Driven Language Selector Dropdown -->
            <div class="lang-form-wrapper">
                <form action="switch_lang.php" method="POST" id="langForm">
                    <select name="lang" class="server-lang-select" onchange="document.getElementById('langForm').submit();">
                        <option value="en" <?php if($current_lang == 'en') echo 'selected'; ?>>🇬🇧 English</option>
                        <option value="hi" <?php if($current_lang == 'hi') echo 'selected'; ?>>🇮🇳 हिंदी</option>
                        <option value="gu" <?php if($current_lang == 'gu') echo 'selected'; ?>>🇮🇳 ગુજરાતી</option>
                    </select>
                </form>
            </div>
                
            <a href="settings.php" class="profile-link">

            <img
                src="<?php echo $userPhoto; ?>"
                class="nav-profile-photo"
                alt="Profile">

            </a>
            <a href="logout.php" class="logout-btn"><?php echo __('logout'); ?></a>
        </nav>
    </header>
</div>
<!-- Spotlight Search -->

<div id="searchOverlay" class="search-overlay">

    <div class="search-modal">

        <input
            type="text"
            id="spotlight"
            placeholder="Search anything..."
            autocomplete="off">

        <div id="searchResults">

    <div id="quickActions">

        <div class="search-section-title">
            ⚡ Quick Actions
        </div>

        <a href="spot_tax.php" class="search-item">
            <span>🧾</span>
            <div>
                <strong>New Spot Tax</strong>
                <small>Create new tax collection</small>
            </div>
        </a>

        <a href="seizure_form.php" class="search-item">
            <span>🚨</span>
            <div>
                <strong>New Seizure Report</strong>
                <small>Create seizure entry</small>
            </div>
        </a>

        <a href="history.php" class="search-item">
            <span>📜</span>
            <div>
                <strong>History</strong>
                <small>View previous collections</small>
            </div>
        </a>

        <a href="settings.php" class="search-item">
            <span>⚙️</span>
            <div>
                <strong>Settings</strong>
                <small>Application settings</small>
            </div>
        </a>

    </div>

</div>

    </div>

</div>
<script src="assets/js/search.js"></script>
