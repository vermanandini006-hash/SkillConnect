
<?php
session_start();

if (!isset($_SESSION["user"])) {
    header("Location: login.php");
    exit;
}

require_once "db.php";

$currentUser = $_SESSION["user"];
$currentUserId = (int)($currentUser["id"] ?? 0);
$currentUserName = $currentUser["name"] ?? "";
$currentUserEmail = $currentUser["email"] ?? "";

if ($currentUserId <= 0 || $currentUserEmail === "") {
    die("Invalid user session.");
}

$stmt = $conn->prepare(
    "SELECT is_premium FROM users WHERE id = ? LIMIT 1"
);
$stmt->bind_param("i", $currentUserId);
$stmt->execute();
$userData = $stmt->get_result()->fetch_assoc();
$stmt->close();

$isPremium = (int)($userData["is_premium"] ?? 0);

$selectedExchangeId = (int)($_GET["exchange_id"] ?? 0);

/* =========================================================
   JSON RESPONSE
========================================================= */

function callJson($data, $status = 200)
{
    http_response_code($status);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode($data);
    exit;
}

/* =========================================================
   ACTIVE EXCHANGE PARTNERS
========================================================= */

function getCallPartners($conn, $userId)
{
    $partners = [];

    $stmt = $conn->prepare("
        SELECT
            e.id,
            e.student_one_id,
            e.student_one_name,
            e.student_one_email,
            e.student_two_id,
            e.student_two_name,
            e.student_two_email,
            e.skill
        FROM exchanges e
        INNER JOIN exchange_requests r
            ON r.id = e.request_id
            AND r.status = 'accepted'
        WHERE e.status = 'active'
          AND (
              e.student_one_id = ?
              OR e.student_two_id = ?
          )
        ORDER BY e.created_at DESC
    ");

    $stmt->bind_param("ii", $userId, $userId);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $isStudentOne =
            (int)$row["student_one_id"] === $userId;

        $partners[] = [
            "exchange_id" => (int)$row["id"],
            "partner_id" => (int)(
                $isStudentOne
                    ? $row["student_two_id"]
                    : $row["student_one_id"]
            ),
            "partner_name" => $isStudentOne
                ? $row["student_two_name"]
                : $row["student_one_name"],
            "partner_email" => $isStudentOne
                ? $row["student_two_email"]
                : $row["student_one_email"],
            "skill" => $row["skill"]
        ];
    }

    $stmt->close();
    return $partners;
}

