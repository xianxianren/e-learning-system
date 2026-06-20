<?php
/**
 * review.php (teacher)
 * Grading and reward manager.
 * Displays completed student quiz scores and enables manual bonus points allocation.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Verify teacher permissions
require_role('teacher');

$teacher_name = $_SESSION['full_name'];

// 1. PHP Context Fetching (GET Parameter)
$target_student_id = filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT);
if (!$target_student_id) {
    // If not present, gracefully redirect to the roster
    header("Location: roster.php");
    exit;
}

try {
    // Fetch student's name to verify existence and use in the UI
    $stmt = $pdo->prepare("SELECT full_name FROM users WHERE user_id = ? AND role = 'student'");
    $stmt->execute([$target_student_id]);
    $student_name = $stmt->fetchColumn();

    if (!$student_name) {
        // Handle invalid student ID gracefully by redirecting
        header("Location: roster.php?msg=" . urlencode("Student not found."));
        exit;
    }
} catch (PDOException $e) {
    header("Location: roster.php?msg=" . urlencode("Database error loading student."));
    exit;
}

$alert_success = $_GET['msg'] ?? "";
$alert_danger = "";

// -------------------------------------------------------------------------
// Process POST: Award Bonus Points
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'award_bonus') {
    $student_id = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
    $bonus_points = filter_input(INPUT_POST, 'bonus_points', FILTER_VALIDATE_INT);

    if (!$student_id || $bonus_points === false || $bonus_points <= 0) {
        $alert_danger = "Please enter a positive bonus points value.";
    } else {
        try {
            // Use database transaction to ensure safety
            $pdo->beginTransaction();

            // Securely add the bonus XP
            $update_stmt = $pdo->prepare("
                UPDATE gamification_stats 
                SET total_points = total_points + ? 
                WHERE student_id = ?
            ");
            $update_stmt->execute([$bonus_points, $student_id]);

            $pdo->commit();

            // Redirect back to the student's review page with a success message
            header("Location: review.php?student_id=" . $student_id . "&msg=" . urlencode("Successfully awarded {$bonus_points} Gold Stars to {$student_name}! ⭐"));
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $alert_danger = "Failed to award points: " . $e->getMessage();
        }
    }
}

// -------------------------------------------------------------------------
// Load Active Student Detail & Quiz Scores
// -------------------------------------------------------------------------
$active_student = null;
$student_quiz_scores = [];

try {
    // Load student profile details
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.full_name, u.class_section, u.avatar_url, COALESCE(gs.total_points, 0) AS total_points
        FROM users u
        LEFT JOIN gamification_stats gs ON u.user_id = gs.student_id
        WHERE u.user_id = ? AND u.role = 'student'
    ");
    $stmt->execute([$target_student_id]);
    $active_student = $stmt->fetch();

    if ($active_student) {
        // Load their quiz scores
        $scores_stmt = $pdo->prepare("
            SELECT qs.*, q.total_marks, l.title AS lesson_title,
                   GROUP_CONCAT(DISTINCT questions.question_type SEPARATOR ', ') AS quiz_types
            FROM quiz_scores qs
            LEFT JOIN quizzes q ON qs.quiz_id = q.quiz_id
            LEFT JOIN lessons l ON q.lesson_id = l.lesson_id
            LEFT JOIN questions ON q.quiz_id = questions.quiz_id
            WHERE qs.student_id = ?
            GROUP BY qs.score_id
            ORDER BY qs.completed_at DESC
        ");
        $scores_stmt->execute([$target_student_id]);
        $student_quiz_scores = $scores_stmt->fetchAll();
    }
} catch (PDOException $e) {
    $alert_danger = "Failed to load student quiz history.";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grading & Rewards - Interactive E-Learning System</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>

<body>

    <!-- Left Sidebar Menu -->
    <div class="admin-sidebar">
        <div class="sidebar-brand">
            🎓 Teacher Console
        </div>
        <ul class="sidebar-menu">
            <li class="sidebar-menu-item">
                <a href="dashboard.php" class="sidebar-link">📊 Dashboard</a>
            </li>
            <li class="sidebar-menu-item">
                <a href="roster.php" class="sidebar-link">👥 Student Roster</a>
            </li>
            <li class="sidebar-menu-item">
                <a href="curriculum.php" class="sidebar-link">📖 Curriculum Manager</a>
            </li>
            <li class="sidebar-menu-item">
                <a href="quizzes.php" class="sidebar-link">📝 Quiz Builder</a>
            </li>
        </ul>
        <div class="sidebar-footer">
            Logged in as:<br>
            <strong><?php echo htmlspecialchars($teacher_name); ?></strong>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="admin-main">

        <!-- Top bar navigation -->
        <div class="admin-topbar">
            <div class="topbar-title">Grading & Gamification Control</div>
            <div class="topbar-user">
                <span class="user-name">Welcome, <?php echo htmlspecialchars($teacher_name); ?></span>
                <span class="user-role-badge">Teacher</span>
                <a href="../auth/logout.php" class="btn-logout">Logout</a>
            </div>
        </div>

        <!-- Main content layout -->
        <div class="admin-content">

            <div style="margin-bottom: 20px;">
                <a href="roster.php" class="btn-sm" style="margin-top: 20px; display: inline-block;">
                    ⬅ Back to Student Roster
                </a>
            </div>

            <?php if (!empty($alert_success)): ?>
                <div class="alert-success"><?php echo htmlspecialchars($alert_success); ?></div>
            <?php endif; ?>
            <?php if (!empty($alert_danger)): ?>
                <div class="alert-danger"><?php echo htmlspecialchars($alert_danger); ?></div>
            <?php endif; ?>

            <div class="admin-grid-two-col">

                <!-- Left Column: Individual Student Scores & Profiles -->
                <div>
                    <?php if ($active_student): ?>

                        <!-- Student profile summary card -->
                        <div class="admin-card">
                            <div class="admin-card-title">
                                👦 Student Detail: <?php echo htmlspecialchars($active_student['full_name']); ?>
                            </div>
                            <div style="display: flex; gap: 20px; align-items: center;">
                                <div class="data-table-avatar"
                                    style="width: 60px; height: 60px; font-size: 2.2rem; border-radius: 50%;">
                                    <?php
                                    $avatar = $active_student['avatar_url'] ?: 'monkey';
                                    $emoji = '🐵';
                                    if ($avatar === 'bunny')
                                        $emoji = '🐰';
                                    elseif ($avatar === 'panda')
                                        $emoji = '🐼';
                                    elseif ($avatar === 'fox')
                                        $emoji = '🦊';
                                    echo $emoji;
                                    ?>
                                </div>
                                <div>
                                    <h3 style="font-size: 1.2rem;">
                                        <?php echo htmlspecialchars($active_student['full_name']); ?>
                                    </h3>
                                    <p style="color: var(--text-secondary); margin-top: 4px;">Class:
                                        <?php echo htmlspecialchars($active_student['class_section'] ?: 'Not Assigned'); ?>
                                    </p>
                                    <p style="color: var(--warning-color); font-weight: 700; margin-top: 4px;">⭐ Current
                                        Reward: <?php echo $active_student['total_points']; ?> Stars</p>
                                </div>
                            </div>
                        </div>

                        <!-- Quiz history data table -->
                        <div class="admin-card">
                            <div class="admin-card-title">Completed Quiz Scores</div>

                            <?php if (empty($student_quiz_scores)): ?>
                                <p style="color: var(--text-secondary);">This student has not submitted any quiz answers yet.
                                </p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th>Lesson / Module</th>
                                                <th>Quiz Type(s)</th>
                                                <th>Score Received</th>
                                                <th>Percentile</th>
                                                <th>Submitted Time</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($student_quiz_scores as $score):
                                                $percentage = round(($score['marks_earned'] / $score['total_marks']) * 100);
                                                $score_color = ($percentage >= 80) ? 'var(--success-color)' : (($percentage >= 50) ? 'var(--warning-color)' : 'var(--danger-color)');
                                                ?>
                                                <tr>
                                                    <td><strong><?php echo htmlspecialchars($score['lesson_title']); ?></strong>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $raw_types = $score['quiz_types'] ?: 'N/A';
                                                        $formatted_types = ucwords(str_replace('_', ' ', $raw_types));
                                                        echo htmlspecialchars($formatted_types);
                                                        ?>
                                                    </td>
                                                    <td style="color: <?php echo $score_color; ?>; font-weight: 700;">
                                                        <?php echo $score['marks_earned']; ?> / <?php echo $score['total_marks']; ?>
                                                    </td>
                                                    <td><strong><?php echo $percentage; ?>%</strong></td>
                                                    <td style="font-size: 0.85rem; color: var(--text-muted);">
                                                        <?php echo date('M d, Y - g:i a', strtotime($score['completed_at'])); ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>

                    <?php endif; ?>
                </div>

                <!-- Right Column: Manual Bonus Rewards Form Panel -->
                <div class="admin-card">
                    <div class="admin-card-title">Award Bonus Points (XP)</div>

                    <form method="POST"
                        action="review.php?student_id=<?php echo htmlspecialchars($target_student_id); ?>">
                        <input type="hidden" name="action" value="award_bonus">
                        <input type="hidden" name="student_id"
                            value="<?php echo htmlspecialchars($target_student_id); ?>">

                        <h3 style="font-size: 1.15rem; margin-bottom: 20px; color: var(--text-color);">
                            Award Bonus XP to: <strong
                                style="color: var(--color-purple);"><?php echo htmlspecialchars($student_name); ?></strong>
                        </h3>

                        <div class="form-group">
                            <label class="form-label" for="bonus_points">Bonus Gold Stars to Award</label>
                            <input type="number" id="bonus_points" name="bonus_points" class="form-control"
                                placeholder="Enter XP amount" min="1" required>
                            <span
                                style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px; display: block;">These
                                stars will be added to their cumulative points total.</span>
                        </div>

                        <button type="submit" class="form-control btn-solid-blue"
                            style="font-weight: 700; cursor: pointer; margin-top: 15px; height: 42px; background-color: var(--success-color);">
                            Award Bonus Stars! ⭐
                        </button>
                    </form>
                </div>

            </div>

        </div>
    </div>

</body>

</html>