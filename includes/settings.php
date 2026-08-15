<?php
session_start();
include 'db_connect.php';

if(!isset($_SESSION['user_id'])){
    header("Location: logout.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";
$message_type = "";

/* ===============================
   Upload Profile Photo
===============================*/

if(isset($_POST['upload_photo']) && isset($_FILES['profile_photo'])){

    if($_FILES['profile_photo']['error']==0){

        $ext = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));

        $allowed = ['jpg','jpeg','png','webp'];

        if(in_array($ext,$allowed)){

            if(!is_dir("uploads/profile")){
                mkdir("uploads/profile",0777,true);
            }

            $filename = "user_".$user_id.".".$ext;

            move_uploaded_file(
                $_FILES['profile_photo']['tmp_name'],
                "uploads/profile/".$filename
            );

            $stmt = $conn->prepare("
            UPDATE users
            SET profile_photo=?
            WHERE user_id=?
            ");

            $stmt->bind_param("si",$filename,$user_id);
            $stmt->execute();

            $message = "Profile photo updated.";
            $message_type = "success";

        }else{

            $message = "Only JPG, PNG and WEBP images are allowed.";
            $message_type = "danger";

        }

    }

}

/* ===============================
   Change Name
===============================*/

if(isset($_POST['update_name'])){

    $name = trim($_POST['new_name']);

    if($name!=""){

        $stmt = $conn->prepare("
        UPDATE users
        SET full_name=?
        WHERE user_id=?
        ");

        $stmt->bind_param("si",$name,$user_id);

        if($stmt->execute()){
            $message="Name updated successfully.";
            $message_type = "success";
        }

    }

}

/* ===============================
   Change Password
===============================*/

if(isset($_POST['update_password'])){

    if(!empty($_POST['password'])){

        $new_pass = password_hash(
            $_POST['password'],
            PASSWORD_DEFAULT
        );

        $stmt = $conn->prepare("
        UPDATE users
        SET password=?
        WHERE user_id=?
        ");

        $stmt->bind_param("si",$new_pass,$user_id);

        if($stmt->execute()){
            $message="Password updated successfully.";
            $message_type = "success";
        }

    }

}

/* ===============================
   Load User
===============================*/

$stmt = $conn->prepare("
SELECT
username,
full_name,
profile_photo,
role
FROM users
WHERE user_id=?
LIMIT 1
");

$stmt->bind_param("i",$user_id);
$stmt->execute();

$user = $stmt->get_result()->fetch_assoc();

include 'header.php';

$photo = "uploads/profile/default.jpg";

if(!empty($user['profile_photo']) && file_exists("uploads/profile/".$user['profile_photo'])){
    $photo = "uploads/profile/".$user['profile_photo'];
}

$name = !empty($user['full_name'])
            ? $user['full_name']
            : $user['username'];
?>

<div class="page">
    <div class="card" style="max-width: 600px;">

        <div class="card-header">
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
                <button type="submit" class="btn btn-danger">
                    <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
                    Logout
                </button>
            </form>
        </div>

    </div>
</div>

<?php include 'footer.php'; ?>