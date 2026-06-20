<?php
/**
 * update_progress.php (student)
 * Background API called via fetch to update student progression status.
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
    $status = filter_input(INPUT_POST, 'status', FILTER_DEFAULT);
    
    if ($lesson_id && in_array($status, ['not_started', 'in_progress', 'completed'])) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO student_progress (student_id, lesson_id, status, last_accessed)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE status = ?, last_accessed = NOW()
            ");
            $stmt->execute([$student_id, $lesson_id, $status, $status]);
            
            echo json_encode(['success' => true, 'status' => $status]);
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
