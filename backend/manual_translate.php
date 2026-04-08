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

if (!$db) {
    echo "ERROR: Database connection failed\n";
    exit(1);
}

echo "=== MANUALLY TRANSLATING ALL PENDING POSTS ===\n\n";

// Find all posts with pending translation status
$posts = $db->blog->find(['translation_status' => 'pending']);

$count = 0;
foreach ($posts as $post) {
    $count++;
    echo "Post #$count: " . (string)$post->_id . "\n";
    echo "  Title DE: " . substr($post->title_de ?? '', 0, 50) . "...\n";
    
    $updateData = [];
    
    // Translate title
    if (!empty($post->title_de)) {
        echo "  Translating title...\n";
        $title_en = translateToEnglish($post->title_de);
        echo "  Title EN: " . substr($title_en, 0, 50) . "...\n";
        $updateData['title_en'] = $title_en;
    }
    
    // Translate content
    if (!empty($post->content_de)) {
        echo "  Translating content (" . strlen($post->content_de ?? '') . " chars)...\n";
        $content_en = translateToEnglish($post->content_de);
        echo "  Content translated (" . strlen($content_en) . " chars)\n";
        $updateData['content_en'] = $content_en;
    }
    
    // Update post
    if (!empty($updateData)) {
        $updateData['translation_status'] = 'completed';
        $db->blog->updateOne(
            ['_id' => $post->_id],
            ['$set' => $updateData]
        );
        echo "  ✓ Post updated!\n";
    }
    
    echo "\n";
}

echo "Translated $count posts.\n";