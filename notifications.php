
<?php
session_start();
require "db.php";

/* =========================
   LOGIN CHECK
========================= */
if (!isset($_SESSION["user"])) {
    header("Location: login.php");
    exit();
}

$currentUser = $_SESSION["user"];

$currentUserEmail = trim($currentUser["email"] ?? "");
$currentUserId = (int)($currentUser["id"] ?? 0);

if ($currentUserEmail === "" && $currentUserId <= 0) {
    die("User details not found. Please log in again.");
}

/* =========================
   HELPER
========================= */
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function getNotificationIcon($type, $title) {
    $type = strtolower((string)$type);
    $title = strtolower((string)$title);

    if (
        strpos($type, "premium") !== false ||
        strpos($title, "premium") !== false
    ) {
        return "👑";
    }

    if (
        strpos($type, "video") !== false ||
        strpos($title, "video") !== false
    ) {
        return "🎥";
    }

    if (
        strpos($type, "note") !== false ||
        strpos($type, "resource") !== false ||
        strpos($title, "note") !== false ||
        strpos($title, "resource") !== false
    ) {
        return "📚";
    }

    if (
        strpos($type, "reminder") !== false ||
        strpos($title, "reminder") !== false ||
        strpos($title, "deadline") !== false
    ) {
        return "⏰";
    }

    if (
        strpos($title, "accepted") !== false ||
        strpos($type, "accepted") !== false
    ) {
        return "✅";
    }

    if (
        strpos($title, "rejected") !== false ||
        strpos($type, "rejected") !== false
    ) {
        return "❌";
    }

    if (
        strpos($type, "exchange") !== false ||
        strpos($title, "request") !== false
    ) {
        return "📩";
    }

    if (
        strpos($type, "message") !== false ||
        strpos($title, "message") !== false
    ) {
        return "💬";
    }

    if (
        strpos($type, "call") !== false ||
        strpos($title, "call") !== false
    ) {
        return "📹";
    }

    return "🔔";
}

/* =========================
   NOTIFICATION OWNERSHIP
   Supports email-based and
   user_id-based notifications.
========================= */
function notificationOwnerCondition(
    $currentUserEmail,
    $currentUserId
) {
    if ($currentUserEmail !== "" && $currentUserId > 0) {
        return "(user_email = ? OR user_id = ?)";
    }

    if ($currentUserEmail !== "") {
        return "user_email = ?";
    }

    return "user_id = ?";
}

/* =========================
   MARK NOTIFICATIONS AS READ
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";

    if ($action === "mark_read") {

        $notificationId = (int)(
            $_POST["notification_id"] ?? 0
        );

        if ($notificationId > 0) {

            $ownerCondition = notificationOwnerCondition(
                $currentUserEmail,
                $currentUserId
            );

            $sql = "
                UPDATE notifications
                SET is_read = 1
                WHERE id = ?
                AND $ownerCondition
            ";

            $stmt = $conn->prepare($sql);

            if ($currentUserEmail !== "" && $currentUserId > 0) {
                $stmt->bind_param(
                    "isi",
                    $notificationId,
                    $currentUserEmail,
                    $currentUserId
                );
            } elseif ($currentUserEmail !== "") {
                $stmt->bind_param(
                    "is",
                    $notificationId,
                    $currentUserEmail
                );
            } else {
                $stmt->bind_param(
                    "ii",
                    $notificationId,
                    $currentUserId
                );
            }

            $stmt->execute();
            $stmt->close();
        }

        header("Location: notifications.php");
        exit();
    }

    if ($action === "mark_all_read") {

        $ownerCondition = notificationOwnerCondition(
            $currentUserEmail,
            $currentUserId
        );

        $sql = "
            UPDATE notifications
            SET is_read = 1
            WHERE $ownerCondition
            AND is_read = 0
        ";

        $stmt = $conn->prepare($sql);

        if ($currentUserEmail !== "" && $currentUserId > 0) {
            $stmt->bind_param(
                "si",
                $currentUserEmail,
                $currentUserId
            );
        } elseif ($currentUserEmail !== "") {
            $stmt->bind_param(
                "s",
                $currentUserEmail
            );
        } else {
            $stmt->bind_param(
                "i",
                $currentUserId
            );
        }

        $stmt->execute();
        $stmt->close();

        header("Location: notifications.php");
        exit();
    }
}

/* =========================
   FETCH NOTIFICATIONS
========================= */
$notifications = [];
$unreadCount = 0;

