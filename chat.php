<?php

session_start();

require "db.php";

date_default_timezone_set("Asia/Kolkata");


/* =========================================
   LOGIN CHECK
========================================= */

if (!isset($_SESSION["user"])) {
    header("Location: login.php");
    exit();
}


/* =========================================
   CURRENT USER
========================================= */

$currentUser = $_SESSION["user"];

$currentEmail = $currentUser["email"] ?? "";
$currentName  = $currentUser["name"] ?? "Student";


/* =========================================
   HELPER
========================================= */

function e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}


/* =========================================
   AJAX REQUEST CHECK
========================================= */

$isAjax = isset($_GET["ajax"]);


/* =========================================
   AJAX - LOAD MESSAGES
========================================= */

if (
    $isAjax &&
    ($_GET["action"] ?? "") === "load_messages"
) {

    header("Content-Type: application/json; charset=UTF-8");

    $exchangeId = (int)($_GET["exchange_id"] ?? 0);

    if ($exchangeId <= 0) {

        echo json_encode([
            "success" => false,
            "message" => "Invalid exchange."
        ]);

        exit();
    }


    try {

        /* -----------------------------------------
           CHECK ACTIVE EXCHANGE
        ----------------------------------------- */

        $stmt = $conn->prepare(
            "SELECT
                id,
                student_one_email,
                student_one_name,
                student_two_email,
                student_two_name,
                skill

             FROM exchanges

             WHERE id = ?

             AND status = 'active'

AND EXISTS (
    SELECT 1
    FROM exchange_requests r
    WHERE r.id = exchanges.request_id
      AND r.status = 'accepted'
AND (
    (
        r.sender_id = exchanges.student_one_id
        AND r.receiver_id = exchanges.student_two_id
    )
    OR
    (
        r.sender_id = exchanges.student_two_id
        AND r.receiver_id = exchanges.student_one_id
    )
)
)

             AND (
                student_one_email = ?
                OR
                student_two_email = ?
             )

             LIMIT 1"
        );


        $stmt->bind_param(
            "iss",
            $exchangeId,
            $currentEmail,
            $currentEmail
        );


        $stmt->execute();


        $exchange =
            $stmt
            ->get_result()
            ->fetch_assoc();


        $stmt->close();


        if (!$exchange) {

            echo json_encode([
                "success" => false,
                "message" => "Exchange not found."
            ]);

            exit();
        }


        /* -----------------------------------------
           GET MESSAGES
        ----------------------------------------- */

        $stmt = $conn->prepare(
            "SELECT
                id,
                sender_email,
                sender_name,
                receiver_email,
                receiver_name,
                message,
                is_read,
                created_at

             FROM messages

             WHERE exchange_id = ?

             AND (
                sender_email = ?
                OR
                receiver_email = ?
             )

             ORDER BY id ASC"
        );


        $stmt->bind_param(
            "iss",
            $exchangeId,
            $currentEmail,
            $currentEmail
        );


        $stmt->execute();


        $result =
            $stmt->get_result();


        $messages = [];


        while ($row = $result->fetch_assoc()) {

            $messages[] = [

                "id" =>
                    (int)$row["id"],

                "sender_email" =>
                    $row["sender_email"],

                "sender_name" =>
                    $row["sender_name"],

                "receiver_email" =>
                    $row["receiver_email"],

                "receiver_name" =>
                    $row["receiver_name"],

                "message" =>
                    $row["message"],

                "is_read" =>
                    (int)$row["is_read"],

                "created_at" =>
                    $row["created_at"]
            ];
        }


        $stmt->close();


        /* -----------------------------------------
           MARK RECEIVED MESSAGES AS READ
        ----------------------------------------- */

        $stmt = $conn->prepare(
            "UPDATE messages

             SET is_read = 1

             WHERE exchange_id = ?

             AND receiver_email = ?

             AND is_read = 0"
        );


        $stmt->bind_param(
            "is",
            $exchangeId,
            $currentEmail
        );


        $stmt->execute();

        $stmt->close();


        echo json_encode([

            "success" => true,

            "messages" => $messages

        ]);

        exit();


    } catch (Throwable $e) {

        echo json_encode([

            "success" => false,

            "message" =>
                "Messages could not be loaded."

        ]);

        exit();
    }
}


