
<?php
session_start();
require "db.php";

if (!isset($_SESSION["user"]["id"])) {
    header("Location: login.php");
    exit();
}

$currentUserId = (int) $_SESSION["user"]["id"];
$currentUserName = $_SESSION["user"]["name"] ?? "Student";

$message = "";
$messageType = "";

/* =========================
   GET MY TEACHING SKILLS
========================= */

$teachSkills = [];

$stmt = $conn->prepare(
    "SELECT skills_to_teach
     FROM users
     WHERE id = ?
     LIMIT 1"
);

$stmt->bind_param("i", $currentUserId);
$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {

    $skillsText = trim($row["skills_to_teach"] ?? "");

    if ($skillsText !== "") {

        $teachSkills = array_filter(
            array_map(
                "trim",
                explode(",", $skillsText)
            )
        );
    }
}

$stmt->close();

/* =========================
   UPLOAD VIDEO
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $skill = trim($_POST["skill"] ?? "");
    $title = trim($_POST["title"] ?? "");

    if ($skill === "") {

        $message = "Please select a skill.";
        $messageType = "error";

    } elseif ($title === "") {

        $message = "Please enter a video title.";
        $messageType = "error";

    } elseif (!isset($_FILES["video"])) {

        $message = "Please select a video.";
        $messageType = "error";

    } else {

        $video = $_FILES["video"];

        /* CHECK TEACHING SKILL */

        $validSkill = false;

        foreach ($teachSkills as $teachSkill) {

            if (strcasecmp($teachSkill, $skill) === 0) {
                $validSkill = true;
                break;
            }
        }

        if (!$validSkill) {

            $message = "You can only upload resources for skills you teach.";
            $messageType = "error";

        } elseif ($video["error"] !== UPLOAD_ERR_OK) {

            $message = "Video upload failed. Please try again.";
            $messageType = "error";

        } else {

            /* MAXIMUM FILE SIZE: 100 MB */

            $maxSize = 100 * 1024 * 1024;

            if ($video["size"] > $maxSize) {

                $message = "Video size must be less than 100 MB.";
                $messageType = "error";

            } else {

                /* ALLOWED VIDEO TYPES */

                $allowedTypes = [
                    "video/mp4",
                    "video/webm",
                    "video/ogg",
                    "video/quicktime"
                ];

                $fileType = mime_content_type($video["tmp_name"]);

                if (!in_array($fileType, $allowedTypes, true)) {

                    $message = "Only MP4, WebM, OGG or MOV videos are allowed.";
                    $messageType = "error";

                } else {

                    /* CREATE UPLOAD FOLDER */

                    $uploadFolder = __DIR__ . "/uploads/videos/";

                    if (!is_dir($uploadFolder)) {
                        mkdir($uploadFolder, 0777, true);
                    }

                    /* GENERATE UNIQUE FILENAME */

                    $extension = strtolower(
                        pathinfo(
                            $video["name"],
                            PATHINFO_EXTENSION
                        )
                    );

                    $newFileName =
                        "video_" .
                        $currentUserId . "_" .
                        time() . "_" .
                        bin2hex(random_bytes(4)) .
                        "." . $extension;

                    $destination = $uploadFolder . $newFileName;

                    /* MOVE VIDEO */

                    if (move_uploaded_file($video["tmp_name"], $destination)) {

                        $filePath = "uploads/videos/" . $newFileName;

                        /* SAVE VIDEO IN RESOURCES TABLE */

                        $insertStmt = $conn->prepare("
                            INSERT INTO resources
                            (
                                exchange_id,
                                skill,
                                uploader_id,
                                uploader_name,
                                title,
                                resource_type,
                                file_name,
                                file_path
                            )
                            VALUES
                            (
                                NULL,
                                ?,
                                ?,
                                ?,
                                ?,
                                'video',
                                ?,
                                ?
                            )
                        ");

                        $insertStmt->bind_param(
                            "sissss",
                            $skill,
                            $currentUserId,
                            $currentUserName,
                            $title,
                            $video["name"],
                            $filePath
                        );

                        if ($insertStmt->execute()) {

                            $resourceId = (int) $conn->insert_id;

                            $message = "🎉 Video uploaded successfully!";
                            $messageType = "success";

                            /* =========================
                               VIDEO NOTIFICATIONS

                               Notify only students
                               learning this skill
                               from current uploader
                               through an active exchange.
                            ========================= */

                            try {

                                /*
                                   Exchange direction:

                                   student_two teaches
                                   'skill' to student_one.

                                   student_one may teach
                                   'return_skill' to student_two.
                                */

                                $partnerStmt = $conn->prepare("
                                    SELECT DISTINCT

                                        CASE
                                            WHEN student_one_id = ?
                                            THEN student_two_id
                                            ELSE student_one_id
                                        END AS partner_id

                                    FROM exchanges

                                    WHERE status = 'active'

                                    AND
                                    (
                                        (
                                            student_one_id = ?
                                            AND LOWER(TRIM(return_skill))
                                                = LOWER(TRIM(?))
                                        )

                                        OR

                                        (
                                            student_two_id = ?
                                            AND LOWER(TRIM(skill))
                                                = LOWER(TRIM(?))
                                        )
                                    )
                                ");

                                $partnerStmt->bind_param(
                                    "iisis",
                                    $currentUserId,
                                    $currentUserId,
                                    $skill,
                                    $currentUserId,
                                    $skill
                                );

                                $partnerStmt->execute();

                                $partners = $partnerStmt->get_result();

                                /* PREPARE NOTIFICATION */

                                $notificationStmt = $conn->prepare("
                                    INSERT INTO notifications
                                    (
                                        user_id,
                                        user_email,
                                        title,
                                        message,
                                        type,
                                        related_id,
                                        is_read
                                    )

                                    SELECT
                                        id,
                                        email,
                                        ?,
                                        ?,
                                        'video_upload',
                                        ?,
                                        0

                                    FROM users

                                    WHERE id = ?
                                ");

                                $notificationTitle = "New Video Uploaded";

                                $notificationMessage =
                                    $currentUserName .
                                    " uploaded a new " .
                                    $skill .
                                    " video: " .
                                    $title;

                                /* SEND TO MATCHING STUDENTS */

                                while ($partner = $partners->fetch_assoc()) {

                                    $partnerId = (int) $partner["partner_id"];

                                    if (
                                        $partnerId <= 0 ||
                                        $partnerId === $currentUserId
                                    ) {
                                        continue;
                                    }

                                    $notificationStmt->bind_param(
                                        "ssii",
                                        $notificationTitle,
                                        $notificationMessage,
                                        $resourceId,
                                        $partnerId
                                    );

                                    $notificationStmt->execute();
                                }

                                $notificationStmt->close();
                                $partnerStmt->close();

                            } catch (Throwable $error) {

                                /*
                                   Notification failure
                                   should not delete
                                   successfully uploaded video.
                                */

                                error_log(
                                    "Video notification error: " .
                                    $error->getMessage()
                                );
                            }

                        } else {

                            if (file_exists($destination)) {
                                unlink($destination);
                            }

                            $message = "Video could not be saved.";
                            $messageType = "error";
                        }

                        $insertStmt->close();

                    } else {

                        $message = "Could not save the uploaded video.";
                        $messageType = "error";
                    }
                }
            }
        }
    }
}

/* =========================
   COMMON NAVBAR
========================= */

require "common-navbar.php";
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Upload Video - SkillConnect</title>

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: Arial, sans-serif;
    background: #f4faf6;
    color: #243b2f;
}

