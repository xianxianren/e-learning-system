<?php
/**
 * admin_login.php
 * Master authentication portal for school administrators.
 * Validates credentials against the users table for the 'admin' role.
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

$error_message = "";

// Redirect if already logged in as admin
if (is_logged_in() && $_SESSION['role'] === 'admin') {
    header("Location: /e-learning system/admin/dashboard.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $password = filter_input(INPUT_POST, 'password', FILTER_DEFAULT);
    
    if ($email && $password) {
        try {
            // Retrieve admin user by email (username column)
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role = 'admin'");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // Set session tokens
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = 'admin';
                $_SESSION['full_name'] = $user['full_name'];
                
                // Redirect to admin dashboard
                header("Location: /e-learning system/admin/dashboard.php");
                exit;
            } else {
                $error_message = "Invalid administrator credentials.";
            }
        } catch (PDOException $e) {
            $error_message = "Database query error. Please contact technical support.";
        }
    } else {
        $error_message = "Please fill in all email and password fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Admin Sign In - Interactive E-Learning System</title>
    <link rel="stylesheet" href="assets/css/admin.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--color-slate-900);
            min-height: 100vh;
            padding: 20px;
        }
        
        .admin-login-box {
            background-color: white;
            border-radius: var(--radius-md);
            max-width: 420px;
            width: 100%;
            padding: 40px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            border: 1px solid var(--color-slate-800);
        }
        
        .admin-login-title {
            text-align: center;
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 24px;
            color: var(--color-slate-900);
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }
        
        .error-banner {
            background-color: #fef2f2;
            border: 1px solid #fca5a5;
            color: var(--color-danger);
            padding: 12px;
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
            font-weight: 600;
            font-size: 0.85rem;
            text-align: center;
        }
    </style>
</head>
<body>

    <div class="admin-login-box">
        <h2 class="admin-login-title">⚙️ System Console</h2>
        
        <?php if (!empty($error_message)): ?>
            <div class="error-banner">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="admin_login.php">
            <div class="admin-form-group">
                <label class="admin-form-label" for="email">Admin ID (Email)</label>
                <input type="email" id="email" name="email" class="admin-form-control" placeholder="admin@school.com" required>
            </div>
            
            <div class="admin-form-group">
                <label class="admin-form-label" for="password">Password</label>
                <input type="password" id="password" name="password" class="admin-form-control" placeholder="••••••••" required>
            </div>
            
            <button type="submit" class="btn-admin btn-admin-primary" style="width: 100%; height: 42px; margin-top: 10px; justify-content: center;">
                Execute Authentication Securely Key
            </button>
        </form>
        
        <div style="text-align: center; margin-top: 24px;">
            <a href="login.php" style="font-size: 0.8rem; color: var(--color-slate-600); font-weight: 600; text-decoration: none;">Return to Classroom Login</a>
        </div>
    </div>

</body>
</html>
