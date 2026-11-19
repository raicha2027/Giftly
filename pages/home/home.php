<?php
require_once dirname(__DIR__, 2) . '/includes/session.php';

$isAdmin = isset($_SESSION['admin_id']);
$isCustomer = isset($_SESSION['customer_id']);

$adminName = $_SESSION['admin_name'] ?? 'Giftly Admin';
$customerName = $_SESSION['customer_name'] ?? 'Customer';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giftly | Find the Perfect Gift</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #ffffff;
            color: #292929;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        /* HEADER */
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            padding: 14px 5%;
            background: #ffffff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            position: relative;
            z-index: 10;
        }

        .logo {
            font-size: 27px;
            font-weight: bold;
            color: #e75480;
            flex-shrink: 0;
        }

        nav {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 9px;
            flex-wrap: wrap;
        }

        nav a {
            font-size: 13px;
            font-weight: 500;
            transition: 0.3s;
        }

        .nav-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(231, 84, 128, 0.10);
            color: #c94f76;
            padding: 9px 13px;
            border-radius: 20px;
            border: 1px solid rgba(231, 84, 128, 0.16);
            white-space: nowrap;
            transition: 0.3s;
        }

        .nav-button:hover {
            background: rgba(231, 84, 128, 0.22);
            color: #b83d68;
        }

        /* ACCOUNT PROFILE */
        .account-area {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-left: 10px;
            flex-shrink: 0;
        }

        .profile-icon {
            width: 30px;
            height: 30px;
            background: #e75480;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
            flex-shrink: 0;
        }

        .profile-details {
            display: flex;
            flex-direction: column;
            gap: 2px;
            line-height: 1.2;
        }

        .profile-details strong {
            font-size: 12px;
            color: #292929;
            white-space: nowrap;
        }

        .profile-details span {
            font-size: 10px;
            color: #888;
        }

        .account-area .dashboard-button {
            background: transparent;
            color: #c94f76;
            border: 1px solid #f3ccda;
            padding: 7px 11px;
            border-radius: 18px;
            font-size: 11px;
            white-space: nowrap;
        }

        .account-area .dashboard-button:hover {
            background: #fff0f5;
        }

        /* HERO SLIDESHOW */
        .hero-slider {
            position: relative;
            width: 100%;
            height: 500px;
            overflow: hidden;
        }

        .hero-slide {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 50px 8%;
            transform: translateX(100%);
            visibility: hidden;
            transition: transform 0.8s ease-in-out;
        }

        .hero-slide.active {
            transform: translateX(0);
            visibility: visible;
            z-index: 2;
        }

        .hero-slide.exit-left {
            transform: translateX(-100%);
            visibility: visible;
            z-index: 1;
        }

        .welcome-slide {
            background-image: url("../../assets/11062b_ec001460634f4042853b1a8ad5d08ead~mv2.avif");
            background-size: cover;
            background-position: center top;
            background-repeat: no-repeat;
            color: white;
            text-align: center;
            flex-direction: column;
        }

        .welcome-content {
            background: transparent;
            border: none;
            box-shadow: none;
            padding: 0;
        }

        .welcome-slide h1 {
            font-size: 65px;
            font-weight: bold;
            letter-spacing: 8px;
            line-height: 1.3;
            max-width: 950px;
            margin-bottom: 20px;
            color: white;
            text-shadow: 0 3px 8px rgba(0, 0, 0, 0.3);
        }

        .welcome-slide p {
            font-size: 19px;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: white;
            text-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
        }

        /* SECOND SLIDE */
        .pink-slide {
            background: #fff0f5;
            justify-content: space-between;
            gap: 40px;
        }

        .hero-content {
            max-width: 520px;
        }

        .hero-content h1 {
            font-size: 46px;
            line-height: 1.2;
            margin-bottom: 20px;
            color: #292929;
        }

        .hero-content p {
            font-size: 16px;
            line-height: 1.7;
            color: #666;
            margin-bottom: 28px;
        }

        .hero-button {
            display: inline-block;
            background: #e75480;
            color: white;
            padding: 14px 28px;
            border-radius: 30px;
            font-weight: bold;
            transition: 0.3s;
        }

        .hero-button:hover {
            background: #d94370;
            transform: translateY(-2px);
        }

        .hero-image {
            width: 45%;
            max-width: 480px;
            height: 320px;
            object-fit: cover;
            border-radius: 20px;
        }

        /* SLIDESHOW DOTS */
        .slider-dots {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 12px;
            z-index: 5;
        }

        .slider-dot {
            width: 12px;
            height: 12px;
            border: none;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.55);
            cursor: pointer;
        }

        .slider-dot.active {
            background: white;
            transform: scale(1.3);
        }

        /* GENERAL SECTIONS */
        .section {
            width: 100%;
            padding: 55px 8%;
            text-align: center;
        }

        .section-title {
            font-size: 30px;
            margin-bottom: 10px;
            color: #292929;
        }

        .section-description {
            font-size: 14px;
            color: #777;
            margin-bottom: 30px;
        }

        /* CATEGORIES */
        .categories {
            width: 100%;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 25px;
        }

        .category {
            padding: 15px;
            background: #fff5f8;
            border-radius: 15px;
            text-align: center;
            transition: 0.3s;
        }

        .category:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(231, 84, 128, 0.12);
        }

        .category img {
            width: 100%;
            height: 220px;
            object-fit: cover;
            border-radius: 12px;
            margin-bottom: 12px;
        }

        .category h3 {
            font-size: 17px;
            margin-bottom: 7px;
        }

        .category p {
            font-size: 13px;
            color: #777;
            line-height: 1.5;
        }

        /* POPULAR GIFTS */
        .gifts {
            background: #fff9fb;
        }

        .gift-grid {
            width: 100%;
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 22px;
        }

        .gift-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.05);
            transition: 0.3s;
        }

        .gift-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.09);
        }

        .gift-card img {
            width: 100%;
            height: 210px;
            object-fit: cover;
        }

        .gift-info {
            padding: 16px;
        }

        .gift-info h3 {
            font-size: 16px;
            margin-bottom: 8px;
        }

        .gift-info p {
            font-size: 13px;
            color: #e75480;
            font-weight: bold;
        }

        /* ABOUT */
        .about {
            background: #fff0f5;
            padding: 55px 8%;
            text-align: center;
        }

        .about h2 {
            font-size: 28px;
            margin-bottom: 15px;
        }

        .about p {
            max-width: 750px;
            margin: auto;
            color: #666;
            line-height: 1.7;
            font-size: 15px;
        }

        /* FOOTER */
        footer {
            background: #292929;
            color: white;
            padding: 35px 8%;
            text-align: center;
        }

        footer .footer-logo {
            font-size: 25px;
            color: #ff8eae;
            font-weight: bold;
            margin-bottom: 12px;
        }

        footer p {
            font-size: 13px;
            color: #ccc;
            line-height: 1.7;
        }

        /* TABLET */
        @media (max-width: 1100px) {
            header {
                padding: 14px 4%;
            }

            nav {
                gap: 7px;
            }

            nav .nav-button {
                padding: 8px 10px;
                font-size: 12px;
            }
        }

        @media (max-width: 900px) {
            header {
                flex-wrap: wrap;
                justify-content: center;
            }

            nav {
                justify-content: center;
            }

            .gift-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .hero-content h1 {
                font-size: 36px;
            }

            .welcome-slide h1 {
                font-size: 48px;
            }
        }

        /* MOBILE */
        @media (max-width: 650px) {
            header {
                flex-direction: column;
                gap: 14px;
                padding: 15px 5%;
            }

            nav {
                gap: 9px;
                justify-content: center;
            }

            nav a {
                font-size: 12px;
            }

            .account-area {
                flex-wrap: wrap;
                justify-content: center;
                margin-left: 0;
            }

            .hero-slider {
                height: 650px;
            }

            .hero-slide {
                padding: 40px 6%;
            }

            .welcome-slide {
                background-position: center top;
            }

            .welcome-slide h1 {
                font-size: 35px;
                letter-spacing: 4px;
            }

            .welcome-slide p {
                font-size: 13px;
                letter-spacing: 2px;
            }

            .pink-slide {
                flex-direction: column;
                text-align: center;
                justify-content: center;
                gap: 25px;
            }

            .hero-content h1 {
                font-size: 32px;
            }

            .hero-image {
                width: 100%;
                height: 210px;
            }

            .categories {
                grid-template-columns: 1fr;
            }

            .category img {
                height: 220px;
            }

            .gift-grid {
                grid-template-columns: 1fr;
            }

            .section {
                padding: 40px 6%;
            }
        }
    </style>
