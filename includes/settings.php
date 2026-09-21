<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: logout.php');
    exit();
}

require_once 'db_connect.php';
require_once 'helpers/csrf.php';

$user_id = (int) $_SESSION['user_id'];
$message = '';
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

            // Delete any previous photo for this user (any extension).
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

    $name = isset($_POST['new_name']) ? trim((string) $_POST['new_name']) : '';

    if ($name === '') {
        $message = 'Name cannot be empty.';
        $message_type = 'danger';
    } elseif (strlen($name) > 150) {
        $message = 'Name is too long.';
        $message_type = 'danger';
    } else {
        $stmt = $conn->prepare("UPDATE users SET full_name = ? WHERE user_id = ?");
        $stmt->bind_param('si', $name, $user_id);

        if ($stmt->execute()) {
            $_SESSION['full_name'] = $name;
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

include 'header.php';
?>

<div class="page">
    <div class="card" style="max-width: 900px;">

        <div class="card-header" style="display: flex; justify-content: center; align-items: center; gap: var(--space-4);">
            <div style="display: flex; align-items: center; gap: var(--space-3);">
                <div class="avatar avatar-lg" style="background-image: url('<?php echo $photo; ?>'); background-size: cover; background-position: center; background: var(--color-primary-light); color: var(--color-primary);">
                    <?php if (empty($user['profile_photo']) || !file_exists("uploads/profile/".$user['profile_photo'])): ?>
                        <?= strtoupper(substr($name, 0, 1)) ?>
                    <?php endif; ?>
                </div>
                <div>
                    <h1 class="card-title" style="font-size: var(--text-2xl); margin: 0;"><?php echo htmlspecialchars($name); ?></h1>
                    <p class="card-subtitle" style="margin: 0; text-transform: capitalize;"><?php echo $user['role']; ?></p>
                </div>
            </div>
        </div>

        <?php if($message != ""): ?>
        <div class="alert alert-<?= $message_type ?>" style="margin: var(--space-4) var(--space-6);">
            <i class="fa-solid fa-<?= $message_type === 'success' ? 'circle-check' : 'triangle-exclamation' ?> alert-icon" aria-hidden="true"></i>
            <div class="alert-content">
                <p class="alert-message" style="margin: 0;"><?php echo $message; ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Profile Photo -->
        <div style="border-top: 1px solid var(--color-border); padding-top: var(--space-6); margin-top: var(--space-4);">
            <h3 style="font-size: var(--text-lg); font-weight: 600; margin: 0 0 var(--space-4); display: flex; align-items: center; gap: var(--space-2);">
                <i class="fa-solid fa-camera" aria-hidden="true"></i>
                Profile Photo
            </h3>

            <form method="POST" enctype="multipart/form-data" class="form-field" style="margin-bottom: 0;">
                <?= csrf_field() ?>
                <label class="form-label">Upload new photo</label>
                <input
                    type="file"
                    name="profile_photo"
                    accept=".jpg,.jpeg,.png,.webp"
                    class="form-input"
                    required
                    style="padding: var(--space-2) var(--space-3);">
                <p class="form-help">JPG, PNG, or WEBP. Max 5MB.</p>
                <button type="submit" name="upload_photo" class="btn btn-primary" style="margin-top: var(--space-2);">
                    <i class="fa-solid fa-upload" aria-hidden="true"></i>
                    Upload Photo
                </button>
            </form>
        </div>

        <!-- Change Name -->
        <div style="border-top: 1px solid var(--color-border); padding-top: var(--space-6); margin-top: var(--space-4);">
            <h3 style="font-size: var(--text-lg); font-weight: 600; margin: 0 0 var(--space-4); display: flex; align-items: center; gap: var(--space-2);">
                <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                Change Name
            </h3>

            <form method="POST" class="form-field" style="margin-bottom: 0;">
                <?= csrf_field() ?>
                <label class="form-label" for="new_name">Display Name</label>
                <input
                    type="text"
                    name="new_name"
                    id="new_name"
                    class="form-input"
                    value="<?php echo htmlspecialchars($name); ?>"
                    required>
                <button type="submit" name="update_name" class="btn btn-secondary" style="margin-top: var(--space-2);">
                    <i class="fa-solid fa-save" aria-hidden="true"></i>
                    Update Name
                </button>
            </form>
        </div>

        <!-- Change Password -->
        <div style="border-top: 1px solid var(--color-border); padding-top: var(--space-6); margin-top: var(--space-4);">
            <h3 style="font-size: var(--text-lg); font-weight: 600; margin: 0 0 var(--space-4); display: flex; align-items: center; gap: var(--space-2);">
                <i class="fa-solid fa-lock" aria-hidden="true"></i>
                Change Password
            </h3>

            <form method="POST" class="form-field" style="margin-bottom: 0;">
                <?= csrf_field() ?>
                <label class="form-label" for="password">New Password</label>
                <input
                    type="password"
                    name="password"
                    id="password"
                    class="form-input"
                    placeholder="Enter new password"
                    minlength="8"
                    required>
                <p class="form-help">Minimum 8 characters</p>
                <button type="submit" name="update_password" class="btn btn-secondary" style="margin-top: var(--space-2);">
                    <i class="fa-solid fa-key" aria-hidden="true"></i>
                    Update Password
                </button>
            </form>
        </div>

        <!-- Danger Zone -->
        <div style="border-top: 1px solid var(--color-border); padding-top: var(--space-6); margin-top: var(--space-4);">
            <h3 style="font-size: var(--text-lg); font-weight: 600; margin: 0 0 var(--space-4); display: flex; align-items: center; gap: var(--space-2); color: var(--color-danger);">
                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                Danger Zone
            </h3>

            <form method="POST" action="logout.php" style="display: inline;">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-danger">
                    <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
                    Logout
                    </button>
            </form>
        </div>

    </div>
</div>

<?php include 'footer.php'; ?>