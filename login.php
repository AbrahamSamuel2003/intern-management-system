<?php
session_start();

// Include database configuration
require_once 'config/database.php';



// If company not setup, redirect to setup
if (!isCompanySetup($conn)) {
    header('Location: index.php');
    exit();
}

// Get company details for advertisement
$company_query = "SELECT 
    company_name, 
    internship_duration, 
    office_timing, 
    min_score_for_job,
    created_at 
    FROM company_details LIMIT 1";
$company_result = mysqli_query($conn, $company_query);
$company_data = mysqli_fetch_assoc($company_result);
$company_name = $company_data['company_name'] ?? 'Intern Management System';
$internship_duration = $company_data['internship_duration'] ?? '6 Months';
$office_timing = $company_data['office_timing'] ?? '9 AM - 6 PM';
$min_score = $company_data['min_score_for_job'] ?? 70;
$created_at = $company_data['created_at'] ?? date('Y-m-d');

// Calculate company age
$company_start = new DateTime($created_at);
$current_date = new DateTime();
$company_age = $current_date->diff($company_start)->y;

// Get statistics for advertisement
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM users WHERE role = 'intern' AND is_active = TRUE) as total_interns,
    (SELECT COUNT(*) FROM users WHERE role = 'team_lead' AND is_active = TRUE) as total_team_leads,
    (SELECT COUNT(*) FROM domains) as total_domains,
    (SELECT COUNT(*) FROM tasks WHERE status = 'completed') as completed_tasks,
    (SELECT COUNT(*) FROM performance WHERE eligibility = 'eligible') as eligible_interns,
    (SELECT COUNT(*) FROM users WHERE role = 'intern') as all_time_interns";
$stats_result = mysqli_query($conn, $stats_query);
$stats_data = mysqli_fetch_assoc($stats_result);

// Check if rating column exists in tasks table
$check_rating_query = "SHOW COLUMNS FROM tasks LIKE 'rating'";
$rating_result = mysqli_query($conn, $check_rating_query);
$rating_column_exists = (mysqli_num_rows($rating_result) > 0);

// Get success stories - FIXED QUERY
if ($rating_column_exists) {
    // If rating column exists
    $success_query = "SELECT 
        u.full_name,
        t.title,
        t.rating,
        d.domain_name
        FROM tasks t
        JOIN users u ON t.assigned_to = u.id
        JOIN domains d ON t.domain_id = d.id
        WHERE t.status = 'completed' 
        AND t.rating >= 4
        ORDER BY t.submitted_date DESC 
        LIMIT 3";
} else {
    // If rating column doesn't exist, use performance table or show generic
    $success_query = "SELECT 
        u.full_name,
        t.title,
        '5' as rating,  -- Default rating
        d.domain_name
        FROM tasks t
        JOIN users u ON t.assigned_to = u.id
        JOIN domains d ON t.domain_id = d.id
        WHERE t.status = 'completed'
        ORDER BY t.submitted_date DESC 
        LIMIT 3";
}
$success_stories = mysqli_query($conn, $success_query);

// Get domains for display
$domains_query = "SELECT domain_name FROM domains LIMIT 4";
$domains_result = mysqli_query($conn, $domains_query);

// Initialize variables
$error = '';
$username = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    
    // Validate inputs
    if (empty($username) || empty($password)) {
        $error = 'Please enter username and password';
    } else {
        // Check if user exists
        $query = "SELECT u.*, d.domain_name FROM users u 
                  LEFT JOIN domains d ON u.domain_id = d.id 
                  WHERE u.username = '$username' AND u.is_active = TRUE";
        $result = mysqli_query($conn, $query);
        
        if (mysqli_num_rows($result) === 1) {
            $user = mysqli_fetch_assoc($result);
            
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['domain_id'] = $user['domain_id'];
                $_SESSION['domain_name'] = $user['domain_name'] ?? 'General';
                $_SESSION['email'] = $user['email'];
                
                // NO last_login update - removed as requested
                
                // Redirect based on role
                switch ($user['role']) {
                    case 'admin':
                        header('Location: admin/dashboard.php');
                        break;
                    case 'team_lead':
                        header('Location: teamlead/dashboard.php');
                        break;
                    case 'intern':
                        header('Location: intern/dashboard.php');
                        break;
                    default:
                        header('Location: login.php');
                }
                exit();
            } else {
                $error = 'Invalid username or password';
            }
        } else {
            $error = 'Invalid username or password';
        }
    }
}

