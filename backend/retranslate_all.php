<?php
require __DIR__ . "/vendor/autoload.php";

// Load environment variables
$envFile = __DIR__ . "/../.env";
if (file_exists($envFile)) {
    $lines = explode("\n", file_get_contents($envFile));
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            putenv(trim($key) . '=' . trim($value));
            $_ENV[trim($key)] = trim($value);
        }
    }
}

require __DIR__ . "/database.php";
require __DIR__ . "/blog/translation.php";

echo "=== RETRANSLATING ALL POSTS ===\n\n";

$posts = $db->blog->find(
    ['status' => ['$ne' => 'archived']],
    ['sort' => ['created_at' => -1], 'limit' => 50]
);

foreach ($posts as $post) {
    echo "Post: " . (string)$post->_id . "\n";
    echo "  Title DE: " . substr($post->title_de, 0, 60) . "...\n";
    
    // Skip if already translated (and different from source)
    if (!empty($post->title_en) && !empty($post->title_de) && $post->title_en !== $post->title_de) {
        echo "  Skipping: Already translated.\n\n";
        continue;
    }

    $updateData = [];
    
    // Translate title
    if (!empty($post->title_de)) {
        echo "  Translating title...\n";
        $title_en = translateToEnglish($post->title_de, false);
        echo "  Title EN: " . substr($title_en, 0, 60) . "...\n";
        $updateData['title_en'] = $title_en;
    }
    
    // Translate content
    if (!empty($post->content_de)) {
        echo "  Translating content (" . strlen($post->content_de) . " chars)...\n";
        $content_en = translateToEnglish($post->content_de, false);
        echo "  Content EN: " . substr($content_en, 0, 100) . "...\n";
        $updateData['content_en'] = $content_en;
    }
    
    // Update post
    if (!empty($updateData)) {
        $db->blog->updateOne(
            ['_id' => $post->_id],
            ['$set' => $updateData]
        );
        echo "  ✓ Updated!\n";
    }
    
    echo "\n";
    sleep(2); // Rate limit API calls
}

echo "Done!\n";
