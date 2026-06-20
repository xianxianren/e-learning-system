<?php
/**
 * course.php (student)
 * Lists all lessons under a chosen subject.
 * Employs client-side category chips (To-Do, Completed) to filter lessons interactively.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Verify student permission role
require_role('student');

$student_id = $_SESSION['user_id'];
$avatar_url = $_SESSION['avatar_url'] ?: 'monkey';

$subject_id = filter_input(INPUT_GET, 'subject_id', FILTER_VALIDATE_INT);
if (!$subject_id) {
    header("Location: subjects.php");
    exit;
}

// 1. Fetch Subject Detail
try {
    $stmt = $pdo->prepare("SELECT * FROM subjects WHERE subject_id = ?");
    $stmt->execute([$subject_id]);
    $subject = $stmt->fetch();
    
    if (!$subject) {
        header("Location: subjects.php");
        exit;
    }
} catch (PDOException $e) {
    die("Error loading subject details.");
}

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

// 2. Fetch Lessons and join student progress states
$lessons_list = [];
try {
    $stmt = $pdo->prepare("
        SELECT l.lesson_id, l.title, l.order_num, COALESCE(sp.status, 'not_started') AS progress_status
        FROM lessons l
        LEFT JOIN student_progress sp ON l.lesson_id = sp.lesson_id AND sp.student_id = ?
        WHERE l.subject_id = ?
        ORDER BY l.order_num ASC
    ");
    $stmt->execute([$student_id, $subject_id]);
    $lessons_list = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_msg = "Could not fetch lessons. Ask your teacher for help!";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($subject['subject_name']); ?> Lessons! ✏️</title>
    <link rel="stylesheet" href="../assets/css/student.css">
    <style>
        .back-btn {
            background-color: white;
            border: 2px solid #e2ebf5;
            border-radius: var(--radius-large);
            padding: 5px 12px;
            font-size: 0.9rem;
            font-weight: 800;
            color: var(--text-color);
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            box-shadow: 0 3px 0px #cbd6e2;
        }
        .back-btn:active {
            transform: translateY(2px);
            box-shadow: 0 1px 0px #cbd6e2;
        }
        
        /* Status Filter Chips Container */
        .filter-container {
            display: flex;
            gap: 8px;
            margin: 15px 0;
        }
        .filter-chip {
            background-color: white;
            border: 3px solid #e2ebf5;
            border-radius: var(--radius-large);
            padding: 8px 16px;
            font-size: 0.9rem;
            font-weight: 800;
            color: var(--text-color);
            cursor: pointer;
            box-shadow: 0 4px 0px #cbd6e2;
            transition: all 0.1s ease;
        }
        .filter-chip.active {
            background-color: var(--color-purple);
            color: white;
            border-color: var(--color-purple);
            box-shadow: 0 4px 0px #6127af;
        }
        .filter-chip:active {
            transform: translateY(3px);
            box-shadow: 0 1px 0px #cbd6e2;
        }
        
        /* Lesson Card Status Tag styles */
        .status-tag {
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 800;
            display: inline-block;
            margin-bottom: 10px;
        }
        .status-todo {
            background-color: #e2f0ff;
            color: var(--color-blue);
            border: 1px solid #b3d7ff;
        }
        .status-completed {
            background-color: #e2ffe2;
            color: var(--color-success);
            border: 1px solid #b3ffb3;
        }
    </style>
</head>
<body>

    <!-- Header Panel -->
<body>

    <div class="app-container">
        <!-- Header Panel -->
        <div class="student-header">
            <div class="student-info">
                <a href="subjects.php" class="back-btn">⬅️ Back</a>
            </div>
            <div class="xp-badge">
                <span class="icon-points"></span>
                <span><?php echo $points; ?> Stars</span>
            </div>
        </div>

        <!-- Main Container -->
        <div class="container-mobile">
            
            <h1 style="color: var(--color-blue); font-size: 1.6rem; margin-top: 10px;"><?php echo htmlspecialchars($subject['subject_name']); ?></h1>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 4px;"><?php echo htmlspecialchars($subject['description'] ?: 'Complete lessons to earn rewards!'); ?></p>
            
            <!-- Quick Filter Chips -->
            <div class="filter-container">
                <button class="filter-chip active" onclick="applyFilter('all', this)">All Lessons 🎒</button>
                <button class="filter-chip" onclick="applyFilter('todo', this)">To-Do 📖</button>
                <button class="filter-chip" onclick="applyFilter('completed', this)">Done! 🌟</button>
            </div>

            <?php if (isset($error_msg)): ?>
                <div class="card" style="border-color: var(--color-primary); color: #d43b3b; text-align: center;">
                    <p><strong><?php echo $error_msg; ?></strong></p>
                </div>
            <?php endif; ?>

            <?php if (empty($lessons_list)): ?>
                <div class="card" style="text-align: center; border-color: #cbd6e2;">
                    <h3 style="margin-bottom: 8px;">No lessons yet! 📝</h3>
                    <p style="color: var(--text-muted);">Your teacher hasn't uploaded lessons for this subject yet.</p>
                </div>
            <?php else: ?>
                <div id="lessons_wrapper">
                    <?php foreach ($lessons_list as $lesson): 
                        $isDone = ($lesson['progress_status'] === 'completed');
                        $filterClass = $isDone ? 'completed' : 'todo';
                    ?>
                        <div class="card lesson-card-item" data-status="<?php echo $filterClass; ?>" style="border-color: <?php echo $isDone ? 'var(--color-success)' : '#e2ebf5'; ?>;">
                            
                            <!-- Status Tag -->
                            <?php if ($isDone): ?>
                                <span class="status-tag status-completed">Done! 🌟</span>
                            <?php else: ?>
                                <span class="status-tag status-todo">To-Do 📖</span>
                            <?php endif; ?>

                            <h3 style="font-size: 1.25rem; color: var(--text-color); margin-bottom: 10px;">
                                <?php echo $lesson['order_num']; ?>. <?php echo htmlspecialchars($lesson['title']); ?>
                            </h3>

                            <!-- Navigation Button -->
                            <a href="lesson.php?id=<?php echo $lesson['lesson_id']; ?>" class="btn <?php echo $isDone ? 'btn-success' : 'btn-blue'; ?>" style="width: 100%; border-radius: 12px; font-size: 1rem;">
                                <?php echo $isDone ? 'Review Lesson 🔄' : 'Start Lesson 🚀'; ?>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

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

    <script>
        /**
         * Dynamic client-side lesson filtering based on progress tags.
         * Toggles display of DOM nodes without page refreshes.
         */
        function applyFilter(status, chipElement) {
            // Update active state of chips
            const chips = document.querySelectorAll('.filter-chip');
            chips.forEach(c => c.classList.remove('active'));
            chipElement.classList.add('active');
            
            // Get all lesson cards
            const cards = document.querySelectorAll('.lesson-card-item');
            cards.forEach(card => {
                if (status === 'all') {
                    card.style.display = 'block';
                } else {
                    const cardStatus = card.dataset.status;
                    if (cardStatus === status) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                }
            });
        }
    </script>
</body>
</html>
