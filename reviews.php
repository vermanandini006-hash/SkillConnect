<?php
session_start();
require "db.php";

if (!isset($_SESSION["user"]["id"])) {
    header("Location: login.php");
    exit();
}

$currentUserId = (int) $_SESSION["user"]["id"];

$message = "";
$messageType = "";


/* =========================
   HELPER
========================= */

function e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}


/* =========================
   SUBMIT REVIEW
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST"
    && ($_POST["action"] ?? "") === "submit_review") {

    $exchangeId = (int)($_POST["exchange_id"] ?? 0);
    $reviewedUserId = (int)($_POST["reviewed_user_id"] ?? 0);
    $rating = (int)($_POST["rating"] ?? 0);
    $feedback = trim($_POST["feedback"] ?? "");

    if (
        $exchangeId <= 0 ||
        $reviewedUserId <= 0 ||
        $rating < 1 ||
        $rating > 5 ||
        $feedback === ""
    ) {
        $message = "Please fill in all review fields.";
        $messageType = "error";
    } else {

        /*
         * Verify that the current user and
         * reviewed student belong to this exchange.
         */

        $stmt = $conn->prepare("
            SELECT id
            FROM exchanges
            WHERE id = ?
              AND (
                    (
                        student_one_id = ?
                        AND student_two_id = ?
                    )
                    OR
                    (
                        student_one_id = ?
                        AND student_two_id = ?
                    )
              )
            LIMIT 1
        ");

        $stmt->bind_param(
            "iiiii",
            $exchangeId,
            $currentUserId,
            $reviewedUserId,
            $reviewedUserId,
            $currentUserId
        );

        $stmt->execute();
        $exchange = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$exchange) {

            $message = "This student is not your exchange partner.";
            $messageType = "error";

        } else {

            /*
             * Check whether this exchange has
             * already been reviewed by this user.
             */

            $stmt = $conn->prepare("
                SELECT id
                FROM reviews
                WHERE exchange_id = ?
                  AND reviewer_id = ?
                LIMIT 1
            ");

            $stmt->bind_param(
                "ii",
                $exchangeId,
                $currentUserId
            );

            $stmt->execute();
            $alreadyReviewed = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($alreadyReviewed) {

                $message = "You have already reviewed this exchange.";
                $messageType = "error";

            } else {

                $stmt = $conn->prepare("
                    INSERT INTO reviews
                    (
                        exchange_id,
                        reviewer_id,
                        reviewed_user_id,
                        rating,
                        feedback
                    )
                    VALUES (?, ?, ?, ?, ?)
                ");

                $stmt->bind_param(
                    "iiiis",
                    $exchangeId,
                    $currentUserId,
                    $reviewedUserId,
                    $rating,
                    $feedback
                );

                if ($stmt->execute()) {
                    $message = "Your review has been submitted successfully!";
                    $messageType = "success";
                } else {
                    $message = "Unable to submit your review. Please try again.";
                    $messageType = "error";
                }

                $stmt->close();
            }
        }
    }
}


/* =========================
   GET EXCHANGE PARTNERS
========================= */

$partners = [];

