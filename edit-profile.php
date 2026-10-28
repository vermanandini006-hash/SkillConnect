
<?php
session_start();
require "db.php";

/* =========================
   CHECK LOGIN
========================= */

if (!isset($_SESSION["user"]["id"])) {
    header("Location: login.php");
    exit();
}

$userId = (int) $_SESSION["user"]["id"];

if ($userId <= 0) {
    header("Location: login.php");
    exit();
}

$message = "";
$messageType = "";

/* =========================
   GET CURRENT USER
========================= */

$stmt = $conn->prepare("
    SELECT
        id,
        name,
        email,
        course,
        college,
        bio,
        profile_image,
        skills_to_teach,
        skills_to_learn
    FROM users
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $userId);
$stmt->execute();

$result = $stmt->get_result();
$user = $result->fetch_assoc();

$stmt->close();

/* =========================
   USER NOT FOUND
========================= */

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit();
}

/* =========================
   CHANGE PROFILE PHOTO
========================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["change_photo"])
) {

    if (
        !isset($_FILES["profile_photo"])
        || $_FILES["profile_photo"]["error"] !== UPLOAD_ERR_OK
    ) {
        $message = "Please select a photo.";
        $messageType = "error";

    } else {

        $file = $_FILES["profile_photo"];
        $tmpName = $file["tmp_name"];
        $fileSize = $file["size"];

        $allowedTypes = [
            "image/jpeg",
            "image/png",
            "image/webp"
        ];

        $fileType = mime_content_type($tmpName);

        if (!in_array($fileType, $allowedTypes, true)) {

            $message = "Only JPG, PNG and WEBP images are allowed.";
            $messageType = "error";

        } elseif ($fileSize > 2 * 1024 * 1024) {

            $message = "Photo size must be less than 2MB.";
            $messageType = "error";

        } else {

            $uploadFolder = "uploads/profiles/";

            if (!is_dir($uploadFolder)) {
                mkdir($uploadFolder, 0777, true);
            }

            if ($fileType === "image/jpeg") {
                $extension = "jpg";
            } elseif ($fileType === "image/png") {
                $extension = "png";
            } else {
                $extension = "webp";
            }

            $newFileName =
                "profile_" .
                $userId . "_" .
                time() . "_" .
                bin2hex(random_bytes(4)) .
                "." . $extension;

            $newFilePath = $uploadFolder . $newFileName;

            if (move_uploaded_file($tmpName, $newFilePath)) {

                $oldImage = $user["profile_image"];

                $photoStmt = $conn->prepare("
                    UPDATE users
                    SET profile_image = ?
                    WHERE id = ?
                ");

                $photoStmt->bind_param(
                    "si",
                    $newFilePath,
                    $userId
                );

                if ($photoStmt->execute()) {

                    if (
                        !empty($oldImage)
                        && strpos(
                            $oldImage,
                            "uploads/profiles/"
                        ) === 0
                        && file_exists($oldImage)
                    ) {
                        unlink($oldImage);
                    }

                    $user["profile_image"] = $newFilePath;
                    $_SESSION["user"]["profile_image"] = $newFilePath;

                    $message = "Profile photo changed successfully.";
                    $messageType = "success";

                } else {

                    if (file_exists($newFilePath)) {
                        unlink($newFilePath);
                    }

                    $message = "Unable to update profile photo.";
                    $messageType = "error";
                }

                $photoStmt->close();

            } else {

                $message = "Unable to upload photo.";
                $messageType = "error";
            }
        }
    }
}

/* =========================
   REMOVE PROFILE PHOTO
========================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["remove_photo"])
) {

    $oldImage = $user["profile_image"];

    if (!empty($oldImage)) {

        $removeStmt = $conn->prepare("
            UPDATE users
            SET profile_image = NULL
            WHERE id = ?
        ");

        $removeStmt->bind_param("i", $userId);

        if ($removeStmt->execute()) {

            if (
                strpos(
                    $oldImage,
                    "uploads/profiles/"
                ) === 0
                && file_exists($oldImage)
            ) {
                unlink($oldImage);
            }

            $user["profile_image"] = null;
            $_SESSION["user"]["profile_image"] = null;

            $message = "Profile photo removed successfully.";
            $messageType = "success";

        } else {

            $message = "Unable to remove profile photo.";
            $messageType = "error";
        }

        $removeStmt->close();

    } else {

        $message = "No profile photo found.";
        $messageType = "error";
    }
}

/* =========================
   UPDATE PROFILE DETAILS
========================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["update_profile"])
) {

    $name = trim($_POST["name"] ?? "");
    $course = trim($_POST["course"] ?? "");
    $college = trim($_POST["college"] ?? "");
    $bio = trim($_POST["bio"] ?? "");
    $skillsToTeach = trim($_POST["skills_to_teach"] ?? "");
    $skillsToLearn = trim($_POST["skills_to_learn"] ?? "");

    if ($name === "") {

        $message = "Name is required.";
        $messageType = "error";

        $user["name"] = $name;
        $user["course"] = $course;
        $user["college"] = $college;
        $user["bio"] = $bio;
        $user["skills_to_teach"] = $skillsToTeach;
        $user["skills_to_learn"] = $skillsToLearn;

    } else {

        $updateStmt = $conn->prepare("
            UPDATE users
            SET
                name = ?,
                course = ?,
                college = ?,
                bio = ?,
                skills_to_teach = ?,
                skills_to_learn = ?
            WHERE id = ?
        ");

        $updateStmt->bind_param(
            "ssssssi",
            $name,
            $course,
            $college,
            $bio,
            $skillsToTeach,
            $skillsToLearn,
            $userId
        );

        if ($updateStmt->execute()) {

            /* UPDATE SESSION */

            $_SESSION["user"]["name"] = $name;
            $_SESSION["user"]["course"] = $course;
            $_SESSION["user"]["college"] = $college;
            $_SESSION["user"]["bio"] = $bio;
            $_SESSION["user"]["skills_to_teach"] = $skillsToTeach;
            $_SESSION["user"]["skills_to_learn"] = $skillsToLearn;

            $updateStmt->close();

            /* =========================
               AUTOMATIC DASHBOARD REDIRECT
            ========================= */

            header("Location: dashboard.php");
            exit();

        } else {

            $message = "Unable to update profile.";
            $messageType = "error";

            $user["name"] = $name;
            $user["course"] = $course;
            $user["college"] = $college;
            $user["bio"] = $bio;
            $user["skills_to_teach"] = $skillsToTeach;
            $user["skills_to_learn"] = $skillsToLearn;
        }

        $updateStmt->close();
    }
}

