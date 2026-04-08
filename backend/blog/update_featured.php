<?php
require_once dirname(__DIR__) . '/session_config.php';
require "../database.php";

header('Content-Type: application/json');

if (!isset($_SESSION["admin"])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';
    $featured = $_POST['featured'] ?? '0'; // 'true' or '1' from JS

    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'Missing ID']);
        exit;
    }

    try {
        $objectId = new MongoDB\BSON\ObjectId($id);
        $isFeatured = ($featured === 'true' || $featured === '1');

        // Server-side guard: only allow setting featured=true when post is published.
        $post = $db->blog->findOne(['_id' => $objectId], ['projection' => ['status' => 1, 'featured' => 1]]);
        $postStatus = $post['status'] ?? 'draft';
        $postCurrentlyFeatured = !empty($post['featured']);

        if ($isFeatured && $postStatus !== 'published') {
            echo json_encode(['success' => false, 'message' => 'Cannot mark unpublished post as featured']);
            exit;
        }

        // Enforce maximum of 3 featured posts (only count published featured posts). If the post
        // is already featured, allow keeping it. Otherwise, if enabling and there are already 3,
        // deny with a helpful message so the UI can show a toast.
        if ($isFeatured && !$postCurrentlyFeatured) {
            $featuredCount = $db->blog->countDocuments([
                'featured' => true,
                'status' => 'published'
            ]);
            if ($featuredCount >= 3) {
                echo json_encode(['success' => false, 'message' => 'Limit reached (3). Remove a featured post to select this one.']);
                exit;
            }
        }

        $result = $db->blog->updateOne(
            ['_id' => $objectId],
            ['$set' => ['featured' => $isFeatured]]
        );

        if ($result->getModifiedCount() > 0 || $result->getMatchedCount() > 0) {
            // Optimization: Regenerate static cache
            include 'cache_featured.php';
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No changes made or post not found']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
}
