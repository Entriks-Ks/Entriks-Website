<?php
require __DIR__ . "/database.php";

$postId = '6931ad323cdf924132092c83';
$post = $db->blog->findOne(['_id' => new MongoDB\BSON\ObjectId($postId)]);

echo "=== POST DEBUG ===\n";
echo "ID: " . (string)$post->_id . "\n";
echo "Title DE: " . $post->title_de . "\n";
echo "Title EN: " . ($post->title_en ?? 'MISSING') . "\n\n";

echo "Content DE (first 200 chars):\n";
echo substr($post->content_de ?? 'MISSING', 0, 200) . "...\n\n";

echo "Content EN (first 200 chars):\n";
echo substr($post->content_en ?? 'MISSING', 0, 200) . "...\n\n";

echo "Content EN length: " . strlen($post->content_en ?? '') . "\n";
echo "Content DE length: " . strlen($post->content_de ?? '') . "\n";

// Check if they're the same
if (isset($post->content_en) && isset($post->content_de)) {
    if ($post->content_en === $post->content_de) {
        echo "\n⚠️  WARNING: Content EN and DE are IDENTICAL!\n";
    } else {
        echo "\n✓ Content EN and DE are different\n";
    }
}
