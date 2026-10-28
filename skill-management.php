<?php

/* =========================================
   PHP ERRORS
========================================= */

ini_set("display_errors", "1");
ini_set("display_startup_errors", "1");
error_reporting(E_ALL);


/* =========================================
   SESSION + DATABASE
========================================= */

session_start();

require "db.php";


/* =========================================
   LOGIN CHECK
========================================= */

if (!isset($_SESSION["user"])) {

    header("Location: login.php");
    exit();
}


/* =========================================
   VARIABLES
========================================= */

$message = "";
$messageType = "";


/* =========================================
   ADD SKILL
========================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    &&
    isset($_POST["add_skill"])
) {

    $skillName =
        trim($_POST["skill_name"] ?? "");

    $category =
        trim($_POST["category"] ?? "");


    if ($skillName === "") {

        $message =
            "Please enter a skill name.";

        $messageType =
            "error";

    } else {


        if ($category === "") {

            $category = "Other";
        }


        /* CHECK DUPLICATE */

        $checkStmt =
            $conn->prepare(
                "SELECT id
                 FROM skills
                 WHERE LOWER(name) = LOWER(?)
                 LIMIT 1"
            );


        if ($checkStmt) {

            $checkStmt->bind_param(
                "s",
                $skillName
            );

            $checkStmt->execute();

            $result =
                $checkStmt->get_result();


            if (
                $result &&
                $result->num_rows > 0
            ) {

                $message =
                    "This skill already exists.";

                $messageType =
                    "error";

            } else {


                /* INSERT SKILL */

                $insertStmt =
                    $conn->prepare(
                        "INSERT INTO skills
                        (name, category)
                        VALUES (?, ?)"
                    );


                if ($insertStmt) {

                    $insertStmt->bind_param(
                        "ss",
                        $skillName,
                        $category
                    );


                    if (
                        $insertStmt->execute()
                    ) {

                        $message =
                            "Skill added successfully.";

                        $messageType =
                            "success";

                    } else {

                        $message =
                            "Unable to add skill.";

                        $messageType =
                            "error";
                    }


                    $insertStmt->close();
                }
            }


            $checkStmt->close();
        }
    }
}


/* =========================================
   REMOVE SKILL
========================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    &&
    isset($_POST["remove_skill"])
) {

    $skillId =
        (int)(
            $_POST["skill_id"]
            ?? 0
        );


    if ($skillId > 0) {


        $used = false;


        /*
           Check user_skills
        */

        $checkUserSkills =
            $conn->prepare(
                "SELECT COUNT(*) AS total
                 FROM user_skills
                 WHERE skill_id = ?"
            );


        if ($checkUserSkills) {

            $checkUserSkills->bind_param(
                "i",
                $skillId
            );

            $checkUserSkills->execute();

            $result =
                $checkUserSkills->get_result();

            $row =
                $result->fetch_assoc();

            if (
                (int)($row["total"] ?? 0)
                > 0
            ) {

                $used = true;
            }

            $checkUserSkills->close();
        }


        /*
           Check exchange requests
        */

        if (!$used) {

            $checkRequests =
                $conn->prepare(
                    "SELECT COUNT(*) AS total
                     FROM exchange_requests
                     WHERE skill_id = ?"
                );


            if ($checkRequests) {

                $checkRequests->bind_param(
                    "i",
                    $skillId
                );

                $checkRequests->execute();

                $result =
                    $checkRequests->get_result();

                $row =
                    $result->fetch_assoc();


                if (
                    (int)($row["total"] ?? 0)
                    > 0
                ) {

                    $used = true;
                }


                $checkRequests->close();
            }
        }


        /*
           Delete only if not used
        */

        if ($used) {

            $message =
                "This skill is already being used and cannot be removed.";

            $messageType =
                "error";

        } else {


            $deleteStmt =
                $conn->prepare(
                    "DELETE FROM skills
                     WHERE id = ?"
                );


            if ($deleteStmt) {

                $deleteStmt->bind_param(
                    "i",
                    $skillId
                );


                if (
                    $deleteStmt->execute()
                ) {

                    $message =
                        "Skill removed successfully.";

                    $messageType =
                        "success";

                } else {

                    $message =
                        "Unable to remove skill.";

                    $messageType =
                        "error";
                }


                $deleteStmt->close();
            }
        }
    }
}


/* =========================================
   SEARCH + CATEGORY
========================================= */

$search =
    trim(
        $_GET["search"]
        ?? ""
    );


