<?php
require_once 'session_config.php';
require_once 'database.php';

if (!isset($_SESSION['admin']) || ($_SESSION['admin']['role'] ?? 'admin') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';

    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID']);
        exit;
    }

    try {
        $objectId = new MongoDB\BSON\ObjectId($id);

        if ($id === (string) ($_SESSION['admin']['_id'] ?? $_SESSION['admin']['id'])) {
            echo json_encode(['success' => false, 'message' => 'You cannot delete yourself!']);
            exit;
        }

        $targetUser = $db->admins->findOne(['_id' => $objectId]);
        if ($targetUser && ($targetUser['role'] ?? 'admin') === 'admin') {
            echo json_encode(['success' => false, 'message' => 'Deleting administrators is not allowed for security reasons.']);
            exit;
        }

        if ($targetUser && ($targetUser['email'] ?? '') === 'admin@entriks.com') {
            echo json_encode(['success' => false, 'message' => 'The supervisor account cannot be deleted.']);
            exit;
        }

        $result = $db->admins->deleteOne(['_id' => $objectId]);

        if ($result->getDeletedCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Member deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Member not found']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
