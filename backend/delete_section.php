<?php
require_once __DIR__ . '/session_config.php';
require_once 'database.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin']) || ($_SESSION['admin']['role'] ?? 'admin') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$pageId = $input['page_id'] ?? null;
$sectionId = $input['section_id'] ?? null;

if (!$pageId || !$sectionId) {
    echo json_encode(['success' => false, 'message' => 'Missing page_id or section_id']);
    exit;
}

try {
    $collection = $db->page_sections;
    $result = $collection->deleteOne([
        'page_id' => $pageId,
        'section_id' => $sectionId
    ]);

    echo json_encode(['success' => true, 'deleted_count' => $result->getDeletedCount()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
