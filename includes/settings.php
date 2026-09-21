<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: logout.php');
    exit();
}

require_once 'db_connect.php';
require_once 'helpers/csrf.php';

$user_id      = (int) $_SESSION['user_id'];
$message      = '';
$message_type = '';

/* ===============================
   Upload Profile Photo
===============================*/
if (isset($_POST['upload_photo']) && isset($_FILES['profile_photo'])) {

    csrf_require_or_die();

    $file = $_FILES['profile_photo'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $message = 'Upload failed. Please try again.';
        $message_type = 'danger';
    }
    elseif ($file['size'] > 5 * 1024 * 1024) {
        $message = 'Image must be 5 MB or smaller.';
        $message_type = 'danger';
    }
    else {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
        ];

        if (!isset($allowed[$mime])) {
            $message = 'Only JPG, PNG, and WEBP images are allowed.';
            $message_type = 'danger';
        }
        elseif (getimagesize($file['tmp_name']) === false) {
            $message = 'That file is not a valid image.';
            $message_type = 'danger';
        }
        else {
            $ext = $allowed[$mime];

            if (!is_dir('uploads/profile')) {
                mkdir('uploads/profile', 0755, true);
            }

            foreach (['jpg', 'jpeg', 'png', 'webp'] as $oldExt) {
                $old = 'uploads/profile/user_' . $user_id . '.' . $oldExt;
                if (file_exists($old)) { @unlink($old); }
            }

            $filename = 'user_' . $user_id . '.' . $ext;
            $dest     = 'uploads/profile/' . $filename;

            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $stmt = $conn->prepare("UPDATE users SET profile_photo = ? WHERE user_id = ?");
                $stmt->bind_param('si', $filename, $user_id);
                $stmt->execute();

                $message = 'Profile photo updated.';
                $message_type = 'success';
            } else {
                error_log('settings.php: move_uploaded_file failed for user ' . $user_id);
                $message = 'Could not save the image. Please try again.';
                $message_type = 'danger';
            }
        }
    }
}

/* ===============================
   Change Name
===============================*/
if (isset($_POST['update_name'])) {

    csrf_require_or_die();

    $name_input = isset($_POST['new_name']) ? trim((string) $_POST['new_name']) : '';

    if ($name_input === '') {
        $message = 'Name cannot be empty.';
        $message_type = 'danger';
    } elseif (strlen($name_input) > 150) {
        $message = 'Name is too long.';
        $message_type = 'danger';
    } else {
        $stmt = $conn->prepare("UPDATE users SET full_name = ? WHERE user_id = ?");
        $stmt->bind_param('si', $name_input, $user_id);

        if ($stmt->execute()) {
            $_SESSION['full_name'] = $name_input;
            $message = 'Name updated successfully.';
            $message_type = 'success';
        } else {
            error_log('settings.php name: ' . $stmt->error);
            $message = 'Could not update name.';
            $message_type = 'danger';
        }
    }
}

/* ===============================
   Change Password
===============================*/
if (isset($_POST['update_password'])) {

    csrf_require_or_die();

    $password = isset($_POST['password']) ? (string) $_POST['password'] : '';

    if (strlen($password) < 8) {
        $message = 'Password must be at least 8 characters.';
        $message_type = 'danger';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $stmt->bind_param('si', $hash, $user_id);

        if ($stmt->execute()) {
            $message = 'Password updated successfully.';
            $message_type = 'success';
        } else {
            error_log('settings.php password: ' . $stmt->error);
            $message = 'Could not update password.';
            $message_type = 'danger';
        }
    }
}

/* ===============================
   Load User
===============================*/
$stmt = $conn->prepare(
    "SELECT username, full_name, profile_photo, role FROM users WHERE user_id = ? LIMIT 1"
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    header('Location: logout.php');
    exit();
}

/* --- FIX: define $photo and $name (were missing) --- */
$has_photo = !empty($user['profile_photo'])
          && file_exists('uploads/profile/' . $user['profile_photo']);

$photo = $has_photo
    ? 'uploads/profile/' . $user['profile_photo']
    : '';

