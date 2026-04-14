<?php
require_once dirname(__DIR__) . '/session_config.php';
require '../database.php';
require '../config.php';

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
    header('Location: ../dashboard.php');
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id)
    die('Invalid ID');

$postObjectId = new MongoDB\BSON\ObjectId($id);
$filter = ['_id' => $postObjectId];
if ($userRole === 'Author') {
    $filter['author_email'] = $_SESSION['admin']['email'] ?? 'unknown';
}
$post = $db->blog->findOne($filter);
if (!$post)
    die('Post not found');

// 1. Delete associated image from GridFS (if exists)
if (isset($post['image'])) {
    try {
        $bucket = $db->selectGridFSBucket();
        $imageId = $post['image'] instanceof MongoDB\BSON\ObjectId
            ? $post['image']
            : new MongoDB\BSON\ObjectId((string) $post['image']);
        $bucket->delete($imageId);
    } catch (Exception $e) {
        // Image already deleted or doesn't exist, continue
    }
}

// 2. Delete all comments associated with this post
try {
    $deletedComments = $db->comments->deleteMany(['post_id' => $postObjectId]);
    $commentCount = $deletedComments->getDeletedCount();
} catch (Exception $e) {
    $commentCount = 0;
}

// 3. Delete the post itself
$db->blog->deleteOne($filter);

// 4. Regenerate featured cache so deleted post no longer appears on homepage
include 'cache_featured.php';

// Set success message
$_SESSION['toast_message'] = sprintf($lang['msg_post_deleted'] ?? 'Post deleted successfully! (Removed %d comment(s) and associated data)', $commentCount);
$_SESSION['toast_type'] = 'success';

header('Location: index.php');
exit;
?>