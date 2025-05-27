<?php
//=====================================================
// DATABASE CONNECTION
//=====================================================
$conn = new mysqli("localhost", "root", "", "ewaste_db");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "SELECT * FROM products ORDER BY product_id DESC";
$result = $conn->query($sql);

//=====================================================
// SESSION MANAGEMENT
//=====================================================
session_start();
$isLoggedIn = isset($_SESSION['user_id']);
$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
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

/* Admin Choice Modal Styles */
.admin-choice-modal {
    display: none;
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(0, 0, 0, 0.85);
    justify-content: center;
    align-items: center;
    z-index: 10000;
}

.admin-choice-box {
    background: #fff;
    padding: 40px;
    border-radius: 15px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.3);
    text-align: center;
    max-width: 450px;
    width: 90%;
}

.admin-choice-box h2 {
    color: #2e7d32;
    margin-bottom: 15px;
    font-size: 24px;
}

.admin-choice-box p {
    font-size: 16px;
    color: #666;
    margin-bottom: 30px;
}

.admin-choice-buttons {
    display: flex;
    gap: 15px;
    justify-content: center;
    flex-wrap: wrap;
}

.admin-choice-btn {
    padding: 12px 25px;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
    min-width: 150px;
}

.admin-choice-btn.dashboard {
    background: #4caf50;
    color: white;
}

.admin-choice-btn.dashboard:hover {
    background: #45a049;
    transform: translateY(-2px);
}

.admin-choice-btn.admin-panel {
    background:rgb(28, 96, 32);
    color: white;
}

.admin-choice-btn.admin-panel:hover {
    background:rgb(62, 89, 6);
    transform: translateY(-2px);
}

