<?php
require __DIR__ . "/database.php";

$postId = '693f1aa7b3dff304132092c6';
$post = $db->blog->findOne(['_id' => new MongoDB\BSON\ObjectId($postId)]);

if (!$post) {
    echo "Post not found!\n";
    exit;
}

echo "=== POST DEBUG ===\n";
echo "Title DE: " . ($post['title_de'] ?? 'MISSING') . "\n";
echo "Title EN: " . ($post['title_en'] ?? 'MISSING') . "\n";
echo "Content DE length: " . strlen($post['content_de'] ?? '') . "\n";
echo "Content EN length: " . strlen($post['content_en'] ?? '') . "\n";
echo "Translation Status: " . ($post['translation_status'] ?? 'MISSING') . "\n";
echo "\n=== TRANSLATION QUEUE ===\n";

$queueItem = $db->translation_queue->findOne(['post_id' => new MongoDB\BSON\ObjectId($postId)]);
if ($queueItem) {
    echo "Queue Status: " . ($queueItem['status'] ?? 'MISSING') . "\n";
    echo "Attempts: " . ($queueItem['attempts'] ?? 0) . "\n";
    echo "Title Changed: " . ($queueItem['title_changed'] ? 'true' : 'false') . "\n";
    echo "Content Changed: " . ($queueItem['content_changed'] ? 'true' : 'false') . "\n";
    if (isset($queueItem['error'])) {
        echo "Error: " . $queueItem['error'] . "\n";
    }
} else {
    echo "No queue item found\n";
}

echo "\n=== FIRST 100 chars of Content EN ===\n";
echo substr($post['content_en'] ?? 'EMPTY', 0, 100) . "\n";
