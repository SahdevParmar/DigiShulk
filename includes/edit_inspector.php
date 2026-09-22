<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: logout.php');
    exit();
}

require_once 'db_connect.php';
require_once 'helpers/csrf.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header('Location: add_inspector.php');
    exit();
}

$message = '';
$message_type = '';

// Load the target user (only inspectors are editable here).
$stmt = $conn->prepare(
    "SELECT user_id, username, role FROM users WHERE user_id = ? LIMIT 1"
);
$stmt->bind_param('i', $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    header('Location: add_inspector.php');
    exit();
}

if ($user['role'] !== 'inspector') {
    // Refuse to edit admins from this screen.
    header('Location: add_inspector.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_require_or_die();

    if (isset($_POST['update_password'])) {

        $password = isset($_POST['password']) ? (string) $_POST['password'] : '';

        if ($password === '') {
            $message = 'No changes made — password field was empty.';
            $message_type = 'warning';
        } elseif (strlen($password) < 6) {
            $message = 'Password must be at least 6 characters.';
            $message_type = 'danger';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $up = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $up->bind_param('si', $hash, $id);

            if ($up->execute()) {
                $message = 'Password updated successfully!';
                $message_type = 'success';
            } else {
                error_log('edit_inspector password: ' . $up->error);
                $message = 'Could not update password. Please try again.';
                $message_type = 'danger';
            }
        }
    }

    elseif (isset($_POST['delete_inspector'])) {

        // Protect against self-deletion and against deleting the last admin.
        if ((int) $id === (int) $_SESSION['user_id']) {
            $message = 'You cannot delete your own account.';
            $message_type = 'danger';
        } else {
            $del = $conn->prepare("DELETE FROM users WHERE user_id = ? AND role = 'inspector'");
            $del->bind_param('i', $id);

            if ($del->execute()) {
                header('Location: add_inspector.php');
                exit();
            } else {
                error_log('edit_inspector delete: ' . $del->error);
                $message = 'Cannot delete this inspector — they have transactions or seizure records. '
                         . 'Reassign or archive them first.';
                $message_type = 'danger';
            }
        }
    }
}

include 'header.php';
?>

<div class="page">
    <div class="card" style="max-width: 500px;">

        <div class="card-header">
            <div style="display: flex; align-items: center; gap: var(--space-3);">
                <div class="avatar avatar-lg" style="background: var(--color-primary-light); color: var(--color-primary);">
                    <?= strtoupper(substr(htmlspecialchars($user['username']), 0, 1)) ?>
                </div>
                <div>
                    <h1 class="card-title" style="font-size: var(--text-xl); margin: 0;">Edit Inspector</h1>
                    <p class="card-subtitle" style="margin: 0;">ID: #<?= $user['user_id'] ?></p>
                </div>
            </div>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?>" style="margin: var(--space-4) var(--space-6);">
            <i class="fa-solid fa-<?= $message_type === 'success' ? 'circle-check' : ($message_type === 'warning' ? 'triangle-exclamation' : 'triangle-exclamation') ?> alert-icon" aria-hidden="true"></i>
            <div class="alert-content">
<p class="alert-message" style="margin: 0;"><?= htmlspecialchars($message) ?></p>            </div>
        </div>
        <?php endif; ?>

        <div class="card-body">
            <!-- Change Password -->
            <form method="POST">
                <?= csrf_field() ?>
                <div class="form-field">
                    <label class="form-label" for="username">Username</label>
                    <input type="text" name="username" id="username" class="form-input" value="<?= htmlspecialchars($user['username']) ?>" readonly style="background: var(--color-surface-muted); color: var(--color-text-muted);">
                </div>

                <div class="form-field">
                    <label class="form-label" for="password">New Password <span class="required" aria-hidden="true">*</span></label>
                    <input type="password" name="password" id="password" class="form-input" placeholder="Leave blank to keep current password" minlength="6">
                    <p class="form-help">Minimum 6 characters. Leave blank to keep current password.</p>
                </div>

                <div class="form-actions">
                    <button type="submit" name="update_password" class="btn btn-primary">
                        <i class="fa-solid fa-key" aria-hidden="true"></i>
                        Update Password
                    </button>
                </div>
            </form>

            <!-- Danger Zone -->
            <div style="border-top: 1px solid var(--color-border); padding-top: var(--space-6); margin-top: var(--space-6);">
                <h3 style="font-size: var(--text-lg); font-weight: 600; margin: 0 0 var(--space-4); display: flex; align-items: center; gap: var(--space-2); color: var(--color-danger);">
                    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                    Danger Zone
                </h3>

                <form method="POST" onsubmit="return confirm('Are you sure you want to permanently delete this inspector? This action cannot be undone.');">
                    <?= csrf_field() ?>                    <p style="font-size: var(--text-sm); color: var(--color-text-muted); margin: 0 0 var(--space-3);">Deleting this inspector will permanently remove their account and all associated data.</p>
                    <button type="submit" name="delete_inspector" class="btn btn-danger">
                        <i class="fa-solid fa-trash" aria-hidden="true"></i>
                        Delete Inspector
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>