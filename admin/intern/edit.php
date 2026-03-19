<?php
// Start session and database connection
session_start();
require_once '../../config/database.php';

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
    <title>Edit Intern - <?php echo $company_name; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        body {
            background-color: #f8f9fc;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
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
            transition: all 0.3s;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
            transition: all 0.3s;
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
            margin-bottom: 25px;
            border-radius: 10px;
        }
        
        /* Form Container */
        .form-container {
            max-width: 900px;
            margin: 0 auto;
        }

        .section-card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 25px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 700;
            color: #4e73df;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            border-bottom: 2px solid #f8f9fc;
            padding-bottom: 10px;
        }

        .section-title i {
            margin-right: 10px;
        }

        .form-label {
            font-size: 13px;
            font-weight: 700;
            color: #5a5c69;
            margin-bottom: 8px;
            text-transform: uppercase;
        }

        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #d1d3e2;
            padding: 12px 15px;
            font-size: 14px;
            transition: all 0.2s;
        }

        .form-control:focus {
            border-color: #4e73df;
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.1);
        }

        .btn-primary {
            background-color: #4e73df;
            border-color: #4e73df;
            padding: 12px 30px;
            font-weight: 700;
            border-radius: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-primary:hover {
            background-color: #2e59d9;
            border-color: #2653d4;
        }

        .btn-outline-secondary {
            border: 2px solid #d1d3e2;
            color: #858796;
            font-weight: 600;
            border-radius: 8px;
            padding: 10px 20px;
        }

        .duration-display {
            background-color: #f8f9fc;
            border-radius: 8px;
            padding: 15px;
            border-left: 4px solid #4e73df;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar { width: 0; overflow: hidden; }
            .main-content { margin-left: 0; }
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
            
            <hr class="mx-3 opacity-25">
            <a href="../../logout.php" class="nav-link text-warning">
                <i class="fas fa-sign-out-alt"></i>Logout
            </a>
        </div>
        
        <div class="user-profile">
            <div class="d-flex align-items-center">
                <div class="profile-img">
                    <?php echo strtoupper(substr($_SESSION['full_name'] ?? 'A', 0, 1)); ?>
                </div>
                <div>
                    <h6 class="mb-0 text-white"><?php echo $_SESSION['full_name'] ?? 'Admin'; ?></h6>
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
                        <h4 class="mb-0 text-gray-800">Edit Intern Details</h4>
                        <small class="text-muted">Updating profile for: <strong><?php echo htmlspecialchars($form_data['full_name']); ?></strong></small>
                    </div>
                    <div>
                        <a href="manage.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-arrow-left me-1"></i>Back to List
                        </a>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Messages -->
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Edit Intern Form -->
        <div class="form-container">
            <form method="POST" action="" id="editInternForm">
                <div class="section-card">
                    <div class="section-title">
                        <i class="fas fa-user-edit"></i>Profile Information
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Full Name *</label>
                            <input type="text" class="form-control" name="full_name" 
                                   value="<?php echo htmlspecialchars($form_data['full_name']); ?>"
                                   placeholder="Enter full name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email Address *</label>
                            <input type="email" class="form-control" name="email" 
                                   value="<?php echo htmlspecialchars($form_data['email']); ?>"
                                   placeholder="intern@company.com" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" name="phone" 
                                   value="<?php echo htmlspecialchars($form_data['phone']); ?>"
                                   placeholder="+91 9876543210">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Account Status</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" id="statusSwitch" name="is_active" <?php echo ($form_data['is_active']) ? 'checked' : ''; ?>>
                                <label class="form-check-label ms-2" for="statusSwitch" id="statusLabel">
                                    <?php echo ($form_data['is_active']) ? 'Active' : 'Inactive'; ?>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="section-card">
                    <div class="section-title">
                        <i class="fas fa-key"></i>Login Credentials
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($form_data['username']); ?>" disabled>
                            <small class="text-muted mt-1 d-block">Read-only field</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">New Password (Optional)</label>
                            <div class="input-group">
                                <input type="password" class="form-control" name="password" id="password"
                                       placeholder="Leave blank to keep current" minlength="6">
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Confirm New Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" name="confirm_password" id="confirmPassword"
                                       placeholder="Repeat new password">
                                <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div id="passwordMatch" class="form-text"></div>
                        </div>
                    </div>
                </div>

                <div class="section-card">
                    <div class="section-title">
                        <i class="fas fa-calendar-alt"></i>Internship Duration & Domain
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Current Domain *</label>
                            <select class="form-select" name="domain_id" id="domainSelect" required>
                                <option value="">Select Domain</option>
                                <?php 
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
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Join Date *</label>
                            <input type="date" class="form-control" name="join_date" id="joinDate"
                                   value="<?php echo htmlspecialchars($form_data['join_date']); ?>" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">End Date *</label>
                            <input type="date" class="form-control" name="end_date" id="endDate"
                                   value="<?php echo htmlspecialchars($form_data['end_date']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="duration-display mt-3">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-info-circle text-primary me-2"></i>
                            <div>
                                <small class="text-muted d-block">Current Duration Calculation</small>
                                <span id="durationText" class="fw-bold text-dark">Calculating...</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mb-5">
                    <a href="manage.php" class="btn btn-outline-secondary">Cancel Changes</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Save All Changes
                    </button>
                </div>
            </form>
        </div>

        <!-- Footer -->
        <div class="footer pb-4 text-center">
            <p class="mb-0 text-muted small">
                &copy; <?php echo date('Y'); ?> <?php echo $company_name; ?> | Intern Management System
            </p>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle password visibility
        function setupToggle(btnId, inputId) {
            const btn = document.getElementById(btnId);
            const input = document.getElementById(inputId);
            if(btn && input) {
                btn.addEventListener('click', function() {
                    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                    input.setAttribute('type', type);
                    this.querySelector('i').classList.toggle('fa-eye');
                    this.querySelector('i').classList.toggle('fa-eye-slash');
                });
            }
        }
        
        setupToggle('togglePassword', 'password');
        setupToggle('toggleConfirmPassword', 'confirmPassword');
        
        // Status switch label update
        const statusSwitch = document.getElementById('statusSwitch');
        const statusLabel = document.getElementById('statusLabel');
        if(statusSwitch && statusLabel) {
            statusSwitch.addEventListener('change', function() {
                statusLabel.textContent = this.checked ? 'Active' : 'Inactive';
            });
        }
        
        // Password matching check
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirmPassword');
        const passwordMatch = document.getElementById('passwordMatch');
        
        function checkPasswords() {
            if (!password.value || !confirmPassword.value) {
                passwordMatch.textContent = '';
                return;
            }
            if (password.value === confirmPassword.value) {
                passwordMatch.textContent = 'Passwords match';
                passwordMatch.className = 'form-text text-success';
            } else {
                passwordMatch.textContent = 'Passwords do not match';
                passwordMatch.className = 'form-text text-danger';
            }
        }
        
        password.addEventListener('input', checkPasswords);
        confirmPassword.addEventListener('input', checkPasswords);
        
        // Duration calculation
        function calculateDuration() {
            const join = new Date(document.getElementById('joinDate').value);
            const end = new Date(document.getElementById('endDate').value);
            const display = document.getElementById('durationText');
            
            if (join && end && !isNaN(join) && !isNaN(end)) {
                if (end < join) {
                    display.textContent = 'Invalid: End date is before join date';
                    display.className = 'fw-bold text-danger';
                    return;
                }
                
                const diffTime = Math.abs(end - join);
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                const months = Math.floor(diffDays / 30);
                const remainingDays = diffDays % 30;
                
                let text = '';
                if (months > 0) text += `${months} month(s) `;
                if (remainingDays > 0) text += `${remainingDays} day(s)`;
                if (text === '') text = '0 days';
                
                display.textContent = text;
                display.className = 'fw-bold text-dark';
            }
        }
        
        document.getElementById('joinDate').addEventListener('change', calculateDuration);
        document.getElementById('endDate').addEventListener('change', calculateDuration);
        calculateDuration();

        // Mobile Sidebar Toggle
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            
            if (sidebar.style.width === '0px' || sidebar.style.width === '') {
                sidebar.style.width = '250px';
                mainContent.style.marginLeft = '250px';
            } else {
                sidebar.style.width = '0px';
                mainContent.style.marginLeft = '0px';
            }
        }
        
        // Add hamburger menu for mobile
        window.addEventListener('DOMContentLoaded', () => {
            if (window.innerWidth <= 768) {
                const navbar = document.querySelector('.navbar-top .d-flex');
                const hamburger = document.createElement('button');
                hamburger.className = 'btn btn-primary me-2';
                hamburger.innerHTML = '<i class="fas fa-bars"></i>';
                hamburger.onclick = toggleSidebar;
                navbar.insertBefore(hamburger, navbar.firstChild);
            }
        });
        
        // Auto-dismiss alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>
