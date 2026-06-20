<?php
/**
 * teacher_login.php
 * Staff authentication portal for teachers.
 * Validates credentials against the users table for the 'teacher' role.
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

$error_message = "";

// Redirect if already logged in as teacher
if (is_logged_in() && $_SESSION['role'] === 'teacher') {
    header("Location: /e-learning system/teacher/dashboard.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $password = filter_input(INPUT_POST, 'password', FILTER_DEFAULT);
    
    if ($email && $password) {
        try {
            // Retrieve teacher user by email (username column)
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role = 'teacher'");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // Set session tokens
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = 'teacher';
                $_SESSION['full_name'] = $user['full_name'];
                
                // Redirect to teacher home
                header("Location: /e-learning system/teacher/dashboard.php");
                exit;
            } else {
                $error_message = "Invalid email address or password.";
            }
        } catch (PDOException $e) {
            $error_message = "Database query error. Please contact the administrator.";
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
    <title>Teacher Sign In - Interactive E-Learning System</title>
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #1e3a8a 0%, #0f172a 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .split-login-container {
            display: grid;
            grid-template-columns: 1.1fr 1fr;
            max-width: 900px;
            width: 100%;
            background-color: var(--bg-card);
            border-radius: var(--radius-standard);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            overflow: hidden;
            border: 1px solid var(--border-color);
        }
        
        .login-branding {
            background-color: #1e3a8a;
            background-image: radial-gradient(circle at 20% 30%, #3b82f6 0%, #1e3a8a 70%);
            color: white;
            padding: 48px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .login-branding h1 {
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 12px;
            letter-spacing: -0.03em;
        }
        .login-branding p {
            color: #93c5fd;
            font-size: 1.05rem;
            line-height: 1.5;
        }
        
        .login-form-panel {
            padding: 48px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        @media (max-width: 768px) {
            .split-login-container {
                grid-template-columns: 1fr;
            }
            .login-branding {
                display: none;
            }
        }
    </style>
</head>
<body>

    <div class="split-login-container">
        
        <!-- Branding side -->
        <div class="login-branding">
            <h1>Fun Learning System</h1>
            <p>Welcome back, Teacher! Sign in to oversee your student cohorts, monitor learning streaks, grade homework tasks, and assign customized bonus rewards.</p>
        </div>
        
        <!-- Form side -->
        <div class="login-form-panel">
            <h2 style="font-weight: 700; margin-bottom: 8px; font-size: 1.6rem;">Teacher Sign In</h2>
            <p style="color: var(--text-secondary); margin-bottom: 24px; font-size: 0.9rem;">Please enter your credentials below.</p>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert-danger">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="teacher_login.php">
                <div class="form-group">
                    <label class="form-label" for="email">School Email</label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="teacher@school.com" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>
                
                <div class="form-group" style="display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" id="remember" name="remember" style="cursor: pointer; width: 16px; height: 16px;">
                    <label for="remember" style="font-size: 0.85rem; color: var(--text-secondary); cursor: pointer; user-select: none;">Remember Me</label>
                </div>
                
                <button type="submit" class="form-control btn-solid-blue" style="font-weight: 700; cursor: pointer; margin-top: 10px; height: 48px;">
                    Sign In 🚀
                </button>
            </form>
            
            <div style="text-align: center; margin-top: 24px;">
                <a href="login.php" style="font-size: 0.85rem; color: var(--accent-color); font-weight: 600;">Go to Student Login Pad</a>
            </div>
        </div>

    </div>

</body>
</html>
