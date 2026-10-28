
<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["user"])) {
    header("Location: login.php");
    exit();
}

$currentUser = $_SESSION["user"];

$userId = (int)($currentUser["id"] ?? 0);
$name = $currentUser["name"] ?? "Student";
$email = $currentUser["email"] ?? "";
$course = $currentUser["course"] ?? "";
$college = $currentUser["college"] ?? "";
$profileImage = "";

$skillsToTeach = [];
$skillsToLearn = [];
$exchangePartners = [];
$recentNotifications = [];

$messageCount = 0;
$activeExchanges = 0;
$unreadCount = 0;

function dashEscape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function skillTextToArray($value) {
    if ($value === null || trim((string)$value) === "") {
        return [];
    }

    $decoded = json_decode((string)$value, true);
    $items = is_array($decoded)
        ? $decoded
        : explode(",", (string)$value);

    $skills = [];

    foreach ($items as $skill) {
        if (is_scalar($skill)) {
            $skill = trim((string)$skill);
            if ($skill !== "") {
                $skills[] = $skill;
            }
        }
    }

    return array_values(array_unique($skills));
}

function dashInitial($name) {
    $name = trim((string)$name);
    return $name !== "" ? strtoupper(substr($name, 0, 1)) : "S";
}

/*
   Convert a stored relative photo path into a browser URL.
   The photo must be inside the project's public directory.
*/
function dashPhotoUrl($path) {
    $path = trim(str_replace("\\", "/", (string)$path));

    if ($path === "") {
        return "";
    }

    // Do not allow arbitrary external URLs or traversal paths.
    if (
        preg_match('~^(?:https?:)?//~i', $path) ||
        preg_match('~^data:~i', $path) ||
        strpos($path, "..") !== false
    ) {
        return "";
    }

    $path = preg_replace('~^\./~', '', $path);
    $path = ltrim($path, "/");

    return $path;
}

/* =========================================
   LOAD CURRENT USER + PROFILE PHOTO
========================================= */

