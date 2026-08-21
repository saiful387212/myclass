<?php
/**
 * Announcement API
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

header('Content-Type: application/json');

$db = Database::getInstance();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Get user info
$userId = Session::get('user_id');
$userType = Session::get('user_type');

switch ($action) {
    case 'mark_read':
        $data = json_decode(file_get_contents('php://input'), true);
        $announcementId = $data['announcement_id'] ?? 0;
        $csrfToken = $data['csrf_token'] ?? '';
        
        // Verify CSRF
        if (!Security::verifyCSRFToken($csrfToken)) {
            echo json_encode(['success' => false, 'message' => 'Invalid security token']);
            exit;
        }
        
        if (!$announcementId) {
            echo json_encode(['success' => false, 'message' => 'Invalid announcement ID']);
            exit;
        }
        
        // Check if already read
        $stmt = $db->prepare("
            SELECT id FROM announcement_reads 
            WHERE announcement_id = ? AND user_id = ? AND user_type = ?
        ");
        $stmt->execute([$announcementId, $userId, $userType]);
        
        if (!$stmt->fetch()) {
            // Mark as read
            $stmt = $db->prepare("
                INSERT INTO announcement_reads (announcement_id, user_id, user_type) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$announcementId, $userId, $userType]);
            
            // Increment view count
            $stmt = $db->prepare("
                UPDATE announcements SET views_count = views_count + 1 
                WHERE id = ?
            ");
            $stmt->execute([$announcementId]);
        }
        
        echo json_encode(['success' => true, 'message' => 'Marked as read']);
        break;
        
    case 'mark_all_read':
        $data = json_decode(file_get_contents('php://input'), true);
        $csrfToken = $data['csrf_token'] ?? '';
        
        if (!Security::verifyCSRFToken($csrfToken)) {
            echo json_encode(['success' => false, 'message' => 'Invalid security token']);
            exit;
        }
        
        // Get all unread announcements for this user
        $stmt = $db->prepare("
            SELECT a.id 
            FROM announcements a
            LEFT JOIN announcement_reads ar ON a.id = ar.announcement_id 
                AND ar.user_id = ? AND ar.user_type = ?
            WHERE a.status = 'published'
            AND ar.id IS NULL
        ");
        $stmt->execute([$userId, $userType]);
        $unreadIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($unreadIds as $id) {
            $stmt = $db->prepare("
                INSERT INTO announcement_reads (announcement_id, user_id, user_type) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$id, $userId, $userType]);
            
            $stmt = $db->prepare("
                UPDATE announcements SET views_count = views_count + 1 
                WHERE id = ?
            ");
            $stmt->execute([$id]);
        }
        
        echo json_encode(['success' => true, 'message' => 'All marked as read', 'count' => count($unreadIds)]);
        break;
        
    case 'unread_count':
        $stmt = $db->prepare("
            SELECT COUNT(*) 
            FROM announcements a
            LEFT JOIN announcement_reads ar ON a.id = ar.announcement_id 
                AND ar.user_id = ? AND ar.user_type = ?
            WHERE a.status = 'published'
            AND ar.id IS NULL
        ");
        $stmt->execute([$userId, $userType]);
        $count = $stmt->fetchColumn();
        
        echo json_encode(['success' => true, 'count' => (int)$count]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
?>