<?php
/**
 * login.php
 * Unified portal login handler.
 * Provides a mobile-first PIN panel for students and a toggleable credential form for staff.
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

$error_message = "";

// If user is already logged in, redirect them to their respective dashboard
if (is_logged_in()) {
    if ($_SESSION['role'] === 'student') {
        header("Location: /e-learning system/student/dashboard.php");
    } elseif ($_SESSION['role'] === 'teacher') {
        header("Location: /e-learning system/teacher/dashboard.php");
    } elseif ($_SESSION['role'] === 'admin') {
        header("Location: /e-learning system/admin/dashboard.php");
    }
    exit;
}

// Handle login submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_type = isset($_POST['login_type']) ? $_POST['login_type'] : 'student';
    
    if ($login_type === 'student') {
        $student_id = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
        $pin = filter_input(INPUT_POST, 'pin', FILTER_DEFAULT);
        
        if ($student_id && $pin) {
            try {
                // Fetch student record
                $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ? AND role = 'student'");
                $stmt->execute([$student_id]);
                $user = $stmt->fetch();
                
                if ($user && password_verify($pin, $user['password_hash'])) {
                    // Start authenticated session
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = 'student';
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['avatar_url'] = $user['avatar_url'];
                    
                    // Update gamification streak & last login date
                    $statStmt = $pdo->prepare("SELECT * FROM gamification_stats WHERE student_id = ?");
                    $statStmt->execute([$user['user_id']]);
                    $stats = $statStmt->fetch();
                    
                    $today = date('Y-m-d');
                    if ($stats) {
                        $last_login = $stats['last_login'];
                        $new_streak = $stats['login_streak'];
                        
                        if ($last_login) {
                            $datetime1 = new DateTime($last_login);
                            $datetime2 = new DateTime($today);
                            $interval = $datetime1->diff($datetime2);
                            $days_diff = $interval->days;
                            
                            if ($days_diff === 1) {
                                // Logged in yesterday: increment streak
                                $new_streak++;
                            } elseif ($days_diff > 1) {
                                // Missed days: reset streak
                                $new_streak = 1;
                            }
                            // If days_diff is 0, they already logged in today; streak remains unchanged
                        } else {
                            $new_streak = 1;
                        }
                        
                        $updateStats = $pdo->prepare("UPDATE gamification_stats SET login_streak = ?, last_login = ? WHERE student_id = ?");
                        $updateStats->execute([$new_streak, $today, $user['user_id']]);
                    } else {
                        // Create default gamification record
                        $insertStats = $pdo->prepare("INSERT INTO gamification_stats (student_id, total_points, login_streak, last_login) VALUES (?, 0, 1, ?)");
                        $insertStats->execute([$user['user_id'], $today]);
                    }
                    
                    // Redirect to dashboard
                    header("Location: /e-learning system/student/dashboard.php");
                    exit;
                } else {
                    $error_message = "Oops! That is not the right secret PIN. Try again! 🤫";
                }
            } catch (PDOException $e) {
                $error_message = "Something went wrong. Please ask your teacher for help!";
            }
        } else {
            $error_message = "Please select your avatar and enter your 4-digit PIN.";
        }
    } else {
        // Staff credentials authentication
        $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
        $password = filter_input(INPUT_POST, 'password', FILTER_DEFAULT);
        
        if ($email && $password) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role IN ('teacher', 'admin')");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                
                if ($user && password_verify($password, $user['password_hash'])) {
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['full_name'] = $user['full_name'];
                    
                    if ($user['role'] === 'teacher') {
                        header("Location: /e-learning system/teacher/dashboard.php");
                    } else {
                        header("Location: /e-learning system/admin/dashboard.php");
                    }
                    exit;
                } else {
                    $error_message = "Invalid email or password.";
                }
            } catch (PDOException $e) {
                $error_message = "Database error. Please try again later.";
            }
        } else {
            $error_message = "Please fill in all email and password fields.";
        }
    }
}

// Fetch students list for avatar scroll bar
try {
    $students_stmt = $pdo->prepare("SELECT user_id, full_name, avatar_url FROM users WHERE role = 'student' ORDER BY full_name ASC");
    $students_stmt->execute();
    $students = $students_stmt->fetchAll();
} catch (PDOException $e) {
    $students = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to the Fun Classroom! 🎈</title>
    <link rel="stylesheet" href="assets/css/student.css">
    <style>
        /* Embedded CSS to support the layout swap between Student and Staff styles */
        body {
            background: radial-gradient(circle, #fbc2eb 0%, #a6c1ee 100%);
            padding-bottom: 20px;
        }
        
        .staff-form-group {
            margin-bottom: 15px;
            text-align: left;
            width: 100%;
        }
        .staff-form-group label {
            display: block;
            font-weight: 800;
            margin-bottom: 5px;
            font-size: 0.95rem;
        }
        .staff-input {
            width: 100%;
            padding: 12px;
            border-radius: 12px;
            border: 3px solid #e2ebf5;
            font-family: var(--font-fun);
            font-size: 1rem;
            outline: none;
        }
        .staff-input:focus {
            border-color: var(--color-blue);
        }
        
        .toggle-btn {
            background: none;
            border: none;
            color: var(--color-blue);
            font-weight: 800;
            cursor: pointer;
            text-decoration: underline;
            margin-top: 15px;
            font-size: 1rem;
        }
        
        .error-bubble {
            background-color: #ffe5e5;
            border: 2px solid #ff4b4b;
            color: #d43b3b;
            padding: 12px;
            border-radius: var(--radius-medium);
            margin-bottom: 15px;
            font-weight: 800;
            width: 100%;
            animation: text-shake 0.3s ease;
        }
        
        /* Centering styles for student login panel */
        #student_login_panel {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 100%;
        }
        
        #student_login_form {
            width: 100%;
            display: flex;
            justify-content: center;
        }
        
        .keypad {
            justify-content: center;
            margin: 10px auto 0 auto;
        }
        
        .avatar-selector, .avatar-grid {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 15px;
            width: 100%;
            margin-bottom: 10px;
        }
        
        .btn-page {
            font-family: var(--font-fun);
            font-weight: 800;
            font-size: 0.95rem;
            padding: 8px 16px;
            border: 2px solid #e2ebf5;
            border-radius: 9999px;
            background-color: white;
            color: var(--text-color);
            cursor: pointer;
            box-shadow: 0 3px 0px #cbd6e2;
            transition: transform 0.1s ease, box-shadow 0.1s ease;
            outline: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-page:active {
            transform: translateY(2px);
            box-shadow: 0 1px 0px #cbd6e2;
        }
        
        .btn-page:hover {
            background-color: #f8fafc;
            border-color: #cbd5e1;
        }
    </style>
</head>
<body>

    <div class="app-container">
        <div class="login-container">
            <!-- Bounce Animation Logo -->
            <div class="animated-logo">Classroom! 🎈</div>
            
            <div class="card" style="box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
                
                <?php if (!empty($error_message)): ?>
                    <div class="error_message error-bubble">
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <!-- Student Login UI Panel -->
                <div id="student_login_panel">
                    <h2 style="margin-bottom: 15px;">Tap Your Avatar!</h2>
                    
                    <!-- Grid Wrapper of Avatars (Paginated) -->
                    <div id="avatarGrid" class="avatar-grid">
                        <?php foreach ($students as $student): ?>
                            <div class="avatar-card avatar-btn" data-user-id="<?php echo $student['user_id']; ?>">
                                <!-- SVG or CSS Icon descriptor mapping -->
                                <div class="icon-avatar-<?php echo htmlspecialchars($student['avatar_url'] ?: 'monkey'); ?>"></div>
                                <span><?php echo htmlspecialchars(explode(' ', $student['full_name'])[0]); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Pagination controls -->
                    <div id="avatarPagination" style="display: flex; justify-content: center; gap: 20px; align-items: center; margin-top: 15px;">
                        <button id="prevAvatarBtn" class="btn-page" style="display: none;">⬅️ Back</button>
                        <button id="nextAvatarBtn" class="btn-page" style="display: none;">Next ➡️</button>
                    </div>
                    
                    <form id="student_login_form" method="POST" action="login.php">
                        <input type="hidden" name="login_type" value="student">
                        <input type="hidden" name="student_id" id="selected_student_id" value="">
                        
                        <!-- 4 PIN Entry Circles -->
                        <div class="pin-display">
                            <div class="pin-dot"></div>
                            <div class="pin-dot"></div>
                            <div class="pin-dot"></div>
                            <div class="pin-dot"></div>
                        </div>
                    </form>
                    
                    <!-- Custom Touch-friendly Keypad dialer -->
                    <div class="keypad">
                        <div class="keypad-btn" data-val="1">1</div>
                        <div class="keypad-btn" data-val="2">2</div>
                        <div class="keypad-btn" data-val="3">3</div>
                        <div class="keypad-btn" data-val="4">4</div>
                        <div class="keypad-btn" data-val="5">5</div>
                        <div class="keypad-btn" data-val="6">6</div>
                        <div class="keypad-btn" data-val="7">7</div>
                        <div class="keypad-btn" data-val="8">8</div>
                        <div class="keypad-btn" data-val="9">9</div>
                        <div class="keypad-btn action-btn" data-val="clear">Clear</div>
                        <div class="keypad-btn" data-val="0">0</div>
                        <div class="keypad-btn action-btn" data-val="back">⌫</div>
                    </div>
                    
                    <button type="button" class="toggle-btn" onclick="switchLogin('staff')">Teacher or Admin Login</button>
                </div>

                <!-- Staff Credentials UI Panel (hidden by default) -->
                <div id="staff_login_panel" style="display: none;">
                    <h2 style="margin-bottom: 20px; color: var(--text-color);">Teacher & Admin Login</h2>
                    
                    <form method="POST" action="login.php">
                        <input type="hidden" name="login_type" value="staff">
                        
                        <div class="staff-form-group">
                            <label for="email">School Email</label>
                            <input type="email" id="email" name="email" class="staff-input" placeholder="e.g. mr.davis@school.com">
                        </div>
                        
                        <div class="staff-form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" class="staff-input" placeholder="••••••••">
                        </div>
                        
                        <button type="submit" class="btn btn-blue" style="width: 100%; margin-top: 10px;">Sign In 🚀</button>
                    </form>
                    
                    <button type="button" class="toggle-btn" onclick="switchLogin('student')">Back to Student Login</button>
                </div>

            </div>
        </div>
    </div>

    <!-- Student Logic library dependency -->
    <script src="assets/js/student.js"></script>
    <script>
        function switchLogin(type) {
            const studentPanel = document.getElementById('student_login_panel');
            const staffPanel = document.getElementById('staff_login_panel');
            
            if (type === 'staff') {
                studentPanel.style.display = 'none';
                staffPanel.style.display = 'block';
            } else {
                studentPanel.style.display = 'block';
                staffPanel.style.display = 'none';
            }
        }
        
        // Student Avatar client-side pagination logic
        document.addEventListener('DOMContentLoaded', () => {
            const itemsPerPage = 8;
            let currentPage = 1;
            const avatars = document.querySelectorAll('.avatar-btn');
            const totalPages = Math.ceil(avatars.length / itemsPerPage);
            
            const prevBtn = document.getElementById('prevAvatarBtn');
            const nextBtn = document.getElementById('nextAvatarBtn');
            const paginationContainer = document.getElementById('avatarPagination');
            
            function showPage(page) {
                if (page < 1) page = 1;
                if (page > totalPages) page = totalPages;
                currentPage = page;
                
                const startIndex = (currentPage - 1) * itemsPerPage;
                const endIndex = startIndex + itemsPerPage;
                
                avatars.forEach((avatar, idx) => {
                    if (idx >= startIndex && idx < endIndex) {
                        avatar.style.display = 'flex';
                    } else {
                        avatar.style.display = 'none';
                    }
                });
                
                // Button visibility logic
                if (currentPage === 1) {
                    prevBtn.style.display = 'none';
                } else {
                    prevBtn.style.display = 'inline-block';
                }
                
                if (currentPage === totalPages || totalPages === 0) {
                    nextBtn.style.display = 'none';
                } else {
                    nextBtn.style.display = 'inline-block';
                }
                
                if (totalPages <= 1) {
                    paginationContainer.style.display = 'none';
                } else {
                    paginationContainer.style.display = 'flex';
                }
            }
            
            if (prevBtn && nextBtn) {
                prevBtn.addEventListener('click', () => {
                    if (currentPage > 1) {
                        showPage(currentPage - 1);
                    }
                });
                
                nextBtn.addEventListener('click', () => {
                    if (currentPage < totalPages) {
                        showPage(currentPage + 1);
                    }
                });
            }
            
            // Initial call
            showPage(1);
        });
    </script>
</body>
</html>
