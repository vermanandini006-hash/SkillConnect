
<?php
session_start();
require "db.php";

if (!isset($_SESSION["user"]["id"])) {
    header("Location: login.php");
    exit();
}

$currentUserId = (int)$_SESSION["user"]["id"];

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function parseSkills($raw) {
    if (empty($raw)) return [];

    $decoded = json_decode((string)$raw, true);
    $items = is_array($decoded)
        ? $decoded
        : explode(",", (string)$raw);

    $skills = [];

    foreach ($items as $item) {
        if (!is_scalar($item)) continue;

        $skill = trim((string)$item);

        if ($skill !== "") {
            $skills[] = $skill;
        }
    }

    return array_values(array_unique($skills));
}

function getTeachingSkills($conn, $userId) {
    $stmt = $conn->prepare(
        "SELECT skills_to_teach FROM users WHERE id = ? LIMIT 1"
    );
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return parseSkills($user["skills_to_teach"] ?? "");
}

function jsonResponse($success, $message, $extra = []) {
    header("Content-Type: application/json; charset=utf-8");

    echo json_encode(array_merge([
        "success" => $success,
        "message" => $message
    ], $extra));

    exit();
}

/* ========================================
   UPDATE EXCHANGE SKILL
======================================== */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    ($_POST["action"] ?? "") === "update_exchange_skill"
) {
    $exchangeId = (int)($_POST["exchange_id"] ?? 0);
    $newSkill = trim($_POST["skill"] ?? "");

    if ($exchangeId <= 0 || $newSkill === "") {
        jsonResponse(false, "Please select a skill.");
    }

    $stmt = $conn->prepare("
        SELECT
            e.id,
            e.request_id,
            e.student_one_id,
            e.student_two_id,
            e.skill,
            e.return_skill
        FROM exchanges e
        INNER JOIN exchange_requests r
            ON r.id = e.request_id
            AND r.sender_id = e.student_one_id
            AND r.receiver_id = e.student_two_id
        WHERE e.id = ?
          AND e.status = 'active'
          AND r.status = 'accepted'
          AND (
              e.student_one_id = ?
              OR e.student_two_id = ?
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

    if (!$exchange) {
        jsonResponse(
            false,
            "Exchange is not active or request is not accepted."
        );
    }

    /*
       Student one = request sender
       Student two = request receiver

       Sender learns exchange.skill from receiver.
       Receiver learns exchange.return_skill from sender.
    */

    $isSender =
        (int)$exchange["student_one_id"] === $currentUserId;

    $partnerId = $isSender
        ? (int)$exchange["student_two_id"]
        : (int)$exchange["student_one_id"];

    $availableSkills = getTeachingSkills($conn, $partnerId);

    $selectedSkill = null;

    foreach ($availableSkills as $skill) {
        if (strcasecmp($skill, $newSkill) === 0) {
            $selectedSkill = $skill;
            break;
        }
    }

    if ($selectedSkill === null) {
        jsonResponse(
            false,
            "This skill is not available in your partner's teaching skills."
        );
    }

    try {
        $conn->begin_transaction();

        if ($isSender) {
            $stmt = $conn->prepare("
                UPDATE exchanges
                SET skill = ?
                WHERE id = ?
                  AND status = 'active'
            ");

            $stmt->bind_param(
                "si",
                $selectedSkill,
                $exchangeId
            );
            $stmt->execute();
            $stmt->close();

            $requestId = (int)$exchange["request_id"];

            $stmt = $conn->prepare("
                UPDATE exchange_requests
                SET skill = ?
                WHERE id = ?
                  AND status = 'accepted'
            ");

            $stmt->bind_param(
                "si",
                $selectedSkill,
                $requestId
            );
            $stmt->execute();
            $stmt->close();

        } else {
            $stmt = $conn->prepare("
                UPDATE exchanges
                SET return_skill = ?
                WHERE id = ?
                  AND status = 'active'
            ");

            $stmt->bind_param(
                "si",
                $selectedSkill,
                $exchangeId
            );
            $stmt->execute();
            $stmt->close();
        }

        $conn->commit();

        jsonResponse(
            true,
            "Skill updated successfully.",
            ["skill" => $selectedSkill]
        );

    } catch (Throwable $error) {
        $conn->rollback();
        jsonResponse(false, "Unable to update skill.");
    }
}

/* ========================================
   FETCH ACCEPTED + ACTIVE EXCHANGES ONLY
======================================== */

$activeExchanges = [];

$stmt = $conn->prepare("
    SELECT
        e.id,
        e.request_id,
        e.student_one_id,
        e.student_one_name,
        e.student_one_email,
        e.student_two_id,
        e.student_two_name,
        e.student_two_email,
        e.skill,
        e.return_skill,
        e.status,
        e.created_at
    FROM exchanges e
    INNER JOIN exchange_requests r
        ON r.id = e.request_id
        AND r.sender_id = e.student_one_id
        AND r.receiver_id = e.student_two_id
    WHERE e.status = 'active'
      AND r.status = 'accepted'
      AND (
          e.student_one_id = ?
          OR e.student_two_id = ?
      )
    ORDER BY e.created_at DESC, e.id DESC
");

$stmt->bind_param(
    "ii",
    $currentUserId,
    $currentUserId
);

$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {

    $isSender =
        (int)$row["student_one_id"] === $currentUserId;

    if ($isSender) {
        $partnerId = (int)$row["student_two_id"];
        $partnerName = $row["student_two_name"];
        $partnerEmail = $row["student_two_email"];

        $learningSkill = $row["skill"];
        $teachingSkill = $row["return_skill"];

    } else {
        $partnerId = (int)$row["student_one_id"];
        $partnerName = $row["student_one_name"];
        $partnerEmail = $row["student_one_email"];

        $learningSkill = $row["return_skill"];
        $teachingSkill = $row["skill"];
    }

    $row["partner_id"] = $partnerId;
    $row["partner_name"] = $partnerName;
    $row["partner_email"] = $partnerEmail;
    $row["learning_skill"] = $learningSkill;
    $row["teaching_skill"] = $teachingSkill;
    $row["available_skills"] =
        getTeachingSkills($conn, $partnerId);

    $activeExchanges[] = $row;
}

$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Skill Exchanges - SkillConnect</title>

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: Arial, sans-serif;
    background: linear-gradient(135deg, #f5faf7, #eef8f2);
    min-height: 100vh;
    color: #285b41;
}

.exchange-container {
    max-width: 1250px;
    margin: 95px auto 50px;
    padding: 20px;
}

.page-title {
    text-align: center;
    font-size: 35px;
    color: #285b41;
    margin-bottom: 12px;
}

.page-subtitle {
    text-align: center;
    color: #6b7d72;
    margin-bottom: 38px;
    font-size: 16px;
}

.exchange-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 25px;
}

.exchange-card {
    background: white;
    border-radius: 20px;
    padding: 24px;
    border: 1px solid #e1eee6;
    box-shadow: 0 10px 30px rgba(40,91,65,.10);
    display: flex;
    flex-direction: column;
    gap: 20px;
    transition: .3s;
    min-width: 0;
}

.exchange-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 15px 35px rgba(40,91,65,.15);
}

.student-section {
    display: flex;
    align-items: center;
    gap: 15px;
}

.student-avatar {
    width: 65px;
    height: 65px;
    border-radius: 50%;
    background: #3b7d5a;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 25px;
    font-weight: bold;
    flex-shrink: 0;
}

.student-info {
    min-width: 0;
}

.student-info h3 {
    color: #285b41;
    font-size: 19px;
    margin-bottom: 5px;
    overflow-wrap: anywhere;
}

.student-info p {
    color: #78877f;
    font-size: 12px;
    overflow-wrap: anywhere;
}

.status {
    display: inline-block;
    margin-top: 8px;
    font-size: 12px;
    color: #3b7d5a;
}

.skill-details {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.skill-box {
    background: #f4faf6;
    border: 1px solid #dcece2;
    border-radius: 14px;
    padding: 15px;
}

.skill-box.learning {
    border-left: 4px solid #3b7d5a;
}

.skill-box.teaching {
    border-left: 4px solid #8bbca1;
}

.skill-label {
    font-size: 12px;
    color: #718178;
    margin-bottom: 7px;
    font-weight: bold;
}

.skill-name {
    font-size: 20px;
    font-weight: bold;
    color: #285b41;
    overflow-wrap: anywhere;
}

.skill-description {
    color: #718178;
    font-size: 12px;
    margin-top: 7px;
    line-height: 1.5;
    overflow-wrap: anywhere;
}

.edit-skill-btn {
    border: none;
    background: #3b7d5a;
    color: white;
    padding: 8px 12px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 12px;
    font-weight: bold;
    margin-top: 12px;
}

.edit-skill-btn:hover {
    background: #285b41;
}

.actions {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 9px;
    margin-top: auto;
}

.actions a {
    text-decoration: none;
    padding: 11px 7px;
    border-radius: 9px;
    font-size: 13px;
    font-weight: 600;
    background: #edf6f0;
    color: #285b41;
    text-align: center;
    border: 1px solid #e0eee5;
    transition: .2s;
}

.actions a:hover {
    background: #dcece2;
    transform: translateY(-1px);
}

.empty-box {
    background: white;
    border-radius: 20px;
    padding: 60px 20px;
    text-align: center;
    box-shadow: 0 10px 30px rgba(40,91,65,.08);
}

.empty-box h3 {
    margin-bottom: 12px;
}

.empty-box p {
    color: #77857d;
    line-height: 1.6;
}

.empty-box a {
    display: inline-block;
    margin-top: 20px;
    background: #3b7d5a;
    color: white;
    padding: 11px 20px;
    border-radius: 9px;
    text-decoration: none;
}

.modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(20,45,32,.55);
    z-index: 9999;
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
    max-width: 430px;
    border-radius: 20px;
    padding: 28px;
    box-shadow: 0 20px 50px rgba(0,0,0,.2);
}

.modal-box h2 {
    color: #285b41;
    margin-bottom: 8px;
}

.modal-box p {
    color: #77857d;
    font-size: 14px;
    line-height: 1.6;
    margin-bottom: 20px;
}

.modal-box label {
    display: block;
    margin-bottom: 8px;
    font-weight: bold;
}

.modal-box select {
    width: 100%;
    padding: 12px;
    border-radius: 10px;
    border: 1px solid #cfe1d5;
    font-size: 15px;
    margin-bottom: 20px;
    background: white;
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.modal-actions button {
    border: none;
    padding: 11px 18px;
    border-radius: 9px;
    cursor: pointer;
    font-weight: bold;
}

.cancel-btn {
    background: #edf2ee;
    color: #4c5e53;
}

.save-btn {
    background: #3b7d5a;
    color: white;
}

.save-btn:disabled {
    opacity: .6;
    cursor: wait;
}

.toast {
    position: fixed;
    right: 25px;
    bottom: 25px;
    background: #285b41;
    color: white;
    padding: 13px 20px;
    border-radius: 10px;
    display: none;
    z-index: 10000;
    max-width: 90vw;
}

.toast.show {
    display: block;
}

@media(max-width: 1000px) {
    .exchange-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media(max-width: 650px) {
    .exchange-container {
        margin-top: 65px;
        padding: 14px;
    }

    .exchange-grid {
        grid-template-columns: 1fr;
    }

    .page-title {
        font-size: 28px;
    }

    .exchange-card {
        padding: 20px;
    }
}

@media(max-width: 380px) {
    .actions {
        grid-template-columns: 1fr;
    }
}
</style>
</head>

<body>

<?php require "common-navbar.php"; ?>

<div class="exchange-container">

    <h1 class="page-title">My Skill Exchanges</h1>

    <p class="page-subtitle">
        Learn and teach skills with your accepted exchange partners
    </p>

    <?php if (empty($activeExchanges)): ?>

        <div class="empty-box">
            <h3>No Active Exchanges</h3>

            <p>
                Only accepted and active skill exchange
                connections appear here.
                Pending requests will not be shown.
            </p>

            <a href="requests.php">View My Requests</a>
        </div>

    <?php else: ?>

        <div class="exchange-grid">

        <?php foreach ($activeExchanges as $exchange): ?>

            <?php
            $exchangeId = (int)$exchange["id"];
            $partnerId = (int)$exchange["partner_id"];
            $partnerName = $exchange["partner_name"];
            $partnerEmail = $exchange["partner_email"];

            $learningSkill =
                trim((string)($exchange["learning_skill"] ?? ""));

            $teachingSkill =
                trim((string)($exchange["teaching_skill"] ?? ""));

            $availableSkills = $exchange["available_skills"];

            $initial = strtoupper(substr($partnerName, 0, 1));
            ?>

            <div class="exchange-card"
                 id="exchange-card-<?= $exchangeId ?>">

                <!-- PARTNER -->
                <div class="student-section">

                    <div class="student-avatar">
                        <?= e($initial) ?>
                    </div>

                    <div class="student-info">

                        <h3><?= e($partnerName) ?></h3>

                        <p><?= e($partnerEmail) ?></p>

                        <span class="status">
                            ● Active Exchange
                        </span>

                    </div>
                </div>

                <!-- LEARNING AND TEACHING -->
                <div class="skill-details">

                    <div class="skill-box learning">

                        <div class="skill-label">
                            📚 YOU ARE LEARNING
                        </div>

                        <div class="skill-name"
                             id="skill-name-<?= $exchangeId ?>">

                            <?= e(
                                $learningSkill !== ""
                                    ? $learningSkill
                                    : "Not specified"
                            ) ?>

                        </div>

                        <div class="skill-description">
                            <?= e($partnerName) ?> is teaching you
                            this skill.
                        </div>

                        <?php if (!empty($availableSkills)): ?>

                            <button
                                type="button"
                                class="edit-skill-btn"
                                onclick='openSkillModal(
                                    <?= $exchangeId ?>,
                                    <?= json_encode(
                                        $learningSkill,
                                        JSON_HEX_TAG |
                                        JSON_HEX_APOS |
                                        JSON_HEX_QUOT |
                                        JSON_HEX_AMP
                                    ) ?>,
                                    <?= json_encode(
                                        $availableSkills,
                                        JSON_HEX_TAG |
                                        JSON_HEX_APOS |
                                        JSON_HEX_QUOT |
                                        JSON_HEX_AMP
                                    ) ?>
                                )'
                            >
                                ✏️ Edit Learning Skill
                            </button>

                        <?php endif; ?>

                    </div>

                    <div class="skill-box teaching">

                        <div class="skill-label">
                            🎓 YOU ARE TEACHING
                        </div>

                        <div class="skill-name">

                            <?= e(
                                $teachingSkill !== ""
                                    ? $teachingSkill
                                    : "Not specified"
                            ) ?>

                        </div>

                        <div class="skill-description">
                            <?= e($partnerName) ?> is learning
                            this skill from you.
                        </div>

                    </div>

                </div>

                <!-- EXISTING BUTTONS -->
                <div class="actions">

                    <a href="chat.php?exchange_id=<?= $exchangeId ?>">
                        💬 Chat
                    </a>

                    <a href="resources.php?exchange_id=<?= $exchangeId ?>">
                        📚 Resources
                    </a>

                    <a href="upload_video.php?exchange_id=<?= $exchangeId ?>">
                        🎥 Video
                    </a>

                    <a href="upload-notes.php?exchange_id=<?= $exchangeId ?>">
                        📝 Notes
                    </a>

                    <a href="video_call.php?exchange_id=<?= $exchangeId ?>"
                       class="video-call-btn">
                        📹 Video Call
                    </a>

                    <a href="student-profile.php?id=<?= $partnerId ?>">
                        👤 Profile
                    </a>

                </div>

            </div>

        <?php endforeach; ?>

        </div>

    <?php endif; ?>

</div>

<!-- EDIT SKILL MODAL -->
<div class="modal" id="skillModal">

    <div class="modal-box">

        <h2>Edit Learning Skill</h2>

        <p>
            Select the skill you want to learn from
            your exchange partner.
        </p>

        <label for="skillSelect">
            Select Skill
        </label>

        <select id="skillSelect">
            <option value="">Select a skill</option>
        </select>

        <div class="modal-actions">

            <button
                type="button"
                class="cancel-btn"
                onclick="closeSkillModal()"
            >
                Cancel
            </button>

            <button
                type="button"
                class="save-btn"
                id="saveSkillButton"
                onclick="saveExchangeSkill()"
            >
                Save Skill
            </button>

        </div>

    </div>

</div>

<div class="toast" id="toast"></div>

<script>
let selectedExchangeId = 0;
let toastTimeout = null;

/* OPEN EDIT MODAL */
function openSkillModal(exchangeId, currentSkill, skills) {

    selectedExchangeId = exchangeId;

    const select = document.getElementById("skillSelect");

    select.innerHTML = "";

    const placeholder = document.createElement("option");
    placeholder.value = "";
    placeholder.textContent = "Select a skill";
    select.appendChild(placeholder);

    skills.forEach(function(skill) {

        const option = document.createElement("option");

        option.value = skill;
        option.textContent = skill;

        if (
            skill.toLowerCase().trim() ===
            String(currentSkill).toLowerCase().trim()
        ) {
            option.selected = true;
        }

        select.appendChild(option);
    });

    document.getElementById("skillModal")
        .classList.add("show");
}

/* CLOSE EDIT MODAL */
function closeSkillModal() {

    document.getElementById("skillModal")
        .classList.remove("show");

    selectedExchangeId = 0;
}

/* SAVE LEARNING SKILL */
async function saveExchangeSkill() {

    const skill = document.getElementById("skillSelect")
        .value.trim();

    if (!skill) {
        showToast("Please select a skill.");
        return;
    }

    if (!selectedExchangeId) {
        showToast("Invalid exchange.");
        return;
    }

    const saveButton =
        document.getElementById("saveSkillButton");

    saveButton.disabled = true;

    const formData = new FormData();

    formData.append("action", "update_exchange_skill");
    formData.append("exchange_id", selectedExchangeId);
    formData.append("skill", skill);

    try {

        const response = await fetch("exchange.php", {
            method: "POST",
            body: formData
        });

        const data = await response.json();

        if (data.success) {

            const skillElement = document.getElementById(
                "skill-name-" + selectedExchangeId
            );

            if (skillElement) {
                skillElement.textContent = data.skill;
            }

            closeSkillModal();

            showToast("Learning skill updated successfully!");

        } else {
            showToast(
                data.message || "Unable to update skill."
            );
        }

    } catch (error) {
        console.error(error);
        showToast("Something went wrong.");
    } finally {
        saveButton.disabled = false;
    }
}

/* TOAST */
function showToast(message) {

    const toast = document.getElementById("toast");

    toast.textContent = message;
    toast.classList.add("show");

    if (toastTimeout) {
        clearTimeout(toastTimeout);
    }

    toastTimeout = setTimeout(function() {
        toast.classList.remove("show");
    }, 3000);
}

/* CLOSE MODAL ON OUTSIDE CLICK */
document.getElementById("skillModal")
    .addEventListener("click", function(event) {

        if (event.target === this) {
            closeSkillModal();
        }
    });

/* ESCAPE KEY */
document.addEventListener("keydown", function(event) {

    if (event.key === "Escape") {
        closeSkillModal();
    }
});
</script>

</body>
</html>
