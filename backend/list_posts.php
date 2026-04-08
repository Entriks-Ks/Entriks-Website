<?php
require __DIR__ . "/database.php";

echo "=== ALL BLOG POSTS ===\n";
$posts = $db->blog->find([], ['sort' => ['created_at' => -1], 'limit' => 5]);

foreach ($posts as $post) {
    echo "\nID: " . (string)$post['_id'] . "\n";
    echo "Title DE: " . ($post['title_de'] ?? 'MISSING') . "\n";
    echo "Title EN: " . ($post['title_en'] ?? 'MISSING') . "\n";
    echo "Content EN length: " . strlen($post['content_en'] ?? '') . "\n";
    echo "Translation Status: " . ($post['translation_status'] ?? 'MISSING') . "\n";
    echo "---\n";
}
