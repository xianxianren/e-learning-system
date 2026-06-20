<?php
/**
 * manage_curriculum.php (admin)
 * Master Curriculum dashboard.
 * Oversees subjects, lesson volumes, and teacher assignments.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Verify admin permissions
require_role('admin');

$admin_name = $_SESSION['full_name'];

$alert_success = "";
$alert_danger = "";

// -------------------------------------------------------------------------
// POST Action Processor
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    // 1. CREATE SUBJECT
    if ($action === 'create_subject') {
        $subject_name = trim(filter_input(INPUT_POST, 'subject_name', FILTER_DEFAULT));
        $teacher_id = filter_input(INPUT_POST, 'teacher_id', FILTER_VALIDATE_INT);
        $icon_url = filter_input(INPUT_POST, 'icon_url', FILTER_DEFAULT);
        
        if (empty($subject_name) || !$teacher_id) {
            $alert_danger = "Required fields (Subject Name, Assigned Teacher) are missing.";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO subjects (subject_name, teacher_id, icon_url) VALUES (?, ?, ?)");
                $stmt->execute([$subject_name, $teacher_id, !empty($icon_url) ? $icon_url : 'default-icon']);
                
                $alert_success = "Subject \"$subject_name\" added to the curriculum! 📚";
            } catch (PDOException $e) {
                $alert_danger = "Database insertion error: " . $e->getMessage();
            }
        }
    }
    
    // 2. DELETE SUBJECT
    elseif ($action === 'delete_subject') {
        $subject_id = filter_input(INPUT_POST, 'subject_id', FILTER_VALIDATE_INT);
        
        if (!$subject_id) {
            $alert_danger = "Invalid Subject ID.";
        } else {
            try {
                // Query all lessons belonging to this subject to delete physical files first
                $stmt_files = $pdo->prepare("SELECT video_url, worksheet_url FROM lessons WHERE subject_id = ?");
                $stmt_files->execute([$subject_id]);
                $lessons_files = $stmt_files->fetchAll();

                foreach ($lessons_files as $lesson_files) {
                    $video_url = $lesson_files['video_url'];
                    $worksheet_url = $lesson_files['worksheet_url'];

                    if ($video_url) {
                        $video_path = __DIR__ . '/../' . $video_url;
                        if (file_exists($video_path) && is_file($video_path)) {
                            unlink($video_path);
                        }
                    }
                    if ($worksheet_url) {
                        $worksheet_path = __DIR__ . '/../' . $worksheet_url;
                        if (file_exists($worksheet_path) && is_file($worksheet_path)) {
                            unlink($worksheet_path);
                        }
                    }
                }

                // Delete subject (cascades and drops all children lessons, quizzes, progress, etc.)
                $stmt = $pdo->prepare("DELETE FROM subjects WHERE subject_id = ?");
                $stmt->execute([$subject_id]);
                
                $alert_success = "Subject and all associated lessons removed successfully.";
            } catch (PDOException $e) {
                $alert_danger = "Failed to remove subject. Database error: " . $e->getMessage();
            }
        }
    }
}

// -------------------------------------------------------------------------
// Load Curriculum Overview & Teachers list
// -------------------------------------------------------------------------
$curriculum_list = [];
$teachers_list = [];

try {
    // Left join linking subjects, teachers, and lessons count
    $curriculum_query = "
        SELECT s.subject_id, s.subject_name, s.icon_url, u.full_name AS teacher_name,
               (SELECT COUNT(*) FROM lessons WHERE subject_id = s.subject_id) AS total_lessons
        FROM subjects s
        LEFT JOIN users u ON s.teacher_id = u.user_id AND u.role = 'teacher'
        ORDER BY s.subject_name ASC
    ";
    $curriculum_list = $pdo->query($curriculum_query)->fetchAll();
    
    // Fetch teachers list for creation form dropdown
    $teachers_list = $pdo->query("SELECT user_id, full_name FROM users WHERE role = 'teacher' ORDER BY full_name ASC")->fetchAll();
    
} catch (PDOException $e) {
    $alert_danger = "Database connection error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Curriculum Control - Interactive E-Learning System</title>
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
                <a href="manage_curriculum.php" class="sidebar-nav-link active">📖 Master Curriculum</a>
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
            <div class="admin-header-title">Master Curriculum Index</div>
            <div class="admin-header-user">
                <span>Welcome, <strong><?php echo htmlspecialchars($admin_name); ?></strong> (Admin)</span>
                <a href="../auth/logout.php" class="admin-logout-btn">Secure Exit</a>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="admin-main-content">
            
            <?php if (!empty($alert_success)): ?>
                <div class="alert-success" style="margin-bottom: 20px;"><?php echo htmlspecialchars($alert_success); ?></div>
            <?php endif; ?>
            <?php if (!empty($alert_danger)): ?>
                <div class="error-banner" style="margin-bottom: 20px;"><?php echo htmlspecialchars($alert_danger); ?></div>
            <?php endif; ?>

            <div class="admin-grid-two-col">
                
                <!-- Left Column: High-level subjects list -->
                <div class="admin-panel-card">
                    <div class="admin-panel-title">Active Subjects</div>
                    
                    <div style="overflow-x: auto;">
                        <table class="admin-dense-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Subject Course</th>
                                    <th>Assigned Instructor</th>
                                    <th>Lessons Count</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($curriculum_list)): ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; color: var(--color-slate-600);">No subject records exist. Create one on the right side!</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($curriculum_list as $row): ?>
                                        <tr>
                                            <td>#<?php echo $row['subject_id']; ?></td>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 8px;">
                                                    <span style="font-size: 1.1rem;">
                                                        <?php 
                                                            $name = strtolower($row['subject_name']);
                                                            if (strpos($name, 'math') !== false) echo '📐';
                                                            elseif (strpos($name, 'science') !== false) echo '🔬';
                                                            elseif (strpos($name, 'english') !== false) echo '📖';
                                                            else echo '🎒';
                                                        ?>
                                                    </span>
                                                    <strong><?php echo htmlspecialchars($row['subject_name']); ?></strong>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($row['teacher_name'] ?: 'Unassigned'); ?></td>
                                            <td><strong><?php echo $row['total_lessons']; ?></strong> Modules</td>
                                            <td>
                                                <form method="POST" action="manage_curriculum.php" onsubmit="return confirm('WARNING: Deleting this subject will delete all associated lessons, quizzes, progress logs, and quiz scores! Continue?');">
                                                    <input type="hidden" name="action" value="delete_subject">
                                                    <input type="hidden" name="subject_id" value="<?php echo $row['subject_id']; ?>">
                                                    <button type="submit" class="btn-admin btn-admin-danger" style="padding: 4px 8px; font-size: 0.75rem; border: none;">
                                                        🗑️ Delete Subject
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Right Column: Create New Subject Form Card -->
                <div class="admin-panel-card">
                    <div class="admin-panel-title">Add Subject to Curriculum</div>
                    
                    <form method="POST" action="manage_curriculum.php">
                        <input type="hidden" name="action" value="create_subject">
                        
                        <div class="admin-form-group">
                            <label class="admin-form-label" for="subject_name">Subject Name</label>
                            <input type="text" id="subject_name" name="subject_name" class="admin-form-control" placeholder="e.g. Primary English" required>
                        </div>
                        
                        <div class="admin-form-group">
                            <label class="admin-form-label" for="teacher_id">Assigned Teacher</label>
                            <select id="teacher_id" name="teacher_id" class="admin-form-control" required>
                                <option value="">Select Teacher...</option>
                                <?php foreach ($teachers_list as $t): ?>
                                    <option value="<?php echo $t['user_id']; ?>"><?php echo htmlspecialchars($t['full_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="admin-form-group">
                            <label class="admin-form-label" for="icon_url">Visual Icon Descriptor (Optional)</label>
                            <select id="icon_url" name="icon_url" class="admin-form-control">
                                <option value="math-icon">📐 Math Icon</option>
                                <option value="science-icon">🔬 Science Icon</option>
                                <option value="english-icon">📖 English Icon</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn-admin btn-admin-primary" style="width: 100%; height: 42px; margin-top: 10px; justify-content: center;">
                            Publish Subject 🚀
                        </button>
                    </form>
                </div>

            </div>

        </div>
    </div>

</body>
</html>
