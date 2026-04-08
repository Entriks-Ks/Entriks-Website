<?php
require_once dirname(__DIR__) . '/session_config.php';
require '../database.php';
require_once '../gridfs.php';
require_once __DIR__ . '/../config.php';

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
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    } else {
        header('Location: ../dashboard.php');
    }
    exit;
}

if (!isset($_POST['id'])) {
    ?>
    <div style="width: 400px; margin: 60px auto; background: #fff; border-radius: 20px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); text-align: center; padding: 32px; font-family: system-ui,sans-serif;">
        <div style="background: #fee2e2; border-radius: 8px; padding: 16px 0; margin-bottom: 18px;">
            <svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='#dc2626' style='width:32px;height:32px;vertical-align:middle;'>
                <path fill-rule='evenodd' d='M9.401 3.003c1.155-2 4.043-2 5.197 0l7.355 12.748c1.154 2-.29 4.5-2.599 4.5H4.645c-2.309 0-3.752-2.5-2.598-4.5L9.4 3.003ZM12 8.25a.75.75 0 0 1 .75.75v3.75a.75.75 0 0 1-1.5 0V9a.75.75 0 0 1 .75-.75Zm0 8.25a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z' clip-rule='evenodd' />
            </svg>
        </div>
        <h2 style="font-size:1.4rem;font-weight:700;color:#111827;margin:0 0 8px 0;">Error!</h2>
        <div style="color:#6b7280;font-size:1rem;">Missing post id</div>
    </div>
    <?php
    exit;
}

$postId = $_POST['id'];
try {
    $filter = ['_id' => new MongoDB\BSON\ObjectId($postId)];
    if ($userRole === 'Author') {
        $filter['author_email'] = $_SESSION['admin']['email'] ?? 'unknown';
    }
    $post = $db->blog->findOne($filter);
    if ($post && !empty($post['image'])) {
        deleteFileFromGridFS($post['image']);
    }

    $result = $db->blog->deleteOne($filter);
    $deletedComments = $db->comments->deleteMany([
        'post_id' => new MongoDB\BSON\ObjectId($postId)
    ]);
    $commentCount = $deletedComments->getDeletedCount();
    if ($result->getDeletedCount() > 0) {
        $msgTemplate = $lang['msg_post_deleted'] ?? 'Post deleted successfully! (%d comment(s) removed)';
        $message = sprintf($msgTemplate, $commentCount);
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => $message, 'commentCount' => $commentCount, 'title' => ($lang['toast_title'] ?? 'Information')]);
            exit;
        }

        header('Location: archived.php');
        exit;
    } else {
        $errorMessage = 'Post not found or already deleted.';
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $errorMessage]);
            exit;
        }
        ?>
        <div style="width: 400px; margin: 60px auto; background: #fff; border-radius: 20px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); text-align: center; padding: 32px; font-family: system-ui,sans-serif;">
            <div style="background: #fee2e2; border-radius: 8px; padding: 16px 0; margin-bottom: 18px;">
                <svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='#dc2626' style='width:32px;height:32px;vertical-align:middle;'>
                    <path fill-rule='evenodd' d='M9.401 3.003c1.155-2 4.043-2 5.197 0l7.355 12.748c1.154 2-.29 4.5-2.599 4.5H4.645c-2.309 0-3.752-2.5-2.598-4.5L9.4 3.003ZM12 8.25a.75.75 0 0 1 .75.75v3.75a.75.75 0 0 1-1.5 0V9a.75.75 0 0 1 .75-.75Zm0 8.25a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z' clip-rule='evenodd' />
                </svg>
            </div>
            <h2 style="font-size:1.4rem;font-weight:700;color:#111827;margin:0 0 8px 0;">Error!</h2>
            <div style="color:#6b7280;font-size:1rem;"><?php echo htmlspecialchars($errorMessage); ?></div>
        </div>
        <?php
        exit;
    }
} catch (Exception $e) {
    ?>
    <div style="width: 400px; margin: 60px auto; background: #fff; border-radius: 20px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); text-align: center; padding: 32px; font-family: system-ui,sans-serif;">
        <div style="background: #fee2e2; border-radius: 8px; padding: 16px 0; margin-bottom: 18px;">
            <svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='#dc2626' style='width:32px;height:32px;vertical-align:middle;'>
                <path fill-rule='evenodd' d='M9.401 3.003c1.155-2 4.043-2 5.197 0l7.355 12.748c1.154 2-.29 4.5-2.599 4.5H4.645c-2.309 0-3.752-2.5-2.598-4.5L9.4 3.003ZM12 8.25a.75.75 0 0 1 .75.75v3.75a.75.75 0 0 1-1.5 0V9a.75.75 0 0 1 .75-.75Zm0 8.25a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z' clip-rule='evenodd' />
            </svg>
        </div>
        <h2 style="font-size:1.4rem;font-weight:700;color:#111827;margin:0 0 8px 0;">Error!</h2>
        <div style="color:#6b7280;font-size:1rem;">Failed to delete post: <?= htmlspecialchars($e->getMessage()) ?></div>
    </div>
    <?php
    exit;
}