/* =========================
   PAGE
========================= */

.page {
    max-width: 850px;
    margin: 100px auto 50px;
    padding: 20px;
}

.heading {
    text-align: center;
    margin-bottom: 30px;
}

.heading h1 {
    color: #285b41;
    font-size: 32px;
    margin-bottom: 8px;
}

.heading p {
    color: #66756d;
}

/* =========================
   UPLOAD CARD
========================= */

.upload-card {
    background: white;
    padding: 35px;
    border-radius: 20px;
    box-shadow: 0 10px 35px rgba(40, 91, 65, 0.10);
}

/* =========================
   FORM
========================= */

.form-group {
    margin-bottom: 22px;
}

label {
    display: block;
    margin-bottom: 9px;
    font-weight: bold;
    color: #285b41;
}

input,
select {
    width: 100%;
    padding: 13px 15px;
    border: 1px solid #d7e6dc;
    border-radius: 10px;
    font-size: 15px;
    background: #fbfefc;
    outline: none;
}

input:focus,
select:focus {
    border-color: #3b7d5a;
}

/* =========================
   FILE UPLOAD BOX
========================= */

.file-box {
    border: 2px dashed #b9d6c4;
    background: #f7fcf8;
    padding: 30px 20px;
    border-radius: 14px;
    text-align: center;
}

