<?php
/**
 * quizzes.php (teacher)
 * Advanced Quiz Manager (CRUD).
 * Enables teachers to Create, Read, Update, and Delete (CRUD) interactive quizzes.
 * Integrates relational database structures and json representations.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Verify teacher permissions
require_role('teacher');

$teacher_name = $_SESSION['full_name'];
$alert_success = "";
$alert_danger = "";

// -------------------------------------------------------------------------
// Self-Healing Database Tables Setup
// -------------------------------------------------------------------------
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS questions (
            question_id INT AUTO_INCREMENT PRIMARY KEY,
            quiz_id INT NOT NULL,
            question_text TEXT NOT NULL,
            question_type ENUM('single_choice', 'multiple_choice', 'fill_in_the_blank', 'drag_and_put', 'connecting_the_link') NOT NULL,
            order_num INT DEFAULT 1,
            FOREIGN KEY (quiz_id) REFERENCES quizzes(quiz_id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS question_options (
            option_id INT AUTO_INCREMENT PRIMARY KEY,
            question_id INT NOT NULL,
            option_text TEXT NOT NULL,
            is_correct TINYINT DEFAULT 0,
            matching_pair TEXT NULL,
            category VARCHAR(100) NULL,
            FOREIGN KEY (question_id) REFERENCES questions(question_id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
} catch (PDOException $e) {
    $alert_danger = "Database Schema Initialization Error: " . $e->getMessage();
}

// -------------------------------------------------------------------------
// Handle Delete Mode
// -------------------------------------------------------------------------
$action = $_GET['action'] ?? 'list';
if ($action === 'delete' && isset($_GET['quiz_id']) && empty($alert_danger)) {
    $delete_id = filter_input(INPUT_GET, 'quiz_id', FILTER_VALIDATE_INT);
    if ($delete_id) {
        try {
            // Delete relies on ON DELETE CASCADE for questions and question_options
            $del_stmt = $pdo->prepare("DELETE FROM quizzes WHERE quiz_id = ?");
            $del_stmt->execute([$delete_id]);
            $alert_success = "Quiz successfully deleted! 🗑️";
            $action = 'list';
        } catch (PDOException $e) {
            $alert_danger = "Failed to delete quiz: " . $e->getMessage();
        }
    }
}

// -------------------------------------------------------------------------
// Process Form Submission (Create or Update)
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($alert_danger)) {
    $lesson_id = filter_input(INPUT_POST, 'lesson_id', FILTER_VALIDATE_INT);
    $total_marks = filter_input(INPUT_POST, 'total_marks', FILTER_VALIDATE_INT) ?: 10;
    $questions = $_POST['questions'] ?? [];
    $posted_quiz_id = filter_input(INPUT_POST, 'quiz_id', FILTER_VALIDATE_INT);

    if (!$lesson_id) {
        $alert_danger = "Please select a lesson to link the quiz to.";
    } elseif (empty($questions)) {
        $alert_danger = "Please add at least one question to the quiz.";
    } else {
        try {
            $pdo->beginTransaction();

            if ($posted_quiz_id) {
                // Update existing quiz info
                $upd_quiz = $pdo->prepare("UPDATE quizzes SET lesson_id = ?, total_marks = ? WHERE quiz_id = ?");
                $upd_quiz->execute([$lesson_id, $total_marks, $posted_quiz_id]);
                $quiz_id = $posted_quiz_id;
            } else {
                // Create new quiz or upsert
                $quiz_stmt = $pdo->prepare("
                    INSERT INTO quizzes (lesson_id, total_marks, questions_json)
                    VALUES (?, ?, '[]')
                    ON DUPLICATE KEY UPDATE total_marks = VALUES(total_marks)
                ");
                $quiz_stmt->execute([$lesson_id, $total_marks]);

                $get_quiz = $pdo->prepare("SELECT quiz_id FROM quizzes WHERE lesson_id = ?");
                $get_quiz->execute([$lesson_id]);
                $quiz_id = $get_quiz->fetchColumn();
            }

            // Clear old relational questions (will cascade delete question options)
            $del_stmt = $pdo->prepare("DELETE FROM questions WHERE quiz_id = ?");
            $del_stmt->execute([$quiz_id]);

            $questions_json_array = [];
            $order_num = 1;

            foreach ($questions as $q) {
                $q_type = $q['type'] ?? '';
                $q_text = trim($q['text'] ?? '');
                if (empty($q_text))
                    continue;

                // Insert question row
                $ins_q = $pdo->prepare("
                    INSERT INTO questions (quiz_id, question_text, question_type, order_num)
                    VALUES (?, ?, ?, ?)
                ");
                $ins_q->execute([$quiz_id, $q_text, $q_type, $order_num]);
                $question_id = $pdo->lastInsertId();

                // Construct compatibility json item
                $json_item = [
                    'id' => $order_num,
                    'question' => $q_text
                ];

                if ($q_type === 'single_choice') {
                    $json_item['type'] = 'mcq';
                    $choices = [];
                    $correct_idx = intval($q['single_correct'] ?? 0);
                    $opts = $q['single_options'] ?? [];

                    foreach ($opts as $idx => $opt_val) {
                        $opt_text = trim($opt_val);
                        if ($opt_text === '')
                            continue;
                        $is_correct = ($idx === $correct_idx) ? 1 : 0;

                        $ins_opt = $pdo->prepare("
                            INSERT INTO question_options (question_id, option_text, is_correct)
                            VALUES (?, ?, ?)
                        ");
                        $ins_opt->execute([$question_id, $opt_text, $is_correct]);

                        $choices[] = $opt_text;
                        if ($is_correct) {
                            $json_item['answer'] = $opt_text;
                        }
                    }
                    $json_item['choices'] = $choices;

                } elseif ($q_type === 'multiple_choice') {
                    $json_item['type'] = 'mcq_multi';
                    $choices = [];
                    $answers = [];
                    $opts = $q['multi_options'] ?? [];
                    $corrects = $q['multi_correct'] ?? [];

                    foreach ($opts as $idx => $opt_val) {
                        $opt_text = trim($opt_val);
                        if ($opt_text === '')
                            continue;
                        $is_correct = isset($corrects[$idx]) ? 1 : 0;

                        $ins_opt = $pdo->prepare("
                            INSERT INTO question_options (question_id, option_text, is_correct)
                            VALUES (?, ?, ?)
                        ");
                        $ins_opt->execute([$question_id, $opt_text, $is_correct]);

                        $choices[] = $opt_text;
                        if ($is_correct) {
                            $answers[] = $opt_text;
                        }
                    }
                    $json_item['choices'] = $choices;
                    $json_item['answers'] = $answers;

                } elseif ($q_type === 'fill_in_the_blank') {
                    $json_item['type'] = 'fill_blank';
                    $ans_text = trim($q['blank_answer'] ?? '');

                    $ins_opt = $pdo->prepare("
                        INSERT INTO question_options (question_id, option_text, is_correct)
                        VALUES (?, ?, 1)
                    ");
                    $ins_opt->execute([$question_id, $ans_text]);

                    $json_item['answer'] = $ans_text;

                } elseif ($q_type === 'drag_and_put') {
                    $json_item['type'] = 'drag_drop';
                    $items_array = [];
                    $submitted_categories = $q['categories'] ?? [];

                    foreach ($submitted_categories as $cat) {
                        $cat_name = trim($cat['name'] ?? '');
                        if ($cat_name === '') {
                            continue;
                        }
                        $items = $cat['items'] ?? [];
                        foreach ($items as $item_val) {
                            $item_text = trim($item_val);
                            if ($item_text === '') {
                                continue;
                            }

                            $ins_opt = $pdo->prepare("
                                INSERT INTO question_options (question_id, option_text, category)
                                VALUES (?, ?, ?)
                            ");
                            $ins_opt->execute([$question_id, $item_text, $cat_name]);

                            $items_array[] = [
                                'text' => $item_text,
                                'category' => $cat_name
                            ];
                        }
                    }
                    $json_item['items'] = $items_array;

                } elseif ($q_type === 'connecting_the_link') {
                    $json_item['type'] = 'matching';
                    $pairs_array = [];
                    $match_left = $q['match_left'] ?? [];
                    $match_right = $q['match_right'] ?? [];

                    foreach ($match_left as $idx => $left_val) {
                        $left_text = trim($left_val);
                        $right_text = trim($match_right[$idx] ?? '');
                        if (empty($left_text))
                            continue;

                        $ins_opt = $pdo->prepare("
                            INSERT INTO question_options (question_id, option_text, matching_pair)
                            VALUES (?, ?, ?)
                        ");
                        $ins_opt->execute([$question_id, $left_text, $right_text]);

                        $pairs_array[] = [
                            'left' => $left_text,
                            'right' => $right_text
                        ];
                    }
                    $json_item['pairs'] = $pairs_array;
                }

                $questions_json_array[] = $json_item;
                $order_num++;
            }

            // Update questions_json on quizzes table
            $questions_json = json_encode($questions_json_array, JSON_UNESCAPED_UNICODE);
            $upd_quiz = $pdo->prepare("UPDATE quizzes SET questions_json = ? WHERE quiz_id = ?");
            $upd_quiz->execute([$questions_json, $quiz_id]);

            $pdo->commit();
            $alert_success = "Quiz saved and synchronized successfully! 🚀";
            $action = 'list';
        } catch (Exception $e) {
            $pdo->rollBack();
            $alert_danger = "Failed to save quiz: " . $e->getMessage();
        }
    }
}

// -------------------------------------------------------------------------
// Load Edit Data if editing
// -------------------------------------------------------------------------
$edit_quiz = null;
$edit_questions = [];
if ($action === 'edit' && isset($_GET['quiz_id'])) {
    $quiz_id = filter_input(INPUT_GET, 'quiz_id', FILTER_VALIDATE_INT);
    if ($quiz_id) {
        try {
            $q_stmt = $pdo->prepare("SELECT * FROM quizzes WHERE quiz_id = ?");
            $q_stmt->execute([$quiz_id]);
            $edit_quiz = $q_stmt->fetch();

            if ($edit_quiz) {
                $qs_stmt = $pdo->prepare("SELECT * FROM questions WHERE quiz_id = ? ORDER BY order_num ASC");
                $qs_stmt->execute([$quiz_id]);
                $edit_questions = $qs_stmt->fetchAll();

                foreach ($edit_questions as &$q) {
                    $opt_stmt = $pdo->prepare("SELECT option_text, is_correct, category, matching_pair FROM question_options WHERE question_id = ?");
                    $opt_stmt->execute([$q['question_id']]);
                    $q['options'] = $opt_stmt->fetchAll();
                }
            } else {
                $alert_danger = "Quiz not found.";
                $action = 'list';
            }
        } catch (PDOException $e) {
            $alert_danger = "Error loading quiz details: " . $e->getMessage();
        }
    }
}

// -------------------------------------------------------------------------
// Load data lists for views
// -------------------------------------------------------------------------
$lessons = [];
$quizzes_list = [];
try {
    // Load lessons dropdown list
    $stmt = $pdo->query("
        SELECT l.lesson_id, l.title, s.subject_name 
        FROM lessons l 
        JOIN subjects s ON l.subject_id = s.subject_id 
        ORDER BY s.subject_name ASC, l.order_num ASC
    ");
    $lessons = $stmt->fetchAll();

    // Load active quizzes list
    $quizzes_list = $pdo->query("
        SELECT q.quiz_id, q.total_marks, l.title AS lesson_title, s.subject_name,
               (SELECT COUNT(*) FROM questions WHERE quiz_id = q.quiz_id) AS question_count
        FROM quizzes q
        JOIN lessons l ON q.lesson_id = l.lesson_id
        JOIN subjects s ON l.subject_id = s.subject_id
        ORDER BY s.subject_name ASC, l.order_num ASC
    ")->fetchAll();
} catch (PDOException $e) {
    $alert_danger = "Error fetching dashboard dataset: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Quiz Manager - Interactive E-Learning System</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        .question-card {
            background-color: #f8fafc;
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            position: relative;
        }

        .question-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 10px;
        }

        .question-card-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .btn-delete-q {
            background-color: #ef4444;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 6px 12px;
            font-weight: 700;
            cursor: pointer;
        }

        .btn-delete-q:hover {
            background-color: #dc2626;
        }

        .option-item-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
        }

        .dynamic-sub-row {
            display: flex;
            gap: 10px;
            margin-bottom: 8px;
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
                <a href="curriculum.php" class="sidebar-link">📖 Curriculum Manager</a>
            </li>
            <li class="sidebar-menu-item">
                <a href="quizzes.php" class="sidebar-link active">📝 Quiz Builder</a>
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
            <div class="topbar-title">Advanced Quiz Manager</div>
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

            <?php if ($action === 'list'): ?>
                <!-- CRUD Overview Dashboard Table -->
                <div class="admin-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <div class="admin-card-title">All Interactive Quizzes</div>
                        <a href="quizzes.php?action=new" class="btn-solid-blue"
                            style="text-decoration: none; padding: 8px 16px; height: auto;">➕ Create New Quiz</a>
                    </div>

                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Subject Module</th>
                                    <th>Linked Lesson Title</th>
                                    <th>Marks (XP)</th>
                                    <th>Questions Count</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($quizzes_list)): ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; color: var(--text-muted);">No quizzes built
                                            yet. Start by clicking "Create New Quiz"!</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($quizzes_list as $qz): ?>
                                        <tr>
                                            <td>#<?php echo $qz['quiz_id']; ?></td>
                                            <td><strong><?php echo htmlspecialchars($qz['subject_name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($qz['lesson_title']); ?></td>
                                            <td>⭐ <?php echo $qz['total_marks']; ?></td>
                                            <td><?php echo $qz['question_count']; ?></td>
                                            <td>
                                                <a href="quizzes.php?action=edit&quiz_id=<?php echo $qz['quiz_id']; ?>"
                                                    class="btn-sm btn-solid-blue"
                                                    style="text-decoration: none; margin-right: 5px;">✏️ Edit</a>
                                                <a href="quizzes.php?action=delete&quiz_id=<?php echo $qz['quiz_id']; ?>"
                                                    class="btn-sm btn-delete-q" style="text-decoration: none;"
                                                    onclick="return confirm('Are you sure you want to delete this quiz? This will permanently erase the configuration and all recorded student marks.');">
                                                    🗑️ Delete
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php elseif ($action === 'new' || $action === 'edit'): ?>
                <!-- Creation & Edit Form Interface -->
                <div class="admin-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <div class="admin-card-title">
                            <?php echo ($action === 'edit') ? 'Edit Quiz #' . $edit_quiz['quiz_id'] : 'Create Interactive Quiz'; ?>
                        </div>
                        <a href="quizzes.php" class="btn-sm btn-solid-blue"
                            style="background-color: var(--text-secondary); text-decoration: none; padding: 6px 12px; height: auto;">Back
                            to List</a>
                    </div>

                    <form method="POST" action="quizzes.php">
                        <?php if ($action === 'edit'): ?>
                            <input type="hidden" name="quiz_id" value="<?php echo $edit_quiz['quiz_id']; ?>">
                        <?php endif; ?>

                        <div class="admin-grid-two-col" style="grid-gap: 20px; margin-bottom: 25px;">
                            <div class="form-group">
                                <label class="form-label" for="lesson_id">Link to Lesson Module</label>
                                <select id="lesson_id" name="lesson_id" class="form-control" required>
                                    <option value="">Select Lesson...</option>
                                    <?php foreach ($lessons as $les):
                                        $selected = ($edit_quiz && $edit_quiz['lesson_id'] == $les['lesson_id']) ? 'selected' : '';
                                        ?>
                                        <option value="<?php echo $les['lesson_id']; ?>" <?php echo $selected; ?>>
                                            [<?php echo htmlspecialchars($les['subject_name']); ?>]
                                            <?php echo htmlspecialchars($les['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="total_marks">Total Quiz Marks (XP Equivalent)</label>
                                <input type="number" id="total_marks" name="total_marks" class="form-control"
                                    value="<?php echo $edit_quiz ? $edit_quiz['total_marks'] : '10'; ?>" min="1" required>
                            </div>
                        </div>

                        <!-- Dynamic Questions Builder Container -->
                        <div id="questions_container"></div>

                        <!-- Actions -->
                        <div style="display: flex; gap: 15px; margin-top: 25px;">
                            <button type="button" class="btn-solid-blue"
                                style="cursor: pointer; padding: 10px 20px; height: auto;" onclick="addQuestionCard()">
                                ➕ Add Question Card
                            </button>
                            <button type="submit" class="form-control btn-solid-blue"
                                style="font-weight: 700; cursor: pointer; height: auto; padding: 10px 20px; background-color: var(--success-color); width: auto;">
                                Save & Publish Quiz 🚀
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- JavaScript Dynamic UI Builder -->
    <script>
        let questionCount = 0;
        const initialQuestions = <?php echo ($action === 'edit' && !empty($edit_questions)) ? json_encode($edit_questions) : 'null'; ?>;

        function escapeHtml(text) {
            if (!text) return '';
            return text.toString()
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        function addQuestionCard(data = null) {
            const container = document.getElementById('questions_container');
            const cardId = questionCount;
            const type = data ? data.question_type : 'single_choice';
            const text = data ? data.question_text : '';

            const cardHtml = `
                <div class="question-card" id="q_card_${cardId}">
                    <div class="question-card-header">
                        <span class="question-card-title">Question #${cardId + 1}</span>
                        <button type="button" class="btn-delete-q" onclick="removeQuestionCard(${cardId})">❌ Remove</button>
                    </div>
                    
                    <div class="admin-grid-two-col" style="grid-gap: 15px; margin-bottom: 15px;">
                        <div class="form-group">
                            <label class="form-label">Question Type</label>
                            <select name="questions[${cardId}][type]" class="form-control" onchange="changeQuestionType(${cardId}, this.value)" required>
                                <option value="single_choice" ${type === 'single_choice' ? 'selected' : ''}>Single Choice (Radio Buttons)</option>
                                <option value="multiple_choice" ${type === 'multiple_choice' ? 'selected' : ''}>Multiple Choice (Checkboxes)</option>
                                <option value="fill_in_the_blank" ${type === 'fill_in_the_blank' ? 'selected' : ''}>Fill in the Blank (Text Input)</option>
                                <option value="drag_and_put" ${type === 'drag_and_put' ? 'selected' : ''}>Drag and Put (Categorization)</option>
                                <option value="connecting_the_link" ${type === 'connecting_the_link' ? 'selected' : ''}>Connecting the Link (Matching Pairs)</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Question text / prompt</label>
                            <input type="text" name="questions[${cardId}][text]" value="${escapeHtml(text)}" placeholder="e.g. What is 5 + 3?" class="form-control" required>
                        </div>
                    </div>
                    
                    <!-- Dynamic fields container based on Type -->
                    <div id="q_fields_${cardId}"></div>
                </div>
            `;

            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = cardHtml;
            container.appendChild(tempDiv.firstElementChild);

            // Generate fields
            changeQuestionType(cardId, type, data);
            questionCount++;
            reindexQuestionHeaders();
        }

        function removeQuestionCard(id) {
            const card = document.getElementById(`q_card_${id}`);
            if (card) {
                card.remove();
                reindexQuestionHeaders();
            }
        }

        function reindexQuestionHeaders() {
            const headers = document.querySelectorAll('.question-card-title');
            headers.forEach((h, idx) => {
                h.innerText = `Question #${idx + 1}`;
            });
        }

        function changeQuestionType(index, type, data = null) {
            const container = document.getElementById(`q_fields_${index}`);
            container.innerHTML = '';
            const options = data ? data.options : [];

            if (type === 'single_choice') {
                container.innerHTML = `
                    <div class="form-group">
                        <label class="form-label">Options (Check the correct option):</label>
                        ${[0, 1, 2, 3].map(i => {
                    const optVal = options[i] ? options[i].option_text : '';
                    const isChecked = options[i] && options[i].is_correct == 1 ? 'checked' : (i === 0 && !data ? 'checked' : '');
                    return `
                                <div class="option-item-row">
                                    <input type="radio" name="questions[${index}][single_correct]" value="${i}" ${isChecked}>
                                    <input type="text" name="questions[${index}][single_options][]" value="${escapeHtml(optVal)}" placeholder="Option ${i + 1}" required class="form-control">
                                </div>
                            `;
                }).join('')}
                    </div>
                `;
            } else if (type === 'multiple_choice') {
                container.innerHTML = `
                    <div class="form-group">
                        <label class="form-label">Options (Check all correct options):</label>
                        ${[0, 1, 2, 3].map(i => {
                    const optVal = options[i] ? options[i].option_text : '';
                    const isChecked = options[i] && options[i].is_correct == 1 ? 'checked' : '';
                    return `
                                <div class="option-item-row">
                                    <input type="checkbox" name="questions[${index}][multi_correct][${i}]" value="1" ${isChecked}>
                                    <input type="text" name="questions[${index}][multi_options][]" value="${escapeHtml(optVal)}" placeholder="Option ${i + 1}" required class="form-control">
                                </div>
                            `;
                }).join('')}
                    </div>
                `;
            } else if (type === 'fill_in_the_blank') {
                const ansVal = options[0] ? options[0].option_text : '';
                container.innerHTML = `
                    <div class="form-group">
                        <label class="form-label">Correct Text Answer:</label>
                        <input type="text" name="questions[${index}][blank_answer]" value="${escapeHtml(ansVal)}" placeholder="e.g. 13" required class="form-control">
                    </div>
                `;
            } else if (type === 'drag_and_put') {
                container.innerHTML = `
                    <div class="form-group">
                        <label class="form-label" style="display:block; margin-bottom:10px; font-weight: 700;">Drag & Put (1-to-Many Categorization):</label>
                        <div id="drag_categories_container_${index}"></div>
                        <button type="button" class="btn-sm btn-solid-blue" style="cursor:pointer; margin-top: 10px;" onclick="addDragCategory(${index})">➕ Add Category Box</button>
                    </div>
                `;
                if (data && options.length > 0) {
                    // Group options by category
                    const grouped = {};
                    options.forEach(opt => {
                        const cat = opt.category || '';
                        if (!grouped[cat]) {
                            grouped[cat] = [];
                        }
                        grouped[cat].push(opt.option_text);
                    });
                    
                    for (const [catName, items] of Object.entries(grouped)) {
                        addDragCategory(index, catName, items);
                    }
                } else {
                    addDragCategory(index, 'Nouns', ['Dog', 'Table']);
                    addDragCategory(index, 'Verbs', ['Run', 'Jump']);
                }
            } else if (type === 'connecting_the_link') {
                container.innerHTML = `
                    <div class="form-group">
                        <label class="form-label" style="display:block; margin-bottom:10px;">Matching Pairs:</label>
                        <div id="match_items_container_${index}"></div>
                        <button type="button" class="btn-sm btn-solid-blue" style="cursor:pointer;" onclick="addMatchPair(${index})">➕ Add Pair</button>
                    </div>
                `;
                if (data && options.length > 0) {
                    options.forEach(opt => {
                        addMatchPair(index, opt.option_text, opt.matching_pair);
                    });
                } else {
                    addMatchPair(index);
                    addMatchPair(index);
                }
            }
        }

        function addDragCategory(qIndex, categoryName = '', items = []) {
            const container = document.getElementById(`drag_categories_container_${qIndex}`);
            const catIdx = container.querySelectorAll('.drag-category-box').length;
            
            const catBox = document.createElement('div');
            catBox.className = 'drag-category-box';
            catBox.setAttribute('data-cat-idx', catIdx);
            catBox.style.border = '1px solid var(--border-color)';
            catBox.style.borderRadius = '8px';
            catBox.style.padding = '15px';
            catBox.style.marginBottom = '15px';
            catBox.style.backgroundColor = '#f1f5f9';
            catBox.style.position = 'relative';

            catBox.innerHTML = `
                <button type="button" onclick="this.parentElement.remove(); reindexCategories(${qIndex});" class="btn-delete-q" style="position: absolute; top: 15px; right: 15px; padding: 2px 8px;">❌ Delete Box</button>
                <div class="form-group" style="margin-bottom: 12px; max-width: 80%;">
                    <label class="form-label" style="font-weight:600;">Category Box / Drop Zone Name:</label>
                    <input type="text" name="questions[${qIndex}][categories][${catIdx}][name]" value="${escapeHtml(categoryName)}" placeholder="e.g. Nouns" required class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label" style="font-weight:600;">Draggable Items:</label>
                    <div class="drag-items-list" id="drag_items_list_${qIndex}_${catIdx}" style="display: flex; flex-direction: column; gap: 8px;"></div>
                    <button type="button" class="btn-sm btn-solid-blue" style="cursor:pointer; margin-top: 8px; font-size: 0.8rem; background-color: var(--primary-light);" onclick="addDragCategoryItem(${qIndex}, ${catIdx})">➕ Add another item to this category</button>
                </div>
            `;
            container.appendChild(catBox);
            
            const listContainer = document.getElementById(`drag_items_list_${qIndex}_${catIdx}`);
            if (items.length > 0) {
                items.forEach(itemText => {
                    addDragCategoryItem(qIndex, catIdx, itemText);
                });
            } else {
                addDragCategoryItem(qIndex, catIdx);
            }
        }
        
        function addDragCategoryItem(qIndex, catIdx, itemText = '') {
            const listContainer = document.getElementById(`drag_items_list_${qIndex}_${catIdx}`);
            const itemRow = document.createElement('div');
            itemRow.style.display = 'flex';
            itemRow.style.gap = '10px';
            itemRow.style.alignItems = 'center';
            itemRow.innerHTML = `
                <input type="text" name="questions[${qIndex}][categories][${catIdx}][items][]" value="${escapeHtml(itemText)}" placeholder="e.g. Dog" required class="form-control" style="flex-grow: 1;">
                <button type="button" onclick="this.parentElement.remove()" class="btn-delete-q" style="padding: 2px 8px;">❌</button>
            `;
            listContainer.appendChild(itemRow);
        }

        function reindexCategories(qIndex) {
            const container = document.getElementById(`drag_categories_container_${qIndex}`);
            if (!container) return;
            const boxes = container.querySelectorAll('.drag-category-box');
            boxes.forEach((box, catIdx) => {
                box.setAttribute('data-cat-idx', catIdx);
                const nameInput = box.querySelector(`input[name^="questions[${qIndex}][categories]"][name$="[name]"]`);
                if (nameInput) {
                    nameInput.name = `questions[${qIndex}][categories][${catIdx}][name]`;
                }
                const itemInputs = box.querySelectorAll(`input[name^="questions[${qIndex}][categories]"][name$="[items][]"]`);
                itemInputs.forEach(input => {
                    input.name = `questions[${qIndex}][categories][${catIdx}][items][]`;
                });
                const addButton = box.querySelector(`button[onclick^="addDragCategoryItem"]`);
                if (addButton) {
                    addButton.setAttribute('onclick', `addDragCategoryItem(${qIndex}, ${catIdx})`);
                }
                const listDiv = box.querySelector(`.drag-items-list`);
                if (listDiv) {
                    listDiv.id = `drag_items_list_${qIndex}_${catIdx}`;
                }
            });
        }

        function addMatchPair(qIndex, left = '', right = '') {
            const container = document.getElementById(`match_items_container_${qIndex}`);
            const row = document.createElement('div');
            row.className = 'dynamic-sub-row';
            row.innerHTML = `
                <input type="text" name="questions[${qIndex}][match_left][]" value="${escapeHtml(left)}" placeholder="Left Side (e.g. Sun)" required class="form-control">
                <input type="text" name="questions[${qIndex}][match_right][]" value="${escapeHtml(right)}" placeholder="Right Side (e.g. Hot)" required class="form-control">
                <button type="button" onclick="this.parentElement.remove()" class="btn-delete-q" style="padding: 2px 8px;">❌</button>
            `;
            container.appendChild(row);
        }

        // Initialize view state
        document.addEventListener('DOMContentLoaded', () => {
            if (initialQuestions && initialQuestions.length > 0) {
                initialQuestions.forEach(q => {
                    addQuestionCard(q);
                });
            } else if (document.getElementById('questions_container')) {
                addQuestionCard();
            }
        });
    </script>
</body>

</html>