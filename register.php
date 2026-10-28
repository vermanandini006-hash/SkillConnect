<?php

session_start();

require_once "db.php";

$message = "";
$messageType = "";


if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $course = trim($_POST["course"] ?? "");
    $college = trim($_POST["college"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";


    if ($name == "" || $email == "" || $course == "" ||
        $college == "" || $password == "" ||
        $confirmPassword == "") {

        $message = "Please fill all fields.";
        $messageType = "error";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $message = "Please enter a valid email.";
        $messageType = "error";

    } elseif (strlen($password) < 6) {

        $message = "Password must be at least 6 characters.";
        $messageType = "error";

    } elseif ($password != $confirmPassword) {

        $message = "Passwords do not match.";
        $messageType = "error";

    } else {

        /* CHECK EMAIL */

        $check = $conn->prepare(
            "SELECT id FROM users WHERE email = ?"
        );

        $check->bind_param("s", $email);

        $check->execute();

        $result = $check->get_result();


        if ($result->num_rows > 0) {

            $message = "This email is already registered.";
            $messageType = "error";

        } else {

            /* PASSWORD */

            $hashedPassword = password_hash(
                $password,
                PASSWORD_DEFAULT
            );


            /* INSERT */

            $stmt = $conn->prepare(
                "INSERT INTO users
                (
                    name,
                    email,
                    password,
                    course,
                    college
                )
                VALUES
                (?, ?, ?, ?, ?)"
            );


            $stmt->bind_param(
                "sssss",
                $name,
                $email,
                $hashedPassword,
                $course,
                $college
            );


            if ($stmt->execute()) {

                /*
                   REGISTER SUCCESSFUL
                */

                header(
                    "Location: login.php?registered=1"
                );

                exit();

            } else {

                $message =
                    "Registration failed: "
                    . $stmt->error;

                $messageType = "error";
            }


            $stmt->close();

        }

        $check->close();

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

<title>Register - SkillConnect</title>


<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {

    min-height: 100vh;

    font-family: Arial, sans-serif;

    background:
        linear-gradient(
            135deg,
            #f4fbf7,
            #e5f3ea
        );

    display: flex;

    justify-content: center;

    align-items: center;

    padding: 30px 15px;
}


.register-card {

    width: 100%;

    max-width: 520px;

    background: white;

    padding: 35px;

    border-radius: 20px;

    box-shadow:
        0 15px 40px
        rgba(0,0,0,0.10);
}


.logo {

    text-align: center;

    color: #3b7d5a;

    font-size: 30px;

    font-weight: bold;

    margin-bottom: 7px;
}


.subtitle {

    text-align: center;

    color: #7b8780;

    font-size: 14px;

    margin-bottom: 25px;
}


h1 {

    text-align: center;

    color: #285b41;

    margin-bottom: 8px;
}


.description {

    text-align: center;

    color: #7b8780;

    font-size: 14px;

    margin-bottom: 25px;
}


.message {

    padding: 12px;

    border-radius: 9px;

    margin-bottom: 20px;

    text-align: center;

    font-size: 14px;
}


.message.error {

    background: #fff0ef;

    color: #b8322b;

}


.form-row {

    display: grid;

    grid-template-columns: 1fr 1fr;

    gap: 15px;
}


.form-group {

    margin-bottom: 18px;
}


label {

    display: block;

    margin-bottom: 7px;

    color: #285b41;

    font-size: 14px;

    font-weight: 600;
}


input {

    width: 100%;

    height: 46px;

    padding: 0 12px;

    border:
        1px solid #d7e4dc;

    border-radius: 9px;

    outline: none;

    font-size: 14px;
}


input:focus {

    border-color: #3b7d5a;

    box-shadow:
        0 0 0 3px
        rgba(59,125,90,0.10);
}


button {

    width: 100%;

    height: 48px;

    border: none;

    border-radius: 9px;

    background: #3b7d5a;

    color: white;

    font-size: 15px;

    font-weight: bold;

    cursor: pointer;
}


button:hover {

    background: #285b41;
}


.login-text {

    text-align: center;

    margin-top: 20px;

    color: #7b8780;

    font-size: 14px;
}


.login-text a {

    color: #3b7d5a;

    font-weight: bold;

    text-decoration: none;
}


@media(max-width:600px) {

    .register-card {

        padding: 25px 20px;
    }

    .form-row {

        grid-template-columns: 1fr;

        gap: 0;
    }

}

</style>

</head>


<body>


<div class="register-card">


    <div class="logo">
        SkillConnect
    </div>


    <div class="subtitle">
        Student Skill Exchange Platform
    </div>


    <h1>
        Create Account
    </h1>


    <p class="description">
        Join SkillConnect and start exchanging skills.
    </p>


    <?php if ($message != ""): ?>

        <div class="message <?= $messageType ?>">

            <?= htmlspecialchars($message) ?>

        </div>

    <?php endif; ?>


    <form method="POST">


        <div class="form-row">


            <div class="form-group">

                <label>
                    Full Name
                </label>

                <input
                    type="text"
                    name="name"
                    placeholder="Enter your name"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    Email
                </label>

                <input
                    type="email"
                    name="email"
                    placeholder="Enter your email"
                    required
                >

            </div>


        </div>


        <div class="form-row">


            <div class="form-group">

                <label>
                    Course
                </label>

                <input
                    type="text"
                    name="course"
                    placeholder="e.g. BCA"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    College
                </label>

                <input
                    type="text"
                    name="college"
                    placeholder="Enter college"
                    required
                >

            </div>


        </div>


        <div class="form-row">


            <div class="form-group">

                <label>
                    Password
                </label>

                <input
                    type="password"
                    name="password"
                    placeholder="Minimum 6 characters"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    Confirm Password
                </label>

                <input
                    type="password"
                    name="confirm_password"
                    placeholder="Repeat password"
                    required
                >

            </div>


        </div>


        <button type="submit">

            Create Account

        </button>


    </form>


    <div class="login-text">

        Already have an account?

        <a href="login.php">
            Login
        </a>

    </div>


</div>


</body>

</html>