$stmt = $conn->prepare("
    SELECT
        id AS exchange_id,
        student_one_id,
        student_one_name,
        student_two_id,
        student_two_name,
        skill
    FROM exchanges
    WHERE student_one_id = ?
       OR student_two_id = ?
    ORDER BY id DESC
");

$stmt->bind_param(
    "ii",
    $currentUserId,
    $currentUserId
);

$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {

    if ((int)$row["student_one_id"] === $currentUserId) {
        $partnerId = (int)$row["student_two_id"];
        $partnerName = $row["student_two_name"];
    } else {
        $partnerId = (int)$row["student_one_id"];
        $partnerName = $row["student_one_name"];
    }

    $row["partner_id"] = $partnerId;
    $row["partner_name"] = $partnerName;

    /*
     * Check whether the current user has
     * already submitted a review for this exchange.
     */

    $check = $conn->prepare("
        SELECT id
        FROM reviews
        WHERE exchange_id = ?
          AND reviewer_id = ?
        LIMIT 1
    ");

    $check->bind_param(
        "ii",
        $row["exchange_id"],
        $currentUserId
    );

    $check->execute();

    $row["already_reviewed"] =
        (bool)$check->get_result()->fetch_assoc();

    $check->close();

    $partners[] = $row;
}

$stmt->close();


/* =========================
   GET FEEDBACK RECEIVED
========================= */

$receivedReviews = [];

$stmt = $conn->prepare("
    SELECT
        r.rating,
        r.feedback,
        r.created_at,
        u.name AS reviewer_name
    FROM reviews r
    INNER JOIN users u
        ON u.id = r.reviewer_id
    WHERE r.reviewed_user_id = ?
    ORDER BY r.created_at DESC
");

$stmt->bind_param("i", $currentUserId);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $receivedReviews[] = $row;
}

$stmt->close();


/* =========================
   CALCULATE AVERAGE RATING
========================= */

$totalReviews = count($receivedReviews);
$averageRating = 0;

if ($totalReviews > 0) {

    $ratingTotal = 0;

    foreach ($receivedReviews as $review) {
        $ratingTotal += (int)$review["rating"];
    }

    $averageRating = round(
        $ratingTotal / $totalReviews,
        1
    );
}


/* =========================
   GET REVIEWS GIVEN
========================= */

$givenReviews = [];

$stmt = $conn->prepare("
    SELECT
        r.rating,
        r.feedback,
        r.created_at,
        u.name AS student_name
    FROM reviews r
    INNER JOIN users u
        ON u.id = r.reviewed_user_id
    WHERE r.reviewer_id = ?
    ORDER BY r.created_at DESC
");

$stmt->bind_param("i", $currentUserId);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $givenReviews[] = $row;
}

$stmt->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Reviews & Ratings - SkillConnect</title>

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: Arial, sans-serif;
    background: #f6faf7;
    color: #26372d;
}

.container {
    max-width: 1100px;
    width: 92%;
    margin: 35px auto;
}

.page-heading {
    margin-bottom: 25px;
}

.page-heading h1 {
    color: #285b41;
    font-size: 30px;
    margin-bottom: 8px;
}

.page-heading p {
    color: #69786e;
    line-height: 1.6;
}

.stats {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 18px;
    margin-bottom: 28px;
}

.stat-card {
    background: white;
    padding: 23px;
    border-radius: 14px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    border: 1px solid #e5eee7;
}

.stat-card h3 {
    color: #66766b;
    font-size: 14px;
    margin-bottom: 12px;
}

.stat-card strong {
    font-size: 28px;
    color: #285b41;
}

.section {
    background: white;
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 25px;
    border: 1px solid #e5eee7;
    box-shadow: 0 4px 15px rgba(0,0,0,0.04);
}

.section h2 {
    color: #285b41;
    margin-bottom: 20px;
    font-size: 22px;
}

.message {
    padding: 14px;
    border-radius: 9px;
    margin-bottom: 22px;
    line-height: 1.5;
}

.success {
    color: #23643b;
    background: #e4f6e9;
    border: 1px solid #c4e8ce;
}

.error {
    color: #a82d2d;
    background: #fdeaea;
    border: 1px solid #f5caca;
}

.partner-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
}

.partner-card {
    border: 1px solid #e1ebe4;
    border-radius: 12px;
    padding: 20px;
    background: #fbfdfb;
}

.partner-card h3 {
    color: #285b41;
    margin-bottom: 9px;
}

.skill {
    display: inline-block;
    background: #e7f3eb;
    color: #285b41;
    padding: 7px 11px;
    border-radius: 20px;
    margin: 4px 0 14px;
    font-size: 13px;
}

label {
    display: block;
    font-weight: bold;
    margin: 12px 0 7px;
    color: #394b3f;
}

select,
textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #ccdcd0;
    border-radius: 8px;
    font-size: 15px;
    font-family: Arial, sans-serif;
}

textarea {
    min-height: 100px;
    resize: vertical;
}

select:focus,
textarea:focus {
    outline: none;
    border-color: #3b7d5a;
}

button {
    background: #3b7d5a;
    color: white;
    border: none;
    padding: 12px 18px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    font-weight: bold;
    margin-top: 14px;
}

button:hover {
    background: #285b41;
}

.already {
    color: #287044;
    background: #e5f5e9;
    border-radius: 8px;
    padding: 12px;
    margin-top: 15px;
    font-size: 14px;
}

.review-card {
    padding: 17px 0;
    border-bottom: 1px solid #e6eee8;
}

.review-card:last-child {
    border-bottom: none;
}

.review-card h3 {
    color: #285b41;
    margin-bottom: 8px;
}

.stars {
    color: #e5a400;
    letter-spacing: 2px;
    font-size: 20px;
    margin: 5px 0 9px;
}

.feedback {
    color: #526257;
    line-height: 1.7;
    overflow-wrap: anywhere;
}

.date {
    color: #87938a;
    font-size: 12px;
    margin-top: 9px;
}

.empty {
    background: #f7faf7;
    padding: 20px;
    border-radius: 10px;
    color: #6c7b70;
    text-align: center;
    line-height: 1.6;
}

@media (max-width: 700px) {
    .stats,
    .partner-grid {
        grid-template-columns: 1fr;
    }

    .section {
        padding: 18px;
    }

    .page-heading h1 {
        font-size: 25px;
    }
}

</style>

</head>

<body>

<?php include "common-navbar.php"; ?>

