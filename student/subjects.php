<?php
/**
 * subjects.php (student)
 * Displays a 2-column grid containing all academic subjects.
 * Calculates completion percentages dynamically per subject and draws bright progress bars.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Verify student permission role
require_role('student');

$student_id = $_SESSION['user_id'];
$avatar_url = $_SESSION['avatar_url'] ?: 'monkey';

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

// Fetch all subjects and calculate progression rates
$subjects_list = [];
try {
    // Fetch subjects
    $subjects_stmt = $pdo->query("SELECT subject_id, subject_name, icon_url FROM subjects");
    $raw_subjects = $subjects_stmt->fetchAll();
    
    foreach ($raw_subjects as $subj) {
        $subject_id = $subj['subject_id'];
        
        // Count total lessons under this subject
        $total_stmt = $pdo->prepare("SELECT COUNT(*) FROM lessons WHERE subject_id = ?");
        $total_stmt->execute([$subject_id]);
        $total_lessons = $total_stmt->fetchColumn();
        
        // Count completed lessons by this student
        $completed_stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM student_progress sp 
            JOIN lessons l ON sp.lesson_id = l.lesson_id 
            WHERE l.subject_id = ? AND sp.student_id = ? AND sp.status = 'completed'
        ");
        $completed_stmt->execute([$subject_id, $student_id]);
        $completed_lessons = $completed_stmt->fetchColumn();
        
        // Calculate percentage
        $percentage = 0;
        if ($total_lessons > 0) {
            $percentage = round(($completed_lessons / $total_lessons) * 100);
        }
        
        // Determine theme colors based on subject ID or name
        if ($subject_id % 2 === 0) {
            $theme_color = 'var(--color-success)';
            $fill_color = '#3ec770';
        } else {
            $theme_color = 'var(--color-blue)';
            $fill_color = '#3a86ff';
        }
        
        $subjects_list[] = [
            'id' => $subject_id,
            'name' => $subj['subject_name'],
            'icon' => $subj['icon_url'] ?: 'math-icon',
            'percentage' => $percentage,
            'theme_color' => $theme_color,
            'fill_color' => $fill_color,
            'total_lessons' => $total_lessons
        ];
    }
} catch (PDOException $e) {
    // Handle error gracefully
    $error_msg = "Could not fetch subjects. Ask your teacher for assistance!";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Subjects! 📚</title>
    <link rel="stylesheet" href="../assets/css/student.css">
    <style>
        .category-filter {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .filter-chip {
            background-color: white;
            border: 2px solid #e2ebf5;
            border-radius: var(--radius-large);
            padding: 8px 16px;
            font-size: 0.9rem;
            font-weight: 800;
            color: var(--text-color);
            cursor: pointer;
            box-shadow: 0 3px 0px #cbd6e2;
            transition: all 0.1s ease;
        }
        .filter-chip.active {
            background-color: var(--color-purple);
            color: white;
            border-color: var(--color-purple);
            box-shadow: 0 3px 0px #6b26cf;
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
                <div class="student-name">My Subjects</div>
            </div>
            <div class="xp-badge">
                <span class="icon-points"></span>
                <span><?php echo $points; ?> Stars</span>
            </div>
        </div>

        <!-- Main Container -->
        <div class="container-mobile">

            <!-- Subject selection cards grid -->
            <h2 style="margin-bottom: 12px; font-size: 1.3rem;">Choose a Subject to Play!</h2>
            
            <?php if (isset($error_msg)): ?>
                <div class="card" style="border-color: var(--color-primary); color: #d43b3b; text-align: center;">
                    <p><strong><?php echo $error_msg; ?></strong></p>
                </div>
            <?php endif; ?>

            <div class="subjects-grid">
                <?php foreach ($subjects_list as $subject): ?>
                    <!-- Card triggers navigation to lessons list -->
                    <div class="subject-card" <?php if ($subject['total_lessons'] > 0): ?>onclick="window.location.href='course.php?subject_id=<?php echo $subject['id']; ?>'"<?php endif; ?> style="border-color: <?php echo $subject['theme_color']; ?>; border-width: 4px; position: relative; <?php if ($subject['total_lessons'] == 0) echo 'opacity: 0.8; cursor: default;'; ?>">
                        
                        <?php if ($subject['total_lessons'] == 0): ?>
                            <span class="coming-soon-badge" style="position: absolute; top: 10px; right: 10px; background-color: var(--color-purple); color: white; padding: 4px 10px; border-radius: 9999px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">Coming Soon!</span>
                        <?php endif; ?>
                        
                        <!-- Friendly Icons based on configuration name -->
                        <?php 
                            $iconClass = 'icon-math';
                            if (strpos(strtolower($subject['name']), 'science') !== false) {
                                $iconClass = 'icon-science';
                            } elseif (strpos(strtolower($subject['name']), 'english') !== false) {
                                $iconClass = 'icon-english';
                            }
                        ?>
                        <div class="subject-icon <?php echo $iconClass; ?>"></div>
                        
                        <div class="subject-title"><?php echo htmlspecialchars($subject['name']); ?></div>
                        
                        <!-- Custom progress bar showing completion -->
                        <div class="subject-progress-wrapper">
                            <div class="progress-bar-container">
                                <div class="progress-bar-fill" style="width: <?php echo $subject['percentage']; ?>%; background-color: <?php echo $subject['fill_color']; ?>;"></div>
                            </div>
                            <div class="progress-percentage-label">
                                <?php echo $subject['percentage']; ?>% Done
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

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

    <!-- Student Logic library dependency -->
    <script src="../assets/js/student.js"></script>
</body>
</html>
