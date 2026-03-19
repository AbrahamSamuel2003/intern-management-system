<?php
// Start session and database connection
session_start();
require_once '../../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit();
}

// Get company name
$company_query = "SELECT company_name FROM company_details LIMIT 1";
$company_result = mysqli_query($conn, $company_query);
$company_data = mysqli_fetch_assoc($company_result);
$company_name = $company_data['company_name'] ?? 'Intern Management System';

// Get all domains for dropdown
$domains_query = "SELECT id, domain_name FROM domains ORDER BY domain_name";
$domains_result = mysqli_query($conn, $domains_query);

// Handle form submission
$error = '';
$success = '';
$form_data = [
    'full_name' => '',
    'username' => '',
    'email' => '',
    'phone' => '',
    'domain_id' => '',
    'join_date' => date('Y-m-d')
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $form_data = [
        'full_name' => mysqli_real_escape_string($conn, $_POST['full_name']),
        'username' => mysqli_real_escape_string($conn, $_POST['username']),
        'email' => mysqli_real_escape_string($conn, $_POST['email']),
        'phone' => mysqli_real_escape_string($conn, $_POST['phone']),
        'domain_id' => mysqli_real_escape_string($conn, $_POST['domain_id']),
        'join_date' => mysqli_real_escape_string($conn, $_POST['join_date'])
    ];
    
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    $confirm_password = mysqli_real_escape_string($conn, $_POST['confirm_password']);
    
    // Validation
    $errors = [];
    
    if (empty($form_data['full_name'])) $errors[] = 'Full name is required';
    if (empty($form_data['username'])) $errors[] = 'Username is required';
    if (empty($form_data['email'])) $errors[] = 'Email is required';
    if (empty($password)) $errors[] = 'Password is required';
    if ($password !== $confirm_password) $errors[] = 'Passwords do not match';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters';
    if (empty($form_data['domain_id'])) $errors[] = 'Please select a domain';
    
    // Check if username already exists
    if (empty($errors)) {
        $check_username = "SELECT id FROM users WHERE username = '{$form_data['username']}'";
        $check_result = mysqli_query($conn, $check_username);
        if (mysqli_num_rows($check_result) > 0) {
            $errors[] = 'Username already exists';
        }
    }
    
    // Check if email already exists
    if (empty($errors)) {
        $check_email = "SELECT id FROM users WHERE email = '{$form_data['email']}'";
        $check_result = mysqli_query($conn, $check_email);
        if (mysqli_num_rows($check_result) > 0) {
            $errors[] = 'Email already exists';
        }
    }
    
    if (!empty($errors)) {
        $error = implode('<br>', $errors);
    } else {
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert new team lead
        $insert_query = "INSERT INTO users (
            username, password, email, full_name, role, domain_id, 
            phone, join_date, is_active, created_at
        ) VALUES (
            '{$form_data['username']}', 
            '$hashed_password', 
            '{$form_data['email']}', 
            '{$form_data['full_name']}', 
            'team_lead', 
            '{$form_data['domain_id']}',
            '{$form_data['phone']}',
            '{$form_data['join_date']}',
            TRUE,
            NOW()
        )";
        
        if (mysqli_query($conn, $insert_query)) {
            $success = 'Team Lead created successfully!';
            // Reset form
            $form_data = [
                'full_name' => '',
                'username' => '',
                'email' => '',
                'phone' => '',
                'domain_id' => '',
                'join_date' => date('Y-m-d')
            ];
        } else {
            $error = 'Error creating team lead: ' . mysqli_error($conn);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $company_name; ?> - Add Team Lead</title>
    
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
            
            <a href="manage.php" class="nav-link active">
                <i class="fas fa-user-tie"></i>Team Leads
            </a>
            
            <a href="../intern/manage.php" class="nav-link">
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
                        <h4 class="mb-0">Add New Team Lead</h4>
                        <small class="text-muted">Create a new team lead account</small>
                    </div>
                    <div>
                        <a href="manage.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Team Leads
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

        <!-- Add Team Lead Form -->
        <div class="form-container">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-user-plus me-2"></i>Create Team Lead Account</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="" id="addTeamLeadForm">
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
                                               placeholder="Enter full name"
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
                                               placeholder="teamlead@company.com"
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
                                               value="<?php echo htmlspecialchars($form_data['phone']); ?>"
                                               placeholder="+91 9876543210">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Join Date</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-calendar"></i>
                                        </span>
                                        <input type="date" class="form-control" 
                                               name="join_date" 
                                               value="<?php echo htmlspecialchars($form_data['join_date']); ?>"
                                               required>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Account Information -->
                            <div class="col-md-6">
                                <h6 class="mb-3 border-bottom pb-2">Account Information</h6>
                                
                                <div class="mb-3">
                                    <label class="form-label">Username *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-at"></i>
                                        </span>
                                        <input type="text" class="form-control" 
                                               name="username" 
                                               value="<?php echo htmlspecialchars($form_data['username']); ?>"
                                               placeholder="Choose username"
                                               required>
                                    </div>
                                    <small class="text-muted">Used for login (e.g., john_web, sarah_ai)</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Password *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-lock"></i>
                                        </span>
                                        <input type="password" class="form-control" 
                                               name="password" 
                                               id="password"
                                               placeholder="Enter password"
                                               required
                                               minlength="6">
                                        <button type="button" class="btn password-toggle" id="togglePassword">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <small class="text-muted">Minimum 6 characters</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Confirm Password *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-lock"></i>
                                        </span>
                                        <input type="password" class="form-control" 
                                               name="confirm_password" 
                                               id="confirmPassword"
                                               placeholder="Confirm password"
                                               required>
                                        <button type="button" class="btn password-toggle" id="toggleConfirmPassword">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div id="passwordMatch" class="form-text"></div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Assign Domain *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-layer-group"></i>
                                        </span>
                                        <select class="form-select" name="domain_id" required>
                                            <option value="">Select Domain</option>
                                            <?php while($domain = mysqli_fetch_assoc($domains_result)): ?>
                                            <option value="<?php echo $domain['id']; ?>" 
                                                <?php echo ($form_data['domain_id'] == $domain['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($domain['domain_name']); ?>
                                            </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <small class="text-muted">Team Lead will manage interns in this domain</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Domain Information Card -->
                        <div class="card bg-light mt-4">
                            <div class="card-body">
                                <h6 class="mb-3"><i class="fas fa-info-circle me-2"></i>Team Lead Responsibilities</h6>
                                <div class="row">
                                    <div class="col-md-4 mb-2">
                                        <small class="text-muted">• Assign tasks to interns</small>
                                    </div>
                                    <div class="col-md-4 mb-2">
                                        <small class="text-muted">• Review submitted tasks</small>
                                    </div>
                                    <div class="col-md-4 mb-2">
                                        <small class="text-muted">• Mark tasks as completed/not completed</small>
                                    </div>
                                    <div class="col-md-4 mb-2">
                                        <small class="text-muted">• Message interns</small>
                                    </div>
                                    <div class="col-md-4 mb-2">
                                        <small class="text-muted">• Approve leave requests</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Form Actions -->
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                            <a href="manage.php" class="btn btn-outline-secondary me-md-2">
                                <i class="fas fa-times me-2"></i>Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Create Team Lead Account
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Credentials Card -->
           
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
        
        // Form validation
        document.getElementById('addTeamLeadForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const domain = document.querySelector('select[name="domain_id"]').value;
            
            if (password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long');
                return false;
            }
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match');
                return false;
            }
            
            if (!domain) {
                e.preventDefault();
                alert('Please select a domain for the team lead');
                return false;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creating...';
            submitBtn.disabled = true;
        });
        
        // Auto-dismiss alerts
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