<div class="container">

    <div class="page-heading">
        <h1>⭐ Reviews & Ratings</h1>

        <p>
            Share your experience with your skill exchange partners
            and read feedback from other students.
        </p>
    </div>


    <?php if ($message !== ""): ?>

        <div class="message <?= e($messageType) ?>">
            <?= e($message) ?>
        </div>

    <?php endif; ?>


    <!-- RATING SUMMARY -->

    <div class="stats">

        <div class="stat-card">
            <h3>⭐ Your Average Rating</h3>

            <strong>
                <?= number_format($averageRating, 1) ?> / 5
            </strong>

            <p class="stars">
                <?php
                for ($i = 1; $i <= 5; $i++) {
                    echo $i <= round($averageRating) ? "★" : "☆";
                }
                ?>
            </p>
        </div>


        <div class="stat-card">
            <h3>💬 Feedback Received</h3>

            <strong>
                <?= (int)$totalReviews ?>
            </strong>

            <p style="color:#718075; margin-top:8px;">
                Reviews from your exchange partners
            </p>
        </div>

    </div>


    <!-- GIVE REVIEW -->

    <div class="section">

        <h2>✍️ Give a Review</h2>

        <?php if (empty($partners)): ?>

            <div class="empty">
                <h3>No Skill Exchange Found</h3>

                <p style="margin-top:8px;">
                    Accept a skill exchange request first.
                    Your exchange partners will appear here.
                </p>
            </div>

        <?php else: ?>

            <div class="partner-grid">

                <?php foreach ($partners as $partner): ?>

                    <div class="partner-card">

                        <h3>
                            👤 <?= e($partner["partner_name"]) ?>
                        </h3>

                        <span class="skill">
                            📚 <?= e($partner["skill"] ?: "Skill Exchange") ?>
                        </span>


                        <?php if ($partner["already_reviewed"]): ?>

                            <div class="already">
                                ✓ You have already reviewed this exchange.
                            </div>

                        <?php else: ?>

                            <form method="POST">

                                <input
                                    type="hidden"
                                    name="action"
                                    value="submit_review"
                                >

                                <input
                                    type="hidden"
                                    name="exchange_id"
                                    value="<?= (int)$partner["exchange_id"] ?>"
                                >

                                <input
                                    type="hidden"
                                    name="reviewed_user_id"
                                    value="<?= (int)$partner["partner_id"] ?>"
                                >


                                <label>Rating</label>

                                <select name="rating" required>

                                    <option value="">
                                        Select rating
                                    </option>

                                    <option value="5">⭐⭐⭐⭐⭐ - Excellent</option>

                                    <option value="4">⭐⭐⭐⭐ - Very Good</option>

                                    <option value="3">⭐⭐⭐ - Good</option>

                                    <option value="2">⭐⭐ - Fair</option>

                                    <option value="1">⭐ - Needs Improvement</option>

                                </select>


                                <label>Student Feedback</label>

                                <textarea
                                    name="feedback"
                                    placeholder="Share your experience with this student..."
                                    maxlength="2000"
                                    required
                                ></textarea>


                                <button type="submit">
                                    Submit Review ⭐
                                </button>

                            </form>

                        <?php endif; ?>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </div>


    <!-- FEEDBACK RECEIVED -->

    <div class="section">

        <h2>💬 Student Feedback Received</h2>

        <?php if (empty($receivedReviews)): ?>

            <div class="empty">
                No feedback received yet.
                Your exchange partners can leave feedback here.
            </div>

        <?php else: ?>

            <?php foreach ($receivedReviews as $review): ?>

                <div class="review-card">

                    <h3>
                        👤 <?= e($review["reviewer_name"]) ?>
                    </h3>

                    <div class="stars">
                        <?php
                        for ($i = 1; $i <= 5; $i++) {
                            echo $i <= (int)$review["rating"] ? "★" : "☆";
                        }
                        ?>
                    </div>

                    <p class="feedback">
                        <?= nl2br(e($review["feedback"])) ?>
                    </p>

                    <p class="date">
                        <?= e($review["created_at"]) ?>
                    </p>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>


    <!-- REVIEWS GIVEN -->

    <div class="section">

        <h2>📝 Reviews You Have Given</h2>

        <?php if (empty($givenReviews)): ?>

            <div class="empty">
                You haven't submitted any reviews yet.
            </div>

        <?php else: ?>

            <?php foreach ($givenReviews as $review): ?>

                <div class="review-card">

                    <h3>
                        👤 <?= e($review["student_name"]) ?>
                    </h3>

                    <div class="stars">
                        <?php
                        for ($i = 1; $i <= 5; $i++) {
                            echo $i <= (int)$review["rating"] ? "★" : "☆";
                        }
                        ?>
                    </div>

                    <p class="feedback">
                        <?= nl2br(e($review["feedback"])) ?>
                    </p>

                    <p class="date">
                        <?= e($review["created_at"]) ?>
                    </p>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>

</div>

</body>
</html>