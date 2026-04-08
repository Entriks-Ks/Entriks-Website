<?php
require_once __DIR__ . '/session_config.php';
require_once 'database.php';

header('Content-Type: application/json');

error_log('[STRUCTURE SAVE] Request received');
error_log('[STRUCTURE SAVE] Session admin: ' . (isset($_SESSION['admin']) ? 'YES' : 'NO'));

if (!isset($_SESSION['admin'])) {
    http_response_code(403);
    error_log('[STRUCTURE SAVE] Unauthorized - no session');
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
    error_log('[STRUCTURE SAVE] Unauthorized - no CMS permissions');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
error_log('[STRUCTURE SAVE] Input data: ' . print_r($input, true));

if (!isset($input['page_id']) || !isset($input['structure']) || !is_array($input['structure'])) {
    http_response_code(400);
    error_log('[STRUCTURE SAVE] Missing page_id or structure array');
    echo json_encode(['success' => false, 'message' => 'Missing page_id or structure array']);
    exit();
}

$pageId = $input['page_id'];
$structure = $input['structure'];

error_log('[STRUCTURE SAVE] Page ID: ' . $pageId);
error_log('[STRUCTURE SAVE] Structure count: ' . count($structure));

try {
    $collection = $db->page_structure;

    $result = $collection->updateOne(
        ['page_id' => $pageId],
        [
            '$set' => [
                'structure' => $structure,
                'last_updated' => new MongoDB\BSON\UTCDateTime()
            ]
        ],
        ['upsert' => true]
    );

    error_log('[STRUCTURE SAVE] MongoDB result - matched: ' . $result->getMatchedCount() . ', modified: ' . $result->getModifiedCount() . ', upserted: ' . $result->getUpsertedCount());

    echo json_encode(['success' => true, 'message' => 'Structure saved successfully']);
} catch (Exception $e) {
    http_response_code(500);
    error_log('[STRUCTURE SAVE] Database error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>