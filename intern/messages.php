<?php
// Start session and database connection
session_start();

// Database configuration
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'imsjr';

// Create connection
$conn = mysqli_connect($host, $username, $password, $database);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Check if user is logged in and is intern
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'intern') {
    header('Location: ../login.php');
    exit();
}

$intern_id = $_SESSION['user_id'];

// Get intern's team lead
$team_lead_query = "
    SELECT tl.id, tl.full_name, tl.email, tl.username
    FROM users tl
    WHERE tl.role = 'team_lead' AND tl.domain_id = (
        SELECT domain_id FROM users WHERE id = $intern_id
    )
";
$team_lead_result = mysqli_query($conn, $team_lead_query);
$team_lead = mysqli_fetch_assoc($team_lead_result);

// Get company name
$company_query = "SELECT company_name FROM company_details LIMIT 1";
$company_result = mysqli_query($conn, $company_query);
$company_data = mysqli_fetch_assoc($company_result);
$company_name = $company_data['company_name'] ?? 'Intern Management System';

// Handle viewing a specific message
if (isset($_GET['view'])) {
    $message_id = intval($_GET['view']);
    
    // Mark message as read
    $mark_read_query = "
        UPDATE messages 
        SET read_status = TRUE 
        WHERE id = $message_id AND receiver_id = $intern_id
    ";
    mysqli_query($conn, $mark_read_query);
    
    // Get message details
    $message_query = "
        SELECT m.*, u.full_name as sender_name, u.role as sender_role
        FROM messages m
        LEFT JOIN users u ON m.sender_id = u.id
        WHERE m.id = $message_id AND (m.sender_id = $intern_id OR m.receiver_id = $intern_id)
    ";
    $message_result = mysqli_query($conn, $message_query);
    $message = mysqli_fetch_assoc($message_result);
}

// Handle sending a new message
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_message'])) {
    $receiver_id = intval($_POST['receiver_id']);
    $message_text = mysqli_real_escape_string($conn, $_POST['message']);
    
    if (!empty($message_text) && $receiver_id > 0) {
        $send_query = "
            INSERT INTO messages (sender_id, receiver_id, message, created_at)
            VALUES ($intern_id, $receiver_id, '$message_text', NOW())
        ";
        
        if (mysqli_query($conn, $send_query)) {
            $success = 'Message sent successfully!';
        } else {
            $error = 'Failed to send message. Please try again.';
        }
    } else {
        $error = 'Please enter a message.';
    }
}

// Get all messages (inbox and sent)
$inbox_query = "
    SELECT m.*, u.full_name as sender_name, u.role as sender_role
    FROM messages m
    LEFT JOIN users u ON m.sender_id = u.id
    WHERE m.receiver_id = $intern_id
    ORDER BY m.created_at DESC
";

$sent_query = "
    SELECT m.*, u.full_name as receiver_name, u.role as receiver_role
    FROM messages m
    LEFT JOIN users u ON m.receiver_id = u.id
    WHERE m.sender_id = $intern_id
    ORDER BY m.created_at DESC
";

$inbox_result = mysqli_query($conn, $inbox_query);
$sent_result = mysqli_query($conn, $sent_query);

