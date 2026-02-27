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

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit();
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: manage.php');
    exit();
}

$user_id = intval($_GET['id']);

// Get company name
$company_query = "SELECT company_name FROM company_details LIMIT 1";
$company_result = mysqli_query($conn, $company_query);
$company_data = mysqli_fetch_assoc($company_result);
$company_name = $company_data['company_name'] ?? 'Intern Management System';

// Get all domains for dropdown
$domains_query = "SELECT id, domain_name FROM domains ORDER BY domain_name";
$domains_result = mysqli_query($conn, $domains_query);

// Fetch existing intern details
$query = "SELECT * FROM users WHERE id = $user_id AND role = 'intern'";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) === 0) {
    header('Location: manage.php');
    exit();
}

$user = mysqli_fetch_assoc($result);
$form_data = $user;

// Handle form submission
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $domain_id = mysqli_real_escape_string($conn, $_POST['domain_id']);
    $join_date = mysqli_real_escape_string($conn, $_POST['join_date']);
    $end_date = mysqli_real_escape_string($conn, $_POST['end_date']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    $confirm_password = mysqli_real_escape_string($conn, $_POST['confirm_password']);
    
    // Validation
    $errors = [];
    
    if (empty($full_name)) $errors[] = 'Full name is required';
    if (empty($email)) $errors[] = 'Email is required';
    if (empty($domain_id)) $errors[] = 'Please select a domain';
    if (empty($join_date)) $errors[] = 'Join date is required';
    if (empty($end_date)) $errors[] = 'End date is required';
    
    // Check dates
    if (strtotime($end_date) < strtotime($join_date)) {
        $errors[] = 'End date must be after join date';
    }
    
    // Check if email already exists (excluding current user)
    if (empty($errors)) {
        $check_email = "SELECT id FROM users WHERE email = '$email' AND id != $user_id";
        $check_result = mysqli_query($conn, $check_email);
        if (mysqli_num_rows($check_result) > 0) {
            $errors[] = 'Email already exists';
        }
    }
    
    // Password validation if provided
    if (!empty($password)) {
        if ($password !== $confirm_password) $errors[] = 'Passwords do not match';
        if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters';
    }
    
    if (!empty($errors)) {
        $error = implode('<br>', $errors);
    } else {
        // Build update query
        $update_query = "UPDATE users SET 
                         full_name = '$full_name',
                         email = '$email',
                         phone = '$phone',
                         domain_id = '$domain_id',
                         join_date = '$join_date',
                         end_date = '$end_date',
                         is_active = $is_active";
        
        // Add password update if provided
        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $update_query .= ", password = '$hashed_password'";
        }
        
        $update_query .= " WHERE id = $user_id";
        
        if (mysqli_query($conn, $update_query)) {
            $success = 'Intern updated successfully!';
            // Refresh data
            $result = mysqli_query($conn, $query);
            $user = mysqli_fetch_assoc($result);
            $form_data = $user;
        } else {
            $error = 'Error updating intern: ' . mysqli_error($conn);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $company_name; ?> - Edit Intern</title>
    
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
            background: linear-gradient(180deg, #4e73df 10%, #224abe 100%);
            color: white;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
        }
        
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
            color: #4e73df;
            font-weight: bold;
            margin-right: 10px;
        }
        
        /* Top Navigation */
        .navbar-top {
            background: white;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 10px;
        }
        
        /* Form Styles */
        .form-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .form-label {
            font-weight: 600;
            color: #333;
        }
        
        .form-control, .form-select {
            border: 2px solid #eef2f7;
            border-radius: 10px;
            padding: 12px 15px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #4e73df;
            box-shadow: 0 0 0 0.25rem rgba(78, 115, 223, 0.25);
        }
        
        .input-group-text {
            background-color: #f8f9fa;
            border: 2px solid #eef2f7;
        }
        
        /* Card Styles */
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 20px;
        }
        
        /* Password Toggle */
        .password-toggle {
            cursor: pointer;
            background: none;
            border: none;
            color: #6c757d;
        }
        
        /* Duration Calculator */
        .duration-display {
            background-color: #e7f5ff;
            border-radius: 8px;
            padding: 10px 15px;
            margin-top: 10px;
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
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <a href="../dashboard.php" class="company-logo">
                <i class="fas fa-graduation-cap me-2"></i><?php echo $company_name; ?>
            </a>
            <small class="text-white-50 d-block mt-2">Admin Panel</small>
        </div>
        
        <div class="sidebar-menu">
            <a href="../dashboard.php" class="nav-link">
                <i class="fas fa-tachometer-alt"></i>Dashboard
            </a>
            
            <a href="../domains/manage.php" class="nav-link">
                <i class="fas fa-layer-group"></i>Domains
            </a>
            
            <a href="../teamlead/manage.php" class="nav-link">
                <i class="fas fa-user-tie"></i>Team Leads
            </a>
            
            <a href="manage.php" class="nav-link active">
                <i class="fas fa-user-graduate"></i>Interns
            </a>
            
            <a href="../performance/overview.php" class="nav-link">
                <i class="fas fa-chart-line"></i>Performance
            </a>
            
            <a href="../settings/company.php" class="nav-link">
                <i class="fas fa-cog"></i>Settings
            </a>
        </div>
        
        <div class="user-profile">
            <div class="d-flex align-items-center">
                <div class="profile-img">
                    <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                </div>
                <div>
                    <h6 class="mb-0"><?php echo $_SESSION['full_name']; ?></h6>
                    <small class="text-white-50">Administrator</small>
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
                        <h4 class="mb-0">Edit Intern</h4>
                        <small class="text-muted">Update intern details and duration</small>
                    </div>
                    <div>
                        <a href="manage.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Interns
                        </a>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Messages -->
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Edit Intern Form -->
        <div class="form-container">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Update Intern Information</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="" id="editInternForm">
                        <div class="row">
                            <!-- Personal Information -->
                            <div class="col-md-6">
                                <h6 class="mb-3 border-bottom pb-2">Personal Information</h6>
                                
                                <div class="mb-3">
                                    <label class="form-label">Full Name *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-user"></i>
                                        </span>
                                        <input type="text" class="form-control" 
                                               name="full_name" 
                                               value="<?php echo htmlspecialchars($form_data['full_name']); ?>"
                                               required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Email Address *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-envelope"></i>
                                        </span>
                                        <input type="email" class="form-control" 
                                               name="email" 
                                               value="<?php echo htmlspecialchars($form_data['email']); ?>"
                                               required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Phone Number</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-phone"></i>
                                        </span>
                                        <input type="tel" class="form-control" 
                                               name="phone" 
                                               value="<?php echo htmlspecialchars($form_data['phone']); ?>">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Account Status</label>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="statusSwitch" name="is_active" <?php echo ($form_data['is_active']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="statusSwitch">
                                            <?php echo ($form_data['is_active']) ? 'Active Account' : 'Inactive Account'; ?>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Account Information -->
                            <div class="col-md-6">
                                <h6 class="mb-3 border-bottom pb-2">Account Information</h6>
                                
                                <div class="mb-3">
                                    <label class="form-label">Username</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-at"></i>
                                        </span>
                                        <input type="text" class="form-control" 
                                               value="<?php echo htmlspecialchars($form_data['username']); ?>"
                                               disabled>
                                    </div>
                                    <small class="text-muted">Username cannot be changed</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">New Password (Optional)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-lock"></i>
                                        </span>
                                        <input type="password" class="form-control" 
                                               name="password" 
                                               id="password"
                                               placeholder="Leave blank to keep current"
                                               minlength="6">
                                        <button type="button" class="btn password-toggle" id="togglePassword">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Confirm New Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-lock"></i>
                                        </span>
                                        <input type="password" class="form-control" 
                                               name="confirm_password" 
                                               id="confirmPassword"
                                               placeholder="Confirm new password">
                                        <button type="button" class="btn password-toggle" id="toggleConfirmPassword">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div id="passwordMatch" class="form-text"></div>
                                </div>
                            </div>
                            
                            <!-- Internship Details -->
                            <div class="col-md-6">
                                <h6 class="mb-3 border-bottom pb-2">Internship Details</h6>
                                
                                <div class="mb-3">
                                    <label class="form-label">Assign Domain *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-layer-group"></i>
                                        </span>
                                        <select class="form-select" name="domain_id" id="domainSelect" required>
                                            <option value="">Select Domain</option>
                                            <?php 
                                            // Reset pointer
                                            mysqli_data_seek($domains_result, 0);
                                            while($domain = mysqli_fetch_assoc($domains_result)): 
                                            ?>
                                            <option value="<?php echo $domain['id']; ?>" 
                                                <?php echo ($form_data['domain_id'] == $domain['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($domain['domain_name']); ?>
                                            </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div id="teamLeadInfo" class="alert alert-info d-none">
                                    <small>
                                        <i class="fas fa-user-tie me-2"></i>
                                        <span id="teamLeadName">No team lead assigned to this domain</span>
                                    </small>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <h6 class="mb-3 border-bottom pb-2">Internship Duration</h6>
                                
                                <div class="mb-3">
                                    <label class="form-label">Join Date *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-calendar-plus"></i>
                                        </span>
                                        <input type="date" class="form-control" 
                                               name="join_date" 
                                               id="joinDate"
                                               value="<?php echo htmlspecialchars($form_data['join_date']); ?>"
                                               required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">End Date *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-calendar-check"></i>
                                        </span>
                                        <input type="date" class="form-control" 
                                               name="end_date" 
                                               id="endDate"
                                               value="<?php echo htmlspecialchars($form_data['end_date']); ?>"
                                               required>
                                    </div>
                                </div>
                                
                                <div class="duration-display">
                                    <small class="text-muted">Internship Duration:</small>
                                    <div id="durationText" class="fw-bold">Calculating...</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                            <a href="manage.php" class="btn btn-outline-secondary me-md-2">
                                <i class="fas fa-times me-2"></i>Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Update Intern
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
        
        document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('confirmPassword');
            const icon = this.querySelector('i');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
        
        // Check password match
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirmPassword');
        const passwordMatch = document.getElementById('passwordMatch');
        
        function checkPasswordMatch() {
            if (password.value === '' || confirmPassword.value === '') {
                passwordMatch.textContent = '';
                passwordMatch.className = 'form-text';
            } else if (password.value === confirmPassword.value) {
                passwordMatch.textContent = '✓ Passwords match';
                passwordMatch.className = 'form-text text-success';
            } else {
                passwordMatch.textContent = '✗ Passwords do not match';
                passwordMatch.className = 'form-text text-danger';
            }
        }
        
        password.addEventListener('input', checkPasswordMatch);
        confirmPassword.addEventListener('input', checkPasswordMatch);
        
        // Calculate internship duration
        function calculateDuration() {
            const joinDate = document.getElementById('joinDate').value;
            const endDate = document.getElementById('endDate').value;
            const durationText = document.getElementById('durationText');
            
            if (joinDate && endDate) {
                const join = new Date(joinDate);
                const end = new Date(endDate);
                
                if (end < join) {
                    durationText.innerHTML = '<span class="text-danger">End date must be after join date</span>';
                    return;
                }
                
                // Calculate difference in months
                const months = (end.getFullYear() - join.getFullYear()) * 12 + 
                              (end.getMonth() - join.getMonth());
                const days = Math.floor((end - join) / (1000 * 60 * 60 * 24));
                
                let duration = '';
                if (months > 0) {
                    duration = `${months} month${months > 1 ? 's' : ''}`;
                    if (days % 30 > 0) {
                        duration += `, ${days % 30} day${days % 30 > 1 ? 's' : ''}`;
                    }
                } else {
                    duration = `${days} day${days > 1 ? 's' : ''}`;
                }
                
                durationText.textContent = duration;
            }
        }
        
        document.getElementById('joinDate').addEventListener('change', calculateDuration);
        document.getElementById('endDate').addEventListener('change', calculateDuration);
        
        // Initialize duration calculation
        calculateDuration();
        
        // Update account status label
        const statusSwitch = document.getElementById('statusSwitch');
        const statusLabel = statusSwitch.nextElementSibling;
        
        statusSwitch.addEventListener('change', function() {
            if(this.checked) {
                statusLabel.textContent = 'Active Account';
            } else {
                statusLabel.textContent = 'Inactive Account';
            }
        });
        
        // Form validation
        document.getElementById('editInternForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const joinDate = document.getElementById('joinDate').value;
            const endDate = document.getElementById('endDate').value;
            
            if (password.length > 0 && password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long');
                return false;
            }
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match');
                return false;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Updating...';
            submitBtn.disabled = true;
        });
        
        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
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
                hamburger.className = 'btn btn-primary me-2';
                hamburger.innerHTML = '<i class="fas fa-bars"></i>';
                hamburger.onclick = toggleSidebar;
                
                navbar.querySelector('.d-flex').insertBefore(hamburger, navbar.querySelector('.d-flex').firstChild);
            }
        });
    </script>
</body>
</html>
