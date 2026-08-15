<?php
session_start();
include 'db_connect.php';
if(!isset($_SESSION['role']) || $_SESSION['role'] != 'admin'){
    header("Location: logout.php");
    exit();
}
$id=$_GET['id'];

$message = "";
$message_type = "";

if($_SERVER["REQUEST_METHOD"]=="GET"){
    $stmt=$conn->prepare("select * from users where user_id=?");
    $stmt->bind_param("i",$id);
    $stmt->execute();
    $user=$stmt->get_result()->fetch_assoc();
}

if($_SERVER["REQUEST_METHOD"]=="POST"){
    if(isset($_POST['update_password'])){
        if(!empty($_POST['password'])){
            $new_pass=password_hash($_POST['password'],PASSWORD_DEFAULT);
            $stmt=$conn->prepare("Update users set password =? where user_id=?");
            $stmt->bind_param("si",$new_pass,$id);
            if($stmt->execute()){
                $message = "Password updated successfully!";
                $message_type = "success";
            } else {
                $message = "Error updating password: ".$stmt->error;
                $message_type = "danger";
            }
        }else{
            $message = "No changes made - password field was empty.";
            $message_type = "warning";
        }
    }
    else if(isset($_POST['delete_inspector'])){
        $stmt=$conn->prepare("delete from users where user_id=?");
        $stmt->bind_param("i",$id);
        if($stmt->execute()){
            header("Location: add_inspector.php");
            exit();
        } else {
            $message = "Error deleting inspector: ".$stmt->error;
            $message_type = "danger";
        }
    }
}
if(!isset($user)){
    $stmt=$conn->prepare("select * from users where user_id=?");
    $stmt->bind_param("i",$id);
    $stmt->execute();
    $user=$stmt->get_result()->fetch_assoc();
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
                <p class="alert-message" style="margin: 0;"><?= $message ?></p>
            </div>
        </div>
        <?php endif; ?>

        <div class="card-body">
            <!-- Change Password -->
            <form method="POST">
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
                    <p style="font-size: var(--text-sm); color: var(--color-text-muted); margin: 0 0 var(--space-3);">Deleting this inspector will permanently remove their account and all associated data.</p>
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