function getValidCallExchange($conn, $exchangeId, $userId)
{
    $stmt = $conn->prepare("
        SELECT
            e.id,
            e.student_one_id,
            e.student_one_email,
            e.student_two_id,
            e.student_two_email
        FROM exchanges e
        INNER JOIN exchange_requests r
            ON r.id = e.request_id
            AND r.status = 'accepted'
        WHERE e.id = ?
          AND e.status = 'active'
          AND (
              e.student_one_id = ?
              OR e.student_two_id = ?
          )
        LIMIT 1
    ");

    $stmt->bind_param(
        "iii",
        $exchangeId,
        $userId,
        $userId
    );

    $stmt->execute();
    $exchange = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $exchange;
}

/* =========================================================
   AJAX - GET PARTNERS
========================================================= */

$action = $_POST["action"] ?? $_GET["action"] ?? "";

if ($action === "get_partners") {
    callJson([
        "success" => true,
        "exchanges" => getCallPartners($conn, $currentUserId)
    ]);
}

/* =========================================================
   AJAX - GET SIGNALS
========================================================= */

if ($action === "get_signals") {
    $lastId = max(0, (int)($_GET["last_id"] ?? 0));

    $stmt = $conn->prepare("
        SELECT
            id,
            exchange_id,
            caller_email,
            receiver_email,
            signal_type,
            signal_data,
            created_at
        FROM call_signals
        WHERE receiver_email = ?
          AND id > ?
          AND created_at >= DATE_SUB(
              NOW(), INTERVAL 5 MINUTE
          )
        ORDER BY id ASC
        LIMIT 50
    ");

    $stmt->bind_param("si", $currentUserEmail, $lastId);
    $stmt->execute();

    $signals = [];

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $row["id"] = (int)$row["id"];
        $row["exchange_id"] = (int)$row["exchange_id"];

        $exchange = getValidCallExchange(
            $conn,
            $row["exchange_id"],
            $currentUserId
        );

        if (!$exchange) {
            continue;
        }

        $otherEmail =
            (int)$exchange["student_one_id"] === $currentUserId
                ? $exchange["student_two_email"]
                : $exchange["student_one_email"];

        if ($row["caller_email"] !== $otherEmail) {
            continue;
        }

        $signals[] = $row;
    }

    $stmt->close();

    callJson([
        "success" => true,
        "signals" => $signals
    ]);
}

/* =========================================================
   AJAX - SEND SIGNAL
========================================================= */

if ($action === "send_signal") {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        callJson([
            "success" => false,
            "message" => "Invalid request."
        ], 405);
    }

    $exchangeId = (int)($_POST["exchange_id"] ?? 0);
    $receiverEmail = trim($_POST["receiver_email"] ?? "");
    $signalType = trim($_POST["signal_type"] ?? "");
    $signalData = $_POST["signal_data"] ?? "";

    $allowedTypes = [
        "offer",
        "answer",
        "candidate",
        "hangup"
    ];

    if (
        $exchangeId <= 0 ||
        $receiverEmail === "" ||
        $signalData === "" ||
        !in_array($signalType, $allowedTypes, true)
    ) {
        callJson([
            "success" => false,
            "message" => "Invalid call signal."
        ], 400);
    }

    if ($signalType === "offer" && $isPremium !== 1) {
        callJson([
            "success" => false,
            "message" => "Premium is required to start a call."
        ], 403);
    }

    $exchange = getValidCallExchange(
        $conn,
        $exchangeId,
        $currentUserId
    );

    if (!$exchange) {
        callJson([
            "success" => false,
            "message" => "This exchange is not active."
        ], 403);
    }

    $expectedReceiver =
        (int)$exchange["student_one_id"] === $currentUserId
            ? $exchange["student_two_email"]
            : $exchange["student_one_email"];

    if ($receiverEmail !== $expectedReceiver) {
        callJson([
            "success" => false,
            "message" => "Invalid call partner."
        ], 403);
    }

    $stmt = $conn->prepare("
        INSERT INTO call_signals (
            exchange_id,
            caller_email,
            receiver_email,
            signal_type,
            signal_data,
            status
        )
        VALUES (?, ?, ?, ?, ?, 'pending')
    ");

    $stmt->bind_param(
        "issss",
        $exchangeId,
        $currentUserEmail,
        $receiverEmail,
        $signalType,
        $signalData
    );

    $success = $stmt->execute();
    $stmt->close();

    callJson([
        "success" => $success,
        "message" => $success
            ? "Signal sent."
            : "Unable to send signal."
    ]);
}

/* =========================================================
   AJAX - END CALL
========================================================= */

if ($action === "end_call") {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        callJson([
            "success" => false,
            "message" => "Invalid request."
        ], 405);
    }

    $exchangeId = (int)($_POST["exchange_id"] ?? 0);

    $exchange = getValidCallExchange(
        $conn,
        $exchangeId,
        $currentUserId
    );

    if (!$exchange) {
        callJson([
            "success" => false,
            "message" => "Invalid exchange."
        ], 403);
    }

    $stmt = $conn->prepare("
        DELETE FROM call_signals
        WHERE exchange_id = ?
          AND signal_type <> 'hangup'
    ");

    $stmt->bind_param("i", $exchangeId);
    $stmt->execute();
    $stmt->close();

    callJson(["success" => true]);
}

$partners = getCallPartners($conn, $currentUserId);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Video Call - SkillConnect</title>

<style>
/* =========================================================
   VIDEO CALL PAGE
========================================================= */

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #f5faf7;
    color: #285b41;
    min-height: 100vh;
}

.video-page {
    max-width: 1200px;
    margin: auto;
    padding: 35px 20px 60px;
}

.video-heading {
    text-align: center;
    margin-bottom: 30px;
}

.video-heading h1 {
    font-size: 32px;
    color: #285b41;
    margin: 0 0 10px;
}

.video-heading p {
    font-size: 15px;
    color: #718077;
    margin: 0;
}

.partners-section {
    background: white;
    border-radius: 16px;
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: 0 5px 20px rgba(0,0,0,.07);
}

.partners-section h2 {
    margin: 0 0 8px;
    font-size: 22px;
}

.partners-subtitle {
    color: #777;
    font-size: 14px;
    margin-bottom: 20px;
}

.partners-list {
    display: grid;
    grid-template-columns: repeat(
        auto-fit,
        minmax(240px, 1fr)
    );
    gap: 15px;
}

.partner-card {
    display: block;
    width: 100%;
    text-align: left;
    background: #fbfefc;
    border: 1px solid #dcebe1;
    border-radius: 12px;
    padding: 18px;
    cursor: pointer;
    transition: .2s;
    font-family: inherit;
}

.partner-card:hover {
    border-color: #3b7d5a;
    transform: translateY(-2px);
}

.partner-card.active {
    border: 2px solid #3b7d5a;
    background: #eef7f1;
}