</head>

<body>

    <!-- HEADER -->
    <header>
        <a href="../../index.php" class="logo">Giftly.</a>

        <nav>
            <a href="home.php" class="nav-button">Home</a>
            <a href="../shop/gifts.php" class="nav-button">Available Gifts</a>
            <a href="#categories" class="nav-button">Categories</a>
            <a href="../../gift_agent/gift_finder.php" class="nav-button">Gift Finder</a>
            <a href="#about" class="nav-button">About</a>

            <?php if ($isAdmin): ?>

                <div class="account-area">
                    <div class="profile-icon">A</div>

                    <div class="profile-details">
                        <strong><?= htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8') ?></strong>
                        <span>Administrator</span>
                    </div>

                    <a href="../../admin/products.php" class="dashboard-button">Products</a>
                    <a href="../../admin/customers.php" class="dashboard-button">Customers</a>
                    <a href="../../logout.php" class="dashboard-button">Logout</a>
                </div>

            <?php elseif ($isCustomer): ?>

                <div class="account-area">
                    <div class="profile-icon">
                        <?= htmlspecialchars(strtoupper(substr($customerName, 0, 1)), ENT_QUOTES, 'UTF-8') ?>
                    </div>

                    <div class="profile-details">
                        <strong><?= htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8') ?></strong>
                        <span>Customer</span>
                    </div>

                    <a href="../profile/profile.php" class="dashboard-button">Profile</a>
                    <a href="../cart/cart.php" class="dashboard-button">Cart</a>
                    <a href="../dashboard/CustomerDashboard.php#recent-orders" class="dashboard-button">My Orders</a>
                    <a href="../../logout.php" class="dashboard-button">Logout</a>
                </div>

            <?php else: ?>

                <a href="../login/login.php" class="nav-button">Login</a>
                <a href="../register/register.php" class="nav-button">Register</a>

            <?php endif; ?>
        </nav>
    </header>

    <!-- HERO SLIDESHOW -->
    <section class="hero-slider">

        <div class="hero-slide welcome-slide active">
            <div class="welcome-content">
                <h1>WELCOME TO GIFTLY</h1>
                <p>Making Every Moment Special</p>
            </div>
        </div>

        <div class="hero-slide pink-slide">

            <div class="hero-content">
                <h1>Find the Perfect Gift for Every Occasion.</h1>

                <p>
                    Make every moment special with thoughtful gifts.
                    Whether it is a birthday, anniversary, or just because,
                    find something meaningful for the people you love.
                </p>

                <a href="../shop/gifts.php" class="hero-button">Explore Gifts</a>
            </div>

            <img src="https://images.unsplash.com/photo-1513201099705-a9746e1e201f?w=900" alt="Beautiful gift boxes"
                class="hero-image">
        </div>

        <div class="slider-dots">
            <button class="slider-dot active" aria-label="Show welcome slide"></button>
            <button class="slider-dot" aria-label="Show gift introduction slide"></button>
        </div>

    </section>

    <!-- CATEGORIES -->
    <section class="section" id="categories">

        <h2 class="section-title">Explore Our Categories</h2>

        <p class="section-description">
            A special gift for every special person.
        </p>

        <div class="categories">

            <div class="category">
                <img src="../../assets/Collection.jpg" alt="Birthday gift box">
                <h3>Birthday Gifts</h3>
                <p>Make their birthday unforgettable.</p>
            </div>

            <div class="category">
                <img src="../../assets/romanticteddy.jpg" alt="Romantic gift">
                <h3>Romantic Gifts</h3>
                <p>Show someone how much they mean to you.</p>
            </div>

            <div class="category">
                <img src="../../assets/chocolategift.webp" alt="Special occasion gifts">
                <h3>Special Occasions</h3>
                <p>Find something lovely for every celebration.</p>
            </div>

        </div>

    </section>

    <!-- POPULAR GIFTS -->
    <section class="section gifts" id="popular">

        <h2 class="section-title">Popular Gifts</h2>

        <p class="section-description">
            Discover some of our favourite gift ideas.
        </p>

        <div class="gift-grid">

            <div class="gift-card">
                <img src="https://images.unsplash.com/photo-1549465220-1a8b9238cd48?w=600" alt="Beautiful gift box">
                <div class="gift-info">
                    <h3>Beautiful Gift Box</h3>
                    <p>Explore this gift</p>
                </div>
            </div>

            <div class="gift-card">
                <img src="https://images.unsplash.com/photo-1490750967868-88aa4486c88?w=600" alt="Beautiful flowers">
                <div class="gift-info">
                    <h3>Beautiful Flowers</h3>
                    <p>Explore this gift</p>
                </div>
            </div>

            <div class="gift-card">
                <img src="../../assets/weddinggift.png" alt="Special gift ideas">
                <div class="gift-info">
                    <h3>Special Gift Ideas</h3>
                    <p>Explore this gift</p>
                </div>
            </div>

            <div class="gift-card">
                <img src="../../assets/Gifts.jpg" alt="Elegant gift package">
                <div class="gift-info">
                    <h3>Elegant Gift Package</h3>
                    <p>Explore this gift</p>
                </div>
            </div>

        </div>

        <br>

        <a href="../shop/gifts.php" class="hero-button">View All Gifts</a>

    </section>

    <!-- ABOUT -->
    <section class="about" id="about">

        <h2>Why Choose Giftly?</h2>

        <p>
            At Giftly, we want you to get the most personalized gift for your friends and family.
            Get the chance to communicate with our online agent, which recommends gifts based
            on the personality and preferences of your loved ones.
            Make them feel special and known by you.
        </p>

    </section>

    <!-- FOOTER -->
    <footer id="contact">

        <div class="footer-logo">Giftly.</div>

        <p>Making every moment special, one gift at a time.</p>

        <p>&copy; 2026 Giftly. All rights reserved.</p>

    </footer>

    <!-- SLIDESHOW SCRIPT -->
    <script>
        const slides = document.querySelectorAll(".hero-slide");
        const dots = document.querySelectorAll(".slider-dot");

        let currentSlide = 0;
        let slideTimer;

        function showSlide(index) {
            if (index === currentSlide) return;

            const previousSlide = slides[currentSlide];
            const nextSlide = slides[index];

            previousSlide.classList.remove("active");
            previousSlide.classList.add("exit-left");

            nextSlide.classList.remove("exit-left");

            void nextSlide.offsetWidth;

            nextSlide.classList.add("active");

            dots.forEach(dot => dot.classList.remove("active"));
            dots[index].classList.add("active");

            currentSlide = index;

            setTimeout(() => {
                previousSlide.classList.remove("exit-left");
            }, 850);
        }

        function nextSlide() {
            const next = (currentSlide + 1) % slides.length;
            showSlide(next);
        }

        function startSlideshow() {
            clearInterval(slideTimer);
            slideTimer = setInterval(nextSlide, 3500);
        }

        dots.forEach((dot, index) => {
            dot.addEventListener("click", () => {
                showSlide(index);
                startSlideshow();
            });
        });

        startSlideshow();
    </script>

</body>

</html>