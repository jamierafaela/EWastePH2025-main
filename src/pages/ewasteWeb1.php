<?php
// DATABASE CONNECTION
$conn = new mysqli("localhost", "root", "", "ewaste_db");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "SELECT * FROM products ORDER BY product_id DESC";
$result = $conn->query($sql);


// SESSION MANAGEMENT
session_start();
$isLoggedIn = isset($_SESSION['user_id']);
$justLoggedIn = false;

if (isset($_SESSION['just_logged_in']) && $_SESSION['just_logged_in'] === true) {
    $justLoggedIn = true;
    $_SESSION['just_logged_in'] = false;
}

// CSRF token generation
$csrf_token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EWastePH</title>

    <!-- Stylesheets -->
    <link rel="stylesheet" href="../../src/styles/ewasteWeb.css">
    <link rel="stylesheet" href="../../src/styles/login.css">

    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;700&family=Jersey+10&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;700&family=Jersey+10&family=Jersey+25&display=swap" rel="stylesheet">

    <!-- JavaScript -->
    <script defer src="../../src/scripts/ewasteWeb.js"></script>
    
    <!-- Inline Styles -->
    <style>
        /* Valid requirement styling */
.requirements-list li.valid {
    color: #4caf50;
}

/* Invalid requirement styling */
.requirements-list li.invalid {
    color: #666;

}

.modal-overlay {
    display: none;
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(0, 0, 0, 0.85); /* Darker overlay */
    justify-content: center;
    align-items: center;
    z-index: 9999;
}

.modal-box {
    background: #fff;
    padding: 30px 40px;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.3);
    text-align: center;
    max-width: 400px;
    width: 90%;
}

.modal-box h2 {
    color: #2e7d32;
    margin-bottom: 10px;
}

.modal-box p {
    font-size: 16px;
    color: #333;
    margin-bottom: 25px;
}

.btn {
    background-color: #2e7d32;
    color: white;
    padding: 10px 25px;
    border: none;
    border-radius: 8px;
    font-weight: bold;
    cursor: pointer;
}

.btn:hover {
    background-color: #1b5e20;
}
</style>

    </style>
</head>
 
