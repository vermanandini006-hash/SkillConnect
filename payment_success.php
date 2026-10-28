
<?php
session_start();
require_once "db.php";

/* ==========================================
   LOGIN CHECK
========================================== */

if (
    !isset($_SESSION["user"]) ||
    !is_array($_SESSION["user"])
) {
    header("Location: login.php");
    exit;
}

$currentUserId = (int)(
    $_SESSION["user"]["id"] ?? 0
);

if ($currentUserId <= 0) {
    header("Location: login.php");
    exit;
}

/* ==========================================
   SAFE REDIRECT HELPER
========================================== */

function demoRedirect($message = "") {

    if ($message !== "") {
        $_SESSION["demo_premium_message"] = $message;
    }

    header("Location: payment.php");
    exit;
}

/* ==========================================
   ALLOW ONLY POST ACTIVATION
========================================== */

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    if (
        !empty($_SESSION["demo_premium_success"]) &&
        $_SESSION["demo_premium_success"] === true
    ) {
        // Previously completed demo activation.
    } else {
        demoRedirect();
    }

} else {

    /* ======================================
       VALIDATE FORM ACTION
    ====================================== */

    $action = $_POST["action"] ?? "";

    if ($action !== "activate_demo") {
        demoRedirect("Invalid demo activation request.");
    }

    /* ======================================
       VALIDATE CSRF TOKEN
    ====================================== */

    $submittedToken = (string)(
        $_POST["demo_token"] ?? ""
    );

    $sessionToken = (string)(
        $_SESSION["demo_premium_token"] ?? ""
    );

    if (
        $sessionToken === "" ||
        $submittedToken === "" ||
        !hash_equals(
            $sessionToken,
            $submittedToken
        )
    ) {
        demoRedirect("Invalid or expired demo request.");
    }

    /* ======================================
       CHECK USER CONFIRMATION
    ====================================== */

    if (
        ($_POST["confirm_demo"] ?? "") !== "1"
    ) {
        demoRedirect("Please confirm demo activation.");
    }

    /* ======================================
       VERIFY USER EXISTS
    ====================================== */

    $stmt = $conn->prepare("
        SELECT id, is_premium
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->bind_param("i", $currentUserId);
    $stmt->execute();

    $user = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    if (!$user) {
        demoRedirect("User account not found.");
    }

    /* ======================================
       ACTIVATE DEMO PREMIUM
    ====================================== */

    if ((int)($user["is_premium"] ?? 0) !== 1) {

        $stmt = $conn->prepare("
            UPDATE users
            SET is_premium = 1
            WHERE id = ?
        ");

        $stmt->bind_param("i", $currentUserId);

        $success = $stmt->execute();

        $stmt->close();

        if (!$success) {
            demoRedirect("Unable to activate Demo Premium.");
        }
    }

    /* ======================================
       REFRESH SESSION STATE
    ====================================== */

    $_SESSION["user"]["is_premium"] = 1;

    $_SESSION["demo_premium_success"] = true;

    /* Invalidate token after successful use */
    unset($_SESSION["demo_premium_token"]);

    /* Prevent form resubmission on refresh */
    header("Location: payment_success.php");
    exit;
}

/* ==========================================
   CONFIRM PREMIUM STATUS
========================================== */

$stmt = $conn->prepare("
    SELECT is_premium
    FROM users
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $currentUserId);
$stmt->execute();

$user = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (
    !$user ||
    (int)($user["is_premium"] ?? 0) !== 1
) {
    unset($_SESSION["demo_premium_success"]);
    demoRedirect();
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Demo Premium Activated - SkillConnect</title>

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: Arial, sans-serif;
    background: #f5faf7;
    color: #263b30;
}

.success-page {
    min-height: 85vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 20px;
}

.success-card {
    width: 100%;
    max-width: 600px;
    background: white;
    border-radius: 20px;
    padding: 45px 35px;
    text-align: center;
    box-shadow: 0 8px 30px rgba(0,0,0,0.08);
}

.success-icon {
    width: 90px;
    height: 90px;
    border-radius: 50%;
    background: #e5f6eb;
    color: #3b7d5a;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    font-size: 48px;
    font-weight: bold;
}

.success-card h1 {
    color: #285b41;
    font-size: 29px;
    margin-bottom: 12px;
}

.success-card p {
    color: #777;
    font-size: 14px;
    line-height: 1.7;
}

.payment-details {
    background: #f5faf7;
    border: 1px solid #dcebe2;
    border-radius: 12px;
    padding: 18px;
    margin: 25px 0;
    text-align: left;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    gap: 15px;
    padding: 10px 0;
    font-size: 13px;
}

.detail-row span:first-child {
    color: #777;
}

.detail-row span:last-child {
    color: #285b41;
    font-weight: bold;
    text-align: right;
}

.premium-message {
    background: #eaf6ef;
    color: #285b41;
    padding: 15px;
    border-radius: 9px;
    font-size: 13px;
    line-height: 1.8;
    margin-bottom: 22px;
}

.video-btn,
.home-btn {
    display: inline-block;
    text-decoration: none;
    padding: 14px 24px;
    border-radius: 9px;
    font-size: 14px;
    font-weight: bold;
    margin: 5px;
}

.video-btn {
    background: #3b7d5a;
    color: white;
}

.video-btn:hover {
    background: #285b41;
}

.home-btn {
    background: #edf3ef;
    color: #285b41;
}

.home-btn:hover {
    background: #dcebe2;
}

.demo-note {
    margin-top: 20px;
    color: #87938b;
    font-size: 12px;
    line-height: 1.7;
}

@media(max-width:600px) {

    .success-card {
        padding: 30px 20px;
    }

    .success-card h1 {
        font-size: 24px;
    }

    .detail-row {
        font-size: 12px;
    }
}

</style>

</head>

<body>

<?php include "common-navbar.php"; ?>

<div class="success-page">

    <div class="success-card">

        <div class="success-icon">
            ✓
        </div>

        <h1>
            Demo Premium Activated!
        </h1>

        <p>
            Congratulations! Your SkillConnect
            Demo Premium access is now active.
        </p>

        <div class="payment-details">

            <div class="detail-row">

                <span>Plan</span>

                <span>Demo Premium</span>

            </div>

            <div class="detail-row">

                <span>Displayed Plan Price</span>

                <span>₹99 / month</span>

            </div>

            <div class="detail-row">

                <span>Actual Amount Charged</span>

                <span>₹0</span>

            </div>

            <div class="detail-row">

                <span>Payment Method</span>

                <span>No Payment Required</span>

            </div>

            <div class="detail-row">

                <span>Status</span>

                <span>✓ Demo Access Active</span>

            </div>

        </div>

        <div class="premium-message">

            👑 Your Demo Premium access includes
            <strong>Video Calling</strong>
            and
            <strong>Screen Sharing</strong>,
            subject to browser support and
            your active skill connections.

        </div>

        <a
            href="video_call.php"
            class="video-btn"
        >
            🎥 Start Video Call
        </a>

        <a
            href="index.php"
            class="home-btn"
        >
            🏠 Back to Home
        </a>

        <div class="demo-note">

            🎓 SkillConnect Academic Project
            <br>
            This was a free demonstration.
            No financial information was collected
            and no real payment was processed.

        </div>

    </div>

</div>

</body>
</html>
