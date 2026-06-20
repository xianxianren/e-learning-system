<?php
/**
 * roster.php (teacher)
 * Student listing displaying names, classes, progress, and gamification totals.
 * Handles creating new class sections via modal and secure PDO transactions.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Verify teacher permissions
require_role('teacher');

$teacher_name = $_SESSION['full_name'];
$alert_success = $_GET['msg'] ?? "";
$alert_danger = "";

// -------------------------------------------------------------------------
// Process Roster Submissions: Create New Class
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_class'])) {
    $class_name = trim($_POST['class_name'] ?? '');

    if (empty($class_name)) {
        $alert_danger = "Class name is required!";
    } else {
        try {
            // Validate that class doesn't exist yet
            $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE class_section = ?");
            $check_stmt->execute([$class_name]);
            if ($check_stmt->fetchColumn() > 0) {
                $alert_danger = "Class '" . htmlspecialchars($class_name) . "' already exists!";
            } else {
                // Insert a placeholder student profile to represent this class section
                $username = 'class_placeholder_' . bin2hex(random_bytes(8));
                $password_hash = password_hash('0000', PASSWORD_DEFAULT);
                
                $insert_placeholder = $pdo->prepare("
                    INSERT INTO users (username, password_hash, role, full_name, class_section)
                    VALUES (?, ?, 'student', 'Class Placeholder', ?)
                ");
                $insert_placeholder->execute([$username, $password_hash, $class_name]);
                
                $alert_success = "New class '{$class_name}' successfully created! 🎉";
                header("Location: roster.php?msg=" . urlencode($alert_success));
                exit;
            }
        } catch (Exception $e) {
            $alert_danger = "Failed to create class: " . $e->getMessage();
        }
    }
}

// -------------------------------------------------------------------------
// Load roster data sets
// -------------------------------------------------------------------------

// 1. Fetch total lessons in curriculum for progress percentage calculation
$total_lessons = 0;
try {
    $total_lessons = $pdo->query("SELECT COUNT(*) FROM lessons")->fetchColumn();
} catch (PDOException $e) {
    $alert_danger = "Curriculum Error: " . $e->getMessage();
}

// 2. Fetch all student profiles and left join their stats
$student_roster = [];
try {
    $roster_query = "
        SELECT u.user_id, u.username, u.full_name, u.class_section, u.avatar_url,
               COALESCE(gs.total_points, 0) AS total_points,
               COALESCE(gs.login_streak, 0) AS login_streak,
               (SELECT COUNT(*) FROM student_progress WHERE student_id = u.user_id AND status = 'completed') AS completed_count
        FROM users u
        LEFT JOIN gamification_stats gs ON u.user_id = gs.student_id
        WHERE u.role = 'student' AND u.username NOT LIKE 'class_placeholder_%'
        ORDER BY u.full_name ASC
    ";
    $student_roster = $pdo->query($roster_query)->fetchAll();
} catch (PDOException $e) {
    $alert_danger = "Could not fetch student roster: " . $e->getMessage();
}

// 3. Fetch unique class sections for the filter dropdown
$classes_list = [];
try {
    $classes_list = $pdo->query("
        SELECT DISTINCT class_section 
        FROM users 
        WHERE class_section IS NOT NULL AND class_section != ''
        ORDER BY class_section ASC
    ")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // Fail silently
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Roster - Interactive E-Learning System</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        .filter-toolbar {
            display: flex;
            gap: 16px;
            margin-bottom: 24px;
            flex-wrap: wrap;
            background-color: var(--bg-card);
            padding: 16px;
            border-radius: var(--radius-standard);
            border: 1px solid var(--border-color);
            align-items: center;
        }

        .filter-search-box {
            flex-grow: 1;
            min-width: 250px;
            position: relative;
        }

        .filter-select-box {
            width: 200px;
        }

        .progress-bar-flat {
            background-color: #e2e8f0;
            height: 10px;
            border-radius: 9999px;
            overflow: hidden;
            width: 120px;
            display: inline-block;
            vertical-align: middle;
            margin-right: 8px;
        }

        .progress-bar-flat-fill {
            height: 100%;
            border-radius: 9999px;
            background-color: var(--success-color);
        }

        /* Modal Overlay design system styling */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            z-index: 10000;
            justify-content: center;
            align-items: center;
            transition: all 0.3s ease;
        }

        .modal-card {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-large);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            width: 90%;
            max-width: 500px;
            padding: 30px;
            position: relative;
            animation: modal-slide-up 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes modal-slide-up {
            from {
                transform: translateY(30px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
    </style>
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
                <a href="roster.php" class="sidebar-link active">👥 Student Roster</a>
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
            <div class="topbar-title">Student Cohort Roster</div>
            <div class="topbar-user">
                <span class="user-name">Welcome, <?php echo htmlspecialchars($teacher_name); ?></span>
                <span class="user-role-badge">Teacher</span>
                <a href="../auth/logout.php" class="btn-logout">Logout</a>
            </div>
        </div>

        <!-- Main content layout -->
        <div class="admin-content">

            <?php if (!empty($alert_success)): ?>
                <div class="alert-success"><?php echo htmlspecialchars($alert_success); ?></div>
            <?php endif; ?>
            <?php if (!empty($alert_danger)): ?>
                <div class="alert-danger"><?php echo htmlspecialchars($alert_danger); ?></div>
            <?php endif; ?>

            <!-- Real-time Filter Toolbar -->
            <div class="filter-toolbar" style="display: flex; gap: 15px; align-items: center; margin-bottom: 20px;">
                <div class="filter-search-box" style="flex-grow: 1;">
                    <input type="text" id="roster_search" class="form-control"
                        placeholder="🔍 Search students by name...">
                </div>
                <div class="filter-select-box">
                    <select id="roster_class_filter" class="form-control">
                        <option value="">All Classes 🏫</option>
                        <?php foreach ($classes_list as $cls): ?>
                            <option value="<?php echo htmlspecialchars($cls); ?>"><?php echo htmlspecialchars($cls); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="button" id="btnOpenCreateClass" class="btn-secondary" style="cursor: pointer; padding: 10px 20px; height: auto;">➕ Create New Class</button>
            </div>

            <!-- Student List Data Table -->
            <div class="admin-card">
                <div class="table-responsive">
                    <table class="data-table" id="roster_table">
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Student Name</th>
                                <th>Class Section</th>
                                <th>Curriculum Progress</th>
                                <th>Gold Stars (XP)</th>
                                <th>Streak</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($student_roster)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; color: var(--text-muted);">No student records
                                        found in the database.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($student_roster as $student):
                                    $completedCount = $student['completed_count'];
                                    $percent = 0;
                                    if ($total_lessons > 0) {
                                        $percent = round(($completedCount / $total_lessons) * 100);
                                    }
                                    ?>
                                    <tr class="roster-row"
                                        data-name="<?php echo htmlspecialchars(strtolower($student['full_name'])); ?>"
                                        data-class="<?php echo htmlspecialchars($student['class_section'] ?: ''); ?>">
                                        <td>#<?php echo $student['user_id']; ?></td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <div class="data-table-avatar">
                                                    <?php
                                                    $avatar = $student['avatar_url'] ?: 'monkey';
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
                                                <span class="student-name-text"
                                                    style="font-weight: 600;"><?php echo htmlspecialchars($student['full_name']); ?></span>
                                            </div>
                                        </td>
                                        <td class="student-class-text">
                                            <?php echo htmlspecialchars($student['class_section'] ?: 'Not Assigned'); ?>
                                        </td>
                                        <td>
                                            <div class="progress-bar-flat">
                                                <div class="progress-bar-flat-fill" style="width: <?php echo $percent; ?>%;">
                                                </div>
                                            </div>
                                            <span
                                                style="font-weight: 600; font-size: 0.85rem; color: var(--text-secondary);"><?php echo $percent; ?>%</span>
                                        </td>
                                        <td>
                                            <strong style="color: var(--warning-color);">⭐
                                                <?php echo $student['total_points']; ?></strong>
                                        </td>
                                        <td>
                                            <strong style="color: var(--danger-color);">🔥
                                                <?php echo $student['login_streak']; ?></strong>
                                        </td>
                                        <td>
                                            <a href="review.php?student_id=<?php echo $student['user_id']; ?>" class="btn-sm"
                                                style="display: inline-block;">
                                                Grade & Award XP
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <!-- Modal Dialog: Create New Class -->
    <div id="createClassModal" class="modal-overlay" style="display: none;">
        <div class="modal-card" style="max-width: 400px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px;">
                <div class="admin-card-title" style="margin-bottom:0;">Create New Class</div>
                <button type="button" onclick="closeCreateClassModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-muted);">❌</button>
            </div>
            
            <form method="POST" action="roster.php" id="createClassForm">
                <input type="hidden" name="create_class" value="1">
                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label" for="brandNewClassName">Class Name</label>
                    <input type="text" id="brandNewClassName" name="class_name" class="form-control" placeholder="e.g., Standard 2B" required>
                </div>
                
                <div style="display: flex; justify-content: flex-end; gap: 12px; border-top: 1px solid var(--border-color); padding-top: 15px;">
                    <button type="button" onclick="closeCreateClassModal()" class="btn-sm btn-solid-blue" style="background-color: var(--text-secondary); padding: 8px 16px;">Cancel</button>
                    <button type="button" id="btnSubmitCreateClass" class="btn-sm btn-solid-blue" style="background-color: var(--primary-light); padding: 8px 16px;">Create Class 🚀</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Client-side Roster Sorting/Filtering Scripts -->
    <script>
        function openCreateClassModal() {
            document.getElementById('createClassModal').style.display = 'flex';
            document.getElementById('brandNewClassName').value = '';
            document.getElementById('brandNewClassName').focus();
        }

        function closeCreateClassModal() {
            document.getElementById('createClassModal').style.display = 'none';
        }

        document.addEventListener('DOMContentLoaded', () => {
            const searchInput = document.getElementById('roster_search');
            const classFilter = document.getElementById('roster_class_filter');
            const rows = document.querySelectorAll('.roster-row');

            // Wire up open class modal button
            const btnOpenCreateClass = document.getElementById('btnOpenCreateClass');
            if (btnOpenCreateClass) {
                btnOpenCreateClass.addEventListener('click', openCreateClassModal);
            }

            // Wire up submit class button
            const btnSubmitCreateClass = document.getElementById('btnSubmitCreateClass');
            if (btnSubmitCreateClass) {
                btnSubmitCreateClass.addEventListener('click', () => {
                    const classNameInput = document.getElementById('brandNewClassName');
                    const className = classNameInput.value.trim();
                    if (!className) {
                        alert("Please enter a valid class name.");
                        return;
                    }
                    document.getElementById('createClassForm').submit();
                });
            }

            function filterRoster() {
                const searchVal = searchInput.value.toLowerCase().trim();
                const classVal = classFilter.value;

                rows.forEach(row => {
                    const name = row.dataset.name;
                    const studentClass = row.dataset.class;

                    const matchesSearch = name.includes(searchVal);
                    const matchesClass = (classVal === "") || (studentClass === classVal);

                    if (matchesSearch && matchesClass) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            }

            if (searchInput) searchInput.addEventListener('input', filterRoster);
            if (classFilter) classFilter.addEventListener('change', filterRoster);

            // Close modal when clicking outside the card boundary
            window.addEventListener('click', (e) => {
                const cModal = document.getElementById('createClassModal');
                if (e.target === cModal) {
                    closeCreateClassModal();
                }
            });
        });
    </script>
</body>

</html>