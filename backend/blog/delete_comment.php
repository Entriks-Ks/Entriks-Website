<?php
require_once dirname(__DIR__) . '/session_config.php';
require '../database.php';
require '../config.php';

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
    header('Location: ../dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ids = [];
    try {
        // Handle bulk or single deletion
        if (isset($_POST['comment_ids']) && is_array($_POST['comment_ids'])) {
            foreach ($_POST['comment_ids'] as $id) {
                $ids[] = new MongoDB\BSON\ObjectId($id);
            }
        } elseif (isset($_POST['comment_id'])) {
            $ids[] = new MongoDB\BSON\ObjectId($_POST['comment_id']);
        }

        if (!empty($ids)) {
            // Recursively collect these comments and all descendant replies
            $toDelete = $ids;
            $queue = $ids;

            // Basic deduplication to start
            $seenIds = [];
            foreach ($ids as $i)
                $seenIds[(string) $i] = true;

            while (!empty($queue)) {
                $childrenCursor = $db->comments->find(['parent_id' => ['$in' => $queue]]);
                $next = [];
                foreach ($childrenCursor as $child) {
                    $childId = $child['_id'];
                    $sChildId = (string) $childId;

                    if (!isset($seenIds[$sChildId])) {
                        $seenIds[$sChildId] = true;
                        $toDelete[] = $childId;
                        $next[] = $childId;
                    }
                }
                $queue = $next;
            }

            // Delete all collected comment ids
            $deleted = $db->comments->deleteMany(['_id' => ['$in' => $toDelete]]);
            $deletedCount = $deleted->getDeletedCount();

            $_SESSION['toast_message'] = $lang['msg_comment_deleted'] ?? 'Comment deleted successfully!';
            $_SESSION['toast_type'] = 'success';
        }
    } catch (Exception $e) {
        $_SESSION['toast_message'] = sprintf($lang['msg_comment_delete_fail'] ?? 'Failed to delete comments: %s', $e->getMessage());
        $_SESSION['toast_type'] = 'error';
    }
}

header('Location: comments.php');
exit;
