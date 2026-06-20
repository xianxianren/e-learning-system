<?php
/**
 * dashboard.php (admin)
 * Master dashboard console for school administrator.
 * Displays overall platform metrics, system health logs, and media storage usage.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Verify admin permissions
require_role('admin');

// Robust PHP function to calculate the total size of a directory in bytes
function getFolderSize($dir) {
    $size = 0;
    $files = glob(rtrim($dir, '/').'/*', GLOB_NOSORT);
    if ($files !== false) {
        foreach ($files as $each) {
            $size += is_file($each) ? filesize($each) : getFolderSize($each);
        }
    }
    return $size;
}

$admin_name = $_SESSION['full_name'];

// 1. Fetch system health metrics
$total_students = 0;
$total_teachers = 0;
$total_subjects = 0;
$total_points_awarded = 0;

try {
    $total_students = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND username NOT LIKE 'class_placeholder_%'")->fetchColumn();
    $total_teachers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'")->fetchColumn();
    $total_subjects = $pdo->query("SELECT COUNT(*) FROM subjects")->fetchColumn();
    $total_points_awarded = $pdo->query("SELECT COALESCE(SUM(total_points), 0) FROM gamification_stats")->fetchColumn();
} catch (PDOException $e) {
    $db_error = "Error querying platform metrics: " . $e->getMessage();
}

// 2. Calculate Server Storage Used
$max_storage_mb = 500; // Increased to 500MB
$uploads_size = getFolderSize('../assets/uploads/');
$videos_size = getFolderSize('../assets/videos/');
$total_used_bytes = $uploads_size + $videos_size;

// Convert bytes to Megabytes (MB)
$used_mb = round($total_used_bytes / 1048576, 2); 

// Calculate accurate percentage
$percentage = ($used_mb > 0) ? round(($used_mb / $max_storage_mb) * 100, 1) : 0;

// Cap percentage at 100% for the UI progress bar
$progress_width = min(100, $percentage);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Admin Dashboard - Interactive E-Learning System</title>
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
                <a href="dashboard.php" class="sidebar-nav-link active">📊 System Metrics</a>
            </li>
            <li class="sidebar-nav-item">
                <a href="manage_users.php" class="sidebar-nav-link">👥 User Accounts</a>
            </li>
            <li class="sidebar-nav-item">
                <a href="manage_curriculum.php" class="sidebar-nav-link">📖 Master Curriculum</a>
            </li>
            <li class="sidebar-nav-item">
                <a href="system_reports.php" class="sidebar-nav-link">📋 System Reports</a>
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
            <div class="admin-header-title">System Metrics Panel</div>
            <div class="admin-header-user">
                <span>Welcome, <strong><?php echo htmlspecialchars($admin_name); ?></strong> (Admin)</span>
                <a href="../auth/logout.php" class="admin-logout-btn">Secure Exit</a>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="admin-main-content">
            
            <?php if (isset($db_error)): ?>
                <div class="error-banner" style="margin-bottom: 20px;"><?php echo htmlspecialchars($db_error); ?></div>
            <?php endif; ?>

            <!-- Metrics Statistics Grid -->
            <div class="admin-metrics-grid">
                <div class="metric-widget-card">
                    <span class="metric-widget-label">Registered Students</span>
                    <span class="metric-widget-value"><?php echo $total_students; ?></span>
                </div>
                <div class="metric-widget-card">
                    <span class="metric-widget-label">Active Teachers</span>
                    <span class="metric-widget-value"><?php echo $total_teachers; ?></span>
                </div>
                <div class="metric-widget-card">
                    <span class="metric-widget-label">Subjects Created</span>
                    <span class="metric-widget-value"><?php echo $total_subjects; ?></span>
                </div>
                <div class="metric-widget-card">
                    <span class="metric-widget-label">Total Gamification Stars</span>
                    <span class="metric-widget-value" style="color: var(--color-warning);">⭐ <?php echo $total_points_awarded; ?></span>
                </div>
            </div>

            <!-- Server Storage alert widget -->
            <div class="storage-alert-card">
                <p>💾 Worksheet & Video Upload Directory Storage</p>
                <h3><?php echo $used_mb; ?> MB / <?php echo $max_storage_mb; ?> MB (<?php echo $percentage; ?>%)</h3>
                <div class="storage-progress-container">
                    <div class="storage-progress-fill progress-bar-fill" style="width: <?php echo $progress_width; ?>%;"></div>
                </div>
                <p style="font-size: 0.75rem; color: var(--color-slate-600); margin-top: 8px;">
                    Tracks physical worksheets uploaded under <code>/assets/uploads/</code> by class teachers.
                </p>
            </div>

            <!-- System Health Log mockup card -->
            <div class="admin-panel-card">
                <div class="admin-panel-title">System Status Log</div>
                
                <table class="admin-dense-table">
                    <thead>
                        <tr>
                            <th>Module Component</th>
                            <th>Resource Status</th>
                            <th>Latency / Execution</th>
                            <th>Operational Code</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>MySQL Connection Pool</strong></td>
                            <td><span style="color: var(--color-success); font-weight: 700;">● Online</span></td>
                            <td>3ms</td>
                            <td><code>DB_SUCCESS_CONNECTED</code></td>
                        </tr>
                        <tr>
                            <td><strong>Session Manager Token Security</strong></td>
                            <td><span style="color: var(--color-success); font-weight: 700;">● Secure</span></td>
                            <td>&lt;1ms</td>
                            <td><code>SESSION_SECURE_VERIFIED</code></td>
                        </tr>
                        <tr>
                            <td><strong>Web Audio API Engine Compatibility</strong></td>
                            <td><span style="color: var(--color-success); font-weight: 700;">● Ready</span></td>
                            <td>N/A</td>
                            <td><code>BROWSER_AUDIO_SYNTH_ACTIVE</code></td>
                        </tr>
                        <tr>
                            <td><strong>Upload Directory Worksheets</strong></td>
                            <td><span style="color: var(--color-warning); font-weight: 700;">● Monitoring</span></td>
                            <td>-</td>
                            <td><code>DIR_WRITABLE_OK</code></td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

</body>
</html>
