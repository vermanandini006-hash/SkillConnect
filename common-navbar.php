<?php

$currentPage = basename($_SERVER["PHP_SELF"]);

?>

<style>

/* =====================================================
   COMMON NAVBAR
===================================================== */

.navbar {

    height: 70px;

    background: #3b7d5a;

    display: flex;

    align-items: center;

    justify-content: flex-end;

    padding: 0 25px;

    box-shadow:
        0 2px 12px rgba(0,0,0,0.06);

    position: sticky;

    top: 0;

    z-index: 1000;

    width: 100%;

}


/* =====================================================
   HAMBURGER BUTTON
===================================================== */

.menu-button {

    width: 46px;

    height: 46px;

    border: 2px solid #111111;

    border-radius: 10px;

    background: #3b7d5a;

    cursor: pointer;

    display: flex;

    flex-direction: column;

    align-items: center;

    justify-content: center;

    gap: 5px;

    margin-left: auto;

}


.menu-button span {

    width: 21px;

    height: 2px;

    background: #ffffff;

    border-radius: 5px;

    display: block;

}


.menu-button:hover {

    background: #285b41;
}


/* =====================================================
   SIDE MENU
===================================================== */

.side-menu {

    position: fixed;

    top: 70px;

    right: -300px;

    width: 300px;

    height: calc(100vh - 70px);

    background: #ffffff;

    box-shadow:
        -5px 0 20px rgba(0,0,0,0.10);

    z-index: 999;

    transition:
        right 0.3s ease;

    overflow-y: auto;

    padding: 12px 0 20px;
}


.side-menu.open {

    right: 0;

}


/* =====================================================
   MENU TITLE
===================================================== */

.menu-title {

    padding:
        12px 22px 15px;

    font-size: 13px;

    font-weight: 600;

    color: #8b949e;

    text-transform: uppercase;

    letter-spacing: 1px;
}


/* =====================================================
   MENU LINKS
===================================================== */

.menu-link {

    display: flex;

    align-items: center;

    gap: 12px;

    height: 50px;

    padding: 0 22px;

    text-decoration: none;

    color: #222222;

    font-size: 15px;

    font-weight: 500;

    border-left:
        3px solid transparent;

    transition:
        0.2s ease;
}


.menu-link:hover {

    background: #edf8f2;

    color: #2f8f61;

    border-left-color:
        #3b7d5a;
}


.menu-link.active {

    background: #edf8f2;

    color: #2f8f61;

    border-left-color:
        #3b7d5a;
}


/* =====================================================
   MENU ICON
===================================================== */

.menu-icon {

    width: 23px;

    min-width: 23px;

    text-align: center;

    font-size: 18px;

    line-height: 1;
}


/* =====================================================
   DIVIDER
===================================================== */

.menu-divider {

    height: 1px;

    background: #eeeeee;

    margin:
        7px 18px;
}


/* =====================================================
   LOGOUT
===================================================== */

.logout-link {

    color: #c0392b;
}


.logout-link:hover {

    background: #fff2f1;

    color: #c0392b;

    border-left-color:
        #c0392b;
}


/* =====================================================
   OVERLAY
===================================================== */

.menu-overlay {

    position: fixed;

    top: 70px;

    left: 0;

    right: 0;

    bottom: 0;

    background:
        rgba(0,0,0,0.22);

    z-index: 998;

    display: none;
}


.menu-overlay.show {

    display: block;
}


/* =====================================================
   SCROLLBAR
===================================================== */

.side-menu::-webkit-scrollbar {

    width: 7px;
}


.side-menu::-webkit-scrollbar-track {

    background: #ffffff;
}


.side-menu::-webkit-scrollbar-thumb {

    background: #999999;

    border-radius: 10px;
}


.side-menu::-webkit-scrollbar-thumb:hover {

    background: #777777;
}


/* =====================================================
   MOBILE
===================================================== */

@media(max-width:600px) {

    .navbar {

        height: 65px;

        padding:
            0 18px;

        width: 100%;

    }


    .menu-button {

        width: 44px;

        height: 44px;

        margin-left: auto;

    }


    .side-menu {

        top: 65px;

        height:
            calc(100vh - 65px);

        width: 280px;

        right: -280px;

    }


    .menu-link {

        height: 48px;

        font-size: 14px;

    }


    .menu-icon {

        font-size: 17px;

    }

}

</style>


<!-- =====================================================
     NAVBAR
===================================================== -->

<nav class="navbar">

    <button
        type="button"
        class="menu-button"
        onclick="toggleMenu()"
        aria-label="Open Menu"
    >

        <span></span>

        <span></span>

        <span></span>

    </button>

</nav>


<!-- =====================================================
     OVERLAY
===================================================== -->

<div
    class="menu-overlay"
    id="menuOverlay"
    onclick="closeMenu()"
></div>


<!-- =====================================================
     SIDE MENU
===================================================== -->

<div
    class="side-menu"
    id="sideMenu"
