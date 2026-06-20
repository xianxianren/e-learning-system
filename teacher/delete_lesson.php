<?php
/**
 * delete_lesson.php (teacher)
 * Securely deletes a lesson and its physical video asset from server.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Verify teacher permissions
require_role('teacher');

$lesson_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($lesson_id) {
    try {
        // Retrieve the old video_url
        $stmt = $pdo->prepare("SELECT video_url FROM lessons WHERE lesson_id = ?");
        $stmt->execute([$lesson_id]);
        $video_url = $stmt->fetchColumn();

        if ($video_url) {
            $video_path = __DIR__ . '/../' . $video_url;
            if (file_exists($video_path) && is_file($video_path)) {
                unlink($video_path);
            }
        }

        // Delete from DB (DB ON DELETE CASCADE wipes linked quizzes)
        $delete_stmt = $pdo->prepare("DELETE FROM lessons WHERE lesson_id = ?");
        $delete_stmt->execute([$lesson_id]);

    } catch (PDOException $e) {
        // Silent or redirect with error code
    }
}

header("Location: curriculum.php");
exit;
