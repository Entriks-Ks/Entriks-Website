<?php
require_once dirname(__DIR__) . '/session_config.php';
require '../database.php';

if (!isset($_SESSION['admin'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$userRole = $_SESSION['admin']['position'] ?? 'Editor';
$userPerms = $_SESSION['admin']['permissions'] ?? [];
if ($userPerms instanceof \MongoDB\Model\BSONArray) {
    $userPerms = $userPerms->getArrayCopy();
} else {
    $userPerms = (array) $userPerms;
}
$isAdmin = ($_SESSION['admin']['role'] ?? 'admin') === 'admin';
if ($isAdmin) {
    $userRole = 'Admin';
}
$hasBlogAccess = $isAdmin || in_array('blog', $userPerms) || in_array($userRole, ['Content Manager', 'Author']);

if (!$hasBlogAccess) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Accept both POST and GET for id
$postId = $_POST['id'] ?? $_GET['id'] ?? null;
if (!$postId) {
    echo json_encode(['success' => false, 'error' => 'Missing post id']);
    exit;
}
try {
    $filter = ['_id' => new MongoDB\BSON\ObjectId($postId)];
    if ($userRole === 'Author') {
        $filter['author_email'] = $_SESSION['admin']['email'] ?? 'unknown';
    }
    $result = $db->blog->updateOne($filter, [
        '$set' => ['status' => 'archived']
    ]);
    echo json_encode(['success' => $result->getModifiedCount() > 0]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
