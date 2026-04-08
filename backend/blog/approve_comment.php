<?php
require_once dirname(__DIR__) . '/session_config.php';

use MongoDB\BSON\ObjectId;

require '../database.php';
require '../config.php';

if (!isset($_SESSION['admin'])) {
    header('Location: ../login.php');
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment_id'])) {
    try {
        $commentId = new ObjectId($_POST['comment_id']);

        $db->comments->updateOne(
            ['_id' => $commentId],
            ['$set' => ['status' => 'approved']]
        );

        $_SESSION['toast_message'] = $lang['msg_comment_approved'] ?? 'Comment approved successfully!';
        $_SESSION['toast_type'] = 'success';
    } catch (Exception $e) {
        $_SESSION['toast_message'] = sprintf($lang['msg_comment_approve_fail'] ?? 'Failed to approve comment: %s', $e->getMessage());
        $_SESSION['toast_type'] = 'error';
    }
}

header('Location: comments.php');
exit;
