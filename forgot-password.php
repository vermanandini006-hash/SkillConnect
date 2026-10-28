<?php

session_start();

require "db.php";

$message = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $newPassword = $_POST["new_password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    /* =========================
       VALIDATION
    ========================= */

    if (empty($email) || empty($newPassword) || empty($confirmPassword)) {

        $message = "Please fill all fields.";
        $messageType = "error";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $message = "Please enter a valid email address.";
        $messageType = "error";

    } elseif (strlen($newPassword) < 6) {

        $message = "Password must be at least 6 characters.";
        $messageType = "error";

    } elseif ($newPassword !== $confirmPassword) {

        $message = "Passwords do not match.";
        $messageType = "error";

    } else {

        try {

            /* =========================
               CHECK EMAIL
            ========================= */

            $stmt = $conn->prepare(
                "SELECT id FROM users WHERE email = ? LIMIT 1"
            );

            $stmt->bind_param("s", $email);

            $stmt->execute();

            $result = $stmt->get_result();

            $user = $result->fetch_assoc();

            $stmt->close();


            if (!$user) {

                $message = "No account found with this email.";
                $messageType = "error";

            } else {

                /* =========================
                   HASH NEW PASSWORD
                ========================= */

                $hashedPassword = password_hash(
                    $newPassword,
                    PASSWORD_DEFAULT
                );


                /* =========================
                   UPDATE PASSWORD
                ========================= */

                $updateStmt = $conn->prepare(
                    "UPDATE users
                     SET password = ?
                     WHERE id = ?"
                );

                $updateStmt->bind_param(
                    "si",
                    $hashedPassword,
                    $user["id"]
                );


                if ($updateStmt->execute()) {

                    $message = "Password reset successfully! You can now login.";
                    $messageType = "success";

                } else {

                    $message = "Unable to reset password.";
                    $messageType = "error";
                }

                $updateStmt->close();
            }

        } catch (Throwable $e) {

            $message = "Something went wrong. Please try again.";
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

    <title>Forgot Password - SkillConnect</title>

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
            line-height: 1.5;
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
            margin-top: 5px;
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

        .back-login {
            text-align: center;
            margin-top: 20px;
        }

        .back-login a {
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

        <h2>Reset Password</h2>

        <p class="subtitle">
            Enter your registered email and create a new password.
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
                    placeholder="Enter your registered email"
                    value="<?= htmlspecialchars($_POST["email"] ?? "") ?>"
                    required
                >

            </div>


            <div class="form-group">

                <label>New Password</label>

                <input
                    type="password"
                    name="new_password"
                    placeholder="Minimum 6 characters"
                    required
                >

            </div>


            <div class="form-group">

                <label>Confirm New Password</label>

                <input
                    type="password"
                    name="confirm_password"
                    placeholder="Re-enter new password"
                    required
                >

            </div>


            <button type="submit">
                Reset Password
            </button>

        </form>


        <div class="back-login">

            <a href="login.php">
                ← Back to Login
            </a>

        </div>


     

    </div>

</div>

</body>

</html>