/* =========================================
   AJAX - SEND MESSAGE
========================================= */

if (
    $isAjax &&
    ($_GET["action"] ?? "") === "send_message" &&
    $_SERVER["REQUEST_METHOD"] === "POST"
) {

    header("Content-Type: application/json; charset=UTF-8");


    $exchangeId =
        (int)($_POST["exchange_id"] ?? 0);


    $messageText =
        trim($_POST["message"] ?? "");


    if ($exchangeId <= 0) {

        echo json_encode([

            "success" => false,

            "message" =>
                "Invalid exchange."

        ]);

        exit();
    }


    if ($messageText === "") {

        echo json_encode([

            "success" => false,

            "message" =>
                "Please type a message."

        ]);

        exit();
    }


    try {

        /* -----------------------------------------
           CHECK EXCHANGE
        ----------------------------------------- */

        $stmt = $conn->prepare(
            "SELECT
                id,
                student_one_email,
                student_one_name,
                student_two_email,
                student_two_name,
                skill

             FROM exchanges

             WHERE id = ?

             AND status = 'active'

AND EXISTS (
    SELECT 1
    FROM exchange_requests r
    WHERE r.id = exchanges.request_id
      AND r.status = 'accepted'
      AND r.sender_id = exchanges.student_one_id
      AND r.receiver_id = exchanges.student_two_id
)

             AND (
                student_one_email = ?
                OR
                student_two_email = ?
             )

             LIMIT 1"
        );


        $stmt->bind_param(
            "iss",
            $exchangeId,
            $currentEmail,
            $currentEmail
        );


        $stmt->execute();


        $exchange =
            $stmt
            ->get_result()
            ->fetch_assoc();


        $stmt->close();


        if (!$exchange) {

            echo json_encode([

                "success" => false,

                "message" =>
                    "You cannot send message in this exchange."

            ]);

            exit();
        }


        /* -----------------------------------------
           FIND RECEIVER
        ----------------------------------------- */

        if (
            $exchange["student_one_email"]
            ===
            $currentEmail
        ) {

            $receiverEmail =
                $exchange["student_two_email"];

            $receiverName =
                $exchange["student_two_name"];

        } else {

            $receiverEmail =
                $exchange["student_one_email"];

            $receiverName =
                $exchange["student_one_name"];
        }


        /* -----------------------------------------
           INSERT MESSAGE
        ----------------------------------------- */

        $stmt = $conn->prepare(
            "INSERT INTO messages
            (
                exchange_id,
                sender_email,
                sender_name,
                receiver_email,
                receiver_name,
                message,
                is_read
            )

            VALUES (?, ?, ?, ?, ?, ?, 0)"
        );


        $stmt->bind_param(
            "isssss",
            $exchangeId,
            $currentEmail,
            $currentName,
            $receiverEmail,
            $receiverName,
            $messageText
        );


        $stmt->execute();


        $newMessageId =
            $stmt->insert_id;


        $stmt->close();


        /* -----------------------------------------
           GET NEW MESSAGE
        ----------------------------------------- */

        $stmt = $conn->prepare(
            "SELECT
                id,
                sender_email,
                sender_name,
                receiver_email,
                receiver_name,
                message,
                is_read,
                created_at

             FROM messages

             WHERE id = ?

             LIMIT 1"
        );


        $stmt->bind_param(
            "i",
            $newMessageId
        );


        $stmt->execute();


        $newMessage =
            $stmt
            ->get_result()
            ->fetch_assoc();


        $stmt->close();


        echo json_encode([

            "success" => true,

            "message_data" =>
                $newMessage

        ]);

        exit();


    } catch (Throwable $e) {

        echo json_encode([

            "success" => false,

            "message" =>
                "Message could not be sent.",

            "error" =>
                $e->getMessage()

        ]);

        exit();
    }
}


