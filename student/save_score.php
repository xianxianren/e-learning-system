<?php
/**
 * save_score.php (student)
 * Background API called via fetch on quiz completion.
 * Saves score details, grants gold stars (XP), updates progression, and returns metrics.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Verify student permissions
if (!is_logged_in() || $_SESSION['role'] !== 'student') {
    echo json_encode(['error' => 'Unauthorized access.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = $_SESSION['user_id'];
    $lesson_id = filter_input(INPUT_POST, 'lesson_id', FILTER_VALIDATE_INT);
    $marks_earned = filter_input(INPUT_POST, 'score', FILTER_VALIDATE_INT);
    
    if ($lesson_id && $marks_earned !== false) {
        try {
            // Find quiz linked to the lesson
            $stmt = $pdo->prepare("SELECT quiz_id, total_marks FROM quizzes WHERE lesson_id = ?");
            $stmt->execute([$lesson_id]);
            $quiz = $stmt->fetch();
            
            if (!$quiz) {
                echo json_encode(['error' => 'Quiz not found.']);
                exit;
            }
            
            $quiz_id = $quiz['quiz_id'];
            
            // Insert quiz score record
            $insertScore = $pdo->prepare("INSERT INTO quiz_scores (student_id, quiz_id, marks_earned, completed_at) VALUES (?, ?, ?, NOW())");
            $insertScore->execute([$student_id, $quiz_id, $marks_earned]);
            
            // Mark lesson progress as completed
            $progStmt = $pdo->prepare("
                INSERT INTO student_progress (student_id, lesson_id, status, last_accessed)
                VALUES (?, ?, 'completed', NOW())
                ON DUPLICATE KEY UPDATE status = 'completed', last_accessed = NOW()
            ");
            $progStmt->execute([$student_id, $lesson_id]);
            
            // Calculate XP points earned (e.g., 10 XP points per correct mark)
            $xp_earned = $marks_earned * 10;
            
            // Update gamification stats
            $statStmt = $pdo->prepare("SELECT * FROM gamification_stats WHERE student_id = ?");
            $statStmt->execute([$student_id]);
            $stats = $statStmt->fetch();
            
            $new_streak = 1;
            if ($stats) {
                $new_points = $stats['total_points'] + $xp_earned;
                $new_streak = $stats['login_streak'];
                
                $updateStats = $pdo->prepare("UPDATE gamification_stats SET total_points = ? WHERE student_id = ?");
                $updateStats->execute([$new_points, $student_id]);
            } else {
                // Insert standard gamification row
                $insertStats = $pdo->prepare("INSERT INTO gamification_stats (student_id, total_points, login_streak, last_login) VALUES (?, ?, 1, CURRENT_DATE)");
                $insertStats->execute([$student_id, $xp_earned]);
            }
            
            echo json_encode([
                'success' => true,
                'xp_earned' => $xp_earned,
                'new_streak' => $new_streak
            ]);
        } catch (PDOException $e) {
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['error' => 'Invalid parameters.']);
    }
} else {
    echo json_encode(['error' => 'Invalid request method.']);
}
?>
