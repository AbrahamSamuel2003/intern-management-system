<?php
// admin/intern/manage.php
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

$company_query = "SELECT company_name FROM company_details LIMIT 1";
$company_result = mysqli_query($conn, $company_query);
$company_data = mysqli_fetch_assoc($company_result);
$company_name = $company_data['company_name'] ?? 'IMS';

// Fetch Interns
$query = "
    SELECT 
        u.*, 
        d.domain_name,
        p.performance_score,
        p.eligibility
    FROM users u
    LEFT JOIN domains d ON u.domain_id = d.id
    LEFT JOIN performance p ON u.id = p.intern_id
    WHERE u.role = 'intern'
    ORDER BY u.created_at DESC
";
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Interns - <?php echo $company_name; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fc; }
        .sidebar { position: fixed; top: 0; left: 0; bottom: 0; width: 250px; background: linear-gradient(180deg, #4e73df 10%, #224abe 100%); color: white; }
        .main-content { margin-left: 250px; padding: 20px; }
        .card { border: none; border-radius: 10px; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15); }
        .table { vertical-align: middle; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="p-3 text-center border-bottom">
            <h4>IMS ADMIN</h4>
        </div>
        <div class="p-3">
            <a href="../dashboard.php" class="nav-link text-white mb-2"><i class="fas fa-tachometer-alt me-2"></i> Dashboard</a>
            <a href="manage.php" class="nav-link text-white active"><i class="fas fa-user-graduate me-2"></i> Interns</a>
        </div>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3><i class="fas fa-user-graduate me-2"></i>Manage Interns</h3>
            <button class="btn btn-primary"><i class="fas fa-plus me-2"></i>Add New Intern</button>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Domain</th>
                                <th>Email</th>
                                <th>Score</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['full_name']); ?></strong><br>
                                    <small class="text-muted">@<?php echo htmlspecialchars($row['username']); ?></small>
                                </td>
                                <td><span class="badge bg-info"><?php echo htmlspecialchars($row['domain_name'] ?? 'N/A'); ?></span></td>
                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                <td>
                                    <span class="fw-bold <?php echo ($row['performance_score'] >= 70) ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo $row['performance_score'] ? round($row['performance_score'], 1) . '%' : 'N/A'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if($row['eligibility'] == 'eligible'): ?>
                                        <span class="badge bg-success">Eligible</span>
                                    <?php elseif($row['eligibility'] == 'not_eligible'): ?>
                                        <span class="badge bg-danger">Not Eligible</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>