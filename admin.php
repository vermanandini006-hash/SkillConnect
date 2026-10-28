
<?php
session_start();
require "db.php";

$conn->set_charset("utf8mb4");

/* ==========================================
   ADMIN LOGIN TABLE
========================================== */

$conn->query("
    CREATE TABLE IF NOT EXISTS admin_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(190) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

/* Create first admin account if none exists */

/* ==========================================
   SECURE ADMIN ACCOUNT HANDLING
========================================== */

/*
   Admin accounts are managed through the
   database and Admin Settings.

   No default admin password is stored
   in this public PHP file.

   Existing admin accounts are preserved.
*/

if (empty($_SESSION["admin_csrf_token"])) {
    $_SESSION["admin_csrf_token"] = bin2hex(
        random_bytes(32)
    );
}


/* ==========================================
   HELPER FUNCTIONS
========================================== */

function h($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}

function tableExists($conn, $table) {

    $table = $conn->real_escape_string($table);

    $result = $conn->query(
        "SHOW TABLES LIKE '$table'"
    );

    return $result && $result->num_rows > 0;
}

function columnExists($conn, $table, $column) {

    if (!tableExists($conn, $table)) {
        return false;
    }

    $table = str_replace("`", "``", $table);
    $column = $conn->real_escape_string($column);

    $result = $conn->query(
        "SHOW COLUMNS FROM `$table` LIKE '$column'"
    );

    return $result && $result->num_rows > 0;
}

function flashRedirect($message, $type = "success") {

    $_SESSION["admin_flash"] = [
        "message" => $message,
        "type" => $type
    ];

    header("Location: admin.php");
    exit;
}

/* ==========================================
   ADMIN LOGOUT
========================================== */


if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && ($_POST["action"] ?? "") === "logout"
) {
    $token = $_POST["csrf_token"] ?? "";

    if (!hash_equals($_SESSION["admin_csrf_token"] ?? "", $token)) {
        http_response_code(403);
        exit("Invalid security token.");
    }

    unset($_SESSION["admin"]);

    header("Location: admin.php");
    exit;
}


/* ==========================================
   ADMIN LOGIN
========================================== */

$loginError = "";

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && ($_POST["action"] ?? "") === "login"
) {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    $stmt = $conn->prepare("
        SELECT id, name, email, password_hash
        FROM admin_users
        WHERE email = ?
        LIMIT 1
    ");

    $stmt->bind_param("s", $email);
    $stmt->execute();

    $admin = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    if (
        $admin &&
        password_verify(
            $password,
            $admin["password_hash"]
        )
    ) {

        session_regenerate_id(true);

        $_SESSION["admin"] = [
            "id" => (int)$admin["id"],
            "name" => $admin["name"],
            "email" => $admin["email"]
        ];

        header("Location: admin.php");
        exit;

    } else {

        $loginError = "Invalid email or password.";
    }
}

/* ==========================================
   ADMIN LOGIN PAGE
========================================== */

if (!isset($_SESSION["admin"]["id"])) {
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Admin Login - SkillConnect</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f0f6f1;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #263b30;
}

.card {
    width: min(420px, 92%);
    background: white;
    padding: 32px;
    border-radius: 18px;
    box-shadow: 0 10px 35px #183b2915;
}

.logo {
    background: #3b7d5a;
    color: white;
    width: 55px;
    height: 55px;
    display: grid;
    place-items: center;
    border-radius: 14px;
    font-size: 27px;
}

h1 {
    color: #285b41;
    margin-bottom: 8px;
}

p {
    color: #77847c;
    font-size: 14px;
    line-height: 1.6;
}

label {
    display: block;
    margin: 18px 0 7px;
    font-weight: bold;
    font-size: 13px;
}

input {
    width: 100%;
    padding: 12px;
    border: 1px solid #d8e4dc;
    border-radius: 9px;
}

button {
    width: 100%;
    margin-top: 20px;
    padding: 13px;
    background: #3b7d5a;
    color: white;
    border: 0;
    border-radius: 9px;
    font-weight: bold;
    cursor: pointer;
}

.error {
    background: #fff0ef;
    color: #b7352a;
    padding: 12px;
    border-radius: 8px;
    font-size: 13px;
}

.hint {
    background: #f2f7f3;
    padding: 12px;
    margin-top: 18px;
    border-radius: 8px;
    font-size: 12px;
    line-height: 1.8;
}

</style>
</head>

<body>

<div class="card">

    <div class="logo">🛡️</div>

    <h1>SkillConnect Admin</h1>

    <p>
        Login to manage students, skills,
        reports and uploaded content.
    </p>

    <?php if ($loginError): ?>

        <div class="error">
            <?= h($loginError) ?>
        </div>

    <?php endif; ?>

    <form method="POST">

        <input
            type="hidden"
            name="action"
            value="login"
        >

        <label>Admin Email</label>

        <input
            type="email"
            name="email"
            required
        >

        <label>Password</label>

        <input
            type="password"
            name="password"
            required
        >

        <button type="submit">
            Login to Admin Panel
        </button>

    </form>

    <div class="hint">
        For security, use your admin credentials.
        Change the initial password in Admin Settings.
    </div>

</div>

</body>
</html>

<?php
    exit;
}

