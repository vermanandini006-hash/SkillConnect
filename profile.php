
<?php
session_start();
require "db.php";

if (!isset($_SESSION["user"]["id"])) {
    header("Location: login.php");
    exit();
}

$conn->set_charset("utf8mb4");

function e($value) {
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

function parseSkills($raw) {
    if (empty($raw)) {
        return [];
    }

    $decoded = json_decode((string)$raw, true);

    $skills = is_array($decoded)
        ? $decoded
        : explode(",", (string)$raw);

    return array_values(array_filter(
        array_map("trim", $skills),
        fn($skill) => $skill !== ""
    ));
}

$loggedInUserId = (int)$_SESSION["user"]["id"];

$profileUserId = isset($_GET["id"])
    ? filter_var($_GET["id"], FILTER_VALIDATE_INT)
    : $loggedInUserId;

if (!$profileUserId || $profileUserId <= 0) {
    http_response_code(404);
    exit("Invalid student profile.");
}

$isOwnProfile = ($profileUserId === $loggedInUserId);

$message = "";
$messageType = "";

/* ========================================
   CREATE REPORTS TABLE
======================================== */

$conn->query("
    CREATE TABLE IF NOT EXISTS reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reporter_id INT NOT NULL,
        reported_user_id INT NOT NULL,
        reason VARCHAR(100) NOT NULL,
        description TEXT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

/* ========================================
   FETCH PROFILE
======================================== */

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
        skills_to_learn,
        status,
        created_at
    FROM users
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $profileUserId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    http_response_code(404);
    exit("Student profile not found.");
}

/* ========================================
   HANDLE POST REQUESTS
======================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    /* ====================================
       REPORT ANOTHER USER
    ==================================== */

    if (isset($_POST["submit_report"])) {

        if ($isOwnProfile) {
            $message = "You cannot report your own profile.";
            $messageType = "error";

        } else {

            $allowedReasons = [
                "Spam",
                "Fake Profile",
                "Inappropriate Behaviour",
                "Harassment",
                "Other"
            ];

            $reason = trim($_POST["reason"] ?? "");
            $description = trim($_POST["description"] ?? "");

            if (!in_array($reason, $allowedReasons, true)) {

                $message = "Please select a valid report reason.";
                $messageType = "error";

            } elseif (strlen($description) > 2000) {

                $message = "Description must be under 2000 characters.";
                $messageType = "error";

            } else {

                $reportStmt = $conn->prepare("
                    INSERT INTO reports
                    (
                        reporter_id,
                        reported_user_id,
                        reason,
                        description,
                        status
                    )
                    VALUES (?, ?, ?, ?, 'pending')
                ");

                $reportStmt->bind_param(
                    "iiss",
                    $loggedInUserId,
                    $profileUserId,
                    $reason,
                    $description
                );

                if ($reportStmt->execute()) {
                    $message = "Your report has been submitted to the admin.";
                    $messageType = "success";
                } else {
                    $message = "Unable to submit report. Please try again.";
                    $messageType = "error";
                }

                $reportStmt->close();
            }
        }
    }

    /* ====================================
       CHANGE PROFILE PHOTO - OWNER ONLY
    ==================================== */

    elseif (isset($_POST["upload_photo"])) {

        if (!$isOwnProfile) {
            http_response_code(403);
            exit("You cannot edit another student's profile.");
        }

        if (
            !isset($_FILES["profile_photo"])
            || $_FILES["profile_photo"]["error"] !== UPLOAD_ERR_OK
        ) {

            $message = "Please select a profile photo.";
            $messageType = "error";

        } else {

            $file = $_FILES["profile_photo"];
            $fileTmp = $file["tmp_name"];
            $fileSize = $file["size"];

            $allowedTypes = [
                "image/jpeg" => "jpg",
                "image/png" => "png",
                "image/webp" => "webp"
            ];

            $fileType = mime_content_type($fileTmp);

            if (!isset($allowedTypes[$fileType])) {

                $message = "Only JPG, PNG and WEBP images are allowed.";
                $messageType = "error";

            } elseif ($fileSize > 2 * 1024 * 1024) {

                $message = "Image size must be less than 2MB.";
                $messageType = "error";

            } else {

                $uploadFolder = "uploads/profiles/";

                if (!is_dir($uploadFolder)) {
                    mkdir($uploadFolder, 0777, true);
                }

                $newFileName =
                    "profile_" .
                    $loggedInUserId . "_" .
                    bin2hex(random_bytes(8)) . "." .
                    $allowedTypes[$fileType];

                $uploadPath = $uploadFolder . $newFileName;

                if (move_uploaded_file($fileTmp, $uploadPath)) {

                    $oldImage = $user["profile_image"] ?? "";

                    $photoStmt = $conn->prepare("
                        UPDATE users
                        SET profile_image = ?
                        WHERE id = ?
                    ");

                    $photoStmt->bind_param(
                        "si",
                        $uploadPath,
                        $loggedInUserId
                    );

                    if ($photoStmt->execute()) {

                        if (
                            $oldImage !== ""
                            && strpos(
                                $oldImage,
                                "uploads/profiles/"
                            ) === 0
                            && is_file($oldImage)
                        ) {
                            unlink($oldImage);
                        }

                        $user["profile_image"] = $uploadPath;
                        $_SESSION["user"]["profile_image"] = $uploadPath;

                        $message = "Profile photo updated successfully.";
                        $messageType = "success";

                    } else {

                        if (is_file($uploadPath)) {
                            unlink($uploadPath);
                        }

                        $message = "Unable to update profile photo.";
                        $messageType = "error";
                    }

                    $photoStmt->close();

                } else {

                    $message = "Unable to upload image.";
                    $messageType = "error";
                }
            }
        }
    }

    /* ====================================
       REMOVE PROFILE PHOTO - OWNER ONLY
    ==================================== */

    elseif (isset($_POST["remove_photo"])) {

        if (!$isOwnProfile) {
            http_response_code(403);
            exit("You cannot edit another student's profile.");
        }

        $oldImage = $user["profile_image"] ?? "";

        if ($oldImage !== "") {

            $removeStmt = $conn->prepare("
                UPDATE users
                SET profile_image = NULL
                WHERE id = ?
            ");

            $removeStmt->bind_param("i", $loggedInUserId);

            if ($removeStmt->execute()) {

                if (
                    strpos(
                        $oldImage,
                        "uploads/profiles/"
                    ) === 0
                    && is_file($oldImage)
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

            $message = "No profile photo to remove.";
            $messageType = "error";
        }
    }
}

$teachSkills = parseSkills($user["skills_to_teach"] ?? "");
$learnSkills = parseSkills($user["skills_to_learn"] ?? "");
$profileImage = $user["profile_image"] ?? "";

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>
    <?= $isOwnProfile ? "My Profile" : e($user["name"]) . " - Profile" ?>
    - SkillConnect
</title>

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: Arial, Helvetica, sans-serif;
    background: #f4faf6;
    color: #333;
}

.container {
    width: 90%;
    max-width: 1100px;
    margin: 40px auto 60px;
}

.page-header {
    margin-bottom: 25px;
}

.page-header h1 {
    color: #285b41;
    font-size: 30px;
    margin-bottom: 7px;
}

.page-header p {
    color: #777;
    font-size: 15px;
}

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

.profile-card {
    background: white;
    border-radius: 18px;
    padding: 30px;
    display: flex;
    align-items: center;
    gap: 28px;
    box-shadow: 0 7px 25px rgba(0,0,0,.06);
    margin-bottom: 25px;
}

.photo-section {
    text-align: center;
    min-width: 150px;
}

.profile-image,
.profile-placeholder {
    width: 125px;
    height: 125px;
    border-radius: 50%;
    border: 5px solid #e4f2e9;
}

.profile-image {
    object-fit: cover;
}

.profile-placeholder {
    background: #dcefe4;
    color: #2f7653;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 45px;
    font-weight: bold;
    margin: auto;
}

.photo-actions {
    margin-top: 12px;
}

.upload-label {
    display: inline-block;
    background: #3b7d5a;
    color: white;
    padding: 8px 13px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 12px;
    font-weight: bold;
}

.upload-label:hover {
    background: #285b41;
}

.upload-label input {
    display: none;
}

.remove-btn {
    background: #f5e8e8;
    color: #b42318;
    border: none;
    padding: 8px 13px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 12px;
    font-weight: bold;
    margin-left: 5px;
}

.profile-info {
    flex: 1;
}

.profile-info h2 {
    color: #285b41;
    font-size: 27px;
    margin-bottom: 8px;
}

.email {
    color: #666;
    margin-bottom: 8px;
    font-size: 14px;
}

.course {
    color: #555;
    margin-bottom: 5px;
    font-size: 15px;
    line-height: 1.8;
}

.status {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    margin-top: 8px;
    font-weight: bold;
}

.status.online {
    color: #217044;
    background: #e7f7ed;
}

.status.offline {
    color: #777;
    background: #eeeeee;
}

.profile-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
    align-items: flex-start;
}

.edit-btn,
.back-btn,
.report-btn {
    text-decoration: none;
    background: #3b7d5a;
    color: white;
    padding: 11px 18px;
    border-radius: 9px;
    font-weight: bold;
    display: inline-block;
    white-space: nowrap;
    border: none;
    font-size: 14px;
    cursor: pointer;
    text-align: center;
}

.edit-btn:hover,
.back-btn:hover {
    background: #285b41;
}

.report-btn {
    background: #fff0ef;
    color: #b42318;
    border: 1px solid #f3ceca;
}

.report-btn:hover {
    background: #fbe0de;
}

.grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 22px;
}

.card {
    background: white;
    padding: 25px;
    border-radius: 16px;
    box-shadow: 0 5px 20px rgba(0,0,0,.05);
}

.card h3 {
    color: #285b41;
    margin-bottom: 16px;
    font-size: 20px;
}

.bio {
    line-height: 1.7;
    color: #666;
    font-size: 15px;
    overflow-wrap: anywhere;
}

.empty {
    color: #999;
    font-style: italic;
    font-size: 14px;
}

.skills {
    display: flex;
    flex-wrap: wrap;
    gap: 9px;
}

.skill {
    background: #e7f5ec;
    color: #2f7653;
    padding: 8px 13px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: bold;
}

.info-row {
    display: flex;
    justify-content: space-between;
    gap: 20px;
    padding: 12px 0;
    border-bottom: 1px solid #edf2ee;
}

.info-row:last-child {
    border-bottom: none;
}

.info-label {
    color: #777;
    font-size: 14px;
}

.info-value {
    color: #333;
    font-weight: bold;
    text-align: right;
    font-size: 14px;
    word-break: break-word;
}

/* ========================================
   REPORT USER MODAL
======================================== */

.report-modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(12, 35, 22, .65);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.report-modal.show {
    display: flex;
}

.report-box {
    width: 100%;
    max-width: 470px;
    max-height: 90vh;
    overflow-y: auto;
    background: white;
    border-radius: 18px;
    padding: 28px;
    box-shadow: 0 15px 45px rgba(0,0,0,.18);
}

.report-box h2 {
    color: #285b41;
    margin-bottom: 10px;
    font-size: 23px;
}

.report-box p {
    color: #777;
    font-size: 14px;
    line-height: 1.6;
    margin-bottom: 18px;
}

.report-box label {
    display: block;
    color: #285b41;
    font-weight: bold;
    font-size: 14px;
    margin-bottom: 8px;
}

.report-box select,
.report-box textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #d8e3dc;
    border-radius: 9px;
    font-size: 14px;
    font-family: Arial, sans-serif;
    margin-bottom: 17px;
    outline: none;
}

.report-box textarea {
    min-height: 110px;
    resize: vertical;
}

.report-box select:focus,
.report-box textarea:focus {
    border-color: #3b7d5a;
}

.report-buttons {
    display: flex;
    gap: 10px;
}

.submit-report {
    background: #b42318;
    color: white;
    border: none;
    border-radius: 9px;
    padding: 12px 18px;
    font-weight: bold;
    cursor: pointer;
}

.close-report {
    background: #edf2ee;
    color: #333;
    border: none;
    border-radius: 9px;
    padding: 12px 18px;
    font-weight: bold;
    cursor: pointer;
}

@media (max-width: 750px) {

    .container {
        width: 92%;
        margin-top: 25px;
    }

    .profile-card {
        flex-direction: column;
        text-align: center;
        padding: 25px 20px;
    }

    .profile-info {
        width: 100%;
    }

    .profile-actions {
        align-items: center;
        width: 100%;
    }

    .grid {
        grid-template-columns: 1fr;
    }

    .info-row {
        flex-direction: column;
        gap: 5px;
    }

    .info-value {
        text-align: left;
    }
}

@media (max-width: 500px) {

    .page-header h1 {
        font-size: 25px;
    }

    .profile-info h2 {
        font-size: 23px;
    }

    .card {
        padding: 20px;
    }

    .report-box {
        padding: 22px;
    }
}

</style>

</head>

<body>

<?php include "common-navbar.php"; ?>

<div class="container">

    <div class="page-header">

        <h1>
            <?= $isOwnProfile
                ? "My Profile"
                : e($user["name"]) . "'s Profile"
            ?>
        </h1>

        <p>
            <?php if ($isOwnProfile): ?>
                Manage and view your SkillConnect profile.
            <?php else: ?>
                View the skills and information shared by this student.
            <?php endif; ?>
        </p>

    </div>

    <?php if ($message !== ""): ?>

        <div class="message <?= e($messageType) ?>">
            <?= e($message) ?>
        </div>

    <?php endif; ?>

    <div class="profile-card">

        <div class="photo-section">

            <?php if (!empty($profileImage)): ?>

                <img
                    src="<?= e($profileImage) ?>"
                    class="profile-image"
                    alt="Profile Photo"
                >

            <?php else: ?>

                <div class="profile-placeholder">
                    <?= e(strtoupper(substr($user["name"], 0, 1))) ?>
                </div>

            <?php endif; ?>

            <?php if ($isOwnProfile): ?>

                <div class="photo-actions">

                    <form
                        method="POST"
                        enctype="multipart/form-data"
                        id="photoForm"
                    >

                        <label class="upload-label">

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
                            name="upload_photo"
                            value="1"
                        >

                    </form>

                    <?php if (!empty($profileImage)): ?>

                        <form
                            method="POST"
                            onsubmit="return confirm('Remove your profile photo?');"
                        >

                            <button
                                type="submit"
                                name="remove_photo"
                                class="remove-btn"
                            >
                                Remove
                            </button>

                        </form>

                    <?php endif; ?>

                </div>

            <?php endif; ?>

        </div>

        <div class="profile-info">

            <h2><?= e($user["name"]) ?></h2>

            <div class="email">
                📧 <?= e($user["email"]) ?>
            </div>

            <div class="course">

                🎓 <?= e($user["course"] ?: "Course not added") ?>

                <?php if (!empty($user["college"])): ?>

                    <br>
                    🏫 <?= e($user["college"]) ?>

                <?php endif; ?>

            </div>

            <?php if (($user["status"] ?? "") === "online"): ?>

                <span class="status online">● Online</span>

            <?php else: ?>

                <span class="status offline">● Offline</span>

            <?php endif; ?>

        </div>

        <div class="profile-actions">

            <?php if ($isOwnProfile): ?>

                <a
                    href="edit-profile.php"
                    class="edit-btn"
                >
                    ✏️ Edit Profile
                </a>

            <?php else: ?>

                <a
                    href="requests.php"
                    class="back-btn"
                >
                    ← Back to Requests
                </a>

                <button
                    type="button"
                    class="report-btn"
                    onclick="openReportModal()"
                >
                    🚩 Report User
                </button>

            <?php endif; ?>

        </div>

    </div>

    <div class="grid">

        <!-- ABOUT ME -->

        <div class="card">

            <h3>👋 About Me</h3>

            <?php if (!empty($user["bio"])): ?>

                <p class="bio">
                    <?= nl2br(e($user["bio"])) ?>
                </p>

            <?php else: ?>

                <p class="empty">
                    No bio added yet.
                </p>

            <?php endif; ?>

        </div>

        <!-- ACCOUNT INFORMATION -->

        <div class="card">

            <h3>👤 Account Information</h3>

            <div class="info-row">
                <span class="info-label">Name</span>
                <span class="info-value"><?= e($user["name"]) ?></span>
            </div>

            <div class="info-row">
                <span class="info-label">Email</span>
                <span class="info-value"><?= e($user["email"]) ?></span>
            </div>

            <div class="info-row">
                <span class="info-label">Course</span>
                <span class="info-value">
                    <?= e($user["course"] ?: "Not added") ?>
                </span>
            </div>

            <div class="info-row">
                <span class="info-label">College</span>
                <span class="info-value">
                    <?= e($user["college"] ?: "Not added") ?>
                </span>
            </div>

            <div class="info-row">
                <span class="info-label">Member Since</span>
                <span class="info-value">
                    <?= !empty($user["created_at"])
                        ? e(date("d M Y", strtotime($user["created_at"])))
                        : "Not available"
                    ?>
                </span>
            </div>

        </div>

        <!-- SKILLS TO TEACH -->

        <div class="card">

            <h3>🎓 Skills I Can Teach</h3>

            <?php if (!empty($teachSkills)): ?>

                <div class="skills">

                    <?php foreach ($teachSkills as $skill): ?>

                        <div class="skill">
                            <?= e($skill) ?>
                        </div>

                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <p class="empty">
                    No teaching skills added yet.
                </p>

            <?php endif; ?>

        </div>

        <!-- SKILLS TO LEARN -->

        <div class="card">

            <h3>📚 Skills I Want to Learn</h3>

            <?php if (!empty($learnSkills)): ?>

                <div class="skills">

                    <?php foreach ($learnSkills as $skill): ?>

                        <div class="skill">
                            <?= e($skill) ?>
                        </div>

                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <p class="empty">
                    No learning skills added yet.
                </p>

            <?php endif; ?>

        </div>

    </div>

</div>

<!-- ========================================
     REPORT USER MODAL
======================================== -->

<?php if (!$isOwnProfile): ?>

<div
    class="report-modal"
    id="reportModal"
    role="dialog"
    aria-modal="true"
    aria-labelledby="reportTitle"
>

    <div class="report-box">

        <h2 id="reportTitle">
            🚩 Report User
        </h2>

        <p>
            Report <?= e($user["name"]) ?> to the SkillConnect admin.
            Please select a reason and explain the issue if needed.
        </p>

        <form method="POST">

            <label for="reason">
                Reason for Report
            </label>

            <select
                id="reason"
                name="reason"
                required
            >
                <option value="">
                    Select a reason
                </option>

                <option value="Spam">
                    Spam
                </option>

                <option value="Fake Profile">
                    Fake Profile
                </option>

                <option value="Inappropriate Behaviour">
                    Inappropriate Behaviour
                </option>

                <option value="Harassment">
                    Harassment
                </option>

                <option value="Other">
                    Other
                </option>

            </select>

            <label for="description">
                Description (Optional)
            </label>

            <textarea
                id="description"
                name="description"
                maxlength="2000"
                placeholder="Describe the issue..."
            ></textarea>

            <div class="report-buttons">

                <button
                    type="submit"
                    name="submit_report"
                    value="1"
                    class="submit-report"
                >
                    Submit Report
                </button>

                <button
                    type="button"
                    class="close-report"
                    onclick="closeReportModal()"
                >
                    Cancel
                </button>

            </div>

        </form>

    </div>

</div>

<?php endif; ?>

<script>

function openReportModal() {
    const modal = document.getElementById("reportModal");

    if (modal) {
        modal.classList.add("show");
        document.body.style.overflow = "hidden";
    }
}

function closeReportModal() {
    const modal = document.getElementById("reportModal");

    if (modal) {
        modal.classList.remove("show");
        document.body.style.overflow = "";
    }
}

document.addEventListener("click", function(event) {
    const modal = document.getElementById("reportModal");

    if (modal && event.target === modal) {
        closeReportModal();
    }
});

document.addEventListener("keydown", function(event) {
    if (event.key === "Escape") {
        closeReportModal();
    }
});

</script>

</body>
</html>
