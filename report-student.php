<?php
session_start();

require "db.php";

/* LOGIN CHECK */
if (!isset($_SESSION["user"]["id"])) {
    header("Location: login.php");
    exit();
}

$currentUserId = (int)$_SESSION["user"]["id"];

/* GET REPORTED STUDENT */
$reportedUserId = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

if ($reportedUserId <= 0 || $reportedUserId === $currentUserId) {
    header("Location: students.php");
    exit();
}

/* CHECK STUDENT EXISTS */
$stmt = $conn->prepare("SELECT id, name FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $reportedUserId);
$stmt->execute();
$reportedStudent = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$reportedStudent) {
    header("Location: students.php");
    exit();
}

/* SUBMIT REPORT */
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $reason = trim($_POST["reason"] ?? "");
    $description = trim($_POST["description"] ?? "");

    $allowedReasons = [
        "Fake Profile",
        "Harassment or Abusive Behaviour",
        "Spam",
        "Inappropriate Behaviour",
        "Other"
    ];

    if (!in_array($reason, $allowedReasons, true)) {
        $error = "Please select a valid reason.";
    } elseif (strlen($description) > 5000) {
        $error = "Description must be 5000 characters or fewer.";
    } else {

        /* CHECK FOR AN EXISTING PENDING REPORT */
        $check = $conn->prepare("
            SELECT id
            FROM reports
            WHERE reporter_id = ?
              AND reported_user_id = ?
              AND status = 'Pending'
            LIMIT 1
        ");

        $check->bind_param("ii", $currentUserId, $reportedUserId);
        $check->execute();
        $existingReport = $check->get_result()->fetch_assoc();
        $check->close();

        if ($existingReport) {
            $error = "You already have a pending report for this student.";
        } else {

            /* SAVE REPORT */
            $insert = $conn->prepare("
                INSERT INTO reports
                    (reporter_id, reported_user_id, reason, description, status)
                VALUES (?, ?, ?, ?, 'Pending')
            ");

            $insert->bind_param(
                "iiss",
                $currentUserId,
                $reportedUserId,
                $reason,
                $description
            );

            if ($insert->execute()) {
                $insert->close();

                echo "<script>
                    alert('Report submitted successfully.');
                    window.location.href = 'student-profile.php?id=" .
                    $reportedUserId . "';
                </script>";
                exit();
            } else {
                $error = "Report could not be submitted. Please try again.";
                $insert->close();
            }
        }
    }
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Student - SkillConnect</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f6faf7;
            color: #222;
        }

        .report-container {
            max-width: 650px;
            margin: 110px auto 50px;
            padding: 30px;
            background: white;
            border: 1px solid #edf1ee;
            border-radius: 18px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.06);
        }

        h1 {
            color: #285b41;
            margin-bottom: 10px;
            font-size: 26px;
        }

        .intro {
            color: #666;
            line-height: 1.6;
            margin-bottom: 25px;
        }

        label {
            display: block;
            font-weight: 600;
            margin: 18px 0 8px;
            color: #333;
        }

        select, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #d8e3dc;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
        }

        textarea {
            min-height: 130px;
            resize: vertical;
        }

        .submit-btn {
            border: none;
            background: #c0392b;
            color: white;
            padding: 12px 22px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 20px;
        }

        .submit-btn:hover {
            background: #962d22;
        }

        .cancel-btn {
            display: inline-block;
            margin-left: 10px;
            color: #3b7d5a;
            text-decoration: none;
            font-weight: 600;
        }

        .error {
            background: #fff0ef;
            color: #a93226;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        @media(max-width: 700px) {
            .report-container {
                margin: 90px 15px 30px;
                padding: 22px;
            }
        }
    </style>
</head>

<body>

<?php require "common-navbar.php"; ?>

<div class="report-container">

    <h1>🚩 Report Student</h1>

    <p class="intro">
        Reporting a student helps keep SkillConnect safe.
        Please choose the reason that best describes your concern.
    </p>

    <p>
        <strong>Student:</strong>
        <?= e($reportedStudent["name"]) ?>
    </p>

    <?php if ($error !== ""): ?>
        <div class="error" style="margin-top:15px;">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST">

        <label for="reason">Reason for reporting</label>

        <select name="reason" id="reason" required>
            <option value="">-- Select a reason --</option>
            <option value="Fake Profile">Fake Profile</option>
            <option value="Harassment or Abusive Behaviour">
                Harassment or Abusive Behaviour
            </option>
            <option value="Spam">Spam</option>
            <option value="Inappropriate Behaviour">
                Inappropriate Behaviour
            </option>
            <option value="Other">Other</option>
        </select>

        <label for="description">Additional details (optional)</label>

        <textarea
            name="description"
            id="description"
            maxlength="5000"
            placeholder="Explain your concern..."
        ></textarea>

        <button type="submit" class="submit-btn">
            Submit Report
        </button>

        <a
            href="student-profile.php?id=<?= (int)$reportedStudent["id"] ?>"
            class="cancel-btn"
        >
            Cancel
        </a>

    </form>

</div>

</body>
</html>