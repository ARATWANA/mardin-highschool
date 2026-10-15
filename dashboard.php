<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';

requireAuth();
$user = currentUser();
$role = $user['role_name'] ?? 'student';

switch ($role) {
    case 'admin':
        require __DIR__ . '/admin/dashboard.php';
        break;
    case 'teacher':
        require __DIR__ . '/teacher/dashboard.php';
        break;
    case 'student':
        require __DIR__ . '/student/dashboard.php';
        break;
    case 'class_monitor':
        require __DIR__ . '/monitor/dashboard.php';
        break;
    default:
        echo '<div class="alert alert-danger">Unauthorized role.</div>';
        break;
}

require __DIR__ . '/includes/footer.php';
