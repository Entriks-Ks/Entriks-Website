<?php
require_once dirname(__DIR__) . '/session_config.php';
require_once __DIR__ . '/../database.php';
if (!isset($_SESSION['admin'])) {
    header('Content-Type: application/json', true, 403);
    echo json_encode(['error' => 'Unauthorized']);
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
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Simple endpoint to save a category name into `categories` collection if not exists.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json', true, 405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$name = trim($input['name'] ?? '');
if ($name === '') {
    header('Content-Type: application/json', true, 400);
    echo json_encode(['error' => 'Empty category name']);
    exit;
}

try {
    // Ensure uniqueness (case-insensitive)
    $existing = $db->categories->findOne(['name' => new MongoDB\BSON\Regex('^' . preg_quote($name, '/') . '$', 'i')]);
    if ($existing) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'message' => 'Already exists']);
        exit;
    }

    $db->categories->insertOne(['name' => $name, 'created_at' => new MongoDB\BSON\UTCDateTime()]);
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    header('Content-Type: application/json', true, 500);
    echo json_encode(['error' => 'Failed to save category', 'detail' => $e->getMessage()]);
}