/* Admin indicator in navigation */
.admin-indicator {
    background:rgb(194, 237, 186);
    color: darkgreen;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 10px;
    font-weight: bold;
    margin-left: 5px;
    text-transform: uppercase;
}
  /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .modal-content-wrapper {
            background: white;
            border-radius: 15px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            transform: scale(0.8);
            transition: transform 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        .modal-overlay.active .modal-content-wrapper {
            transform: scale(1);
        }

        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.3rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .close-btn {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 5px;
            border-radius: 50%;
            transition: background-color 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 35px;
            height: 35px;
        }

        .close-btn:hover {
            background-color: rgba(255,255,255,0.2);
        }

        .modal-body-content {
            overflow-y: auto;
            padding: 0;
            flex-grow: 1;
        }

        .actual-modal-content {
            padding: 20px;
            line-height: 1.6;
            color: #333;
        }

        .actual-modal-content h1 {
            color: #667eea;
            font-size: 1.5rem;
            margin-bottom: 10px;
            text-align: center;
        }

        .actual-modal-content h2 {
            color: #667eea;
            font-size: 1.2rem;
            margin-top: 20px;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e9ecef;
        }

        .actual-modal-content h3 {
            color: #495057;
            font-size: 1.1rem;
            margin-top: 15px;
            margin-bottom: 10px;
        }

        .actual-modal-content p {
            margin-bottom: 10px;
            text-align: justify;
        }

        .actual-modal-content ul {
            margin: 10px 0;
            padding-left: 20px;
        }

        .actual-modal-content li {
            margin-bottom: 8px;
        }

        .actual-modal-content strong {
            color: #495057;
        }

        .effective-date {
            text-align: center;
            font-style: italic;
            color: #6c757d;
            margin-bottom: 20px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 8px;
        }

        .contact-info {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .final-acknowledgment {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            text-align: center;
            font-weight: 500;
        }

        .terms-link {
            color: #667eea;
            text-decoration: underline;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .terms-link:hover {
            color: #5a6fd8;
        }

        /* Scrollbar Styling */
        .modal-body-content::-webkit-scrollbar {
            width: 8px;
        }

        .modal-body-content::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .modal-body-content::-webkit-scrollbar-thumb {
            background: #667eea;
            border-radius: 4px;
        }

        .modal-body-content::-webkit-scrollbar-thumb:hover {
            background: #5a6fd8;
        }

</style>
</head>
 
<body>
    <!-- Login Popup -->
    <div id="loginPopup" class="login-popup">
        <div class="login-popup-content">
            <span class="login-popup-close">&times;</span>
            <h2>Welcome, <?php echo isset($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : 'User'; ?>!</h2>
            <p>You have successfully logged in to E-WastePH.</p>
            <?php if ($isAdmin): ?>
                <p style="color:rgb(15, 122, 42); font-weight: bold;">Admin Access Detected</p>
            <?php endif; ?>
            <button class="login-popup-button">Continue</button>
        </div>
    </div>

    <!-- Admin Choice Modal -->
    <?php if ($isAdmin): ?>
    <div id="adminChoiceModal" class="admin-choice-modal">
        <div class="admin-choice-box">
            <h2>Admin Access</h2>
            <p>Welcome, Admin! Where would you like to go?</p>
            <div class="admin-choice-buttons">
                <a href="userdash.php" class="admin-choice-btn dashboard">
                    <i class="fas fa-user"></i> User Dashboard
                </a>
                <a href="admin.php" class="admin-choice-btn admin-panel">
                    <i class="fas fa-cog"></i> Admin Panel
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

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
                        <a href="#" onclick="showUserOptions(event)">
                            <i class="fa fa-user"></i>
                            <?php if ($isAdmin): ?>
                                <span class="admin-indicator">Admin</span>
                            <?php endif; ?>
                        </a>
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


<div class="container12">
    <div class="container1">
        <!-- Contact Section (Left Side) -->
        <section id="contact" class="section contact-section">
            <h2>Contact Us</h2>
            <div class="contact-info">
                <p><strong>Email:</strong> support@ewasteph.org</p>
                <p><strong>Phone:</strong> 0929369606</p>
                <p><strong>Address:</strong> Las Piñas, Philippines</p>
            </div>

            <form class="contact-form" id="contactForm">
                <div id="errorMessage" class="error-message" style="display: none;"></div>
                <input type="text" name="name" placeholder="Enter your full name" required>
                <input type="email" name="email" placeholder="Enter your email address" required>
                <textarea name="message" placeholder="Write your message here..." rows="5" required></textarea>
                <button type="submit">Send Message</button>
            </form>
        </section>

        <!-- FAQ Section (Right Side) -->
        <section id="faq" class="section faq-section">
            <div class="faq-name">
                <h1 class="faq-header1">
                    <h1 class="faq-header">FAQ</h1>
                    <h1 class="faq-header"><b>Need Assistance?</b></h1>
                </h1>
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
                            <li>Step 1: Create an account on our platform.</li>
                            <li>Step 2: List your e-waste with photos and descriptions.</li>
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

        
        <!-- Modal -->
        <div id="modal" class="modal-overlay">
            <div class="modal-box">
                <button class="close-icon" onclick="closeModal()" aria-label="Close popup">&times;</button>
                <i class="fas fa-check-circle success-icon"></i>
                <h2>Message Sent!</h2>
                <p>Thank you for contacting us. We'll get back to you within 24 hours.</p>
                <button class="modal-btn" onclick="closeModal()">Continue</button>
            </div>
        </div>
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
<!-- Terms and Conditions Modal -->
    <div class="modal-overlay" id="termsModalOverlay" onclick="closeTermsModal(event)">
        <div class="modal-content-wrapper" onclick="event.stopPropagation()">
            <div class="modal-header">
                <div class="modal-title">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14,2 14,8 20,8"/>
                        <line x1="16" y1="13" x2="8" y2="13"/>
                        <line x1="16" y1="17" x2="8" y2="17"/>
                        <polyline points="10,9 9,9 8,9"/>
                    </svg>
                    Terms and Conditions
                </div>
                <button class="close-btn" onclick="closeTermsModal()">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
            <div class="modal-body-content"> 
              <div class="actual-modal-content">
                  <h1>Terms and Conditions</h1>
                  <div class="effective-date">
                      <strong>E-Waste PH</strong><br>
                      <strong>Effective Date:</strong> <?php echo date("F j, Y"); ?><br>
                      <strong>Last Updated:</strong> <?php echo date("F j, Y"); ?>
                  </div>

                  <h2>1. Introduction and Acceptance</h2>
                  <p>Welcome to E-Waste PH ("we," "our," "us"). These Terms and Conditions ("Terms") govern your use of our website located at www.ewasteph.com and all related services provided by E-Waste PH.</p>
                  <p>By accessing or using our website and services, you agree to be bound by these Terms. If you do not agree to these Terms, please do not use our services.</p>

                  <h2>2. About Our Services</h2>
                  <p>E-Waste PH provides electronic waste collection, recycling, and disposal services in the Philippines. Our services include:</p>
                  <ul>
                      <li>Collection of electronic waste from residential and commercial locations</li>
                      <li>Proper recycling and disposal of electronic devices</li>
                      <li>Data destruction services</li>
                      <li>Environmental compliance certification</li>
                      <li>Educational resources about e-waste management</li>
                      <li>Online marketplace for buying and selling refurbished electronics</li>
                  </ul>

                  <h2>3. Definitions</h2>
                  <ul>
                      <li><strong>"E-waste"</strong> refers to discarded electronic devices and equipment</li>
                      <li><strong>"User"</strong> refers to any person or entity using our website or services</li>
                      <li><strong>"Services"</strong> refers to all services provided by E-Waste PH</li>
                      <li><strong>"Content"</strong> refers to all information, text, images, and materials on our website</li>
                      <li><strong>"Listing"</strong> refers to any item posted for sale or exchange on our platform</li>
                      <li><strong>"Seller"</strong> refers to users who list items for sale on our platform</li>
                      <li><strong>"Buyer"</strong> refers to users who purchase items through our platform</li>
                  </ul>

                  <h2>4. User Eligibility and Account Registration</h2>
                  <h3>4.1 Eligibility</h3>
                  <ul>
                      <li>Users must be at least 18 years old or have parental/guardian consent</li>
                      <li>Users must provide accurate and complete information</li>
                      <li>Users must comply with all applicable Philippine laws and regulations</li>
                  </ul>
                  <h3>4.2 Account Registration</h3>
                  <ul>
                      <li>Some services may require account creation</li>
                      <li>You are responsible for maintaining account security</li>
                      <li>You must notify us immediately of any unauthorized access</li>
                      <li>One person or entity per account unless otherwise authorized</li>
                  </ul>
                  <h3>4.3 Identity Verification and Truthful Information</h3>
                  <ul>
                      <li>Users must provide accurate, current, and truthful information</li>
                      <li>Falsification of identity, contact information, or personal details is strictly prohibited</li>
                      <li>Users must promptly update any changes to their account information</li>
                      <li>We reserve the right to verify user identity and may request additional documentation</li>
                      <li>Providing false information may result in immediate account suspension or termination</li>
                  </ul>

                  <h2>5. User Conduct and Prohibited Activities</h2>
                  <h3>5.1 Acceptable Use</h3>
                  <p>Users must conduct themselves professionally and respectfully when using our services and interacting with other users, staff, or partners.</p>
                  <h3>5.2 Prohibited Activities</h3>
                  <p>Users are strictly prohibited from:</p>
                  <ul>
                      <li>Providing false, misleading, or inaccurate information</li>
                      <li>Impersonating another person or entity</li>
                      <li>Creating multiple accounts to circumvent restrictions</li>
                      <li>Posting inappropriate, offensive, or harmful content</li>
                      <li>Using profanity, hate speech, or discriminatory language</li>
                      <li>Sharing content that is defamatory, threatening, or harassing</li>
                      <li>Posting sexually explicit, violent, or illegal content</li>
                      <li>Engaging in fraudulent activities or scams</li>
                      <li>Attempting to bypass security measures</li>
                      <li>Interfering with the proper functioning of our services</li>
                  </ul>
                  <h3>5.3 Content Standards</h3>
                  <p>All user-generated content must:</p>
                  <ul>
                      <li>Be accurate and truthful</li>
                      <li>Comply with Philippine laws and regulations</li>
                      <li>Respect intellectual property rights</li>
                      <li>Be appropriate for all audiences</li>
                      <li>Not contain spam, advertisements, or promotional material (unless authorized)</li>
                  </ul>

                  <h2>6. Listing Terms and Marketplace Rules</h2>
                  <h3>6.1 Listing Requirements</h3>
                  <ul>
                      <li>All listings must accurately describe the item being sold</li>
                      <li>Photos must be clear, recent, and accurately represent the item</li>
                      <li>Pricing must be clearly stated and reasonable</li>
                      <li>Item condition must be honestly described</li>
                      <li>Only functional or repairable electronic items may be listed</li>
                  </ul>
                  <h3>6.2 Listing Restrictions</h3>
                  <p>The following items may not be listed:</p>
                  <ul>
                      <li>Stolen or illegally obtained items</li>
                      <li>Items that violate intellectual property rights</li>
                      <li>Dangerous or hazardous materials</li>
                      <li>Non-electronic items (unless specifically allowed)</li>
                      <li>Items requiring special permits or licenses</li>
                      <li>Counterfeit or replica items marketed as genuine</li>
                  </ul>
                  <h3>6.3 Listing Management</h3>
                  <ul>
                      <li>We reserve the right to remove any listing that violates these Terms</li>
                      <li>Listings may be subject to review and approval</li>
                      <li>Users are responsible for keeping their listings current and accurate</li>
                      <li>Inactive or expired listings may be automatically removed</li>
                  </ul>
                  <h3>6.4 Transaction Responsibilities</h3>
                  <ul>
                      <li>Sellers are responsible for accurate item descriptions</li>
                      <li>Buyers are responsible for inspecting items before purchase</li>
                      <li>Payment and delivery arrangements are between buyers and sellers</li>
                      <li>We may provide dispute resolution services but are not liable for transaction outcomes</li>
                  </ul>

                  <h2>7. Service Terms and Scheduling</h2>
                  <h3>7.1 Collection Services</h3>
                  <ul>
                      <li>Collection services are available within our designated service areas</li>
                      <li>Scheduling is subject to availability and confirmation</li>
                      <li>We reserve the right to refuse collection of certain items or materials</li>
                      <li>Minimum quantities may apply for collection services</li>
                  </ul>
                  <h3>7.2 Accepted Items</h3>
                  <p>We accept various electronic devices including but not limited to:</p>
                  <ul>
                      <li>Computers, laptops, and tablets</li>
                      <li>Mobile phones and accessories</li>
                      <li>Televisions and monitors</li>
                      <li>Printers and office equipment</li>
                      <li>Home appliances with electronic components</li>
                  </ul>
                  <h3>7.3 Prohibited Items</h3>
                  <p>We do not accept:</p>
                  <ul>
                      <li>Hazardous materials beyond standard e-waste</li>
                      <li>Items containing radioactive materials</li>
                      <li>Medical devices without proper authorization</li>
                      <li>Items that pose safety risks to our personnel</li>
                  </ul>

                  <h2>8. Pricing and Payment</h2>
                  <h3>8.1 Service Fees</h3>
                  <ul>
                      <li>Pricing information is available on our website or upon request</li>
                      <li>Fees may vary based on location, quantity, and type of items</li>
                      <li>Special handling fees may apply for certain items</li>
                      <li>Listing fees may apply for marketplace services</li>
                  </ul>
                  <h3>8.2 Payment Terms</h3>
                  <ul>
                      <li>Payment is due as specified in your service agreement</li>
                      <li>We accept various payment methods as indicated on our website</li>
                      <li>Late payment fees may apply for overdue accounts</li>
                      <li>All prices are in Philippine Peso (PHP) unless otherwise stated</li>
                  </ul>

                  <h2>9. Data Security and Privacy</h2>
                  <h3>9.1 Data Destruction</h3>
                  <ul>
                      <li>We provide secure data destruction services for storage devices</li>
                      <li>Data destruction certificates are available upon request</li>
                      <li>We are not responsible for data not properly backed up by users</li>
                      <li>Users should remove personal data before service when possible</li>
                  </ul>
                  <h3>9.2 Privacy</h3>
                  <ul>
                      <li>Our <span class="terms-link" onclick="openPrivacyPolicyModal()">Privacy Policy</span> governs the collection and use of personal information</li>
                      <li>We implement industry-standard security measures</li>
                      <li>Personal information is handled in compliance with Philippine data protection laws</li>
                  </ul>

                  <h2>10. Environmental Compliance</h2>
                  <h3>10.1 Regulatory Compliance</h3>
                  <ul>
                      <li>We operate in compliance with Philippine environmental laws</li>
                      <li>We maintain proper licenses and certifications</li>
                      <li>We follow Department of Environment and Natural Resources (DENR) guidelines</li>
                      <li>We adhere to international e-waste management standards</li>
                  </ul>
                  <h3>10.2 Certificates and Documentation</h3>
                  <ul>
                      <li>Certificates of proper disposal are available upon request</li>
                      <li>We maintain records of all processed materials</li>
                      <li>Compliance documentation is available for audit purposes</li>
                  </ul>

                  <h2>11. Moderation and Enforcement</h2>
                  <h3>11.1 Content Moderation</h3>
                  <ul>
                      <li>We reserve the right to monitor, review, and moderate all user content</li>
                      <li>Inappropriate content will be removed without notice</li>
                      <li>Repeated violations may result in account restrictions or termination</li>
                  </ul>
                  <h3>11.2 Enforcement Actions</h3>
                  <p>Violations of these Terms may result in:</p>
                  <ul>
                      <li>Content removal</li>
                      <li>Account warnings or restrictions</li>
                      <li>Temporary or permanent account suspension</li>
                      <li>Legal action where appropriate</li>
                      <li>Cooperation with law enforcement authorities</li>
                  </ul>

                  <h2>12. Liability and Disclaimers</h2>
                  <h3>12.1 Service Disclaimers</h3>
                  <ul>
                      <li>Services are provided "as is" without warranties</li>
                      <li>We do not guarantee specific outcomes or timelines</li>
                      <li>Weather, traffic, and other factors may affect service delivery</li>
                      <li>We reserve the right to modify or discontinue services</li>
                  </ul>
                  <h3>12.2 Limitation of Liability</h3>
                  <ul>
                      <li>Our liability is limited to the fees paid for specific services</li>
                      <li>We are not liable for indirect, consequential, or punitive damages</li>
                      <li>Total liability shall not exceed the amount paid for services</li>
                      <li>Users assume responsibility for backing up important data</li>
                  </ul>
                  <h3>12.3 Indemnification</h3>
                  <p>Users agree to indemnify E-Waste PH against claims arising from:</p>
                  <ul>
                      <li>Misrepresentation of items or materials</li>
                      <li>Violation of applicable laws or regulations</li>
                      <li>Unauthorized or improper use of our services</li>
                      <li>Breach of these Terms and Conditions</li>
                      <li>False information or identity falsification</li>
                      <li>Inappropriate content or conduct</li>
                  </ul>

                  <h2>13. Intellectual Property</h2>
                  <h3>13.1 Our Content</h3>
                  <ul>
                      <li>All website content is owned by E-Waste PH or licensed to us</li>
                      <li>Users may not reproduce, distribute, or modify our content without permission</li>
                      <li>Our trademarks and logos are protected intellectual property</li>
                  </ul>
                  <h3>13.2 User Content</h3>
                  <ul>
                      <li>Users retain ownership of content they provide to us</li>
                      <li>Users grant us license to use provided content for service delivery</li>
                      <li>Users represent that they have rights to all provided content</li>
                  </ul>

                  <h2>14. Cancellation and Refunds</h2>
                  <h3>14.1 Service Cancellation</h3>
                  <ul>
                      <li>Services may be cancelled with reasonable notice</li>
                      <li>Cancellation fees may apply depending on timing and circumstances</li>
                      <li>Scheduled collections must be cancelled at least 24 hours in advance</li>
                  </ul>
                  <h3>14.2 Refund Policy</h3>
                  <ul>
                      <li>Refunds are considered on a case-by-case basis</li>
                      <li>Service fees are generally non-refundable once services are rendered</li>
                      <li>Unused service credits may be refunded at our discretion</li>
                  </ul>

                  <h2>15. Force Majeure</h2>
                  <p>We are not liable for delays or failures due to circumstances beyond our reasonable control, including:</p>
                  <ul>
                      <li>Natural disasters, weather conditions, or acts of God</li>
                      <li>Government actions, regulations, or restrictions</li>
                      <li>Labor disputes, strikes, or transportation issues</li>
                      <li>Technical failures or infrastructure problems</li>
                  </ul>

                  <h2>16. Governing Law and Dispute Resolution</h2>
                  <h3>16.1 Governing Law</h3>
                  <p>These Terms are governed by the laws of the Republic of the Philippines.</p>
                  <h3>16.2 Dispute Resolution</h3>
                  <ul>
                      <li>Disputes should first be addressed through direct communication</li>
                      <li>Mediation may be pursued for unresolved disputes</li>
                      <li>Philippine courts have jurisdiction over legal proceedings</li>
                      <li>Users consent to venue in Quezon City for legal matters</li>
                  </ul>

                  <h2>17. Modifications and Updates</h2>
                  <h3>17.1 Terms Updates</h3>
                  <ul>
                      <li>We reserve the right to modify these Terms at any time</li>
                      <li>Material changes will be communicated via website notice or email</li>
                      <li>Continued use of services constitutes acceptance of updated Terms</li>
                      <li>Users should regularly review Terms for changes</li>
                  </ul>
                  <h3>17.2 Service Changes</h3>
                  <ul>
                      <li>We may modify, suspend, or discontinue services with notice</li>
                      <li>New features may be subject to additional terms</li>
                      <li>We strive to provide advance notice of significant changes</li>
                  </ul>

                  <h2>18. Termination</h2>
                  <h3>18.1 Termination by Users</h3>
                  <ul>
                      <li>Users may terminate their account at any time</li>
                      <li>Outstanding obligations survive termination</li>
                      <li>Data and content may be deleted upon termination</li>
                  </ul>
                  <h3>18.2 Termination by E-Waste PH</h3>
                  <p>We may terminate user accounts for:</p>
                  <ul>
                      <li>Violation of these Terms</li>
                      <li>Fraudulent or illegal activity</li>
                      <li>Non-payment of fees</li>
                      <li>Abuse of our services or personnel</li>
                      <li>Providing false information or falsifying identity</li>
                      <li>Posting inappropriate content</li>
                      <li>Repeated policy violations</li>
                  </ul>

                  <h2>19. Miscellaneous Provisions</h2>
                  <h3>19.1 Entire Agreement</h3>
                  <p>These Terms, along with our <span class="terms-link" onclick="openPrivacyPolicyModal()">Privacy Policy</span>, constitute the entire agreement between users and E-Waste PH.</p>
                  <h3>19.2 Severability</h3>
                  <p>If any provision of these Terms is found invalid, the remaining provisions shall remain in effect.</p>
                  <h3>19.3 Assignment</h3>
                  <p>Users may not assign their rights under these Terms without our written consent.</p>
                  <h3>19.4 Waiver</h3>
                  <p>Failure to enforce any provision does not constitute a waiver of our rights.</p>

                  <h2>20. Contact Information</h2>
                  <div class="contact-info">
                      <p>For questions about these Terms and Conditions, please contact us:</p>
                      <p><strong>E-Waste PH</strong><br>
                      Email: ewasteph.services@gmail.com<br>
                      Phone: +63 912 345 6789<br>
                      Address: 123 E-Waste Avenue, Quezon City, Philippines<br>
                      Website: www.ewasteph.com</p>
                  </div>

                  <div class="final-acknowledgment">
                      <strong>By using our services, you acknowledge that you have read, understood, and agree to be bound by these Terms and Conditions.</strong>
                  </div>
              </div>
          </div>
      </div>
  </div>
    <!-- Back to Top Button -->
    <button id="upButton" title="Go to top">
        <i class="fa fa-arrow-up"></i>
    </button>

    <!-- Footer -->
    <footer>
        <p>&copy; 2024 EWastePH. All rights reserved. </p>
        <div class="footer-links">
           <a href="#" onclick="openTermsModal(event)">Terms and Conditions</a> 
            <a href="#" onclick="openTermsModal(event)">Privacy Policy</a>
        </div>
        <div class="footer-social">
            <a href="https://www.facebook.com/yourpage" target="_blank"><i class="fab fa-facebook-f"></i></a>
            <a href="https://twitter.com/yourprofile" target="_blank"><i class="fab fa-twitter"></i></a>
            <a href="https://www.instagram.com/yourprofile" target="_blank"><i class="fab fa-instagram"></i></a>
        </div>
    </footer>
<script>
    //for footer modal
      function openTermsModal(event) {
            event.preventDefault(); // Prevent default link behavior
            const modalOverlay = document.getElementById('termsModalOverlay');
            if (modalOverlay) {
                modalOverlay.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        }

        function closeTermsModal(event) {
            const modalOverlay = document.getElementById('termsModalOverlay');
            // If the click is on the overlay itself or on a close button
            if (modalOverlay && ((event && event.target === modalOverlay) || (event && event.target.closest('.close-btn')) || !event)) {
                modalOverlay.classList.remove('active');
                document.body.style.overflow = 'auto';
            }
        }

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const modalOverlay = document.getElementById('termsModalOverlay');
                if (modalOverlay && modalOverlay.classList.contains('active')) {
                    closeTermsModal();
                }
            }
        });

        // Set current date on page load
        document.addEventListener('DOMContentLoaded', function() {
            const currentDate = new Date().toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            
            const currentDateEl = document.getElementById('current-date');
            const updatedDateEl = document.getElementById('updated-date');
            
            if (currentDateEl) currentDateEl.textContent = currentDate;
            if (updatedDateEl) updatedDateEl.textContent = currentDate;
        });

   
        function openPrivacyPolicyModal() {
            alert('Privacy Policy modal would open here. You can implement this similarly to the Terms modal.');
        }
            // script for login
            const isLoggedIn = <?php echo $isLoggedIn ? 'true' : 'false'; ?>;
            const isAdmin = <?php echo $isAdmin ? 'true' : 'false'; ?>;

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

            // To show your loggedin
            function showUserOptions(event) {
                event.preventDefault();
                
                if (isAdmin) {
                    document.getElementById('adminChoiceModal').style.display = 'flex';
                } else {
                 
                    window.location.href = 'userdash.php';
                }
            }

            document.getElementById('adminChoiceModal')?.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.style.display = 'none';
                }
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    const adminModal = document.getElementById('adminChoiceModal');
                    if (adminModal && adminModal.style.display === 'flex') {
                        adminModal.style.display = 'none';
                    }
                }
            });
 
        // Login popup 
        document.addEventListener('DOMContentLoaded', function() {
            const justLoggedIn = <?php echo $justLoggedIn ? 'true' : 'false'; ?>;
            const loginPopup = document.getElementById('loginPopup');
            
            if (justLoggedIn && loginPopup) {
                
                setTimeout(function() {
                    loginPopup.classList.add('show');
                }, 250);
                
                const closeBtn = loginPopup.querySelector('.login-popup-close');
                if (closeBtn) {
                    closeBtn.addEventListener('click', function() {
                        loginPopup.classList.remove('show');
                        // Show admin choice modal after closing login popup if user is admin
                        if (isAdmin) {
                            setTimeout(() => {
                                document.getElementById('adminChoiceModal').style.display = 'flex';
                            }, 300);
                        }
                    });
                }
                
                const continueBtn = loginPopup.querySelector('.login-popup-button');
                if (continueBtn) {
                    continueBtn.addEventListener('click', function() {
                        loginPopup.classList.remove('show');
                        // Show admin choice modal after closing login popup if user is admin
                        if (isAdmin) {
                            setTimeout(() => {
                                document.getElementById('adminChoiceModal').style.display = 'flex';
                            }, 300);
                        }
                    });
                }

                loginPopup.addEventListener('click', function(e) {
                    if (e.target === loginPopup) {
                        loginPopup.classList.remove('show');
                        // Show admin choice modal after closing login popup if user is admin
                        if (isAdmin) {
                            setTimeout(() => {
                                document.getElementById('adminChoiceModal').style.display = 'flex';
                            }, 300);
                        }
                    }
                });
            }
        });

        //Password toggle functionality
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.toggle-password').forEach(function(toggle) {
            toggle.addEventListener('click', function() {
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
            signupForm.addEventListener('submit', function(event) {
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





    // About Us Cards - Default Active State Management
    document.addEventListener('DOMContentLoaded', function() {
    const aboutCard = document.querySelector('.info-card.about-card');
    const missionCard = document.querySelector('.info-card.mission-card');
    const visionCard = document.querySelector('.info-card.vision-card');
    const allCards = [aboutCard, missionCard, visionCard];
    
    let activeTimeout;
    let isUserInteracting = false;
    
    // Function to set about card as active
    function setAboutCardActive() {
        // Remove active class from all cards
        allCards.forEach(card => {
            if (card) card.classList.remove('active');
        });
        
        // Add active class to about card
        if (aboutCard) {
            aboutCard.classList.add('active');
        }
    }
    
    // Function to handle user interaction start
    function handleInteractionStart(card) {
        isUserInteracting = true;
        clearTimeout(activeTimeout);
        
        // Remove active from all cards
        allCards.forEach(c => {
            if (c) c.classList.remove('active');
        });
        
        // Add active to hovered card
        if (card) {
            card.classList.add('active');
        }
    }
    
    // Function to handle user interaction end
    function handleInteractionEnd() {
        isUserInteracting = false;
        
        // Set a timeout to return to default state after user stops interacting
        activeTimeout = setTimeout(() => {
            if (!isUserInteracting) {
                setAboutCardActive();
            }
        }, 500); // 500ms delay before returning to default
    }
    
    // Add event listeners to each card
    allCards.forEach(card => {
        if (card) {
            // Mouse events
            card.addEventListener('mouseenter', () => handleInteractionStart(card));
            card.addEventListener('mouseleave', handleInteractionEnd);
            
            // Touch events for mobile
            card.addEventListener('touchstart', () => handleInteractionStart(card));
            card.addEventListener('touchend', handleInteractionEnd);
            
            // Click events
            card.addEventListener('click', () => handleInteractionStart(card));
        }
    });
    
    // Set initial active state to about card
    setAboutCardActive();
    
    // Optional: Add click outside functionality
    document.addEventListener('click', function(event) {
        const clickedInsideCards = allCards.some(card => 
            card && card.contains(event.target)
        );
        
        if (!clickedInsideCards) {
            handleInteractionEnd();
        }
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