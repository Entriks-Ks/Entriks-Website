<?php
require_once dirname(__DIR__) . '/session_config.php';
require_once dirname(__DIR__) . '/gridfs.php';
if (!isset($_SESSION['admin'])) {
    header('Content-Type: application/json', true, 403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$userPerms = $_SESSION['admin']['permissions'] ?? [];
if ($userPerms instanceof \MongoDB\Model\BSONArray) {
    $userPerms = $userPerms->getArrayCopy();
} else {
    $userPerms = (array) $userPerms;
}
$isAdmin = ($_SESSION['admin']['role'] ?? 'admin') === 'admin';
$hasBlogAccess = $isAdmin || in_array('blog', $userPerms);

if (!$hasBlogAccess) {
    header('Content-Type: application/json', true, 403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'No valid file uploaded']);
    exit;
}

// Use the existing helper from gridfs.php
$imageId = uploadImageToGridFS($_FILES['image']);

if ($imageId) {
    // Return relative path that the frontend can use
    // Assuming backend/image.php is where images are served
    $url = '../image.php?id=' . $imageId;
    echo json_encode(['success' => true, 'url' => $url, 'filename' => $_FILES['image']['name']]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to save image to GridFS']);
}
