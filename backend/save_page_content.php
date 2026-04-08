<?php
require_once __DIR__ . '/session_config.php';
require_once 'database.php';

header('Content-Type: application/json');

$userPerms = $_SESSION['admin']['permissions'] ?? [];
if ($userPerms instanceof \MongoDB\Model\BSONArray) {
    $userPerms = $userPerms->getArrayCopy();
} else {
    $userPerms = (array) $userPerms;
}
$isAdmin = ($_SESSION['admin']['role'] ?? 'admin') === 'admin';
$hasCmsAccess = $isAdmin || in_array('cms', $userPerms);

if (!$hasCmsAccess) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Ensure database connection
if (!isset($db) || $db === null) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['page_id']) || !isset($input['blocks']) || !is_array($input['blocks'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing page_id or blocks array']);
    exit();
}

$pageId = $input['page_id'];
$blocks = $input['blocks'];

try {
    $collection = $db->content_blocks;

    $count = 0;
    foreach ($blocks as $key => $data) {
        $content = $data['content'] ?? '';
        $type = $data['type'] ?? 'text';
        $isDeleted = $data['is_deleted'] ?? false;

        $setData = [
            'draft_content' => $content,
            'draft_type' => $type,
            'draft_is_deleted' => $isDeleted,
            'last_updated' => new MongoDB\BSON\UTCDateTime()
        ];

        // ONLY update style if it's explicitly provided in the data
        if (isset($data['style'])) {
            $setData['draft_style'] = $data['style'];
        }

        // Upsert each block
        $collection->updateOne(
            ['page_id' => $pageId, 'block_key' => $key],
            ['$set' => $setData],
            ['upsert' => true]
        );
        $count++;
    }

    // Handle separate deleted_keys array if provided
    if (isset($input['deleted_keys']) && is_array($input['deleted_keys'])) {
        foreach ($input['deleted_keys'] as $key) {
            error_log("🗑️ MongoDB Deletion Request: Marking '$key' as is_deleted: true");
            $collection->updateOne(
                ['page_id' => $pageId, 'block_key' => $key],
                ['$set' => [
                    'draft_is_deleted' => true,
                    'last_updated' => new MongoDB\BSON\UTCDateTime()
                ]],
                ['upsert' => true]
            );
        }
    }

    echo json_encode(['success' => true, 'message' => "Saved $count blocks successfully"]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>