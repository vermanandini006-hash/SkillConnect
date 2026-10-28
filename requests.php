
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
$currentUserId = (int)($currentUser["id"] ?? 0);
$currentUserName = $currentUser["name"] ?? "";
$currentUserEmail = $currentUser["email"] ?? "";

if ($currentUserId <= 0) {
    die("Invalid user session.");
}

/* =========================
   HELPERS
========================= */
function e($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}

function getPartnerSkills($conn, $userId) {
    $skills = [];

    $stmt = $conn->prepare("
        SELECT skills_to_teach
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->bind_param("i", $userId);
    $stmt->execute();

    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        return [];
    }

    $raw = trim((string)($user["skills_to_teach"] ?? ""));

    if ($raw === "") {
        return [];
    }

    $decoded = json_decode($raw, true);

    if (is_array($decoded)) {
        foreach ($decoded as $skill) {
            $skill = trim((string)$skill);

            if ($skill !== "") {
                $skills[] = $skill;
            }
        }
    } else {
        foreach (explode(",", $raw) as $skill) {
            $skill = trim($skill);

            if ($skill !== "") {
                $skills[] = $skill;
            }
        }
    }

    return array_values(array_unique($skills));
}

/* =========================
   ADD NOTIFICATION
========================= */

/* =========================
   ADD NOTIFICATION
========================= */

