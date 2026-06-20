<?php
/**
 * student_quiz.php (student)
 * Mobile-first interactive quiz player interface.
 * Pulls relational quiz questions and options, serializes to JSON, and initializes
 * the mobile-friendly touch/drag/two-tap quiz engine.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Verify student permissions
require_role('student');

$student_id = $_SESSION['user_id'];
$quiz_id = filter_input(INPUT_GET, 'quiz_id', FILTER_VALIDATE_INT);

if (!$quiz_id) {
    die("Invalid quiz request parameters.");
}

try {
    // 1. Fetch Quiz Info
    $quiz_stmt = $pdo->prepare("
        SELECT q.quiz_id, q.lesson_id, q.total_marks, l.title AS lesson_title, l.subject_id 
        FROM quizzes q
        JOIN lessons l ON q.lesson_id = l.lesson_id
        WHERE q.quiz_id = ?
    ");
    $quiz_stmt->execute([$quiz_id]);
    $quiz = $quiz_stmt->fetch();

    if (!$quiz) {
        die("Quiz not found in database.");
    }

    // 2. Fetch Questions
    $q_stmt = $pdo->prepare("SELECT * FROM questions WHERE quiz_id = ? ORDER BY order_num ASC");
    $q_stmt->execute([$quiz_id]);
    $questions = $q_stmt->fetchAll();

    // 3. Nest Options inside each question
    $quiz_structure = [];
    foreach ($questions as $q) {
        $opt_stmt = $pdo->prepare("SELECT option_text, is_correct, category, matching_pair FROM question_options WHERE question_id = ?");
        $opt_stmt->execute([$q['question_id']]);
        $options = $opt_stmt->fetchAll();

        $quiz_structure[] = [
            'question_id' => $q['question_id'],
            'question_text' => $q['question_text'],
            'question_type' => $q['question_type'],
            'options' => $options
        ];
    }

    // Encode structure for frontend engine
    $quiz_json = json_encode($quiz_structure, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    die("Database access error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Interactive Quiz - <?php echo htmlspecialchars($quiz['lesson_title']); ?></title>
    <link rel="stylesheet" href="../assets/css/student.css">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@600;800;900&family=Inter:wght@600;800&display=swap"
        rel="stylesheet">

    <style>
        /* Kid-friendly Header Styling */
        .header-container {
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 60px;
            padding: 0 15px;
            width: 100%;
            border-bottom: 2px solid #e2ebf5;
            background-color: white;
            box-sizing: border-box;
        }

        .back-link {
            position: absolute;
            left: 15px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: var(--text-color);
            font-size: 0.75rem;
            font-weight: 800;
            line-height: 1.1;
        }

        .back-link span:first-child {
            font-size: 1.4rem;
        }

        .back-link .back-text {
            font-size: 0.65rem;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .quiz-header-title {
            margin: 0;
            text-align: center;
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--text-color);
            max-width: 65%;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .header-right-icon {
            position: absolute;
            right: 15px;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
        }

        /* Mobile Touch-Interactive Question Elements Styles */

        .quiz-option-card {
            display: block;
            width: 100%;
            background-color: white;
            border: 3px solid #e2ebf5;
            border-radius: var(--radius-medium);
            padding: 18px;
            font-family: var(--font-fun);
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--text-color);
            text-align: center;
            cursor: pointer;
            box-shadow: 0 5px 0px #cbd6e2;
            transition: all 0.1s ease;
            margin-bottom: 12px;
            box-sizing: border-box;
        }

        .quiz-option-card:active,
        .quiz-option-card.selected:active {
            transform: translateY(3px);
            box-shadow: 0 2px 0px #cbd6e2;
        }

        .quiz-option-card.selected {
            border-color: var(--color-blue);
            background-color: #ebf3ff;
            box-shadow: 0 5px 0px #1d5fc6;
            color: var(--color-blue);
        }

        .quiz-option-card.correct-outline {
            border-color: var(--color-success);
            background-color: #e8f9ee;
            box-shadow: 0 5px 0px #2a9b51;
            color: var(--color-success);
        }

        /* Fill Blank Styles */
        .fill-blank-wrapper {
            padding: 10px 0;
            width: 100%;
            box-sizing: border-box;
        }

        /* Drag and Drop Styles */
        .drag-items-pool {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
            padding: 15px;
            background-color: #f8fafc;
            border: 3px dashed #cbd5e1;
            border-radius: var(--radius-medium);
            margin-bottom: 20px;
            min-height: 80px;
            align-items: center;
            width: 100%;
            box-sizing: border-box;
        }

        .drag-item {
            background-color: white;
            border: 3px solid #e2ebf5;
            border-radius: 9999px;
            padding: 10px 20px;
            font-weight: 800;
            color: var(--text-color);
            box-shadow: 0 4px 0px #cbd6e2;
            cursor: grab;
            user-select: none;
            font-size: 0.95rem;
            touch-action: none;
            /* Disables standard scrolling for touchscreen dragging */
        }

        .drag-item:active {
            cursor: grabbing;
        }

        .buckets-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            width: 100%;
            box-sizing: border-box;
        }

        .category-bucket {
            background-color: #fff8eb;
            border: 3px dashed var(--color-secondary);
            border-radius: var(--radius-medium);
            padding: 12px;
            display: flex;
            flex-direction: column;
            min-height: 140px;
            box-shadow: var(--shadow-main);
            box-sizing: border-box;
        }

        .category-bucket:nth-child(even) {
            background-color: #eefbee;
            border-color: var(--color-success);
        }

        .bucket-title {
            text-align: center;
            font-weight: 800;
            color: var(--text-color);
            font-size: 1rem;
            margin-bottom: 10px;
            border-bottom: 2px solid rgba(0, 0, 0, 0.05);
            padding-bottom: 5px;
        }

        .bucket-items-list {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            gap: 8px;
            align-items: center;
            justify-content: flex-start;
            padding-top: 5px;
        }

        .bucket-items-list .drag-item {
            width: 90%;
            text-align: center;
            font-size: 0.85rem;
            padding: 6px 12px;
            box-sizing: border-box;
        }

        /* Connecting Links Pairing Styles */
        .matching-cols-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
            width: 100%;
            box-sizing: border-box;
        }

        .match-column {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .match-item {
            background-color: white;
            border: 3px solid #e2ebf5;
            border-radius: var(--radius-medium);
            padding: 14px;
            font-weight: 800;
            color: var(--text-color);
            text-align: center;
            cursor: pointer;
            box-shadow: 0 4px 0px #cbd6e2;
            transition: all 0.1s ease;
            box-sizing: border-box;
        }

        .match-item:active {
            transform: translateY(2px);
            box-shadow: 0 2px 0px #cbd6e2;
        }

        .match-item.selected {
            border-color: var(--color-blue);
            background-color: #ebf3ff;
            box-shadow: 0 4px 0px #1d5fc6;
            color: var(--color-blue);
        }

        .match-item.matched {
            opacity: 0.4;
            pointer-events: none;
            background-color: #f1f5f9;
            border-color: #cbd5e1;
            box-shadow: none;
            transform: none;
        }

        .matched-pairs-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
            margin-bottom: 20px;
            width: 100%;
            box-sizing: border-box;
        }

        .pair-bubble {
            display: inline-flex;
            align-items: center;
            background-color: #f3e8ff;
            border: 2px solid var(--color-purple);
            border-radius: 9999px;
            padding: 6px 15px;
            font-size: 0.85rem;
            font-weight: 800;
            color: var(--color-purple);
        }

        .btn-remove-pair {
            transition: transform 0.1s ease;
        }

        .btn-remove-pair:active {
            transform: scale(0.85);
        }

        /* Feedback Banner */
        .quiz-feedback-banner {
            min-height: 40px;
            font-size: 1.15rem;
            font-weight: 800;
            text-align: center;
            margin: 15px 0;
            box-sizing: border-box;
        }

        .feedback-correct {
            color: var(--color-success);
            animation: text-pop 0.3s ease;
        }

        .feedback-wrong {
            color: var(--color-primary);
            animation: text-shake 0.3s ease;
        }
    </style>
