<!DOCTYPE html>
<html>
<head>
    <title>Admin - Messages Dashboard</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root {
            --primary-green: #2e7d32;
            --light-green: #4caf50;
            --dark-green: #1b5e20;
            --accent-yellow: #ffc107;
            --light-yellow: #fff8e1;
            --pure-white: #ffffff;
            --off-white: #fafafa;
            --text-dark: #2e2e2e;
            --text-light: #666666;
            --border-light: #e0e0e0;
            --shadow-light: 0 2px 4px rgba(0,0,0,0.1);
            --shadow-medium: 0 4px 12px rgba(0,0,0,0.15);
            --border-radius: 8px;
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, var(--off-white) 0%, #f0f4f1 100%);
            color: var(--text-dark);
            line-height: 1.6;
        }

        .header {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--light-green) 100%);
            color: var(--pure-white);
            padding: 20px 0;
            box-shadow: var(--shadow-medium);
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 1.8rem;
            font-weight: 600;
        }

        .stats {
            display: flex;
            gap: 20px;
        }

        .stat-item {
            text-align: center;
            padding: 5px 15px;
            background: rgba(255,255,255,0.2);
            border-radius: var(--border-radius);
            backdrop-filter: blur(10px);
        }

        .stat-number {
            font-size: 1.2rem;
            font-weight: bold;
        }

        .stat-label {
            font-size: 0.85rem;
            opacity: 0.9;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .controls-section {
            background: var(--pure-white);
            padding: 25px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
            margin-bottom: 25px;
        }

        .controls-row {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            align-items: center;
            margin-bottom: 20px;
        }

        .search-container {
            flex: 1;
            min-width: 300px;
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 12px 45px 12px 15px;
            border: 2px solid var(--border-light);
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: var(--transition);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--light-green);
            box-shadow: 0 0 0 3px rgba(76,175,80,0.1);
        }

        .search-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
            font-size: 1.1rem;
        }

        .filter-group {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .filter-select {
            padding: 10px 15px;
            border: 2px solid var(--border-light);
            border-radius: var(--border-radius);
            background: var(--pure-white);
            font-size: 0.95rem;
            min-width: 120px;
            transition: var(--transition);
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--light-green);
        }

        .clear-filters {
            padding: 10px 20px;
            background: var(--accent-yellow);
            color: var(--text-dark);
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
        }

        .clear-filters:hover {
            background: #ffb300;
            transform: translateY(-1px);
        }

        .templates-section {
            background: var(--light-yellow);
            padding: 20px;
            border-radius: var(--border-radius);
            border-left: 4px solid var(--accent-yellow);
        }

        .templates-title {
            color: var(--text-dark);
            margin-bottom: 15px;
            font-size: 1.1rem;
            font-weight: 600;
        }

        .template-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,193,7,0.3);
        }

        .template-item:last-child {
            border-bottom: none;
        }

        .template-text {
            flex: 1;
            font-size: 0.95rem;
            color: var(--text-dark);
        }

        .copy-template-btn {
            padding: 6px 12px;
            background: var(--primary-green);
            color: var(--pure-white);
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
            transition: var(--transition);
        }

        .copy-template-btn:hover {
            background: var(--dark-green);
            transform: scale(1.05);
        }

        .messages-container {
            background: var(--pure-white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
            overflow: hidden;
        }

        .message-card {
            border-bottom: 1px solid var(--border-light);
            padding: 25px;
            transition: var(--transition);
            position: relative;
        }

        .message-card:hover {
            background: #f9f9f9;
        }

        .message-card:last-child {
            border-bottom: none;
        }

        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .message-info {
            flex: 1;
        }

        .sender-info {
            display: flex;
            gap: 20px;
            margin-bottom: 10px;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-label {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.9rem;
        }

        .info-value {
            color: var(--text-light);
            font-size: 0.9rem;
        }

        .copy-btn {
            padding: 4px 8px;
            background: var(--light-green);
            color: var(--pure-white);
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.75rem;
            transition: var(--transition);
        }

        .copy-btn:hover {
            background: var(--primary-green);
            transform: scale(1.1);
        }

        .message-content {
            background: #f8f9fa;
            padding: 15px;
            border-radius: var(--border-radius);
            margin: 15px 0;
            border-left: 3px solid var(--light-green);
        }

        .message-text {
            color: var(--text-dark);
            line-height: 1.6;
            white-space: pre-wrap;
        }

        .message-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
        }

        .date-time {
            color: var(--text-light);
            font-size: 0.85rem;
        }

        .message-actions {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .status-dropdown {
            padding: 8px 15px;
            border: 2px solid var(--border-light);
            border-radius: var(--border-radius);
            background: var(--pure-white);
            font-size: 0.9rem;
            font-weight: 500;
            transition: var(--transition);
        }

        .status-dropdown:focus {
            outline: none;
            border-color: var(--light-green);
        }

        .status-pending { background: #fff3cd; color: #856404; border-color: #ffeaa7; }
        .status-replied { background: #d1ecf1; color: #0c5460; border-color: #bee5eb; }
        .status-resolved { background: #d4edda; color: #155724; border-color: #c3e6cb; }
        .status-ignored { background: #f8d7da; color: #721c24; border-color: #f5c6cb; }

        .reply-btn {
            padding: 10px 15px;
            background: var(--primary-green);
            color: var(--pure-white);
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .reply-btn:hover {
            background: var(--dark-green);
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: var(--pure-white);
            margin: 5% auto;
            padding: 0;
            width: 90%;
            max-width: 600px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-medium);
            position: relative;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            background: var(--primary-green);
            color: var(--pure-white);
            padding: 20px 25px;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 1.2rem;
        }

        .close {
            background: none;
            border: none;
            color: var(--pure-white);
            font-size: 24px;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: var(--transition);
        }

        .close:hover {
            background: rgba(255,255,255,0.2);
        }

        .modal-body {
            padding: 25px;
        }

        .templates-modal {
            margin-bottom: 20px;
            padding: 15px;
            background: var(--light-yellow);
            border-radius: var(--border-radius);
            border: 1px solid var(--accent-yellow);
        }

        .templates-modal h4 {
            margin-bottom: 15px;
            color: var(--text-dark);
            font-size: 1rem;
        }

        .template-option {
            display: block;
            width: 100%;
            padding: 10px 15px;
            margin-bottom: 8px;
            background: var(--pure-white);
            border: 1px solid var(--border-light);
            border-radius: var(--border-radius);
            cursor: pointer;
            text-align: left;
            font-size: 0.9rem;
            transition: var(--transition);
        }

        .template-option:hover {
            background: var(--off-white);
            border-color: var(--light-green);
        }

        .reply-textarea {
            width: 100%;
            min-height: 120px;
            padding: 15px;
            border: 2px solid var(--border-light);
            border-radius: var(--border-radius);
            font-family: inherit;
            font-size: 1rem;
            resize: vertical;
            transition: var(--transition);
        }

        .reply-textarea:focus {
            outline: none;
            border-color: var(--light-green);
            box-shadow: 0 0 0 3px rgba(76,175,80,0.1);
        }

        .modal-footer {
            padding: 20px 25px;
            border-top: 1px solid var(--border-light);
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 600;
            transition: var(--transition);
        }

        .btn-primary {
            background: var(--primary-green);
            color: var(--pure-white);
        }

        .btn-primary:hover {
            background: var(--dark-green);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: var(--border-light);
            color: var(--text-dark);
        }

        .btn-secondary:hover {
            background: #d0d0d0;
        }

        .no-results {
            text-align: center;
            padding: 40px;
            color: var(--text-light);
        }

        .no-results-icon {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .controls-row {
                flex-direction: column;
                align-items: stretch;
            }

            .search-container {
                min-width: unset;
            }

            .filter-group {
                flex-wrap: wrap;
            }

            .sender-info {
                flex-direction: column;
                gap: 10px;
            }

            .message-actions {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
            }

            .modal-content {
                margin: 10% auto;
                width: 95%;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>📧 Messages Dashboard</h1>
            <div class="stats">
                <div class="stat-item">
                    <div class="stat-number" id="totalMessages">0</div>
                    <div class="stat-label">Total</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" id="pendingMessages">0</div>
                    <div class="stat-label">Pending</div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="controls-section">
            <div class="controls-row">
                <div class="search-container">
                    <input type="text" id="searchInput" class="search-input" placeholder="Search messages, names, emails...">
                    <span class="search-icon">🔍</span>
                </div>
                <div class="filter-group">
                    <select id="statusFilter" class="filter-select">
                        <option value="">All Status</option>
                        <option value="Pending">Pending</option>
                        <option value="Replied">Replied</option>
                        <option value="Resolved">Resolved</option>
                        <option value="Ignored">Ignored</option>
                    </select>
                    <select id="dateFilter" class="filter-select">
                        <option value="">All Dates</option>
                        <option value="today">Today</option>
                        <option value="week">This Week</option>
                        <option value="month">This Month</option>
                    </select>
                    <button class="clear-filters" onclick="clearFilters()">Clear Filters</button>
                </div>
            </div>

            <div class="templates-section">
                <div class="templates-title">📝 Quick Templates</div>
                <div class="template-item">
                    <div class="template-text">"Thank you for contacting us. We've received your message and will get back to you shortly."</div>
                    <button class="copy-template-btn" onclick="copyText('Thank you for contacting us. We\\'ve received your message and will get back to you shortly.')">Copy</button>
                </div>
                <div class="template-item">
                    <div class="template-text">"We appreciate your feedback. Your concern has been noted and forwarded to our team."</div>
                    <button class="copy-template-btn" onclick="copyText('We appreciate your feedback. Your concern has been noted and forwarded to our team.')">Copy</button>
                </div>
                <div class="template-item">
                    <div class="template-text">"Thank you for your inquiry. We'll review your request and respond within 24 hours."</div>
                    <button class="copy-template-btn" onclick="copyText('Thank you for your inquiry. We\\'ll review your request and respond within 24 hours.')">Copy</button>
                </div>
            </div>
        </div>

        <div class="messages-container" id="messagesContainer">
            <!-- Sample Messages for Demo -->
            <div class="message-card" data-status="Pending">
                <div class="message-header">
                    <div class="message-info">
                        <div class="sender-info">
                            <div class="info-item">
                                <span class="info-label">👤 Name:</span>
                                <span class="info-value">John Smith</span>
                                <button class="copy-btn" onclick="copyText('John Smith')">Copy</button>
                            </div>
                            <div class="info-item">
                                <span class="info-label">📧 Email:</span>
                                <span class="info-value">john.smith@email.com</span>
                                <button class="copy-btn" onclick="copyText('john.smith@email.com')">Copy</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="message-content">
                    <div class="message-text">Hello, I'm interested in your e-waste recycling services. Could you please provide more information about your pickup service and pricing?</div>
                </div>
                <div class="message-meta">
                    <div class="date-time">📅 May 23, 2025 • 🕐 2:30 PM</div>
                    <div class="message-actions">
                        <select class="status-dropdown status-pending" onchange="updateStatus(this, 1)">
                            <option value="Pending" selected>Pending</option>
                            <option value="Replied">Replied</option>
                            <option value="Resolved">Resolved</option>
                            <option value="Ignored">Ignored</option>
                        </select>
                        <button class="reply-btn" onclick="openModal('john.smith@email.com', 1)">
                            ✉️ Reply
                        </button>
                    </div>
                </div>
            </div>

            <div class="message-card" data-status="Replied">
                <div class="message-header">
                    <div class="message-info">
                        <div class="sender-info">
                            <div class="info-item">
                                <span class="info-label">👤 Name:</span>
                                <span class="info-value">Sarah Johnson</span>
                                <button class="copy-btn" onclick="copyText('Sarah Johnson')">Copy</button>
                            </div>
                            <div class="info-item">
                                <span class="info-label">📧 Email:</span>
                                <span class="info-value">sarah.j@company.com</span>
                                <button class="copy-btn" onclick="copyText('sarah.j@company.com')">Copy</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="message-content">
                    <div class="message-text">We have a large quantity of old computers and monitors that need proper disposal. What's the process for bulk collection?</div>
                </div>
                <div class="message-meta">
                    <div class="date-time">📅 May 22, 2025 • 🕐 10:15 AM</div>
                    <div class="message-actions">
                        <select class="status-dropdown status-replied" onchange="updateStatus(this, 2)">
                            <option value="Pending">Pending</option>
                            <option value="Replied" selected>Replied</option>
                            <option value="Resolved">Resolved</option>
                            <option value="Ignored">Ignored</option>
                        </select>
                        <button class="reply-btn" onclick="openModal('sarah.j@company.com', 2)">
                            ✉️ Reply
                        </button>
                    </div>
                </div>
            </div>

            <div class="message-card" data-status="Resolved">
                <div class="message-header">
                    <div class="message-info">
                        <div class="sender-info">
                            <div class="info-item">
                                <span class="info-label">👤 Name:</span>
                                <span class="info-value">Mike Chen</span>
                                <button class="copy-btn" onclick="copyText('Mike Chen')">Copy</button>
                            </div>
                            <div class="info-item">
                                <span class="info-label">📧 Email:</span>
                                <span class="info-value">mike.chen@email.com</span>
                                <button class="copy-btn" onclick="copyText('mike.chen@email.com')">Copy</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="message-content">
                    <div class="message-text">Great service! The pickup was on time and the team was very professional. Thank you for helping us dispose of our e-waste responsibly.</div>
                </div>
                <div class="message-meta">
                    <div class="date-time">📅 May 21, 2025 • 🕐 4:45 PM</div>
                    <div class="message-actions">
                        <select class="status-dropdown status-resolved" onchange="updateStatus(this, 3)">
                            <option value="Pending">Pending</option>
                            <option value="Replied">Replied</option>
                            <option value="Resolved" selected>Resolved</option>
                            <option value="Ignored">Ignored</option>
                        </select>
                        <button class="reply-btn" onclick="openModal('mike.chen@email.com', 3)">
                            ✉️ Reply
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Reply Modal -->
    <div id="replyModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>📧 Send Reply</h3>
                <button class="close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="templates-modal">
                    <h4>🚀 Quick Templates</h4>
                    <button class="template-option" onclick="insertTemplate('Thank you for contacting us. We\\'ve received your message and will get back to you shortly.')">
                        Thank you message
                    </button>
                    <button class="template-option" onclick="insertTemplate('We appreciate your feedback. Your concern has been noted and forwarded to our team.')">
                        Feedback acknowledgment
                    </button>
                    <button class="template-option" onclick="insertTemplate('Thank you for your inquiry. We\\'ll review your request and respond within 24 hours.')">
                        Inquiry response
                    </button>
                    <button class="template-option" onclick="insertTemplate('We apologize for the inconvenience. Our team is working to resolve this issue promptly.')">
                        Apology template
                    </button>
                </div>
                <form method="POST" action="send_reply.php">
                    <input type="hidden" id="reply_email" name="to_email">
                    <input type="hidden" id="reply_id" name="message_id">
                    <textarea id="replyTextarea" name="reply_message" class="reply-textarea" required placeholder="Write your reply here..."></textarea>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" onclick="sendReply()">Send Reply</button>
            </div>
        </div>
    </div>

    <script>
        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            updateStats();
            setupEventListeners();
        });

        function setupEventListeners() {
            // Search functionality
            document.getElementById('searchInput').addEventListener('input', filterMessages);
            document.getElementById('statusFilter').addEventListener('change', filterMessages);
            document.getElementById('dateFilter').addEventListener('change', filterMessages);

            // Modal close on outside click
            window.onclick = function(event) {
                const modal = document.getElementById('replyModal');
                if (event.target === modal) {
                    closeModal();
                }
            };
        }

        function updateStats() {
            const messages = document.querySelectorAll('.message-card');
            const pendingMessages = document.querySelectorAll('.message-card[data-status="Pending"]');
            
            document.getElementById('totalMessages').textContent = messages.length;
            document.getElementById('pendingMessages').textContent = pendingMessages.length;
        }

        function filterMessages() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;
            const dateFilter = document.getElementById('dateFilter').value;
            const messages = document.querySelectorAll('.message-card');

            let visibleCount = 0;

            messages.forEach(message => {
                const text = message.textContent.toLowerCase();
                const status = message.getAttribute('data-status');
                
                // Search filter
                const matchesSearch = searchTerm === '' || text.includes(searchTerm);
                
                // Status filter
                const matchesStatus = statusFilter === '' || status === statusFilter;
                
                // Date filter (simplified - in real implementation, parse actual dates)
                const matchesDate = dateFilter === '' || true; // Placeholder
                
                if (matchesSearch && matchesStatus && matchesDate) {
                    message.style.display = 'block';
                    visibleCount++;
                } else {
                    message.style.display = 'none';
                }
            });

            // Show no results message
            const container = document.getElementById('messagesContainer');
            let noResults = container.querySelector('.no-results');
            
            if (visibleCount === 0) {
                if (!noResults) {
                    noResults = document.createElement('div');
                    noResults.className = 'no-results';
                    noResults.innerHTML = `
                        <div class="no-results-icon">🔍</div>
                        <h3>No messages found</h3>
                        <p>Try adjusting your search criteria or filters</p>
                    `;
                    container.appendChild(noResults);
                }
            } else if (noResults) {
                noResults.remove();
            }
        }

        function clearFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('statusFilter').value = '';
            document.getElementById('dateFilter').value = '';
            filterMessages();
        }

        function copyText(text) {
            navigator.clipboard.writeText(text).then(() => {
                // Show temporary notification
                const notification = document.createElement('div');
                notification.textContent = 'Copied!';
                notification.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: var(--primary-green);
                    color: white;
                    padding: 10px 20px;
                    border-radius: 8px;
                    z-index: 10000;
                    animation: slideIn 0.3s ease;
                `;
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    notification.remove();
                }, 2000);
            }).catch(() => {
                alert('Failed to copy to clipboard');
            });
        }

        function updateStatus(selectElement, messageId) {
            const newStatus = selectElement.value;
            const messageCard = selectElement.closest('.message-card');
            
            // Update data attribute
            messageCard.setAttribute('data-status', newStatus);
            
            // Update dropdown styling
            selectElement.className = `status-dropdown status-${newStatus.toLowerCase()}`;
            
            // Here you would normally send AJAX request to update database
            // fetch('update_status.php', {
            //     method: 'POST',
            //     headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            //     body: `message_id=${messageId}&status=${newStatus}`
            // });
            
            updateStats();
            
            // Show success notification
            showNotification(`Status updated to ${newStatus}`, 'success');
        }

        function openModal(email, messageId) {
            document.getElementById('reply_email').value = email;
            document.getElementById('reply_id').value = messageId;
            document.getElementById('replyTextarea').value = '';
            document.getElementById('replyModal').style.display = 'block';
            
            // Focus on textarea
            setTimeout(() => {
                document.getElementById('replyTextarea').focus();
            }, 100);
        }

        function closeModal() {
            document.getElementById('replyModal').style.display = 'none';
        }

        function insertTemplate(template) {
            const textarea = document.getElementById('replyTextarea');
            const currentValue = textarea.value;
            
            if (currentValue && !currentValue.endsWith('\n\n')) {
                textarea.value = currentValue + '\n\n' + template;
            } else {
                textarea.value = currentValue + template;
            }
            
            textarea.focus();
            textarea.setSelectionRange(textarea.value.length, textarea.value.length);
        }

        function sendReply() {
            const email = document.getElementById('reply_email').value;
            const messageId = document.getElementById('reply_id').value;
            const message = document.getElementById('replyTextarea').value;
            
            if (!message.trim()) {
                showNotification('Please enter a reply message', 'error');
                return;
            }
            
            // Here you would normally send the reply via AJAX
            // fetch('send_reply.php', {
            //     method: 'POST',
            //     headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            //     body: `to_email=${email}&message_id=${messageId}&reply_message=${encodeURIComponent(message)}`
            // }).then(response => {
            //     if (response.ok) {
            //         showNotification('Reply sent successfully!', 'success');
            //         closeModal();
            //         // Update status to "Replied"
            //         const statusDropdown = document.querySelector(`select[onchange*="${messageId}"]`);
            //         if (statusDropdown) {
            //             statusDropdown.value = 'Replied';
            //             updateStatus(statusDropdown, messageId);
            //         }
            //     }
            // });
            
            // For demo purposes
            showNotification('Reply sent successfully!', 'success');
            closeModal();
            
            // Update status to "Replied"
            const messageCards = document.querySelectorAll('.message-card');
            messageCards.forEach(card => {
                const dropdown = card.querySelector('select');
                if (dropdown && dropdown.getAttribute('onchange').includes(messageId)) {
                    dropdown.value = 'Replied';
                    updateStatus(dropdown, messageId);
                }
            });
        }

        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.textContent = message;
            
            const colors = {
                success: 'var(--light-green)',
                error: '#f44336',
                info: 'var(--primary-green)'
            };
            
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: ${colors[type]};
                color: white;
                padding: 15px 25px;
                border-radius: 8px;
                z-index: 10000;
                box-shadow: var(--shadow-medium);
                animation: slideInRight 0.3s ease;
                max-width: 300px;
                word-wrap: break-word;
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideOutRight 0.3s ease';
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 3000);
        }

        // Add CSS animations
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideInRight {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            
            @keyframes slideOutRight {
                from {
                    transform: translateX(0);
                    opacity: 1;
                }
                to {
                    transform: translateX(100%);
                    opacity: 0;
                }
            }
            
            .message-card {
                animation: fadeIn 0.3s ease;
            }
            
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(10px); }
                to { opacity: 1; transform: translateY(0); }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>