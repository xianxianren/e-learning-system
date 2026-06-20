<?php
/**
 * system_reports.php (admin)
 * System Reports and Leaderboards.
 * Displays class ranking metrics and downloads dynamic CSV spreadsheet summaries.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Verify admin permissions
require_role('admin');

$admin_name = $_SESSION['full_name'];

// -------------------------------------------------------------------------
// CSV File Exporter Request Handler
// -------------------------------------------------------------------------
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Clear any previous output buffers to avoid corrupting CSV files
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=gamification_leaderboard_' . date('Ymd') . '.csv');
    
    $output = fopen('php://output', 'w');
    
    // Add Column Names
    fputcsv($output, ['Rank', 'Student Name', 'Class Section', 'Gold Stars (XP)', 'Login Streak (Days)']);
    
    try {
        $stmt = $pdo->query("
            SELECT u.full_name, u.class_section, gs.total_points, gs.login_streak
            FROM users u
            JOIN gamification_stats gs ON u.user_id = gs.student_id
            WHERE u.role = 'student'
            ORDER BY gs.total_points DESC, gs.login_streak DESC
        ");
        
        $rank = 1;
        while ($row = $stmt->fetch()) {
            fputcsv($output, [
                $rank++,
                $row['full_name'],
                $row['class_section'] ?: 'Not Assigned',
                $row['total_points'],
                $row['login_streak']
            ]);
        }
    } catch (PDOException $e) {
        fputcsv($output, ['Error generating system report', $e->getMessage()]);
    }
    
    fclose($output);
    exit;
}

// -------------------------------------------------------------------------
// Load Top 10 Student Leaderboard
// -------------------------------------------------------------------------
$top_students = [];
try {
    $leader_query = "
        SELECT u.full_name, u.class_section, u.avatar_url, gs.total_points, gs.login_streak
        FROM users u
        JOIN gamification_stats gs ON u.user_id = gs.student_id
        WHERE u.role = 'student'
        ORDER BY gs.total_points DESC, gs.login_streak DESC
        LIMIT 10
    ";
    $top_students = $pdo->query($leader_query)->fetchAll();
} catch (PDOException $e) {
    $error_msg = "Could not connect and load ranking logs.";
}

// -------------------------------------------------------------------------
// Load Class Aggregates
// -------------------------------------------------------------------------
$class_reports = [];
try {
    $class_query = "
        SELECT u.class_section, 
               COUNT(u.user_id) AS student_count,
               SUM(gs.total_points) AS total_stars,
               ROUND(AVG(gs.total_points)) AS average_stars
        FROM users u
        JOIN gamification_stats gs ON u.user_id = gs.student_id
        WHERE u.role = 'student' AND u.class_section IS NOT NULL AND u.class_section != ''
        GROUP BY u.class_section
        ORDER BY total_stars DESC
    ";
    $class_reports = $pdo->query($class_query)->fetchAll();
} catch (PDOException $e) {
    // Fail silently
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Reports - Interactive E-Learning System</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>

    <!-- Admin Left Sidebar Menu -->
    <div class="admin-sidebar">
        <div class="sidebar-header">
            ⚙️ System Admin
        </div>
        <ul class="sidebar-nav">
            <li class="sidebar-nav-item">
                <a href="dashboard.php" class="sidebar-nav-link">📊 System Metrics</a>
            </li>
            <li class="sidebar-nav-item">
                <a href="manage_users.php" class="sidebar-nav-link">👥 User Accounts</a>
            </li>
            <li class="sidebar-nav-item">
                <a href="manage_curriculum.php" class="sidebar-nav-link">📖 Master Curriculum</a>
            </li>
            <li class="sidebar-nav-item">
                <a href="system_reports.php" class="sidebar-nav-link active">📋 System Reports</a>
            </li>
        </ul>
        <div class="sidebar-footer">
            Logged in as:<br>
            <strong><?php echo htmlspecialchars($admin_name); ?></strong>
        </div>
    </div>

    <!-- Main Viewport -->
    <div class="admin-main-viewport">
        
        <!-- Header Bar -->
        <div class="admin-header-bar">
            <div class="admin-header-title">System Logs & Leaderboard Reports</div>
            <div class="admin-header-user">
                <span>Welcome, <strong><?php echo htmlspecialchars($admin_name); ?></strong> (Admin)</span>
                <a href="../auth/logout.php" class="admin-logout-btn">Secure Exit</a>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="admin-main-content">
            
            <?php if (isset($error_msg)): ?>
                <div class="error-banner" style="margin-bottom: 20px;"><?php echo htmlspecialchars($error_msg); ?></div>
            <?php endif; ?>

            <!-- Action Toolbar for Exporting -->
            <div style="margin-bottom: 24px; display: flex; justify-content: flex-end;">
                <a href="system_reports.php?export=csv" class="btn-admin btn-admin-primary">
                    📥 Export Leaderboard to CSV Spreadsheet
                </a>
            </div>

            <div class="admin-grid-two-col">
                
                <!-- Left Column: Top 10 Leaderboard Table -->
                <div class="admin-panel-card">
                    <div class="admin-panel-title">⭐ Global Top 10 Leaderboard</div>
                    
                    <div style="overflow-x: auto;">
                        <table class="admin-dense-table">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Student Name</th>
                                    <th>Class Section</th>
                                    <th>Gold Stars</th>
                                    <th>Streak</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($top_students)): ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; color: var(--color-slate-600);">No gamification records recorded yet.</td>
                                    </tr>
                                <?php else: 
                                    $rank = 1;
                                    foreach ($top_students as $row):
                                ?>
                                    <tr>
                                        <td><strong>#<?php echo $rank++; ?></strong></td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                <span style="font-size: 1.15rem;">
                                                    <?php 
                                                        $avatar = $row['avatar_url'] ?: 'monkey';
                                                        $emoji = '🐵';
                                                        if ($avatar === 'bunny') $emoji = '🐰';
                                                        elseif ($avatar === 'panda') $emoji = '🐼';
                                                        elseif ($avatar === 'fox') $emoji = '🦊';
                                                        echo $emoji;
                                                    ?>
                                                </span>
                                                <strong><?php echo htmlspecialchars($row['full_name']); ?></strong>
                                            </div>
                                        </td>
                                        <td><code><?php echo htmlspecialchars($row['class_section'] ?: 'Not Assigned'); ?></code></td>
                                        <td><span style="color: var(--color-warning); font-weight: 700;">⭐ <?php echo $row['total_points']; ?></span></td>
                                        <td><span style="color: var(--color-danger); font-weight: 700;">🔥 <?php echo $row['login_streak']; ?> Days</span></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Right Column: Class Performance Aggregates -->
                <div class="admin-panel-card">
                    <div class="admin-panel-title">🏫 Class-Wide Performance</div>
                    
                    <div style="overflow-x: auto;">
                        <table class="admin-dense-table">
                            <thead>
                                <tr>
                                    <th>Class</th>
                                    <th>Students</th>
                                    <th>Sum Stars</th>
                                    <th>Avg Stars</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($class_reports)): ?>
                                    <tr>
                                        <td colspan="4" style="text-align: center; color: var(--color-slate-600);">No class records found.</td>
                                    </tr>
                                <?php else: 
                                    foreach ($class_reports as $row):
                                ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($row['class_section']); ?></strong></td>
                                        <td><?php echo $row['student_count']; ?> Pupils</td>
                                        <td><span style="color: var(--color-warning); font-weight: 700;">⭐ <?php echo $row['total_stars']; ?></span></td>
                                        <td><strong><?php echo $row['average_stars']; ?></strong></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

        </div>
    </div>

</body>
</html>