$selectedCategory =
    trim(
        $_GET["category"]
        ?? ""
    );


/* =========================================
   GET CATEGORIES
========================================= */

$categories = [];


$categoryResult =
    $conn->query(
        "SELECT DISTINCT category
         FROM skills
         WHERE category IS NOT NULL
         AND category != ''
         ORDER BY category ASC"
    );


if ($categoryResult) {

    while (
        $row =
        $categoryResult->fetch_assoc()
    ) {

        $categories[] =
            $row["category"];
    }
}


/* =========================================
   GET SKILLS
========================================= */

$skills = [];


$sql =
    "SELECT
        id,
        name,
        category,
        created_at
     FROM skills
     WHERE 1=1";


$params = [];

$types = "";


/* SEARCH */

if ($search !== "") {

    $sql .=
        " AND (
            name LIKE ?
            OR category LIKE ?
        )";


    $searchValue =
        "%" . $search . "%";


    $params[] =
        $searchValue;

    $params[] =
        $searchValue;

    $types .= "ss";
}


/* CATEGORY */

if ($selectedCategory !== "") {

    $sql .=
        " AND category = ?";

    $params[] =
        $selectedCategory;

    $types .= "s";
}


$sql .=
    " ORDER BY category ASC, name ASC";


$stmt =
    $conn->prepare($sql);


if ($stmt) {


    if (!empty($params)) {

        $stmt->bind_param(
            $types,
            ...$params
        );
    }


    $stmt->execute();


    $result =
        $stmt->get_result();


    while (
        $row =
        $result->fetch_assoc()
    ) {

        $skills[] =
            $row;
    }


    $stmt->close();
}


?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    Skill Management - SkillConnect
</title>


<style>

/* =========================================
   PAGE
========================================= */

* {

    margin: 0;

    padding: 0;

    box-sizing: border-box;
}


body {

    font-family:
        Arial,
        sans-serif;

    background:
        #f6faf7;

    color:
        #183c2b;
}


/* =========================================
   MAIN
========================================= */

.main-container {

    width: 90%;

    max-width: 1100px;

    margin:
        40px auto 60px;
}


/* =========================================
   HEADER
========================================= */

.page-header {

    margin-bottom:
        28px;
}


.page-header h1 {

    font-size:
        30px;

    color:
        #183c2b;

    margin-bottom:
        8px;
}


.page-header p {

    color:
        #777;

    font-size:
        15px;
}


/* =========================================
   MESSAGE
========================================= */

.message {

    padding:
        13px 18px;

    border-radius:
        10px;

    margin-bottom:
        22px;

    font-size:
        14px;

    font-weight:
        600;
}


.message.success {

    background:
        #eaf7ef;

    color:
        #217447;

    border:
        1px solid #ccebd8;
}


.message.error {

    background:
        #fff1f0;

    color:
        #c0392b;

    border:
        1px solid #f2cfcc;
}


/* =========================================
   TOP GRID
========================================= */

.top-grid {

    display:
        grid;

    grid-template-columns:
        1fr 1fr;

    gap:
        22px;

    margin-bottom:
        25px;
}


/* =========================================
   CARD
========================================= */

.card {

    background:
        #ffffff;

    border-radius:
        16px;

    padding:
        25px;

    box-shadow:
        0 6px 22px
        rgba(0,0,0,0.06);
}


.card h2 {

    font-size:
        20px;

    color:
        #183c2b;

    margin-bottom:
        18px;
}


/* =========================================
   FORM
========================================= */

.form-group {

    margin-bottom:
        15px;
}


.form-group label {

    display:
        block;

    margin-bottom:
        7px;

    font-size:
        14px;

    font-weight:
        600;

    color:
        #44584d;
}


.form-group input {

    width:
        100%;

    padding:
        12px 14px;

    border:
        1px solid #d8e3dc;

    border-radius:
        9px;

    outline:
        none;

    font-size:
        14px;

    background:
        #ffffff;
}


.form-group input:focus {

    border-color:
        #3b7d5a;

    box-shadow:
        0 0 0 3px
        rgba(59,125,90,0.10);
}


/* =========================================
   BUTTON
========================================= */

.primary-btn {

    border:
        none;

    background:
        #3b7d5a;

    color:
        white;

    padding:
        11px 20px;

    border-radius:
        9px;

    font-size:
        14px;

    font-weight:
        600;

    cursor:
        pointer;
}