// Check if user is already logged in - REMOVED automatic redirect to dashboard
// to ensure user is always presented with the login page as requested.
/*
if (isset($_SESSION['user_id'])) {
    // Redirect based on role
    switch ($_SESSION['role']) {
        case 'admin':
            header('Location: admin/dashboard.php');
            break;
        case 'team_lead':
            header('Location: teamlead/dashboard.php');
            break;
        case 'intern':
            header('Location: intern/dashboard.php');
            break;
    }
    exit();
}
*/
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to <?php echo htmlspecialchars($company_name); ?> - IMSJR</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #00b09b 0%, #96c93d 100%);
            --success-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            --warning-gradient: linear-gradient(135deg, #f7971e 0%, #ffd200 100%);
        }
        
        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }
        
        /* Hero Section */
        .hero-section {
            background: var(--primary-gradient);
            color: white;
            padding: 80px 0 40px;
            margin-bottom: 40px;
            position: relative;
            overflow: hidden;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="%23ffffff" fill-opacity="0.1" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,112C672,96,768,96,864,112C960,128,1056,160,1152,160C1248,160,1344,128,1392,112L1440,96L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>');
            background-size: cover;
            opacity: 0.1;
        }
        
        .company-logo {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }
        
        .company-tagline {
            font-size: 1.2rem;
            opacity: 0.9;
            font-weight: 300;
            max-width: 600px;
            margin: 0 auto 30px;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 40px 0;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            transition: transform 0.3s, box-shadow 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: var(--success-gradient);
        }
        
        .stat-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        .stat-icon {
            width: 70px;
            height: 70px;
            background: var(--primary-gradient);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 30px;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #7f8c8d;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        /* Features Section */
        .features-section {
            background: white;
            border-radius: 20px;
            padding: 40px;
            margin: 40px 0;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }
        
        .section-title {
            text-align: center;
            margin-bottom: 40px;
            color: #2c3e50;
            font-weight: 600;
        }
        
        .feature-item {
            text-align: center;
            padding: 20px;
            transition: transform 0.3s;
        }
        
        .feature-item:hover {
            transform: translateY(-5px);
        }
        
        .feature-icon {
            width: 60px;
            height: 60px;
            background: var(--primary-gradient);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 24px;
        }
        
        /* Success Stories */
        .story-card {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            border-left: 5px solid #667eea;
            transition: all 0.3s;
        }
        
        .story-card:hover {
            background: white;
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.1);
        }
        
        /* Login Form Section */
        .login-section {
            background: white;
            border-radius: 20px;
            padding: 40px;
            margin: 40px auto;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
            position: relative;
            z-index: 10;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-title {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .login-subtitle {
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        
        .form-control {
            border: 2px solid #eef2f7;
            border-radius: 10px;
            padding: 15px;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-login {
            background: var(--primary-gradient);
            border: none;
            border-radius: 10px;
            padding: 15px;
            font-weight: 600;
            font-size: 16px;
            width: 100%;
            transition: all 0.3s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .input-group-text {
            background: transparent;
            border: 2px solid #eef2f7;
            border-right: none;
        }
        
        /* Domain Tags */
        .domain-tag {
            display: inline-block;
            background: #e3f2fd;
            color: #1976d2;
            padding: 5px 15px;
            border-radius: 20px;
            margin: 5px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        /* Footer */
        .footer {
            text-align: center;
            padding: 30px 0;
            color: #7f8c8d;
            font-size: 0.9rem;
            border-top: 1px solid #eee;
            margin-top: 40px;
        }
        
        /* Animation Classes */
        .animate-delay-1 {
            animation-delay: 0.2s;
        }
        
        .animate-delay-2 {
            animation-delay: 0.4s;
        }
        
        .animate-delay-3 {
            animation-delay: 0.6s;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .hero-section {
                padding: 40px 0 20px;
            }
            
            .company-logo {
                font-size: 2.5rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .features-section, .login-section {
                padding: 25px;
            }
        }
    </style>
</head>
<body>
    <!-- Hero Section -->
    <div class="hero-section">
        <div class="container">
            <div class="text-center animate__animated animate__fadeInDown">
                <div class="company-logo"><?php echo htmlspecialchars($company_name); ?></div>
                <div class="company-tagline">
                    Transforming Interns into Industry Professionals through Structured Mentorship & Real-World Projects
                </div>
                <div class="mt-3">
                    <span class="badge bg-light text-dark me-2">Est. <?php echo $company_age; ?> Years</span>
                    <span class="badge bg-light text-dark me-2"><?php echo $internship_duration; ?> Program</span>
                    <span class="badge bg-light text-dark"><?php echo $min_score; ?>%+ for Job</span>
                </div>
            </div>
            
            <!-- Quick Stats -->
            <div class="stats-grid">
                <div class="stat-card animate__animated animate__fadeInUp">
                    <div class="stat-icon">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="stat-number" id="activeInterns">0</div>
                    <div class="stat-label">Active Interns</div>
                </div>
                
                <div class="stat-card animate__animated animate__fadeInUp animate-delay-1">
                    <div class="stat-icon">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <div class="stat-number" id="completedTasks">0</div>
                    <div class="stat-label">Projects Completed</div>
                </div>
                
                <div class="stat-card animate__animated animate__fadeInUp animate-delay-2">
                    <div class="stat-icon">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <div class="stat-number" id="eligibleInterns">0</div>
                    <div class="stat-label">Job Eligible Interns</div>
                </div>
                
                <div class="stat-card animate__animated animate__fadeInUp animate-delay-3">
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-number"><?php echo $min_score; ?>%</div>
                    <div class="stat-label">Minimum Eligibility Score</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Company Advertisement Section -->
    <div class="container">
        <!-- Features Section -->
        <div class="features-section animate__animated animate__fadeIn">
            <h2 class="section-title">Why Choose Our Internship Program?</h2>
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="feature-item">
                        <div class="feature-icon">
                            <i class="fas fa-laptop-code"></i>
                        </div>
                        <h4>Real Projects</h4>
                        <p class="text-muted">Work on live projects with actual business impact and client requirements.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="feature-item">
                        <div class="feature-icon">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <h4>Expert Mentorship</h4>
                        <p class="text-muted">1:1 guidance from industry experts with 5+ years of experience.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="feature-item">
                        <div class="feature-icon">
                            <i class="fas fa-briefcase"></i>
                        </div>
                        <h4>Job Placement</h4>
                        <p class="text-muted">Top performers get direct job offers from our partner companies.</p>
                    </div>
                </div>
            </div>
            
            <!-- Available Domains -->
            <div class="text-center mt-4">
                <h5 class="mb-3">Available Domains</h5>
                <div>
                    <?php 
                    // Reset pointer for domains result
                    mysqli_data_seek($domains_result, 0);
                    while($domain = mysqli_fetch_assoc($domains_result)): ?>
                        <span class="domain-tag"><?php echo htmlspecialchars($domain['domain_name']); ?></span>
                    <?php endwhile; ?>
                    <span class="domain-tag">+ More</span>
                </div>
            </div>
        </div>
        
        <!-- Success Stories -->
        <div class="row mb-5">
            <div class="col-md-12">
                <h3 class="section-title">Recent Success Stories</h3>
                <div class="row">
                    <?php if(mysqli_num_rows($success_stories) > 0): ?>
                        <?php while($story = mysqli_fetch_assoc($success_stories)): ?>
                        <div class="col-md-4 mb-3">
                            <div class="story-card animate__animated animate__fadeIn">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="bg-primary rounded-circle p-2 me-3">
                                        <i class="fas fa-user text-white"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0"><?php echo htmlspecialchars($story['full_name']); ?></h6>
                                        <small class="text-muted"><?php echo htmlspecialchars($story['domain_name']); ?></small>
                                    </div>
                                </div>
                                <p class="mb-2">"Completed <?php echo htmlspecialchars($story['title']); ?>"</p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="text-warning">
                                        <?php 
                                        $rating = isset($story['rating']) ? (int)$story['rating'] : 5;
                                        for($i = 0; $i < $rating; $i++): ?>
                                            <i class="fas fa-star"></i>
                                        <?php endfor; ?>
                                        <?php for($i = $rating; $i < 5; $i++): ?>
                                            <i class="far fa-star"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <span class="badge bg-success">Success</span>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="col-12 text-center">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                Success stories will appear here as interns complete their projects.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Login Form (Now below advertisement) -->
        <div class="login-section animate__animated animate__fadeInUp">
            <div class="login-header">
                <h3 class="login-title">Access Your Dashboard</h3>
                <p class="login-subtitle">Enter your credentials to continue</p>
            </div>
            
            <!-- Error Message -->
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show animate__animated animate__shakeX" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Success Message -->
            <?php if (isset($_GET['setup']) && $_GET['setup'] == 'success'): ?>
                <div class="alert alert-success alert-dismissible fade show animate__animated animate__fadeIn" role="alert">
                    <i class="fas fa-check-circle me-2"></i>Company setup successful! Please login with your admin credentials.
                </div>
            <?php endif; ?>
            
            <!-- Login Form -->
            <form method="POST" action="" id="loginForm">
                <div class="mb-4">
                    <label class="form-label">Username</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-user"></i>
                        </span>
                        <input type="text" class="form-control" 
                               name="username" 
                               value="<?php echo htmlspecialchars($username); ?>"
                               placeholder="Enter your username" 
                               required
                               autofocus>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" class="form-control" 
                               name="password" 
                               id="password"
                               placeholder="Enter your password" 
                               required>
                        <button type="button" class="input-group-text" id="togglePassword">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="d-grid mb-3">
                    <button type="submit" class="btn btn-login">
                        <i class="fas fa-sign-in-alt me-2"></i>Login to Dashboard
                    </button>
                </div>
                
                <div class="text-center">
                    <p class="text-muted mb-2">
                        <i class="fas fa-info-circle me-1"></i>
                        Use credentials provided by administrator
                    </p>
                    <small class="text-muted">
                        <i class="fas fa-shield-alt me-1"></i>
                        Secure & Encrypted Connection
                    </small>
                </div>
            </form>
        </div>
        
        <!-- Program Information -->
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-calendar-alt text-primary me-2"></i>Program Duration</h5>
                        <p class="card-text"><?php echo $internship_duration; ?> comprehensive program with structured learning path.</p>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-primary" style="width: 100%"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-clock text-success me-2"></i>Office Hours</h5>
                        <p class="card-text"><?php echo $office_timing; ?> - Flexible timing available for remote interns.</p>
                        <div class="d-flex align-items-center">
                            <i class="fas fa-laptop-house text-success me-2"></i>
                            <span>Remote & On-site options available</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <div class="footer">
        <div class="container">
            <p>© <?php echo date('Y'); ?> <?php echo htmlspecialchars($company_name); ?> - Intern Management System</p>
            <p class="mb-0">
                <small>
                    <i class="fas fa-envelope me-1"></i> contact@<?php echo strtolower(str_replace(' ', '', $company_name)); ?>.com | 
                    <i class="fas fa-phone ms-3 me-1"></i> +1 (555) 123-4567
                </small>
            </p>
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
        
        // Form validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const username = document.querySelector('input[name="username"]').value.trim();
            const password = document.querySelector('input[name="password"]').value.trim();
            
            if (username === '' || password === '') {
                e.preventDefault();
                alert('Please enter both username and password');
                return false;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Authenticating...';
            submitBtn.disabled = true;
        });
        
        // Animated counters
        function animateCounter(element, start, end, duration) {
            let startTimestamp = null;
            const step = (timestamp) => {
                if (!startTimestamp) startTimestamp = timestamp;
                const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                const current = Math.floor(progress * (end - start) + start);
                element.textContent = current;
                if (progress < 1) {
                    window.requestAnimationFrame(step);
                }
            };
            window.requestAnimationFrame(step);
        }
        
        // Initialize counters with data from PHP
        document.addEventListener('DOMContentLoaded', function() {
            const stats = {
                activeInterns: <?php echo $stats_data['total_interns'] ?? 0; ?>,
                completedTasks: <?php echo $stats_data['completed_tasks'] ?? 0; ?>,
                eligibleInterns: <?php echo $stats_data['eligible_interns'] ?? 0; ?>
            };
            
            // Animate counters with different speeds
            setTimeout(() => {
                animateCounter(document.getElementById('activeInterns'), 0, stats.activeInterns, 2000);
            }, 500);
            
            setTimeout(() => {
                animateCounter(document.getElementById('completedTasks'), 0, stats.completedTasks, 1500);
            }, 1000);
            
            setTimeout(() => {
                animateCounter(document.getElementById('eligibleInterns'), 0, stats.eligibleInterns, 2500);
            }, 1500);
            
            // Typewriter effect for company name
            const companyName = document.querySelector('.company-logo');
            const originalText = companyName.textContent;
            companyName.textContent = '';
            let i = 0;
            
            function typeWriter() {
                if (i < originalText.length) {
                    companyName.textContent += originalText.charAt(i);
                    i++;
                    setTimeout(typeWriter, 50);
                }
            }
            
            // Start typing after page loads
            setTimeout(typeWriter, 1000);
            
            // Add scroll animation
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animate__animated', 'animate__fadeInUp');
                    }
                });
            }, observerOptions);
            
            // Observe elements for scroll animation
            document.querySelectorAll('.feature-item, .story-card, .card').forEach(el => {
                observer.observe(el);
            });
            
            // Auto-rotate success stories every 10 seconds
            const storyCards = document.querySelectorAll('.story-card');
            let currentStory = 0;
            
            function rotateStories() {
                storyCards.forEach(card => card.style.opacity = '0.5');
                storyCards[currentStory].style.opacity = '1';
                currentStory = (currentStory + 1) % storyCards.length;
            }
            
            if (storyCards.length > 1) {
                setInterval(rotateStories, 10000);
            }
        });
    </script>
</body>
</html>
