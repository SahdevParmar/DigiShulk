<?php
session_start();
include 'db_connect.php';

if(!isset($_SESSION['user_id'])){
    header("Location: logout.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";

/* ===============================
   Upload Profile Photo
================================*/

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

        }else{

            $message = "Only JPG, PNG and WEBP images are allowed.";

        }

    }

}

/* ===============================
   Change Name
================================*/

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

        }

    }

}

/* ===============================
   Change Password
================================*/

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

        }

    }

}

/* ===============================
   Load User
================================*/

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

<div class="card">

    <h2>👤 My Profile</h2>

    <p class="subtitle">
        Manage your DigiShulk account
    </p>

    <?php if($message!=""): ?>

        <p style="color:green;font-weight:bold;">
            <?php echo $message; ?>
        </p>

    <?php endif; ?>


    <div class="profile-header">

        <img
            src="<?php echo $photo; ?>"
            class="profile-avatar">

        <h2>

            <?php echo htmlspecialchars($name); ?>

        </h2>

        <p>

            <?php echo ucfirst($user['role']); ?>

        </p>

    </div>

    <hr><br>

    <h3>📷 Profile Photo</h3>

    <form method="POST" enctype="multipart/form-data">

        <input
            type="file"
            name="profile_photo"
            accept=".jpg,.jpeg,.png,.webp"
            required>

        <button
            type="submit"
            name="upload_photo">

            Upload Photo

        </button>

    </form>

    <br>

    <h3>✏ Change Name</h3>

    <form method="POST">

        <input
            type="text"
            name="new_name"
            value="<?php echo htmlspecialchars($name); ?>"
            required>

        <button
            type="submit"
            name="update_name">

            Update Name

        </button>

    </form>

    <br>

    <h3>🔒 Change Password</h3>

    <form method="POST">

        <input
            type="password"
            name="password"
            placeholder="New Password">

        <button
            type="submit"
            name="update_password">

            Update Password

        </button>

    </form>

    <hr><br>

    <a href="logout.php" class="card-action-logout">
        Logout
    </a>

</div>

<?php include 'footer.php'; ?>