<?php
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'db_connect.php';
include 'lang_engine.php'; // Injects the zero-lag system hooks

// Keep inspector status updated in real-time
if (isset($_SESSION['user_id'])) {
    $update_status = $conn->prepare("UPDATE users SET last_active = NOW() WHERE id = ?");
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
    <link rel="stylesheet" href="style.css">
    <style>
        .lang-form-wrapper {
            display: inline-block;
            margin: 0 8px;
        }
        .server-lang-select {
            background: var(--bg-soft) !important;
            color: var(--text-light) !important;
            font-family: 'Space Grotesk', sans-serif !important;
            font-weight: 600 !important;
            font-size: 0.9rem !important;
            padding: 6px 14px !important;
            border: 1.5px solid rgba(255, 255, 255, 0.15) !important;
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
        <div class="logo"></div>
        <div class="emptySpace"></div>
        <nav>
            <button id="openSearch" class="search-btn">

                🔍 Search

            </button>
            <?php if(isset($_SESSION['role']) && $_SESSION['role']=='admin'): ?>
                <a href="admin_dashboard.php"><?php echo __('home'); ?></a>
                <a href="add_inspector.php"><?php echo __('manage'); ?></a>
            <?php else: ?>
                <a href="dashboard.php"><?php echo __('home'); ?></a>
            <?php endif; ?>
            <a href="history.php"><?php echo __('history'); ?></a>
            <a href="settings.php"><?php echo __('settings'); ?></a>
            
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

            <div class="search-empty">

                Start typing to search...

            </div>

        </div>

    </div>

</div>
<script src="assets/js/search.js"></script>
</body>
</html>