<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if ((!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) && 
    (!isset($_SESSION['child_id']) || empty($_SESSION['child_id']))) {
    header('Location: index.php');
    exit();
}

// Redirect based on user role or if it's a parent
if (isset($_SESSION['is_parent']) && $_SESSION['is_parent']) {
    // Parent login - redirect to parent dashboard
    header('Location: parent/dashboard.php');
    exit();
} elseif (isset($_SESSION['role'])) {
    // Staff login - redirect to role-specific dashboard
    switch ($_SESSION['role']) {
        case 'super_admin':
            header('Location: super_admin/dashboard.php');
            break;
        case 'admin':
        case 'hospital_admin':  // ADD THIS LINE - Support both admin and hospital_admin
            header('Location: admin/dashboard.php');
            break;
        case 'doctor':
            header('Location: doctor/dashboard.php');
            break;
        case 'nurse':
            header('Location: nurse/dashboard.php');
            break;
        default:
            // If role is not recognized, logout
            session_destroy();
            header('Location: index.php?error=invalid_role');
            exit();
    }
} else {
    // No valid session, redirect to login
    session_destroy();
    header('Location: index.php?error=session_expired');
    exit();
}
?>