>


    <!-- MENU TITLE -->

    <div class="menu-title">

        SkillConnect Menu

    </div>


    <!-- DASHBOARD -->

    <a
        href="dashboard.php"
        class="menu-link
        <?= $currentPage == 'dashboard.php' ? 'active' : '' ?>"
    >

        <span class="menu-icon">
            🏠
        </span>

        <span>
            Dashboard
        </span>

    </a>


    <!-- PROFILE -->

    <a
        href="profile.php"
        class="menu-link
        <?= $currentPage == 'profile.php' ? 'active' : '' ?>"
    >

        <span class="menu-icon">
            👤
        </span>

        <span>
            My Profile
        </span>

    </a>


    <!-- EDIT PROFILE -->

    <a
        href="edit-profile.php"
        class="menu-link
        <?= $currentPage == 'edit-profile.php' ? 'active' : '' ?>"
    >

        <span class="menu-icon">
            ✏️
        </span>

        <span>
            Edit Profile
        </span>

    </a>


    <!-- DIVIDER -->

    <div class="menu-divider"></div>


    <!-- FIND STUDENTS -->

    <a
        href="students.php"
        class="menu-link
        <?= $currentPage == 'students.php' ? 'active' : '' ?>"
    >

        <span class="menu-icon">
            🔎
        </span>

        <span>
            Find Students
        </span>

    </a>


    <!-- SKILL EXCHANGE -->

    <a
        href="exchange.php"
        class="menu-link
        <?= $currentPage == 'exchange.php' ? 'active' : '' ?>"
    >

        <span class="menu-icon">
            🤝
        </span>

        <span>
            Skill Exchange
        </span>

    </a>


    <!-- REQUESTS -->

    <a
        href="requests.php"
        class="menu-link
        <?= $currentPage == 'requests.php' ? 'active' : '' ?>"
    >

        <span class="menu-icon">
            📩
        </span>

        <span>
            Requests
        </span>

    </a>


    <!-- CHAT -->

    <a
        href="chat.php"
        class="menu-link
        <?= $currentPage == 'chat.php' ? 'active' : '' ?>"
    >

        <span class="menu-icon">
            💬
        </span>

        <span>
            Chat
        </span>

    </a>


    <!-- VIDEO CALL -->

    <a
        href="video_call.php"
        class="menu-link
        <?= $currentPage == 'video_call.php' ? 'active' : '' ?>"
    >

        <span class="menu-icon">
            📹
        </span>

        <span>
            Video Call
        </span>

    </a>


    <!-- DIVIDER -->

    <div class="menu-divider"></div>


    <!-- RESOURCES -->

    <a
        href="resources.php"
        class="menu-link
        <?= $currentPage == 'resources.php' ? 'active' : '' ?>"
    >

        <span class="menu-icon">
            📚
        </span>

        <span>
            Resources
        </span>

    </a>


    <!-- UPLOAD VIDEO -->

    <a
        href="upload_video.php"
        class="menu-link
        <?= $currentPage == 'upload_video.php' ? 'active' : '' ?>"
    >

        <span class="menu-icon">
            🎥
        </span>

        <span>
            Upload Video
        </span>

    </a>


    <!-- UPLOAD NOTES -->

    <a
        href="upload-notes.php"
        class="menu-link
        <?= $currentPage == 'upload-notes.php' ? 'active' : '' ?>"
    >

        <span class="menu-icon">
            📝
        </span>

        <span>
            Upload Notes
        </span>

    </a>


    <!-- REVIEWS -->

    <a
        href="reviews.php"
        class="menu-link
        <?= $currentPage == 'reviews.php' ? 'active' : '' ?>"
    >

        <span class="menu-icon">
            ⭐
        </span>

        <span>
            Reviews & Ratings
        </span>

    </a>


    <!-- NOTIFICATIONS -->

    <a
        href="notifications.php"
        class="menu-link
        <?= $currentPage == 'notifications.php' ? 'active' : '' ?>"
    >

        <span class="menu-icon">
            🔔
        </span>

        <span>
            Notifications
        </span>

    </a>


    <!-- DIVIDER -->

    <div class="menu-divider"></div>


    <!-- LOGOUT -->

    <a
        href="logout.php"
        class="menu-link logout-link"
    >

        <span class="menu-icon">
            🚪
        </span>

        <span>
            Logout
        </span>

    </a>


</div>


<!-- =====================================================
     JAVASCRIPT
===================================================== -->

<script>

function toggleMenu() {

    const sideMenu =
        document.getElementById("sideMenu");

    const menuOverlay =
        document.getElementById("menuOverlay");


    sideMenu.classList.toggle("open");

    menuOverlay.classList.toggle("show");

}


function closeMenu() {

    const sideMenu =
        document.getElementById("sideMenu");

    const menuOverlay =
        document.getElementById("menuOverlay");


    sideMenu.classList.remove("open");

    menuOverlay.classList.remove("show");

}


/* ESC KEY */

document.addEventListener(
    "keydown",
    function(event) {

        if (event.key === "Escape") {

            closeMenu();

        }

    }
);

</script>