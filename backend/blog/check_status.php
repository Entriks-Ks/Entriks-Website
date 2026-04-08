<?php
require_once dirname(__DIR__) . '/session_config.php';
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE); // Suppress warnings/notices to keep JSON clean
ini_set('display_errors', 0);

use MongoDB\BSON\ObjectId;

require "../database.php";

header('Content-Type: application/json');

if (!isset($_POST['post_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing post_id']);
    exit;
}

try {
    $postId = new ObjectId($_POST['post_id']);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid post_id']);
    exit;
}

try {
    $post = $db->blog->findOne(['_id' => $postId]);

    if (!$post) {
        http_response_code(404);
        echo json_encode(['error' => 'Post not found']);
        exit;
    }

    // Check translation status
    $translationStatus = $post->translation_status ?? 'pending';
    $titleEn = $post->title_en ?? '';
    $contentEn = $post->content_en ?? '';

    // Check image status (safely - collection might not exist)
    $imageStatus = 'completed';
    try {
        $imageQueue = $db->image_queue->findOne(['post_id' => $postId, 'status' => 'pending']);
        if ($imageQueue) {
            $imageStatus = 'pending';
        }
    } catch (Exception $imgErr) {
        // Collection doesn't exist yet, that's okay
        $imageStatus = 'completed';
    }

    // Get queue item to see attempts (safely)
    $attempts = 0;
    $elapsedSeconds = 0;
    try {
        $queueItem = $db->translation_queue->findOne(['post_id' => $postId]);
        if ($queueItem) {
            $attempts = $queueItem->attempts ?? 0;
            $createdAt = $queueItem->created_at ?? null;
            
            if ($createdAt) {
                $createdTime = $createdAt->toDateTime()->getTimestamp();
                $elapsedSeconds = time() - $createdTime;
            }
        }
    } catch (Exception $qErr) {
        // Queue collection might not exist
    }

    echo json_encode([
        'translation_status' => $translationStatus,
        'title_en' => $titleEn,
        'content_en' => $contentEn,
        'image_status' => $imageStatus,
        'has_english_content' => !empty($titleEn) && !empty($contentEn),
        'attempts' => $attempts,
        'elapsed_seconds' => $elapsedSeconds
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
