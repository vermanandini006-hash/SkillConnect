
<?php
session_start();

if (!isset($_SESSION["user"]["id"])) {
    header("Location: login.php");
    exit();
}

require "db.php";

$currentUser = $_SESSION["user"];
$currentUserId = (int)($currentUser["id"] ?? 0);
$currentUserName = $currentUser["name"] ?? "Student";

if ($currentUserId <= 0) {
    die("Invalid user session.");
}

$message = "";
$messageType = "";

/* =========================
   UPLOAD NOTE
========================= */

if (isset($_POST["upload_note"])) {

    $title = trim($_POST["title"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $skill = trim($_POST["skill"] ?? "");

    if ($title === "" || $skill === "") {

        $message = "Please enter note title and skill.";
        $messageType = "error";

    } elseif (
        !isset($_FILES["note_file"]) ||
        $_FILES["note_file"]["error"] !== UPLOAD_ERR_OK
    ) {

        $message = "Please select a PDF file.";
        $messageType = "error";

    } else {

        $file = $_FILES["note_file"];

        $originalName = $file["name"];
        $fileSize = $file["size"];
        $tmpName = $file["tmp_name"];

        $extension = strtolower(
            pathinfo($originalName, PATHINFO_EXTENSION)
        );

        /* ONLY PDF */

        $fileType = mime_content_type($tmpName);

        if (
            $extension !== "pdf" ||
            $fileType !== "application/pdf"
        ) {

            $message = "Only PDF files are allowed.";
            $messageType = "error";

        } elseif ($fileSize > 10 * 1024 * 1024) {

            $message = "File size must be less than 10 MB.";
            $messageType = "error";

        } else {

            /* =========================
               UPLOAD FOLDER
            ========================= */

            $uploadFolder = __DIR__ . "/uploads/notes/";

            if (!is_dir($uploadFolder)) {
                mkdir($uploadFolder, 0777, true);
            }

            /* UNIQUE FILE NAME */

            $newFileName =
                time() . "_" .
                uniqid() . "_" .
                preg_replace(
                    "/[^A-Za-z0-9._-]/",
                    "_",
                    $originalName
                );

            $destination = $uploadFolder . $newFileName;

            $filePath = "uploads/notes/" . $newFileName;

            /* MOVE FILE */

            if (move_uploaded_file($tmpName, $destination)) {

                /* =========================
                   SAVE IN DATABASE
                ========================= */

                $stmt = $conn->prepare("
                    INSERT INTO notes
                    (
                        user_id,
                        skill,
                        title,
                        description,
                        file_name,
                        file_path
                    )
                    VALUES (?, ?, ?, ?, ?, ?)
                ");

                $stmt->bind_param(
                    "isssss",
                    $currentUserId,
                    $skill,
                    $title,
                    $description,
                    $originalName,
                    $filePath
                );

                if ($stmt->execute()) {

                    $noteId = (int)$conn->insert_id;

                    $message = "Notes uploaded successfully!";
                    $messageType = "success";

                    /* =========================
                       NOTES NOTIFICATION SYSTEM

                       Notify only active exchange
                       partners learning this skill
                       from the uploader.
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
                                'note_upload',
                                ?,
                                0

                            FROM users

                            WHERE id = ?
                        ");

                        $notificationTitle = "New Notes Uploaded";

                        $notificationMessage =
                            $currentUserName .
                            " uploaded new " .
                            $skill .
                            " notes: " .
                            $title;

                        /* SEND TO RELEVANT PARTNERS */

                        while ($partner = $partners->fetch_assoc()) {

                            $partnerId = (int)$partner["partner_id"];

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
                                $noteId,
                                $partnerId
                            );

                            $notificationStmt->execute();
                        }

                        $notificationStmt->close();
                        $partnerStmt->close();

                    } catch (Throwable $error) {

                        /*
                           If notification fails,
                           uploaded notes remain saved.
                        */

                        error_log(
                            "Notes notification error: " .
                            $error->getMessage()
                        );
                    }

                } else {

                    if (file_exists($destination)) {
                        unlink($destination);
                    }

                    $message = "Database error. Notes could not be saved.";
                    $messageType = "error";
                }

                $stmt->close();

            } else {

                $message = "File upload failed.";
                $messageType = "error";
            }
        }
    }
}

/* =========================
   COMMON NAVBAR
========================= */

include "common-navbar.php";
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Upload Notes - SkillConnect</title>

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: Arial, sans-serif;
    background: linear-gradient(135deg, #f5faf7, #eaf5ee);
    min-height: 100vh;
    color: #285b41;
}

/* =========================
   NAVBAR
========================= */

.navbar {
    background: #3b7d5a;
    padding: 16px 40px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: white;
}

.logo {
    font-size: 24px;
    font-weight: bold;
}

.navbar a {
    color: white;
    text-decoration: none;
    margin-left: 20px;
    font-size: 14px;
}

.navbar a:hover {
    text-decoration: underline;
}

/* =========================
   CONTAINER
========================= */

.container {
    width: 90%;
    max-width: 700px;
    margin: 50px auto;
}

/* =========================
   CARD
========================= */

.card {
    background: white;
    padding: 35px;
    border-radius: 18px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.08);
}

