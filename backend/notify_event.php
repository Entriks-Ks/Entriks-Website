<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$type = $_POST['type'] ?? null;
$item_title = $_POST['item_title'] ?? null;
$item_id = $_POST['item_id'] ?? null;

if (!$type) {
    echo json_encode(['success' => false, 'error' => 'Missing type']);
    exit;
}

try {
    $data = [
        'type' => $type,
        'item_title' => $item_title,
        'timestamp' => new MongoDB\BSON\UTCDateTime(),
        'created_at' => new MongoDB\BSON\UTCDateTime()
    ];
    
    if ($item_id) {
        try {
            $data['item_id'] = new MongoDB\BSON\ObjectId($item_id);
        } catch (Exception $e) {
            $data['item_id'] = $item_id;
        }
    }
    
    $db->notifications->insertOne($data);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