/* =========================
   ESCAPE OUTPUT
========================= */

function editEscape($value) {
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Edit Profile - SkillConnect</title>

<style>

/* =========================
   BASIC
========================= */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: Arial, sans-serif;
    background: #f4faf6;
    color: #333;
}

/* =========================
   CONTAINER
========================= */

.container {
    width: 90%;
    max-width: 850px;
    margin: 40px auto 60px;
}

/* =========================
   TITLE
========================= */

.page-title {
    margin-bottom: 25px;
}

.page-title h1 {
    color: #285b41;
    font-size: 30px;
    margin-bottom: 7px;
}

.page-title p {
    color: #777;
    font-size: 15px;
}

/* =========================
   MESSAGE
========================= */

.message {
    padding: 13px 16px;
    border-radius: 10px;
    margin-bottom: 20px;
    text-align: center;
    font-size: 14px;
}

.message.success {
    background: #e7f7ed;
    color: #217044;
}

.message.error {
    background: #fdeaea;
    color: #b42318;
}

/* =========================
   FORM CARD
========================= */

.form-card {
    background: #ffffff;
    padding: 30px;
    border-radius: 18px;
    box-shadow: 0 7px 25px rgba(0,0,0,0.06);
}

/* =========================
   PHOTO AREA
========================= */

.photo-area {
    text-align: center;
    padding-bottom: 25px;
    margin-bottom: 25px;
    border-bottom: 1px solid #edf2ee;
}

