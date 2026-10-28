
<?php

session_start();

require "db.php";

/* =====================================================
   LOGIN CHECK
===================================================== */

if (!isset($_SESSION["user"])) {
    header("Location: login.php");
    exit();
}

$currentUser = $_SESSION["user"];

$currentUserId = (int)($currentUser["id"] ?? 0);
$currentUserName = $currentUser["name"] ?? "";
$currentUserEmail = $currentUser["email"] ?? "";

if ($currentUserId <= 0) {
    die("Invalid user session.");
}


/* =====================================================
   HELPER
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
   MESSAGES
===================================================== */

$successMessage = "";
$errorMessage = "";


/* =====================================================
   SEND CONNECT REQUEST
===================================================== */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["send_request"])
) {

    $receiverId = (int)($_POST["receiver_id"] ?? 0);

    $skill = trim($_POST["skill"] ?? "");

    $requestMessage = trim(
        $_POST["request_message"] ?? ""
    );


    /* BASIC VALIDATION */

    if (
        $receiverId <= 0
        || $skill === ""
    ) {

        $errorMessage =
            "Please select a valid student and skill.";

    }

    elseif ($receiverId === $currentUserId) {

        $errorMessage =
            "You cannot send a request to yourself.";

    }

    else {

        /* GET RECEIVER */

        $stmt = $conn->prepare("
            SELECT
                id,
                name,
                email
            FROM users
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->bind_param(
            "i",
            $receiverId
        );

        $stmt->execute();

        $receiver =
            $stmt
                ->get_result()
                ->fetch_assoc();

        $stmt->close();


        if (!$receiver) {

            $errorMessage =
                "Student not found.";

        }

        else {

            /* CHECK EXISTING PENDING REQUEST */

            $stmt = $conn->prepare("
                SELECT id
                FROM exchange_requests
                WHERE sender_id = ?
                AND receiver_id = ?
                AND skill = ?
                AND status = 'pending'
                LIMIT 1
            ");

            $stmt->bind_param(
                "iis",
                $currentUserId,
                $receiverId,
                $skill
            );

            $stmt->execute();

            $existingRequest =
                $stmt
                    ->get_result()
                    ->fetch_assoc();

            $stmt->close();


            if ($existingRequest) {

                $errorMessage =
                    "You already sent a request for this skill.";

            }

            else {

                /* INSERT REQUEST */

                $status = "pending";

                $stmt = $conn->prepare("
                    INSERT INTO exchange_requests
                    (
                        sender_id,
                        sender_email,
                        sender_name,
                        receiver_id,
                        receiver_email,
                        receiver_name,
                        skill,
                        message,
                        status
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?
                    )
                ");

                $stmt->bind_param(
                    "ississsss",
                    $currentUserId,
                    $currentUserEmail,
                    $currentUserName,
                    $receiver["id"],
                    $receiver["email"],
                    $receiver["name"],
                    $skill,
                    $requestMessage,
                    $status
                );

if ($stmt->execute()) {

    $requestId = (int)$conn->insert_id;

    $successMessage =
        "Connect request sent successfully!";

    // Send notification to the receiver
    try {

        $notificationTitle = "New Skill Exchange Request";

        $notificationMessage =
            $currentUserName .
            " sent you a skill exchange request for " .
            $skill . ".";

        $notificationType = "exchange_request";

        $notificationStmt = $conn->prepare("
            INSERT INTO notifications
            (
                user_email,
                user_id,
                title,
                message,
                type,
                request_id,
                related_id,
                is_read
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, 0)
        ");

        $notificationStmt->bind_param(
            "sisssii",
            $receiver["email"],
            $receiverId,
            $notificationTitle,
            $notificationMessage,
            $notificationType,
            $requestId,
            $requestId
        );

        $notificationStmt->execute();
        $notificationStmt->close();

    } catch (Throwable $error) {
        error_log(
            "Notification error: " . $error->getMessage()
        );
    }

}

                else {

                    $errorMessage =
                        "Request could not be sent.";

                }

                $stmt->close();
            }
        }
    }
}


/* =====================================================
   GET CURRENT USER SKILLS
===================================================== */

$currentUserTeachSkills = [];
$currentUserLearnSkills = [];

$stmt = $conn->prepare("
    SELECT
        skills_to_teach,
        skills_to_learn
    FROM users
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param(
    "i",
    $currentUserId
);

$stmt->execute();

$currentUserSkills =
    $stmt
        ->get_result()
        ->fetch_assoc();

$stmt->close();


if (
    !empty(
        $currentUserSkills["skills_to_teach"] ?? ""
    )
) {

    $currentUserTeachSkills =
        array_filter(
            array_map(
                "trim",
                explode(
                    ",",
                    strtolower(
                        $currentUserSkills["skills_to_teach"]
                    )
                )
            )
        );
}


if (
    !empty(
        $currentUserSkills["skills_to_learn"] ?? ""
    )
) {

    $currentUserLearnSkills =
        array_filter(
            array_map(
                "trim",
                explode(
                    ",",
                    strtolower(
                        $currentUserSkills["skills_to_learn"]
                    )
                )
            )
        );
}


/* =====================================================
   GET ALL OTHER STUDENTS
===================================================== */

$students = [];

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
        status
    FROM users
    WHERE id != ?
    ORDER BY name ASC
");

$stmt->bind_param(
    "i",
    $currentUserId
);

$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {

    $students[] = $row;
}

$stmt->close();


/* =====================================================
   SEARCH
===================================================== */

$search = trim(
    $_GET["search"] ?? ""
);

$filteredStudents = [];

foreach ($students as $student) {

    if ($search === "") {

        $filteredStudents[] = $student;

        continue;
    }


    $searchText =
        strtolower($search);


    $studentText =
        strtolower(
            ($student["name"] ?? "")
            . " "
            . ($student["email"] ?? "")
            . " "
            . ($student["course"] ?? "")
            . " "
            . ($student["college"] ?? "")
            . " "
            . ($student["skills_to_teach"] ?? "")
            . " "
            . ($student["skills_to_learn"] ?? "")
        );


    if (
        strpos(
            $studentText,
            $searchText
        ) !== false
    ) {

        $filteredStudents[] =
            $student;
    }
}


/* =====================================================
   SUGGESTED STUDENTS
===================================================== */

$suggestedStudents = [];

foreach ($students as $student) {

    $score = 0;

    $studentTeachSkills = [];
    $studentLearnSkills = [];


    /* STUDENT TEACH SKILLS */

    if (
        !empty(
            $student["skills_to_teach"]
        )
    ) {

        $studentTeachSkills =
            array_filter(
                array_map(
                    "trim",
                    explode(
                        ",",
                        strtolower(
                            $student["skills_to_teach"]
                        )
                    )
                )
            );
    }


    /* STUDENT LEARN SKILLS */

    if (
        !empty(
            $student["skills_to_learn"]
        )
    ) {

        $studentLearnSkills =
            array_filter(
                array_map(
                    "trim",
                    explode(
                        ",",
                        strtolower(
                            $student["skills_to_learn"]
                        )
                    )
                )
            );
    }


    /* CURRENT USER WANTS TO LEARN
       OTHER STUDENT TEACHES IT */

    foreach (
        $currentUserLearnSkills as $skill
    ) {

        if (
            in_array(
                $skill,
                $studentTeachSkills
            )
        ) {

            $score += 3;
        }
    }


    /* CURRENT USER CAN TEACH
       OTHER STUDENT WANTS TO LEARN IT */

    foreach (
        $currentUserTeachSkills as $skill
    ) {

        if (
            in_array(
                $skill,
                $studentLearnSkills
            )
        ) {

            $score += 2;
        }
    }


    if ($score > 0) {

        $student["suggestion_score"] =
            $score;

        $suggestedStudents[] =
            $student;
    }
}


/* =====================================================
   SORT SUGGESTED STUDENTS
===================================================== */

usort(
    $suggestedStudents,
    function ($a, $b) {

        return
            $b["suggestion_score"]
            <=>
            $a["suggestion_score"];
    }
);


/* =====================================================
   TOP 3
===================================================== */

$suggestedStudents =
    array_slice(
        $suggestedStudents,
        0,
        3
    );

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Find Students - SkillConnect
    </title>


    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }


        body {

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: #f6faf7;

            color: #222;
        }


        /* =================================================
           PAGE
        ================================================= */

        .page-container {

            max-width: 1200px;

            margin: auto;

            padding:
                40px 25px 60px;
        }


        .page-header {

            margin-bottom: 25px;
        }


        .page-header h1 {

            font-size: 32px;

            color: #285b41;

            margin-bottom: 8px;
        }


        .page-header p {

            color: #707a74;

            font-size: 15px;
        }


        /* =================================================
           ALERTS
        ================================================= */

        .alert {

            padding:
                14px 18px;

            border-radius: 10px;

            margin-bottom: 22px;

            font-size: 14px;

            font-weight: 600;
        }


        .alert.success {

            background: #e8f7ee;

            color: #267448;

            border:
                1px solid #bce5cb;
        }


        .alert.error {

            background: #fff0ef;

            color: #b7352a;

            border:
                1px solid #f1c1bd;
        }


        /* =================================================
           SEARCH
        ================================================= */

        .search-box {

            background: white;

            padding: 18px;

            border-radius: 13px;

            box-shadow:
                0 3px 14px
                rgba(0,0,0,0.05);

            margin-bottom: 30px;

            display: flex;

            gap: 10px;
        }


        .search-box input {

            flex: 1;

            padding:
                12px 15px;

            border:
                1px solid #dce5df;

            border-radius: 8px;

            outline: none;

            font-size: 14px;
        }


        .search-box input:focus {

            border-color:
                #3b7d5a;
        }


        .search-btn {

            border: none;

            background:
                #3b7d5a;

            color: white;

            padding:
                0 22px;

            border-radius: 8px;

            cursor: pointer;

            font-weight: 600;
        }


        .search-btn:hover {

            background:
                #285b41;
        }


        /* =================================================
           SECTION HEADER
        ================================================= */

        .section-header {

            margin-bottom: 15px;
        }


        .section-header h2 {

            color: #285b41;

            font-size: 22px;

            margin-bottom: 5px;
        }


        .section-header p {

            color: #777;

            font-size: 13px;
        }


        /* =================================================
           RECOMMENDED BADGE
        ================================================= */

        .recommended-badge {

            background:
                #f0f8f3;

            color:
                #2f704f;

            padding:
                8px 10px;

            border-radius: 8px;

            font-size: 11px;

            margin-bottom: 14px;
        }


        /* =================================================
           GRID
        ================================================= */

        .students-grid {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 22px;
        }


        /* =================================================
           CARD
        ================================================= */

        .student-card {

            background: white;

            border-radius: 16px;

            padding: 22px;

            box-shadow:
                0 4px 16px
                rgba(0,0,0,0.06);

            border:
                1px solid #edf1ee;

            transition:
                transform 0.2s ease,
                box-shadow 0.2s ease;
        }


        .student-card:hover {

            transform:
                translateY(-3px);

            box-shadow:
                0 8px 22px
                rgba(0,0,0,0.09);
        }


        /* =================================================
           STUDENT TOP
        ================================================= */

        .student-top {

            display: flex;

            align-items: center;

            gap: 14px;

            margin-bottom: 17px;
        }


        .profile-photo {

            width: 55px;

            height: 55px;

            border-radius: 50%;

            object-fit: cover;

            background:
                #dff1e6;
        }


        .avatar {

            width: 55px;

            height: 55px;

            min-width: 55px;

            border-radius: 50%;

            background:
                #dff1e6;

            color:
                #2f704f;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 20px;

            font-weight: 700;
        }


        .student-name {

            color:
                #285b41;

            font-size: 18px;

            margin-bottom: 4px;
        }


        .student-email {

            color:
                #888;

            font-size: 12px;
        }


        /* =================================================
           INFO
        ================================================= */

        .info {

            margin-bottom: 14px;

            color:
                #666;

            font-size: 13px;

            line-height: 1.6;
        }


        .info strong {

            color:
                #444;
        }


        /* =================================================
           STATUS
        ================================================= */

        .student-status {

            display: inline-flex;

            align-items: center;

            gap: 6px;

            font-size: 12px;

            margin-bottom: 15px;
        }


        .status-dot {

            width: 8px;

            height: 8px;

            border-radius: 50%;

            display: inline-block;
        }


        .online {

            background:
                #31a35b;
        }


        .offline {

            background:
                #999;
        }


        .status-online {

            color:
                #287347;
        }


        .status-offline {

            color:
                #888;
        }


        /* =================================================
           SKILLS
        ================================================= */

        .skills-title {

            font-size: 13px;

            font-weight: 700;

            color:
                #444;

            margin-bottom: 8px;
        }


        .skills {

            display: flex;

            flex-wrap: wrap;

            gap: 6px;

            margin-bottom: 17px;
        }


        .skill-tag {

            background:
                #edf8f2;

            color:
                #2f704f;

            border-radius: 20px;

            padding:
                5px 9px;

            font-size: 11px;
        }


        /* =================================================
           BUTTONS
        ================================================= */

        .card-buttons {

            display: flex;

            gap: 8px;
        }


        .profile-btn,
        .connect-btn {

            flex: 1;

            padding:
                10px 8px;

            border-radius: 8px;

            text-align: center;

            font-size: 12px;

            font-weight: 600;

            cursor: pointer;

            text-decoration: none;
        }


        .profile-btn {

            background:
                #edf8f2;

            color:
                #2f704f;

            border: none;
        }


        .profile-btn:hover {

            background:
                #dff1e6;
        }


        .connect-btn {

            background:
                #3b7d5a;

            color: white;

            border: none;
        }


        .connect-btn:hover {

            background:
                #285b41;
        }


        /* =================================================
           MODAL
        ================================================= */

        .modal {

            display: none;

            position: fixed;

            inset: 0;

            background:
                rgba(0,0,0,0.45);

            z-index: 2000;

            align-items: center;

            justify-content: center;

            padding: 20px;
        }


        .modal.show {

            display: flex;
        }


        .modal-box {

            background: white;

            width: 100%;

            max-width: 480px;

            border-radius: 16px;

            padding: 28px;

            position: relative;

            box-shadow:
                0 15px 40px
                rgba(0,0,0,0.2);
        }


        .modal-box h2 {

            color:
                #285b41;

            margin-bottom: 7px;
        }


        .modal-box p {

            color:
                #777;

            font-size: 13px;

            margin-bottom: 22px;
        }


        .close-btn {

            position: absolute;

            top: 15px;

            right: 18px;

            border: none;

            background: transparent;

            font-size: 24px;

            cursor: pointer;

            color: #777;
        }


        .form-group {

            margin-bottom: 17px;
        }


        .form-group label {

            display: block;

            margin-bottom: 7px;

            color: #444;

            font-size: 13px;

            font-weight: 600;
        }


        .form-group input,
        .form-group select,
        .form-group textarea {

            width: 100%;

            padding:
                11px 12px;

            border:
                1px solid #dce5df;

            border-radius: 8px;

            outline: none;

            font-family: inherit;

            font-size: 13px;

            background: white;
        }


        .form-group select {

            cursor: pointer;
        }


        .form-group textarea {

            min-height: 100px;

            resize: vertical;
        }


        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {

            border-color:
                #3b7d5a;
        }


        .send-btn {

            width: 100%;

            border: none;

            background:
                #3b7d5a;

            color: white;

            padding:
                12px;

            border-radius: 8px;

            cursor: pointer;

            font-weight: 600;
        }


        .send-btn:hover {

            background:
                #285b41;
        }


        /* =================================================
           EMPTY
        ================================================= */

        .empty {

            background: white;

            padding: 50px 20px;

            text-align: center;

            border-radius: 15px;

            color: #888;

            box-shadow:
                0 4px 15px
                rgba(0,0,0,0.04);
        }


        .empty-icon {

            font-size: 45px;

            margin-bottom: 10px;
        }


        .empty h3 {

            color: #555;

            margin-bottom: 6px;
        }


        /* =================================================
           MOBILE
        ================================================= */

        @media(max-width: 950px) {

            .students-grid {

                grid-template-columns:
                    repeat(2, 1fr);
            }
        }


        @media(max-width: 650px) {

            .page-container {

                padding:
                    30px 15px 50px;
            }


            .students-grid {

                grid-template-columns: 1fr;
            }


            .search-box {

                flex-direction: column;
            }


            .search-btn {

                padding:
                    12px;
            }
        }

    </style>

</head>


<body>


<?php

require "common-navbar.php";

?>


<div class="page-container">


    <!-- =================================================
         HEADER
    ================================================= -->

    <div class="page-header">

        <h1>
            Find Students
        </h1>

        <p>
            Find students and connect with them
            for skill exchange.
        </p>

    </div>


    <!-- =================================================
         ALERTS
    ================================================= -->

    <?php if ($successMessage !== ""): ?>

        <div class="alert success">

            <?= e($successMessage) ?>

        </div>

    <?php endif; ?>


    <?php if ($errorMessage !== ""): ?>

        <div class="alert error">

            <?= e($errorMessage) ?>

        </div>

    <?php endif; ?>


    <!-- =================================================
         SEARCH
    ================================================= -->

    <form
        method="GET"
        class="search-box"
    >

        <input
            type="text"
            name="search"
            placeholder="Search by name, course, college or skill..."
            value="<?= e($search) ?>"
        >

        <button
            type="submit"
            class="search-btn"
        >
            🔎 Search
        </button>

    </form>


    <!-- =================================================
         SUGGESTED STUDENTS
    ================================================= -->

    <?php if (!empty($suggestedStudents)): ?>


        <div style="margin-bottom: 35px;">


            <div class="section-header">

                <h2>
                    ⭐ Suggested Students
                </h2>

                <p>
                    Students recommended based on your skills.
                </p>

            </div>


            <div class="students-grid">


                <?php foreach (
                    $suggestedStudents as $student
                ): ?>


                    <?php

                    $suggestedTeachSkills = [];
                    $suggestedLearnSkills = [];


                    if (
                        !empty(
                            $student["skills_to_teach"]
                        )
                    ) {

                        $suggestedTeachSkills =
                            array_filter(
                                array_map(
                                    "trim",
                                    explode(
                                        ",",
                                        $student["skills_to_teach"]
                                    )
                                )
                            );
                    }


                    if (
                        !empty(
                            $student["skills_to_learn"]
                        )
                    ) {

                        $suggestedLearnSkills =
                            array_filter(
                                array_map(
                                    "trim",
                                    explode(
                                        ",",
                                        $student["skills_to_learn"]
                                    )
                                )
                            );
                    }


                    $suggestedAllSkills =
                        array_unique(
                            array_merge(
                                $suggestedTeachSkills,
                                $suggestedLearnSkills
                            )
                        );


                    $profileImage =
                        trim(
                            $student["profile_image"] ?? ""
                        );

                    ?>


                    <div class="student-card">


                        <div class="student-top">


                            <?php if (
                                $profileImage !== ""
                                &&
                                file_exists(
                                    __DIR__ . "/" . $profileImage
                                )
                            ): ?>

                                <img
                                    src="<?= e($profileImage) ?>"
                                    class="profile-photo"
                                    alt="Profile"
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


                            <div>

                                <div class="student-name">

                                    <?= e(
                                        $student["name"]
                                    ) ?>

                                </div>


                                <div class="student-email">

                                    <?= e(
                                        $student["email"]
                                    ) ?>

                                </div>

                            </div>


                        </div>


                        <div
                            class="student-status
                            <?= $student["status"] === "online"
                                ? "status-online"
                                : "status-offline"
                            ?>"
                        >

                            <span
                                class="status-dot
                                <?= $student["status"] === "online"
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


                        <?php if (
                            !empty(
                                $student["course"]
                            )
                        ): ?>

                            <div class="info">

                                🎓

                                <strong>
                                    Course:
                                </strong>

                                <?= e(
                                    $student["course"]
                                ) ?>

                            </div>

                        <?php endif; ?>


                        <?php if (
                            !empty(
                                $student["college"]
                            )
                        ): ?>

                            <div class="info">

                                🏫

                                <strong>
                                    College:
                                </strong>

                                <?= e(
                                    $student["college"]
                                ) ?>

                            </div>

                        <?php endif; ?>


                        <div class="recommended-badge">

                            ⭐ Recommended for your skills

                        </div>


                        <?php if (
                            !empty($suggestedAllSkills)
                        ): ?>

                            <div class="skills-title">
                                Skills
                            </div>


                            <div class="skills">

                                <?php foreach (
                                    array_slice(
                                        $suggestedAllSkills,
                                        0,
                                        6
                                    ) as $oneSkill
                                ): ?>

                                    <span class="skill-tag">

                                        <?= e(
                                            $oneSkill
                                        ) ?>

                                    </span>

                                <?php endforeach; ?>

                            </div>

                        <?php endif; ?>


                        <div class="card-buttons">


                            <!-- PROFILE -->

                            <a
                                href="student-profile.php?id=<?= (int)$student["id"] ?>"
                                class="profile-btn"
                            >
                                👤 View Profile
                            </a>


                            <!-- CONNECT -->

                            <button
                                type="button"
                                class="connect-btn"
                                onclick='openConnectModal(
                                    <?= (int)$student["id"] ?>,
                                    <?= json_encode(
                                        $student["name"],
                                        JSON_HEX_TAG |
                                        JSON_HEX_APOS |
                                        JSON_HEX_QUOT |
                                        JSON_HEX_AMP
                                    ) ?>,
                                    <?= json_encode(
                                        $suggestedTeachSkills,
                                        JSON_HEX_TAG |
                                        JSON_HEX_APOS |
                                        JSON_HEX_QUOT |
                                        JSON_HEX_AMP
                                    ) ?>
                                )'
                            >
                                🤝 Connect
                            </button>


                        </div>


                    </div>


                <?php endforeach; ?>


            </div>


        </div>


    <?php endif; ?>


    <!-- =================================================
         ALL STUDENTS
    ================================================= -->

    <div class="section-header">

        <h2>
            👥 All Students
        </h2>

        <p>
            Browse all available students.
        </p>

    </div>


    <?php if (empty($filteredStudents)): ?>


        <div class="empty">

            <div class="empty-icon">
                🔍
            </div>

            <h3>
                No students found
            </h3>

            <p>
                Try searching with another name
                or skill.
            </p>

        </div>


    <?php else: ?>


        <div class="students-grid">


            <?php foreach (
                $filteredStudents as $student
            ): ?>


                <?php

                /* TEACH SKILLS */

                $teachSkills = [];

                if (
                    !empty(
                        $student["skills_to_teach"]
                    )
                ) {

                    $teachSkills =
                        array_filter(
                            array_map(
                                "trim",
                                explode(
                                    ",",
                                    $student["skills_to_teach"]
                                )
                            )
                        );
                }


                /* LEARN SKILLS */

                $learnSkills = [];

                if (
                    !empty(
                        $student["skills_to_learn"]
                    )
                ) {

                    $learnSkills =
                        array_filter(
                            array_map(
                                "trim",
                                explode(
                                    ",",
                                    $student["skills_to_learn"]
                                )
                            )
                        );
                }


                /* COMBINE SKILLS */

                $allSkills =
                    array_unique(
                        array_merge(
                            $teachSkills,
                            $learnSkills
                        )
                    );


                /* PROFILE IMAGE */

                $profileImage =
                    trim(
                        $student["profile_image"] ?? ""
                    );

                ?>


                <div class="student-card">


                    <div class="student-top">


                        <?php if (
                            $profileImage !== ""
                            &&
                            file_exists(
                                __DIR__ . "/" . $profileImage
                            )
                        ): ?>

                            <img
                                src="<?= e($profileImage) ?>"
                                class="profile-photo"
                                alt="Profile"
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


                        <div>

                            <div class="student-name">

                                <?= e(
                                    $student["name"]
                                ) ?>

                            </div>


                            <div class="student-email">

                                <?= e(
                                    $student["email"]
                                ) ?>

                            </div>

                        </div>


                    </div>


                    <!-- STATUS -->

                    <div
                        class="student-status
                        <?= $student["status"] === "online"
                            ? "status-online"
                            : "status-offline"
                        ?>"
                    >

                        <span
                            class="status-dot
                            <?= $student["status"] === "online"
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


                    <!-- COURSE -->

                    <?php if (
                        !empty(
                            $student["course"]
                        )
                    ): ?>

                        <div class="info">

                            🎓

                            <strong>
                                Course:
                            </strong>

                            <?= e(
                                $student["course"]
                            ) ?>

                        </div>

                    <?php endif; ?>


                    <!-- COLLEGE -->

                    <?php if (
                        !empty(
                            $student["college"]
                        )
                    ): ?>

                        <div class="info">

                            🏫

                            <strong>
                                College:
                            </strong>

                            <?= e(
                                $student["college"]
                            ) ?>

                        </div>

                    <?php endif; ?>


                    <!-- SKILLS -->

                    <?php if (
                        !empty($allSkills)
                    ): ?>

                        <div class="skills-title">
                            Skills
                        </div>


                        <div class="skills">

                            <?php foreach (
                                array_slice(
                                    $allSkills,
                                    0,
                                    6
                                ) as $oneSkill
                            ): ?>

                                <span class="skill-tag">

                                    <?= e(
                                        $oneSkill
                                    ) ?>

                                </span>

                            <?php endforeach; ?>

                        </div>

                    <?php endif; ?>


                    <!-- BUTTONS -->

                    <div class="card-buttons">


                        <!-- PROFILE -->

                        <a
                            href="student-profile.php?id=<?= (int)$student["id"] ?>"
                            class="profile-btn"
                        >
                            👤 View Profile
                        </a>


                        <!-- CONNECT -->

                        <button
                            type="button"
                            class="connect-btn"
                            onclick='openConnectModal(
                                <?= (int)$student["id"] ?>,
                                <?= json_encode(
                                    $student["name"],
                                    JSON_HEX_TAG |
                                    JSON_HEX_APOS |
                                    JSON_HEX_QUOT |
                                    JSON_HEX_AMP
                                ) ?>,
                                <?= json_encode(
                                    $teachSkills,
                                    JSON_HEX_TAG |
                                    JSON_HEX_APOS |
                                    JSON_HEX_QUOT |
                                    JSON_HEX_AMP
                                ) ?>
                            )'
                        >
                            🤝 Connect
                        </button>


                    </div>


                </div>


            <?php endforeach; ?>


        </div>


    <?php endif; ?>


</div>


<!-- =====================================================
     CONNECT MODAL
===================================================== -->

<div
    class="modal"
    id="connectModal"
>


    <div class="modal-box">


        <button
            type="button"
            class="close-btn"
            onclick="closeConnectModal()"
        >
            ×
        </button>


        <h2>
            Send Skill Exchange Request
        </h2>


        <p id="connectStudentName">
            Connect with student
        </p>


        <form method="POST">


            <input
                type="hidden"
                name="receiver_id"
                id="receiverId"
            >


            <!-- SKILL -->

            <div class="form-group">

                <label>
                    Skill
                </label>


                <select
                    name="skill"
                    id="skillInput"
                    required
                >

                    <option value="">
                        Select a skill
                    </option>

                </select>

            </div>


            <!-- MESSAGE -->

            <div class="form-group">

                <label>
                    Message
                </label>


                <textarea
                    name="request_message"
                    placeholder="Write a message..."
                ></textarea>

            </div>


            <button
                type="submit"
                name="send_request"
                class="send-btn"
            >
                🤝 Send Request
            </button>


        </form>


    </div>

</div>


<script>

/* =====================================================
   OPEN CONNECT MODAL
===================================================== */

function openConnectModal(
    studentId,
    studentName,
    skills
) {

    /* SET RECEIVER ID */

    document
        .getElementById("receiverId")
        .value = studentId;


    /* SET STUDENT NAME */

    document
        .getElementById("connectStudentName")
        .textContent =
            "Send a skill exchange request to "
            + studentName;


    /* GET DROPDOWN */

    const skillSelect =
        document.getElementById("skillInput");


    /* REMOVE OLD SKILLS */

    skillSelect.innerHTML =
        '<option value="">Select a skill</option>';


    /* ADD TEACHING SKILLS */

    if (
        Array.isArray(skills)
        && skills.length > 0
    ) {

        skills.forEach(
            function(skill) {

                const option =
                    document.createElement(
                        "option"
                    );

                option.value =
                    skill;

                option.textContent =
                    skill;

                skillSelect.appendChild(
                    option
                );
            }
        );

    }

    else {

        const option =
            document.createElement(
                "option"
            );

        option.value = "";

        option.textContent =
            "This student has no teaching skills added";

        option.disabled = true;

        skillSelect.appendChild(
            option
        );
    }


    /* SHOW MODAL */

    document
        .getElementById("connectModal")
        .classList.add("show");
}


/* =====================================================
   CLOSE MODAL
===================================================== */

function closeConnectModal() {

    document
        .getElementById("connectModal")
        .classList.remove("show");
}


/* =====================================================
   CLOSE WHEN CLICK OUTSIDE
===================================================== */

document
    .getElementById("connectModal")
    .addEventListener(
        "click",
        function(event) {

            if (
                event.target === this
            ) {

                closeConnectModal();
            }
        }
    );


/* =====================================================
   ESCAPE KEY
===================================================== */

document.addEventListener(
    "keydown",
    function(event) {

        if (
            event.key === "Escape"
        ) {

            closeConnectModal();
        }
    }
);

</script>


</body>

</html>