// Count unread messages
$unread_query = "SELECT COUNT(*) as unread_count FROM messages WHERE receiver_id = $intern_id AND read_status = FALSE";
$unread_result = mysqli_query($conn, $unread_query);
$unread_data = mysqli_fetch_assoc($unread_result);
$unread_count = $unread_data['unread_count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $company_name; ?> - Messages</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        body {
            background-color: #f8f9fc;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: 250px;
            background: linear-gradient(180deg, #1a237e 10%, #283593 100%);
            color: white;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
        }
        
        /* Sidebar Header */
        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .company-logo {
            font-size: 20px;
            font-weight: 700;
            color: white;
            text-decoration: none;
        }
        
        /* Sidebar Menu */
        .sidebar-menu {
            padding: 20px 0;
        }
        
        .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            margin: 5px 10px;
            border-radius: 5px;
            transition: all 0.3s;
            text-decoration: none;
            display: block;
        }
        
        .nav-link:hover {
            color: white;
            background: rgba(255,255,255,0.1);
        }
        
        .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.2);
            font-weight: 600;
        }
        
        .nav-link i {
            width: 20px;
            margin-right: 10px;
            text-align: center;
        }
        
        /* User Profile in Sidebar */
        .user-profile {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 20px;
            background: rgba(0,0,0,0.1);
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        
        .profile-img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1a237e;
            font-weight: bold;
            margin-right: 10px;
        }
        
        /* Top Navigation Bar */
        .navbar-top {
            background: white;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 10px;
        }
        
        /* Message Styles */
        .message-list {
            max-height: 500px;
            overflow-y: auto;
        }
        
        .message-item {
            border-left: 3px solid transparent;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .message-item:hover {
            background-color: #f8f9fa;
        }
        
        .message-item.unread {
            border-left-color: #4e73df;
            background-color: #f0f3ff;
        }
        
        .message-item.selected {
            background-color: #e3e6f0;
        }
        
        .message-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        
        .avatar-teamlead {
            background: linear-gradient(45deg, #4e73df, #224abe);
        }
        
        .avatar-intern {
            background: linear-gradient(45deg, #1cc88a, #13855c);
        }
        
        .avatar-admin {
            background: linear-gradient(45deg, #f6c23e, #dda20a);
        }
        
        /* Chat Container */
        .chat-container {
            height: 400px;
            overflow-y: auto;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 10px;
        }
        
        .message-bubble {
            max-width: 70%;
            padding: 12px 16px;
            border-radius: 18px;
            margin-bottom: 10px;
            position: relative;
        }
        
        .message-sent {
            background-color: #4e73df;
            color: white;
            margin-left: auto;
            border-bottom-right-radius: 4px;
        }
        
        .message-received {
            background-color: white;
            color: #333;
            margin-right: auto;
            border-bottom-left-radius: 4px;
            border: 1px solid #e3e6f0;
        }
        
        .message-time {
            font-size: 12px;
            color: #6c757d;
            margin-top: 5px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 0;
                overflow: hidden;
            }
            
            .main-content {
                margin-left: 0;
            }
        }
        
        /* Compose Message */
        .compose-area {
            border: 1px solid #e3e6f0;
            border-radius: 10px;
            padding: 20px;
            background: white;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="company-logo">
                <i class="fas fa-graduation-cap me-2"></i><?php echo $company_name; ?>
            </a>
            <small class="text-white-50 d-block mt-2">Intern Portal</small>
        </div>
        
        <div class="sidebar-menu">
            <a href="dashboard.php" class="nav-link">
                <i class="fas fa-tachometer-alt"></i>Dashboard
            </a>
            
            <a href="my_tasks.php" class="nav-link">
                <i class="fas fa-tasks"></i>My Tasks
            </a>
            
            <a href="submit_task.php" class="nav-link">
                <i class="fas fa-paper-plane"></i>Submit Task
            </a>
            
            <a href="view_feedback.php" class="nav-link">
                <i class="fas fa-comment-dots"></i>Feedback
            </a>
            
            <a href="messages.php" class="nav-link active">
                <i class="fas fa-envelope"></i>Messages
                <?php if($unread_count > 0): ?>
                <span class="badge bg-danger float-end"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </a>
            
            <a href="profile.php" class="nav-link">
                <i class="fas fa-user"></i>Profile
            </a>
        </div>
        
        <div class="user-profile">
            <div class="d-flex align-items-center">
                <div class="profile-img">
                    <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                </div>
                <div>
                    <h6 class="mb-0"><?php echo $_SESSION['full_name']; ?></h6>
                    <small class="text-white-50">Intern</small>
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
                            <i class="fas fa-envelope me-2"></i>Messages
                            <?php if($unread_count > 0): ?>
                            <span class="badge bg-danger"><?php echo $unread_count; ?> unread</span>
                            <?php endif; ?>
                        </h4>
                        <small class="text-muted">Communicate with your team lead</small>
                    </div>
                    <div class="d-flex align-items-center">
                        <a href="dashboard.php" class="btn btn-outline-dark btn-sm">
                            <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </nav>

        <?php if(isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if(isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="row">
            <!-- Left Column: Message List -->
            <div class="col-lg-4 mb-4">
                <!-- Compose New Message -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-edit me-2"></i>New Message</h6>
                    </div>
                    <div class="card-body">
                        <?php if($team_lead): ?>
                        <form method="POST" action="">
                            <input type="hidden" name="receiver_id" value="<?php echo $team_lead['id']; ?>">
                            <div class="mb-3">
                                <label class="form-label">To</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($team_lead['full_name']); ?> (Team Lead)" readonly>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Message *</label>
                                <textarea class="form-control" name="message" rows="3" placeholder="Type your message here..." required></textarea>
                            </div>
                            <button type="submit" name="send_message" class="btn btn-primary w-100">
                                <i class="fas fa-paper-plane me-2"></i>Send Message
                            </button>
                        </form>
                        <?php else: ?>
                        <div class="text-center py-3">
                            <i class="fas fa-user-tie fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No team lead assigned to your domain yet.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Inbox -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="fas fa-inbox me-2"></i>Inbox</h6>
                        <span class="badge bg-primary"><?php echo mysqli_num_rows($inbox_result); ?></span>
                    </div>
                    <div class="card-body p-0">
                        <div class="message-list">
                            <?php if(mysqli_num_rows($inbox_result) > 0): 
                                mysqli_data_seek($inbox_result, 0); // Reset pointer
                                while($msg = mysqli_fetch_assoc($inbox_result)): 
                                    $is_selected = isset($message) && $message['id'] == $msg['id'];
                            ?>
                            <a href="?view=<?php echo $msg['id']; ?>" class="text-decoration-none text-dark">
                                <div class="message-item p-3 border-bottom <?php echo !$msg['read_status'] ? 'unread' : ''; ?> <?php echo $is_selected ? 'selected' : ''; ?>">
                                    <div class="d-flex">
                                        <div class="flex-shrink-0 me-3">
                                            <div class="message-avatar <?php 
                                                if($msg['sender_role'] == 'team_lead') echo 'avatar-teamlead';
                                                elseif($msg['sender_role'] == 'admin') echo 'avatar-admin';
                                                else echo 'avatar-intern';
                                            ?>">
                                                <?php echo strtoupper(substr($msg['sender_name'], 0, 1)); ?>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($msg['sender_name']); ?></h6>
                                                <small class="text-muted"><?php echo date('h:i A', strtotime($msg['created_at'])); ?></small>
                                            </div>
                                            <p class="mb-1 text-muted"><?php echo htmlspecialchars(substr($msg['message'], 0, 60)); ?><?php echo strlen($msg['message']) > 60 ? '...' : ''; ?></p>
                                            <?php if(!$msg['read_status']): ?>
                                            <small class="text-primary"><i class="fas fa-circle fa-xs"></i> New</small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </a>
                            <?php endwhile; ?>
                            <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No messages in inbox</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Message View -->
            <div class="col-lg-8 mb-4">
                <?php if(isset($message)): ?>
                <!-- View Message -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">
                                <i class="fas fa-eye me-2"></i>View Message
                            </h6>
                        </div>
                        <div>
                            <a href="messages.php" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-times me-1"></i>Close
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Message Header -->
                        <div class="d-flex align-items-center mb-4">
                            <div class="message-avatar me-3 <?php 
                                if($message['sender_role'] == 'team_lead') echo 'avatar-teamlead';
                                elseif($message['sender_role'] == 'admin') echo 'avatar-admin';
                                else echo 'avatar-intern';
                            ?>">
                                <?php echo strtoupper(substr($message['sender_name'], 0, 1)); ?>
                            </div>
                            <div>
                                <h5 class="mb-1"><?php echo htmlspecialchars($message['sender_name']); ?></h5>
                                <small class="text-muted">
                                    <i class="fas fa-clock me-1"></i>
                                    <?php echo date('F j, Y \a\t h:i A', strtotime($message['created_at'])); ?>
                                </small>
                            </div>
                        </div>
                        
                        <!-- Message Content -->
                        <div class="mb-4">
                            <div class="message-bubble <?php echo $message['sender_id'] == $intern_id ? 'message-sent' : 'message-received'; ?>">
                                <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                                <div class="message-time">
                                    <?php echo date('h:i A', strtotime($message['created_at'])); ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Reply Form -->
                        <?php if($message['sender_id'] != $intern_id): ?>
                        <div class="compose-area">
                            <form method="POST" action="">
                                <input type="hidden" name="receiver_id" value="<?php echo $message['sender_id']; ?>">
                                <div class="mb-3">
                                    <label class="form-label">Reply to <?php echo htmlspecialchars($message['sender_name']); ?></label>
                                    <textarea class="form-control" name="message" rows="3" placeholder="Type your reply..." required></textarea>
                                </div>
                                <button type="submit" name="send_message" class="btn btn-primary">
                                    <i class="fas fa-reply me-2"></i>Reply
                                </button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                <!-- Welcome Message -->
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-envelope-open-text fa-4x text-muted mb-4"></i>
                        <h4>Welcome to Messages</h4>
                        <p class="text-muted mb-4">
                            Select a message from your inbox to view it, or compose a new message to your team lead.
                        </p>
                        
                        <?php if($team_lead): ?>
                        <div class="row justify-content-center">
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6 class="mb-3">Your Team Lead</h6>
                                        <div class="d-flex align-items-center mb-3">
                                            <div class="message-avatar avatar-teamlead me-3">
                                                <?php echo strtoupper(substr($team_lead['full_name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($team_lead['full_name']); ?></h6>
                                                <small class="text-muted"><?php echo htmlspecialchars($team_lead['email']); ?></small>
                                            </div>
                                        </div>
                                        <p class="text-muted mb-0">
                                            <i class="fas fa-info-circle me-1"></i>
                                            You can only message your assigned team lead.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Mobile menu toggle
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
        
        // Add hamburger menu for mobile
        window.addEventListener('DOMContentLoaded', function() {
            if (window.innerWidth <= 768) {
                const navbar = document.querySelector('.navbar-top');
                const hamburger = document.createElement('button');
                hamburger.className = 'btn btn-dark me-2';
                hamburger.innerHTML = '<i class="fas fa-bars"></i>';
                hamburger.onclick = toggleSidebar;
                
                navbar.querySelector('.d-flex').insertBefore(hamburger, navbar.querySelector('.d-flex').firstChild);
            }
            
            // Auto-refresh messages every 30 seconds
            setTimeout(function() {
                window.location.reload();
            }, 30000);
        });
        
        // Message list scroll to selected
        document.addEventListener('DOMContentLoaded', function() {
            const selectedMessage = document.querySelector('.message-item.selected');
            if (selectedMessage) {
                selectedMessage.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    </script>
</body>
</html>