/* ==========================================
   ADMIN ACTIONS
========================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $csrfToken = $_POST["csrf_token"] ?? "";

    if (
        !hash_equals(
            $_SESSION["admin_csrf_token"] ?? "",
            $csrfToken
        )
    ) {
        http_response_code(403);
        exit("Invalid security token. Please refresh the page.");
    }

    $action = $_POST["action"] ?? "";

    /* ======================================
       ACTIVATE / DEACTIVATE STUDENT
    ====================================== */

    if ($action === "student_status") {

        if (!columnExists($conn, "users", "status")) {

            flashRedirect(
                "Users table vich status column nahi hai.",
                "error"
            );
        }

        $id = (int)($_POST["student_id"] ?? 0);

        $status = ($_POST["status"] ?? "") === "inactive"
            ? "inactive"
            : "active";

        $stmt = $conn->prepare("
            UPDATE users
            SET status = ?
            WHERE id = ?
        ");

        $stmt->bind_param("si", $status, $id);
        $stmt->execute();
        $stmt->close();

        flashRedirect("Student status updated.");
    }

    /* ======================================
       DELETE UPLOADED RESOURCE
    ====================================== */

    if ($action === "delete_resource") {

        if (!tableExists($conn, "resources")) {

            flashRedirect(
                "Resources table not found.",
                "error"
            );
        }

        $id = (int)($_POST["resource_id"] ?? 0);

        $stmt = $conn->prepare("
            SELECT file_path
            FROM resources
            WHERE id = ?
        ");

        $stmt->bind_param("i", $id);
        $stmt->execute();

        $resource = $stmt->get_result()->fetch_assoc();

        $stmt->close();

        if (!$resource) {

            flashRedirect(
                "Resource not found.",
                "error"
            );
        }

        $stmt = $conn->prepare("
            DELETE FROM resources
            WHERE id = ?
        ");

        $stmt->bind_param("i", $id);

        $ok = $stmt->execute();

        $stmt->close();

        if ($ok) {

            $file = trim(
                (string)($resource["file_path"] ?? "")
            );

            $absolute = __DIR__ . DIRECTORY_SEPARATOR
                . str_replace(
                    ["/", "\\"],
                    DIRECTORY_SEPARATOR,
                    $file
                );

            $base = realpath(__DIR__);
            $real = realpath($absolute);

            if (
                $file !== ""
                && $base
                && $real
                && strpos(
                    $real,
                    rtrim(
                        $base,
                        DIRECTORY_SEPARATOR
                    ) . DIRECTORY_SEPARATOR
                ) === 0
                && is_file($real)
            ) {

                @unlink($real);
            }

            flashRedirect(
                "Uploaded content deleted."
            );
        }

        flashRedirect(
            "Unable to delete resource.",
            "error"
        );
    }

    /* ======================================
       CHANGE ADMIN PASSWORD
    ====================================== */

    if ($action === "change_password") {

        $old = $_POST["old_password"] ?? "";
        $new = $_POST["new_password"] ?? "";
        $confirm = $_POST["confirm_password"] ?? "";

        if (strlen($new) < 8) {

            flashRedirect(
                "Password must have at least 8 characters.",
                "error"
            );
        }

        if ($new !== $confirm) {

            flashRedirect(
                "Passwords do not match.",
                "error"
            );
        }

        $id = (int)$_SESSION["admin"]["id"];

        $stmt = $conn->prepare("
            SELECT password_hash
            FROM admin_users
            WHERE id = ?
        ");

        $stmt->bind_param("i", $id);
        $stmt->execute();

        $row = $stmt->get_result()->fetch_assoc();

        $stmt->close();

        if (
            !$row ||
            !password_verify(
                $old,
                $row["password_hash"]
            )
        ) {

            flashRedirect(
                "Current password is incorrect.",
                "error"
            );
        }

        $hash = password_hash(
            $new,
            PASSWORD_DEFAULT
        );

        $stmt = $conn->prepare("
            UPDATE admin_users
            SET password_hash = ?
            WHERE id = ?
        ");

        $stmt->bind_param("si", $hash, $id);
        $stmt->execute();
        $stmt->close();

        flashRedirect(
            "Admin password changed."
        );
    }
}

/* ==========================================
   DASHBOARD DATA
========================================== */

$flash = $_SESSION["admin_flash"] ?? null;

unset($_SESSION["admin_flash"]);

$section = $_GET["section"] ?? "dashboard";

$allowed = [
    "dashboard",
    "students",
    "skills",
    "reports",
    "content",
    "settings"
];

if (!in_array($section, $allowed, true)) {
    $section = "dashboard";
}

/* ==========================================
   STATISTICS
========================================== */

$studentCount = tableExists($conn, "users")
    ? (int)$conn->query(
        "SELECT COUNT(*) n FROM users"
    )->fetch_assoc()["n"]
    : null;

$exchangeCount = tableExists($conn, "exchanges")
    ? (int)$conn->query(
        "SELECT COUNT(*) n FROM exchanges"
    )->fetch_assoc()["n"]
    : null;

$activeCount = tableExists($conn, "exchanges")
    ? (int)$conn->query(
        "SELECT COUNT(*) n FROM exchanges
         WHERE status = 'active'"
    )->fetch_assoc()["n"]
    : null;

$reportCount = tableExists($conn, "reports")
    ? (int)$conn->query(
        "SELECT COUNT(*) n FROM reports"
    )->fetch_assoc()["n"]
    : null;

$resourceCount = tableExists($conn, "resources")
    ? (int)$conn->query(
        "SELECT COUNT(*) n FROM resources"
    )->fetch_assoc()["n"]
    : null;

/* ==========================================
   DATA ARRAYS
========================================== */

$students = [];
$skills = [];
$reports = [];
$resources = [];

$search = trim($_GET["q"] ?? "");

/* ==========================================
   STUDENTS LIST
========================================== */

if (
    $section === "students"
    && tableExists($conn, "users")
) {

    $sql = "SELECT * FROM users";

    $params = [];
    $types = "";

    if (
        $search !== ""
        && columnExists($conn, "users", "name")
        && columnExists($conn, "users", "email")
    ) {

        $sql .= " WHERE name LIKE ? OR email LIKE ?";

        $like = "%$search%";

        $params = [$like, $like];
        $types = "ss";
    }

    $sql .= " ORDER BY id DESC LIMIT 300";

    if ($params) {

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            $types,
            ...$params
        );

        $stmt->execute();

        $result = $stmt->get_result();

    } else {

        $result = $conn->query($sql);
    }

    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }

    if (isset($stmt)) {
        $stmt->close();
    }
}

/* ==========================================
   SKILLS LIST
========================================== */

if (
    $section === "skills"
    && tableExists($conn, "users")
) {

    foreach (
        [
            "skills_to_teach" => "Teaching",
            "skills_to_learn" => "Learning"
        ] as $column => $kind
    ) {

        if (!columnExists($conn, "users", $column)) {
            continue;
        }

        $result = $conn->query("
            SELECT id, name,
                   `$column` AS skill_data
            FROM users
        ");

        while ($row = $result->fetch_assoc()) {

            $raw = trim(
                (string)$row["skill_data"]
            );

            if ($raw === "") {
                continue;
            }

            $decoded = json_decode($raw, true);

            $items = (
                json_last_error() === JSON_ERROR_NONE
                && is_array($decoded)
            )
                ? $decoded
                : preg_split("/\s*,\s*/", $raw);

            foreach ($items as $item) {

                $item = trim((string)$item);

                if ($item === "") {
                    continue;
                }

                $key = strtolower($item);

                if (!isset($skills[$key])) {

                    $skills[$key] = [
                        "name" => $item,
                        "teaching" => 0,
                        "learning" => 0
                    ];
                }

                if ($kind === "Teaching") {

                    $skills[$key]["teaching"]++;

                } else {

                    $skills[$key]["learning"]++;
                }
            }
        }
    }

    ksort($skills);
}

/* ==========================================
   REPORTS
========================================== */

if (
    $section === "reports"
    && tableExists($conn, "reports")
) {

    $result = $conn->query("
        SELECT *
        FROM reports
        ORDER BY id DESC
        LIMIT 200
    ");

    while ($row = $result->fetch_assoc()) {
        $reports[] = $row;
    }
}

/* ==========================================
   UPLOADED CONTENT
========================================== */

if (
    $section === "content"
    && tableExists($conn, "resources")
) {

    $result = $conn->query("
        SELECT *
        FROM resources
        ORDER BY id DESC
        LIMIT 300
    ");

    while ($row = $result->fetch_assoc()) {
        $resources[] = $row;
    }
}

/* ==========================================
   COUNT FORMAT
========================================== */

function countText($number) {

    return $number === null
        ? "Table missing"
        : number_format($number);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1"
>

<title>SkillConnect Admin Panel</title>

<style>

/* ==========================================
   GLOBAL
========================================== */

:root {
    --green: #3b7d5a;
    --dark: #285b41;
    --bg: #f6faf7;
    --line: #e3ece5;
}

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: var(--bg);
    color: #263b30;
}

a {
    text-decoration: none;
    color: inherit;
}

/* ==========================================
   MAIN LAYOUT
========================================== */

.layout {
    display: flex;
    min-height: 100vh;
}

/* ==========================================
   ADMIN SIDEBAR
========================================== */

.sidebar {
    width: 245px;
    background: #173a2a;
    color: white;
    padding: 22px 14px;
    flex-shrink: 0;
}

.brand {
    font-size: 20px;
    font-weight: bold;
    padding: 8px 10px 24px;
}

.brand small {
    display: block;
    font-size: 10px;
    letter-spacing: 2px;
    color: #b9d2c1;
    margin-top: 6px;
}

.label {
    font-size: 10px;
    letter-spacing: 1.5px;
    color: #9fbaa9;
    margin: 22px 10px 9px;
}

.nav a {
    display: block;
    padding: 12px;
    border-radius: 9px;
    color: #dce9e0;
    margin: 4px 0;
    font-size: 14px;
}

.nav a:hover,
.nav a.active {
    background: #2e6246;
    color: white;
}

/* ==========================================
   MAIN CONTENT
========================================== */

.main {
    flex: 1;
    min-width: 0;
}

.topbar {
    background: white;
    border-bottom: 1px solid var(--line);
    padding: 18px 26px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
}

.topbar h2 {
    margin: 0;
    color: var(--dark);
    font-size: 18px;
}

.content {
    max-width: 1400px;
    padding: 28px;
    margin: auto;
}

.heading h1 {
    margin: 0 0 8px;
    color: var(--dark);
    font-size: 28px;
}

.heading p,
.muted {
    color: #78857d;
    font-size: 13px;
    line-height: 1.6;
}

/* ==========================================
   STATISTICS
========================================== */

.stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
    margin: 22px 0;
}

.stat,
.panel {
    background: white;
    border: 1px solid var(--line);
    border-radius: 14px;
    padding: 20px;
    margin-bottom: 18px;
}

.stat span {
    color: #78857d;
    font-size: 13px;
}

.stat strong {
    display: block;
    font-size: 27px;
    color: var(--dark);
    margin: 13px 0;
}

.panel h3 {
    color: var(--dark);
    margin-top: 0;
}

/* ==========================================
   QUICK ADMIN TOOLS
========================================== */

.quick {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
}

.quick a {
    border: 1px solid var(--line);
    border-radius: 10px;
    padding: 16px;
}

.quick a:hover {
    border-color: #a7cbb3;
}

.quick b {
    display: block;
    color: var(--dark);
    margin: 9px 0;
}

.quick small {
    font-size: 12px;
    color: #78857d;
    line-height: 1.5;
}

/* ==========================================
   ALERTS
========================================== */

.flash {
    padding: 13px;
    border-radius: 9px;
    margin-bottom: 18px;
    font-size: 13px;
}

.success {
    background: #e7f7ed;
    color: #217044;
}

.error {
    background: #fff0ef;
    color: #b7352a;
}

/* ==========================================
   TABLES
========================================== */

.tablewrap {
    overflow: auto;
    border: 1px solid var(--line);
    border-radius: 10px;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 700px;
}

th,
td {
    padding: 12px;
    border-bottom: 1px solid #edf1ee;
    text-align: left;
    font-size: 13px;
    vertical-align: top;
}

th {
    background: #f2f7f3;
    color: #52695a;
    text-transform: uppercase;
    font-size: 11px;
}

/* ==========================================
   BUTTONS AND BADGES
========================================== */

.pill {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 20px;
    background: #e7f5eb;
    color: #287347;
    font-size: 11px;
}

.btn {
    display: inline-block;
    border: 0;
    border-radius: 8px;
    padding: 8px 11px;
    background: var(--green);
    color: white;
    font-size: 12px;
    font-weight: bold;
    cursor: pointer;
}

.btn.light {
    background: #edf5ef;
    color: var(--dark);
}

.btn.red {
    background: #fff0ef;
    color: #b7352a;
}

/* ==========================================
   SEARCH
========================================== */

.search {
    display: flex;
    gap: 8px;
    margin-bottom: 16px;
    flex-wrap: wrap;
}

.search input {
    flex: 1;
    min-width: 200px;
    padding: 11px;
    border: 1px solid #d8e4dc;
    border-radius: 8px;
}

.search button {
    border: 0;
    border-radius: 8px;
    background: var(--green);
    color: white;
    padding: 10px 16px;
    cursor: pointer;
}

/* ==========================================
   SKILLS
========================================== */

.skillgrid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 13px;
}

.skillcard {
    background: white;
    border: 1px solid var(--line);
    border-radius: 12px;
    padding: 18px;
}

.skillcard h3 {
    color: var(--dark);
    margin-top: 0;
}

/* ==========================================
   EMPTY STATE
========================================== */

.empty {
    text-align: center;
    padding: 30px;
    color: #78857d;
}

/* ==========================================
   ADMIN SETTINGS
========================================== */

label {
    display: block;
    margin: 15px 0 7px;
    font-size: 13px;
    font-weight: bold;
}

.settings input {
    width: 100%;
    padding: 11px;
    border: 1px solid #d8e4dc;
    border-radius: 8px;
}

.settings button {
    margin-top: 18px;
}

/* ==========================================
   RESPONSIVE
========================================== */

@media (max-width: 1000px) {

    .stats {
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
    }

    .quick,
    .skillgrid {
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
    }

    .content {
        padding: 20px;
    }
}

@media (max-width: 700px) {

    .layout {
        display: block;
    }

    .sidebar {
        width: 100%;
    }

    .nav {
        display: grid;
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
    }

    .topbar {
        padding: 15px;
    }

    .content {
        padding: 15px;
    }

    .quick,
    .skillgrid {
        grid-template-columns: 1fr;
    }

    .stats {
        gap: 9px;
    }

    .stat {
        padding: 13px;
    }
}

</style>

</head>

<body>

<div class="layout">

<!-- ==========================================
     ADMIN SIDEBAR
     STUDENT PLATFORM REMOVED
========================================== -->

<aside class="sidebar">

    <div class="brand">
        🎓 SkillConnect
        <small>ADMIN CONSOLE</small>
    </div>

    <div class="label">
        MAIN MENU
    </div>

    <nav class="nav">

        <a
            class="<?= $section === "dashboard" ? "active" : "" ?>"
            href="admin.php"
        >
            ▦ Dashboard
        </a>

        <a
            class="<?= $section === "students" ? "active" : "" ?>"
            href="admin.php?section=students"
        >
            👥 Manage Students
        </a>

        <a
            class="<?= $section === "skills" ? "active" : "" ?>"
            href="admin.php?section=skills"
        >
            ✦ Manage Skills
        </a>

        <a
            class="<?= $section === "reports" ? "active" : "" ?>"
            href="admin.php?section=reports"
        >
            ⚑ Manage Reports
        </a>

        <a
            class="<?= $section === "content" ? "active" : "" ?>"
            href="admin.php?section=content"
        >
            📚 Uploaded Content
        </a>

    </nav>

    <div class="label">
        ACCOUNT
    </div>

    <nav class="nav">

        <a
            class="<?= $section === "settings" ? "active" : "" ?>"
            href="admin.php?section=settings"
        >
            ⚙ Admin Settings
        </a>

       
<form method="POST" action="admin.php">
    <input
        type="hidden"
        name="action"
        value="logout"
    >

    <input
        type="hidden"
        name="csrf_token"
        value="<?= h($_SESSION["admin_csrf_token"]) ?>"
    >

    <button
        type="submit"
        style="background:none;border:none;color:#dce9e0;padding:12px;cursor:pointer;font-size:14px;width:100%;text-align:left;"
    >
        ↪ Logout
    </button>
</form>

    </nav>

</aside>

<!-- ==========================================
     ADMIN MAIN AREA
========================================== -->

<main class="main">

<header class="topbar">

    <h2>
        <?= h(ucwords($section)) ?>
    </h2>

    <div>
        🛡️ <?= h($_SESSION["admin"]["name"]) ?> · Admin
    </div>

</header>

<div class="content">

<?php if ($flash): ?>

    <div class="flash <?= h($flash["type"]) ?>">
        <?= h($flash["message"]) ?>
    </div>

<?php endif; ?>

<!-- ==========================================
     ADMIN DASHBOARD
========================================== -->

<?php if ($section === "dashboard"): ?>

<div class="heading">

    <h1>Admin Dashboard 👋</h1>

    <p>
        Manage the Student Skill Exchange Platform
        from one place.
    </p>

</div>

<div class="stats">

    <div class="stat">

        <span>👥 Total Students</span>

        <strong>
            <?= countText($studentCount) ?>
        </strong>

        <small>Registered accounts</small>

    </div>

    <div class="stat">

        <span>🤝 Exchanges</span>

        <strong>
            <?= countText($exchangeCount) ?>
        </strong>

        <small>
            <?= countText($activeCount) ?> active
        </small>

    </div>

    <div class="stat">

        <span>⚑ Reports</span>

        <strong>
            <?= countText($reportCount) ?>
        </strong>

        <small>Submitted reports</small>

    </div>

    <div class="stat">

        <span>📚 Uploaded Content</span>

        <strong>
            <?= countText($resourceCount) ?>
        </strong>

        <small>Videos, notes and PDFs</small>

    </div>

</div>

<!-- ADMIN TOOLS -->

<div class="panel">

    <h3>Admin Tools</h3>

    <p class="muted">
        Choose a module to manage the platform.
    </p>

    <div class="quick">

        <a href="admin.php?section=students">
            👥
            <b>Manage Students</b>
            <small>
                Search and review student accounts.
            </small>
        </a>

        <a href="admin.php?section=skills">
            ✦
            <b>Manage Skills</b>
            <small>
                Review skills students teach and learn.
            </small>
        </a>

        <a href="admin.php?section=reports">
            ⚑
            <b>Manage Reports</b>
            <small>
                Review submitted reports.
            </small>
        </a>

        <a href="admin.php?section=content">
            📚
            <b>Uploaded Content</b>
            <small>
                Review and delete uploaded resources.
            </small>
        </a>

        <a href="admin.php?section=settings">
            ⚙
            <b>Admin Settings</b>
            <small>
                Change your admin password.
            </small>
        </a>

    </div>

</div>

<!-- DATABASE STATUS -->

<div class="panel">

    <h3>Database Status</h3>

    <div class="tablewrap">

        <table>

            <thead>
                <tr>
                    <th>Module</th>
                    <th>Database Table</th>
                    <th>Status</th>
                    <th>Records</th>
                </tr>
            </thead>

            <tbody>

            <?php foreach ([
                ["Students", "users", $studentCount],
                ["Exchanges", "exchanges", $exchangeCount],
                ["Reports", "reports", $reportCount],
                ["Resources", "resources", $resourceCount]
            ] as $item): ?>

                <tr>

                    <td><?= h($item[0]) ?></td>

                    <td><?= h($item[1]) ?></td>

                    <td>
                        <span class="pill">
                            <?= $item[2] === null
                                ? "Table missing"
                                : "Connected" ?>
                        </span>
                    </td>

                    <td>
                        <?= countText($item[2]) ?>
                    </td>

                </tr>

            <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</div>

<!-- ==========================================
     MANAGE STUDENTS
========================================== -->

<?php elseif ($section === "students"): ?>

<div class="heading">

    <h1>Manage Students</h1>

    <p>
        Search registered students and
        change account status.
    </p>

</div>

<div class="panel">

    <form class="search" method="GET">

        <input
            type="hidden"
            name="section"
            value="students"
        >

        <input
            name="q"
            value="<?= h($search) ?>"
            placeholder="Search by student name or email"
        >

        <button type="submit">
            Search
        </button>

        <a
            class="btn light"
            href="admin.php?section=students"
        >
            Reset
        </a>

    </form>

    <?php if (!tableExists($conn, "users")): ?>

        <div class="empty">
            Users table not found.
        </div>

    <?php elseif (!$students): ?>

        <div class="empty">
            No students found.
        </div>

    <?php else: ?>

        <div class="tablewrap">

            <table>

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Student</th>
                        <th>Course / College</th>
                        <th>Skills</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($students as $student):

                    $status = strtolower(
                        (string)($student["status"] ?? "active")
                    );

                ?>

                    <tr>

                        <td>
                            #<?= (int)$student["id"] ?>
                        </td>

                        <td>

                            <b>
                                <?= h($student["name"] ?? "Student") ?>
                            </b>

                            <br>

                            <?= h($student["email"] ?? "") ?>

                        </td>

                        <td>

                            <?= h($student["course"] ?? "—") ?>

                            <br>

                            <?= h($student["college"] ?? "—") ?>

                        </td>

                        <td class="muted">

                            Teach:
                            <?= h($student["skills_to_teach"] ?? "—") ?>

                            <br>

                            Learn:
                            <?= h($student["skills_to_learn"] ?? "—") ?>

                        </td>

                        <td>

                            <span class="pill">
                                <?= h($status) ?>
                            </span>

                        </td>

                        <td>

                        <?php if (
                            columnExists(
                                $conn,
                                "users",
                                "status"
                            )
                        ): ?>

                            <form
                                method="POST"
                                onsubmit="return confirm('Change this student status?')"
                            >

<input
    type="hidden"
    name="csrf_token"
    value="<?= h($_SESSION["admin_csrf_token"]) ?>"
>

                                <input
                                    type="hidden"
                                    name="action"
                                    value="student_status"
                                >

                                <input
                                    type="hidden"
                                    name="student_id"
                                    value="<?= (int)$student["id"] ?>"
                                >

                                <input
                                    type="hidden"
                                    name="status"
                                    value="<?= $status === "inactive" ? "active" : "inactive" ?>"
                                >

                                <button
                                    class="btn <?= $status === "inactive" ? "" : "red" ?>"
                                >
                                    <?= $status === "inactive"
                                        ? "Activate"
                                        : "Deactivate" ?>
                                </button>

                            </form>

                        <?php else: ?>

                            <span class="muted">
                                Status column unavailable
                            </span>

                        <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

</div>

<!-- ==========================================
     MANAGE SKILLS
========================================== -->

<?php elseif ($section === "skills"): ?>

<div class="heading">

    <h1>Manage Skills</h1>

    <p>
        Skill catalogue collected from student profiles.
    </p>

</div>

<?php if (!$skills): ?>

    <div class="panel empty">
        No profile skills found.
    </div>

<?php else: ?>

    <div class="skillgrid">

    <?php foreach ($skills as $skill): ?>

        <div class="skillcard">

            <h3>
                <?= h($skill["name"]) ?>
            </h3>

            <span class="pill">
                Teaching: <?= (int)$skill["teaching"] ?>
            </span>

            <span class="pill">
                Learning: <?= (int)$skill["learning"] ?>
            </span>

        </div>

    <?php endforeach; ?>

    </div>

<?php endif; ?>

<!-- ==========================================
     MANAGE REPORTS
========================================== -->

<?php elseif ($section === "reports"): ?>

<div class="heading">

    <h1>Manage Reports</h1>

    <p>Review submitted reports.</p>

</div>

<?php if (!tableExists($conn, "reports")): ?>

    <div class="panel">

        <h3>Reports table not found</h3>

        <p class="muted">
            Your database does not currently contain
            a table named reports. Connect this section
            to the actual table used by your
            report-submission page.
        </p>

    </div>

<?php elseif (!$reports): ?>

    <div class="panel empty">
        No reports found.
    </div>

<?php else: ?>

    <div class="panel">

        <div class="tablewrap">

            <table>

                <thead>

                    <tr>

                    <?php foreach (
                        array_keys($reports[0]) as $key
                    ): ?>

                        <th><?= h($key) ?></th>

                    <?php endforeach; ?>

                    </tr>

                </thead>

                <tbody>

                <?php foreach ($reports as $report): ?>

                    <tr>

                    <?php foreach ($report as $value): ?>

                        <td>
                            <?= h(
                                is_scalar($value)
                                    ? $value
                                    : json_encode($value)
                            ) ?>
                        </td>

                    <?php endforeach; ?>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    </div>

<?php endif; ?>

<!-- ==========================================
     UPLOADED CONTENT
========================================== -->

<?php elseif ($section === "content"): ?>

<div class="heading">

    <h1>Manage Uploaded Content</h1>

    <p>
        Review videos, notes and PDFs shared by students.
    </p>

</div>

<?php if (!tableExists($conn, "resources")): ?>

    <div class="panel empty">
        Resources table not found.
    </div>

<?php elseif (!$resources): ?>

    <div class="panel empty">
        No uploaded resources found.
    </div>

<?php else: ?>

    <div class="panel">

        <div class="tablewrap">

            <table>

                <thead>

                    <tr>
                        <th>ID</th>
                        <th>Title / Type</th>
                        <th>Skill</th>
                        <th>Uploader</th>
                        <th>File</th>
                        <th>Action</th>
                    </tr>

                </thead>

                <tbody>

                <?php foreach ($resources as $resource): ?>

                    <tr>

                        <td>
                            #<?= (int)($resource["id"] ?? 0) ?>
                        </td>

                        <td>

                            <b>
                                <?= h(
                                    $resource["title"] ?? "Untitled"
                                ) ?>
                            </b>

                            <br>

                            <span class="pill">
                                <?= h(
                                    $resource["resource_type"] ?? "file"
                                ) ?>
                            </span>

                        </td>

                        <td>
                            <?= h($resource["skill"] ?? "—") ?>
                        </td>

                        <td>
                            <?= h(
                                $resource["uploader_name"]
                                ?? ($resource["uploader_id"] ?? "—")
                            ) ?>
                        </td>

                        <td>

                        <?php if (!empty($resource["file_path"])): ?>

                            <a
                                class="btn light"
                                target="_blank"
                                rel="noopener"
                                href="<?= h($resource["file_path"]) ?>"
                            >
                                Open
                            </a>

                        <?php else: ?>

                            —

                        <?php endif; ?>

                        </td>

                        <td>

                            <form
                                method="POST"
                                onsubmit="return confirm('Permanently delete this uploaded resource?')"
                            >

                                <input
                                    type="hidden"
                                    name="action"
                                    value="delete_resource"
                                >

<input
    type="hidden"
    name="csrf_token"
    value="<?= h($_SESSION["admin_csrf_token"]) ?>"
>

                                <input
                                    type="hidden"
                                    name="resource_id"
                                    value="<?= (int)($resource["id"] ?? 0) ?>"
                                >

                                <button class="btn red">
                                    Delete
                                </button>

                            </form>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    </div>

<?php endif; ?>

<!-- ==========================================
     ADMIN SETTINGS
========================================== -->

<?php elseif ($section === "settings"): ?>

<div class="heading">

    <h1>Admin Settings</h1>

    <p>
        Change your password to secure
        your admin account.
    </p>

</div>

<div
    class="panel settings"
    style="max-width:500px"
>

    <h3>Change Password</h3>

    <form method="POST">

<input
    type="hidden"
    name="csrf_token"
    value="<?= htmlspecialchars($_SESSION['admin_csrf_token'], ENT_QUOTES, 'UTF-8') ?>"
>

        <input
            type="hidden"
            name="action"
            value="change_password"
        >

        <label>Current Password</label>

        <input
            type="password"
            name="old_password"
            required
        >

        <label>
            New Password (minimum 8 characters)
        </label>

        <input
            type="password"
            name="new_password"
            minlength="8"
            required
        >

        <label>Confirm New Password</label>

        <input
            type="password"
            name="confirm_password"
            minlength="8"
            required
        >

        <button class="btn">
            Update Password
        </button>

    </form>

</div>

<?php endif; ?>

<!-- ==========================================
     ADMIN FOOTER
========================================== -->

<p
    class="muted"
    style="margin-top:25px"
>
    SkillConnect Admin Console
</p>

</div>

</main>

</div>

</body>
</html>
