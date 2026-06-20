<?php
/**
 * dashboard.php (teacher)
 * Teacher home portal. Displays quick metrics cards (Total Students, Completed Lessons,
 * Average Score) and a live student learning activity log.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Verify teacher permissions
require_role('teacher');

$teacher_name = $_SESSION['full_name'];

// 1. Fetch Stats metrics
$total_students = 0;
$pending_grades = 0;
$average_score = 0;

try {
    // Total Students Roster count
    $total_students = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND username NOT LIKE 'class_placeholder_%'")->fetchColumn();
    
    // Lessons completed but quiz not yet taken
    $pending_query = "
        SELECT COUNT(*) 
        FROM student_progress sp
        JOIN lessons l ON sp.lesson_id = l.lesson_id
        JOIN quizzes q ON l.lesson_id = q.lesson_id
        LEFT JOIN quiz_scores qs ON q.quiz_id = qs.quiz_id AND sp.student_id = qs.student_id
        WHERE sp.status = 'completed' AND qs.score_id IS NULL
    ";
    $pending_grades = $pdo->query($pending_query)->fetchColumn();
    
    // Average Class Score calculation (out of average max score)
    $avg_query = "
        SELECT COALESCE(AVG(qs.marks_earned / q.total_marks * 100), 0)
        FROM quiz_scores qs
        JOIN quizzes q ON qs.quiz_id = q.quiz_id
    ";
    $average_score = round($pdo->query($avg_query)->fetchColumn(), 1);
    
} catch (PDOException $e) {
    // Handle error gracefully
    $db_error = "Could not load stats metrics.";
}

// 2. Fetch Recent student activities feed
$recent_activities = [];
try {
    $feed_query = "
        SELECT u.full_name, u.avatar_url, l.title, sp.status, sp.last_accessed 
        FROM student_progress sp
        JOIN users u ON sp.student_id = u.user_id
        JOIN lessons l ON sp.lesson_id = l.lesson_id
        ORDER BY sp.last_accessed DESC
        LIMIT 5
    ";
    $recent_activities = $pdo->query($feed_query)->fetchAll();
} catch (PDOException $e) {
    // Fail silently
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - Interactive E-Learning System</title>
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
                <a href="dashboard.php" class="sidebar-link active">📊 Dashboard</a>
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
            <div class="topbar-title">Dashboard Overview</div>
            <div class="topbar-user">
                <span class="user-name">Welcome, <?php echo htmlspecialchars($teacher_name); ?></span>
                <span class="user-role-badge">Teacher</span>
                <a href="../auth/logout.php" class="btn-logout">Logout</a>
            </div>
        </div>
        
        <!-- Main content layout -->
        <div class="admin-content">
            
            <?php if (isset($db_error)): ?>
                <div class="alert-danger"><?php echo htmlspecialchars($db_error); ?></div>
            <?php endif; ?>

            <!-- Metrics Statistics Cards -->
            <div class="dashboard-stats-grid">
                <div class="stat-card">
                    <div class="stat-card-info">
                        <h3>Total Students</h3>
                        <div class="stat-card-value"><?php echo $total_students; ?></div>
                    </div>
                    <div class="stat-card-icon icon-blue">👥</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-info">
                        <h3>Pending Quizzes</h3>
                        <div class="stat-card-value"><?php echo $pending_grades; ?></div>
                    </div>
                    <div class="stat-card-icon icon-orange">📝</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-info">
                        <h3>Average Score</h3>
                        <div class="stat-card-value"><?php echo $average_score; ?>%</div>
                    </div>
                    <div class="stat-card-icon icon-green">📈</div>
                </div>
            </div>

            <!-- Recent Activity card -->
            <div class="admin-card">
                <div class="admin-card-title">Recent Student Activities</div>
                
                <?php if (empty($recent_activities)): ?>
                    <p style="color: var(--text-secondary); font-size: 0.95rem;">No student activity recorded yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Lesson</th>
                                    <th>Action</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_activities as $activity): ?>
                                    <tr>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <div class="data-table-avatar">
                                                    <?php 
                                                        $avatar = $activity['avatar_url'] ?: 'monkey';
                                                        $emoji = '🐵';
                                                        if ($avatar === 'bunny') $emoji = '🐰';
                                                        elseif ($avatar === 'panda') $emoji = '🐼';
                                                        elseif ($avatar === 'fox') $emoji = '🦊';
                                                        echo $emoji;
                                                    ?>
                                                </div>
                                                <strong><?php echo htmlspecialchars($activity['full_name']); ?></strong>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($activity['title']); ?></td>
                                        <td>
                                            <?php if ($activity['status'] === 'completed'): ?>
                                                <span class="user-role-badge" style="background-color: #ecfdf5; color: var(--success-color);">Finished</span>
                                            <?php else: ?>
                                                <span class="user-role-badge" style="background-color: #eff6ff; color: var(--accent-color);">In Progress</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span style="font-size: 0.85rem; color: var(--text-muted);">
                                                <?php echo date('M d, g:i a', strtotime($activity['last_accessed'])); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

</body>
</html>
