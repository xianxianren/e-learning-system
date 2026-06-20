<?php
/**
 * class_leaderboard.php (student)
 * Mobile-first classmate leaderboard dashboard.
 * Queries student cohort points, ranks them desc, and highlights the top three classmates.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Verify student permissions
require_role('student');

$student_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$avatar_url = $_SESSION['avatar_url'] ?: 'monkey';

// 1. Fetch current student's class section
$class_section = "";
try {
    $stmt = $pdo->prepare("SELECT class_section FROM users WHERE user_id = ?");
    $stmt->execute([$student_id]);
    $class_section = $stmt->fetchColumn() ?: "Standard 1A";
} catch (PDOException $e) {
    // Fallback
    $class_section = "Standard 1A";
}

// 2. Fetch classmate leaderboard dataset
$leaderboard = [];
try {
    // SQL query joining users and gamification_stats, filtered by class_section
    $leaderboard_query = "
        SELECT u.user_id, u.full_name, u.avatar_url, u.class_section,
               COALESCE(gs.total_points, 0) AS total_points,
               COALESCE(gs.login_streak, 0) AS login_streak
        FROM users u
        JOIN gamification_stats gs ON u.user_id = gs.student_id
        WHERE u.role = 'student' AND u.class_section = ?
        ORDER BY gs.total_points DESC, u.full_name ASC
    ";
    $stmt = $pdo->prepare($leaderboard_query);
    $stmt->execute([$class_section]);
    $leaderboard = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_msg = "Could not fetch leaderboard ranks.";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Leaderboard - E-Learning System</title>
    <link rel="stylesheet" href="../assets/css/student.css">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@600;800;900&family=Inter:wght@600;800&display=swap"
        rel="stylesheet">

    <style>
        .leaderboard-header {
            text-align: center;
            background-color: var(--color-purple);
            border-radius: var(--radius-large);
            padding: 25px 15px;
            color: white;
            margin-bottom: 25px;
            box-shadow: var(--shadow-main);
        }

        .leaderboard-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 10px;
        }

        .leaderboard-row {
            background-color: white;
            border-radius: var(--radius-medium);
            box-shadow: var(--shadow-main);
            transition: transform 0.15s ease;
        }

        .leaderboard-row:active {
            transform: scale(0.98);
        }

        .leaderboard-row.active-student {
            border: 3px solid var(--color-primary);
            background-color: #fff5f5;
        }

        .leaderboard-cell {
            padding: 15px 10px;
            text-align: center;
            vertical-align: middle;
            font-weight: 800;
        }

        .leaderboard-cell:first-child {
            border-top-left-radius: var(--radius-medium);
            border-bottom-left-radius: var(--radius-medium);
            width: 50px;
            font-size: 1.25rem;
        }

        .leaderboard-cell:last-child {
            border-top-right-radius: var(--radius-medium);
            border-bottom-right-radius: var(--radius-medium);
            color: var(--color-secondary);
            font-size: 1.15rem;
        }

        .rank-badge {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            font-weight: 900;
        }

        .rank-1 { background-color: #fef3c7; color: #d97706; border: 2px solid #fbbf24; }
        .rank-2 { background-color: #f1f5f9; color: #475569; border: 2px solid #cbd5e1; }
        .rank-3 { background-color: #ffedd5; color: #c2410c; border: 2px solid #f97316; }

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
    </style>
</head>

<body>

    <div class="app-container">

        <!-- Kid-friendly Navigation Header -->
        <div class="header-container">
            <a href="dashboard.php" class="back-link">
                <span>🔙</span>
            </a>
            <h2 class="quiz-header-title">
                Class Leaderboard 🏆
            </h2>
            <div class="header-right-icon">🌟</div>
        </div>

        <div class="content-body" style="padding-top: 10px;">

            <div class="leaderboard-header">
                <h2 style="font-size: 1.6rem; font-weight: 900; margin-bottom: 5px; color: white;">Classmates Ranking 🥇
                </h2>
                <p style="font-size: 0.95rem; opacity: 0.9; font-weight: 800; color: white; margin: 0;">
                    Cohort: <?php echo htmlspecialchars($class_section); ?>
                </p>
            </div>

            <?php if (isset($error_msg)): ?>
                <div class="card" style="border-color: var(--color-primary); text-align: center;">
                    <p style="color: var(--color-primary); font-weight: 800;"><?php echo htmlspecialchars($error_msg); ?>
                    </p>
                </div>
            <?php else: ?>

                <table class="leaderboard-table">
                    <tbody>
                        <?php
                        $rank = 1;
                        foreach ($leaderboard as $row):
                            $isSelf = ($row['user_id'] == $student_id);

                            // Highlight top three positions with crowns / custom colors
                            $rankHtml = $rank;
                            if ($rank === 1)
                                $rankHtml = '👑';
                            elseif ($rank === 2)
                                $rankHtml = '🥈';
                            elseif ($rank === 3)
                                $rankHtml = '🥉';

                            $rankClass = "";
                            if ($rank === 1)
                                $rankClass = "rank-1";
                            elseif ($rank === 2)
                                $rankClass = "rank-2";
                            elseif ($rank === 3)
                                $rankClass = "rank-3";
                            ?>
                            <tr class="leaderboard-row <?php echo $isSelf ? 'active-student' : ''; ?>">
                                <td class="leaderboard-cell">
                                    <span class="rank-badge <?php echo $rankClass; ?>"><?php echo $rankHtml; ?></span>
                                </td>
                                <td class="leaderboard-cell" style="text-align: left; width: 60px;">
                                    <div class="student-avatar-small"
                                        style="margin: 0; width: 44px; height: 44px; font-size: 1.6rem; display: flex; align-items: center; justify-content: center; background-color: #f1f5f9; border-radius: 50%;">
                                        <?php
                                        $avatar = $row['avatar_url'] ?: 'monkey';
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
                                </td>
                                <td class="leaderboard-cell"
                                    style="text-align: left; font-size: 0.95rem; color: var(--text-color);">
                                    <?php echo htmlspecialchars($row['full_name']); ?>
                                    <?php if ($isSelf): ?>
                                        <span
                                            style="font-size:0.75rem; background-color:var(--color-primary); color:white; padding:2px 8px; border-radius:9999px; margin-left:5px; vertical-align:middle;">YOU</span>
                                    <?php endif; ?>
                                </td>
                                <td class="leaderboard-cell">
                                    ⭐ <?php echo $row['total_points']; ?>
                                </td>
                            </tr>
                            <?php
                            $rank++;
                        endforeach;
                        ?>
                    </tbody>
                </table>

            <?php endif; ?>

        </div>

        <!-- Sticky Bottom Navigation Footer -->
        <div class="bottom-nav">
            <a href="dashboard.php" class="bottom-nav-item">
                <div class="bottom-nav-icon">🏠</div>
                <div>Home</div>
            </a>
            <a href="subjects.php" class="bottom-nav-item">
                <div class="bottom-nav-icon">✏️</div>
                <div>Subjects</div>
            </a>
            <a href="class_leaderboard.php" class="bottom-nav-item active">
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
    <!-- General Student Logic dependency -->
    <script src="../assets/js/student.js"></script>
</body>

</html>