.primary-btn:hover {

    background:
        #285b41;
}


/* =========================================
   SEARCH
========================================= */

.search-row {

    display:
        flex;

    gap:
        10px;
}


.search-row input {

    flex:
        1;
}


.search-btn {

    border:
        none;

    background:
        #3b7d5a;

    color:
        white;

    padding:
        0 18px;

    border-radius:
        9px;

    cursor:
        pointer;

    font-weight:
        600;
}


.clear-btn {

    display:
        inline-flex;

    align-items:
        center;

    justify-content:
        center;

    text-decoration:
        none;

    background:
        #eef3ef;

    color:
        #3b5c4b;

    padding:
        0 15px;

    border-radius:
        9px;

    font-size:
        14px;
}


/* =========================================
   CATEGORIES
========================================= */

.category-card {

    margin-bottom:
        25px;
}


.category-list {

    display:
        flex;

    flex-wrap:
        wrap;

    gap:
        9px;
}


.category-btn {

    text-decoration:
        none;

    background:
        #eef7f1;

    color:
        #26754d;

    border:
        1px solid #d5ebdd;

    padding:
        8px 14px;

    border-radius:
        20px;

    font-size:
        13px;

    font-weight:
        600;

    transition:
        0.2s;
}


.category-btn:hover {

    background:
        #dff2e6;
}


.category-btn.active {

    background:
        #3b7d5a;

    color:
        white;

    border-color:
        #3b7d5a;
}


/* =========================================
   SKILL HEADER
========================================= */

.skills-header {

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        center;

    margin-bottom:
        18px;
}


.skills-header h2 {

    margin:
        0;
}


.skill-count {

    background:
        #eaf7ef;

    color:
        #28764d;

    padding:
        7px 12px;

    border-radius:
        20px;

    font-size:
        13px;

    font-weight:
        600;
}


/* =========================================
   SKILL GRID
========================================= */

.skill-grid {

    display:
        grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap:
        16px;
}


/* =========================================
   SKILL ITEM
========================================= */

.skill-item {

    border:
        1px solid #e2ebe5;

    border-radius:
        13px;

    padding:
        18px;

    background:
        #ffffff;

    transition:
        0.2s;
}


.skill-item:hover {

    border-color:
        #b9dbc6;

    transform:
        translateY(-2px);
}


.skill-name {

    font-size:
        17px;

    font-weight:
        600;

    color:
        #214a35;

    margin-bottom:
        8px;
}


.skill-category {

    display:
        inline-block;

    background:
        #edf8f2;

    color:
        #2f8f61;

    padding:
        5px 9px;

    border-radius:
        15px;

    font-size:
        12px;

    margin-bottom:
        15px;
}


.skill-bottom {

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;
}


.skill-date {

    font-size:
        11px;

    color:
        #999;
}


.remove-btn {

    border:
        none;

    background:
        #fff1f0;

    color:
        #c0392b;

    padding:
        7px 10px;

    border-radius:
        7px;

    cursor:
        pointer;

    font-size:
        12px;

    font-weight:
        600;
}


.remove-btn:hover {

    background:
        #fbdedb;
}


/* =========================================
   EMPTY
========================================= */

.empty-box {

    text-align:
        center;

    padding:
        45px 20px;

    color:
        #999;
}


.empty-box-icon {

    font-size:
        38px;

    margin-bottom:
        10px;
}


.empty-box p {

    font-size:
        14px;
}


/* =========================================
   RESPONSIVE
========================================= */

@media(max-width:850px) {

    .top-grid {

        grid-template-columns:
            1fr;
    }


    .skill-grid {

        grid-template-columns:
            repeat(2, 1fr);
    }
}


@media(max-width:550px) {

    .main-container {

        width:
            92%;

        margin-top:
            25px;
    }


    .page-header h1 {

        font-size:
            25px;
    }


    .card {

        padding:
            20px;
    }


    .skill-grid {

        grid-template-columns:
            1fr;
    }


    .search-row {

        flex-wrap:
            wrap;
    }


    .search-row input {

        width:
            100%;

        flex:
            none;
    }


    .search-btn,
    .clear-btn {

        height:
            42px;
    }

}

</style>

</head>


<body>


<!-- =========================================
     COMMON NAVBAR
========================================= -->

<?php include "common-navbar.php"; ?>


<!-- =========================================
     MAIN
========================================= -->

