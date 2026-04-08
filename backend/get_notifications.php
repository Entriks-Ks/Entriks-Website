<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/database.php';

if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/session_config.php';
}

if (!isset($_SESSION['admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

// Run automatic cleanup
require_once __DIR__ . '/cleanup_notifications.php';
cleanup_old_notifications($db);

$since = $_GET['since'] ?? null;
$limit = isset($_GET['limit']) ? min(50, intval($_GET['limit'])) : 10;

$filter = [];
if ($since) {
    try {
        // Query by created_at field which is what notifications use
        $filter['created_at'] = ['$gt' => new MongoDB\BSON\UTCDateTime(intval($since))];
    } catch (Exception $e) {
        // Fallback or error
    }
}

// EXCLUDE 'watched_comments' as per checking
$filter['type'] = ['$ne' => 'watched_comments'];

if (!$db) {
    throw new Exception('Database connection not established');
}

try {
    $cursor = $db->notifications->find($filter, [
        'sort' => ['created_at' => -1],
        'limit' => $limit
    ]);

    $notifications = [];
    foreach ($cursor as $doc) {
        // Get timestamp - use created_at if timestamp doesn't exist
        $timestamp = isset($doc['timestamp']) ? (string) $doc['timestamp'] : (string) $doc['created_at'];

        $notifications[] = [
            'id' => (string) $doc['_id'],
            'type' => $doc['type'],
            'item_title' => $doc['item_title'] ?? 'Something',
            'timestamp' => $timestamp,
            'created_at' => $doc['created_at'] ?? ''
        ];
    }

    // Reverse to get them in chronological order for the dashboard to process
    $notifications = array_reverse($notifications);

    $serverTime = new MongoDB\BSON\UTCDateTime();
    echo json_encode(['success' => true, 'notifications' => $notifications, 'server_time' => (string) $serverTime]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