<body>
    <!-- Login Popup -->
    <div id="loginPopup" class="login-popup">
        <div class="login-popup-content">
            <span class="login-popup-close">&times;</span>
            <h2>Welcome, <?php echo isset($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : 'User'; ?>!</h2>
            <p>You have successfully logged in to E-WastePH.</p>
            <button class="login-popup-button">Continue</button>
        </div>
    </div>

    <!-- Header and Navigation -->
    <header>
        <nav class="navbar">
            <div class="logo-container">
                <a href="#home" class="logo"><img src="../../Public/images/logo.png" alt="EWastePH Logo"></a>
            </div>
            <ul class="nav-links">
                <li><a href="#home">Home</a></li>
                <li><a href="#about">About Us</a></li>
                <li><a href="#faq">FAQ</a></li>
                <li><a href="#contact">Contact Us</a></li>
                <li><a href="ewasteShop.php">Shop</a></li>
                <li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="userdash.php"><i class="fa fa-user"></i></a>
                    <?php else: ?>
                        <a href="#loginSection"><i class="fa fa-user"></i></a>
                    <?php endif; ?>
                </li>
            </ul>
        </nav>
    </header>

    <!-- Main Content -->
    
    <!-- Home Section -->
    <section id="home" class="section home-section">
        <div class="text-box">
            <h1>E-WASTE PH</h1>
            <p>"Old tech, New harm—Dispose responsibly, Save our Planet."</p>
            <div class="cta-buttons">
                <button onclick="handleAction('buy')" class="btn">Buy</button>
                <button onclick="handleAction('sell')" class="btn">Sell</button>
            </div>
        </div>
    </section>

<div class="part1">
    <section id="about" class="section about-section">
        <div class="aboutHeader">
            <h2 class="aboutHead">About Us</h2>
            
        <div class="ewaste-page-container">
            <!--<div class="aboutHeader">
                <h2>ABOUT US</h2>
            </div>
                            -->
            <!--ewaste-page-cpntainer-->
            <div class="info-cards-wrapper">
            <!-- About Us Card -->
            <div class="info-card about-card">
                <img src="../../Public/images/vission.jpg" alt="About E-Waste Philippines" class="info-card-image" />
                <div class="info-card-overlay"></div>
                <div class="info-card-content">
                <div class="info-card-header">E-WastePH</div>
                <div class="info-card-text">
                    E-Waste Philippines is a dedicated waste management company based in Las Piñas, Philippines. Founded in 2023, we aim to tackle the growing issue of electronic waste responsibly through recycling and reusing e-waste items...
                </div>
                </div>
            </div>

            <!-- Mission Card -->
            <div class="info-card mission-card">
                <img src="../../Public/images/mission.jpeg" alt="E-waste collection process" class="info-card-image" />
                <div class="info-card-overlay"></div>
                <div class="info-card-content">
                <div class="info-card-header">Mission</div>
                <div class="info-card-text">
                    Our mission is to transform how the Philippines manages electronic waste, promoting responsible e-waste disposal to protect our environment and create a sustainable future for generations to come.
                </div>
                </div>
            </div>

            <!-- Vision Card -->
            <div class="info-card vision-card">
                <img src="../../Public/images/values.jpg" alt="Sustainable e-waste future" class="info-card-image" />
                <div class="info-card-overlay"></div>
                <div class="info-card-content">
                <div class="info-card-header">Vision</div>
                <div class="info-card-text">
                    We envision a cleaner, safer Philippines where e-waste is no longer a threat to health or the environment, and where sustainable practices are embraced by all communities across the nation.
                </div>
                </div>
            </div>
            </div>
        </div>
    </section>


    <!-- Shop Section -->
    <section id="shop" class="section shop-section">
        <h2>Shop</h2>
        <div class="new-products">
            <h3>Latest Available Items</h3>
            <div class="product-grid" id="product-list">
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <div class="product-card" data-category="<?= htmlspecialchars($row['category']) ?>">
                            <img src="<?= htmlspecialchars($row['image']) ?>" alt="<?= htmlspecialchars($row['name']) ?>">
                            <h3><?= htmlspecialchars($row['name']) ?></h3>
                            <p>P <?= number_format($row['price'], 2) ?></p>

                            <button class="btn add-to-cart" <?= $row['quantity'] <= 0 ? 'disabled' : '' ?>>Add to Cart</button>
                            <button class="btn" <?= $row['quantity'] <= 0 ? 'disabled style="background-color: gray; cursor: not-allowed;"' : '' ?>
                                onclick="<?= $row['quantity'] > 0 ? ($isLoggedIn ? 'location.href=\'checkout1.php?name=' . urlencode($row['name']) . '&price=' . $row['price'] . '&quantity=1&image=' . urlencode($row['image']) . '\'' : 'document.getElementById(\"loginSection\").scrollIntoView({ behavior: \"smooth\" })') : 'return false;' ?>">Buy
                            </button>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p>No products available.</p>
                <?php endif; ?>
            </div>
        </div>

        <form>
            <div class="show-more-container">
                <button type="submit" formaction="ewasteShop.php" class="show-more-btn">Show More in SHOP</button>
            </div>
        </form>
    </section>
</div>



    <section class="container1">
        <!-- Contact Section (Left Side) -->
        <section id="contact" class="section contact-section">
            <h2>Contact Us</h2>
            <div class="contact-info">
                <p><strong>Email:</strong> support@ewasteph.org</p>
                <p><strong>Phone:</strong> 0929369606</p>
                <p><strong>Address:</strong> Las Piñas, Philippines</p>
            </div>

            <form class="contact-form" id="guestContactForm">
                <input type="text" name="name" placeholder="Enter your full name" required>
                <input type="email" name="email" placeholder="Enter your email address" required>
                <textarea name="message" placeholder="Write your message here..." rows="5" required></textarea>
                <button type="submit">Send Message</button>
            </form>

            <div id="modal" class="modal-overlay">
                <div class="modal-box">
                    <button id="closePopup" class="close-icon" aria-label="Close popup">&times;</button>
                    <i class="fas fa-check-circle success-icon"></i>
                    <p>Your message has been sent successfully!</p>
                    <button class="btn" onclick="closeModal()">OK</button>
                </div>
            </div>



        </section>

        <!-- FAQ Section (Right Side) -->
        <section id="faq" class="section faq-section">
            <div class="faq-name">
                <h1 class="faq-header">FAQ</h1>
                <h1 class="faq-header"><b>Need Assistance?</b></h1>
                <h2 class="faq-welcome">
                    Welcome to the E-WastePH Shop FAQ section! Here, we answer your most frequently asked questions about our electronic waste shop, where you can buy and sell e-waste scraps. We're committed to promoting sustainable practices by giving old electronics a new purpose.
                </h2>
            </div>

            <div class="faq-box">
                <div class="faq-wrapper">
                    <input type="checkbox" class="faq-trigger" id="faq-trigger-1">
                    <label class="faq-title" for="faq-trigger-1">
                        What is E-WastePH Shop?
                        <i class="fa fa-chevron-right"></i>
                    </label>
                    <div class="faq-detail">
                        <p>E-WastePH is a platform that facilitates the buying and selling of electronic waste (e-waste) scraps. Our goal is to help individuals and businesses recycle or repurpose their unused electronics, contributing to a greener future.</p>
                    </div>
                </div>

                <div class="faq-wrapper">
                    <input type="checkbox" class="faq-trigger" id="faq-trigger-2">
                    <label class="faq-title" for="faq-trigger-2">
                        What can I buy at E-WastePH?
                        <i class="fa fa-chevron-right"></i>
                    </label>
                    <div class="faq-detail">
                        <p>You can find:</p>
                        <ul>
                            <li>Refurbished electronics</li>
                            <li>Spare parts for repairs</li>
                            <li>Recyclable materials for DIY projects</li>
                            <li>Rare and vintage electronic components</li>
                        </ul>
                    </div>
                </div>

                <div class="faq-wrapper">
                    <input type="checkbox" class="faq-trigger" id="faq-trigger-3">
                    <label class="faq-title" for="faq-trigger-3">
                        How do I sell my E-Waste?
                        <i class="fa fa-chevron-right"></i>
                    </label>
                    <div class="faq-detail">
                        <ul>
                            <li><strong>Step 1:</strong> Create an account on our platform.</li>
                            <li><strong>Step 2:</strong> List your e-waste with photos and descriptions.</li>
                            <li>Step 3: Set a price or choose to recycle it for free.</li>
                            <li>Step 4: Connect with buyers or schedule a pickup/drop-off for recycling.</li>
                        </ul>
                    </div>
                </div>

                <div class="faq-wrapper">
                    <input type="checkbox" class="faq-trigger" id="faq-trigger-4">
                    <label class="faq-title" for="faq-trigger-4">
                        Why should I recycle E-Waste?
                        <i class="fa fa-chevron-right"></i>
                    </label>
                    <div class="faq-detail">
                        <p>
                            E-waste contains valuable materials like gold, silver, and copper, but also harmful chemicals that can damage the environment if improperly disposed of. Recycling helps recover these materials and reduces landfill waste.
                        </p>
                    </div>
                </div>
            </div>
        </section>
</section>






<form id="contactForm">
    <h2>Contact Us</h2>
    <input type="text" name="name" placeholder="Your name" required>
    <input type="email" name="email" placeholder="Your email" required>
    <textarea name="message" rows="5" placeholder="Your message" required></textarea>
    <button type="submit">Send Message</button>
</form>

<!-- Modal -->
<div id="modal" class="modal-overlay">
        <div class="modal-box">
            <button class="close-icon" aria-label="Close popup">&times;</button>
            <i class="fas fa-check-circle success-icon"></i>
            <h2>Message Sent!</h2>
            <p>Thank you for contacting us. We'll get back to you within 24 hours.</p>
            <button class="modal-btn" onclick="closeModal()">Continue</button>
        </div>
</div>






















        <!-- Popup -->
        <div id="popup" class="popup">
            <div class="popup-content">
                <button id="closePopup" class="close-icon" aria-label="Close popup">&times;</button>
                <i class="fas fa-check-circle success-icon"></i>
                <p>Your message has been sent successfully!</p>
            </div>
        </div>

    <!-- Login/Profile Section -->
    <?php if (!isset($_SESSION['user_id'])): ?>
        <section id="loginSection" class="section profile-section">
            <section class="profile-contents">
                <div class="logIn">
                    <h2 id="formTitle">Log in</h2>
                    <p id="formToggleText">
                        New to site? <a href="#" id="toggleForm">Sign up</a>
                    </p>
                </div>
                <div class="continueAcc">
                    <!-- PHP Check for Form Handling -->
                    <?php
                    if (isset($_GET['error'])) {
                        echo "<p style='color: red;'>" . htmlspecialchars($_GET['error']) . "</p>";
                    }
                    ?>

                    <!-- Log In Form -->
                    <div id="loginForm">
                        <form action="login.php" method="POST">
                            <input type="hidden" name="signin" value="1">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <?php
                            if (isset($_SESSION['login_error'])) {
                                echo '<div class="error-message" style="display: block; color: red; margin-bottom: 15px;">' .
                                    htmlspecialchars($_SESSION['login_error']) . '</div>';
                                unset($_SESSION['login_error']);
                            }
                            ?>

                            <ul>
                                <li>
                                    <label>Email:</label>
                                    <input type="email" id="login-email" name="email" placeholder="Enter your email" required>
                                </li>
                                <li>
                                    <label>Password:</label>
                                    <div class="password-field">
                                        <input type="password" id="login-password" name="password" placeholder="Enter your password" required>
                                        <button type="button" class="toggle-password" data-target="login-password">
                                            <i class="far fa-eye-slash"></i>
                                        </button>
                                    </div>
                                </li>
                                <li>
                                    <button type="submit" class="btn">Log in</button>
                                </li>
                            </ul>
                        </form>
                    </div>

                    <!-- Signup Form -->
                    <div id="signupForm" class="hidden">
                        <?php
                            // Display signup error if it exists
                            if (isset($_SESSION['signup_error'])) {
                                echo '<div class="error-message" style="display: block; color: red; margin-bottom: 15px;">' . 
                                    htmlspecialchars($_SESSION['signup_error']) . 
                                '</div>';
                                unset($_SESSION['signup_error']);
                            }
                        ?>
                        <form action="signup.php" method="POST">
                            <input type="hidden" name="signup" value="1">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <ul>
                                <div class="input-row">
                                    <li>
                                        <label>Name:</label>
                                        <input type="text" id="full_name" name="full_name" placeholder="Enter your name" required>
                                    </li>
                                    <li>
                                        <label>Email:</label>
                                        <input type="email" id="signup-email" name="email" placeholder="Enter your email" required>
                                    </li>
                                </div>

                                <div class="input-row">
                                    <li>

                                                <label>Password:</label>
                                                <div class="password-container">
                                                    <input type="password" id="signup-password" name="password" placeholder="Enter your password" required>
                                                    <button type="button" class="toggle-password" data-target="signup-password">
                                                        <i class="far fa-eye-slash"></i>
                                                    </button>
                                                </div>

                                                <div class="password-requirements">
                                                    <p>Password must contain:</p>
                                                    <ul class="requirements-list">
                                                        <li id="length" class="invalid"><i class="fas fa-times-circle"></i> At least 8 characters</li>
                                                        <li id="uppercase" class="invalid"><i class="fas fa-times-circle"></i> At least one uppercase letter</li>
                                                        <li id="lowercase" class="invalid"><i class="fas fa-times-circle"></i> At least one lowercase letter</li>
                                                        <li id="number" class="invalid"><i class="fas fa-times-circle"></i> At least one number</li>
                                                    </ul>
                                                </div>
                                    </li>
                                    <li>
                                    

                                            <!-- Confirm Password Field and Match Status -->
                                           
                                                <label>Confirm Password:</label>
                                                <div class="password-field">
                                                    <input type="password" id="confirm-password" name="confirm_password" placeholder="Re-enter your password" required>
                                                    <button type="button" class="toggle-password" data-target="confirm-password">
                                                        <i class="far fa-eye-slash"></i>
                                                    </button>
                                                </div>
                                                <p id="match-status" class="password-requirements"></p>
                                         
                                        </div>
                                    </li>

                                    <li style="text-align: center; margin-top: 30px;">
                                        <button type="submit" class="btn" id="signup-btn" disabled>Sign up</button>
                                    </li>
                                </div>

                            </ul>
                        </form>
                    </div>
                </div>
            </section>
        </section>
    <?php endif; ?>

    <!-- Back to Top Button -->
    <button id="upButton" title="Go to top">
        <i class="fa fa-arrow-up"></i>
    </button>

    <!-- Footer -->
    <footer>
        <p>&copy; 2024 EWastePH. All rights reserved. </p>
        <div class="footer-links">
            <a href="#">Privacy Policy </a>
            <a href="#">Terms of Service</a>
        </div>
        <div class="footer-social">
            <a href="https://www.facebook.com/yourpage" target="_blank"><i class="fab fa-facebook-f"></i></a>
            <a href="https://twitter.com/yourprofile" target="_blank"><i class="fab fa-twitter"></i></a>
            <a href="https://www.instagram.com/yourprofile" target="_blank"><i class="fab fa-instagram"></i></a>
        </div>
    </footer>





<script>
    // script for login
    const isLoggedIn = <?php echo $isLoggedIn ? 'true' : 'false'; ?>;

    function handleAction(action) {
        if (isLoggedIn) {
            if (action === 'buy') {
                window.location.href = "ewasteShop.php";
            } else if (action === 'sell') {
                window.location.href = "sell.php";
            }
        } else {
            document.getElementById("loginSection").scrollIntoView({
                behavior: "smooth"
            });
        }
    }

    // Login popup 
    document.addEventListener('DOMContentLoaded', function () {
        const justLoggedIn = <?php echo $justLoggedIn ? 'true' : 'false'; ?>;
        const loginPopup = document.getElementById('loginPopup');

        if (justLoggedIn && loginPopup) {
            setTimeout(function () {
                loginPopup.classList.add('show');
            }, 250);

            const closeBtn = loginPopup.querySelector('.login-popup-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', function () {
                    loginPopup.classList.remove('show');
                });
            }

            const continueBtn = loginPopup.querySelector('.login-popup-button');
            if (continueBtn) {
                continueBtn.addEventListener('click', function () {
                    loginPopup.classList.remove('show');
                });
            }

            loginPopup.addEventListener('click', function (e) {
                if (e.target === loginPopup) {
                    loginPopup.classList.remove('show');
                }
            });
        }
    });

    // Password toggle functionality
    document.addEventListener('DOMContentLoaded', function () {
        // Toggle password visibility
        document.querySelectorAll('.toggle-password').forEach(function (toggle) {
            toggle.addEventListener('click', function () {
                const targetId = this.getAttribute('data-target');
                const input = document.getElementById(targetId);
                const icon = this.querySelector('i');

                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                } else {
                    input.type = 'password';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                }
            });
        });

        // Password validation for signup
        const passwordInput = document.getElementById('signup-password');
        const confirmPasswordInput = document.getElementById('confirm-password');
        const signupBtn = document.getElementById('signup-btn');
        const matchStatus = document.getElementById('match-status');

        function validatePassword() {
            const password = passwordInput.value;
            let valid = true;

            // Check length
            const lengthElement = document.getElementById('length');
            if (password.length >= 8) {
                lengthElement.className = 'valid';
                lengthElement.querySelector('i').className = 'fas fa-check-circle';
            } else {
                lengthElement.className = 'invalid';
                lengthElement.querySelector('i').className = 'fas fa-times-circle';
                valid = false;
            }

            // Check uppercase
            const uppercaseElement = document.getElementById('uppercase');
            if (/[A-Z]/.test(password)) {
                uppercaseElement.className = 'valid';
                uppercaseElement.querySelector('i').className = 'fas fa-check-circle';
            } else {
                uppercaseElement.className = 'invalid';
                uppercaseElement.querySelector('i').className = 'fas fa-times-circle';
                valid = false;
            }

            // Check lowercase
            const lowercaseElement = document.getElementById('lowercase');
            if (/[a-z]/.test(password)) {
                lowercaseElement.className = 'valid';
                lowercaseElement.querySelector('i').className = 'fas fa-check-circle';
            } else {
                lowercaseElement.className = 'invalid';
                lowercaseElement.querySelector('i').className = 'fas fa-times-circle';
                valid = false;
            }

            // Check number
            const numberElement = document.getElementById('number');
            if (/[0-9]/.test(password)) {
                numberElement.className = 'valid';
                numberElement.querySelector('i').className = 'fas fa-check-circle';
            } else {
                numberElement.className = 'invalid';
                numberElement.querySelector('i').className = 'fas fa-times-circle';
                valid = false;
            }

            // Check if passwords match
            if (password && confirmPasswordInput.value) {
                if (password === confirmPasswordInput.value) {
                    matchStatus.innerHTML = '<i class="fas fa-check-circle"></i> Passwords match!';
                    matchStatus.style.color = "#4caf50";
                } else {
                    matchStatus.innerHTML = '<i class="fas fa-times-circle"></i> Passwords do not match!';
                    matchStatus.style.color = "#f44336";
                    valid = false;
                }
            } else {
                matchStatus.textContent = "";
            }

            // Enable/disable sign up button based on validation
            signupBtn.disabled = !valid || !confirmPasswordInput.value || password !== confirmPasswordInput.value;
        }

        // Add event listeners for password validation
        if (passwordInput) {
            passwordInput.addEventListener('input', validatePassword);
        }
        if (confirmPasswordInput) {
            confirmPasswordInput.addEventListener('input', validatePassword);
        }

        // Form submission validation
        const signupForm = document.querySelector('#signupForm form');
        if (signupForm) {
            signupForm.addEventListener('submit', function (event) {
                const password = passwordInput.value;

                if (password.length < 8 || !(/[A-Z]/.test(password)) ||
                    !(/[a-z]/.test(password)) || !(/[0-9]/.test(password))) {
                    alert('Please ensure your password meets all requirements');
                    event.preventDefault();
                } else if (password !== confirmPasswordInput.value) {
                    alert('Password and confirmation do not match');
                    event.preventDefault();
                }
            });
        }
    });

    // Form submission functionality
    document.getElementById('guestContactForm').addEventListener('submit', function (e) {
        e.preventDefault();
        // Show success popup
        document.getElementById('popup').style.display = 'flex';
        // Reset form
        this.reset();
    });

    // Close popup functionality
    document.getElementById('closePopup').addEventListener('click', function () {
        document.getElementById('popup').style.display = 'none';
    });

    // Form submission and popup handling
    document.getElementById('guestContactForm').addEventListener('submit', function (e) {
        e.preventDefault();

        // You would typically send the form data to a server here
        // For demonstration, we'll just show the success popup

        const popup = document.getElementById('popup');
        popup.style.display = 'flex';

        // Reset form
        this.reset();
    });

    document.getElementById('closePopup').addEventListener('click', function () {
        document.getElementById('popup').style.display = 'none';
    });

    // FAQ accordion functionality - only one open at a time
    const faqTriggers = document.querySelectorAll('.faq-trigger');

    faqTriggers.forEach(trigger => {
        trigger.addEventListener('change', function () {
            // If this trigger is checked (opened)
            if (this.checked) {
                // Close all other FAQ items
                faqTriggers.forEach(otherTrigger => {
                    if (otherTrigger !== this) {
                        otherTrigger.checked = false;
                    }
                });
            }
        });
    });

    // JavaScript to handle card switching and maintain one active card at all times
    document.addEventListener('DOMContentLoaded', function () {
        // Set About Us card as default active on page load
        document.querySelector('.about-card').classList.add('active');

        const cards = document.querySelectorAll('.info-card');

        cards.forEach(card => {
            card.addEventListener('mouseenter', function () {
                cards.forEach(c => c.classList.remove('active'));
                this.classList.add('active');
            });
        });

        // When mouse leaves the wrapper, default back to About Us
        const wrapper = document.querySelector('.info-cards-wrapper');
        wrapper.addEventListener('mouseleave', function () {
            cards.forEach(c => c.classList.remove('active'));
            document.querySelector('.about-card').classList.add('active');
        });
    });




    


 
        // Contact form submission with database save
        document.getElementById('contactForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitButton = this.querySelector('button[type="submit"]');
            const errorMessage = document.getElementById('errorMessage');
            
            // Hide any previous error messages
            errorMessage.style.display = 'none';
            
            // Disable submit button and show loading state
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            
            fetch('save_message.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.ok) {
                    // Show success modal
                    document.getElementById('modal').style.display = 'flex';
                    // Reset form
                    this.reset();
                } else if (response.status === 400) {
                    errorMessage.textContent = 'Please fill in all required fields.';
                    errorMessage.style.display = 'block';
                } else if (response.status === 500) {
                    errorMessage.textContent = 'Database connection failed. Please try again later.';
                    errorMessage.style.display = 'block';
                } else {
                    throw new Error('Failed to send message');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                errorMessage.textContent = 'Failed to send message. Please check your connection and try again.';
                errorMessage.style.display = 'block';
            })
            .finally(() => {
                // Re-enable submit button
                submitButton.disabled = false;
                submitButton.innerHTML = 'Send Message';
            });
        });

        // Close modal function
        function closeModal() {
            document.getElementById('modal').style.display = 'none';
        }

        // Close modal when clicking outside
        document.getElementById('modal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // FAQ accordion functionality - only one open at a time
        const faqTriggers = document.querySelectorAll('.faq-trigger');

        faqTriggers.forEach(trigger => {
            trigger.addEventListener('change', function() {
                // If this trigger is checked (opened)
                if (this.checked) {
                    // Close all other FAQ items
                    faqTriggers.forEach(otherTrigger => {
                        if (otherTrigger !== this) {
                            otherTrigger.checked = false;
                        }
                    });
                }
            });
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });


    
</script>


</body>
</html>