$name = !empty($user['full_name']) ? $user['full_name'] : $user['username'];

include 'header.php';
?>

<style>
/* ================================================================
   DigiShulk — Settings page (self-contained styles)
   Scope: .settings-page — no leakage to other pages.
   ================================================================ */

.settings-page {
    --set-ease: cubic-bezier(0.16, 1, 0.3, 1);
    --set-accent: #3b82f6;
    --set-accent-soft: rgba(59, 130, 246, 0.15);
    --set-radius: 16px;
    max-width: 820px;
    margin: 0 auto;
}

/* ---------------- animations ---------------- */
@keyframes setRise {
    from { opacity: 0; transform: translateY(14px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes setPop {
    0%   { opacity: 0; transform: scale(0.85); }
    60%  { opacity: 1; transform: scale(1.04); }
    100% { opacity: 1; transform: scale(1); }
}
@keyframes setOrbit {
    0%   { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
@keyframes setPulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.55); }
    70%      { box-shadow: 0 0 0 10px rgba(34, 197, 94, 0); }
}
@keyframes setShimmer {
    0%   { background-position: -200% 0; }
    100% { background-position:  200% 0; }
}
@keyframes setSlideDown {
    from { opacity: 0; transform: translateY(-12px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes setFlash {
    0%   { background: rgba(34, 197, 94, 0.20); }
    100% { background: transparent; }
}

.set-rise {
    opacity: 0;
    animation: setRise 0.55s var(--set-ease) forwards;
    animation-delay: var(--delay, 0ms);
}
@media (prefers-reduced-motion: reduce) {
    .set-rise, .set-pop { animation: none !important; opacity: 1 !important; transform: none !important; }
}

/* ---------------- Hero ---------------- */
.settings-hero {
    position: relative;
    display: flex;
    align-items: center;
    gap: 22px;
    padding: 28px;
    border-radius: var(--set-radius);
    background:
        radial-gradient(120% 140% at 0% 0%, rgba(59, 130, 246, 0.22), transparent 55%),
        radial-gradient(100% 140% at 100% 100%, rgba(168, 85, 247, 0.18), transparent 55%),
        var(--color-surface);
    border: 1px solid var(--color-border);
    color: var(--color-text);
    overflow: hidden;
    margin-bottom: 26px;
}
.settings-hero::after {
    content: '';
    position: absolute;
    inset: -50% auto auto -50%;
    width: 320px;
    height: 320px;
    background: radial-gradient(circle, rgba(59, 130, 246, 0.18), transparent 65%);
    filter: blur(6px);
    pointer-events: none;
    animation: setOrbit 26s linear infinite;
    transform-origin: center;
}

.settings-avatar-wrap {
    position: relative;
    flex-shrink: 0;
}
.settings-avatar {
    width: 92px;
    height: 92px;
    border-radius: 50%;
    background-color: var(--color-primary-light);
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    color: var(--color-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    font-weight: 700;
    border: 3px solid rgba(255,255,255,0.08);
    box-shadow:
        0 8px 24px -10px rgba(0, 0, 0, 0.5),
        inset 0 0 0 1px rgba(255,255,255,0.06);
    animation: setPop 0.6s var(--set-ease) 120ms both;
}
.settings-avatar-ring {
    position: absolute;
    inset: -6px;
    border-radius: 50%;
    background: conic-gradient(
        from 0deg,
        rgba(59, 130, 246, 0.55),
        rgba(168, 85, 247, 0.55),
        rgba(59, 130, 246, 0.55)
    );
    filter: blur(2px);
    opacity: 0.7;
    z-index: -1;
    animation: setOrbit 8s linear infinite;
}
.settings-avatar-status {
    position: absolute;
    right: 4px;
    bottom: 4px;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: #22c55e;
    border: 3px solid var(--color-surface);
    animation: setPulse 2.4s ease-in-out infinite;
}

.settings-hero-info { flex: 1; min-width: 0; }
.settings-hero-name {
    font-size: 1.75rem;
    font-weight: 700;
    letter-spacing: -0.02em;
    color: var(--color-text);
    margin: 0 0 6px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.settings-role-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: #93c5fd;
    background: rgba(59, 130, 246, 0.15);
    border: 1px solid rgba(59, 130, 246, 0.28);
}
.settings-hero-meta {
    margin-top: 10px;
    font-size: 0.82rem;
    color: var(--color-text-muted);
    display: flex;
    align-items: center;
    gap: 8px;
}
.settings-hero-meta i { color: #93c5fd; opacity: 0.7; }

/* ---------------- Toast ---------------- */
.settings-toast {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 14px 18px;
    margin-bottom: 22px;
    border-radius: 14px;
    border: 1px solid transparent;
    animation: setSlideDown 0.5s var(--set-ease) both;
    position: relative;
    overflow: hidden;
}
.settings-toast::after {
    content: '';
    position: absolute;
    left: 0; right: 0; bottom: 0;
    height: 2px;
    background: currentColor;
    opacity: 0.35;
}
.settings-toast-icon {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
}
.settings-toast-body { flex: 1; font-size: 0.92rem; padding-top: 4px; }

.settings-toast-success {
    background: rgba(34, 197, 94, 0.10);
    border-color: rgba(34, 197, 94, 0.30);
    color: #86efac;
}
.settings-toast-success .settings-toast-icon {
    background: rgba(34, 197, 94, 0.18);
    color: #4ade80;
}
.settings-toast-danger {
    background: rgba(239, 68, 68, 0.10);
    border-color: rgba(239, 68, 68, 0.30);
    color: #fca5a5;
}
.settings-toast-danger .settings-toast-icon {
    background: rgba(239, 68, 68, 0.18);
    color: #f87171;
}
.settings-toast-warning {
    background: rgba(234, 179, 8, 0.10);
    border-color: rgba(234, 179, 8, 0.30);
    color: #fcd34d;
}
.settings-toast-warning .settings-toast-icon {
    background: rgba(234, 179, 8, 0.18);
    color: #facc15;
}

/* ---------------- Section cards ---------------- */
.settings-section {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--set-radius);
    margin-bottom: 18px;
    overflow: hidden;
    transition:
        transform 0.28s var(--set-ease),
        box-shadow 0.28s var(--set-ease),
        border-color 0.28s var(--set-ease);
}
.settings-section:hover {
    transform: translateY(-2px);
    box-shadow: 0 18px 40px -22px rgba(0, 0, 0, 0.55);
    border-color: rgba(59, 130, 246, 0.30);
}
.settings-section-head {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 18px 22px;
    border-bottom: 1px solid var(--color-border);
    background: linear-gradient(180deg, rgba(255,255,255,0.02), transparent);
}
.settings-section-icon {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
    color: #93c5fd;
    background: var(--set-accent-soft);
    transition: transform 0.3s var(--set-ease);
}
.settings-section:hover .settings-section-icon {
    transform: scale(1.08) rotate(-4deg);
}
.settings-section-title {
    font-size: 1rem;
    font-weight: 600;
    margin: 0;
    color: var(--color-text);
    letter-spacing: -0.005em;
}
.settings-section-desc {
    font-size: 0.8rem;
    color: var(--color-text-muted);
    margin: 2px 0 0;
}
.settings-section-body {
    padding: 20px 22px 22px;
}

/* ---------------- Forms ---------------- */
.settings-field { margin-bottom: 16px; }
.settings-field:last-child { margin-bottom: 0; }
.settings-label {
    display: block;
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--color-text);
    margin-bottom: 8px;
}
.settings-input {
    width: 100%;
    padding: 12px 14px;
    background: var(--color-surface-muted);
    border: 1px solid var(--color-border);
    border-radius: 10px;
    color: var(--color-text);
    font-family: inherit;
    font-size: 0.94rem;
    transition: border-color 0.2s var(--set-ease), box-shadow 0.2s var(--set-ease), background 0.2s var(--set-ease);
    box-sizing: border-box;
}
.settings-input:focus {
    outline: none;
    border-color: var(--set-accent);
    background: var(--color-surface);
    box-shadow: 0 0 0 3px var(--set-accent-soft);
}
.settings-input::placeholder { color: var(--color-text-subtle, var(--color-text-muted)); opacity: 0.7; }

.settings-help {
    font-size: 0.78rem;
    color: var(--color-text-muted);
    margin-top: 6px;
}
.settings-counter {
    font-size: 0.78rem;
    color: var(--color-text-muted);
    margin-top: 6px;
    text-align: right;
    font-variant-numeric: tabular-nums;
}

/* ---------------- Upload zone ---------------- */
.settings-upload {
    position: relative;
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 18px;
    border: 2px dashed var(--color-border);
    border-radius: 14px;
    cursor: pointer;
    transition: border-color 0.2s var(--set-ease), background 0.2s var(--set-ease), transform 0.2s var(--set-ease);
    margin-bottom: 14px;
}
.settings-upload:hover {
    border-color: var(--set-accent);
    background: rgba(59, 130, 246, 0.04);
}
.settings-upload.is-dragover {
    border-color: var(--set-accent);
    background: rgba(59, 130, 246, 0.08);
    transform: scale(1.01);
}
.settings-upload input[type="file"] {
    position: absolute;
    inset: 0;
    opacity: 0;
    cursor: pointer;
}
.settings-upload-preview {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: var(--color-surface-muted);
    background-size: cover;
    background-position: center;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--color-text-muted);
    font-size: 1.4rem;
    flex-shrink: 0;
    border: 2px solid var(--color-border);
    transition: border-color 0.2s var(--set-ease);
}
.settings-upload:hover .settings-upload-preview { border-color: var(--set-accent); }
.settings-upload-text { flex: 1; min-width: 0; }
.settings-upload-title {
    font-weight: 600;
    font-size: 0.92rem;
    color: var(--color-text);
    margin: 0 0 3px;
}
.settings-upload-hint {
    font-size: 0.78rem;
    color: var(--color-text-muted);
    margin: 0;
}

/* ---------------- Buttons ---------------- */
.settings-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 11px 20px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.9rem;
    font-family: inherit;
    border: 1px solid transparent;
    cursor: pointer;
    transition: transform 0.2s var(--set-ease),
                box-shadow 0.2s var(--set-ease),
                background 0.2s var(--set-ease),
                border-color 0.2s var(--set-ease);
    position: relative;
    overflow: hidden;
}
.settings-btn:hover { transform: translateY(-1px); }
.settings-btn:active { transform: translateY(0); }
.settings-btn i { transition: transform 0.25s var(--set-ease); }
.settings-btn:hover i { transform: scale(1.15) rotate(-4deg); }

.settings-btn-primary {
    background: var(--set-accent);
    color: #fff;
    box-shadow: 0 8px 20px -10px rgba(59, 130, 246, 0.7);
}
.settings-btn-primary:hover {
    background: #2563eb;
    box-shadow: 0 12px 26px -10px rgba(59, 130, 246, 0.85);
}
.settings-btn-primary::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(110deg, transparent 30%, rgba(255,255,255,0.28) 45%, transparent 60%);
    background-size: 200% 100%;
    animation: setShimmer 3.4s linear infinite;
    pointer-events: none;
}

.settings-btn-secondary {
    background: var(--color-surface-muted);
    color: var(--color-text);
    border-color: var(--color-border);
}
.settings-btn-secondary:hover {
    background: var(--color-surface);
    border-color: var(--set-accent);
}

.settings-btn-danger {
    background: rgba(239, 68, 68, 0.12);
    color: #f87171;
    border-color: rgba(239, 68, 68, 0.35);
}
.settings-btn-danger:hover {
    background: rgba(239, 68, 68, 0.2);
    border-color: rgba(239, 68, 68, 0.55);
}

/* ---------------- Password strength ---------------- */
.settings-strength {
    display: flex;
    gap: 4px;
    margin-top: 8px;
    height: 4px;
}
.settings-strength-segment {
    flex: 1;
    background: var(--color-border);
    border-radius: 999px;
    transition: background 0.3s var(--set-ease);
}
.settings-strength[data-level="1"] .settings-strength-segment:nth-child(-n+1) { background: #ef4444; }
.settings-strength[data-level="2"] .settings-strength-segment:nth-child(-n+2) { background: #f59e0b; }
.settings-strength[data-level="3"] .settings-strength-segment:nth-child(-n+3) { background: #22c55e; }
.settings-strength[data-level="4"] .settings-strength-segment:nth-child(-n+4) { background: #22c55e; }
.settings-strength-label {
    font-size: 0.75rem;
    margin-top: 6px;
    color: var(--color-text-muted);
    transition: color 0.2s var(--set-ease);
}
.settings-strength-label[data-level="1"] { color: #f87171; }
.settings-strength-label[data-level="2"] { color: #fbbf24; }
.settings-strength-label[data-level="3"],
.settings-strength-label[data-level="4"] { color: #4ade80; }

/* ---------------- Danger zone ---------------- */
.settings-section-danger {
    border-color: rgba(239, 68, 68, 0.28);
}
.settings-section-danger .settings-section-head {
    border-bottom-color: rgba(239, 68, 68, 0.20);
}
.settings-section-danger .settings-section-icon {
    color: #f87171;
    background: rgba(239, 68, 68, 0.14);
}
.settings-section-danger:hover {
    border-color: rgba(239, 68, 68, 0.5);
    box-shadow: 0 18px 40px -22px rgba(239, 68, 68, 0.5);
}

/* ---------------- Responsive ---------------- */
@media (max-width: 640px) {
    .settings-hero { flex-direction: column; text-align: center; padding: 24px 20px; }
    .settings-hero-info { text-align: center; }
    .settings-hero-meta { justify-content: center; }
    .settings-hero-name { font-size: 1.4rem; white-space: normal; }
    .settings-avatar { width: 80px; height: 80px; font-size: 1.7rem; }
    .settings-section-head { padding: 16px 18px; }
    .settings-section-body { padding: 18px; }
}
</style>

<div class="page settings-page">

    <!-- Hero -->
    <div class="settings-hero set-rise" style="--delay: 0ms;">
        <div class="settings-avatar-wrap">
            <div class="settings-avatar-ring" aria-hidden="true"></div>
            <div class="settings-avatar"
                 <?php if ($has_photo): ?>
                     style="background-image: url('<?= htmlspecialchars($photo, ENT_QUOTES, 'UTF-8') ?>');"
                 <?php endif; ?>>
                <?php if (!$has_photo): ?>
                    <?= htmlspecialchars(strtoupper(substr($name, 0, 1))) ?>
                <?php endif; ?>
            </div>
            <div class="settings-avatar-status" aria-hidden="true"></div>
        </div>

        <div class="settings-hero-info">
            <h1 class="settings-hero-name"><?= htmlspecialchars($name) ?></h1>
            <span class="settings-role-badge">
                <i class="fa-solid fa-<?= $user['role'] === 'admin' ? 'shield-halved' : 'user-shield' ?>" aria-hidden="true"></i>
                <?= htmlspecialchars(ucfirst($user['role'])) ?>
            </span>
            <div class="settings-hero-meta">
                <i class="fa-solid fa-at" aria-hidden="true"></i>
                <span><?= htmlspecialchars($user['username']) ?></span>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <?php if ($message !== ''):
        $tIcon = $message_type === 'success' ? 'circle-check'
               : ($message_type === 'warning' ? 'triangle-exclamation' : 'circle-xmark');
    ?>
    <div class="settings-toast settings-toast-<?= htmlspecialchars($message_type) ?>" role="alert" data-autodismiss="1">
        <div class="settings-toast-icon">
            <i class="fa-solid fa-<?= $tIcon ?>" aria-hidden="true"></i>
        </div>
        <div class="settings-toast-body"><?= htmlspecialchars($message) ?></div>
    </div>
    <?php endif; ?>

    <!-- Profile Photo -->
    <section class="settings-section set-rise" style="--delay: 80ms;">
        <div class="settings-section-head">
            <div class="settings-section-icon">
                <i class="fa-solid fa-camera" aria-hidden="true"></i>
            </div>
            <div>
                <h2 class="settings-section-title">Profile Photo</h2>
                <p class="settings-section-desc">Shown on the navigation bar and your dashboard</p>
            </div>
        </div>
        <form method="POST" enctype="multipart/form-data" class="settings-section-body" id="photoForm">
            <?= csrf_field() ?>

            <label class="settings-upload" id="uploadZone" for="profile_photo_input">
                <input type="file" name="profile_photo" id="profile_photo_input"
                       accept=".jpg,.jpeg,.png,.webp" required>
                <div class="settings-upload-preview" id="uploadPreview"
                     <?php if ($has_photo): ?>
                         style="background-image: url('<?= htmlspecialchars($photo, ENT_QUOTES, 'UTF-8') ?>');"
                     <?php endif; ?>>
                    <?php if (!$has_photo): ?>
                        <i class="fa-solid fa-image" aria-hidden="true"></i>
                    <?php endif; ?>
                </div>
                <div class="settings-upload-text">
                    <p class="settings-upload-title" id="uploadTitle">Click to upload or drag &amp; drop</p>
                    <p class="settings-upload-hint">JPG, PNG, or WEBP · max 5 MB</p>
                </div>
            </label>

            <button type="submit" name="upload_photo" class="settings-btn settings-btn-primary">
                <i class="fa-solid fa-upload" aria-hidden="true"></i>
                Upload Photo
            </button>
        </form>
    </section>

    <!-- Change Name -->
    <section class="settings-section set-rise" style="--delay: 160ms;">
        <div class="settings-section-head">
            <div class="settings-section-icon">
                <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
            </div>
            <div>
                <h2 class="settings-section-title">Display Name</h2>
                <p class="settings-section-desc">How your name appears across DigiShulk</p>
            </div>
        </div>
        <form method="POST" class="settings-section-body">
            <?= csrf_field() ?>

            <div class="settings-field">
                <label class="settings-label" for="new_name">Your name</label>
                <input type="text" name="new_name" id="new_name"
                       class="settings-input" maxlength="150"
                       value="<?= htmlspecialchars($name) ?>" required
                       autocomplete="name">
                <div class="settings-counter">
                    <span id="nameCount"><?= strlen($name) ?></span> / 150
                </div>
            </div>

            <button type="submit" name="update_name" class="settings-btn settings-btn-secondary">
                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                Save Name
            </button>
        </form>
    </section>

    <!-- Change Password -->
    <section class="settings-section set-rise" style="--delay: 240ms;">
        <div class="settings-section-head">
            <div class="settings-section-icon">
                <i class="fa-solid fa-lock" aria-hidden="true"></i>
            </div>
            <div>
                <h2 class="settings-section-title">Password</h2>
                <p class="settings-section-desc">Choose a strong password you don't use elsewhere</p>
            </div>
        </div>
        <form method="POST" class="settings-section-body" id="passwordForm">
            <?= csrf_field() ?>

            <div class="settings-field">
                <label class="settings-label" for="password">New password</label>
                <input type="password" name="password" id="password"
                       class="settings-input"
                       placeholder="At least 8 characters"
                       minlength="8" required
                       autocomplete="new-password">

                <div class="settings-strength" id="strengthBar" data-level="0" aria-hidden="true">
                    <span class="settings-strength-segment"></span>
                    <span class="settings-strength-segment"></span>
                    <span class="settings-strength-segment"></span>
                    <span class="settings-strength-segment"></span>
                </div>
                <div class="settings-strength-label" id="strengthLabel" data-level="0">
                    Password strength
                </div>
            </div>

            <button type="submit" name="update_password" class="settings-btn settings-btn-secondary">
                <i class="fa-solid fa-key" aria-hidden="true"></i>
                Update Password
            </button>
        </form>
    </section>

    <!-- Danger Zone -->
    <section class="settings-section settings-section-danger set-rise" style="--delay: 320ms;">
        <div class="settings-section-head">
            <div class="settings-section-icon">
                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            </div>
            <div>
                <h2 class="settings-section-title">Sign Out</h2>
                <p class="settings-section-desc">End your session on this device</p>
            </div>
        </div>
        <div class="settings-section-body">
            <form method="POST" action="logout.php">
                <?= csrf_field() ?>
                <button type="submit" class="settings-btn settings-btn-danger">
                    <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
                    Log Out
                </button>
            </form>
        </div>
    </section>

</div>

<script>
/* ============================================================
   1. File upload — drag & drop + preview
   ============================================================ */
(function () {
    var zone    = document.getElementById('uploadZone');
    var input   = document.getElementById('profile_photo_input');
    var preview = document.getElementById('uploadPreview');
    var title   = document.getElementById('uploadTitle');
    if (!zone || !input) return;

    function showPreview(file) {
        if (!file || !file.type.startsWith('image/')) return;
        var reader = new FileReader();
        reader.onload = function (e) {
            preview.style.backgroundImage = 'url("' + e.target.result + '")';
            preview.innerHTML = '';
            if (title) {
                title.textContent = file.name;
            }
        };
        reader.readAsDataURL(file);
    }

    input.addEventListener('change', function () {
        if (input.files && input.files[0]) {
            showPreview(input.files[0]);
        }
    });

    ['dragenter', 'dragover'].forEach(function (ev) {
        zone.addEventListener(ev, function (e) {
            e.preventDefault();
            zone.classList.add('is-dragover');
        });
    });
    ['dragleave', 'drop'].forEach(function (ev) {
        zone.addEventListener(ev, function (e) {
            e.preventDefault();
            zone.classList.remove('is-dragover');
        });
    });
    zone.addEventListener('drop', function (e) {
        if (e.dataTransfer.files && e.dataTransfer.files[0]) {
            input.files = e.dataTransfer.files;
            showPreview(e.dataTransfer.files[0]);
        }
    });
})();

/* ============================================================
   2. Name character counter
   ============================================================ */
(function () {
    var input = document.getElementById('new_name');
    var count = document.getElementById('nameCount');
    if (!input || !count) return;
    input.addEventListener('input', function () {
        count.textContent = input.value.length;
    });
})();

/* ============================================================
   3. Password strength meter
   ============================================================ */
(function () {
    var input = document.getElementById('password');
    var bar   = document.getElementById('strengthBar');
    var label = document.getElementById('strengthLabel');
    if (!input || !bar || !label) return;

    var words = ['Password strength', 'Weak', 'Fair', 'Good', 'Strong'];

    function score(v) {
        if (!v) return 0;
        var s = 0;
        if (v.length >= 8)         s++;
        if (/[A-Z]/.test(v) && /[a-z]/.test(v)) s++;
        if (/[0-9]/.test(v))       s++;
        if (/[^A-Za-z0-9]/.test(v) || v.length >= 14) s++;
        return Math.min(s, 4);
    }

    input.addEventListener('input', function () {
        var s = score(input.value);
        bar.setAttribute('data-level', s);
        label.setAttribute('data-level', s);
        label.textContent = words[s];
    });
})();

/* ============================================================
   4. Auto-dismiss toast
   ============================================================ */
(function () {
    var toast = document.querySelector('.settings-toast[data-autodismiss]');
    if (!toast) return;
    setTimeout(function () {
        toast.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(-12px)';
        setTimeout(function () { toast.remove(); }, 450);
    }, 5000);
})();

/* ============================================================
   5. Success flash on hero avatar after photo upload
   ============================================================ */
(function () {
    var url = new URL(window.location.href);
    // If the previous action returned success, briefly flash the avatar.
    var av = document.querySelector('.settings-avatar');
    if (!av) return;
    var toast = document.querySelector('.settings-toast-success');
    if (toast && av) {
        av.style.transition = 'box-shadow 0.4s ease';
        av.style.boxShadow = '0 0 0 6px rgba(34, 197, 94, 0.35)';
        setTimeout(function () {
            av.style.boxShadow = '0 8px 24px -10px rgba(0, 0, 0, 0.5)';
        }, 900);
    }
})();
</script>

<?php include 'footer.php'; ?>