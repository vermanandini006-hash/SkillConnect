<?php
session_start();
require "db.php";

if (!isset($_SESSION["user"]["id"])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: students.php");
    exit();
}

$reporterId = (int) $_SESSION["user"]["id"];
$reportedUserId = (int) ($_POST["reported_user_id"] ?? 0);
$reason = trim($_POST["reason"] ?? "");
$description = trim($_POST["description"] ?? "");

$allowedReasons = [
    "Fake Profile",
    "Harassment or Abusive Behaviour",
    "Spam",
    "Inappropriate Behaviour",
    "Other"
];

if ($reportedUserId <= 0 || $reportedUserId === $reporterId) {
    die("Invalid student selected for report.");
}

if (!in_array($reason, $allowedReasons, true)) {
    die("Please select a valid report reason.");
}

if (strlen($description) > 2000) {
    die("Description must be under 2000 characters.");
}

/* Check that the reported student exists */
$stmt = $conn->prepare(
    "SELECT id FROM users WHERE id = ? LIMIT 1"
);
$stmt->bind_param("i", $reportedUserId);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    die("Student not found.");
}

/* Prevent duplicate reports with the same reason
   from the same reporter for the same student */
$stmt = $conn->prepare(
    "SELECT id FROM reports
     WHERE reporter_id = ?
       AND reported_user_id = ?
       AND reason = ?
       AND status = 'Pending'
     LIMIT 1"
);

$stmt->bind_param(
    "iis",
    $reporterId,
    $reportedUserId,
    $reason
);

$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existing) {
    $_SESSION["report_message"] =
        "You have already submitted this pending report.";
    header("Location: students.php");
    exit();
}

/* Save report */
$stmt = $conn->prepare(
    "INSERT INTO reports
        (reporter_id, reported_user_id, reason, description, status)
     VALUES (?, ?, ?, ?, 'Pending')"
);

$stmt->bind_param(
    "iiss",
    $reporterId,
    $reportedUserId,
    $reason,
    $description
);

if ($stmt->execute()) {
    $_SESSION["report_message"] =
        "Report submitted successfully. The admin will review it.";
} else {
    $_SESSION["report_message"] =
        "Unable to submit the report. Please try again.";
}

$stmt->close();

header("Location: students.php");
exit();
?>