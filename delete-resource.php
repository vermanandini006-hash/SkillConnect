<?php
session_start();

if (!isset($_SESSION["user"])) {
    header("Location: login.php");
    exit();
}

require "db.php";

$currentUserId = (int)($_SESSION["user"]["id"] ?? 0);
$resourceId = (int)($_POST["resource_id"] ?? 0);
$exchangeId = (int)($_POST["exchange_id"] ?? 0);

if ($_SERVER["REQUEST_METHOD"] !== "POST" ||
    $currentUserId <= 0 ||
    $resourceId <= 0) {
    die("Invalid request.");
}

/* Get resource only if current user owns it */
$stmt = $conn->prepare("
    SELECT file_path
    FROM resources
    WHERE id = ? AND uploader_id = ?
    LIMIT 1
");

$stmt->bind_param("ii", $resourceId, $currentUserId);
$stmt->execute();
$resource = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$resource) {
    die("Resource not found or you are not allowed to delete it.");
}

/* Delete database record */
$stmt = $conn->prepare("
    DELETE FROM resources
    WHERE id = ? AND uploader_id = ?
    LIMIT 1
");

$stmt->bind_param("ii", $resourceId, $currentUserId);
$stmt->execute();
$deleted = $stmt->affected_rows;
$stmt->close();

if ($deleted > 0) {
    /* Delete actual file if it is inside the project folder */
    $relativePath = str_replace("\\", "/", $resource["file_path"]);
    $absolutePath = __DIR__ . DIRECTORY_SEPARATOR .
        str_replace("/", DIRECTORY_SEPARATOR, $relativePath);

    $projectPath = realpath(__DIR__);
    $realFile = realpath($absolutePath);

    if ($projectPath && $realFile &&
        strpos($realFile, rtrim($projectPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) === 0 &&
        is_file($realFile)) {
        unlink($realFile);
    }
}

header("Location: resources.php" .
    ($exchangeId > 0 ? "?exchange_id=" . $exchangeId : ""));
exit();