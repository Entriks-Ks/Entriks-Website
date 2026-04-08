<?php
require_once __DIR__ . '/database.php';

function cleanup_old_notifications($db)
{
    if (!$db) {
        return ['success' => false, 'error' => 'Database connection not available'];
    }

    try {
        $twentyFourHoursAgo = (time() - 24 * 3600) * 1000;

        $result = $db->notifications->deleteMany([
            'created_at' => ['$lt' => $twentyFourHoursAgo],
            'type' => ['$ne' => 'watched_comments']
        ]);

        return [
            'success' => true,
            'deleted_count' => $result->getDeletedCount(),
            'threshold' => $twentyFourHoursAgo
        ];
    } catch (Exception $e) {
        error_log('Cleanup Notifications Error: ' . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// If run directly
if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) {
    header('Content-Type: application/json');
    $res = cleanup_old_notifications($db);
    echo json_encode($res);
}