```php
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>SkillConnect | Student Skill Exchange Platform</title>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f7fbf9;
            color: #173b2b;
        }

        /* ================= NAVBAR ================= */

        .navbar {
            height: 75px;
            padding: 0 7%;

            display: flex;
            align-items: center;
            justify-content: space-between;

            background: white;

            position: sticky;
            top: 0;
            z-index: 1000;

            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.06);
        }

        .logo {
            font-size: 26px;
            font-weight: 800;
            color: #173b2b;
        }

        .logo span {
            color: #2f8f61;
        }

        .nav-links {
            display: flex;
            gap: 30px;
        }

        .nav-links a {
            text-decoration: none;
            color: #52665b;
            font-size: 15px;
            font-weight: 600;
            transition: 0.3s;
        }

        .nav-links a:hover,
        .nav-links a.active {
            color: #2f8f61;
        }

        .nav-buttons {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .login-btn,
        .register-btn {
            text-decoration: none;
            padding: 10px 19px;
            border-radius: 8px;
            font-weight: 600;
        }

        .login-btn {
            color: #2f8f61;
        }

        .register-btn {
            background: #2f8f61;
            color: white;
        }

        .register-btn:hover {
            background: #26764f;
        }

        /* ================= HERO ================= */

        .hero {
            min-height: 650px;

            padding: 80px 8%;

            display: flex;
            align-items: center;
            justify-content: space-between;

            gap: 60px;

            background: linear-gradient(
                135deg,
                #eef9f2,
                #ffffff
            );
        }

        .hero-content {
            width: 53%;
        }

        .badge {
            display: inline-block;

            padding: 9px 16px;

            margin-bottom: 20px;

            background: #e0f3e7;

            color: #26744e;

            border-radius: 30px;

            font-size: 14px;
            font-weight: 600;
        }

        .hero h1 {
            font-size: 56px;
            line-height: 1.08;

            margin-bottom: 25px;

            color: #163d2c;
        }

        .hero h1 span {
            color: #2f9662;
        }

        .hero-description {
            max-width: 620px;

            font-size: 18px;

            line-height: 1.7;

            color: #60736a;

            margin-bottom: 30px;
        }

        .hero-buttons {
            display: flex;
            gap: 15px;

            margin-bottom: 45px;
        }

        .primary-btn,
        .secondary-btn {
            text-decoration: none;

            padding: 14px 23px;

            border-radius: 9px;

            font-weight: 700;

            transition: 0.3s;
        }

        .primary-btn {
            background: #2f8f61;
            color: white;

            box-shadow: 0 8px 20px rgba(47, 143, 97, 0.2);
        }

        .primary-btn:hover {
            transform: translateY(-2px);
            background: #26764f;
        }

        .secondary-btn {
            background: white;
            color: #2f8f61;

            border: 1px solid #cde4d7;
        }

        .secondary-btn:hover {
            background: #eaf7ef;
        }

        /* ================= STATS ================= */

        .hero-stats {
            display: flex;
            gap: 45px;
        }

        .hero-stats h3 {
            font-size: 25px;
            color: #2f8f61;
        }

        .hero-stats p {
            margin-top: 4px;
            color: #718078;
            font-size: 14px;
        }

        /* ================= HERO CARD ================= */

        .hero-visual {
            width: 43%;

            min-height: 480px;

            position: relative;

            display: flex;

            align-items: center;

            justify-content: center;
        }

        .main-card {
            width: 370px;

            padding: 27px;

            background: white;

            border-radius: 20px;

            box-shadow:
                0 20px 50px rgba(35, 93, 62, 0.15);

            position: relative;

            z-index: 2;

            transition: 0.4s;
        }

        .main-card:hover {
            transform: translateY(-6px);
        }

        .card-top {
            color: #66766e;
            font-size: 14px;

            margin-bottom: 22px;
        }

        .online-dot {
            width: 9px;
            height: 9px;

            display: inline-block;

            background: #35b86f;

            border-radius: 50%;

            margin-right: 7px;
        }

        .profile {
            display: flex;

            align-items: center;

            gap: 15px;

            padding-bottom: 20px;

            border-bottom: 1px solid #edf2ef;
        }

        .profile-img {
            width: 55px;
            height: 55px;

            display: flex;

            align-items: center;
            justify-content: center;

            background: #e2f4e9;

            border-radius: 50%;

            font-size: 27px;
        }

        .profile h3 {
            font-size: 17px;
            margin-bottom: 4px;
        }

        .profile p {
            color: #7b8982;
            font-size: 12px;
        }

        .skill-title {
            font-size: 13px;

            font-weight: 700;

            margin-top: 20px;

            margin-bottom: 10px;

            color: #52645a;
        }

        .skills {
            display: flex;

            flex-wrap: wrap;

            gap: 8px;
        }

        .skills span {
            padding: 7px 11px;

            border-radius: 6px;

            background: #e8f6ed;

            color: #2c8155;

            font-size: 12px;

            font-weight: 600;
        }

        .skills.learn span {
            background: #f3efe2;
            color: #8a7235;
        }

        .exchange-btn {
            width: 100%;

            border: none;

            margin-top: 22px;

            padding: 12px;

            background: #2f8f61;

            color: white;

            border-radius: 8px;

            font-weight: 700;

            cursor: pointer;

            transition: 0.3s;
        }

        .exchange-btn:hover {
            background: #26764f;
        }

        /* ================= FLOATING CARDS ================= */

        .floating-card {
            position: absolute;

            padding: 13px 17px;

            background: white;

            border-radius: 12px;

            box-shadow:
                0 10px 30px rgba(0, 0, 0, 0.1);

            font-size: 13px;

            z-index: 3;

            animation: floating 3s ease-in-out infinite;
        }

        .floating-card strong {
            display: block;

            margin-left: 20px;
        }

        .floating-card small {
            display: block;

            color: #78857f;

            margin-left: 20px;

            margin-top: 4px;
        }

        .card-one {
            left: 0;
            top: 90px;
        }

        .card-two {
            right: 0;
            bottom: 80px;

            animation-delay: 1s;
        }

        @keyframes floating {

            0% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-8px);
            }

            100% {
                transform: translateY(0);
            }

        }

        /* ================= COMMON SECTION ================= */

        section {
            padding: 90px 8%;
        }

        .section-heading {
            text-align: center;

            max-width: 700px;

            margin: 0 auto 50px;
        }

        .section-label {
            color: #2f8f61;

            font-size: 13px;

            font-weight: 800;

            letter-spacing: 1.5px;

            margin-bottom: 10px;
        }

        .section-heading h2 {
            font-size: 38px;

            color: #173b2b;

            margin-bottom: 15px;
        }

        .section-heading h2 span {
            color: #2f8f61;
        }

        .section-heading p:last-child {
            color: #708078;

            line-height: 1.6;
        }

        /* ================= FEATURES ================= */

        .features-section {
            background: white;
        }

        .features-grid {
            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 22px;
        }

        .feature-card {
            padding: 30px;

            background: #f8fcfa;

            border: 1px solid #e3eee8;

            border-radius: 15px;

            transition: 0.3s;
        }

        .feature-card:hover {
            transform: translateY(-6px);

            box-shadow:
                0 12px 30px rgba(36, 100, 67, 0.1);
        }

        .feature-icon {
            width: 52px;
            height: 52px;

            display: flex;

            align-items: center;
            justify-content: center;

            background: #e2f4e9;

            border-radius: 12px;

            font-size: 23px;

            margin-bottom: 18px;
        }

        .feature-card h3 {
            margin-bottom: 10px;
        }

        .feature-card p {
            color: #718078;

            line-height: 1.6;

            font-size: 14px;
        }

        /* ================= SKILLS ================= */

        .skills-section {
            background: #f1f8f4;
        }

        .skill-categories {
            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 20px;
        }

        .category-card {
            padding: 30px 20px;

            background: white;

            text-align: center;

            border-radius: 15px;

            border: 1px solid #e2eee7;

            transition: 0.3s;
        }

        .category-card:hover {
            transform: translateY(-5px);

            box-shadow:
                0 10px 25px rgba(40, 100, 70, 0.1);
        }

        .category-card > span {
            font-size: 38px;
        }

        .category-card h3 {
            margin: 15px 0 8px;
        }

        .category-card p {
            color: #77857e;

            font-size: 13px;

            line-height: 1.5;
        }

        /* ================= HOW IT WORKS ================= */

        .how-section {
            background: white;
        }

        .steps {
            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 25px;
        }

        .step {
            text-align: center;
        }

        .step-number {
            width: 60px;
            height: 60px;

            margin: 0 auto 20px;

            display: flex;

            align-items: center;
            justify-content: center;

            background: #e3f4e9;

            color: #2f8f61;

            border-radius: 50%;

            font-weight: 800;
        }

        .step h3 {
            margin-bottom: 10px;
        }

        .step p {
            color: #718078;

            line-height: 1.6;

            font-size: 14px;
        }

        /* ================= CTA ================= */

        .cta {
            text-align: center;

            background:
                linear-gradient(
                    135deg,
                    #dff3e6,
                    #f2faf5
                );
        }

        .cta h2 {
            font-size: 38px;

            margin-bottom: 15px;
        }

        .cta h2 span {
            color: #2f8f61;
        }

        .cta p {
            color: #67766e;

            margin-bottom: 25px;
        }

        .cta-btn {
            display: inline-block;

            padding: 14px 25px;

            background: #2f8f61;

            color: white;

            text-decoration: none;

            border-radius: 9px;

            font-weight: 700;
        }

        .cta-btn:hover {
            background: #26764f;
        }

        /* ================= FOOTER ================= */

        footer {
            background: #173b2b;

            color: white;

            padding: 40px 8% 20px;
        }

        .footer-content {
            display: flex;

            justify-content: space-between;

            align-items: center;

            padding-bottom: 25px;

            border-bottom:
                1px solid rgba(255, 255, 255, 0.15);
        }

        .footer-content h2 span {
            color: #61c48b;
        }

        .footer-content p {
            color: #aec1b6;

            margin-top: 6px;
        }

        .footer-links {
            display: flex;

            gap: 25px;
        }

        .footer-links a {
            color: #c7d6ce;

            text-decoration: none;

            font-size: 14px;
        }

        .footer-links a:hover {
            color: white;
        }

        .copyright {
            text-align: center;

            padding-top: 20px;

            color: #9db1a6;

            font-size: 13px;
        }

        /* ================= RESPONSIVE ================= */

        @media (max-width: 1000px) {

            .nav-links {
                display: none;
            }

            .hero {
                flex-direction: column;

                text-align: center;
            }

            .hero-content,
            .hero-visual {
                width: 100%;
            }

            .hero-description {
                margin-left: auto;
                margin-right: auto;
            }

            .hero-buttons,
            .hero-stats {
                justify-content: center;
            }

            .features-grid {
                grid-template-columns:
                    repeat(2, 1fr);
            }

            .skill-categories,
            .steps {
                grid-template-columns:
                    repeat(2, 1fr);
            }

        }

        @media (max-width: 600px) {

            .navbar {
                padding: 0 5%;
            }

            .login-btn {
                display: none;
            }

            .hero {
                padding: 60px 5%;
            }

            .hero h1 {
                font-size: 40px;
            }

            .hero-buttons {
                flex-direction: column;
            }

            .hero-stats {
                gap: 20px;
            }

            .hero-visual {
                min-height: 430px;
            }

            .main-card {
                width: 95%;
            }

            .floating-card {
                display: none;
            }

            section {
                padding: 65px 5%;
            }

            .section-heading h2 {
                font-size: 30px;
            }

            .features-grid,
            .skill-categories,
            .steps {
                grid-template-columns: 1fr;
            }

            .footer-content {
                flex-direction: column;

                gap: 25px;

                text-align: center;
            }

            .footer-links {
                flex-wrap: wrap;

                justify-content: center;
            }

        }

    </style>
</head>


<body>


<!-- ================= NAVBAR ================= -->

<header class="navbar">

    <div class="logo">
        <span>Skill</span>Connect
    </div>

    <nav class="nav-links">

        <a href="index.php" class="active">
            Home
        </a>

        <a href="#features">
            Features
        </a>

        <a href="#skills">
            Skills
        </a>

        <a href="#how-it-works">
            How It Works
        </a>

    </nav>

    <div class="nav-buttons">

        <a href="login.php" class="login-btn">
            Login
        </a>

        <a href="register.php" class="register-btn">
            Get Started
        </a>

    </div>

</header>


<!-- ================= HERO ================= -->

<section class="hero">

    <div class="hero-content">

        <div class="badge">
            🎓 Learn • Teach • Connect
        </div>

        <h1>
            Share Your Skills.
            <span>Learn From Others.</span>
        </h1>

        <p class="hero-description">

            A student-powered platform where you can
            teach what you know, learn new skills,
            connect with other students and grow together.

        </p>

        <div class="hero-buttons">

            <a href="register.php" class="primary-btn">
                Join SkillConnect →
            </a>

            <a href="#how-it-works" class="secondary-btn">
                Explore Platform
            </a>

        </div>


        <div class="hero-stats">

            <div>
                <h3>500+</h3>
                <p>Students</p>
            </div>

            <div>
                <h3>100+</h3>
                <p>Skills</p>
            </div>

            <div>
                <h3>1000+</h3>
                <p>Exchanges</p>
            </div>

        </div>

    </div>


    <!-- ================= HERO VISUAL ================= -->

    <div class="hero-visual">

        <div class="main-card">

            <div class="card-top">

                <span class="online-dot"></span>

                Student Skill Profile

            </div>


            <div class="profile">

                <div class="profile-img">
                    👩‍💻
                </div>

                <div>

                    <h3>
                        Priya Sharma
                    </h3>

                    <p>
                        Computer Science Student
                    </p>

                </div>

            </div>


            <div class="skill-title">
                Skills I Can Teach
            </div>

            <div class="skills">

                <span>PHP</span>
                <span>JavaScript</span>
                <span>HTML</span>
                <span>CSS</span>

            </div>


            <div class="skill-title">
                Looking To Learn
            </div>

            <div class="skills learn">

                <span>Python</span>
                <span>UI/UX</span>

            </div>


            <button
                class="exchange-btn"
                onclick="showMessage()">

                🤝 Exchange Skills

            </button>

        </div>


        <div class="floating-card card-one">

            💬

            <strong>
                New Message
            </strong>

            <small>
                Hey! Can you teach me PHP?
            </small>

        </div>


        <div class="floating-card card-two">

            ⭐

            <strong>
                Skill Match
            </strong>

            <small>
                Great skill compatibility
            </small>

        </div>

    </div>

</section>


<!-- ================= FEATURES ================= -->

<section
    class="features-section"
    id="features">

    <div class="section-heading">

        <p class="section-label">
            WHY SKILLCONNECT?
        </p>

        <h2>
            Everything You Need To
            <span>Learn & Grow</span>
        </h2>

        <p>
            Connect with students, exchange skills
            and learn together through an interactive
            platform.
        </p>

    </div>


    <div class="features-grid">


        <div class="feature-card">

            <div class="feature-icon">
                🔍
            </div>

            <h3>
                Find Students
            </h3>

            <p>
                Search students based on the skills
                they can teach or want to learn.
            </p>

        </div>


        <div class="feature-card">

            <div class="feature-icon">
                🤝
            </div>

            <h3>
                Skill Exchange
            </h3>

            <p>
                Exchange your knowledge with other
                students and learn together.
            </p>

        </div>


        <div class="feature-card">

            <div class="feature-icon">
                💬
            </div>

            <h3>
                Live Chat
            </h3>

            <p>
                Communicate with your learning partners
                using an integrated chat system.
            </p>

        </div>


        <div class="feature-card">

            <div class="feature-icon">
                📹
            </div>

            <h3>
                Video Calls
            </h3>

            <p>
                Connect through video calls and
                conduct interactive learning sessions.
            </p>

        </div>


        <div class="feature-card">

            <div class="feature-icon">
                📚
            </div>

            <h3>
                Share Resources
            </h3>

            <p>
                Upload notes, PDFs and learning
                videos to help other students.
            </p>

        </div>


        <div class="feature-card">

            <div class="feature-icon">
                ⭐
            </div>

            <h3>
                Reviews & Ratings
            </h3>

            <p>
                Build your profile through feedback
                and ratings from other students.
            </p>

        </div>

    </div>

</section>


<!-- ================= SKILLS ================= -->

<section
    class="skills-section"
    id="skills">

    <div class="section-heading">

        <p class="section-label">
            EXPLORE SKILLS
        </p>

        <h2>
            Learn Something
            <span>New Today</span>
        </h2>

        <p>
            Discover popular skills students are
            teaching and learning on the platform.
        </p>

    </div>


    <div class="skill-categories">


        <div class="category-card">

            <span>💻</span>

            <h3>
                Programming
            </h3>

            <p>
                PHP • Java • Python • JavaScript
            </p>

        </div>


        <div class="category-card">

            <span>🎨</span>

            <h3>
                Design
            </h3>

            <p>
                UI/UX • Photoshop • Figma
            </p>

        </div>


        <div class="category-card">

            <span>📊</span>

            <h3>
                Business
            </h3>

            <p>
                Marketing • Excel • Finance
            </p>

        </div>


        <div class="category-card">

            <span>🎥</span>

            <h3>
                Creative
            </h3>

            <p>
                Video Editing • Photography
            </p>

        </div>

    </div>

</section>


<!-- ================= HOW IT WORKS ================= -->

<section
    class="how-section"
    id="how-it-works">

    <div class="section-heading">

        <p class="section-label">
            SIMPLE PROCESS
        </p>

        <h2>
            How SkillConnect
            <span>Works</span>
        </h2>

    </div>


    <div class="steps">


        <div class="step">

            <div class="step-number">
                01
            </div>

            <h3>
                Create Your Profile
            </h3>

            <p>
                Add your information and
                skills you can teach.
            </p>

        </div>


        <div class="step">

            <div class="step-number">
                02
            </div>

            <h3>
                Find Your Match
            </h3>

            <p>
                Search students who have
                the skills you want to learn.
            </p>

        </div>


        <div class="step">

            <div class="step-number">
                03
            </div>

            <h3>
                Exchange Skills
            </h3>

            <p>
                Send a request and start
                learning together.
            </p>

        </div>


        <div class="step">

            <div class="step-number">
                04
            </div>

            <h3>
                Learn & Grow
            </h3>

            <p>
                Chat, video call and
                share learning resources.
            </p>

        </div>

    </div>

</section>


<!-- ================= CTA ================= -->

<section class="cta">

    <h2>
        Ready To Share What
        <span>You Know?</span>
    </h2>

    <p>
        Join students who are learning,
        teaching and growing together.
    </p>

    <a
        href="register.php"
        class="cta-btn">

        Create Your Free Account →

    </a>

</section>


<!-- ================= FOOTER ================= -->

<footer>

    <div class="footer-content">

        <div>

            <h2>
                <span>Skill</span>Connect
            </h2>

            <p>
                Learn. Teach. Connect. Grow.
            </p>

        </div>


        <div class="footer-links">

            <a href="index.php">
                Home
            </a>

            <a href="#features">
                Features
            </a>

            <a href="#skills">
                Skills
            </a>

            <a href="#how-it-works">
                How It Works
            </a>

        </div>

    </div>


    <div class="copyright">

        © 2026 SkillConnect |
        Student Skill Exchange Platform

    </div>

</footer>


<script>

    // Exchange button
    function showMessage() {

        alert(
            "Create an account or login to start a skill exchange!"
        );

    }


    // Navbar shadow on scroll
    window.addEventListener("scroll", function () {

        const navbar =
            document.querySelector(".navbar");

        if (window.scrollY > 30) {

            navbar.style.boxShadow =
                "0 4px 20px rgba(0,0,0,0.10)";

        } else {

            navbar.style.boxShadow =
                "0 2px 15px rgba(0,0,0,0.06)";

        }

    });

</script>


</body>
</html>
```
