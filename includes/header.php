<?php
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include_once 'db_connect.php';
include_once 'lang_engine.php';

$userPhoto = "uploads/profile/default.jpg";
if(isset($_SESSION['user_id'])){
    $stmt = $conn->prepare("SELECT profile_photo FROM users WHERE user_id=? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if(!empty($row['profile_photo']) && file_exists("uploads/profile/".$row['profile_photo'])){
            $userPhoto = "uploads/profile/".$row['profile_photo'];
        }
    }
}

$user_role = $_SESSION['role'] ?? 'guest';
?>
<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DigiShulk Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="style2.css">
    <style>
        .lang-form-wrapper { display: inline-block; margin: 0 8px; }
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

<?php
// Load role-specific navigation
if ($user_role == 'admin') {
    include_once 'admin_sidebar.php';
    echo '<main class="admin-layout">';
} elseif ($user_role == 'inspector') {
    include_once 'inspector_nav.php';
    echo '<main class="inspector-layout">';
} else {
    // Layout for guests (e.g., login page)
    echo '<main class="landing-page">';
}
?>

<!-- Spotlight Search (now universal) -->
<div id="searchOverlay" class="search-overlay">
    <div class="search-modal">
        <div style="display: flex; align-items: center; border-bottom: 1px solid var(--border);">
            <input type="text" id="spotlight" placeholder="Search anything..." autocomplete="off" style="border-bottom: none; margin-bottom: 0;">
                <button id="closeSearch" class="close-search-btn"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </div>
        <div id="searchResults">
            <div id="quickActions">
                <div class="search-section-title"><i class="fa-solid fa-bolt" aria-hidden="true"></i> Quick Actions</div>
                <a href="spot_tax.php" class="search-item"><i class="fa-solid fa-receipt" aria-hidden="true"></i><div><strong>New Spot Tax</strong><small>Create new tax collection</small></div></a>
                <a href="seizure_form.php" class="search-item"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i><div><strong>New Seizure Report</strong><small>Create seizure entry</small></div></a>
                <a href="history.php" class="search-item"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i><div><strong>History</strong><small>View previous collections</small></div></a>
                <a href="settings.php" class="search-item"><i class="fa-solid fa-gear" aria-hidden="true"></i><div><strong>Settings</strong><small>Application settings</small></div></a>
            </div>
        </div>
    </div>
</div>
<script src="assets/js/search.js"></script>