.profile-image {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    object-fit: cover;
    border: 5px solid #e4f2e9;
}

.profile-placeholder {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: #dcefe4;
    color: #2f7653;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 45px;
    font-weight: bold;
    border: 5px solid #e4f2e9;
}

/* =========================
   PHOTO BUTTONS
========================= */

.photo-buttons {
    margin-top: 15px;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.change-photo {
    display: inline-block;
    background: #3b7d5a;
    color: white;
    padding: 9px 15px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 13px;
    font-weight: bold;
    transition: 0.2s;
}

.change-photo:hover {
    background: #285b41;
}

.change-photo input {
    display: none;
}

.remove-photo {
    background: #fff0ef;
    color: #b42318;
    border: none;
    padding: 9px 15px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 13px;
    font-weight: bold;
}

.remove-photo:hover {
    background: #fbe0de;
}

.photo-hint {
    margin-top: 9px;
    color: #888;
    font-size: 12px;
}

/* =========================
   FORM
========================= */

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    color: #285b41;
    font-weight: 600;
    font-size: 14px;
}

.form-group input,
.form-group textarea {
    width: 100%;
    padding: 12px 14px;
    border: 1px solid #d8e3dc;
    border-radius: 9px;
    font-size: 14px;
    outline: none;
    transition: 0.2s;
    font-family: Arial, sans-serif;
}

.form-group input:focus,
.form-group textarea:focus {
    border-color: #3b7d5a;
    box-shadow: 0 0 0 3px rgba(59,125,90,0.10);
}

.form-group textarea {
    min-height: 110px;
    resize: vertical;
}

.form-group input:disabled {
    background: #f3f5f4;
    color: #777;
    cursor: not-allowed;
}

/* =========================
   HINT
========================= */

.hint {
    color: #888;
    font-size: 12px;
    margin-top: 6px;
}

/* =========================
   BUTTONS
========================= */

.buttons {
    display: flex;
    gap: 12px;
    margin-top: 25px;
}

.save-btn {
    background: #3b7d5a;
    color: white;
    border: none;
    padding: 12px 22px;
    border-radius: 9px;
    font-size: 14px;
    font-weight: bold;
    cursor: pointer;
}

.save-btn:hover {
    background: #285b41;
}

.cancel-btn {
    text-decoration: none;
    background: #edf2ee;
    color: #555;
    padding: 12px 22px;
    border-radius: 9px;
    font-size: 14px;
    font-weight: bold;
}

.cancel-btn:hover {
    background: #e0e7e2;
}

/* =========================
   MOBILE
========================= */

@media(max-width:600px) {

    .container {
        width: 92%;
        margin-top: 25px;
    }

    .form-card {
        padding: 22px;
    }

    .page-title h1 {
        font-size: 26px;
    }

    .buttons {
        flex-direction: column;
    }

    .save-btn,
    .cancel-btn {
        width: 100%;
        text-align: center;
    }
}

</style>

</head>

<body>

<!-- =========================
     COMMON NAVBAR
========================= -->

<?php include "common-navbar.php"; ?>

<!-- =========================
     MAIN CONTENT
========================= -->

