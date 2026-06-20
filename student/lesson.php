<?php
/**
 * lesson.php (student)
 * Renders an active lesson workspace.
 * Displays a custom-controlled HTML5 video player and a quiz modal,
 * which remains locked until the student finishes watching the video.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Verify student permission role
require_role('student');

$student_id = $_SESSION['user_id'];
$avatar_url = $_SESSION['avatar_url'] ?: 'monkey';

$lesson_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_GET, 'lesson_id', FILTER_VALIDATE_INT);
if (!$lesson_id) {
    header("Location: subjects.php");
    exit;
}

// 1. Fetch Lesson details
try {
    $stmt = $pdo->prepare("
        SELECT l.*, s.subject_name 
        FROM lessons l 
        JOIN subjects s ON l.subject_id = s.subject_id 
        WHERE l.lesson_id = ?
    ");
    $stmt->execute([$lesson_id]);
    $lesson = $stmt->fetch();
    
    if (!$lesson) {
        header("Location: subjects.php");
        exit;
    }
    
    // Set status to in_progress if not already started
    $progStmt = $pdo->prepare("SELECT status FROM student_progress WHERE student_id = ? AND lesson_id = ?");
    $progStmt->execute([$student_id, $lesson_id]);
    $current_status = $progStmt->fetchColumn();
    
    if (!$current_status) {
        $startProg = $pdo->prepare("INSERT INTO student_progress (student_id, lesson_id, status) VALUES (?, ?, 'in_progress')");
        $startProg->execute([$student_id, $lesson_id]);
    }
} catch (PDOException $e) {
    die("Error retrieving lesson content.");
}

// Fetch XP points for the header
$points = 0;
try {
    $stmt = $pdo->prepare("SELECT total_points FROM gamification_stats WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $stats = $stmt->fetch();
    if ($stats) {
        $points = $stats['total_points'];
    }
} catch (PDOException $e) {
    // Silent
}

// 2. Fetch Quiz details linked to this lesson
$has_quiz = false;
$quiz_id = null;
try {
    $stmt = $pdo->prepare("SELECT quiz_id FROM quizzes WHERE lesson_id = ? LIMIT 1");
    $stmt->execute([$lesson_id]);
    $quiz_row = $stmt->fetch();
    if ($quiz_row) {
        $has_quiz = true;
        $quiz_id = $quiz_row['quiz_id'];
    }
} catch (PDOException $e) {
    // Silent
}

// Custom check: if lesson progress is already completed, the student can take the quiz immediately!
$isAlreadyCompleted = ($current_status === 'completed');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Learning: <?php echo htmlspecialchars($lesson['title']); ?> 🎬</title>
    <link rel="stylesheet" href="../assets/css/student.css">
    <style>
        .back-btn {
            background-color: white;
            border: 2px solid #e2ebf5;
            border-radius: var(--radius-large);
            padding: 5px 12px;
            font-size: 0.9rem;
            font-weight: 800;
            color: var(--text-color);
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            box-shadow: 0 3px 0px #cbd6e2;
        }
        .back-btn:active {
            transform: translateY(2px);
            box-shadow: 0 1px 0px #cbd6e2;
        }
        .btn-disabled {
            background-color: #e2ebf5 !important;
            border: 2px solid #cbd6e2 !important;
            color: #8fa0b5 !important;
            cursor: not-allowed;
            box-shadow: none !important;
            transform: none !important;
            pointer-events: none; /* prevents hover/active animations */
        }
    </style>
</head>
<body>

    <div class="app-container">
        <!-- Header Panel -->
        <div class="student-header">
            <div class="student-info">
                <a href="course.php?subject_id=<?php echo $lesson['subject_id']; ?>" class="back-btn">⬅️ Subjects</a>
            </div>
            <div class="xp-badge">
                <span class="icon-points"></span>
                <span><?php echo $points; ?> Stars</span>
            </div>
        </div>

        <!-- Main Container -->
        <div class="container-mobile">
            
            <h2 style="font-size: 1.4rem; margin-top: 10px; color: var(--text-color);"><?php echo htmlspecialchars($lesson['title']); ?></h2>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 12px;"><?php echo htmlspecialchars($lesson['subject_name']); ?></p>

            <!-- Playable Video Area -->
            <div class="lesson-video-container">
                <?php if (!empty($lesson['video_url'])): ?>
                    <!-- HTML5 Video Player without native controls -->
                    <video id="lesson_video" class="lesson-video" preload="metadata">
                        <source src="../<?php echo htmlspecialchars($lesson['video_url']); ?>" type="video/mp4">
                        Your browser does not support HTML5 video player.
                    </video>
                <?php else: ?>
                    <!-- Video missing/fallback layout -->
                    <div style="color: white; padding: 40px 20px; text-align: center; font-weight: 800;">
                        📖 Read the lesson material below!
                    </div>
                <?php endif; ?>
            </div>

            <!-- Custom Video Control buttons (Active only if video exists) -->
            <?php if (!empty($lesson['video_url'])): ?>
                <div class="video-controls">
                    <button type="button" id="rewind_btn" class="ctrl-btn" title="Rewind 10 seconds">⏪<span class="rewind-label">10s</span></button>
                    <button type="button" id="play_pause_btn" class="ctrl-btn" title="Play/Pause">▶️</button>
                </div>
            <?php endif; ?>

            <!-- Educational text material -->
            <div class="lesson-content">
                <h3 style="margin-bottom: 8px; color: var(--color-purple);">Teacher's Notes 📝</h3>
                <p style="line-height: 1.5; font-size: 1rem; color: #475a6e;">
                    Welcome! Watch the video above to learn, or tap the button below to take the fun quiz challenge and earn Gold Stars!
                </p>
            </div>

            <!-- Action Quiz Button (Always unlocked) -->
            <input type="hidden" id="lesson_id" value="<?php echo $lesson_id; ?>">
            <?php if ($has_quiz): ?>
                <button onclick="window.location.href='student_quiz.php?quiz_id=<?php echo $quiz_id; ?>'" id="take_quiz_btn" class="btn btn-primary" style="display: block; width: 100%; font-size: 1.2rem; padding: 15px 0; margin-bottom: 30px; cursor: pointer;">
                    📝 Take the Quiz Now!
                </button>
            <?php else: ?>
                <button class="btn btn-disabled" style="width: 100%; font-size: 1.1rem; padding: 15px 0; margin-bottom: 30px;" disabled>
                    🎉 No Quiz for this Lesson!
                </button>
            <?php endif; ?>

        </div>

        <!-- Sticky Bottom Navigation Footer -->
        <div class="bottom-nav">
            <a href="dashboard.php" class="bottom-nav-item">
                <div class="bottom-nav-icon">🏠</div>
                <div>Home</div>
            </a>
            <a href="subjects.php" class="bottom-nav-item active">
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
    <!-- General Student logic engine -->
    <script src="../assets/js/student.js"></script>
</body>
</html>