.partner-name {
    font-size: 18px;
    font-weight: bold;
    margin-bottom: 7px;
    color: #285b41;
}

.partner-email {
    color: #777;
    font-size: 13px;
    margin-bottom: 10px;
    overflow-wrap: anywhere;
}

.partner-skill {
    display: inline-block;
    background: #e8f4ec;
    color: #3b7d5a;
    padding: 6px 10px;
    border-radius: 15px;
    font-size: 12px;
}

.no-partners {
    text-align: center;
    padding: 35px;
    color: #777;
}

.premium-notice {
    margin-top: 18px;
    padding: 14px 18px;
    background: #fff9e9;
    border: 1px solid #f1dda2;
    border-radius: 10px;
    color: #765f20;
    text-align: center;
    font-size: 14px;
    line-height: 1.7;
}

.premium-notice a {
    display: inline-block;
    margin-top: 8px;
    color: #3b7d5a;
    font-weight: bold;
    text-decoration: none;
}

.call-box {
    display: none;
    background: white;
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 5px 25px rgba(0,0,0,.08);
}

.call-box.show {
    display: block;
}

.call-header {
    background: #285b41;
    color: white;
    padding: 18px 22px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
}

.call-title {
    font-size: 19px;
    font-weight: bold;
}

.call-status {
    background: rgba(255,255,255,.15);
    padding: 7px 12px;
    border-radius: 20px;
    font-size: 13px;
}

.video-area {
    height: 500px;
    background: #14221a;
    position: relative;
    overflow: hidden;
}

#remoteVideo {
    width: 100%;
    height: 100%;
    object-fit: cover;
    background: #14221a;
}

#localVideo {
    position: absolute;
    right: 20px;
    bottom: 20px;
    width: 220px;
    height: 150px;
    object-fit: cover;
    border-radius: 12px;
    border: 3px solid white;
    background: #25352b;
    z-index: 5;
}

.remote-placeholder {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    color: white;
    text-align: center;
    z-index: 2;
    padding: 20px;
}

.remote-avatar {
    width: 85px;
    height: 85px;
    border-radius: 50%;
    background: #3b7d5a;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 35px;
    margin-bottom: 15px;
}

.remote-placeholder h3 {
    font-size: 22px;
    margin: 0 0 8px;
}

.remote-placeholder p {
    color: #c8d5cc;
    font-size: 14px;
}

.screen-share-label {
    position: absolute;
    left: 20px;
    top: 20px;
    background: #3b7d5a;
    color: white;
    padding: 9px 14px;
    border-radius: 20px;
    z-index: 10;
    display: none;
}

.screen-share-label.show {
    display: block;
}

.start-panel {
    text-align: center;
    padding: 35px 20px;
}

.start-icon {
    font-size: 45px;
    margin-bottom: 10px;
}

.start-panel h2 {
    margin: 0 0 8px;
}

.start-panel p {
    color: #777;
    margin-bottom: 20px;
}

.start-call-btn {
    border: none;
    background: #3b7d5a;
    color: white;
    padding: 13px 28px;
    border-radius: 9px;
    font-size: 15px;
    font-weight: bold;
    cursor: pointer;
}

.start-call-btn:hover {
    background: #285b41;
}

.start-call-btn:disabled {
    background: #9eaea4;
    cursor: not-allowed;
}

.controls {
    display: flex;
    justify-content: center;
    gap: 12px;
    padding: 18px;
    flex-wrap: wrap;
}

.control-btn {
    width: 50px;
    height: 50px;
    border: none;
    border-radius: 50%;
    background: #eef7f1;
    color: #285b41;
    font-size: 21px;
    cursor: pointer;
}

.control-btn:hover {
    background: #dcefe3;
}

.control-btn.off {
    background: #f2dddd;
}

.control-btn.sharing {
    background: #3b7d5a;
    color: white;
}

.hangup-btn {
    background: #c94c4c;
    color: white;
}

.hangup-btn:hover {
    background: #a93d3d;
}