/* =========================================
   NORMAL PAGE
========================================= */

$exchangeId =
    (int)($_GET["exchange_id"] ?? 0);


$activeExchanges = [];

$exchange = null;

$otherName = "";

$otherEmail = "";

$error = "";


/* =========================================
   LOAD ACTIVE EXCHANGES
========================================= */

try {

    $stmt = $conn->prepare(
        "SELECT
            id,
            student_one_email,
            student_one_name,
            student_two_email,
            student_two_name,
            skill,
            status,
            created_at

         FROM exchanges

         WHERE status = 'active'
AND EXISTS (
    SELECT 1
    FROM exchange_requests r
    WHERE r.id = exchanges.request_id
      AND r.status = 'accepted'
      AND (
          (r.sender_id = exchanges.student_one_id
           AND r.receiver_id = exchanges.student_two_id)
          OR
          (r.sender_id = exchanges.student_two_id
           AND r.receiver_id = exchanges.student_one_id)
      )
)
         AND (
            student_one_email = ?
            OR
            student_two_email = ?
         )

         ORDER BY created_at DESC"
    );


    $stmt->bind_param(
        "ss",
        $currentEmail,
        $currentEmail
    );


    $stmt->execute();


    $result =
        $stmt->get_result();


    while ($row = $result->fetch_assoc()) {

        $activeExchanges[] =
            $row;
    }


    $stmt->close();


} catch (Throwable $e) {

    $error =
        "Unable to load exchanges.";
}


/* =========================================
   OPEN FIRST CHAT FROM NAVBAR
========================================= */

if (
    $exchangeId <= 0 &&
    !empty($activeExchanges)
) {

    $exchangeId =
        (int)$activeExchanges[0]["id"];
}


/* =========================================
   LOAD SELECTED EXCHANGE
========================================= */

if ($exchangeId > 0) {

    try {

        $stmt = $conn->prepare(
            "SELECT
                id,
                student_one_email,
                student_one_name,
                student_two_email,
                student_two_name,
                skill,
                status

             FROM exchanges

             WHERE id = ?

             AND status = 'active'
             AND EXISTS (
    SELECT 1
    FROM exchange_requests r
    WHERE r.id = exchanges.request_id
      AND r.status = 'accepted'
      AND (
          (r.sender_id = exchanges.student_one_id
           AND r.receiver_id = exchanges.student_two_id)
          OR
          (r.sender_id = exchanges.student_two_id
           AND r.receiver_id = exchanges.student_one_id)
      )
)

             AND (
                student_one_email = ?
                OR
                student_two_email = ?
             )

             LIMIT 1"
        );


        $stmt->bind_param(
            "iss",
            $exchangeId,
            $currentEmail,
            $currentEmail
        );


        $stmt->execute();


        $exchange =
            $stmt
            ->get_result()
            ->fetch_assoc();


        $stmt->close();


        if ($exchange) {

            if (
                $exchange["student_one_email"]
                ===
                $currentEmail
            ) {

                $otherEmail =
                    $exchange["student_two_email"];

                $otherName =
                    $exchange["student_two_name"];

            } else {

                $otherEmail =
                    $exchange["student_one_email"];

                $otherName =
                    $exchange["student_one_name"];
            }

        } else {

            $error =
                "This exchange is not available.";
        }


    } catch (Throwable $e) {

        $error =
            "Unable to open chat.";
    }
}


/* =========================================
   LOAD NAVBAR ONLY FOR NORMAL PAGE
========================================= */

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
    Chat - SkillConnect
</title>


<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}


body {

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background:
        linear-gradient(
            135deg,
            #f5faf7,
            #ffffff
        );

    color: #1f3d2d;

    min-height: 100vh;
}


/* =========================================
   MAIN
========================================= */