try {
    $stmt = $conn->prepare("
        SELECT
            id,
            name,
            email,
            course,
            college,
            profile_image,
            skills_to_teach,
            skills_to_learn
        FROM users
        WHERE email = ?
        LIMIT 1
    ");

    $stmt->bind_param("s", $email);
    $stmt->execute();

    $dbUser = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($dbUser) {
        $userId = (int)$dbUser["id"];
        $name = $dbUser["name"];
        $email = $dbUser["email"];
        $course = $dbUser["course"] ?? "";
        $college = $dbUser["college"] ?? "";

        $profileImage = dashPhotoUrl(
            $dbUser["profile_image"] ?? ""
        );

        $skillsToTeach = skillTextToArray(
            $dbUser["skills_to_teach"] ?? ""
        );

        $skillsToLearn = skillTextToArray(
            $dbUser["skills_to_learn"] ?? ""
        );

        $_SESSION["user"]["id"] = $userId;
        $_SESSION["user"]["name"] = $name;
        $_SESSION["user"]["email"] = $email;
    }
} catch (Throwable $e) {
    error_log("Dashboard user error: " . $e->getMessage());
}

/* =========================================
   MESSAGE COUNT
========================================= */

try {
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM messages
        WHERE sender_email = ?
           OR receiver_email = ?
    ");

    $stmt->bind_param("ss", $email, $email);
    $stmt->execute();

    $row = $stmt->get_result()->fetch_assoc();
    $messageCount = (int)($row["total"] ?? 0);

    $stmt->close();
} catch (Throwable $e) {
    error_log("Dashboard messages error: " . $e->getMessage());
}

/* =========================================
   ACTIVE EXCHANGES
   ONLY ACCEPTED + ACTIVE CONNECTIONS
========================================= */

try {
    $stmt = $conn->prepare("
        SELECT
            e.id,
            e.student_one_id,
            e.student_one_name,
            e.student_one_email,
            e.student_two_id,
            e.student_two_name,
            e.student_two_email,
            e.skill,
            e.return_skill
        FROM exchanges e
        INNER JOIN exchange_requests r
            ON r.id = e.request_id
        WHERE e.status = 'active'
          AND r.status = 'accepted'
          AND (
              (
                  r.sender_id = e.student_one_id
                  AND r.receiver_id = e.student_two_id
              )
              OR
              (
                  r.sender_id = e.student_two_id
                  AND r.receiver_id = e.student_one_id
              )
          )
          AND (
              e.student_one_id = ?
              OR e.student_two_id = ?
          )
        ORDER BY e.id DESC
    ");

    $stmt->bind_param("ii", $userId, $userId);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($exchange = $result->fetch_assoc()) {
        $isStudentOne =
            (int)$exchange["student_one_id"] === $userId;

        if ($isStudentOne) {
            $partnerId = (int)$exchange["student_two_id"];
            $partnerName = $exchange["student_two_name"];
            $partnerEmail = $exchange["student_two_email"];
            $learningSkill = $exchange["skill"];
            $teachingSkill = $exchange["return_skill"];
        } else {
            $partnerId = (int)$exchange["student_one_id"];
            $partnerName = $exchange["student_one_name"];
            $partnerEmail = $exchange["student_one_email"];
            $learningSkill = $exchange["return_skill"];
            $teachingSkill = $exchange["skill"];
        }

        $exchangePartners[] = [
            "exchange_id" => (int)$exchange["id"],
            "partner_id" => $partnerId,
            "name" => $partnerName,
            "email" => $partnerEmail,
            "learning_skill" => $learningSkill,
            "teaching_skill" => $teachingSkill
        ];
    }

    $stmt->close();
    $activeExchanges = count($exchangePartners);

} catch (Throwable $e) {
    error_log("Dashboard exchanges error: " . $e->getMessage());
}

/* =========================================
   RECENT NOTIFICATIONS
========================================= */

try {
    $stmt = $conn->prepare("
        SELECT
            id, title, message, type,
            is_read, created_at
        FROM notifications
        WHERE user_id = ?
           OR user_email = ?
        ORDER BY created_at DESC, id DESC
        LIMIT 5
    ");

    $stmt->bind_param("is", $userId, $email);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $recentNotifications[] = $row;
    }

    $stmt->close();

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM notifications
        WHERE (user_id = ? OR user_email = ?)
          AND is_read = 0
    ");

    $stmt->bind_param("is", $userId, $email);
    $stmt->execute();

    $row = $stmt->get_result()->fetch_assoc();
    $unreadCount = (int)($row["total"] ?? 0);

    $stmt->close();

} catch (Throwable $e) {
    error_log("Dashboard notification error: " . $e->getMessage());
}

$initial = dashInitial($name);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Dashboard - SkillConnect</title>

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: Arial, Helvetica, sans-serif;
    background: #f5f9f6;
    color: #183c2b;
}

a {
    text-decoration: none;
}

button {
    font-family: inherit;
    cursor: pointer;
}

.main {
    max-width: 1350px;
    margin: 0 auto;
    padding: 0 24px 60px;
}

/* =========================================
   HERO
========================================= */

.hero {
    background: linear-gradient(120deg, #20774d, #185b3b);
    border-radius: 0 0 24px 24px;
    color: white;
    padding: 34px 42px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
}

.hero h1 {
    font-size: 34px;
    margin-bottom: 9px;
}

.hero p {
    font-size: 16px;
    opacity: .94;
    margin-bottom: 22px;
    line-height: 1.5;
}

.hero-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.hero-btn {
    display: inline-block;
    background: white;
    color: #17613f;
    padding: 12px 20px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: bold;
}

.hero-btn.secondary {
    background: rgba(255,255,255,.13);
    border: 1px solid rgba(255,255,255,.5);
    color: white;
}

.hero-btn:hover {
    opacity: .9;
}

/* =========================================
   DASHBOARD PROFILE PHOTO
========================================= */

.hero-avatar {
    width: 112px;
    height: 112px;
    min-width: 112px;
    border-radius: 50%;
    border: 3px solid rgba(255,255,255,.5);
    background: rgba(255,255,255,.12);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 50px;
    font-weight: bold;
    overflow: hidden;
}

.profile-avatar {
    width: 58px;
    height: 58px;
    min-width: 58px;
    border-radius: 50%;
    background: #dff3e7;
    color: #21804f;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    font-weight: bold;
    overflow: hidden;
}

.avatar-photo {
    width: 100%;
    height: 100%;
    display: block;
    object-fit: cover;
    border-radius: 50%;
}

.avatar-fallback {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
}

/* =========================================
   STATS
========================================= */

.stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 18px;
    margin-top: 25px;
}

.stat-card {
    background: white;
    border: 1px solid #e8f0ea;
    border-radius: 16px;
    padding: 22px;
    box-shadow: 0 5px 18px rgba(0,0,0,.045);
    transition: .2s;
}

.stat-card.clickable {
    cursor: pointer;
}

.stat-card.clickable:hover {
    transform: translateY(-4px);
    border-color: #8cc6a3;
    box-shadow: 0 10px 25px rgba(35,105,69,.12);
}

.stat-card:focus-visible {
    outline: 3px solid #3b7d5a;
    outline-offset: 3px;
}

.stat-icon {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    background: #e8f6ee;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 23px;
    margin-bottom: 13px;
}

.stat-number {
    font-size: 30px;
    font-weight: 800;
    color: #123b29;
}

.stat-title {
    font-size: 14px;
    color: #6b7770;
    margin-top: 5px;
}

.stat-hint {
    color: #278354;
    font-size: 12px;
    margin-top: 12px;
    font-weight: bold;
}

/* =========================================
   COMMON
========================================= */

.section {
    margin-top: 27px;
}

.section-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 15px;
}

