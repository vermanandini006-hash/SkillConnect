
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

$currentUser = $_SESSION["user"];
$currentUserId = (int)($currentUser["id"] ?? 0);

if ($currentUserId <= 0) {
    header("Location: login.php");
    exit;
}

/* ==========================================
   CHECK PREMIUM STATUS
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

if (!$user) {
    header("Location: login.php");
    exit;
}

$isPremium = (int)($user["is_premium"] ?? 0) === 1;

/* ==========================================
   CREATE DEMO ACTIVATION TOKEN
========================================== */

if (empty($_SESSION["demo_premium_token"])) {
    $_SESSION["demo_premium_token"] =
        bin2hex(random_bytes(32));
}

$token = $_SESSION["demo_premium_token"];

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Demo Premium - SkillConnect</title>

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

.payment-page {
    min-height: 85vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 20px;
}

.payment-container {
    width: 100%;
    max-width: 850px;
    background: white;
    border-radius: 20px;
    padding: 35px;
    box-shadow: 0 8px 30px rgba(0,0,0,0.08);
}

.payment-heading {
    text-align: center;
    margin-bottom: 30px;
}

.payment-heading h1 {
    color: #285b41;
    font-size: 30px;
    margin-bottom: 10px;
}

.payment-heading p {
    color: #777;
    font-size: 14px;
    line-height: 1.7;
}

.payment-content {
    display: grid;
    grid-template-columns: 260px 1fr;
    gap: 30px;
}

.plan-box {
    background: #f2f9f5;
    border: 1px solid #dcebe2;
    border-radius: 15px;
    padding: 25px;
    height: fit-content;
}

.plan-box h2 {
    color: #285b41;
    font-size: 21px;
    margin-bottom: 15px;
}

.plan-price {
    font-size: 34px;
    font-weight: bold;
    color: #3b7d5a;
    margin-bottom: 6px;
}

.plan-price span {
    font-size: 13px;
    color: #777;
    font-weight: normal;
}

.demo-label {
    display: inline-block;
    background: #e0f1e6;
    color: #285b41;
    font-size: 11px;
    font-weight: bold;
    padding: 7px 12px;
    border-radius: 20px;
    margin: 12px 0;
}

.plan-feature {
    font-size: 13px;
    color: #526158;
    margin: 15px 0;
    line-height: 1.6;
}

.plan-feature span {
    color: #3b7d5a;
    font-weight: bold;
    margin-right: 6px;
}

.payment-box h2 {
    color: #285b41;
    font-size: 22px;
    margin-bottom: 15px;
}

.payment-box p {
    font-size: 14px;
    color: #68796e;
    line-height: 1.8;
    margin-bottom: 18px;
}

.demo-info {
    background: #f0f8f3;
    border: 1px solid #d8e9dd;
    border-radius: 12px;
    padding: 18px;
    margin-bottom: 20px;
}

.demo-info h3 {
    font-size: 16px;
    color: #285b41;
    margin-bottom: 10px;
}

.demo-info p {
    margin: 0;
    font-size: 13px;
}

.warning {
    background: #fff8e8;
    color: #84631c;
    padding: 13px;
    border-radius: 9px;
    font-size: 12px;
    line-height: 1.7;
    margin-bottom: 20px;
}

.confirm-box {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin: 18px 0;
    font-size: 13px;
    line-height: 1.7;
    color: #526158;
}

.confirm-box input {
    margin-top: 4px;
    accent-color: #3b7d5a;
}

.pay-btn {
    width: 100%;
    border: none;
    background: #3b7d5a;
    color: white;
    padding: 15px;
    border-radius: 9px;
    cursor: pointer;
    font-size: 15px;
    font-weight: bold;
}

.pay-btn:hover {
    background: #285b41;
}

.active-box {
    background: #eaf6ef;
    color: #285b41;
    border-radius: 12px;
    padding: 20px;
    text-align: center;
}

.active-box h3 {
    margin-bottom: 10px;
}

.active-box p {
    color: #285b41;
}

.action-link {
    display: inline-block;
    text-decoration: none;
    background: #3b7d5a;
    color: white;
    padding: 13px 22px;
    border-radius: 9px;
    font-weight: bold;
    font-size: 13px;
    margin-top: 15px;
}

.demo-note {
    text-align: center;
    margin-top: 20px;
    font-size: 12px;
    color: #87938b;
    line-height: 1.7;
}

@media(max-width: 700px) {

    .payment-container {
        padding: 25px 18px;
    }

    .payment-content {
        grid-template-columns: 1fr;
    }

    .payment-heading h1 {
        font-size: 25px;
    }
}

</style>

</head>

<body>

<?php include "common-navbar.php"; ?>

<div class="payment-page">

    <div class="payment-container">

        <div class="payment-heading">

            <h1>👑 SkillConnect Demo Premium</h1>

            <p>
                Explore Premium features through
                a free demonstration.
            </p>

        </div>

        <div class="payment-content">

            <!-- PREMIUM PLAN -->

            <div class="plan-box">

                <h2>👑 Premium Plan</h2>

                <div class="plan-price">
                    ₹99 <span>/ month</span>
                </div>

                <div class="demo-label">
                    DEMO ONLY — FREE
                </div>

                <div class="plan-feature">
                    <span>✓</span> Video Calling
                </div>

                <div class="plan-feature">
                    <span>✓</span> Screen Sharing
                </div>

                <div class="plan-feature">
                    <span>✓</span> Camera & Mic
                </div>

                <div class="plan-feature">
                    <span>✓</span> Live Skill Demonstration
                </div>

                <div class="plan-feature">
                    <span>✓</span> Skill Exchange Collaboration
                </div>

            </div>

            <!-- DEMO ACTIVATION -->

            <div class="payment-box">

                <?php if ($isPremium): ?>

                    <div class="active-box">

                        <h3>✓ Demo Premium Active!</h3>

                        <p>
                            Your account already has
                            Demo Premium access.
                        </p>

                        <a
                            href="video_call.php"
                            class="action-link"
                        >
                            🎥 Open Video Calling
                        </a>

                    </div>

                <?php else: ?>

                    <h2>Activate Demo Premium</h2>

                    <p>
                        Experience SkillConnect Premium
                        features without making a real payment.
                    </p>

                    <div class="demo-info">

                        <h3>🎓 College Project Demo</h3>

                        <p>
                            This is a demonstration of the
                            Premium membership feature.
                            No payment is processed and
                            no banking information is required.
                        </p>

                    </div>

                    <div class="warning">

                        🔒 Do not enter any real UPI ID,
                        card number, CVV or bank details.
                        This demonstration does not collect
                        financial information.

                    </div>

                    <form
                        action="payment_success.php"
                        method="POST"
                    >

                        <input
                            type="hidden"
                            name="action"
                            value="activate_demo"
                        >

                        <input
                            type="hidden"
                            name="demo_token"
                            value="<?= htmlspecialchars(
                                $token,
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>"
                        >

                        <label class="confirm-box">

                            <input
                                type="checkbox"
                                name="confirm_demo"
                                value="1"
                                required
                            >

                            <span>
                                I understand that this is a
                                free demo activation and
                                no real payment will occur.
                            </span>

                        </label>

                        <button
                            type="submit"
                            class="pay-btn"
                        >
                            👑 Activate Free Demo Premium
                        </button>

                    </form>

                <?php endif; ?>

                <div class="demo-note">

                    SkillConnect Academic Project
                    <br>
                    No real money will be charged.

                </div>

            </div>

        </div>

    </div>

</div>

</body>
</html>