.container {

    width: 92%;

    max-width: 1200px;

    margin: 35px auto;
}


.title {

    margin-bottom: 25px;
}


.title h1 {

    color: #285b41;

    margin-bottom: 7px;
}


.title p {

    color: #718078;
}


/* =========================================
   ALERT
========================================= */

.alert {

    padding: 13px 18px;

    border-radius: 10px;

    margin-bottom: 20px;

    text-align: center;

    font-weight: 600;

    background: #ffe8e8;

    color: #a33232;
}


/* =========================================
   CHAT LAYOUT
========================================= */

.chat-layout {

    display: grid;

    grid-template-columns:
        280px 1fr;

    gap: 20px;

    min-height: 620px;
}


/* =========================================
   EXCHANGE LIST
========================================= */

.exchange-list {

    background: white;

    border-radius: 18px;

    padding: 20px;

    box-shadow:
        0 6px 25px
        rgba(0,0,0,0.07);

    height: fit-content;

    max-height: 620px;

    overflow-y: auto;
}


.exchange-list h3 {

    color: #285b41;

    margin-bottom: 15px;
}


.exchange-item {

    display: block;

    text-decoration: none;

    color: #285b41;

    padding: 14px;

    border-radius: 12px;

    margin-bottom: 8px;

    border: 1px solid transparent;

    transition: 0.2s;
}


.exchange-item:hover {

    background: #edf7f1;
}


.exchange-item.active {

    background: #e3f3e9;

    border-color: #c6e4d1;
}


.exchange-name {

    font-weight: bold;

    margin-bottom: 5px;
}


.exchange-skill {

    font-size: 12px;

    color: #718078;
}


/* =========================================
   CHAT BOX
========================================= */

.chat-box {

    background: white;

    border-radius: 18px;

    box-shadow:
        0 6px 25px
        rgba(0,0,0,0.07);

    overflow: hidden;

    display: flex;

    flex-direction: column;

    min-height: 620px;
}


/* =========================================
   HEADER
========================================= */

.chat-header {

    padding: 18px 22px;

    border-bottom:
        1px solid #e6eee9;

    display: flex;

    align-items: center;
}


.avatar {

    width: 48px;

    height: 48px;

    border-radius: 50%;

    background:
        linear-gradient(
            135deg,
            #3b7d5a,
            #79b894
        );

    color: white;

    display: flex;

    align-items: center;

    justify-content: center;

    font-weight: bold;

    font-size: 18px;

    margin-right: 12px;
}


.person-name {

    font-weight: bold;

    color: #285b41;
}


.chat-skill {

    color: #718078;

    font-size: 12px;

    margin-top: 4px;
}


.live-status {

    margin-left: auto;

    color: #3b7d5a;

    font-size: 12px;

    font-weight: bold;

    display: flex;

    align-items: center;

    gap: 6px;
}


.live-dot {

    width: 8px;

    height: 8px;

    border-radius: 50%;

    background: #4caf50;

    animation: pulse 1.5s infinite;
}


@keyframes pulse {

    0% {
        opacity: 1;
    }

    50% {
        opacity: .35;
    }

    100% {
        opacity: 1;
    }
}


/* =========================================
   MESSAGES
========================================= */

.messages {

    flex: 1;

    min-height: 430px;

    max-height: 500px;

    overflow-y: auto;

    padding: 25px;

    background: #f8fbf9;
}


.message-row {

    display: flex;

    margin-bottom: 14px;
}


.message-row.mine {

    justify-content: flex-end;
}


.message-bubble {

    max-width: 70%;

    background: white;

    padding: 11px 15px;

    border-radius: 15px;

    box-shadow:
        0 2px 7px
        rgba(0,0,0,0.05);
}


.message-row.mine
.message-bubble {

    background: #3b7d5a;

    color: white;

    border-bottom-right-radius: 4px;
}


.message-row:not(.mine)
.message-bubble {

    border-bottom-left-radius: 4px;
}