function addNotification(
    $conn,
    $userId,
    $title,
    $message,
    $relatedId
) {
    try {

        $stmt = $conn->prepare("
            INSERT INTO notifications
            (
                user_id,
                user_email,
                type,
                title,
                message,
                related_id,
                is_read
            )
            SELECT
                id,
                email,
                'exchange',
                ?,
                ?,
                ?,
                0
            FROM users
            WHERE id = ?
        ");

        $stmt->bind_param(
            "ssii",
            $title,
            $message,
            $relatedId,
            $userId
        );

        $stmt->execute();
        $stmt->close();

    } catch (Throwable $error) {

        error_log(
            "Exchange notification error: " .
            $error->getMessage()
        );
    }
}

        

/* =========================
   MESSAGES
========================= */
$message = "";
$messageType = "";

/* =========================
   POST ACTIONS
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";

    /* =====================
       ACCEPT REQUEST
    ===================== */
    if ($action === "accept") {

        $requestId = (int)($_POST["request_id"] ?? 0);
        $returnSkill = trim($_POST["return_skill"] ?? "");

        if ($requestId <= 0) {
            $message = "Invalid request.";
            $messageType = "error";
        } else {

            $stmt = $conn->prepare("
                SELECT *
                FROM exchange_requests
                WHERE id = ?
                AND receiver_id = ?
                LIMIT 1
            ");

            $stmt->bind_param(
                "ii",
                $requestId,
                $currentUserId
            );

            $stmt->execute();
            $request = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$request) {
                $message = "Request not found.";
                $messageType = "error";

            } elseif ($request["status"] !== "pending") {
                $message = "Request already processed.";
                $messageType = "error";

            } else {

                $partnerSkills = getPartnerSkills(
                    $conn,
                    (int)$request["sender_id"]
                );

                if (
                    $returnSkill !== "" &&
                    !empty($partnerSkills) &&
                    !in_array($returnSkill, $partnerSkills, true)
                ) {
                    $message = "Please select a valid return skill.";
                    $messageType = "error";

                } else {

                    $returnSkillValue = $returnSkill !== ""
                        ? $returnSkill
                        : null;

                    try {
                        $conn->begin_transaction();

                        $stmt = $conn->prepare("
                            UPDATE exchange_requests
                            SET status = 'accepted'
                            WHERE id = ?
                            AND receiver_id = ?
                            AND status = 'pending'
                        ");

                        $stmt->bind_param(
                            "ii",
                            $requestId,
                            $currentUserId
                        );

                        $stmt->execute();
                        $updated = $stmt->affected_rows;
                        $stmt->close();

                        if ($updated !== 1) {
                            throw new Exception("Request already processed.");
                        }

                        $stmt = $conn->prepare("
                            SELECT id
                            FROM exchanges
                            WHERE request_id = ?
                            LIMIT 1
                        ");

                        $stmt->bind_param("i", $requestId);
                        $stmt->execute();

                        $existing = $stmt->get_result()->fetch_assoc();
                        $stmt->close();

                        if (!$existing) {

                            $studentOneId = (int)$request["sender_id"];
                            $studentOneEmail = $request["sender_email"];
                            $studentOneName = $request["sender_name"];

                            $studentTwoId = (int)$request["receiver_id"];
                            $studentTwoEmail = $request["receiver_email"];
                            $studentTwoName = $request["receiver_name"];

                            $skill = $request["skill"];

                            $stmt = $conn->prepare("
                                INSERT INTO exchanges
                                (
                                    request_id,
                                    student_one_id,
                                    student_one_email,
                                    student_one_name,
                                    student_two_id,
                                    student_two_email,
                                    student_two_name,
                                    skill,
                                    return_skill,
                                    status
                                )
                                VALUES
                                (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
                            ");

                            $stmt->bind_param(
                                "iississss",
                                $requestId,
                                $studentOneId,
                                $studentOneEmail,
                                $studentOneName,
                                $studentTwoId,
                                $studentTwoEmail,
                                $studentTwoName,
                                $skill,
                                $returnSkillValue
                            );

                            $stmt->execute();
                            $stmt->close();
                        }

                        $conn->commit();

                        $notificationMessage =
                            $currentUserName .
                            " accepted your skill exchange request for " .
                            $request["skill"] . ".";

                        addNotification(
                            $conn,
                            (int)$request["sender_id"],
                            "Request Accepted",
                            $notificationMessage,
                            $requestId
                        );

                        $message = "Request accepted successfully.";
                        $messageType = "success";

                    } catch (Throwable $error) {

                        $conn->rollback();

                        $message = "Unable to accept request.";
                        $messageType = "error";
                    }
                }
            }
        }
    }

    /* =====================
       REJECT REQUEST
    ===================== */
    elseif ($action === "reject") {

        $requestId = (int)($_POST["request_id"] ?? 0);

        $stmt = $conn->prepare("
            SELECT *
            FROM exchange_requests
            WHERE id = ?
            AND receiver_id = ?
            LIMIT 1
        ");

        $stmt->bind_param(
            "ii",
            $requestId,
            $currentUserId
        );

        $stmt->execute();
        $request = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$request || $request["status"] !== "pending") {

            $message = "Request not found or already processed.";
            $messageType = "error";

        } else {

            $stmt = $conn->prepare("
                UPDATE exchange_requests
                SET status = 'rejected'
                WHERE id = ?
                AND receiver_id = ?
                AND status = 'pending'
            ");

            $stmt->bind_param(
                "ii",
                $requestId,
                $currentUserId
            );

            $stmt->execute();
            $updated = $stmt->affected_rows;
            $stmt->close();

            if ($updated === 1) {

                addNotification(
                    $conn,
                    (int)$request["sender_id"],
                    "Request Rejected",
                    "Your skill exchange request for " .
                    $request["skill"] . " was rejected.",
                    $requestId
                );

                $message = "Request rejected.";
                $messageType = "success";

            } else {
                $message = "Request already processed.";
                $messageType = "error";
            }
        }
    }

    /* =====================
       END EXCHANGE
    ===================== */
    elseif ($action === "end_exchange") {

        $exchangeId = (int)($_POST["exchange_id"] ?? 0);

        $stmt = $conn->prepare("
            SELECT *
            FROM exchanges
            WHERE id = ?
            AND (
                student_one_id = ?
                OR student_two_id = ?
            )
            LIMIT 1
        ");

        $stmt->bind_param(
            "iii",
            $exchangeId,
            $currentUserId,
            $currentUserId
        );

        $stmt->execute();
        $exchange = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$exchange || $exchange["status"] !== "active") {

            $message = "Exchange not found or already ended.";
            $messageType = "error";

        } else {

            if ((int)$exchange["student_one_id"] === $currentUserId) {
                $partnerId = (int)$exchange["student_two_id"];
                $partnerName = $exchange["student_two_name"];
            } else {
                $partnerId = (int)$exchange["student_one_id"];
                $partnerName = $exchange["student_one_name"];
            }

            $stmt = $conn->prepare("
                UPDATE exchanges
                SET status = 'ended'
                WHERE id = ?
                AND (
                    student_one_id = ?
                    OR student_two_id = ?
                )
                AND status = 'active'
            ");

            $stmt->bind_param(
                "iii",
                $exchangeId,
                $currentUserId,
                $currentUserId
            );

            $stmt->execute();
            $updated = $stmt->affected_rows;
            $stmt->close();

            if ($updated === 1) {

                addNotification(
                    $conn,
                    $partnerId,
                    "Exchange Ended",
                    $currentUserName .
                    " ended the skill exchange with you. " .
                    "Your chat history is still saved.",
                    $exchangeId
                );

                $message = "Exchange with " .
                    $partnerName .
                    " ended successfully.";

                $messageType = "success";

            } else {
                $message = "Unable to end exchange.";
                $messageType = "error";
            }
        }
    }
}

/* =========================
   RECEIVED REQUESTS
========================= */
$receivedRequests = [];

$stmt = $conn->prepare("
    SELECT *
    FROM exchange_requests
    WHERE receiver_id = ?
    ORDER BY id DESC
");

$stmt->bind_param("i", $currentUserId);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $receivedRequests[] = $row;
}

$stmt->close();

/* =========================
   SENT REQUESTS
========================= */
$sentRequests = [];

$stmt = $conn->prepare("
    SELECT *
    FROM exchange_requests
    WHERE sender_id = ?
    ORDER BY id DESC
");

$stmt->bind_param("i", $currentUserId);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $sentRequests[] = $row;
}

$stmt->close();

/* =========================
   ACTIVE EXCHANGES
========================= */
$activeExchanges = [];

$stmt = $conn->prepare("
    SELECT *
    FROM exchanges
    WHERE (
        student_one_id = ?
        OR student_two_id = ?
    )
    AND status = 'active'
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
    $activeExchanges[] = $row;
}

$stmt->close();

/* =========================
   EXCHANGE MAP
========================= */
$exchangeByRequestId = [];

$stmt = $conn->prepare("
    SELECT *
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
    $exchangeByRequestId[(int)$row["request_id"]] = $row;
}

$stmt->close();

/* =========================
   PARTNER SKILLS
========================= */
$partnerSkillsMap = [];

foreach ($receivedRequests as $request) {

    if ($request["status"] !== "pending") {
        continue;
    }

    $partnerSkillsMap[(string)$request["id"]] =
        getPartnerSkills(
            $conn,
            (int)$request["sender_id"]
        );
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Requests - SkillConnect</title>

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
            max-width: 1150px;
            margin: auto;
            padding: 40px 25px 70px;
        }

        .page-header {
            margin-bottom: 25px;
        }

        .page-header h1 {
            color: #285b41;
            font-size: 32px;
            margin-bottom: 8px;
        }

        .page-header p {
            color: #6d7771;
            font-size: 15px;
        }

        .message {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 22px;
            font-size: 14px;
            font-weight: 600;
        }

        .message.success {
            background: #e8f7ee;
            color: #247348;
            border: 1px solid #bce5cb;
        }

        .message.error {
            background: #fff0ef;
            color: #b7352a;
            border: 1px solid #f1c1bd;
        }

        .tabs {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }

        .tab-button {
            border: none;
            background: #fff;
            color: #555;
            padding: 12px 20px;
            border-radius: 9px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            box-shadow: 0 2px 10px rgba(0,0,0,.05);
        }

        .tab-button:hover {
            background: #edf8f2;
            color: #2f8f61;
        }

        .tab-button.active {
            background: #3b7d5a;
            color: #fff;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .request-card,
        .exchange-card {
            background: #fff;
            border-radius: 15px;
            padding: 22px;
            margin-bottom: 17px;
            box-shadow: 0 4px 16px rgba(0,0,0,.06);
            border: 1px solid #edf1ee;
        }

        .card-top,
        .exchange-title {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 15px;
        }

        .person {
            display: flex;
            gap: 14px;
            align-items: center;
        }

        .avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: #dff1e6;
            color: #2f704f;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 18px;
            flex-shrink: 0;
        }

        .person h3 {
            color: #285b41;
            font-size: 17px;
            margin-bottom: 4px;
        }

        .person p {
            color: #777;
            font-size: 13px;
            overflow-wrap: anywhere;
        }

        .status {
            padding: 7px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: capitalize;
            white-space: nowrap;
        }

        .status.pending {
            background: #fff5d9;
            color: #9b7200;
        }

        .status.accepted,
        .status.active {
            background: #e5f7eb;
            color: #287347;
        }

        .status.rejected,
        .status.ended {
            background: #ffe9e7;
            color: #b53b32;
        }

        .skill-box {
            margin-top: 18px;
            padding: 15px;
            background: #f6faf7;
            border-radius: 10px;
        }

        .skill-box h4 {
            color: #285b41;
            font-size: 14px;
            margin-bottom: 7px;
        }

        .skill-box p {
            color: #555;
            font-size: 14px;
            line-height: 1.6;
            overflow-wrap: anywhere;
        }

        .card-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 18px;
            align-items: center;
        }

        .btn {
            display: inline-block;
            border: none;
            border-radius: 8px;
            padding: 11px 17px;
            text-decoration: none;
            text-align: center;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: .2s;
        }

        .btn-accept {
            background: #3b7d5a;
            color: #fff;
        }

        .btn-accept:hover {
            background: #285b41;
        }

        .btn-reject {
            background: #fff0ef;
            color: #b7352a;
        }

        .btn-reject:hover {
            background: #ffe0dd;
        }

        .btn-chat {
            background: #e8f7ee;
            color: #287347;
        }

        .btn-chat:hover {
            background: #d7efdf;
        }

        .btn-end {
            background: #fff0ef;
            color: #b7352a;
        }

        .btn-end:hover {
            background: #ffe0dd;
        }

        /* VIEW PROFILE BUTTON */
        .btn-profile {
            background: #e6f1fa;
            color: #25608c;
            border: 1px solid #c5dff0;
        }

        .btn-profile:hover {
            background: #d3e9f8;
            color: #174e76;
        }

        .empty-state {
            background: #fff;
            padding: 45px 20px;
            text-align: center;
            border-radius: 15px;
            border: 1px dashed #cbd9cf;
        }

        .empty-state h3 {
            color: #285b41;
            margin-bottom: 8px;
        }

        .empty-state p {
            color: #777;
            font-size: 14px;
            line-height: 1.6;
        }

        .exchange-meta {
            margin-top: 14px;
            color: #666;
            font-size: 13px;
            line-height: 1.8;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            inset: 0;
            background: rgba(0,0,0,.55);
            align-items: center;
            justify-content: center;
            padding: 18px;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: #fff;
            width: 100%;
            max-width: 460px;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 35px rgba(0,0,0,.2);
        }

        .modal-content h2 {
            color: #285b41;
            margin-bottom: 10px;
            font-size: 22px;
        }

        .modal-content p {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 18px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            color: #333;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #d6e1d9;
            border-radius: 8px;
            outline: none;
            background: #fff;
            font-size: 14px;
        }

        .form-group select:focus {
            border-color: #3b7d5a;
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-cancel {
            background: #edf1ee;
            color: #444;
        }

        @media (max-width: 650px) {
            .page-container {
                padding: 25px 14px 45px;
            }

            .page-header h1 {
                font-size: 27px;
            }

            .request-card,
            .exchange-card {
                padding: 17px;
            }

            .card-top,
            .exchange-title {
                flex-direction: column;
            }

            .tabs {
                display: grid;
                grid-template-columns: 1fr 1fr;
            }

            .tab-button {
                padding: 11px 8px;
            }

            .card-actions .btn,
            .card-actions form {
                flex: 1 1 100%;
            }

            .card-actions form .btn {
                width: 100%;
            }
        }
    </style>
</head>

<body>

<?php include "common-navbar.php"; ?>

<div class="page-container">

    <div class="page-header">
        <h1>My Requests</h1>
        <p>Manage your skill exchange requests and active exchanges.</p>
    </div>

    <?php if ($message !== ""): ?>
        <div class="message <?= e($messageType) ?>">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <div class="tabs">

        <button type="button"
                class="tab-button active"
                data-tab="received">
            Received Requests (<?= count($receivedRequests) ?>)
        </button>

        <button type="button"
                class="tab-button"
                data-tab="sent">
            Sent Requests (<?= count($sentRequests) ?>)
        </button>

        <button type="button"
                class="tab-button"
                data-tab="exchanges">
            Active Exchanges (<?= count($activeExchanges) ?>)
        </button>

    </div>

    <!-- RECEIVED REQUESTS -->
    <section id="received" class="tab-content active">

        <?php if (empty($receivedRequests)): ?>

            <div class="empty-state">
                <h3>No Received Requests</h3>
                <p>When someone sends you a request, it will appear here.</p>
            </div>

        <?php else: ?>

            <?php foreach ($receivedRequests as $request): ?>

                <?php
                $senderId = (int)$request["sender_id"];
                $requestId = (int)$request["id"];
                $status = strtolower($request["status"]);
                $requestExchange = $exchangeByRequestId[$requestId] ?? null;
                ?>

                <div class="request-card">

                    <div class="card-top">
                        <div class="person">
                            <div class="avatar">
                                <?= e(strtoupper(substr($request["sender_name"] ?? "S", 0, 1))) ?>
                            </div>
                            <div>
                                <h3><?= e($request["sender_name"]) ?></h3>
                                <p><?= e($request["sender_email"]) ?></p>
                            </div>
                        </div>

                        <span class="status <?= e($status) ?>">
                            <?= e(ucfirst($status)) ?>
                        </span>
                    </div>

                    <div class="skill-box">
                        <h4>Skill They Want to Learn</h4>
                        <p><?= e($request["skill"]) ?></p>
                    </div>

                    <?php if (!empty($request["message"])): ?>
                        <div class="skill-box">
                            <h4>Message</h4>
                            <p><?= nl2br(e($request["message"])) ?></p>
                        </div>
                    <?php endif; ?>

                    <div class="exchange-meta">
                        <strong>Request ID:</strong> #<?= $requestId ?>
                        <br>
                        <strong>Date:</strong> <?= e($request["created_at"] ?? "") ?>
                    </div>

                    <div class="card-actions">

                        <a href="profile.php?id=<?= $senderId ?>"
                           class="btn btn-profile">
                            👤 View Profile
                        </a>

                        <?php if ($status === "pending"): ?>

                            <button type="button"
                                    class="btn btn-accept"
                                    onclick="openAcceptModal(<?= $requestId ?>)">
                                Accept Request
                            </button>

                            <form method="POST">
                                <input type="hidden" name="action" value="reject">
                                <input type="hidden" name="request_id" value="<?= $requestId ?>">

                                <button type="submit"
                                        class="btn btn-reject"
                                        onclick="return confirm('Reject this request?')">
                                    Reject
                                </button>
                            </form>

                        <?php elseif ($status === "accepted" && $requestExchange): ?>

                            <?php if ($requestExchange["status"] === "active"): ?>

                                <a class="btn btn-chat"
                                   href="chat.php?exchange_id=<?= (int)$requestExchange["id"] ?>">
                                    Open Chat
                                </a>

                                <form method="POST">
                                    <input type="hidden" name="action" value="end_exchange">
                                    <input type="hidden"
                                           name="exchange_id"
                                           value="<?= (int)$requestExchange["id"] ?>">

                                    <button type="submit"
                                            class="btn btn-end"
                                            onclick="return confirm('End exchange? Chat history will remain saved.')">
                                        End Exchange
                                    </button>
                                </form>

                            <?php else: ?>
                                <span class="status ended">Exchange Ended</span>
                            <?php endif; ?>

                        <?php endif; ?>

                    </div>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </section>

    <!-- SENT REQUESTS -->
    <section id="sent" class="tab-content">

        <?php if (empty($sentRequests)): ?>

            <div class="empty-state">
                <h3>No Sent Requests</h3>
                <p>Requests you send to students will appear here.</p>
            </div>

        <?php else: ?>

            <?php foreach ($sentRequests as $request): ?>

                <?php
                $receiverId = (int)$request["receiver_id"];
                $requestId = (int)$request["id"];
                $status = strtolower($request["status"]);
                $requestExchange = $exchangeByRequestId[$requestId] ?? null;
                ?>

                <div class="request-card">

                    <div class="card-top">
                        <div class="person">
                            <div class="avatar">
                                <?= e(strtoupper(substr($request["receiver_name"] ?? "S", 0, 1))) ?>
                            </div>
                            <div>
                                <h3><?= e($request["receiver_name"]) ?></h3>
                                <p><?= e($request["receiver_email"]) ?></p>
                            </div>
                        </div>

                        <span class="status <?= e($status) ?>">
                            <?= e(ucfirst($status)) ?>
                        </span>
                    </div>

                    <div class="skill-box">
                        <h4>Requested Skill</h4>
                        <p><?= e($request["skill"]) ?></p>
                    </div>

                    <?php if (!empty($request["message"])): ?>
                        <div class="skill-box">
                            <h4>Your Message</h4>
                            <p><?= nl2br(e($request["message"])) ?></p>
                        </div>
                    <?php endif; ?>

                    <div class="exchange-meta">
                        <strong>Request ID:</strong> #<?= $requestId ?>
                        <br>
                        <strong>Date:</strong> <?= e($request["created_at"] ?? "") ?>
                    </div>

                    <div class="card-actions">

                        <a href="profile.php?id=<?= $receiverId ?>"
                           class="btn btn-profile">
                            👤 View Profile
                        </a>

                        <?php if ($status === "accepted" && $requestExchange): ?>

                            <?php if ($requestExchange["status"] === "active"): ?>

                                <a class="btn btn-chat"
                                   href="chat.php?exchange_id=<?= (int)$requestExchange["id"] ?>">
                                    Open Chat
                                </a>

                                <form method="POST">
                                    <input type="hidden" name="action" value="end_exchange">
                                    <input type="hidden"
                                           name="exchange_id"
                                           value="<?= (int)$requestExchange["id"] ?>">

                                    <button type="submit"
                                            class="btn btn-end"
                                            onclick="return confirm('End exchange? Chat history will remain saved.')">
                                        End Exchange
                                    </button>
                                </form>

                            <?php else: ?>
                                <span class="status ended">Exchange Ended</span>
                            <?php endif; ?>

                        <?php endif; ?>

                    </div>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </section>

    <!-- ACTIVE EXCHANGES -->
    <section id="exchanges" class="tab-content">

        <?php if (empty($activeExchanges)): ?>

            <div class="empty-state">
                <h3>No Active Exchanges</h3>
                <p>Accept a request to start exchanging skills.</p>
            </div>

        <?php else: ?>

            <?php foreach ($activeExchanges as $exchange): ?>

                <?php
                if ((int)$exchange["student_one_id"] === $currentUserId) {
                    $partnerName = $exchange["student_two_name"];
                    $partnerEmail = $exchange["student_two_email"];
                    $partnerId = (int)$exchange["student_two_id"];
                    $learningSkill = $exchange["skill"];
                    $teachingSkill = $exchange["return_skill"];
                } else {
                    $partnerName = $exchange["student_one_name"];
                    $partnerEmail = $exchange["student_one_email"];
                    $partnerId = (int)$exchange["student_one_id"];
                    $learningSkill = $exchange["return_skill"];
                    $teachingSkill = $exchange["skill"];
                }
                ?>

                <div class="exchange-card">

                    <div class="exchange-title">
                        <div class="person">
                            <div class="avatar">
                                <?= e(strtoupper(substr($partnerName ?? "S", 0, 1))) ?>
                            </div>
                            <div>
                                <h3><?= e($partnerName) ?></h3>
                                <p><?= e($partnerEmail) ?></p>
                            </div>
                        </div>

                        <span class="status active">Active</span>
                    </div>

                    <div class="skill-box">
                        <h4>Skill Exchange</h4>

                        <p>
                            <strong>Requested Skill:</strong>
                            <?= e($learningSkill ?: "Not specified") ?>
                        </p>

                        <p>
                            <strong>Return Skill:</strong>
                            <?= e($teachingSkill ?: "Not specified") ?>
                        </p>
                    </div>

                    <div class="card-actions">

                        <a href="profile.php?id=<?= $partnerId ?>"
                           class="btn btn-profile">
                            👤 View Profile
                        </a>

                        <a class="btn btn-chat"
                           href="chat.php?exchange_id=<?= (int)$exchange["id"] ?>">
                            Open Chat
                        </a>

                        <form method="POST">
                            <input type="hidden" name="action" value="end_exchange">
                            <input type="hidden"
                                   name="exchange_id"
                                   value="<?= (int)$exchange["id"] ?>">

                            <button type="submit"
                                    class="btn btn-end"
                                    onclick="return confirm('End exchange? Chat history will remain saved.')">
                                End Exchange
                            </button>
                        </form>

                    </div>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </section>

</div>

<!-- ACCEPT REQUEST MODAL -->
<div class="modal" id="acceptModal">

    <div class="modal-content">

        <h2>Accept Skill Exchange</h2>

        <p>
            Choose an optional skill you want to learn from
            the student who sent this request.
        </p>

        <form method="POST" id="acceptForm">

            <input type="hidden" name="action" value="accept">

            <input type="hidden"
                   name="request_id"
                   id="modalRequestId"
                   value="">

            <div class="form-group">

                <label for="returnSkill">
                    Skill You Want to Learn (Optional)
                </label>

                <select name="return_skill" id="returnSkill">
                    <option value="">No return skill selected</option>
                </select>

            </div>

            <div class="modal-actions">

                <button type="button"
                        class="btn btn-cancel"
                        onclick="closeAcceptModal()">
                    Cancel
                </button>

                <button type="submit" class="btn btn-accept">
                    Confirm Accept
                </button>

            </div>

        </form>

    </div>

</div>

<script>

/* =========================================
   TAB SWITCHING
========================================= */

document.querySelectorAll(".tab-button").forEach(function(button) {

    button.addEventListener("click", function() {

        document.querySelectorAll(".tab-button").forEach(function(item) {
            item.classList.remove("active");
        });

        document.querySelectorAll(".tab-content").forEach(function(section) {
            section.classList.remove("active");
        });

        button.classList.add("active");

        const tabId = button.dataset.tab;
        const section = document.getElementById(tabId);

        if (section) {
            section.classList.add("active");
        }

        try {
            localStorage.setItem("requestsActiveTab", tabId);
        } catch (error) {
            // Tab switching still works without localStorage.
        }

    });

});


/* =========================================
   RESTORE PREVIOUSLY SELECTED TAB
========================================= */

try {

    const savedTab = localStorage.getItem("requestsActiveTab");

    if (savedTab) {

        const savedButton = document.querySelector(
            '.tab-button[data-tab="' + savedTab + '"]'
        );

        if (savedButton) {
            savedButton.click();
        }

    }

} catch (error) {
    // Ignore unavailable localStorage.
}


/* =========================================
   PARTNER SKILLS DATA FROM PHP
========================================= */

const partnerSkillsMap = <?php
    echo json_encode(
        $partnerSkillsMap,
        JSON_HEX_TAG |
        JSON_HEX_APOS |
        JSON_HEX_QUOT |
        JSON_HEX_AMP
    );
?>;


/* =========================================
   OPEN ACCEPT REQUEST MODAL
========================================= */

function openAcceptModal(requestId) {

    const modal = document.getElementById("acceptModal");

    const requestInput = document.getElementById("modalRequestId");

    const skillSelect = document.getElementById("returnSkill");

    if (!modal || !requestInput || !skillSelect) {
        return;
    }

    requestInput.value = requestId;

    skillSelect.innerHTML = "";

    const defaultOption = document.createElement("option");

    defaultOption.value = "";

    defaultOption.textContent = "No return skill selected";

    skillSelect.appendChild(defaultOption);

    const skills = partnerSkillsMap[String(requestId)] || [];

    skills.forEach(function(skill) {

        const option = document.createElement("option");

        option.value = skill;

        option.textContent = skill;

        skillSelect.appendChild(option);

    });

    modal.classList.add("show");

}


/* =========================================
   CLOSE ACCEPT REQUEST MODAL
========================================= */

function closeAcceptModal() {

    const modal = document.getElementById("acceptModal");

    if (modal) {
        modal.classList.remove("show");
    }

}


/* =========================================
   CLOSE MODAL WHEN CLICKING OUTSIDE
========================================= */

const acceptModal = document.getElementById("acceptModal");

if (acceptModal) {

    acceptModal.addEventListener("click", function(event) {

        if (event.target === this) {
            closeAcceptModal();
        }

    });

}


/* =========================================
   CLOSE MODAL WITH ESCAPE KEY
========================================= */

document.addEventListener("keydown", function(event) {

    if (event.key === "Escape") {
        closeAcceptModal();
    }

});

</script>

</body>
</html>
