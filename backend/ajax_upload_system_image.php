<?php
require_once 'session_config.php';
if (!isset($_SESSION['admin'])) {
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

$file = $_FILES['image'];
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/x-icon', 'image/vnd.microsoft.icon'];

if (!in_array($file['type'], $allowedTypes)) {
    echo json_encode(['success' => false, 'error' => 'Invalid file type. Allowed: JPEG, PNG, GIF, WebP, ICO']);
    exit;
}

if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['success' => false, 'error' => 'File too large (max 5MB)']);
    exit;
}

// Create upload directory if it doesn't exist
$uploadDir = __DIR__ . '/../assets/img/uploads/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Generate unique filename - check if file exists, if so add counter
$filename = preg_replace('/[^a-zA-Z0-9.-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$uniqueFilename = $filename . '.' . $extension;
$counter = 1;

// If file exists, add counter
while (file_exists($uploadDir . $uniqueFilename)) {
    $uniqueFilename = $filename . '_' . $counter . '.' . $extension;
    $counter++;
}

$uploadPath = $uploadDir . $uniqueFilename;

if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
    // Return relative path from root
    $url = 'assets/img/uploads/' . $uniqueFilename;
    echo json_encode(['success' => true, 'url' => $url, 'filename' => $file['name']]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to save file']);
}
?>
