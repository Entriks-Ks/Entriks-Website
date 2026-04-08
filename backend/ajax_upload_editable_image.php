<?php
require_once __DIR__ . '/session_config.php';
require_once 'gridfs.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_FILES['image'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No image uploaded']);
    exit();
}

$imageId = uploadImageToGridFS($_FILES['image']);

if ($imageId) {
    echo json_encode(['success' => true, 'image_id' => $imageId, 'url' => 'backend/image.php?id=' . $imageId]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to upload image']);
}