.section-heading h2 {
    font-size: 22px;
    color: #19432d;
}

.section-heading p {
    color: #77827a;
    font-size: 13px;
    margin-top: 5px;
}

.view-link {
    color: #21804f;
    font-size: 13px;
    font-weight: bold;
}

.card {
    background: white;
    border: 1px solid #e8f0ea;
    border-radius: 17px;
    padding: 24px;
    box-shadow: 0 5px 18px rgba(0,0,0,.045);
}

/* =========================================
   QUICK ACTIONS
========================================= */

.quick-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
}

.quick-action {
    display: flex;
    align-items: center;
    gap: 13px;
    background: white;
    border: 1px solid #e7eee8;
    padding: 19px 16px;
    border-radius: 14px;
    transition: .2s;
    color: #1d4930;
}

.quick-action:hover {
    background: #edf8f1;
    transform: translateY(-2px);
}

.quick-icon {
    width: 43px;
    height: 43px;
    min-width: 43px;
    border-radius: 11px;
    background: #e6f5eb;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
}

.quick-action strong {
    display: block;
    font-size: 14px;
    margin-bottom: 5px;
}

.quick-action small {
    color: #7a8580;
    font-size: 12px;
}

/* =========================================
   PROFILE AND NOTIFICATIONS
========================================= */

.content-grid {
    display: grid;
    grid-template-columns: 1.6fr 1fr;
    gap: 22px;
    margin-top: 26px;
}

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 20px;
}

.card-header h2 {
    font-size: 20px;
}

.profile-top {
    display: flex;
    align-items: center;
    gap: 15px;
    padding-bottom: 20px;
    border-bottom: 1px solid #edf0ed;
}

.profile-info h3 {
    font-size: 19px;
    margin-bottom: 6px;
}

.profile-info p {
    font-size: 13px;
    color: #748077;
    margin-top: 5px;
}

.skills-section {
    margin-top: 20px;
}

.skills-section h3 {
    font-size: 15px;
    margin-bottom: 12px;
}

.skill-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.skill-tag {
    background: #eaf7ef;
    color: #197348;
    border-radius: 20px;
    padding: 8px 12px;
    font-size: 12px;
    font-weight: bold;
}

.empty-text {
    font-size: 13px;
    color: #849087;
}

/* =========================================
   NOTIFICATIONS
========================================= */

.notification-item {
    padding: 14px 0;
    border-bottom: 1px solid #edf0ed;
}

.notification-item:last-child {
    border-bottom: none;
}

.notification-item strong {
    font-size: 14px;
    display: block;
    margin-bottom: 6px;
}

.notification-item p {
    font-size: 12px;
    color: #738077;
    line-height: 1.6;
}

