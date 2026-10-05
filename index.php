<?php session_start(); ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay San Isidro</title>
    <link rel="stylesheet" href="/BMS/STYLE/landing.css">
    <link rel="stylesheet" href="/BMS/CHATBOT/chatbot.css">

</head>
<body>

<?php if (isset($_SESSION['msg_success']) || isset($_SESSION['msg_error'])): ?>
<div id="popupModal" class="popup">
    <div class="popup-content <?php echo isset($_SESSION['msg_success']) ? 'success' : 'error'; ?>">
        <div class="popup-icon">
            <?php echo isset($_SESSION['msg_success']) ? '✔' : '✖'; ?>
        </div>

        <h3><?php echo isset($_SESSION['msg_success']) ? 'Success' : 'Error'; ?></h3>

        <p>
            <?php
                if (isset($_SESSION['msg_success'])) {
                    echo $_SESSION['msg_success'];
                    unset($_SESSION['msg_success']);
                }
                if (isset($_SESSION['msg_error'])) {
                    echo $_SESSION['msg_error'];
                    unset($_SESSION['msg_error']);
                }
            ?>
        </p>

        <button onclick="closePopup()">OK</button>
    </div>
</div>
<?php endif; ?>

<header class="topnav">
    <div class="logo-area">
        <img src="/BMS/IMAGES/silogo.png" alt="San Isidro Logo" class="logo1">
        <img src="/BMS/IMAGES/Home.png" alt="One Cainta Logo" class="logo2">
    </div>

    <ul class="nav-links">
        <li><a href="#" id="homeBtn">Home</a></li>
        <li><a href="#" id="aboutBtn">About</a></li>
        <li><a href="#" id="contactBtn">Contact</a></li>
        <li><a href="/BMS/CODES/login.php" class="account-link">Account</a></li>
    </ul>
</header>

<div class="fullbg">
    <section class="topp">
        <div class="top-text">
            <div class="top-badge">Official Online Portal of Barangay San Isidro</div>

            <h1>Welcome to</h1>
            <h1>Barangay</h1>
            <h1 class="san">SAN <span>ISIDRO</span></h1>

            <p class="hero-subtext">
                A centralized web-based platform that helps residents access barangay services,
                submit requests, receive updates, and connect with the barangay more easily.
            </p>

            <div class="hero-buttons">
                <a href="/BMS/CODES/login.php" class="hero-btn primary">Get Started</a>
                <a href="#" id="learnMoreBtn" class="hero-btn secondary">Learn More</a>
            </div>
        </div>

        <div class="top-logo">
            <img src="/BMS/IMAGES/silogo.png" alt="Barangay San Isidro Logo">
        </div>
    </section>
</div>

<section class="features">
    <div class="section-wrap">
        <h2 class="section-title">Features</h2>
        <p class="section-subtitle">
            Designed to make barangay services faster, more organized, and more accessible for residents.
        </p>

        <div class="boxcontainer">
            <a href="/BMS/CODES/login.php">
                <div class="box">
                    <img src="/BMS/IMAGES/e-certificate.png" alt="E-Certificate">
                    <h3>E-Certificate</h3>
                    <p>Request barangay documents online in a faster and more convenient way.</p>
                </div>
            </a>

            <a href="/BMS/CODES/login.php">
                <div class="box">
                    <img src="/BMS/IMAGES/report.png" alt="Incident Report">
                    <h3>Incident Report</h3>
                    <p>Submit concerns and incident reports securely through the online portal.</p>
                </div>
            </a>

            <a href="/BMS/CODES/login.php">
                <div class="box">
                    <img src="/BMS/IMAGES/alert.png" alt="Request Tracking">
                    <h3>Request Tracking</h3>
                    <p>Track the status of submitted requests online in a simple and organized way.</p>
                </div>
            </a>

            <a href="#">
                <div class="box">
                    <img src="/BMS/IMAGES/bot.png" alt="AI ChatBot">
                    <h3>AI ChatBot</h3>
                    <p>Receive instant answers to common barangay questions anytime you need help.</p>
                </div>
            </a>
        </div>
    </div>
</section>

<section class="about">
    <div class="section-wrap">
        <h2 class="section-title">About Barangay San Isidro</h2>
        <p class="section-subtitle">
            Learn more about the background, identity, and development of the barangay.
        </p>

        <div class="about-container">
            <div class="about-text">
                <p>
                    <strong>Barangay San Isidro</strong>, located in the Municipality of Cainta, Rizal,
                    was originally an agricultural community named after San Isidro Labrador,
                    the patron saint of farmers. The barangay became known for its rice fields
                    and traditional delicacies such as bibingka and suman, which remain
                    part of its cultural heritage.
                </p>

                <p>
                    In the 1960s and 1970s, with the expansion of Metro Manila,
                    Barangay San Isidro gradually transformed from a quiet farming
                    village into a growing residential and commercial community.
                    Today, it continues to provide essential public services and maintain
                    community records for its residents through more organized and modern systems.
                </p>
            </div>

            <div class="about-image">
                <img src="/BMS/IMAGES/San Isidro.jpg" alt="Barangay San Isidro">
            </div>
        </div>
    </div>
</section>