.message-text {

    font-size: 14px;

    line-height: 1.45;

    word-break: break-word;
}


.message-time {

    font-size: 10px;

    margin-top: 6px;

    opacity: .7;

    text-align: right;
}


.empty-messages {

    min-height: 400px;

    display: flex;

    align-items: center;

    justify-content: center;

    text-align: center;

    color: #8a9690;
}


.empty-icon {

    font-size: 45px;

    margin-bottom: 10px;
}


/* =========================================
   INPUT
========================================= */

.chat-input {

    display: flex;

    gap: 10px;

    padding: 15px;

    border-top:
        1px solid #e6eee9;

    background: white;
}


.chat-input textarea {

    flex: 1;

    height: 48px;

    resize: none;

    padding: 13px;

    border:
        1px solid #d7e4dc;

    border-radius: 12px;

    outline: none;

    font-family: Arial, sans-serif;

    font-size: 14px;
}


.chat-input textarea:focus {

    border-color: #3b7d5a;
}


.send-btn {

    width: 100px;

    border: none;

    border-radius: 12px;

    background: #3b7d5a;

    color: white;

    font-weight: bold;

    cursor: pointer;
}


.send-btn:hover {

    background: #285b41;
}


.send-btn:disabled {

    opacity: .6;

    cursor: not-allowed;
}


/* =========================================
   NO CHAT
========================================= */

.no-chat {

    background: white;

    min-height: 620px;

    border-radius: 18px;

    display: flex;

    align-items: center;

    justify-content: center;

    text-align: center;

    padding: 30px;

    box-shadow:
        0 6px 25px
        rgba(0,0,0,0.07);
}


.no-chat h2 {

    color: #285b41;

    margin-bottom: 10px;
}


.no-chat p {

    color: #718078;

    line-height: 1.6;
}


.no-chat a {

    display: inline-block;

    margin-top: 18px;

    padding: 11px 18px;

    border-radius: 10px;

    background: #3b7d5a;

    color: white;

    text-decoration: none;

    font-weight: bold;
}


/* =========================================
   MOBILE
========================================= */

@media(max-width:800px) {

    .chat-layout {

        grid-template-columns: 1fr;
    }


    .exchange-list {

        max-height: 230px;
    }


    .chat-box {

        min-height: 560px;
    }
}


@media(max-width:550px) {

    .container {

        width: 94%;
    }


    .chat-input {

        flex-direction: column;
    }


    .send-btn {

        width: 100%;

        height: 45px;
    }


    .message-bubble {

        max-width: 85%;
    }


    .live-status {

        display: none;
    }
}

</style>

</head>


<body>


<?php

/*
=================================================
COMMON NAVBAR
IMPORTANT:
It is loaded AFTER AJAX handling.
Therefore AJAX gets clean JSON.
=================================================
*/

require "common-navbar.php";

?>


