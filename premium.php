<?php

session_start();
require "db.php";

if (!isset($_SESSION["user"])) {
    header("Location: login.php");
    exit();
}

$currentUser = $_SESSION["user"];
$currentUserId = (int)($currentUser["id"] ?? 0);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>SkillConnect Premium</title>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f5faf7;
            color: #222;
        }

        .premium-page {
            min-height: 85vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px 20px;
        }

        .premium-card {
            width: 100%;
            max-width: 850px;
            background: white;
            border-radius: 22px;
            padding: 45px;
            text-align: center;
            box-shadow: 0 8px 30px rgba(0,0,0,0.08);
        }

        .premium-icon {
            width: 85px;
            height: 85px;
            margin: auto;
            border-radius: 50%;
            background: #e8f5ed;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            margin-bottom: 20px;
        }

        .premium-card h1 {
            color: #285b41;
            font-size: 34px;
            margin-bottom: 10px;
        }

        .premium-card .subtitle {
            color: #777;
            font-size: 15px;
            margin-bottom: 30px;
        }

        .price-box {
            background: #f4faf6;
            border: 1px solid #dcebe2;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 28px;
        }

        .price {
            color: #285b41;
            font-size: 40px;
            font-weight: bold;
        }

        .price span {
            font-size: 14px;
            color: #777;
            font-weight: normal;
        }

        .features {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 13px;
            text-align: left;
            margin: 25px 0 30px;
        }

        .feature {
            background: #f8fbf9;
            padding: 15px;
            border-radius: 10px;
            color: #365443;
            font-size: 14px;
        }

        .feature span {
            color: #3b7d5a;
            font-weight: bold;
            margin-right: 6px;
        }

        .upgrade-btn {
            display: inline-block;
            text-decoration: none;
            background: #3b7d5a;
            color: white;
            padding: 15px 35px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: bold;
            transition: 0.2s;
        }

        .upgrade-btn:hover {
            background: #285b41;
            transform: translateY(-2px);
        }

        .note {
            margin-top: 18px;
            color: #999;
            font-size: 12px;
        }

        @media(max-width: 600px) {

            .premium-card {
                padding: 30px 20px;
            }

            .premium-card h1 {
                font-size: 27px;
            }

            .features {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>

<body>

<?php
include "common-navbar.php";
?>

<div class="premium-page">

    <div class="premium-card">

        <div class="premium-icon">
            👑
        </div>

        <h1>
            SkillConnect Premium
        </h1>

        <p class="subtitle">
            Unlock advanced features and connect with students through live video calls.
        </p>

        <div class="price-box">

            <div class="price">
                ₹99
                <span>/ month</span>
            </div>

        </div>

        <div class="features">

            <div class="feature">
                <span>✓</span>
                Video Calling
            </div>

            <div class="feature">
                <span>✓</span>
                Screen Sharing
            </div>

            <div class="feature">
                <span>✓</span>
                Camera & Microphone
            </div>

            <div class="feature">
                <span>✓</span>
                Live Skill Demonstration
            </div>

            <div class="feature">
                <span>✓</span>
                Connect Through Video
            </div>

            <div class="feature">
                <span>✓</span>
                Premium Access
            </div>

        </div>

        <a
            href="payment.php"
            class="upgrade-btn"
        >
            👑 Upgrade to Premium
        </a>

       
    </div>

</div>

</body>

</html>