.file-icon {
    font-size: 45px;
    margin-bottom: 10px;
}

.file-box p {
    color: #687970;
    margin-bottom: 15px;
}

.file-box input {
    border: none;
    background: transparent;
}

/* =========================
   UPLOAD BUTTON
========================= */

.submit-btn {
    width: 100%;
    padding: 14px;
    border: none;
    border-radius: 11px;
    background: #3b7d5a;
    color: white;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
    transition: 0.3s;
}

.submit-btn:hover {
    background: #285b41;
}

.submit-btn:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

/* =========================
   MESSAGES
========================= */

.message {
    padding: 14px 16px;
    border-radius: 10px;
    margin-bottom: 20px;
    font-weight: 600;
}

.message.success {
    background: #e6f6ec;
    color: #267044;
    border: 1px solid #bce3c9;
}

.message.error {
    background: #fff0f0;
    color: #b33a3a;
    border: 1px solid #f0c4c4;
}

/* =========================
   INFORMATION BOX
========================= */

.info {
    margin-top: 20px;
    padding: 15px;
    border-radius: 10px;
    background: #f1f8f3;
    color: #617169;
    font-size: 13px;
    line-height: 1.6;
}

/* =========================
   MOBILE RESPONSIVE
========================= */

@media (max-width: 600px) {

    .page {
        margin-top: 90px;
        padding: 15px;
    }

    .upload-card {
        padding: 22px;
    }

    .heading h1 {
        font-size: 26px;
    }
}

</style>

</head>

<body>

<div class="page">

    <div class="heading">

        <h1>🎥 Upload Video</h1>

        <p>
            Share your learning videos with students learning your skills.
        </p>

    </div>

    <div class="upload-card">

        <?php if ($message !== ""): ?>

            <div class="message <?= htmlspecialchars($messageType) ?>">

                <?= htmlspecialchars($message) ?>

            </div>

        <?php endif; ?>

        <form
            method="POST"
            enctype="multipart/form-data"
        >

            <!-- SKILL -->

            <div class="form-group">

                <label for="skill">
                    Skill
                </label>

                <select
                    name="skill"
                    id="skill"
                    required
                >

                    <option value="">
                        Select the skill you are teaching
                    </option>

                    <?php foreach ($teachSkills as $teachSkill): ?>

                        <option
                            value="<?= htmlspecialchars($teachSkill) ?>"
                        >
                            <?= htmlspecialchars($teachSkill) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <!-- VIDEO TITLE -->

            <div class="form-group">

                <label for="title">
                    Video Title
                </label>

                <input
                    type="text"
                    name="title"
                    id="title"
                    placeholder="e.g. Java Basics Tutorial"
                    maxlength="255"
                    required
                >

            </div>

            <!-- VIDEO FILE -->

            <div class="form-group">

                <label for="video">
                    Select Video
                </label>

                <div class="file-box">

                    <div class="file-icon">
                        🎬
                    </div>

                    <p>
                        Upload your learning video
                    </p>

                    <input
                        type="file"
                        name="video"
                        id="video"
                        accept="video/mp4,video/webm,video/ogg,video/quicktime"
                        required
                    >

                    <small>
                        Maximum size: 100 MB
                    </small>

                </div>

            </div>

            <!-- SUBMIT BUTTON -->

            <button
                type="submit"
                class="submit-btn"
            >
                📤 Upload Video
            </button>

        </form>

        <!-- INFORMATION -->

        <div class="info">

            <strong>How it works:</strong>

            <p style="margin-top: 10px;">
                Select the skill you teach and upload your video.
            </p>

            <p style="margin-top: 10px;">
                Students who are actively connected with you
                and learning that skill will receive a notification.
            </p>

        </div>

    </div>

</div>

<script>

/* =========================
   VIDEO SIZE VALIDATION
========================= */

const videoInput = document.getElementById("video");

videoInput.addEventListener("change", function () {

    if (this.files.length > 0) {

        const file = this.files[0];

        const maxSize = 100 * 1024 * 1024;

        if (file.size > maxSize) {

            alert("Video size must be less than 100 MB.");

            this.value = "";
        }
    }
});

/* =========================
   UPLOAD BUTTON LOADING
========================= */

document.querySelector("form").addEventListener(
    "submit",
    function () {

        const button = document.querySelector(".submit-btn");

        button.disabled = true;

        button.innerHTML = "⏳ Uploading...";
    }
);

</script>

</body>
</html>