<div class="container">


    <div class="title">

        <h1>
            Student Chat 💬
        </h1>

        <p>
            Chat privately with your skill exchange partner.
        </p>

    </div>


    <?php if ($error !== ""): ?>

        <div class="alert">

            <?= e($error) ?>

        </div>

    <?php endif; ?>


    <div class="chat-layout">


        <!-- =====================================
             ACTIVE EXCHANGES
        ====================================== -->

        <div class="exchange-list">

            <h3>
                My Exchanges
            </h3>


            <?php if (!empty($activeExchanges)): ?>


                <?php foreach (
                    $activeExchanges
                    as $active
                ): ?>


                    <?php

                    if (
                        $active["student_one_email"]
                        ===
                        $currentEmail
                    ) {

                        $personName =
                            $active["student_two_name"];

                    } else {

                        $personName =
                            $active["student_one_name"];
                    }


                    $activeId =
                        (int)$active["id"];

                    ?>


                    <a
                        href="chat.php?exchange_id=<?= $activeId ?>"
                        class="
                            exchange-item
                            <?= $activeId === $exchangeId
                                ? "active"
                                : ""
                            ?>
                        "
                    >

                        <div class="exchange-name">

                            👤
                            <?= e($personName) ?>

                        </div>


                        <div class="exchange-skill">

                            🤝
                            <?= e(
                                $active["skill"]
                                ?? "Skill Exchange"
                            ) ?>

                        </div>

                    </a>


                <?php endforeach; ?>


            <?php else: ?>


                <div
                    style="
                        color:#85928b;
                        font-size:13px;
                        line-height:1.6;
                    "
                >

                    No active exchanges yet.

                    <br><br>

                    Accept a skill request first
                    to start chatting.

                </div>


            <?php endif; ?>


        </div>


        <!-- =====================================
             CHAT
        ====================================== -->

        <?php if ($exchange): ?>


            <div class="chat-box">


                <div class="chat-header">


                    <div class="avatar">

                        <?= e(
                            strtoupper(
                                substr(
                                    trim($otherName),
                                    0,
                                    1
                                )
                            )
                        ) ?>

                    </div>


                    <div>

                        <div class="person-name">

                            <?= e($otherName) ?>

                        </div>


                        <div class="chat-skill">

                            Skill:

                            <?= e(
                                $exchange["skill"]
                                ?? "Exchange"
                            ) ?>

                        </div>

                    </div>


                    <div class="live-status">

                        <span class="live-dot"></span>

                        Live Chat

                    </div>

                </div>


                <div
                    class="messages"
                    id="messages"
                >

                    <div class="empty-messages">

                        <div>

                            <div class="empty-icon">
                                💬
                            </div>

                            Loading messages...

                        </div>

                    </div>

                </div>


                <form
                    class="chat-input"
                    id="chatForm"
                >

                    <textarea
                        id="messageBox"
                        name="message"
                        placeholder="Type your message..."
                        maxlength="5000"
                        required
                    ></textarea>


                    <button
                        type="submit"
                        class="send-btn"
                        id="sendButton"
                    >
                        Send
                    </button>

                </form>


            </div>


        <?php else: ?>


            <div class="no-chat">

                <div>

                    <h2>
                        Select a Chat 💬
                    </h2>


                    <?php if (
                        !empty($activeExchanges)
                    ): ?>

                        <p>

                            Select one of your active
                            skill exchanges from the left
                            to start chatting.

                        </p>


                    <?php else: ?>

                        <p>

                            You don't have any active
                            skill exchange yet.

                        </p>


                        <a href="students.php">

                            Find Students

                        </a>


                    <?php endif; ?>

                </div>

            </div>


        <?php endif; ?>


    </div>

</div>


<?php if ($exchange): ?>

<script>


/* =========================================
   CHAT VARIABLES
========================================= */

const exchangeId =
    <?= (int)$exchangeId ?>;


const currentEmail =
    <?= json_encode($currentEmail) ?>;


const messagesBox =
    document.getElementById("messages");


const messageBox =
    document.getElementById("messageBox");


const chatForm =
    document.getElementById("chatForm");


const sendButton =
    document.getElementById("sendButton");


/* =========================================
   ESCAPE HTML
========================================= */

function escapeHtml(text)
{

    const div =
        document.createElement("div");

    div.textContent =
        text ?? "";

    return div.innerHTML;
}


/* =========================================
   TIME
========================================= */

function formatTime(timestamp)
{

    if (!timestamp) {
        return "";
    }


    const date =
        new Date(
            timestamp.replace(
                " ",
                "T"
            )
        );


    if (isNaN(date.getTime())) {

        return timestamp;
    }


    return date.toLocaleString(
        "en-IN",
        {
            day: "2-digit",
            month: "short",
            hour: "2-digit",
            minute: "2-digit",
            hour12: true
        }
    );
}


/* =========================================
   RENDER
========================================= */

