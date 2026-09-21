<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: logout.php');
    exit();
}

require_once 'db_connect.php';
require_once 'helpers/csrf.php';

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_require_or_die();

    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? (string) $_POST['password'] : '';

    $errors = [];

    if ($username === '') {
        $errors[] = 'Username is required.';
    } elseif (strlen($username) > 20) {
        $errors[] = 'Username must be 20 characters or fewer.';
    } elseif (!preg_match('/^[a-zA-Z0-9_.-]+$/', $username)) {
        $errors[] = 'Username may only contain letters, numbers, dot, dash, and underscore.';
    }

    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }

    if (empty($errors)) {
        // Check for duplicate username (case-insensitive).
        $chk = $conn->prepare("SELECT user_id FROM users WHERE LOWER(username) = LOWER(?) LIMIT 1");
        if ($chk) {
            $chk->bind_param('s', $username);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $errors[] = 'That username is already taken.';
            }
        }
    }

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare(
            "INSERT INTO users (username, password, role) VALUES (?, ?, 'inspector')"
        );
        $stmt->bind_param('ss', $username, $hash);

        if ($stmt->execute()) {
            $message = 'Inspector added successfully!';
            $message_type = 'success';
        } else {
            // Never leak raw DB error to the browser.
            error_log('add_inspector: ' . $stmt->error);
            $message = 'Could not add inspector. Please try again.';
            $message_type = 'danger';
        }
    } else {
        $message = implode(' ', $errors);
        $message_type = 'danger';
    }
}

$inspectors = $conn->query(
    "SELECT user_id, username, last_active FROM users WHERE role='inspector' ORDER BY user_id DESC"
);

include 'header.php';
?>

<div class="page">
    <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: var(--space-4); margin-bottom: var(--space-6);">
        <div>
            <h1 style="font-size: var(--text-3xl); font-weight: 700; color: var(--color-text); margin: 0;">Manage Inspectors</h1>
            <p class="subtitle" style="margin-top: var(--space-1);">View and manage inspector accounts</p>
        </div>
    </div>

    <?php if (isset($message)): ?>
    <div class="alert alert-<?= $message_type ?>" style="margin-bottom: var(--space-6);">
        <i class="fa-solid fa-<?= $message_type === 'success' ? 'circle-check' : 'triangle-exclamation' ?> alert-icon" aria-hidden="true"></i>
        <div class="alert-content">
            <p class="alert-message" style="margin: 0;"><?= htmlspecialchars($message) ?></p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Inspectors Table -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title" style="font-size: var(--text-xl);">All Inspectors</h2>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Status</th>
                            <th>Last Active</th>
                            <th style="width: 100px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($inspectors->num_rows > 0): ?>
                            <?php while($row= $inspectors->fetch_assoc()):
                                $is_online = (strtotime($row['last_active']) > strtotime('-1 minutes'));
                            ?>
                            <tr>
                                <td><?= $row['user_id'] ?></td>
                                <td><strong><?= htmlspecialchars($row['username']) ?></strong></td>
                                <td>
                                    <span class="badge badge-<?= $is_online ? 'success' : 'neutral' ?> badge-dot">
                                        <?= $is_online ? 'Online' : 'Offline' ?>
                                    </span>
                                </td>
                                <td><?= date('d M Y, h:i A', strtotime($row['last_active'])) ?></td>
                                <td>
                                    <a href="edit_inspector.php?id=<?= $row['user_id'] ?>" class="table-action-btn">
                                        <i class="fa-solid fa-pen" aria-hidden="true"></i>
                                        Edit
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: var(--space-8);">
                                    <div class="empty-state" style="margin: 0; border: none; border-radius: 0; background: transparent;">
                                        <div class="empty-state-icon"><i class="fa-solid fa-users-gear" aria-hidden="true"></i></div>
                                        <p class="empty-state-title">No inspectors found</p>
                                        <p class="empty-state-message">Add your first inspector using the form below</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Inspector Form -->
    <div class="card" style="margin-top: var(--space-6);">
        <div class="card-header">
            <h2 class="card-title" style="font-size: var(--text-xl);">Add New Inspector</h2>
        </div>
        <div class="card-body">
            <form method="POST" novalidate>
                <?= csrf_field() ?>
                <div class="form-grid form-grid-2">
                    <div class="form-field">
                        <label class="form-label" for="username">Username <span class="required" aria-hidden="true">*</span></label>
                        <input type="text" name="username" id="username" class="form-input" placeholder="Username" required autocomplete="username">
                    </div>
                    <div class="form-field">
                        <label class="form-label" for="password">Password <span class="required" aria-hidden="true">*</span></label>
                        <input type="password" name="password" id="password" class="form-input" placeholder="Password" required minlength="6" autocomplete="new-password">
                        <p class="form-help">Minimum 6 characters</p>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
                        Add Inspector
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>