$ownerCondition = notificationOwnerCondition(
    $currentUserEmail,
    $currentUserId
);

$sql = "
    SELECT
        id,
        user_email,
        user_id,
        title,
        message,
        type,
        request_id,
        related_id,
        is_read,
        created_at
    FROM notifications
    WHERE $ownerCondition
    ORDER BY id DESC
";

$stmt = $conn->prepare($sql);

if ($currentUserEmail !== "" && $currentUserId > 0) {
    $stmt->bind_param(
        "si",
        $currentUserEmail,
        $currentUserId
    );
} elseif ($currentUserEmail !== "") {
    $stmt->bind_param(
        "s",
        $currentUserEmail
    );
} else {
    $stmt->bind_param(
        "i",
        $currentUserId
    );
}

$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {

    $notifications[] = $row;

    if ((int)$row["is_read"] === 0) {
        $unreadCount++;
    }
}

$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Notifications - SkillConnect</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f6faf7;
            color: #26352c;
        }

        .page-container {
            max-width: 1000px;
            margin: auto;
            padding: 40px 22px 70px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 18px;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }

        .page-header h1 {
            color: #285b41;
            font-size: 32px;
            margin-bottom: 8px;
        }

        .page-header p {
            color: #718078;
            font-size: 14px;
            line-height: 1.6;
        }

        .unread-badge {
            display: inline-block;
            background: #e0f2e7;
            color: #286344;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 5px;
            vertical-align: middle;
        }

        .mark-all-btn {
            background: #3b7d5a;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 11px 16px;
            font-weight: bold;
            cursor: pointer;
        }

        .mark-all-btn:hover {
            background: #285b41;
        }

        .notification-list {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .notification-card {
            background: white;
            border: 1px solid #e6eee8;
            border-radius: 13px;
            padding: 20px;
            display: flex;
            gap: 15px;
            align-items: flex-start;
            box-shadow: 0 4px 13px rgba(0, 0, 0, 0.04);
        }

        .notification-card.unread {
            background: #f0faf3;
            border-left: 5px solid #3b7d5a;
        }

        .notification-icon {
            width: 45px;
            height: 45px;
            flex-shrink: 0;
            border-radius: 50%;
            background: #e0f2e7;
            color: #285b41;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 21px;
        }

        .notification-content {
            flex: 1;
            min-width: 0;
        }

        .notification-content h3 {
            color: #285b41;
            font-size: 16px;
            margin-bottom: 7px;
            overflow-wrap: anywhere;
        }

        .notification-content p {
            color: #59665e;
            font-size: 14px;
            line-height: 1.6;
            overflow-wrap: anywhere;
        }

        .notification-date {
            color: #8a958e;
            font-size: 12px;
            margin-top: 10px;
        }

        .notification-actions {
            margin-top: 12px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .small-btn {
            display: inline-block;
            border: none;
            border-radius: 7px;
            padding: 8px 12px;
            cursor: pointer;
            text-decoration: none;
            font-size: 12px;
            font-weight: bold;
        }

        .read-btn {
            background: #e8f0eb;
            color: #285b41;
        }

        .requests-btn {
            background: #3b7d5a;
            color: white;
        }

        .small-btn:hover {
            opacity: 0.85;
        }

        .new-label {
            display: inline-block;
            background: #d8f0e1;
            color: #286344;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: bold;
            margin-left: 7px;
            vertical-align: middle;
        }

        .empty-state {
            background: white;
            padding: 55px 20px;
            border-radius: 14px;
            text-align: center;
            border: 1px solid #e6eee8;
        }

        .empty-icon {
            font-size: 45px;
            margin-bottom: 15px;
        }

        .empty-state h2 {
            color: #285b41;
            margin-bottom: 9px;
            font-size: 22px;
        }

        .empty-state p {
            color: #77847b;
            font-size: 14px;
            line-height: 1.6;
        }

        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #3b7d5a;
            text-decoration: none;
            font-weight: bold;
            font-size: 14px;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        @media (max-width: 600px) {
            .page-container {
                padding: 25px 14px 45px;
            }

            .page-header h1 {
                font-size: 27px;
            }

            .notification-card {
                padding: 15px;
                gap: 10px;
            }

            .notification-icon {
                width: 38px;
                height: 38px;
                font-size: 18px;
            }
        }
    </style>
</head>

<body>

<?php
if (file_exists("common-navbar.php")) {
    include "common-navbar.php";
}
?>

<main class="page-container">

    <div class="page-header">

        <div>
            <h1>
                🔔 Notifications

                <?php if ($unreadCount > 0): ?>
                    <span class="unread-badge">
                        <?= $unreadCount ?> unread
                    </span>
                <?php endif; ?>
            </h1>

            <p>
                Stay updated about your SkillConnect activities.
            </p>
        </div>

        <?php if ($unreadCount > 0): ?>
            <form method="POST">
                <input
                    type="hidden"
                    name="action"
                    value="mark_all_read"
                >

                <button
                    type="submit"
                    class="mark-all-btn"
                >
                    ✓ Mark All as Read
                </button>
            </form>
        <?php endif; ?>

    </div>

    <?php if (empty($notifications)): ?>

        <div class="empty-state">

            <div class="empty-icon">🔔</div>

            <h2>You're All Caught Up!</h2>

            <p>
                You don't have any notifications yet.<br>
                Updates about your skill exchange activities
                will appear here.
            </p>

            <a href="requests.php" class="back-link">
                View Exchange Requests →
            </a>

        </div>

    <?php else: ?>

        <div class="notification-list">

            <?php foreach ($notifications as $notification): ?>

                <?php
                    $isUnread = ((int)$notification["is_read"] === 0);

                    $title = (string)(
                        $notification["title"] ?? "Notification"
                    );

                    $message = (string)(
                        $notification["message"] ?? ""
                    );

                    $type = (string)(
                        $notification["type"] ?? ""
                    );

                    $icon = getNotificationIcon($type, $title);

                    $createdAt = $notification["created_at"] ?? "";

                    /*
                     * Keep the existing requests page unchanged.
                     * This link is useful for exchange-related alerts.
                     */
                    $typeLower = strtolower($type);
                    $titleLower = strtolower($title);

                    $isExchangeNotification =
                        strpos($typeLower, "exchange") !== false ||
                        strpos($typeLower, "request") !== false ||
                        strpos($titleLower, "request") !== false ||
                        strpos($titleLower, "accepted") !== false ||
                        strpos($titleLower, "rejected") !== false;
                ?>

                <article class="notification-card <?= $isUnread ? 'unread' : '' ?>">

                    <div class="notification-icon">
                        <?= e($icon) ?>
                    </div>

                    <div class="notification-content">

                        <h3>
                            <?= e($title) ?>

                            <?php if ($isUnread): ?>
                                <span class="new-label">NEW</span>
                            <?php endif; ?>
                        </h3>

                        <p><?= nl2br(e($message)) ?></p>

                        <div class="notification-date">
                            <?php
                            if (!empty($createdAt)) {
                                $timestamp = strtotime((string)$createdAt);

                                echo $timestamp
                                    ? e(date("d M Y, h:i A", $timestamp))
                                    : e($createdAt);
                            }
                            ?>
                        </div>

                        <div class="notification-actions">

                            <?php if ($isExchangeNotification): ?>
                                <a
                                    href="requests.php"
                                    class="small-btn requests-btn"
                                >
                                    View Requests
                                </a>
                            <?php endif; ?>

                            <?php if ($isUnread): ?>

                                <form method="POST">
                                    <input
                                        type="hidden"
                                        name="action"
                                        value="mark_read"
                                    >

                                    <input
                                        type="hidden"
                                        name="notification_id"
                                        value="<?= (int)$notification["id"] ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="small-btn read-btn"
                                    >
                                        ✓ Mark as Read
                                    </button>
                                </form>

                            <?php else: ?>

                                <span style="font-size:12px;color:#75847a;">
                                    ✓ Read
                                </span>

                            <?php endif; ?>

                        </div>

                    </div>

                </article>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</main>

</body>
</html>