<section class="misvis">
    <div class="section-wrap">
        <h2 class="section-title">Mission and Vision</h2>
        <p class="section-subtitle">
            The guiding principles of Barangay San Isidro in serving the community.
        </p>

        <div class="misvis-grid">
            <div class="card mission">
                <h2>Mission</h2>
                <p>
                    To provide fast, transparent, and reliable public service
                    through an organized digital system that promotes
                    efficiency and community participation.
                </p>
            </div>

            <div class="card vision">
                <h2>Vision</h2>
                <p>
                    To become a modern and digitally empowered barangay that
                    ensures accessible services, promotes unity, and supports
                    sustainable community development.
                </p>
            </div>
        </div>
    </div>
</section>

<section class="contact">
    <div class="section-wrap">
        <h1 class="section-title">Contact Us</h1>
        <p class="section-subtitle">
            Reach out to the barangay for questions, concerns, and other service-related inquiries.
        </p>

        <div class="contact-container">
            <div class="contact-left">
                <img src="/BMS/IMAGES/balanti.png" alt="Barangay Hall">

                <div class="contact-info">
                    <p><strong>Address:</strong> Barangay Hall, San Isidro, Cainta, Rizal</p>
                    <p><strong>Contact Number:</strong> 09123456789</p>
                    <p><strong>Email:</strong> sanisidrobarangay@gmail.com</p>
                    <p><strong>Office Hours:</strong> Monday - Friday | 8:00 AM - 5:00 PM</p>
                </div>
            </div>

            <div class="contact-form">
                <h2>Message Us</h2>
                <form method="POST" action="/BMS/barangay_admin/send_message.php">
                    <input type="hidden" name="send_message" value="1">

                    <label for="name">Name</label>
                    <input type="text" id="name" name="name" required>

                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>

                    <label for="contact">Contact No.</label>
                    <input type="text" id="contact" name="contact" required>

                    <label for="message">Message</label>
                    <textarea id="message" name="message" required></textarea>

                    <button type="submit">Send Message</button>
                </form>
            </div>
        </div>
    </div>
</section>

<footer>
    <a href="/BMS/CODES/privacy-policy.php">Privacy Policy</a> |
    <a href="/BMS/CODES/cookie-policy.php">Cookie Policy</a>
    <p>© 2026 Barangay San Isidro Cainta Rizal | All Rights Reserved</p>
</footer>

<div class="chatbot-button" id="chatbotToggle" onclick="toggleChat()">
    <img src="/BMS/IMAGES/chat-icon.png" alt="Chat">
    <span class="chatbot-ping" aria-hidden="true"></span>
</div>

<div class="chatbot-container" id="chatbot">
    <div class="chat-header">
        <div class="chat-header-info">
            <div class="chat-avatar">B</div>
            <div>
                <div class="chat-title">Barangay Assistant</div>
                <div class="chat-status"><span class="status-dot" aria-hidden="true"></span>Online</div>
            </div>
        </div>
        <div class="chat-header-actions">
            <button type="button" id="speakerToggle" class="icon-btn" title="Toggle spoken replies" aria-pressed="true">🔊</button>
            <button type="button" onclick="toggleChat()" class="icon-btn close-chat" title="Close chat">✖</button>
        </div>
    </div>

    <div class="chat-body" id="chat-body" aria-live="polite">
        <div class="bot-row">
            <div class="chat-avatar small" aria-hidden="true">B</div>
            <div class="bot-msg">
                Hello! I am the Barangay Assistant. How can I help you today? You can type or tap the mic to speak.
            </div>
        </div>

        <div class="faq-buttons">
            <button onclick="askFAQ('What are the barangay office hours?')">Office Hours</button>
            <button onclick="askFAQ('How can I request barangay clearance?')">Clearance</button>
            <button onclick="askFAQ('How do I report an incident?')">Incident</button>
            <button onclick="askFAQ('What services does the barangay provide?')">Services</button>
            <button onclick="askFAQ('Is there a fee for certificate?')">Fees</button>
            <button onclick="askFAQ('What is the emergency hotline of the barangay?')">Emergency Contact</button>
        </div>
    </div>

    <div class="lang-switch" id="langSwitch" role="group" aria-label="Speech language">
        <span class="lang-label">Voice:</span>
        <button type="button" class="lang-btn active" data-lang="fil-PH">Filipino</button>
        <button type="button" class="lang-btn" data-lang="en-US">English</button>
    </div>

    <div class="chat-input">
        <button type="button" id="micBtn" class="mic-btn" title="Speak your question">
            <span class="mic-ring" aria-hidden="true"></span>
            <svg class="mic-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path d="M12 15a3 3 0 0 0 3-3V6a3 3 0 1 0-6 0v6a3 3 0 0 0 3 3Z"/>
                <path d="M19 11a7 7 0 0 1-14 0M12 19v3"/>
            </svg>
        </button>
        <input type="text" id="userMessage" placeholder="Ask something...">
        <button onclick="sendMessage()">Send</button>
    </div>
</div>

<div id="cookieBanner">
  <div class="cookie-content">
    
    <p>
      This website uses cookies to ensure proper functionality and better user experience.
      By clicking <strong>Accept</strong>, you agree to our use of cookies.
      Read more in our 
      <a href="/BMS/CODES/cookie-policy.php">Cookie Policy</a>.
    </p>

    <div class="cookie-buttons">
      <button class="cookie-btn-accept" onclick="acceptCookies()">Accept</button>
      <button class="cookie-btn-reject" onclick="rejectCookies()">Reject</button>
    </div>

  </div>
</div>

<script src="/BMS/CHATBOT/chatbot.js"></script>
<script src="/BMS/STYLE/landing.js"></script>

</body>
</html>