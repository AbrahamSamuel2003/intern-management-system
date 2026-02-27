<?php
// teamlead/messages.php

session_start();
require_once '../config/database.php';

// Check if user is logged in and is team lead
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'team_lead') {
    header('Location: ../login.php');
    exit();
}

$team_lead_id = $_SESSION['user_id'];
$domain_id = $_SESSION['domain_id'];

// Get team lead's domain
$domain_query = "SELECT domain_name FROM domains WHERE id = $domain_id";
$domain_result = mysqli_query($conn, $domain_query);
$domain_data = mysqli_fetch_assoc($domain_result);
$domain_name = $domain_data['domain_name'] ?? 'General';

// Get company name
$company_query = "SELECT company_name FROM company_details LIMIT 1";
$company_result = mysqli_query($conn, $company_query);
$company_data = mysqli_fetch_assoc($company_result);
$company_name = $company_data['company_name'] ?? 'Intern Management System';

// Get selected intern for conversation
$selected_intern = isset($_GET['intern_id']) ? intval($_GET['intern_id']) : 0;

// Get interns for dropdown
$interns_query = "SELECT id, full_name, email FROM users 
                 WHERE role = 'intern' AND domain_id = $domain_id 
                 ORDER BY full_name ASC";
$interns_result = mysqli_query($conn, $interns_query);

// Handle new message
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['message'])) {
    $receiver_id = intval($_POST['receiver_id']);
    $message = mysqli_real_escape_string($conn, $_POST['message']);
    
    $insert_query = "INSERT INTO messages (sender_id, receiver_id, message) 
                     VALUES ($team_lead_id, $receiver_id, '$message')";
    
    if (mysqli_query($conn, $insert_query)) {
        $selected_intern = $receiver_id;
        $_SESSION['success'] = "Message sent successfully!";
        header("Location: messages.php?intern_id=$receiver_id");
        exit();
    } else {
        $error = "Error: " . mysqli_error($conn);
    }
}

// Get messages for selected intern
$messages = [];
if ($selected_intern > 0) {
    $messages_query = "SELECT m.*, 
                              s.full_name as sender_name,
                              r.full_name as receiver_name
                       FROM messages m
                       LEFT JOIN users s ON m.sender_id = s.id
                       LEFT JOIN users r ON m.receiver_id = r.id
                       WHERE (m.sender_id = $team_lead_id AND m.receiver_id = $selected_intern)
                          OR (m.sender_id = $selected_intern AND m.receiver_id = $team_lead_id)
                       ORDER BY m.created_at ASC";
    $messages_result = mysqli_query($conn, $messages_query);
    
    // Mark messages as read
    $mark_read_query = "UPDATE messages SET read_status = TRUE 
                        WHERE receiver_id = $team_lead_id 
                        AND sender_id = $selected_intern";
    mysqli_query($conn, $mark_read_query);
}

// Get unread message count
    $unread_query = "SELECT COUNT(*) as unread_count FROM messages 
                 WHERE receiver_id = $team_lead_id AND read_status = FALSE";
$unread_result = mysqli_query($conn, $unread_query);
$unread_data = mysqli_fetch_assoc($unread_result);
$unread_count = $unread_data['unread_count'];

// Get recent conversations
$conversations_query = "
    SELECT 
        u.id,
        u.full_name,
        u.email,
        COUNT(CASE WHEN m.read_status = FALSE AND m.receiver_id = $team_lead_id THEN 1 END) as unread,
        MAX(m.created_at) as last_message_time
    FROM users u
    LEFT JOIN messages m ON (m.sender_id = u.id AND m.receiver_id = $team_lead_id) 
                         OR (m.sender_id = $team_lead_id AND m.receiver_id = u.id)
    WHERE u.role = 'intern' AND u.domain_id = $domain_id
    GROUP BY u.id
    ORDER BY last_message_time DESC
