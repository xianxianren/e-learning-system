<?php
/**
 * dashboard.php (student)
 * Playful, mobile-first dashboard displaying student greeting, current gold star XP,
 * weekly login streaks, and a massive call-to-action button to continue their last lesson.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Verify student permission role
require_role('student');

$student_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$first_name = explode(' ', $full_name)[0];
$avatar_url = $_SESSION['avatar_url'] ?: 'monkey';

// 1. Fetch Gamification points and streak details
$points = 0;
$streak = 0;
try {
    $stmt = $pdo->prepare("SELECT total_points, login_streak FROM gamification_stats WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $stats = $stmt->fetch();
    if ($stats) {
        $points = $stats['total_points'];
        $streak = $stats['login_streak'];
    }
} catch (PDOException $e) {
    // Fail silently in terms of layout, default to 0
}

// 2. Fetch the "Continue Lesson" detail
$continue_lesson = null;
try {
    // Try to find the latest "in_progress" lesson
    $stmt = $pdo->prepare("
        SELECT l.lesson_id, l.title, s.subject_name 
        FROM student_progress sp
        JOIN lessons l ON sp.lesson_id = l.lesson_id
        JOIN subjects s ON l.subject_id = s.subject_id
        WHERE sp.student_id = ? AND sp.status = 'in_progress'
        ORDER BY sp.last_accessed DESC
        LIMIT 1
    ");
    $stmt->execute([$student_id]);
    $continue_lesson = $stmt->fetch();
    
    // If no in_progress lesson exists, find the first uncompleted lesson
    if (!$continue_lesson) {
        $stmt = $pdo->prepare("
            SELECT l.lesson_id, l.title, s.subject_name 
            FROM lessons l
            JOIN subjects s ON l.subject_id = s.subject_id
            WHERE l.lesson_id NOT IN (
                SELECT lesson_id FROM student_progress WHERE student_id = ? AND status = 'completed'
            )
            ORDER BY s.subject_id ASC, l.order_num ASC
            LIMIT 1
        ");
        $stmt->execute([$student_id]);
        $continue_lesson = $stmt->fetch();
    }
    
    // Fallback if they completed everything or nothing is assigned
    if (!$continue_lesson) {
        $stmt = $pdo->prepare("
            SELECT l.lesson_id, l.title, s.subject_name 
            FROM lessons l
            JOIN subjects s ON l.subject_id = s.subject_id
            ORDER BY s.subject_id ASC, l.order_num ASC
            LIMIT 1
        ");
        $stmt->execute();
        $continue_lesson = $stmt->fetch();
    }
} catch (PDOException $e) {
    // Fail silently
}

// 3. Fetch student's next incomplete lesson for the Daily Challenge
$challenge_url = "subjects.php";
try {
    $stmt = $pdo->prepare("SELECT lesson_id FROM student_progress WHERE student_id = ? AND status != 'completed' ORDER BY progress_id ASC LIMIT 1");
    $stmt->execute([$student_id]);
    $next_lesson_id = $stmt->fetchColumn();
    
    if ($next_lesson_id) {
        $challenge_url = "lesson.php?lesson_id=" . $next_lesson_id;
    } else {
        // Fallback: Check if there's any lesson in database that student hasn't completed
        $stmt = $pdo->prepare("
            SELECT lesson_id 
            FROM lessons 
            WHERE lesson_id NOT IN (
                SELECT lesson_id FROM student_progress WHERE student_id = ? AND status = 'completed'
            )
            ORDER BY lesson_id ASC
            LIMIT 1
        ");
        $stmt->execute([$student_id]);
        $unstarted_lesson_id = $stmt->fetchColumn();
        if ($unstarted_lesson_id) {
            $challenge_url = "lesson.php?lesson_id=" . $unstarted_lesson_id;
        } else {
            $challenge_url = "subjects.php";
        }
    }
} catch (PDOException $e) {
    $challenge_url = "subjects.php";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Fun Dashboard! 🚀</title>
    <link rel="stylesheet" href="../assets/css/student.css">
    <style>
        .daily-challenge-card {
            cursor: pointer;
            transition: all 0.2s ease-in-out;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        .daily-challenge-card:hover, .daily-challenge-card:focus {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 8px 16px rgba(58, 134, 255, 0.15);
        }
    </style>
</head>
<body>

    <div class="app-container">
        <!-- Header Panel -->
        <div class="student-header">
            <div class="student-info">
                <div class="student-avatar-small">
                    <div class="icon-avatar-<?php echo htmlspecialchars($avatar_url); ?>"></div>
                </div>
                <div class="student-name">Hi, <?php echo htmlspecialchars($first_name); ?>! 👋</div>
            </div>
            <div class="xp-badge">
                <span class="icon-points"></span>
                <span><?php echo $points; ?> Stars</span>
            </div>
        </div>

        <!-- Main Container -->
        <div class="container-mobile">
            
            <!-- Welcome banner card -->
            <div class="card" style="border-color: var(--color-success); border-width: 4px; text-align: center;">
                <h2 style="font-size: 1.5rem; color: var(--color-success); margin-bottom: 5px;">Let's Learn Today! 🎨</h2>
                <p style="color: var(--text-muted); font-size: 0.95rem;">Tap on your buttons to play and win rewards!</p>
            </div>

            <!-- Continue Lesson Action Card -->
            <?php if ($continue_lesson): ?>
                <div class="card continue-card">
                    <h3>Continue Your Lesson!</h3>
                    <p><?php echo htmlspecialchars($continue_lesson['subject_name']); ?> • <?php echo htmlspecialchars($continue_lesson['title']); ?></p>
                    <a href="lesson.php?id=<?php echo $continue_lesson['lesson_id']; ?>" class="btn btn-secondary" style="width: 100%; border-radius: 12px; font-size: 1.2rem;">
                        ▶️ Play Lesson!
                    </a>
                </div>
            <?php else: ?>
                <div class="card" style="background-color: #f0f7ff; text-align: center;">
                    <h3 style="margin-bottom: 8px;">No lessons found!</h3>
                    <p style="color: var(--text-muted); margin-bottom: 12px;">Ask Mr. Davis to add some subjects.</p>
                </div>
            <?php endif; ?>

            <!-- Weekly Streak Card -->
            <div class="card">
                <h3 style="display: flex; align-items: center; gap: 8px;">
                    <span class="icon-streak"></span> 
                    <span>Your Streak: <?php echo $streak; ?> Days!</span>
                </h3>
                <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 5px;">Keep learning every day to keep the flame alive!</p>
                
                <!-- Streak Row representation -->
                <div class="streak-row">
                    <?php 
                    // Draw 7 stars, highlighting matching days up to streak length
                    for ($i = 1; $i <= 7; $i++): 
                        $isActive = ($i <= $streak);
                    ?>
                        <div class="streak-day <?php echo $isActive ? 'active' : ''; ?>">
                            <div class="streak-star">⭐</div>
                            <div style="margin-top: 4px;">Day <?php echo $i; ?></div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
            
            <!-- Daily Challenge Card -->
            <div class="card daily-challenge-card" onclick="window.location.href='<?php echo $challenge_url; ?>'" style="border-color: var(--color-blue); display: flex; align-items: center; justify-content: space-between; gap: 10px;">
                <div>
                    <h4 style="color: var(--color-blue); font-size: 1.1rem;">Daily Challenge! 🏆</h4>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 4px;">Complete 1 quiz to win 25 bonus stars.</p>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div class="xp-badge" style="background-color: var(--color-blue); color: white; box-shadow: 0 4px 0px #1d5fc6; padding: 4px 10px; font-size: 0.8rem; margin: 0;">
                        +25 XP
                    </div>
                    <span class="challenge-play-indicator" style="font-weight: 800; font-size: 0.85rem; color: var(--color-blue); display: flex; align-items: center; gap: 2px;">
                        Play Now ▶
                    </span>
                </div>
            </div>

        </div>

        <!-- Sticky Bottom Navigation Footer -->
        <div class="bottom-nav">
            <a href="dashboard.php" class="bottom-nav-item active">
                <div class="bottom-nav-icon">🏠</div>
                <div>Home</div>
            </a>
            <a href="subjects.php" class="bottom-nav-item">
                <div class="bottom-nav-icon">✏️</div>
                <div>Subjects</div>
            </a>
            <a href="class_leaderboard.php" class="bottom-nav-item">
                <div class="bottom-nav-icon">🏆</div>
                <div>Leaderboard</div>
            </a>
            <a href="../auth/logout.php" class="bottom-nav-item">
                <div class="bottom-nav-icon">🚪</div>
                <div>Exit</div>
            </a>
        </div>
    </div>

    <!-- Web Audio API SFX instance -->
    <script src="../assets/js/audio.js"></script>
    <!-- General Student Logic dependency -->
    <script src="../assets/js/student.js"></script>
</body>
</html>
