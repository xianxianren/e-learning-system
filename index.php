<?php
/**
 * index.php
 * Main entry point. Redirects users to their corresponding dashboard if authenticated,
 * or routes them directly to the portal login view.
 */

require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    if ($_SESSION['role'] === 'student') {
        header("Location: /e-learning system/student/dashboard.php");
    } elseif ($_SESSION['role'] === 'teacher') {
        header("Location: /e-learning system/teacher/dashboard.php");
    } elseif ($_SESSION['role'] === 'admin') {
        header("Location: /e-learning system/admin/dashboard.php");
    } else {
        header("Location: /e-learning system/login.php");
    }
} else {
    header("Location: /e-learning system/login.php");
}
exit;
?>
