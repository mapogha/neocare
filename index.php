<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Redirect if already logged in
if ((isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) || 
    (isset($_SESSION['child_id']) && !empty($_SESSION['child_id']))) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $login_type = $_POST['login_type'] ?? '';
    
    if ($login_type == 'staff') {
        $username = $utils->cleanInput($_POST['username'] ?? '');
        $password = $utils->cleanInput($_POST['password'] ?? '');
        
        if (empty($username) || empty($password)) {
            $error = 'Please fill in all fields';
        } else {
            $database = new Database();
            $db = $database->getConnection();
            
            $query = "SELECT * FROM users WHERE username = :username AND is_active = 1";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && $password === $user['password']) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['hospital_id'] = $user['hospital_id'];
                $_SESSION['full_name'] = $user['full_name'];
                
                header('Location: dashboard.php');
                exit();
            } else {
                $error = 'Invalid username or password';
            }
        }
    } elseif ($login_type == 'parent') {
        $child_name = $utils->cleanInput($_POST['child_name'] ?? '');
        $registration_number = $utils->cleanInput($_POST['registration_number'] ?? '');
        
        if (empty($child_name) || empty($registration_number)) {
            $error = 'Please fill in all fields';
        } else {
            $database = new Database();
            $db = $database->getConnection();
            
            $query = "SELECT * FROM children WHERE child_name = :child_name AND registration_number = :reg_number";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':child_name', $child_name);
            $stmt->bindParam(':reg_number', $registration_number);
            $stmt->execute();
            
            $child = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($child) {
                $_SESSION['child_id'] = $child['child_id'];
                $_SESSION['child_name'] = $child['child_name'];
                $_SESSION['parent_name'] = $child['parent_name'];
                $_SESSION['hospital_id'] = $child['hospital_id'];
                $_SESSION['registration_number'] = $child['registration_number'];
                $_SESSION['is_parent'] = true;
                
                header('Location: dashboard.php');
                exit();
            } else {
                $error = 'Invalid child name or registration number';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NeoCare System - Login</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: rgb(53, 50, 84);
            --background-color: white;
        }
        
        body {
            background: linear-gradient(135deg, var(--primary-color) 0%, #6c5ce7 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .login-container {
            background: var(--background-color);
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            overflow: hidden;
            max-width: 900px;
            width: 100%;
        }
        
        .login-header {
            background: var(--primary-color);
            color: white;
            padding: 40px;
            text-align: center;
        }
        
        .login-body {
            padding: 40px;
        }
        
        .nav-tabs {
            border-bottom: 2px solid var(--primary-color);
        }
        
        .nav-tabs .nav-link {
            border: none;
            color: var(--primary-color);
            font-weight: 600;
        }
        
        .nav-tabs .nav-link.active {
            background: var(--primary-color);
            color: white;
            border-radius: 8px 8px 0 0;
        }
        
        .btn-primary {
            background: var(--primary-color);
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
        }
        
        .btn-primary:hover {
            background: #3a3654;
        }
        
        .form-control {
            border-radius: 8px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(53, 50, 84, 0.25);
        }
        
        .alert {
            border-radius: 8px;
            border: none;
        }
        
        .feature-list {
            list-style: none;
            padding: 0;
        }
        
        .feature-list li {
            padding: 8px 0;
            color: rgba(255,255,255,0.9);
        }
        
        .feature-list i {
            margin-right: 10px;
            color: #74b9ff;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="login-container">
                    <div class="row g-0">
                        <div class="col-lg-5">
                            <div class="login-header h-100 d-flex flex-column justify-content-center">
                                <div>
                                    <i class="fas fa-heartbeat fa-3x mb-4"></i>
                                    <h2 class="mb-4">NeoCare System</h2>
                                    <p class="mb-4">Child Immunization & Healthcare Management</p>
                                    <ul class="feature-list">
                                        <li><i class="fas fa-syringe"></i>Vaccination Tracking</li>
                                        <li><i class="fas fa-chart-line"></i>Growth Monitoring</li>
                                        <li><i class="fas fa-bell"></i>SMS Reminders</li>
                                        <li><i class="fas fa-users"></i>Multi-Hospital Support</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-7">
                            <div class="login-body">
                                <?php if (!empty($error)): ?>
                                    <div class="alert alert-danger">
                                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($success)): ?>
                                    <div class="alert alert-success">
                                        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <ul class="nav nav-tabs mb-4" id="loginTabs" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active" id="staff-tab" data-bs-toggle="tab" data-bs-target="#staff" type="button">
                                            <i class="fas fa-user-md me-2"></i>Staff Login
                                        </button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="parent-tab" data-bs-toggle="tab" data-bs-target="#parent" type="button">
                                            <i class="fas fa-users me-2"></i>Parent Login
                                        </button>
                                    </li>
                                </ul>
                                
                                <div class="tab-content" id="loginTabsContent">
                                    <!-- Staff Login -->
                                    <div class="tab-pane fade show active" id="staff">
                                        <form method="POST" action="">
                                            <input type="hidden" name="login_type" value="staff">
                                            <div class="mb-3">
                                                <label for="username" class="form-label">Username</label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                                    <input type="text" class="form-control" id="username" name="username" required>
                                                </div>
                                            </div>
                                            <div class="mb-4">
                                                <label for="password" class="form-label">Password</label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                                    <input type="password" class="form-control" id="password" name="password" required>
                                                </div>
                                            </div>
                                            <button type="submit" class="btn btn-primary w-100">
                                                <i class="fas fa-sign-in-alt me-2"></i>Login
                                            </button>
                                        </form>
                                        
                                        <hr class="my-4">
                                        <div class="text-center">
                                            <small class="text-muted">
                                                <strong>Demo Accounts:</strong><br>
                                                Super Admin: superadmin / admin123<br>
                                                Hospital Admin: central_admin / admin123
                                            </small>
                                        </div>
                                    </div>
                                    
                                    <!-- Parent Login -->
                                    <div class="tab-pane fade" id="parent">
                                        <form method="POST" action="">
                                            <input type="hidden" name="login_type" value="parent">
                                            <div class="mb-3">
                                                <label for="child_name" class="form-label">Child's Full Name</label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="fas fa-baby"></i></span>
                                                    <input type="text" class="form-control" id="child_name" name="child_name" required>
                                                </div>
                                            </div>
                                            <div class="mb-4">
                                                <label for="registration_number" class="form-label">Registration Number</label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                                    <input type="text" class="form-control" id="registration_number" name="registration_number" placeholder="e.g., NC2024010001" required>
                                                </div>
                                            </div>
                                            <button type="submit" class="btn btn-primary w-100">
                                                <i class="fas fa-sign-in-alt me-2"></i>Access Child Records
                                            </button>
                                        </form>
                                        
                                        <div class="alert alert-info mt-3">
                                            <i class="fas fa-info-circle me-2"></i>
                                            <small>Use your child's name and registration number provided by the hospital.</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
</body>
</html>