.incoming-call {
    position: fixed;
    inset: 0;
    background: rgba(15,30,21,.75);
    z-index: 3000;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.incoming-call.show {
    display: flex;
}

.incoming-box {
    width: 100%;
    max-width: 420px;
    background: white;
    border-radius: 20px;
    padding: 35px 25px;
    text-align: center;
    box-shadow: 0 15px 50px rgba(0,0,0,.25);
}

.incoming-icon {
    font-size: 55px;
    margin-bottom: 15px;
}

.incoming-box h2 {
    margin: 0 0 8px;
}

.incoming-box p {
    color: #777;
    margin-bottom: 25px;
}

.incoming-buttons {
    display: flex;
    justify-content: center;
    gap: 12px;
}

.accept-btn,
.reject-btn {
    border: none;
    padding: 12px 22px;
    border-radius: 9px;
    cursor: pointer;
    font-weight: bold;
}

.accept-btn {
    background: #3b7d5a;
    color: white;
}

.reject-btn {
    background: #f1e3e3;
    color: #a33c3c;
}

.toast {
    position: fixed;
    right: 20px;
    bottom: 20px;
    background: #285b41;
    color: white;
    padding: 14px 18px;
    border-radius: 9px;
    z-index: 4000;
    display: none;
    max-width: 350px;
}

.toast.show {
    display: block;
}

@media (max-width: 700px) {
    .video-page {
        padding: 25px 12px;
    }

    .video-area {
        height: 360px;
    }

    #localVideo {
        width: 130px;
        height: 95px;
        right: 10px;
        bottom: 10px;
    }

    .incoming-buttons {
        flex-direction: column;
    }

    .accept-btn,
    .reject-btn {
        width: 100%;
    }
}
</style>
</head>

<body>

<?php include "common-navbar.php"; ?>