.notification-time {
    display: block;
    color: #9aa59d;
    font-size: 11px;
    margin-top: 7px;
}

.unread-dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #3b9c65;
    margin-right: 6px;
}

.empty-state {
    padding: 25px 12px;
    text-align: center;
    color: #829087;
    font-size: 13px;
    line-height: 1.7;
}

.empty-state .empty-icon {
    font-size: 35px;
    margin-bottom: 10px;
}

/* =========================================
   LEARNING RESOURCES
========================================= */

.resource-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
}

.resource-card {
    display: block;
    background: white;
    border: 1px solid #e7eee8;
    border-radius: 15px;
    padding: 22px;
    color: #214a32;
    transition: .2s;
}

.resource-card:hover {
    transform: translateY(-3px);
    background: #f2faf5;
    border-color: #a5d1b5;
}

.resource-icon {
    font-size: 29px;
    margin-bottom: 12px;
}

.resource-card h3 {
    font-size: 16px;
    margin-bottom: 7px;
}

.resource-card p {
    color: #79867c;
    font-size: 12px;
    line-height: 1.6;
}

/* =========================================
   ACTIVE EXCHANGES POPUP
========================================= */

.modal-overlay {
    position: fixed;
    inset: 0;
    z-index: 2000;
    background: rgba(10,32,20,.65);
    display: none;
    align-items: center;
    justify-content: center;
    padding: 18px;
}

.modal-overlay.show {
    display: flex;
}

.modal-box {
    width: 100%;
    max-width: 900px;
    max-height: 85vh;
    overflow-y: auto;
    background: white;
    border-radius: 20px;
    padding: 32px;
    box-shadow: 0 15px 45px rgba(0,0,0,.2);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 20px;
}

.modal-header h2 {
    color: #19432d;
    font-size: 25px;
}

.close-modal {
    border: 2px solid #183c2b;
    background: #eef5f0;
    color: #285b41;
    width: 46px;
    height: 46px;
    border-radius: 11px;
    font-size: 25px;
}

.modal-description {
    font-size: 14px;
    color: #748077;
    margin-bottom: 22px;
}

.partner-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.partner-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 13px;
    border: 1px solid #e9f0eb;
    border-radius: 12px;
    background: #fcfefd;
}

.partner-avatar {
    width: 45px;
    height: 45px;
    min-width: 45px;
    border-radius: 50%;
    background: #dff3e7;
    color: #21804f;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 19px;
    font-weight: bold;
}

.partner-info {
    flex: 1;
    min-width: 0;
}

.partner-info strong {
    display: block;
    font-size: 14px;
    margin-bottom: 5px;
}

.partner-info small {
    display: block;
    color: #748077;
    font-size: 12px;
    line-height: 1.5;
    overflow-wrap: anywhere;
}

.partner-actions {
    display: flex;
    gap: 7px;
    flex-wrap: wrap;
}

.chat-btn {
    display: inline-block;
    background: #3b7d5a;
    color: white;
    border: 0;
    border-radius: 9px;
    padding: 10px 13px;
    font-size: 12px;
    font-weight: bold;
}

.chat-btn:hover {
    background: #285b41;
}

.profile-btn {
    display: inline-block;
    background: #edf6f0;
    color: #286d47;
    border-radius: 9px;
    padding: 10px 12px;
    font-size: 12px;
    font-weight: bold;
}

/* =========================================
   RESPONSIVE
========================================= */

@media (max-width: 1000px) {
    .quick-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .content-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 750px) {
    .stats {
        grid-template-columns: repeat(2, 1fr);
    }

    .resource-grid {
        grid-template-columns: 1fr;
    }

    .hero {
        padding: 28px 22px;
    }

    .hero h1 {
        font-size: 27px;
    }

    .hero-avatar {
        display: none;
    }

    .main {
        padding: 0 14px 35px;
    }
}

