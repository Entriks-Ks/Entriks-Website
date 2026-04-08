<?php
require_once dirname(__DIR__) . '/session_config.php';
require_once '../database.php';

/**
 * @var \MongoDB\Database $db
 */

use MongoDB\BSON\ObjectId;

header('Content-Type: application/json');

if (!isset($_SESSION['admin'])) {
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
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$id = $_POST['id'] ?? null;
$status = $_POST['status'] ?? null;

if (!$id || !in_array($status, ['draft', 'published'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

try {
    // Check current status and featured first
    $current = $db->blog->findOne(['_id' => new ObjectId($id)], ['projection' => ['status' => 1, 'featured' => 1]]);

    // If status unchanged and either it's published (no featured change needed)
    // or featured is already false for non-published, then nothing to do.
    if ($current && isset($current->status) && $current->status === $status) {
        if ($status === 'published' || empty($current->featured)) {
            echo json_encode(['success' => true, 'message' => 'No change']);
            exit;
        }
        // else fall through to clear featured (status unchanged but featured true and status not published)
    }

    // Prepare update: always set status; if setting to non-published, also clear featured flag.
    $set = ['status' => $status];
    if ($status !== 'published') {
        $set['featured'] = false;
    }

    $result = $db->blog->updateOne(
        ['_id' => new ObjectId($id)],
        ['$set' => $set]
    );
    if ($result->getModifiedCount() > 0) {
        // Regenerate featured cache so unpublished posts are immediately removed
        // from featured.json and no longer appear on the main pages.
        include __DIR__ . '/cache_featured.php';
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Not modified']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
