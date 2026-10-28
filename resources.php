<?php
session_start();

if (!isset($_SESSION["user"])) {
    header("Location: login.php");
    exit();
}

require "db.php";

$currentUser = $_SESSION["user"];
$currentUserId = (int)($currentUser["id"] ?? 0);

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

/* Active exchanges */
$exchanges = [];

$stmt = $conn->prepare("
    SELECT id, student_one_id, student_one_name,
           student_two_id, student_two_name, skill
    FROM exchanges
    WHERE (student_one_id = ? OR student_two_id = ?)
      AND status = 'active'
    ORDER BY id DESC
");

$stmt->bind_param("ii", $currentUserId, $currentUserId);
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

    $exchanges[] = [
        "id" => (int)$row["id"],
        "partner_id" => $partnerId,
        "partner_name" => $partnerName,
        "skill" => trim($row["skill"])
    ];
}
$stmt->close();

/* Select exchange */
$exchangeId = isset($_GET["exchange_id"])
    ? (int)$_GET["exchange_id"]
    : 0;

$selected = null;

foreach ($exchanges as $exchange) {
    if ($exchange["id"] === $exchangeId) {
        $selected = $exchange;
        break;
    }
}

if ($selected === null && !empty($exchanges)) {
    $selected = $exchanges[0];
}

/* Fetch resources */
$videos = [];
$notes = [];

if ($selected !== null) {
    $partnerId = $selected["partner_id"];
    $skill = $selected["skill"];

    $stmt = $conn->prepare("
        SELECT id, skill, uploader_id, uploader_name,
               title, resource_type, file_name, file_path
        FROM resources
        WHERE uploader_id IN (?, ?)
          AND LOWER(TRIM(skill)) = LOWER(TRIM(?))
          AND LOWER(TRIM(resource_type)) = 'video'
        ORDER BY id DESC
    ");

    $stmt->bind_param("iis", $currentUserId, $partnerId, $skill);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $videos[] = $row;
    }
    $stmt->close();

    $stmt = $conn->prepare("
        SELECT id, skill, uploader_id, uploader_name,
               title, resource_type, file_name, file_path
        FROM resources
        WHERE uploader_id IN (?, ?)
          AND LOWER(TRIM(skill)) = LOWER(TRIM(?))
          AND LOWER(TRIM(resource_type)) IN ('note', 'notes', 'pdf')
        ORDER BY id DESC
    ");

    $stmt->bind_param("iis", $currentUserId, $partnerId, $skill);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $notes[] = $row;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Learning Resources - SkillConnect</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f6faf7;
    color: #222;
}

.container {
    max-width: 1150px;
    margin: auto;
    padding: 30px 20px;
}

h1, h2, h3 {
    color: #285b41;
}

.exchange-list {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin: 20px 0;
}

.exchange-card {
    background: white;
    border: 1px solid #dce9e0;
    border-radius: 10px;
    padding: 14px;
    cursor: pointer;
    text-decoration: none;
    color: #285b41;
}

.exchange-card.active {
    border: 2px solid #3b7d5a;
    background: #eaf5ee;
}

.section {
    background: white;
    padding: 22px;
    margin: 22px 0;
    border-radius: 14px;
    box-shadow: 0 3px 14px #0000000d;
}

.resource-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 16px;
}

.resource-card {
    border: 1px solid #dce9e0;
    padding: 18px;
    border-radius: 12px;
    overflow-wrap: anywhere;
}

.resource-card h3 {
    margin-top: 0;
}

.actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 15px;
}

.btn {
    display: inline-block;
    border: 0;
    border-radius: 7px;
    padding: 9px 12px;
    text-decoration: none;
    cursor: pointer;
    font-size: 13px;
    font-weight: bold;
}

.view {
    color: #285b41;
    background: #e5f2ea;
}

.download {
    color: white;
    background: #3b7d5a;
}

.delete {
    color: #b42318;
    background: #ffeded;
}

.empty {
    padding: 25px;
    text-align: center;
    color: #68756c;
    background: #f9fcfa;
    border: 1px dashed #cbdcd1;
    border-radius: 10px;
}
</style>
</head>

<body>

<?php
/* Existing shared navbar — keep its own design and options */
if (file_exists("common-navbar.php")) {
    include "common-navbar.php";
}
?>