@media (max-width: 480px) {
    .stats {
        gap: 10px;
    }

    .stat-card {
        padding: 16px;
    }

    .stat-number {
        font-size: 25px;
    }

    .quick-grid {
        gap: 10px;
    }

    .quick-action {
        padding: 13px 10px;
        gap: 8px;
    }

    .quick-icon {
        width: 34px;
        height: 34px;
        min-width: 34px;
        font-size: 18px;
    }

    .quick-action strong {
        font-size: 12px;
    }

    .quick-action small {
        font-size: 10px;
    }

    .card {
        padding: 18px;
    }

    .partner-item {
        flex-wrap: wrap;
    }

    .partner-actions {
        width: 100%;
    }

    .modal-box {
        padding: 18px;
    }
}
</style>
</head>

<body>

<?php include "common-navbar.php"; ?>

<main class="main">

    <!-- =====================================
         WELCOME BANNER WITH SAVED DP
    ====================================== -->

    <section class="hero">

        <div>
            <h1>
                Welcome, <?= dashEscape($name) ?> 👋
            </h1>

            <p>
                Ready to learn something new and share
                your skills with others?
            </p>

            <div class="hero-actions">
                <a href="students.php" class="hero-btn">
                    Find Students →
                </a>

                <a href="requests.php"
                   class="hero-btn secondary">
                    My Exchanges
                </a>
            </div>
        </div>

        <div class="hero-avatar">

            <?php if ($profileImage !== ""): ?>

                <img
                    src="<?= dashEscape($profileImage) ?>"
                    alt="My Profile Photo"
                    class="avatar-photo"
                    onerror="this.style.display='none';this.nextElementSibling.style.display='flex';"
                >

                <span class="avatar-fallback" style="display:none;">
                    <?= dashEscape($initial) ?>
                </span>

            <?php else: ?>

                <?= dashEscape($initial) ?>

            <?php endif; ?>

        </div>

    </section>

    <!-- =====================================
         DASHBOARD STATS
    ====================================== -->

    <section class="stats">

        <div class="stat-card">
            <div class="stat-icon">🎯</div>

            <div class="stat-number">
                <?= count($skillsToTeach) ?>
            </div>

            <div class="stat-title">
                Skills You Teach
            </div>

            <div class="stat-hint">
                Your teaching skills
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">📚</div>

            <div class="stat-number">
                <?= count($skillsToLearn) ?>
            </div>

            <div class="stat-title">
                Skills You Want
            </div>

            <div class="stat-hint">
                Your learning goals
            </div>
        </div>

        <div class="stat-card clickable"
             role="button"
             tabindex="0"
             onclick="openPartnerModal('exchanges')"
             onkeydown="handleCardKey(event,'exchanges')">

            <div class="stat-icon">🤝</div>

            <div class="stat-number">
                <?= $activeExchanges ?>
            </div>

            <div class="stat-title">
                Active Exchanges
            </div>

            <div class="stat-hint">
                View all connected students →
            </div>
        </div>

        <div class="stat-card clickable"
             role="button"
             tabindex="0"
             onclick="openPartnerModal('messages')"
             onkeydown="handleCardKey(event,'messages')">

            <div class="stat-icon">💬</div>

            <div class="stat-number">
                <?= $messageCount ?>
            </div>

            <div class="stat-title">
                Messages
            </div>

            <div class="stat-hint">
                Open your chats →
            </div>
        </div>

    </section>

    <!-- =====================================
         QUICK ACTIONS
    ====================================== -->

    <section class="section">

        <div class="section-heading">
            <div>
                <h2>⚡ Quick Actions</h2>
                <p>Everything you need in one place</p>
            </div>
        </div>

        <div class="quick-grid">

            <a href="students.php" class="quick-action">
                <span class="quick-icon">🔎</span>
                <span>
                    <strong>Find Students</strong>
                    <small>Explore skill partners</small>
                </span>
            </a>

            <a href="requests.php" class="quick-action">
                <span class="quick-icon">🤝</span>
                <span>
                    <strong>My Requests</strong>
                    <small>Manage exchanges</small>
                </span>
            </a>

            <a href="upload_video.php" class="quick-action">
                <span class="quick-icon">🎥</span>
                <span>
                    <strong>Upload Video</strong>
                    <small>Share tutorials</small>
                </span>
            </a>

            <a href="upload-notes.php" class="quick-action">
                <span class="quick-icon">📄</span>
                <span>
                    <strong>Upload Notes</strong>
                    <small>Share PDF notes</small>
                </span>
            </a>

        </div>

    </section>

    <!-- =====================================
         PROFILE AND NOTIFICATIONS
    ====================================== -->

    <section class="content-grid">

        <!-- MY PROFILE -->

        <div class="card">

            <div class="card-header">
                <h2>👤 My Profile</h2>

                <a href="profile.php" class="view-link">
                    View Profile →
                </a>
            </div>

            <div class="profile-top">

                <div class="profile-avatar">

                    <?php if ($profileImage !== ""): ?>

                        <img
                            src="<?= dashEscape($profileImage) ?>"
                            alt="My Profile Photo"
                            class="avatar-photo"
                            onerror="this.style.display='none';this.nextElementSibling.style.display='flex';"
                        >

                        <span class="avatar-fallback" style="display:none;">
                            <?= dashEscape($initial) ?>
                        </span>

                    <?php else: ?>

                        <?= dashEscape($initial) ?>

                    <?php endif; ?>

                </div>

                <div class="profile-info">
                    <h3><?= dashEscape($name) ?></h3>
                    <p>🎓 <?= dashEscape($course) ?></p>
                    <p>🏫 <?= dashEscape($college) ?></p>
                </div>

            </div>

            <div class="skills-section">
                <h3>💡 Skills I Can Teach</h3>

                <div class="skill-tags">

                    <?php if ($skillsToTeach): ?>

                        <?php foreach ($skillsToTeach as $skill): ?>
                            <span class="skill-tag">
                                <?= dashEscape($skill) ?>
                            </span>
                        <?php endforeach; ?>

                    <?php else: ?>

                        <span class="empty-text">
                            No teaching skills added yet.
                        </span>

                    <?php endif; ?>

                </div>
            </div>

            <div class="skills-section">
                <h3>📚 Skills I Want To Learn</h3>

                <div class="skill-tags">

                    <?php if ($skillsToLearn): ?>

                        <?php foreach ($skillsToLearn as $skill): ?>
                            <span class="skill-tag">
                                <?= dashEscape($skill) ?>
                            </span>
                        <?php endforeach; ?>

                    <?php else: ?>

                        <span class="empty-text">
                            No learning skills added yet.
                        </span>

                    <?php endif; ?>

                </div>
            </div>

        </div>

        <!-- RECENT NOTIFICATIONS -->

        <div class="card">

            <div class="card-header">
                <h2>🔔 Recent Notifications</h2>

                <a href="notifications.php" class="view-link">
                    View All →
                </a>
            </div>

            <?php if ($unreadCount > 0): ?>

                <p style="color:#278354;font-size:13px;margin-bottom:12px;">
                    <?= $unreadCount ?> unread notification(s)
                </p>

            <?php endif; ?>

            <?php if (empty($recentNotifications)): ?>

                <div class="empty-state">
                    <div class="empty-icon">🔔</div>
                    No notifications yet.
                </div>

            <?php else: ?>

                <?php foreach ($recentNotifications as $notification): ?>

                    <div class="notification-item">

                        <strong>

                            <?php if (!(int)$notification["is_read"]): ?>
                                <span class="unread-dot"></span>
                            <?php endif; ?>

                            <?= dashEscape($notification["title"]) ?>

                        </strong>

                        <p>
                            <?= dashEscape($notification["message"]) ?>
                        </p>

                        <span class="notification-time">
                            <?= dashEscape($notification["created_at"]) ?>
                        </span>

                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

    </section>

    <!-- =====================================
         LEARNING RESOURCES
    ====================================== -->

    <section class="section">

        <div class="section-heading">
            <div>
                <h2>📚 Learning Resources</h2>
                <p>Access and share learning materials</p>
            </div>
        </div>

        <div class="resource-grid">

            <a href="resources.php" class="resource-card">
                <div class="resource-icon">📖</div>
                <h3>Browse Resources</h3>
                <p>
                    Explore available learning videos and notes.
                </p>
            </a>

            <a href="upload_video.php" class="resource-card">
                <div class="resource-icon">🎬</div>
                <h3>Share Video</h3>
                <p>
                    Upload video tutorials for your connected learners.
                </p>
            </a>

            <a href="upload-notes.php" class="resource-card">
                <div class="resource-icon">📄</div>
                <h3>Share Notes</h3>
                <p>
                    Upload PDF notes and study materials.
                </p>
            </a>

        </div>

    </section>