<div class="container">

    <div class="page-title">

        <h1>Edit Profile</h1>

        <p>
            Update your SkillConnect profile information.
        </p>

    </div>

    <!-- MESSAGE -->

    <?php if (!empty($message)): ?>

        <div class="message <?= editEscape($messageType) ?>">
            <?= editEscape($message) ?>
        </div>

    <?php endif; ?>

    <!-- =========================
         FORM CARD
    ========================= -->

    <div class="form-card">

        <!-- =========================
             PROFILE PHOTO
        ========================= -->

        <div class="photo-area">

            <?php if (!empty($user["profile_image"])): ?>

                <img
                    src="<?= editEscape($user["profile_image"]) ?>"
                    class="profile-image"
                    alt="Profile Photo"
                >

            <?php else: ?>

                <div class="profile-placeholder">

                    <?= editEscape(
                        strtoupper(
                            substr(
                                $user["name"] ?: "S",
                                0,
                                1
                            )
                        )
                    ) ?>

                </div>

            <?php endif; ?>

            <div class="photo-buttons">

                <!-- CHANGE PHOTO -->

                <form
                    method="POST"
                    enctype="multipart/form-data"
                    id="photoForm"
                >

                    <label class="change-photo">

                        📷 Change Photo

                        <input
                            type="file"
                            name="profile_photo"
                            accept="image/jpeg,image/png,image/webp"
                            onchange="document.getElementById('photoForm').submit();"
                        >

                    </label>

                    <input
                        type="hidden"
                        name="change_photo"
                        value="1"
                    >

                </form>

                <!-- REMOVE PHOTO -->

                <?php if (!empty($user["profile_image"])): ?>

                    <form
                        method="POST"
                        onsubmit="return confirm('Are you sure you want to remove your profile photo?');"
                    >

                        <button
                            type="submit"
                            name="remove_photo"
                            class="remove-photo"
                        >
                            🗑️ Remove
                        </button>

                    </form>

                <?php endif; ?>

            </div>

            <div class="photo-hint">
                JPG, PNG or WEBP • Maximum 2MB
            </div>

        </div>

        <!-- =========================
             PROFILE FORM
        ========================= -->

        <form method="POST" action="">

            <!-- NAME -->

            <div class="form-group">

                <label for="name">
                    Full Name
                </label>

                <input
                    type="text"
                    id="name"
                    name="name"
                    value="<?= editEscape($user["name"]) ?>"
                    placeholder="Enter your full name"
                    required
                >

            </div>

            <!-- EMAIL -->

            <div class="form-group">

                <label for="email">
                    Email
                </label>

                <input
                    type="email"
                    id="email"
                    value="<?= editEscape($user["email"]) ?>"
                    disabled
                >

                <div class="hint">
                    Email cannot be changed from this page.
                </div>

            </div>

            <!-- COURSE -->

            <div class="form-group">

                <label for="course">
                    Course
                </label>

                <input
                    type="text"
                    id="course"
                    name="course"
                    value="<?= editEscape($user["course"] ?? "") ?>"
                    placeholder="e.g. BCA, B.Tech, MCA"
                >

            </div>

            <!-- COLLEGE -->

            <div class="form-group">

                <label for="college">
                    College
                </label>

                <input
                    type="text"
                    id="college"
                    name="college"
                    value="<?= editEscape($user["college"] ?? "") ?>"
                    placeholder="Enter your college"
                >

            </div>

            <!-- BIO -->

            <div class="form-group">

                <label for="bio">
                    About Me
                </label>

                <textarea
                    id="bio"
                    name="bio"
                    placeholder="Tell something about yourself..."
                ><?= editEscape($user["bio"] ?? "") ?></textarea>

            </div>

            <!-- SKILLS TO TEACH -->

            <div class="form-group">

                <label for="skills_to_teach">
                    Skills I Can Teach
                </label>

                <input
                    type="text"
                    id="skills_to_teach"
                    name="skills_to_teach"
                    value="<?= editEscape($user["skills_to_teach"] ?? "") ?>"
                    placeholder="PHP, Java, HTML, CSS"
                >

                <div class="hint">
                    Separate multiple skills with commas.
                </div>

            </div>

            <!-- SKILLS TO LEARN -->

            <div class="form-group">

                <label for="skills_to_learn">
                    Skills I Want to Learn
                </label>

                <input
                    type="text"
                    id="skills_to_learn"
                    name="skills_to_learn"
                    value="<?= editEscape($user["skills_to_learn"] ?? "") ?>"
                    placeholder="Python, React, JavaScript"
                >

                <div class="hint">
                    Separate multiple skills with commas.
                </div>

            </div>

            <!-- BUTTONS -->

            <div class="buttons">

                <button
                    type="submit"
                    name="update_profile"
                    value="1"
                    class="save-btn"
                >
                    💾 Save Changes
                </button>

                <a
                    href="profile.php"
                    class="cancel-btn"
                >
                    Cancel
                </a>

            </div>

        </form>

    </div>

</div>

</body>
</html>
