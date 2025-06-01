<?php
require_once __DIR__ . '/../includes/session.php';
$current_user = $session->getCurrentUser();
$current_child = $session->getCurrentChild();
$is_parent = isset($_SESSION['is_parent']) && $_SESSION['is_parent'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>NeoCare System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: rgb(53, 50, 84);
            --background-color: white;
        }
        
        body {
            background-color: var(--background-color);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar {
            background-color: var(--primary-color) !important;
        }
        
        .navbar-brand, .nav-link {
            color: white !important;
        }
        
        .nav-link:hover {
            color: #f8f9fa !important;
            opacity: 0.8;
        }
        
        .sidebar {
            background-color: var(--primary-color);
            min-height: calc(100vh - 56px);
            color: white;
        }
        
        .sidebar .nav-link {
            color: white;
            padding: 15px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar .nav-link:hover {
            background-color: rgba(255,255,255,0.1);
        }
        
        .sidebar .nav-link.active {
            background-color: rgba(255,255,255,0.2);
        }
        
        .main-content {
            background-color: var(--background-color);
            min-height: calc(100vh - 56px);
            padding: 20px;
        }
        
        .card {
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: #3a3654;
            border-color: #3a3654;
        }
        
        .table th {
            background-color: var(--primary-color);
            color: white;
        }
        
        .badge-success { background-color: #28a745; }
        .badge-warning { background-color: #ffc107; }
        .badge-danger { background-color: #dc3545; }
        
        .alert {
            margin-bottom: 20px;
        }
        
        .dropdown-menu {
            border: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="../dashboard.php">
                <i class="fas fa-heartbeat me-2"></i>NeoCare System
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <?php if ($is_parent): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($current_child['parent_name']); ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="../parent/profile.php"><i class="fas fa-user me-2"></i>Child Profile</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($current_user['full_name']); ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="../profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                            </ul>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php if (!$is_parent): ?>
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="../dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        
                        <?php if ($session->hasPermission('super_admin')): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="../super_admin/hospitals.php">
                                <i class="fas fa-hospital me-2"></i>Manage Hospitals
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../super_admin/admins.php">
                                <i class="fas fa-users-cog me-2"></i>Hospital Admins
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../super_admin/reports.php">
                                <i class="fas fa-chart-bar me-2"></i>Global Reports
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if ($session->hasPermission('hospital_admin')): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="../admin/staff.php">
                                <i class="fas fa-user-md me-2"></i>Staff Management
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../admin/children.php">
                                <i class="fas fa-baby me-2"></i>Children
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../admin/vaccines.php">
                                <i class="fas fa-syringe me-2"></i>Vaccines
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../admin/reports.php">
                                <i class="fas fa-chart-line me-2"></i>Reports
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if ($session->hasPermission('doctor')): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="../doctor/children.php">
                                <i class="fas fa-baby me-2"></i>View Children
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../doctor/medical_records.php">
                                <i class="fas fa-file-medical me-2"></i>Medical Records
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../doctor/growth_charts.php">
                                <i class="fas fa-chart-area me-2"></i>Growth Charts
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if ($session->hasPermission('nurse')): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="../nurse/register_child.php">
                                <i class="fas fa-plus me-2"></i>Register Child
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../nurse/vaccination.php">
                                <i class="fas fa-syringe me-2"></i>Vaccinations
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../nurse/children_list.php">
                                <i class="fas fa-baby me-2"></i>Children List
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../nurse/reports.php">
                                <i class="fas fa-chart-pie me-2"></i>Reports
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </nav>
            <?php endif; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($_GET['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($_GET['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
