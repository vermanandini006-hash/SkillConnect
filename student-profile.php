```php
<?php

session_start();

require "db.php";

/* =====================================================
   LOGIN CHECK
===================================================== */

if (!isset($_SESSION["user"]["id"])) {
    header("Location: login.php");
    exit();
}

$currentUserId = (int)$_SESSION["user"]["id"];


/* =====================================================
   GET STUDENT ID FROM URL
===================================================== */

$studentId = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

if ($studentId <= 0) {
    die("Invalid student profile.");
}


/* =====================================================
   GET STUDENT DATA
===================================================== */

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

$stmt->bind_param("i", $studentId);
$stmt->execute();

$result = $stmt->get_result();
$student = $result->fetch_assoc();

$stmt->close();


/* =====================================================
   CHECK STUDENT
===================================================== */

if (!$student) {
    die("Student not found.");
}


/* =====================================================
   HELPER FUNCTION
===================================================== */

function e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}


/* =====================================================
   TEACHING SKILLS
===================================================== */

$teachSkills = [];

if (!empty($student["skills_to_teach"])) {

    $teachSkills = array_filter(
        array_map(
            "trim",
            explode(",", $student["skills_to_teach"])
        )
    );
}


/* =====================================================
   LEARNING SKILLS
===================================================== */

$learnSkills = [];

if (!empty($student["skills_to_learn"])) {

    $learnSkills = array_filter(
        array_map(
            "trim",
            explode(",", $student["skills_to_learn"])
        )
    );
}


/* =====================================================
   PROFILE IMAGE
===================================================== */

$profileImage = trim(
    $student["profile_image"] ?? ""
);

$hasProfileImage = false;

if ($profileImage !== "") {

    $imagePath = __DIR__ . "/" . $profileImage;

    if (file_exists($imagePath)) {
        $hasProfileImage = true;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>
    <?= e($student["name"]) ?> - SkillConnect
</title>

<style>

/* =====================================================
   RESET
===================================================== */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}


/* =====================================================
   BODY
===================================================== */

body {
    font-family: Arial, Helvetica, sans-serif;
    background: #f6faf7;
    color: #222;
}


/* =====================================================
   MAIN CONTAINER
===================================================== */

.page-container {

    max-width: 1050px;

    margin: 90px auto 50px;

    padding: 20px 25px 50px;
}


/* =====================================================
   BACK BUTTON
===================================================== */

.back-btn {

    display: inline-block;

    margin-bottom: 20px;

    text-decoration: none;

    color: #3b7d5a;

    font-size: 14px;

    font-weight: 600;
}

.back-btn:hover {
    color: #285b41;
}


/* =====================================================
   PROFILE HEADER
===================================================== */

.profile-header {

    background: white;

    border-radius: 20px;

    padding: 30px;

    display: flex;

    align-items: center;

    gap: 25px;

    box-shadow:
        0 5px 20px rgba(0,0,0,0.06);

    border: 1px solid #edf1ee;

    margin-bottom: 25px;
}


/* =====================================================
   PROFILE PHOTO
===================================================== */

.profile-photo {

    width: 115px;

    height: 115px;

    min-width: 115px;

    border-radius: 50%;

    object-fit: cover;

    border: 4px solid #edf8f2;

    background: #dff1e6;
}


/* =====================================================
   DEFAULT AVATAR
===================================================== */

.avatar {

    width: 115px;

    height: 115px;

    min-width: 115px;

    border-radius: 50%;

    background: #dff1e6;

    color: #2f704f;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 42px;

    font-weight: 700;
}


/* =====================================================
   PROFILE INFO
===================================================== */

.profile-info {
    flex: 1;
}

.profile-info h1 {

    color: #285b41;

    font-size: 30px;

    margin-bottom: 8px;
}

.profile-email {

    color: #777;

    font-size: 14px;

    margin-bottom: 12px;
}


/* =====================================================
   STATUS
===================================================== */

.status {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    font-size: 13px;

    font-weight: 600;
}

.status-dot {

    width: 9px;

    height: 9px;

    border-radius: 50%;
}

.online {
    background: #31a35b;
}

.offline {
    background: #999;
}

.status-online {
    color: #287347;
}

.status-offline {
    color: #888;
}


/* =====================================================
   PROFILE GRID
===================================================== */

.profile-grid {

    display: grid;

    grid-template-columns: 1fr 1fr;

    gap: 25px;
}


/* =====================================================
   CARD
===================================================== */

.card {

    background: white;

    border-radius: 18px;

    padding: 25px;

    box-shadow:
        0 4px 16px rgba(0,0,0,0.05);

    border: 1px solid #edf1ee;
}

.card h2 {

    color: #285b41;

    font-size: 20px;

    margin-bottom: 18px;
}


/* =====================================================
   INFORMATION
===================================================== */

.info-row {

    padding: 13px 0;

    border-bottom: 1px solid #edf1ee;

    font-size: 14px;

    color: #666;

    line-height: 1.6;
}

.info-row:last-child {
    border-bottom: none;
}

.info-row strong {

    color: #444;

    display: inline-block;

    min-width: 90px;
}


/* =====================================================
   BIO
===================================================== */

.bio {

    margin-top: 10px;

    color: #666;

    font-size: 14px;

    line-height: 1.7;

    background: #f7faf8;

    padding: 15px;

    border-radius: 10px;
}


/* =====================================================
   SKILLS
===================================================== */

.skill-section {
    margin-bottom: 25px;
}

.skill-section:last-child {
    margin-bottom: 0;
}

.skill-heading {

    font-size: 14px;

    font-weight: 700;

    color: #444;

    margin-bottom: 10px;
}

.skills {

    display: flex;

    flex-wrap: wrap;

    gap: 8px;
}

.skill-tag {

    background: #edf8f2;

    color: #2f704f;

    padding: 8px 13px;

    border-radius: 20px;

    font-size: 12px;

    font-weight: 600;
}

.empty-skill {

    color: #999;

    font-size: 13px;
}


/* =====================================================
   CONNECT AREA
===================================================== */

.connect-area {

    margin-top: 25px;

    text-align: center;
}

.connect-btn {

    display: inline-block;

    text-decoration: none;

    background: #3b7d5a;

    color: white;

    padding: 12px 25px;

    border-radius: 9px;

    font-size: 14px;

    font-weight: 600;

    transition: 0.2s;
}

.connect-btn:hover {
    background: #285b41;
}


/* =====================================================
   REPORT STUDENT BUTTON
===================================================== */

.report-btn {

    display: inline-block;

    text-decoration: none;

    background: #fff;

    color: #c0392b;

    border: 1px solid #e5b7b3;

    padding: 12px 25px;

    border-radius: 9px;

    font-size: 14px;

    font-weight: 600;

    transition: 0.2s;

    margin-left: 10px;
}

.report-btn:hover {

    background: #fff0ef;

    border-color: #c0392b;
}


/* =====================================================
   MOBILE
===================================================== */

@media(max-width: 700px) {

    .page-container {

        margin-top: 80px;

        padding: 15px 15px 40px;
    }

    .profile-header {

        flex-direction: column;

        text-align: center;

        padding: 25px 20px;
    }

    .profile-info h1 {
        font-size: 25px;
    }

    .profile-grid {
        grid-template-columns: 1fr;
    }

    .connect-area {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 12px;
    }

    .report-btn {
        margin-left: 0;
    }

}

</style>

</head>


<body>


<?php

require "common-navbar.php";

?>


<div class="page-container">


    <!-- BACK -->

    <a href="students.php"
       class="back-btn">

        ← Back to Find Students

    </a>


    <!-- =================================================
         PROFILE HEADER
    ================================================= -->

    <div class="profile-header">


        <?php if ($hasProfileImage): ?>

            <img
                src="<?= e($profileImage) ?>"
                class="profile-photo"
                alt="Profile Image"
            >

        <?php else: ?>

            <div class="avatar">

                <?= e(
                    strtoupper(
                        substr(
                            $student["name"],
                            0,
                            1
                        )
                    )
                ) ?>

            </div>

        <?php endif; ?>


        <div class="profile-info">


            <h1>

                <?= e($student["name"]) ?>

            </h1>


            <div class="profile-email">

                <?= e($student["email"]) ?>

            </div>


            <div class="status
                <?= ($student["status"] ?? "") === "online"
                    ? "status-online"
                    : "status-offline"
                ?>"
            >

                <span class="status-dot
                    <?= ($student["status"] ?? "") === "online"
                        ? "online"
                        : "offline"
                    ?>"
                ></span>


                <?= e(
                    ucfirst(
                        $student["status"] ?? "offline"
                    )
                ) ?>

            </div>


        </div>


    </div>


    <!-- =================================================
         PROFILE INFORMATION
    ================================================= -->

    <div class="profile-grid">


        <!-- =================================================
             ABOUT STUDENT
        ================================================= -->

        <div class="card">


            <h2>
                👤 About Student
            </h2>


            <?php if (!empty($student["course"])): ?>

                <div class="info-row">

                    <strong>
                        🎓 Course:
                    </strong>

                    <?= e($student["course"]) ?>

                </div>

            <?php endif; ?>


            <?php if (!empty($student["college"])): ?>

                <div class="info-row">

                    <strong>
                        🏫 College:
                    </strong>

                    <?= e($student["college"]) ?>

                </div>

            <?php endif; ?>


            <div class="info-row">

                <strong>
                    📧 Email:
                </strong>

                <?= e($student["email"]) ?>

            </div>


            <?php if (!empty($student["bio"])): ?>

                <div class="info-row">

                    <strong>
                        📝 Bio:
                    </strong>

                    <div class="bio">

                        <?= nl2br(
                            e($student["bio"])
                        ) ?>

                    </div>

                </div>

            <?php endif; ?>


        </div>


        <!-- =================================================
             SKILLS
        ================================================= -->

        <div class="card">


            <h2>
                🧩 Skills
            </h2>


            <!-- TEACHING SKILLS -->

            <div class="skill-section">


                <div class="skill-heading">

                    💚 Skills I Can Teach

                </div>


                <?php if (!empty($teachSkills)): ?>


                    <div class="skills">

                        <?php foreach (
                            $teachSkills as $skill
                        ): ?>

                            <span class="skill-tag">

                                <?= e($skill) ?>

                            </span>

                        <?php endforeach; ?>

                    </div>


                <?php else: ?>


                    <div class="empty-skill">

                        No teaching skills added.

                    </div>


                <?php endif; ?>


            </div>


            <!-- LEARNING SKILLS -->

            <div class="skill-section">


                <div class="skill-heading">

                    📚 Skills I Want to Learn

                </div>


                <?php if (!empty($learnSkills)): ?>


                    <div class="skills">

                        <?php foreach (
                            $learnSkills as $skill
                        ): ?>

                            <span class="skill-tag">

                                <?= e($skill) ?>

                            </span>

                        <?php endforeach; ?>

                    </div>


                <?php else: ?>


                    <div class="empty-skill">

                        No learning skills added.

                    </div>


                <?php endif; ?>


            </div>


        </div>


    </div>


    <!-- =====================================================
         CONNECT AND REPORT STUDENT
    ===================================================== -->

    <?php if ($studentId !== $currentUserId): ?>

        <div class="connect-area">

            <a
                href="students.php"
                class="connect-btn"
            >
                🤝 Find & Connect
            </a>

            <a
                href="report-student.php?id=<?= (int)$student["id"] ?>"
                class="report-btn"
            >
                🚩 Report Student
            </a>

        </div>

    <?php endif; ?>


</div>


</body>

</html>
```