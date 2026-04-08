<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/database.php';

header('Content-Type: application/json');

try {
    // 1. Clear Blog Views
    $blogResult = $db->blog->updateMany(
        [],
        [
            '$set' => ['views' => 0],
            '$unset' => ['views_history' => ""]
        ]
    );

    // 2. Clear Main Views
    $mainViewsResult = $db->main_views->deleteMany([]);

    // 3. Clear Notifications (view related)
    $notificationsResult = $db->notifications->deleteMany([
        'type' => ['$in' => ['blog_view', 'main_view']]
    ]);

    echo json_encode([
        'success' => true,
        'blog_updated' => $blogResult->getModifiedCount(),
        'main_views_deleted' => $mainViewsResult->getDeletedCount(),
        'notifications_deleted' => $notificationsResult->getDeletedCount(),
        'message' => 'All view data has been cleared.'
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
