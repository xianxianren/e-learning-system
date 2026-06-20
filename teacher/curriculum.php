<?php
/**
 * curriculum.php (teacher)
 * Lesson builder and subject catalog.
 * Manages insertions of lessons, sequence orders, and worksheet PDF uploads.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Verify teacher permissions
require_role('teacher');

$teacher_name = $_SESSION['full_name'];
$teacher_id = $_SESSION['user_id'];

$alert_success = "";
$alert_danger = "";

// -------------------------------------------------------------------------
// Form Submission Processing
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'add_lesson') {
        $subject_id = filter_input(INPUT_POST, 'subject_id', FILTER_VALIDATE_INT);
        $title = filter_input(INPUT_POST, 'title', FILTER_DEFAULT);
        $order_num = filter_input(INPUT_POST, 'order_num', FILTER_VALIDATE_INT);
        $teacher_notes = filter_input(INPUT_POST, 'teacher_notes', FILTER_DEFAULT);

        $title = trim($title);

        // Validation
        if (!$subject_id || empty($title) || $order_num === false) {
            $alert_danger = "Please fill in all required fields (Subject, Title, Sequence Order).";
        } else {
            $worksheet_path = null;
            $video_url = null;

            // Check video file upload
            if (isset($_FILES['lesson_video']) && $_FILES['lesson_video']['error'] === UPLOAD_ERR_OK) {
                $video_file = $_FILES['lesson_video'];
                $extension = strtolower(pathinfo($video_file['name'], PATHINFO_EXTENSION));

                if (!in_array($extension, ['mp4', 'webm'])) {
                    $alert_danger = "Only MP4 and WebM formats are allowed for videos.";
                } elseif ($video_file['size'] > 52428800) { // 50MB Limit
                    $alert_danger = "Video file size exceeds the 50MB limit.";
                } else {
                    $upload_video_dir = __DIR__ . '/../assets/videos/';
                    if (!is_dir($upload_video_dir)) {
                        mkdir($upload_video_dir, 0777, true);
                    }

                    $new_filename = uniqid('vid_') . '.' . $extension;
                    if (move_uploaded_file($video_file['tmp_name'], $upload_video_dir . $new_filename)) {
                        $video_url = 'assets/videos/' . $new_filename;
                    } else {
                        $alert_danger = "Failed to save the uploaded video file.";
                    }
                }
            }

            // Check worksheet file upload
            if (empty($alert_danger) && isset($_FILES['worksheet']) && $_FILES['worksheet']['error'] !== UPLOAD_ERR_NO_FILE) {
                $file = $_FILES['worksheet'];

                if ($file['error'] !== UPLOAD_ERR_OK) {
                    $alert_danger = "Worksheet upload failed. Error code: " . $file['error'];
                } else {
                    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                    // Restrict to PDF only for security
                    if ($file_ext !== 'pdf') {
                        $alert_danger = "Only PDF files are allowed for worksheets.";
                    } elseif ($file['size'] > 5000000) { // 5MB Limit
                        $alert_danger = "Worksheet PDF size exceeds the 5MB limit.";
                    } else {
                        // Create upload directory path
                        $upload_dir = __DIR__ . '/../assets/uploads/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }

                        // Generate a unique file name
                        $new_filename = 'worksheet_' . time() . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $title) . '.pdf';
                        $target_path = $upload_dir . $new_filename;

                        if (move_uploaded_file($file['tmp_name'], $target_path)) {
                            // Relative path saved to database
                            $worksheet_path = 'assets/uploads/' . $new_filename;
                        } else {
                            $alert_danger = "Failed to save the uploaded worksheet file.";
                        }
                    }
                }
            }

            // If no upload errors, execute DB insert
            if (empty($alert_danger)) {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO lessons (subject_id, title, video_url, worksheet_url, order_num, teacher_notes) 
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $subject_id,
                        $title,
                        $video_url,
                        $worksheet_path,
                        $order_num,
                        $teacher_notes
                    ]);

                    // Retrieve newly created lesson ID to create a blank quiz for it!
                    $new_lesson_id = $pdo->lastInsertId();

                    // Every lesson gets a default empty quiz initialized
                    $quiz_stmt = $pdo->prepare("
                        INSERT INTO quizzes (lesson_id, total_marks, questions_json)
                        VALUES (?, 10, '[]')
                    ");
                    $quiz_stmt->execute([$new_lesson_id]);

                    $alert_success = "Lesson \"$title\" added successfully and blank quiz initialized! 🎉";
                } catch (PDOException $e) {
                    $alert_danger = "Database insertion error: " . $e->getMessage();
                }
            }
        }
    } elseif ($_POST['action'] === 'edit_lesson') {
        $lesson_id = filter_input(INPUT_POST, 'lesson_id', FILTER_VALIDATE_INT);
        $title = filter_input(INPUT_POST, 'title', FILTER_DEFAULT);
        $teacher_notes = filter_input(INPUT_POST, 'teacher_notes', FILTER_DEFAULT);
        $title = trim($title);

        if (!$lesson_id || empty($title)) {
            $alert_danger = "Please enter a valid lesson title.";
        } else {
            try {
                $new_video_url = null;
                $has_new_video = false;

                if (isset($_FILES['lesson_video']) && $_FILES['lesson_video']['error'] === UPLOAD_ERR_OK) {
                    $video_file = $_FILES['lesson_video'];
                    $extension = strtolower(pathinfo($video_file['name'], PATHINFO_EXTENSION));

                    if (!in_array($extension, ['mp4', 'webm'])) {
                        $alert_danger = "Only MP4 and WebM formats are allowed for replacement videos.";
                    } elseif ($video_file['size'] > 52428800) { // 50MB Limit
                        $alert_danger = "Video file size exceeds the 50MB limit.";
                    } else {
                        // Retrieve old video path to delete it
                        $stmt = $pdo->prepare("SELECT video_url FROM lessons WHERE lesson_id = ?");
                        $stmt->execute([$lesson_id]);
                        $old_video_url = $stmt->fetchColumn();

                        if ($old_video_url) {
                            $old_video_path = __DIR__ . '/../' . $old_video_url;
                            if (file_exists($old_video_path) && is_file($old_video_path)) {
                                unlink($old_video_path);
                            }
                        }

                        // Save new file
                        $upload_video_dir = __DIR__ . '/../assets/videos/';
                        if (!is_dir($upload_video_dir)) {
                            mkdir($upload_video_dir, 0777, true);
                        }

                        $new_filename = uniqid('vid_') . '.' . $extension;
                        if (move_uploaded_file($video_file['tmp_name'], $upload_video_dir . $new_filename)) {
                            $new_video_url = 'assets/videos/' . $new_filename;
                            $has_new_video = true;
                        } else {
                            $alert_danger = "Failed to save the new uploaded video file.";
                        }
                    }
                }

                if (empty($alert_danger)) {
                    if ($has_new_video) {
                        $stmt = $pdo->prepare("UPDATE lessons SET title = ?, video_url = ?, teacher_notes = ? WHERE lesson_id = ?");
                        $stmt->execute([$title, $new_video_url, $teacher_notes, $lesson_id]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE lessons SET title = ?, teacher_notes = ? WHERE lesson_id = ?");
                        $stmt->execute([$title, $teacher_notes, $lesson_id]);
                    }
                    $alert_success = "Lesson updated successfully! 🎉";
                }
            } catch (PDOException $e) {
                $alert_danger = "Database update error: " . $e->getMessage();
            }
        }
    }
}

// -------------------------------------------------------------------------
// Load Subjects and Lessons list
// -------------------------------------------------------------------------
$subjects = [];
$lessons = [];

try {
    // Fetch subjects teacher belongs to (we fetch all subjects since this is a demo/staff layout)
    $subj_stmt = $pdo->query("SELECT * FROM subjects ORDER BY subject_name ASC");
    $subjects = $subj_stmt->fetchAll();

    // Fetch all lessons
    $les_stmt = $pdo->query("
        SELECT l.*, s.subject_name 
        FROM lessons l 
        JOIN subjects s ON l.subject_id = s.subject_id 
        ORDER BY s.subject_name ASC, l.order_num ASC
    ");
    $lessons = $les_stmt->fetchAll();
} catch (PDOException $e) {
    $alert_danger = "Failed to load database values.";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Curriculum Manager - Interactive E-Learning System</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        .btn-edit,
        .btn-delete {
            padding: 4px 8px;
            font-size: 0.75rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 5px;
            font-weight: bold;
            display: inline-block;
        }

        .btn-edit {
            background-color: #f39c12;
            color: white;
        }

        .btn-delete {
            background-color: #e74c3c;
            color: white;
        }

        .btn-edit:hover {
            background-color: #d35400;
        }

        .btn-delete:hover {
            background-color: #c0392b;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: #ffffff;
            padding: 25px;
            border: none;
            width: 90%;
            max-width: 450px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.25);
            box-sizing: border-box;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #f1f2f6;
            padding-bottom: 12px;
            margin-bottom: 18px;
        }

        .modal-close {
            color: #888;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
        }

        .modal-close:hover {
            color: #333;
        }

        /* Empty State Styling */
        .empty-state {
            background-color: #f8fafc;
            border: 2px dashed #cbd5e1;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            color: #64748b;
            font-weight: 600;
            margin-bottom: 20px;
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
                <a href="roster.php" class="sidebar-link">👥 Student Roster</a>
            </li>
            <li class="sidebar-menu-item">
                <a href="curriculum.php" class="sidebar-link active">📖 Curriculum Manager</a>
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
            <div class="topbar-title">Curriculum & Course Manager</div>
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

            <div class="admin-grid-two-col">

                <!-- Left Column: Subject & Lesson Roadmap Roster -->
                <div>
                    <div class="admin-card">
                        <div class="admin-card-title">Subject & Lesson Roadmaps</div>

                        <?php if (empty($subjects)): ?>
                            <p style="color: var(--text-secondary);">No subjects created yet.</p>
                        <?php else: ?>
                            <?php
                            foreach ($subjects as $subj):
                                $subject_name = $subj['subject_name'];
                                $subj_lessons = array_filter($lessons, function($l) use ($subj) {
                                    return $l['subject_id'] == $subj['subject_id'];
                                });
                                ?>
                                <h3
                                    style="margin: 15px 0 10px 0; color: var(--primary-color); border-bottom: 2px solid var(--border-color); padding-bottom: 5px;">
                                    📚 <?php echo htmlspecialchars($subject_name); ?>
                                </h3>
                                
                                <?php if (empty($subj_lessons)): ?>
                                    <div class="empty-state">No lessons created yet. Click "Add New Lesson" to begin!</div>
                                <?php else: ?>
                                    <div class="table-responsive" style="margin-bottom: 20px;">
                                        <table class="data-table">
                                            <thead>
                                                <tr>
                                                    <th style="width: 80px;">Seq</th>
                                                    <th>Lesson Title</th>
                                                    <th>Video URL</th>
                                                    <th>Worksheet</th>
                                                    <th style="width: 140px;">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($subj_lessons as $l): ?>
                                                    <tr>
                                                        <td><strong>#<?php echo $l['order_num']; ?></strong></td>
                                                        <td><strong><?php echo htmlspecialchars($l['title']); ?></strong></td>
                                                        <td>
                                                            <span
                                                                style="font-size: 0.8rem; color: var(--text-secondary); max-width: 150px; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                                <?php echo htmlspecialchars($l['video_url'] ?: 'No Video'); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php if ($l['worksheet_url']): ?>
                                                                <a href="../<?php echo htmlspecialchars($l['worksheet_url']); ?>"
                                                                    class="btn-sm" target="_blank"
                                                                    style="padding: 2px 8px; font-size: 0.75rem;">
                                                                    View PDF
                                                                </a>
                                                            <?php else: ?>
                                                                <span style="font-size: 0.8rem; color: var(--text-muted);">None</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <div style="display: flex; gap: 5px; align-items: center;">
                                                                <button type="button" class="btn-edit"
                                                                    onclick='openEditLessonModal(<?php echo $l['lesson_id']; ?>, <?php echo htmlspecialchars(json_encode($l['title']), ENT_QUOTES); ?>, <?php echo htmlspecialchars(json_encode($l['teacher_notes'] ?? ''), ENT_QUOTES); ?>)'>✏️
                                                                    Edit</button>
                                                                <button type="button" class="btn-delete"
                                                                    onclick="confirmDeleteLesson(<?php echo $l['lesson_id']; ?>)">🗑️
                                                                    Delete</button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Right Column: Lesson Builder Form Panel -->
                <div class="admin-card">
                    <div class="admin-card-title">New Lesson Builder</div>

                    <form method="POST" action="curriculum.php" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="add_lesson">

                        <div class="form-group">
                            <label class="form-label" for="subject_id">Subject Curriculum</label>
                            <select id="subject_id" name="subject_id" class="form-control" required>
                                <option value="">Select Subject...</option>
                                <?php foreach ($subjects as $subj): ?>
                                    <option value="<?php echo $subj['subject_id']; ?>">
                                        <?php echo htmlspecialchars($subj['subject_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="title">Lesson Title</label>
                            <input type="text" id="title" name="title" class="form-control"
                                placeholder="e.g. Addition up to 20" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="order_num">Sequence Order Number</label>
                            <input type="number" id="order_num" name="order_num" class="form-control" value="1" min="1"
                                required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="videoUpload">Lesson Video File (Optional)</label>
                            <input type="file" name="lesson_video" accept="video/mp4,video/webm" id="videoUpload"
                                class="form-control" style="padding: 6px;">
                            <small
                                style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px; display: block;">Max
                                file size: 50MB. Allowed formats: MP4, WebM.</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Teacher's Notes 📝 (Optional):</label>
                            <textarea name="teacher_notes" class="form-control" rows="4" placeholder="Enter instructions, homework details, or encouraging words here..."></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="worksheet">PDF Worksheet File (Optional)</label>
                            <input type="file" id="worksheet" name="worksheet" class="form-control" accept=".pdf"
                                style="padding: 6px;">
                            <span
                                style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px; display: block;">Only
                                PDF format up to 5MB is accepted.</span>
                        </div>

                        <button type="submit" class="form-control btn-solid-blue"
                            style="font-weight: 700; cursor: pointer; margin-top: 15px; height: 42px;">
                            Save & Build Lesson 🚀
                        </button>
                    </form>
                </div>

            </div>

        </div>
    </div>

    <!-- Edit Lesson Modal -->
    <div id="editLessonModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="margin: 0; color: var(--primary-color);">✏️ Edit Lesson Details</h3>
                <span class="modal-close" onclick="closeEditLessonModal()">&times;</span>
            </div>
            <form method="POST" action="curriculum.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit_lesson">
                <input type="hidden" name="lesson_id" id="edit_lesson_id">

                <div class="form-group" style="margin-bottom: 15px;">
                    <label class="form-label" for="edit_title"
                        style="display: block; font-weight: bold; margin-bottom: 5px;">Lesson Title</label>
                    <input type="text" id="edit_title" name="title" class="form-control" style="width: 100%;" required>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label" for="edit_videoUpload"
                        style="display: block; font-weight: bold; margin-bottom: 5px;">Replace Video File
                        (Optional)</label>
                    <input type="file" name="lesson_video" accept="video/mp4,video/webm" id="edit_videoUpload"
                        class="form-control" style="width: 100%; padding: 6px;">
                    <small style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px; display: block;">Max
                        file size: 50MB. Allowed formats: MP4, WebM.</small>
                </div>

                <div class="form-group" style="margin-bottom: 15px;">
                    <label class="form-label" for="edit_notes" style="display: block; font-weight: bold; margin-bottom: 5px;">Teacher's Notes 📝 (Optional):</label>
                    <textarea id="edit_notes" name="teacher_notes" class="form-control" rows="4" style="width: 100%;" placeholder="Enter instructions, homework details, or encouraging words here..."></textarea>
                </div>

                <button type="submit" class="form-control btn-solid-blue"
                    style="font-weight: 700; cursor: pointer; height: 42px; width: 100%;">
                    Update Lesson Details 💾
                </button>
            </form>
        </div>
    </div>

    <script>
        function confirmDeleteLesson(lessonId) {
            if (confirm("Are you sure? This will permanently delete the video and all associated quizzes.")) {
                window.location.href = "delete_lesson.php?id=" + lessonId;
            }
        }

        function openEditLessonModal(lessonId, currentTitle, currentNotes) {
            document.getElementById('edit_lesson_id').value = lessonId;
            document.getElementById('edit_title').value = currentTitle;
            document.getElementById('edit_notes').value = currentNotes || '';
            document.getElementById('editLessonModal').style.display = 'flex';
        }

        function closeEditLessonModal() {
            document.getElementById('editLessonModal').style.display = 'none';
        }

        // Close modal when clicking outside content area
        window.onclick = function (event) {
            const modal = document.getElementById('editLessonModal');
            if (event.target === modal) {
                closeEditLessonModal();
            }
        };
    </script>
</body>

</html>