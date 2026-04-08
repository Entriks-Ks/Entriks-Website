<?php
require __DIR__ . "/vendor/autoload.php";

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . "/../");
$dotenv->load();

require __DIR__ . "/database.php";
require __DIR__ . "/blog/translation.php";

if (!$db) {
    echo "ERROR: Database connection failed\n";
    exit(1);
}

// Get the first pending post
$post = $db->blog->findOne(['translation_status' => 'pending']);

if (!$post) {
    echo "No pending posts found\n";
    exit;
}

echo "=== Translating Post ===\n";
echo "ID: " . (string)$post['_id'] . "\n";
echo "Title DE: " . ($post['title_de'] ?? 'MISSING') . "\n\n";

// Translate title
if (!empty($post['title_de'])) {
    echo "Translating title...\n";
    $title_en = translateToEnglish($post['title_de']);
    echo "Title EN: $title_en\n\n";
    
    // Translate content
    echo "Translating content (first 200 chars shown)...\n";
    $content_de_preview = substr($post['content_de'] ?? '', 0, 200);
    echo "Content DE preview: $content_de_preview...\n\n";
    
    $content_en = translateToEnglish($post['content_de'] ?? '');
    $content_en_preview = substr($content_en, 0, 200);
    echo "Content EN preview: $content_en_preview...\n\n";
    
    // Update the post
    echo "Updating post in database...\n";
    $result = $db->blog->updateOne(
        ['_id' => $post['_id']],
        ['$set' => [
            'title_en' => $title_en,
            'content_en' => $content_en,
            'translation_status' => 'completed'
        ]]
    );
    
    echo "Modified count: " . $result->getModifiedCount() . "\n";
    echo "✓ Translation completed!\n";
} else {
    echo "ERROR: No German title found\n";
}