<main class="main-container">


    <!-- PAGE HEADER -->

    <div class="page-header">

        <h1>
            Skill Management
        </h1>

        <p>
            Add, remove, search and explore skills available on SkillConnect.
        </p>

    </div>


    <!-- MESSAGE -->

    <?php if ($message !== ""): ?>

        <div
            class="message <?= htmlspecialchars($messageType) ?>"
        >

            <?= htmlspecialchars($message) ?>

        </div>

    <?php endif; ?>


    <!-- =========================================
         ADD + SEARCH
    ========================================= -->

    <div class="top-grid">


        <!-- ADD SKILL -->

        <div class="card">

            <h2>
                ➕ Add Skill
            </h2>


            <form
                method="POST"
                action=""
            >


                <div class="form-group">

                    <label>
                        Skill Name
                    </label>

                    <input
                        type="text"
                        name="skill_name"
                        placeholder="Example: PHP"
                        maxlength="100"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>
                        Category
                    </label>

                    <input
                        type="text"
                        name="category"
                        placeholder="Example: Programming"
                        maxlength="100"
                        required
                    >

                </div>


                <button
                    type="submit"
                    name="add_skill"
                    class="primary-btn"
                >

                    Add Skill

                </button>

            </form>

        </div>


        <!-- SEARCH -->

        <div class="card">

            <h2>
                🔎 Search Skills
            </h2>


            <form
                method="GET"
                action=""
            >

                <div class="search-row">

                    <input
                        type="text"
                        name="search"
                        value="<?= htmlspecialchars($search) ?>"
                        placeholder="Search skill or category..."
                    >


                    <button
                        type="submit"
                        class="search-btn"
                    >

                        Search

                    </button>


                    <?php if ($search !== "" || $selectedCategory !== ""): ?>

                        <a
                            href="skill-management.php"
                            class="clear-btn"
                        >

                            Clear

                        </a>

                    <?php endif; ?>

                </div>

            </form>

        </div>


    </div>


    <!-- =========================================
         CATEGORIES
    ========================================= -->

    <div class="card category-card">


        <h2>
            📂 Skill Categories
        </h2>


        <div class="category-list">


            <a
                href="skill-management.php"
                class="category-btn
                <?= $selectedCategory === "" ? "active" : "" ?>"
            >

                All Skills

            </a>


            <?php foreach ($categories as $category): ?>

                <a
                    href="?category=<?= urlencode($category) ?>"
                    class="category-btn
                    <?= $selectedCategory === $category ? "active" : "" ?>"
                >

                    <?= htmlspecialchars($category) ?>

                </a>

            <?php endforeach; ?>


        </div>

    </div>


    <!-- =========================================
         ALL SKILLS
    ========================================= -->

    <div class="card">


        <div class="skills-header">

            <h2>
                🧩 Available Skills
            </h2>


            <span class="skill-count">

                <?= count($skills) ?>

                Skills

            </span>

        </div>


        <?php if (!empty($skills)): ?>


            <div class="skill-grid">


                <?php foreach ($skills as $skill): ?>


                    <div class="skill-item">


                        <div class="skill-name">

                            <?= htmlspecialchars(
                                $skill["name"]
                            ) ?>

                        </div>


                        <div class="skill-category">

                            <?= htmlspecialchars(
                                $skill["category"]
                            ) ?>

                        </div>


                        <div class="skill-bottom">


                            <span class="skill-date">

                                <?php

                                if (
                                    !empty(
                                        $skill["created_at"]
                                    )
                                ) {

                                    echo htmlspecialchars(
                                        date(
                                            "d M Y",
                                            strtotime(
                                                $skill["created_at"]
                                            )
                                        )
                                    );

                                } else {

                                    echo "Recently added";
                                }

                                ?>

                            </span>


                            <form
                                method="POST"
                                action=""
                                onsubmit="
                                    return confirm(
                                        'Are you sure you want to remove this skill?'
                                    );
                                "
                            >

                                <input
                                    type="hidden"
                                    name="skill_id"
                                    value="<?= (int)$skill["id"] ?>"
                                >


                                <button
                                    type="submit"
                                    name="remove_skill"
                                    class="remove-btn"
                                >

                                    🗑 Remove

                                </button>

                            </form>


                        </div>

                    </div>


                <?php endforeach; ?>


            </div>


        <?php else: ?>


            <div class="empty-box">

                <div class="empty-box-icon">
                    🔍
                </div>

                <p>
                    No skills found.
                </p>

            </div>


        <?php endif; ?>


    </div>


</main>


</body>

</html>