<main class="video-page">

    <div class="video-heading">
        <h1>📹 Video Call</h1>
        <p>
            Connect face-to-face with your active
            skill exchange partner.
        </p>
    </div>

    <section class="partners-section">

        <h2>Your Active Skill Exchanges</h2>

        <p class="partners-subtitle">
            Select an accepted exchange partner to
            open the video call.
        </p>

        <div class="partners-list">

            <?php if (empty($partners)): ?>

                <div class="no-partners">
                    🤝
                    <p>
                        You don't have any active
                        skill exchanges yet.
                    </p>
                </div>

            <?php else: ?>

                <?php foreach ($partners as $partner): ?>

                    <button
                        type="button"
                        class="partner-card"
                        data-exchange-id="<?= (int)$partner["exchange_id"] ?>"
                        onclick="selectPartner(<?= (int)$partner["exchange_id"] ?>)"
                    >

                        <div class="partner-name">
                            👤 <?= htmlspecialchars(
                                $partner["partner_name"],
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>
                        </div>

                        <div class="partner-email">
                            <?= htmlspecialchars(
                                $partner["partner_email"],
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>
                        </div>

                        <span class="partner-skill">
                            🎯 <?= htmlspecialchars(
                                $partner["skill"],
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>
                        </span>

                    </button>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

    </section>

    <section class="call-box" id="callBox">

        <div class="call-header">
            <div class="call-title" id="callTitle">
                Select a partner
            </div>

            <div class="call-status" id="callStatus">
                Ready
            </div>
        </div>

        <div class="video-area">

            <video
                id="remoteVideo"
                autoplay
                playsinline
            ></video>

            <div
                class="remote-placeholder"
                id="remotePlaceholder"
            >
                <div class="remote-avatar" id="remoteAvatar">
                    ?
                </div>

                <h3 id="remoteName">
                    No partner selected
                </h3>

                <p id="remoteText">
                    Select an active partner.
                </p>
            </div>

            <div
                class="screen-share-label"
                id="screenShareLabel"
            >
                🖥️ Screen Sharing
            </div>

            <video
                id="localVideo"
                autoplay
                muted
                playsinline
            ></video>

        </div>

        <div class="start-panel" id="startPanel">

            <div class="start-icon">📹</div>

            <h2>Ready for a video call?</h2>

            <p id="startText">
                Select an active partner first.
            </p>

            <button
                type="button"
                class="start-call-btn"
                id="startCallButton"
                onclick="startCall()"
                disabled
            >
                📞 Start Video Call
            </button>

            <?php if ($isPremium !== 1): ?>

                <div class="premium-notice">
                    ⭐ <strong>
                        Premium is required to start a call.
                    </strong>

                    <br>

                    You can still receive and join
                    incoming calls for free.

                    <br>

                    <a href="premium.php">
                        Upgrade to Premium →
                    </a>
                </div>

            <?php endif; ?>

        </div>

        <div class="controls">

            <button
                type="button"
                class="control-btn"
                id="cameraButton"
                onclick="toggleCamera()"
                title="Camera"
            >🎥</button>

            <button
                type="button"
                class="control-btn"
                id="micButton"
                onclick="toggleMic()"
                title="Microphone"
            >🎤</button>

            <button
                type="button"
                class="control-btn"
                id="screenButton"
                onclick="toggleScreenShare()"
                title="Screen Share"
            >🖥️</button>

            <button
                type="button"
                class="control-btn hangup-btn"
                onclick="endCall()"
                title="End Call"
            >☎</button>

        </div>

    </section>

</main>

<!-- INCOMING CALL POPUP -->

<div class="incoming-call" id="incomingCall">

    <div class="incoming-box">

        <div class="incoming-icon">📹</div>

        <h2>Incoming Video Call</h2>

        <p id="incomingText">
            Someone is calling you.
        </p>

        <div class="incoming-buttons">

            <button
                type="button"
                class="accept-btn"
                onclick="acceptIncomingCall()"
            >
                📹 Join Call
            </button>

            <button
                type="button"
                class="reject-btn"
                onclick="rejectIncomingCall()"
            >
                ✕ Reject
            </button>

        </div>

    </div>

</div>

<div class="toast" id="toast"></div>

<script>
/* =========================================================
   USER AND PARTNERS
========================================================= */

const currentUserEmail =
    <?= json_encode($currentUserEmail) ?>;

const currentUserName =
    <?= json_encode($currentUserName) ?>;

const isPremium =
    <?= $isPremium === 1 ? "true" : "false" ?>;

const activePartners =
    <?= json_encode(
        $partners,
        JSON_HEX_TAG |
        JSON_HEX_AMP |
        JSON_HEX_APOS |
        JSON_HEX_QUOT
    ) ?>;

const initialExchangeId =
    <?= (int)$selectedExchangeId ?>;

/* =========================================================
   WEBRTC STATE
========================================================= */

let peerConnection = null;
let localStream = null;
let remoteStream = null;
let screenStream = null;
let originalVideoTrack = null;
let screenSharing = false;

let selectedExchangeId = null;
let selectedPartnerName = "";
let selectedPartnerEmail = "";

let lastSignalId = 0;
let incomingOffer = null;
let incomingCallerEmail = "";
let pendingCandidates = [];

let callActive = false;
let pollingBusy = false;
let endingCall = false;

const rtcConfig = {
    iceServers: [
        { urls: "stun:stun.l.google.com:19302" },
        { urls: "stun:stun1.l.google.com:19302" }
    ]
};

/* =========================================================
   HELPERS
========================================================= */

function element(id) {
    return document.getElementById(id);
}

function showToast(message) {
    const toast = element("toast");

    toast.textContent = message;
    toast.classList.add("show");

    clearTimeout(showToast.timer);

    showToast.timer = setTimeout(() => {
        toast.classList.remove("show");
    }, 4000);
}

function setStatus(status) {
    element("callStatus").textContent = status;
}

function findPartnerByExchangeId(exchangeId) {
    return activePartners.find(
        partner =>
            Number(partner.exchange_id) ===
            Number(exchangeId)
    ) || null;
}

function showCallBox() {
    element("callBox").classList.add("show");
}

function resetCallView() {
    element("startPanel").style.display = "block";
    element("remotePlaceholder").style.display = "flex";
    element("screenShareLabel").classList.remove("show");

    setStatus("Ready");

    element("remoteText").textContent =
        "Ready to connect.";

    element("cameraButton").classList.remove("off");
    element("micButton").classList.remove("off");
    element("screenButton").classList.remove("sharing");
}

/* =========================================================
   SELECT PARTNER
========================================================= */

function selectPartner(exchangeId) {
    if (callActive || peerConnection) {
        showToast("End your current call first.");
        return;
    }

    const partner = findPartnerByExchangeId(exchangeId);

    if (!partner) {
        showToast("Exchange partner not found.");
        return;
    }

    selectedExchangeId = Number(partner.exchange_id);
    selectedPartnerName = partner.partner_name;
    selectedPartnerEmail = partner.partner_email;

    document.querySelectorAll(".partner-card").forEach(card => {
        card.classList.toggle(
            "active",
            Number(card.dataset.exchangeId) === selectedExchangeId
        );
    });

    showCallBox();
    resetCallView();

    element("callTitle").textContent =
        "📹 " + selectedPartnerName;

    element("remoteName").textContent =
        selectedPartnerName;

    element("remoteAvatar").textContent =
        selectedPartnerName.charAt(0).toUpperCase();

    const button = element("startCallButton");

    button.disabled = !isPremium;

    button.textContent = isPremium
        ? "📞 Start Video Call"
        : "🔒 Premium Required";

    element("startText").textContent = isPremium
        ? "Click to call " + selectedPartnerName + "."
        : "Upgrade to Premium to start a call. You can still receive calls.";
}

/* =========================================================
   SIGNAL API
========================================================= */

async function sendSignal(type, data) {
    if (!selectedExchangeId || !selectedPartnerEmail) {
        throw new Error("Please select a partner.");
    }

    const form = new FormData();

    form.append("action", "send_signal");
    form.append("exchange_id", selectedExchangeId);
    form.append("receiver_email", selectedPartnerEmail);
    form.append("signal_type", type);
    form.append("signal_data", data);

    const response = await fetch(
        window.location.pathname,
        {
            method: "POST",
            body: form,
            cache: "no-store"
        }
    );

    const result = await response.json();

    if (!result.success) {
        throw new Error(
            result.message || "Unable to send signal."
        );
    }

    return result;
}

async function cleanupCallSignals(exchangeId) {
    if (!exchangeId) return;

    const form = new FormData();

    form.append("action", "end_call");
    form.append("exchange_id", exchangeId);

    try {
        await fetch(window.location.pathname, {
            method: "POST",
            body: form
        });
    } catch (error) {
        console.error("Cleanup error:", error);
    }
}

/* =========================================================
   CAMERA AND MICROPHONE
========================================================= */

async function startLocalMedia() {
    if (localStream) {
        return localStream;
    }

    if (!navigator.mediaDevices?.getUserMedia) {
        throw new Error(
            "Camera and microphone require localhost or HTTPS."
        );
    }

    localStream =
        await navigator.mediaDevices.getUserMedia({
            video: true,
            audio: true
        });

    element("localVideo").srcObject = localStream;

    return localStream;
}

/* =========================================================
   PEER CONNECTION
========================================================= */

function createPeerConnection() {
    if (peerConnection) {
        return peerConnection;
    }

    peerConnection = new RTCPeerConnection(rtcConfig);
    remoteStream = new MediaStream();

    element("remoteVideo").srcObject = remoteStream;

    if (localStream) {
        localStream.getTracks().forEach(track => {
            peerConnection.addTrack(track, localStream);
        });
    }

    peerConnection.ontrack = event => {
        const tracks = event.streams?.[0]?.getTracks()
            || (event.track ? [event.track] : []);

        tracks.forEach(track => {
            if (!remoteStream.getTracks().some(
                existing => existing.id === track.id
            )) {
                remoteStream.addTrack(track);
            }
        });

        element("remotePlaceholder").style.display = "none";
        setStatus("Connected");
    };

    peerConnection.onicecandidate = async event => {
        if (!event.candidate || endingCall) return;

        try {
            await sendSignal(
                "candidate",
                JSON.stringify(event.candidate)
            );
        } catch (error) {
            console.error("ICE error:", error);
        }
    };

    peerConnection.onconnectionstatechange = () => {
        if (!peerConnection) return;

        const state = peerConnection.connectionState;

        if (state === "connecting") {
            setStatus("Connecting...");
        }

        if (state === "connected") {
            setStatus("Connected");
        }

        if (state === "failed" || state === "disconnected") {
            setStatus("Connection lost");
        }
    };

    return peerConnection;
}

async function applyPendingCandidates() {
    if (
        !peerConnection ||
        !peerConnection.remoteDescription
    ) {
        return;
    }

    while (pendingCandidates.length) {
        const candidate = pendingCandidates.shift();

        try {
            await peerConnection.addIceCandidate(
                new RTCIceCandidate(candidate)
            );
        } catch (error) {
            console.error("Candidate error:", error);
        }
    }
}

/* =========================================================
   START VIDEO CALL
========================================================= */

async function startCall() {
    if (!isPremium) {
        showToast("Premium is required to start a call.");
        return;
    }

    if (!selectedExchangeId || !selectedPartnerEmail) {
        showToast("Please select a partner first.");
        return;
    }

    if (callActive || peerConnection) {
        showToast("A call is already in progress.");
        return;
    }

    try {
        pendingCandidates = [];
        callActive = true;

        await startLocalMedia();
        createPeerConnection();

        const offer = await peerConnection.createOffer();

        await peerConnection.setLocalDescription(offer);

        await sendSignal(
            "offer",
            JSON.stringify(offer)
        );

        element("startPanel").style.display = "none";

        setStatus("Calling...");

        element("remoteText").textContent =
            "Waiting for " + selectedPartnerName + " to answer.";

        showToast("Calling " + selectedPartnerName + "...");

    } catch (error) {
        console.error(error);
        showToast(error.message || "Unable to start call.");

        closePeerConnection();
        resetCallView();
    }
}

/* =========================================================
   RECEIVE SIGNALS
========================================================= */

async function pollSignals() {
    if (pollingBusy) return;

    pollingBusy = true;

    try {
        const response = await fetch(
            window.location.pathname +
            "?action=get_signals&last_id=" +
            encodeURIComponent(lastSignalId),
            { cache: "no-store" }
        );

        const result = await response.json();

        if (!result.success || !Array.isArray(result.signals)) {
            return;
        }

        for (const signal of result.signals) {
            lastSignalId = Math.max(
                lastSignalId,
                Number(signal.id)
            );

            await handleSignal(signal);
        }

    } catch (error) {
        console.error("Polling error:", error);

    } finally {
        pollingBusy = false;
    }
}

/* =========================================================
   HANDLE SIGNAL
========================================================= */

async function handleSignal(signal) {
    const partner = findPartnerByExchangeId(
        signal.exchange_id
    );

    if (!partner) return;

    if (
        signal.caller_email !== partner.partner_email ||
        signal.receiver_email !== currentUserEmail
    ) {
        return;
    }

    if (signal.signal_type === "offer") {
        if (callActive || peerConnection || incomingOffer) {
            return;
        }

        try {
            incomingOffer = JSON.parse(signal.signal_data);
        } catch (error) {
            console.error("Invalid offer:", error);
            return;
        }

        incomingCallerEmail = signal.caller_email;
        pendingCandidates = [];

        selectPartner(signal.exchange_id);

        element("incomingText").textContent =
            partner.partner_name + " is calling you.";

        element("remoteText").textContent =
            partner.partner_name + " is calling you.";

        setStatus("Incoming call");

        element("incomingCall").classList.add("show");

        return;
    }

    if (
        Number(signal.exchange_id) !== selectedExchangeId
    ) {
        return;
    }

    if (signal.signal_type === "answer") {
        if (!peerConnection) return;

        try {
            const answer = JSON.parse(signal.signal_data);

            if (peerConnection.signalingState === "have-local-offer") {
                await peerConnection.setRemoteDescription(
                    new RTCSessionDescription(answer)
                );

                await applyPendingCandidates();
                setStatus("Connecting...");
            }

        } catch (error) {
            console.error("Answer error:", error);
        }

        return;
    }

    if (signal.signal_type === "candidate") {
        try {
            const candidate = JSON.parse(signal.signal_data);

            if (
                peerConnection &&
                peerConnection.remoteDescription
            ) {
                await peerConnection.addIceCandidate(
                    new RTCIceCandidate(candidate)
                );
            } else if (peerConnection || incomingOffer) {
                pendingCandidates.push(candidate);
            }

        } catch (error) {
            console.error("Candidate error:", error);
        }

        return;
    }

    if (signal.signal_type === "hangup") {
        const hadCall =
            callActive ||
            !!peerConnection ||
            !!incomingOffer;

        closePeerConnection();
        resetCallView();

        if (hadCall) {
            showToast("The call has ended.");
        }
    }
}

/* =========================================================
   ACCEPT INCOMING CALL
========================================================= */

async function acceptIncomingCall() {
    if (!incomingOffer || !selectedExchangeId) {
        showToast("No incoming call available.");
        return;
    }

    element("incomingCall").classList.remove("show");

    try {
        callActive = true;

        await startLocalMedia();

        createPeerConnection();

        await peerConnection.setRemoteDescription(
            new RTCSessionDescription(incomingOffer)
        );

        await applyPendingCandidates();

        const answer = await peerConnection.createAnswer();

        await peerConnection.setLocalDescription(answer);

        await sendSignal(
            "answer",
            JSON.stringify(answer)
        );

        incomingOffer = null;
        incomingCallerEmail = "";

        element("startPanel").style.display = "none";

        setStatus("Connecting...");

        showToast("Joining video call...");

    } catch (error) {
        console.error(error);

        showToast(
            error.message || "Unable to join the call."
        );

        closePeerConnection();
        resetCallView();
    }
}

/* =========================================================
   REJECT INCOMING CALL
========================================================= */

async function rejectIncomingCall() {
    element("incomingCall").classList.remove("show");

    if (incomingOffer && selectedExchangeId) {
        try {
            await sendSignal(
                "hangup",
                JSON.stringify({ reason: "rejected" })
            );
        } catch (error) {
            console.error(error);
        }
    }

    incomingOffer = null;
    incomingCallerEmail = "";

    closePeerConnection();
    resetCallView();

    showToast("Incoming call rejected.");
}

/* =========================================================
   CAMERA ON / OFF
========================================================= */

function toggleCamera() {
    if (!localStream) {
        showToast("Start or join a call first.");
        return;
    }

    const track = localStream.getVideoTracks()[0];

    if (!track) {
        showToast("Camera is unavailable.");
        return;
    }

    track.enabled = !track.enabled;

    element("cameraButton").classList.toggle(
        "off",
        !track.enabled
    );

    showToast(
        track.enabled ? "Camera on" : "Camera off"
    );
}

/* =========================================================
   MICROPHONE ON / OFF
========================================================= */

function toggleMic() {
    if (!localStream) {
        showToast("Start or join a call first.");
        return;
    }

    const track = localStream.getAudioTracks()[0];

    if (!track) {
        showToast("Microphone is unavailable.");
        return;
    }

    track.enabled = !track.enabled;

    element("micButton").classList.toggle(
        "off",
        !track.enabled
    );

    showToast(
        track.enabled
            ? "Microphone on"
            : "Microphone muted"
    );
}

/* =========================================================
   SCREEN SHARING
========================================================= */

async function toggleScreenShare() {
    if (!peerConnection || !localStream) {
        showToast("Start or join a call first.");
        return;
    }

    const sender = peerConnection.getSenders().find(
        sender => sender.track?.kind === "video"
    );

    if (!sender) {
        showToast("Video connection unavailable.");
        return;
    }

    if (screenSharing) {
        await stopScreenShare();
        return;
    }

    if (!navigator.mediaDevices?.getDisplayMedia) {
        showToast("Screen sharing is not supported.");
        return;
    }

    try {
        screenStream =
            await navigator.mediaDevices.getDisplayMedia({
                video: true,
                audio: false
            });

        const screenTrack =
            screenStream.getVideoTracks()[0];

        originalVideoTrack =
            localStream.getVideoTracks()[0];

        await sender.replaceTrack(screenTrack);

        element("localVideo").srcObject = screenStream;

        screenSharing = true;

        element("screenButton").classList.add("sharing");
        element("screenShareLabel").classList.add("show");

        screenTrack.onended = () => {
            stopScreenShare();
        };

        showToast("Screen sharing started.");

    } catch (error) {
        console.error(error);

        showToast(
            "Screen sharing was cancelled or unavailable."
        );
    }
}

async function stopScreenShare() {
    if (!screenSharing && !screenStream) return;

    const sender = peerConnection?.getSenders().find(
        sender => sender.track?.kind === "video"
    );

    if (sender && originalVideoTrack) {
        try {
            await sender.replaceTrack(originalVideoTrack);
        } catch (error) {
            console.error(error);
        }
    }

    if (screenStream) {
        screenStream.getTracks().forEach(track => track.stop());
        screenStream = null;
    }

    element("localVideo").srcObject = localStream;

    screenSharing = false;
    originalVideoTrack = null;

    element("screenButton").classList.remove("sharing");
    element("screenShareLabel").classList.remove("show");

    showToast("Screen sharing stopped.");
}

/* =========================================================
   CLOSE MEDIA AND CONNECTION
========================================================= */

function closePeerConnection() {
    if (screenStream) {
        screenStream.getTracks().forEach(track => track.stop());
        screenStream = null;
    }

    if (peerConnection) {
        peerConnection.onicecandidate = null;
        peerConnection.ontrack = null;
        peerConnection.onconnectionstatechange = null;

        peerConnection.close();
        peerConnection = null;
    }

    if (localStream) {
        localStream.getTracks().forEach(track => track.stop());
        localStream = null;
    }

    if (remoteStream) {
        remoteStream.getTracks().forEach(track => track.stop());
        remoteStream = null;
    }

    element("localVideo").srcObject = null;
    element("remoteVideo").srcObject = null;

    element("incomingCall").classList.remove("show");

    screenSharing = false;
    originalVideoTrack = null;
    incomingOffer = null;
    incomingCallerEmail = "";
    pendingCandidates = [];
    callActive = false;
}

/* =========================================================
   END CALL
========================================================= */


async function endCall() {
    if (endingCall) return;

    endingCall = true;

    const exchangeId = selectedExchangeId;

    try {
        if (
            exchangeId &&
            selectedPartnerEmail &&
            (callActive || peerConnection || incomingOffer)
        ) {
            await sendSignal(
                "hangup",
                JSON.stringify({ reason: "ended" })
            );
        }
    } catch (error) {
        console.error("Hangup error:", error);
    }

    // Stop camera, microphone and peer connection
    closePeerConnection();

    // Reset call UI
    resetCallView();

    // Hide the entire video call screen
    element("callBox").classList.remove("show");

    // Remove selected partner highlight
    document.querySelectorAll(".partner-card").forEach(card => {
        card.classList.remove("active");
    });

    // Clear selected partner
    selectedExchangeId = null;
    selectedPartnerName = "";
    selectedPartnerEmail = "";

    // Clean old signals
    await cleanupCallSignals(exchangeId);

    endingCall = false;

    showToast("Video call ended successfully.");
}

/* =========================================================
   INITIALIZE
========================================================= */

document.addEventListener("DOMContentLoaded", () => {
    if (initialExchangeId > 0) {
        const partner = findPartnerByExchangeId(
            initialExchangeId
        );

        if (partner) {
            selectPartner(initialExchangeId);
        }
    }

    pollSignals();

    setInterval(pollSignals, 1500);
});

window.addEventListener("beforeunload", () => {
    if (peerConnection || localStream) {
        closePeerConnection();
    }
});

</script>

</body>
</html>