.card h1 {
    text-align: center;
    margin-bottom: 10px;
    color: #285b41;
}

.subtitle {
    text-align: center;
    color: #777;
    margin-bottom: 30px;
}

/* =========================
   FORM
========================= */

label {
    display: block;
    margin-top: 18px;
    margin-bottom: 7px;
    font-weight: bold;
}

input,
textarea,
select {
    width: 100%;
    padding: 12px;
    border: 1px solid #cddbd2;
    border-radius: 9px;
    font-size: 15px;
    outline: none;
}

input:focus,
textarea:focus,
select:focus {
    border-color: #3b7d5a;
}

textarea {
    height: 110px;
    resize: vertical;
}

input[type="file"] {
    background: #f6faf7;
    padding: 10px;
}

/* =========================
   BUTTON
========================= */

button {
    width: 100%;
    margin-top: 25px;
    padding: 14px;
    border: none;
    border-radius: 9px;
    background: #3b7d5a;
    color: white;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
}

button:hover {
    background: #285b41;
}

/* =========================
   MESSAGE
========================= */

.message {
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 20px;
    text-align: center;
    font-weight: bold;
}

.success {
    background: #dff3e6;
    color: #28613f;
}

.error {
    background: #fde4e4;
    color: #a33a3a;
}

/* =========================
   INFO
========================= */

.info {
    margin-top: 25px;
    background: #f0f8f3;
    padding: 15px;
    border-radius: 10px;
    font-size: 14px;
    line-height: 1.6;
}

.back {
    display: block;
    text-align: center;
    margin-top: 20px;
    color: #3b7d5a;
    text-decoration: none;
    font-weight: bold;
}

.back:hover {
    text-decoration: underline;
}

/* =========================
   MOBILE
========================= */

@media(max-width:700px) {

    .navbar {
        padding: 15px 20px;
        flex-direction: column;
        gap: 10px;
    }

    .navbar a {
        margin-left: 8px;
        font-size: 12px;
    }

    .container {
        width: 94%;
        margin: 30px auto;
    }

    .card {
        padding: 22px;
    }
}

</style>

</head>

<body>

<!-- =========================
     MAIN
========================= -->

<div class="container">

<div class="card">

<h1>📄 Upload Notes / PDF</h1>

<p class="subtitle">
    Share learning notes with students learning the same skill.
</p>

<?php if ($message !== ""): ?>

<div class="message <?php echo htmlspecialchars($messageType); ?>">

    <?php echo htmlspecialchars($message); ?>

</div>

<?php endif; ?>

<form method="POST" enctype="multipart/form-data">

    <!-- TITLE -->

    <label>
        Note Title
    </label>

    <input
        type="text"
        name="title"
        placeholder="Example: Java OOP Notes"
        required
    >

    <!-- SKILL -->

    <label>
        Skill
    </label>

    <input
        type="text"
        name="skill"
        placeholder="Example: Java"
        required
    >

    <!-- DESCRIPTION -->

    <label>
        Description
    </label>

    <textarea
        name="description"
        placeholder="Write a short description about these notes..."
    ></textarea>

    <!-- FILE -->

    <label>
        Select PDF
    </label>

    <input
        type="file"
        name="note_file"
        accept=".pdf,application/pdf"
        required
    >

    <button
        type="submit"
        name="upload_note"
    >
        📤 Upload Notes
    </button>

</form>

<div class="info">

    <strong>How it works?</strong>

    <p style="margin-top:10px;">
        Enter the skill name correctly, for example
        <strong>Java</strong>.
    </p>

    <p style="margin-top:10px;">
        Students actively connected with you
        and learning that skill will receive
        a notification when you upload notes.
    </p>

    <p style="margin-top:10px;">
        Maximum file size: <strong>10 MB</strong>
    </p>

</div>

<a
    href="resources.php"
    class="back"
>
    ← View Learning Resources
</a>

</div>

</div>

</body>

</html>
