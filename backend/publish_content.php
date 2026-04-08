<?php
require_once __DIR__ . '/session_config.php';
require_once 'database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

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
    error_log('[PUBLISH CONTENT] Unauthorized - no CMS permissions');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($db) || $db === null) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['page_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing page_id']);
    exit();
}

$pageId = $input['page_id'];

try {
    $cursor = $db->content_blocks->find([
        'page_id' => $pageId,
        '$or' => [
            ['draft_content' => ['$exists' => true]],
            ['draft_is_deleted' => ['$exists' => true]]
        ]
    ]);

    $count = 0;
    foreach ($cursor as $block) {
        $setData = [
            'last_published' => new MongoDB\BSON\UTCDateTime()
        ];
        $unsetData = [];

        if (isset($block['draft_content'])) {
            $setData['content'] = $block['draft_content'];
            $unsetData['draft_content'] = '';
        }

        if (isset($block['draft_type'])) {
            $setData['type'] = $block['draft_type'];
            $unsetData['draft_type'] = '';
        }

        if (isset($block['draft_style'])) {
            $setData['style'] = $block['draft_style'];
            $unsetData['draft_style'] = '';
        }

        if (isset($block['draft_is_deleted'])) {
            $setData['is_deleted'] = $block['draft_is_deleted'];
            $unsetData['draft_is_deleted'] = '';
        }

        $op = ['$set' => $setData];
        if (!empty($unsetData)) {
            $op['$unset'] = $unsetData;
        }

        $db->content_blocks->updateOne(['_id' => $block['_id']], $op);
        $count++;
    }

    echo json_encode(['success' => true, 'message' => "Published $count blocks successfully"]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}