function renderMessages(messageList)
{

    const shouldScroll =
        (
            messagesBox.scrollHeight
            -
            messagesBox.scrollTop
            -
            messagesBox.clientHeight
        ) < 150;


    if (!messageList.length) {

        messagesBox.innerHTML = `

            <div class="empty-messages">

                <div>

                    <div class="empty-icon">
                        💬
                    </div>

                    <strong>
                        Start chatting
                    </strong>

                    <p style="margin-top:7px;">
                        Send your first message.
                    </p>

                </div>

            </div>

        `;

        return;
    }


    let html = "";


    messageList.forEach(
        function(msg)
        {

            const mine =
                msg.sender_email
                ===
                currentEmail;


            html += `

                <div class="
                    message-row
                    ${mine ? "mine" : ""}
                ">

                    <div class="message-bubble">

                        <div class="message-text">

                            ${
                                escapeHtml(
                                    msg.message
                                ).replace(
                                    /\n/g,
                                    "<br>"
                                )
                            }

                        </div>

                        <div class="message-time">

                            ${
                                escapeHtml(
                                    formatTime(
                                        msg.created_at
                                    )
                                )
                            }

                        </div>

                    </div>

                </div>

            `;
        }
    );


    messagesBox.innerHTML =
        html;


    if (shouldScroll) {

        messagesBox.scrollTop =
            messagesBox.scrollHeight;
    }
}


/* =========================================
   LOAD MESSAGES
========================================= */

async function loadMessages(
    forceScroll = false
)
{

    try {

        const response =
            await fetch(
                "chat.php?ajax=1&action=load_messages&exchange_id="
                +
                exchangeId
                +
                "&time="
                +
                Date.now(),
                {
                    cache: "no-store"
                }
            );


        const data =
            await response.json();


        if (data.success) {

            renderMessages(
                data.messages || []
            );


            if (forceScroll) {

                messagesBox.scrollTop =
                    messagesBox.scrollHeight;
            }

        } else {

            console.log(
                data.message
            );
        }


    } catch (error) {

        console.log(
            "Load messages error:",
            error
        );
    }
}


/* =========================================
   SEND MESSAGE
========================================= */

chatForm.addEventListener(
    "submit",
    async function(event)
    {

        event.preventDefault();


        const message =
            messageBox.value.trim();


        if (!message) {

            return;
        }


        sendButton.disabled =
            true;


        sendButton.textContent =
            "Sending...";


        const formData =
            new FormData();


        formData.append(
            "exchange_id",
            exchangeId
        );


        formData.append(
            "message",
            message
        );


        try {

            const response =
                await fetch(
                    "chat.php?ajax=1&action=send_message",
                    {
                        method: "POST",

                        body: formData
                    }
                );


            const text =
                await response.text();


            let data;


            try {

                data =
                    JSON.parse(text);

            } catch (jsonError) {

                console.log(
                    "SERVER RESPONSE:",
                    text
                );

                alert(
                    "Server ne valid response nahi ditta. Console check karo."
                );

                return;
            }


            if (data.success) {

                messageBox.value = "";

                await loadMessages(true);

                messageBox.focus();

            } else {

                alert(
                    data.message
                    ||
                    "Message could not be sent."
                );
            }


        } catch (error) {

            console.log(
                "SEND ERROR:",
                error
            );

            alert(
                "Something went wrong while sending the message."
            );

        } finally {

            sendButton.disabled =
                false;

            sendButton.textContent =
                "Send";
        }

    }
);


/* =========================================
   ENTER TO SEND
========================================= */

messageBox.addEventListener(
    "keydown",
    function(event)
    {

        if (
            event.key === "Enter"
            &&
            !event.shiftKey
        ) {

            event.preventDefault();


            if (
                this.value.trim()
                !== ""
            ) {

                chatForm.requestSubmit();
            }
        }

    }
);


/* =========================================
   FIRST LOAD
========================================= */

loadMessages(true);


/* =========================================
   REAL-TIME CHAT
   EVERY 2 SECONDS
========================================= */

setInterval(
    function()
    {

        loadMessages(false);

    },
    2000
);

</script>

<?php endif; ?>


</body>

</html>