<div class="container">

    <h1>📚 Learning Resources</h1>
    <p>View and manage videos and notes shared in your active exchanges.</p>

    <?php if (empty($exchanges)): ?>

        <div class="empty">
            No active skill exchanges found.
        </div>

    <?php else: ?>

        <h2>Your Active Exchanges</h2>

        <div class="exchange-list">
            <?php foreach ($exchanges as $exchange): ?>
                <a
                    class="exchange-card <?= $selected["id"] === $exchange["id"] ? "active" : "" ?>"
                    href="resources.php?exchange_id=<?= $exchange["id"] ?>"
                >
                    <strong><?= e($exchange["partner_name"]) ?></strong><br>
                    Skill: <?= e($exchange["skill"]) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <h2><?= e($selected["skill"]) ?> Resources</h2>
        <p>Exchange with <?= e($selected["partner_name"]) ?></p>

        <!-- Videos -->
        <div class="section">
            <h2>🎥 Videos (<?= count($videos) ?>)</h2>

            <?php if (empty($videos)): ?>
                <div class="empty">No videos found for this skill.</div>
            <?php else: ?>
                <div class="resource-grid">
                    <?php foreach ($videos as $video): ?>
                        <div class="resource-card">
                            <h3><?= e($video["title"]) ?></h3>
                            <p>File: <?= e($video["file_name"]) ?></p>
                            <p>
                                Uploaded by:
                                <?= e($video["uploader_name"]) ?>
                                <?php if ((int)$video["uploader_id"] === $currentUserId): ?>
                                    <strong>(You)</strong>
                                <?php endif; ?>
                            </p>

                            <div class="actions">
                                <a
                                    class="btn view"
                                    href="<?= e($video["file_path"]) ?>"
                                    target="_blank"
                                    rel="noopener"
                                >▶ View</a>

                                <a
                                    class="btn download"
                                    href="<?= e($video["file_path"]) ?>"
                                    download="<?= e($video["file_name"]) ?>"
                                >⬇ Download</a>

                                <?php if ((int)$video["uploader_id"] === $currentUserId): ?>
                                    <form method="POST" action="delete-resource.php"
                                          onsubmit="return confirm('Delete this video?');">
                                        <input type="hidden" name="resource_id"
                                               value="<?= (int)$video["id"] ?>">
                                        <input type="hidden" name="exchange_id"
                                               value="<?= (int)$selected["id"] ?>">
                                        <button class="btn delete" type="submit">
                                            🗑 Delete
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Notes -->
        <div class="section">
            <h2>📄 Notes / PDF (<?= count($notes) ?>)</h2>

            <?php if (empty($notes)): ?>
                <div class="empty">No notes or PDFs found for this skill.</div>
            <?php else: ?>
                <div class="resource-grid">
                    <?php foreach ($notes as $note): ?>
                        <div class="resource-card">
                            <h3><?= e($note["title"]) ?></h3>
                            <p>File: <?= e($note["file_name"]) ?></p>
                            <p>
                                Uploaded by:
                                <?= e($note["uploader_name"]) ?>
                                <?php if ((int)$note["uploader_id"] === $currentUserId): ?>
                                    <strong>(You)</strong>
                                <?php endif; ?>
                            </p>

                            <div class="actions">
                                <a
                                    class="btn view"
                                    href="<?= e($note["file_path"]) ?>"
                                    target="_blank"
                                    rel="noopener"
                                >👁 View PDF</a>

                                <a
                                    class="btn download"
                                    href="<?= e($note["file_path"]) ?>"
                                    download="<?= e($note["file_name"]) ?>"
                                >⬇ Download</a>

                                <?php if ((int)$note["uploader_id"] === $currentUserId): ?>
                                    <form method="POST" action="delete-resource.php"
                                          onsubmit="return confirm('Delete these notes?');">
                                        <input type="hidden" name="resource_id"
                                               value="<?= (int)$note["id"] ?>">
                                        <input type="hidden" name="exchange_id"
                                               value="<?= (int)$selected["id"] ?>">
                                        <button class="btn delete" type="submit">
                                            🗑 Delete
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    <?php endif; ?>

</div>
</body>
</html>