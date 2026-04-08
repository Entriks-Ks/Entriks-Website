<?php
require_once dirname(__DIR__) . '/session_config.php';
require '../database.php';

if (!isset($_SESSION['admin'])) {
    header('Location: ../login.php');
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

if (!isset($_POST['id'])) {
    echo json_encode(['success' => false, 'error' => 'Missing post id']);
    exit;
}

$postId = $_POST['id'];
try {
    $filter = ['_id' => new MongoDB\BSON\ObjectId($postId)];
    if ($userRole === 'Author') {
        $filter['author_email'] = $_SESSION['admin']['email'] ?? 'unknown';
    }
    $result = $db->blog->updateOne($filter, [
        '$set' => ['status' => 'draft']
    ]);
    echo json_encode(['success' => $result->getModifiedCount() > 0]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
