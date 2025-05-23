<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact & FAQ - E-WastePH</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            color: #333;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .container1 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .section {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(10px);
        }

        /* Contact Section Styles */
        .contact-section h2 {
            color: #4a5568;
            margin-bottom: 1.5rem;
            font-size: 2rem;
            text-align: center;
        }

        .contact-info {
            background: linear-gradient(135deg, #667eea, #764ba2);
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            color: white;
        }

        .contact-info p {
            margin: 0.5rem 0;
            font-size: 1.1rem;
        }

        .contact-form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .contact-form input,
        .contact-form textarea {
            padding: 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .contact-form input:focus,
        .contact-form textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .contact-form button {
            padding: 1rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .contact-form button:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .contact-form button:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        /* Error message styles */
        .error-message {
            background: #fed7d7;
            color: #c53030;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border-left: 4px solid #e53e3e;
        }

        /* Enhanced FAQ Section Styles */
        .faq-section {
            height: fit-content;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(240, 245, 255, 0.95) 100%);
        }

        .faq-name {
            text-align: center;
            margin-bottom: 2.5rem;
            position: relative;
        }

        .faq-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
            font-size: 2.5rem;
            font-weight: bold;
        }

        .faq-subtitle {
            color: #4a5568;
            font-size: 1.8rem;
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .faq-welcome {
            color: #718096;
            font-size: 1.1rem;
            line-height: 1.7;
            max-width: 90%;
            margin: 0 auto;
            background: rgba(102, 126, 234, 0.05);
            padding: 1.5rem;
            border-radius: 12px;
            border-left: 4px solid #667eea;
        }

        .faq-box {
            margin-top: 2.5rem;
        }

        .faq-wrapper {
            margin-bottom: 1.5rem;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            background: white;
            border: 1px solid rgba(102, 126, 234, 0.1);
        }

        .faq-wrapper:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.15);
        }

        .faq-trigger {
            display: none;
        }

        .faq-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            cursor: pointer;
            font-weight: 600;
            color: #2d3748;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .faq-title::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(102, 126, 234, 0.1), transparent);
            transition: left 0.5s;
        }

        .faq-title:hover::before {
            left: 100%;
        }

        .faq-title:hover {
            background: linear-gradient(135deg, #edf2f7 0%, #cbd5e0 100%);
            color: #667eea;
        }

        .faq-title i {
            transition: all 0.3s ease;
            font-size: 1.2rem;
            color: #667eea;
            background: rgba(102, 126, 234, 0.1);
            padding: 0.5rem;
            border-radius: 50%;
            width: 2.5rem;
            height: 2.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .faq-trigger:checked + .faq-title i {
            transform: rotate(90deg);
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .faq-trigger:checked + .faq-title {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .faq-detail {
            max-height: 0;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            opacity: 0;
        }

        .faq-trigger:checked + .faq-title + .faq-detail {
            max-height: 400px;
            opacity: 1;
        }

        .faq-detail p,
        .faq-detail ul {
            padding: 2rem;
            margin: 0;
            color: #4a5568;
            line-height: 1.7;
            font-size: 1.05rem;
        }

        .faq-detail ul {
            padding-left: 3rem;
            padding-top: 1rem;
        }

        .faq-detail li {
            margin: 0.8rem 0;
            position: relative;
            padding-left: 1rem;
        }

        .faq-detail li::before {
            content: '✓';
            position: absolute;
            left: -0.5rem;
            color: #667eea;
            font-weight: bold;
            background: rgba(102, 126, 234, 0.1);
            border-radius: 50%;
            width: 1.5rem;
            height: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
        }

        /* Add number badges to FAQ items */
        .faq-wrapper {
            position: relative;
        }

        .faq-wrapper::before {
            content: counter(faq-counter);
            counter-increment: faq-counter;
            position: absolute;
            top: -0.8rem;
            left: 1.5rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            width: 2rem;
            height: 2rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.9rem;
            z-index: 10;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
        }

        .faq-box {
            counter-reset: faq-counter;
        }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .modal-box {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            text-align: center;
            max-width: 400px;
            width: 90%;
            position: relative;
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .close-icon {
            position: absolute;
            top: 10px;
            right: 15px;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #999;
        }

        .success-icon {
            font-size: 3rem;
            color: #48bb78;
            margin-bottom: 1rem;
        }

        .modal-box h2 {
            color: #4a5568;
            margin-bottom: 1rem;
        }

        .modal-box p {
            color: #718096;
            margin-bottom: 1.5rem;
        }

        .modal-btn,
        .btn {
            padding: 0.8rem 2rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .modal-btn:hover,
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        /* Scroll animation for FAQ items */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .faq-wrapper {
            animation: fadeInUp 0.6s ease forwards;
        }

        .faq-wrapper:nth-child(1) { animation-delay: 0.1s; }
        .faq-wrapper:nth-child(2) { animation-delay: 0.2s; }
        .faq-wrapper:nth-child(3) { animation-delay: 0.3s; }
        .faq-wrapper:nth-child(4) { animation-delay: 0.4s; }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container1 {
                grid-template-columns: 1fr;
                gap: 1rem;
                margin: 1rem auto;
            }

            .section {
                padding: 1.5rem;
            }

            .faq-header {
                font-size: 2rem;
            }

            .faq-subtitle {
                font-size: 1.4rem;
            }

            .contact-section h2 {
                font-size: 1.5rem;
            }

            .faq-welcome {
                font-size: 1rem;
                padding: 1rem;
            }

            .faq-title {
                padding: 1.2rem;
                font-size: 0.95rem;
            }

            .faq-detail p,
            .faq-detail ul {
                padding: 1.5rem;
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
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

        <!-- Enhanced FAQ Section (Right Side) -->
        <section id="faq" class="section faq-section">
            <div class="faq-name">
                <h1 class="faq-header">FAQ</h1>
                <h2 class="faq-subtitle">Need Assistance?</h2>
                <div class="faq-welcome">
                    Welcome to the E-WastePH Shop FAQ section! Here, we answer your most frequently asked questions about our electronic waste shop, where you can buy and sell e-waste scraps. We're committed to promoting sustainable practices by giving old electronics a new purpose.
                </div>
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
                        <p>You can find a variety of electronic items and components:</p>
                        <ul>
                            <li>Refurbished electronics ready for use</li>
                            <li>Spare parts for electronic repairs</li>
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
                        <p>Selling your e-waste is simple and straightforward:</p>
                        <ul>
                            <li>Create an account on our platform</li>
                            <li>List your e-waste with photos and descriptions</li>
                            <li>Set a price or choose to recycle it for free</li>
                            <li>Connect with buyers or schedule a pickup/drop-off</li>
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
                            E-waste contains valuable materials like gold, silver, and copper, but also harmful chemicals that can damage the environment if improperly disposed of. Recycling helps recover these materials and reduces landfill waste, contributing to a sustainable future for everyone.
                        </p>
                    </div>
                </div>
            </div>
        </section>
    </div>

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

    <script>
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

        // Add smooth scroll behavior for better UX
        document.documentElement.style.scrollBehavior = 'smooth';
    </script>
</body>
</html>