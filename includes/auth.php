<?php
/**
 * auth.php
 * Session management, CSRF validation, and role protection for the E-Learning System.
 */

if (session_status() === PHP_SESSION_NONE) {
    // Configure session cookie settings for security
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    
    // Start session
    session_start();
}

/**
 * Regenerates session ID periodically to prevent session fixation.
 */
function secure_session_regenerate() {
    if (!isset($_SESSION['last_regeneration']) || time() - $_SESSION['last_regeneration'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}
secure_session_regenerate();

/**
 * Checks if a user is logged in.
 * @return bool
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

/**
 * Requires the user to be logged in and possess a specific role.
 * Redirects to the login page if unauthorized.
 * @param string $role ('student', 'teacher', 'admin')
 */
function require_role($role) {
    if (!is_logged_in()) {
        header("Location: /e-learning system/login.php");
        exit;
    }
    
    if ($_SESSION['role'] !== $role) {
        // Redirect unauthorized users to their respective homepages
        if ($_SESSION['role'] === 'student') {
            header("Location: /e-learning system/student/dashboard.php");
        } elseif ($_SESSION['role'] === 'teacher') {
            header("Location: /e-learning system/teacher/dashboard.php");
        } elseif ($_SESSION['role'] === 'admin') {
            header("Location: /e-learning system/admin/dashboard.php");
        } else {
            header("Location: /e-learning system/login.php");
        }
        exit;
    }
}

/**
 * Generates a CSRF token and saves it in the session.
 * @return string
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validates a submitted CSRF token.
 * @param string $token
 * @return bool
 */
function validate_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}
?>