";
$conversations_result = mysqli_query($conn, $conversations_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $company_name; ?> - Messages</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fc; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .sidebar { position: fixed; top: 0; left: 0; bottom: 0; width: 250px; background: linear-gradient(180deg, #4e73df 10%, #224abe 100%); color: white; box-shadow: 0 0 15px rgba(0,0,0,0.1); z-index: 1000; }
        .main-content { margin-left: 250px; padding: 20px; min-height: 100vh; }
        .sidebar-header { padding: 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .company-logo { font-size: 20px; font-weight: 700; color: white; text-decoration: none; }
        .sidebar-menu { padding: 20px 0; }
        .nav-link { color: rgba(255,255,255,0.8); padding: 12px 20px; margin: 5px 10px; border-radius: 5px; transition: all 0.3s; text-decoration: none; display: block; }
        .nav-link:hover { color: white; background: rgba(255,255,255,0.1); }
        .nav-link.active { color: white; background: rgba(255,255,255,0.2); font-weight: 600; }
        .nav-link i { width: 20px; margin-right: 10px; text-align: center; }
        .user-profile { position: absolute; bottom: 0; left: 0; right: 0; padding: 20px; background: rgba(0,0,0,0.1); border-top: 1px solid rgba(255,255,255,0.1); }
        .profile-img { width: 40px; height: 40px; border-radius: 50%; background: white; display: flex; align-items: center; justify-content: center; color: #4e73df; font-weight: bold; margin-right: 10px; }
        .navbar-top { background: white; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15); padding: 15px 20px; margin-bottom: 20px; border-radius: 10px; }
        .card { border: none; border-radius: 10px; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15); margin-bottom: 20px; }
        .card-header { background: white; border-bottom: 1px solid #e3e6f0; padding: 15px 20px; border-radius: 10px 10px 0 0 !important; font-weight: 600; }
        .message-left { background-color: #e3f2fd; border-radius: 15px 15px 15px 0; padding: 10px 15px; margin: 5px 0; max-width: 70%; float: left; }
        .message-right { background-color: #007bff; color: white; border-radius: 15px 15px 0 15px; padding: 10px 15px; margin: 5px 0; max-width: 70%; float: right; }
        .message-time { font-size: 11px; color: #6c757d; }
        .conversation-item { padding: 10px 15px; border-bottom: 1px solid #e3e6f0; cursor: pointer; transition: background-color 0.3s; }
        .conversation-item:hover { background-color: #f8f9fa; }
        .conversation-item.active { background-color: #e3f2fd; }
        .unread-badge { background-color: #dc3545; color: white; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-size: 12px; }
        .message-container { height: 400px; overflow-y: auto; }
        @media (max-width: 768px) { .sidebar { width: 0; overflow: hidden; } .main-content { margin-left: 0; } }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="company-logo">
                <i class="fas fa-graduation-cap me-2"></i><?php echo $company_name; ?>
            </a>
            <small class="text-white-50 d-block mt-2">Team Lead Panel</small>
        </div>
        
        <div class="sidebar-menu">
            <a href="dashboard.php" class="nav-link">
                <i class="fas fa-tachometer-alt"></i>Dashboard
            </a>
            <a href="interns.php" class="nav-link">
                <i class="fas fa-user-graduate"></i>My Interns
            </a>
            <a href="tasks.php" class="nav-link">
                <i class="fas fa-tasks"></i>Tasks
            </a>
            <a href="assign_task.php" class="nav-link">
                <i class="fas fa-plus-circle"></i>Assign Task
            </a>
            <a href="messages.php" class="nav-link active">
                <i class="fas fa-envelope"></i>Messages
                <?php if ($unread_count > 0): ?>
                <span class="badge bg-danger float-end"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="reports.php" class="nav-link">
                <i class="fas fa-chart-bar"></i>Reports
            </a>
        </div>
        
        <div class="user-profile">
            <div class="d-flex align-items-center">
                <div class="profile-img">
                    <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                </div>
                <div>
                    <h6 class="mb-0"><?php echo $_SESSION['full_name']; ?></h6>
                    <small class="text-white-50">Team Lead</small>
                    <small class="text-white-50 d-block"><?php echo $domain_name; ?></small>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navigation Bar -->
        <nav class="navbar-top">
            <div class="container-fluid">
                <div class="d-flex justify-content-between align-items-center w-100">
                    <div>
                        <h4 class="mb-0">
                            <a href="dashboard.php" class="text-decoration-none text-dark">
                                <i class="fas fa-arrow-left me-2"></i>
                            </a>
                            Messages
                        </h4>
                        <small class="text-muted">Communicate with your interns</small>
                    </div>
                    <div>
                        <?php if ($unread_count > 0): ?>
                        <span class="badge bg-danger me-2">
                            <i class="fas fa-envelope me-1"></i><?php echo $unread_count; ?> unread
                        </span>
                        <?php endif; ?>
                        <a href="interns.php" class="btn btn-outline-primary">
                            <i class="fas fa-user-graduate me-2"></i>My Interns
                        </a>
                    </div>
                </div>
            </div>
        </nav>

        <div class="row">
            <!-- Conversations List -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-users me-2"></i>Conversations</h6>
                    </div>
                    <div class="card-body p-0">
                        <?php if (mysqli_num_rows($conversations_result) > 0): ?>
                            <div class="conversation-list">
                                <?php while($conv = mysqli_fetch_assoc($conversations_result)): ?>
                                    <a href="messages.php?intern_id=<?php echo $conv['id']; ?>" 
                                       class="d-block text-decoration-none text-dark">
                                        <div class="conversation-item <?php echo $selected_intern == $conv['id'] ? 'active' : ''; ?>">
                                            <div class="d-flex align-items-center">
                                                <div class="me-3">
                                                    <div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" 
                                                         style="width: 40px; height: 40px;">
                                                        <i class="fas fa-user text-primary"></i>
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($conv['full_name']); ?></h6>
                                                    <small class="text-muted"><?php echo htmlspecialchars($conv['email']); ?></small>
                                                </div>
                                                <?php if ($conv['unread'] > 0): ?>
                                                    <div class="unread-badge">
                                                        <?php echo $conv['unread']; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </a>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No conversations yet</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Chat Area -->
            <div class="col-lg-8">
                <?php if ($selected_intern > 0): ?>
                    <?php 
                    // Get selected intern info
                    $intern_info_query = "SELECT full_name, email FROM users WHERE id = $selected_intern";
                    $intern_info_result = mysqli_query($conn, $intern_info_query);
                    $intern_info = mysqli_fetch_assoc($intern_info_result);
                    ?>
                    
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">
                                    <i class="fas fa-comment-dots me-2"></i>
                                    Conversation with <?php echo htmlspecialchars($intern_info['full_name']); ?>
                                </h6>
                                <small class="text-muted"><?php echo htmlspecialchars($intern_info['email']); ?></small>
                            </div>
                            <div>
                                <a href="view_intern.php?id=<?php echo $selected_intern; ?>" 
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye me-1"></i>View Profile
                                </a>
                            </div>
                        </div>
                        
                        <div class="card-body">
                            <?php if (isset($error)): ?>
                                <div class="alert alert-danger"><?php echo $error; ?></div>
                            <?php endif; ?>
                            
                            <?php if (isset($_SESSION['success'])): ?>
                                <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
                            <?php endif; ?>
                            
                            <!-- Messages Container -->
                            <div class="message-container mb-3" id="messageContainer">
                                <?php if (isset($messages_result) && mysqli_num_rows($messages_result) > 0): ?>
                                    <?php while($message = mysqli_fetch_assoc($messages_result)): ?>
                                        <?php if ($message['sender_id'] == $team_lead_id): ?>
                                            <!-- Sent message (right) -->
                                            <div class="message-right">
                                                <div><?php echo htmlspecialchars($message['message']); ?></div>
                                                <div class="message-time text-white-50">
                                                    <?php echo date('h:i A', strtotime($message['created_at'])); ?>
                                                    <?php if ($message['read_status']): ?>
                                                        <i class="fas fa-check-double ms-1"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-check ms-1"></i>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="clearfix"></div>
                                        <?php else: ?>
                                            <!-- Received message (left) -->
                                            <div class="message-left">
                                                <div><?php echo htmlspecialchars($message['message']); ?></div>
                                                <div class="message-time">
                                                    <?php echo date('h:i A', strtotime($message['created_at'])); ?>
                                                </div>
                                            </div>
                                            <div class="clearfix"></div>
                                        <?php endif; ?>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-comment-slash fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">No messages yet. Start the conversation!</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Message Form -->
                            <form method="POST" action="">
                                <input type="hidden" name="receiver_id" value="<?php echo $selected_intern; ?>">
                                <div class="input-group">
                                    <textarea class="form-control" name="message" rows="2" 
                                              placeholder="Type your message here..." required></textarea>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-comments fa-4x text-muted mb-3"></i>
                            <h5>Select a Conversation</h5>
                            <p class="text-muted mb-4">Choose an intern from the list to start messaging</p>
                            <a href="interns.php" class="btn btn-primary">
                                <i class="fas fa-user-graduate me-2"></i>View All Interns
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-scroll to bottom of messages
        function scrollToBottom() {
            const container = document.getElementById('messageContainer');
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        }
        
        // Auto-refresh messages every 5 seconds
        function refreshMessages() {
            if (<?php echo $selected_intern; ?> > 0) {
                // You can implement AJAX refresh here if needed
                // For now, just scroll to bottom
                scrollToBottom();
            }
        }
        
        // Initial scroll
        window.addEventListener('DOMContentLoaded', scrollToBottom);
        
        // Set up auto-refresh
        setInterval(refreshMessages, 5000);
        
        // Mobile menu toggle
        if (window.innerWidth <= 768) {
            const navbar = document.querySelector('.navbar-top');
            const hamburger = document.createElement('button');
            hamburger.className = 'btn btn-primary me-2';
            hamburger.innerHTML = '<i class="fas fa-bars"></i>';
            hamburger.onclick = toggleSidebar;
            
            navbar.querySelector('.d-flex').insertBefore(hamburger, navbar.querySelector('.d-flex').firstChild);
        }
        
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            
            if (window.innerWidth <= 768) {
                if (sidebar.style.width === '250px') {
                    sidebar.style.width = '0';
                    mainContent.style.marginLeft = '0';
                } else {
                    sidebar.style.width = '250px';
                    mainContent.style.marginLeft = '250px';
                }
            }
        }
        
        // Form validation
        document.querySelector('form')?.addEventListener('submit', function(e) {
            const message = this.querySelector('textarea[name="message"]').value.trim();
            if (!message) {
                e.preventDefault();
                alert('Please enter a message!');
                return false;
            }
            return true;
        });
    </script>
</body>
</html>
<?php mysqli_close($conn); ?>