</head>

<body>

    <div class="app-container">

        <!-- Kid-friendly Navigation Header -->
        <div class="header-container">
            <a href="lesson.php?lesson_id=<?php echo $quiz['lesson_id']; ?>" class="back-link">
                <span>🔙</span>
            </a>
            <h2 class="quiz-header-title">
                <?php echo htmlspecialchars($quiz['lesson_title']); ?> Quiz
            </h2>
            <div class="header-right-icon">🏆</div>
        </div>

        <div class="content-body" style="padding-top: 10px; width: 100%; box-sizing: border-box;">

            <!-- Progress Status Bar -->
            <div class="progress-bar-container" style="height: 18px; margin-bottom: 5px;">
                <div class="progress-bar-fill" id="quiz_progress_bar_fill" style="width: 0%;"></div>
            </div>
            <div id="quiz_progress_text"
                style="font-size: 0.85rem; font-weight: 800; color: var(--text-muted); text-align: right; margin-bottom: 15px;">
                Loading...
            </div>

            <!-- Quiz Question Bubble Card -->
            <div class="continue-card"
                style="background-color: var(--color-purple); box-shadow: none; margin-bottom: 20px; text-align: center; padding: 25px 20px;">
                <h3 id="quiz_question_text"
                    style="font-size: 1.35rem; font-weight: 900; line-height: 1.3; margin: 0; color: white;">
                    Loading question...
                </h3>
            </div>

            <!-- Dynamic Question Form Container -->
            <div id="quiz_options_container" style="width: 100%; min-height: 200px; box-sizing: border-box;">
                <!-- Loaded dynamically by JavaScript engine -->
            </div>

            <!-- Validation Feedback Area -->
            <div class="quiz-feedback-banner" id="quiz_feedback_banner"></div>

            <!-- Submit Confirmation Button -->
            <button type="button" id="quiz_submit_btn" class="btn btn-primary"
                style="width: 100%; font-size: 1.25rem; padding: 15px 0; margin-bottom: 20px; display: none;">
                Check Answer! 🌟
            </button>

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

    <!-- JavaScript Interactive Engine -->
    <script src="../assets/js/quiz_engine.js"></script>

    <!-- Initialize active player engine -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const engine = new QuizEngine({
                quizId: <?php echo $quiz['quiz_id']; ?>,
                lessonId: <?php echo $quiz['lesson_id']; ?>,
                totalMarks: <?php echo $quiz['total_marks']; ?>,
                questions: <?php echo $quiz_json; ?>
            });

            // Start quiz questions loop
            engine.start();

            // Link confirmation triggers
            const submitBtn = document.getElementById('quiz_submit_btn');
            submitBtn.addEventListener('click', () => {
                engine.checkAnswer();
            });
        });
    </script>
</body>

</html>