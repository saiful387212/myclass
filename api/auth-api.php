<?php
/**
 * Auth API Endpoint
 */

require_once __DIR__ . '/../config.php';

// Only allow AJAX requests
if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_request':
        $id = $_GET['id'] ?? 0;
        
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Invalid request ID']);
            exit;
        }
        
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT r.*, a.full_name as reviewed_by_name 
            FROM registration_requests r 
            LEFT JOIN admins a ON r.reviewed_by = a.id 
            WHERE r.id = ?
        ");
        $stmt->execute([$id]);
        $request = $stmt->fetch();
        
        if ($request) {
            echo json_encode(['success' => true, 'request' => $request]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Request not found']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
?>