</main>

<!-- =========================================
     ACTIVE EXCHANGES POPUP
========================================= -->

<div class="modal-overlay"
     id="partnersModal"
     role="dialog"
     aria-modal="true"
     aria-labelledby="modalTitle"
     aria-hidden="true">

    <div class="modal-box">

        <div class="modal-header">

            <h2 id="modalTitle">
                🤝 My Active Exchanges
            </h2>

            <button
                type="button"
                class="close-modal"
                onclick="closePartnerModal()"
                aria-label="Close">
                ×
            </button>

        </div>

        <p class="modal-description" id="modalDescription">
            Your connected skill exchange partners.
        </p>

        <?php if (empty($exchangePartners)): ?>

            <div class="empty-state">
                <div class="empty-icon">🤝</div>

                <p>No active connected students found.</p>

                <a href="students.php" class="view-link">
                    Find Students →
                </a>
            </div>

        <?php else: ?>

            <div class="partner-list">

                <?php foreach ($exchangePartners as $partner): ?>

                    <div class="partner-item">

                        <div class="partner-avatar">
                            <?= dashEscape(
                                dashInitial($partner["name"])
                            ) ?>
                        </div>

                        <div class="partner-info">

                            <strong>
                                <?= dashEscape($partner["name"]) ?>
                            </strong>

                            <small>
                                <?= dashEscape($partner["email"]) ?>
                            </small>

                            <small>
                                You learn:
                                <?= dashEscape(
                                    $partner["learning_skill"]
                                    ?: "Not selected"
                                ) ?>
                            </small>

                            <small>
                                You teach:
                                <?= dashEscape(
                                    $partner["teaching_skill"]
                                    ?: "Not selected"
                                ) ?>
                            </small>

                        </div>

                        <div class="partner-actions">

                            <a
                                href="student-profile.php?id=<?= (int)$partner["partner_id"] ?>"
                                class="profile-btn">
                                Profile
                            </a>

                            <a
                                href="chat.php?exchange_id=<?= (int)$partner["exchange_id"] ?>"
                                class="chat-btn">
                                💬 Open Chat
                            </a>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </div>
