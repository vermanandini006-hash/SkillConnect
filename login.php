<?php

session_start();

require "db.php";

$message = "";
$messageType = "";


/* =========================
   LOGIN
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";


    if (empty($email) || empty($password)) {

        $message = "Please enter email and password.";
        $messageType = "error";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $message = "Please enter a valid email address.";
        $messageType = "error";

    } else {

        try {

            /* =========================
               FIND USER BY EMAIL
            ========================= */

            $stmt = $conn->prepare(
                "SELECT id, name, email, password, course, college, status
                 FROM users
                 WHERE email = ?
                 LIMIT 1"
            );

            $stmt->bind_param("s", $email);

            $stmt->execute();

            $result = $stmt->get_result();

            $user = $result->fetch_assoc();


            /* =========================
               CHECK USER
            ========================= */

            if (!$user) {

                $message = "No account found with this email.";
                $messageType = "error";

            } elseif (!password_verify($password, $user["password"])) {

                $message = "Incorrect password.";
                $messageType = "error";

            } else {

                /* =========================
                   UPDATE ONLINE STATUS
                ========================= */

                $updateStmt = $conn->prepare(
                    "UPDATE users
                     SET status = 'online'
                     WHERE id = ?"
                );

                $updateStmt->bind_param(
                    "i",
                    $user["id"]
                );

                $updateStmt->execute();

                $updateStmt->close();


                /* =========================
                   SAVE USER IN SESSION
                ========================= */

                $_SESSION["user"] = [

                    "id" => (string)$user["id"],

                    "name" => $user["name"],

                    "email" => $user["email"],

                    "course" => $user["course"],

                    "college" => $user["college"]

                ];


                /* =========================
                   LOGIN SUCCESS
                ========================= */

                header("Location: dashboard.php");
                exit();
            }


            $stmt->close();

        } catch (Throwable $e) {

            $message = "Unable to login right now.";
            $messageType = "error";
        }
    }
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

    <title>Login - SkillConnect</title>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #f4fbf7, #e8f5ed);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 25px;
        }

        .container {
            width: 100%;
            max-width: 430px;
        }

        .logo {
            text-align: center;
            margin-bottom: 22px;
        }

        .logo h1 {
            color: #2f7653;
            font-size: 35px;
        }

        .logo p {
            color: #777;
            margin-top: 5px;
        }

        .card {
            background: white;
            padding: 35px;
            border-radius: 20px;
            box-shadow: 0 10px 35px rgba(0,0,0,0.08);
        }

        .card h2 {
            text-align: center;
            color: #285b41;
            margin-bottom: 8px;
        }

        .subtitle {
            text-align: center;
            color: #777;
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            color: #333;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 14px;
            border: 1px solid #d8e5dd;
            border-radius: 10px;
            outline: none;
            font-size: 15px;
        }

        input:focus {
            border-color: #3b7d5a;
        }

        button {
            width: 100%;
            padding: 14px;
            background: #3b7d5a;
            border: none;
            color: white;
            font-size: 16px;
            font-weight: bold;
            border-radius: 10px;
            cursor: pointer;
        }

        button:hover {
            background: #285b41;
        }

        .message {
            padding: 12px;
            border-radius: 9px;
            margin-bottom: 18px;
            text-align: center;
        }

        .success {
            background: #e7f7ed;
            color: #217044;
        }

        .error {
            background: #fdeaea;
            color: #b42318;
        }

        .forgot {
            text-align: right;
            margin: 10px 0 20px;
        }

        .forgot a {
            color: #3b7d5a;
            text-decoration: none;
            font-size: 14px;
        }

        .forgot a:hover {
            text-decoration: underline;
        }

        .register {
            text-align: center;
            margin-top: 20px;
            color: #666;
        }

        .register a {
            color: #2f7653;
            font-weight: bold;
            text-decoration: none;
        }

        .status {
            text-align: center;
            color: #217044;
            font-size: 13px;
            margin-top: 15px;
        }

    </style>

</head>

<body>

<div class="container">

    <div class="logo">

        <h1>SkillConnect</h1>

        <p>
            Student Skill Exchange Platform
        </p>

    </div>


    <div class="card">

        <h2>Welcome Back</h2>

        <p class="subtitle">
            Login to continue your skill journey.
        </p>


        <?php if (!empty($message)): ?>

            <div class="message <?= htmlspecialchars($messageType) ?>">

                <?= htmlspecialchars($message) ?>

            </div>

        <?php endif; ?>


        <form method="POST">

            <div class="form-group">

                <label>Email</label>

                <input
                    type="email"
                    name="email"
                    placeholder="Enter your email"
                    value="<?= htmlspecialchars($_POST["email"] ?? "") ?>"
                    required
                >

            </div>


            <div class="form-group">

                <label>Password</label>

                <input
                    type="password"
                    name="password"
                    placeholder="Enter your password"
                    required
                >

            </div>


            <!-- =========================
                 FORGOT PASSWORD
            ========================= -->

            <div class="forgot">

                <a href="forgot-password.php">
                    Forgot Password?
                </a>

            </div>


            <button type="submit">
                Login
            </button>

        </form>


        <div class="register">

            Don't have an account?

            <a href="register.php">
                Create Account
            </a>

        </div>


        

    </div>

</div>

</body>

</html>