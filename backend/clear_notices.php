<?php
require_once __DIR__ . '/session_config.php';
require "database.php";

header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION["admin"])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    // Delete all notifications
    $result = $db->notifications->deleteMany([]);
    
    echo json_encode([
        'success' => true,
        'deleted_count' => $result->getDeletedCount()
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to clear notifications: ' . $e->getMessage()
    ]);
}