</div>

<script>

/* =========================================
   OPEN POPUP
========================================= */

function openPartnerModal(type) {

    const modal =
        document.getElementById("partnersModal");

    const title =
        document.getElementById("modalTitle");

    const description =
        document.getElementById("modalDescription");

    if (!modal) {
        return;
    }

    if (type === "messages") {

        title.textContent = "💬 My Chats";

        description.textContent =
            "Select an active skill partner to open your conversation.";

    } else {

        title.textContent = "🤝 My Active Exchanges";

        description.textContent =
            "All students currently connected with you for skill exchange.";
    }

    modal.classList.add("show");

    modal.setAttribute("aria-hidden", "false");

    document.body.style.overflow = "hidden";

    const closeButton =
        modal.querySelector(".close-modal");

    if (closeButton) {
        closeButton.focus();
    }
}

/* =========================================
   CLOSE POPUP
========================================= */

function closePartnerModal() {

    const modal =
        document.getElementById("partnersModal");

    if (!modal) {
        return;
    }

    modal.classList.remove("show");

    modal.setAttribute("aria-hidden", "true");

    document.body.style.overflow = "";
}

/* =========================================
   KEYBOARD SUPPORT
========================================= */

function handleCardKey(event, type) {

    if (event.key === "Enter" || event.key === " ") {

        event.preventDefault();

        openPartnerModal(type);
    }
}

/* =========================================
   CLICK OUTSIDE TO CLOSE
========================================= */

document.getElementById("partnersModal")
    .addEventListener("click", function(event) {

        if (event.target === this) {
            closePartnerModal();
        }
    });

/* =========================================
   ESCAPE TO CLOSE
========================================= */

document.addEventListener("keydown", function(event) {

    if (event.key === "Escape") {
        closePartnerModal();
    }
});

</script>

</body>
</html>
