<?php
/**
 * One-time script to translate all existing blog posts to English
 * Run this once: php backend/scripts/translate_existing_posts.php
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../database.php';
require __DIR__ . '/../translate.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

echo "=== Blog Post Translation Script ===\n\n";

// Find all blog posts that don't have English translations
$posts = $db->blog->find([
    '$or' => [
        ['title_en' => ['$exists' => false]],
        ['content_en' => ['$exists' => false]]
    ]
]);

$count = 0;
$total = iterator_count($db->blog->find([
    '$or' => [
        ['title_en' => ['$exists' => false]],
        ['content_en' => ['$exists' => false]]
    ]
]));

echo "Found $total posts to translate...\n\n";

foreach ($posts as $post) {
    $count++;
    $postId = (string)$post['_id'];
    $title = $post['title'] ?? $post['title_de'] ?? 'Untitled';
    
    echo "[$count/$total] Translating: $title\n";
    
    try {
        $updateFields = [];
        
        // Translate title
        if (!isset($post['title_en'])) {
            echo "  - Translating title...";
            $updateFields['title_en'] = aiTranslate($post['title'] ?? $post['title_de'] ?? '', 'English');
            echo " ✓\n";
            sleep(1); // Avoid rate limits
        }
        
        // Translate description
        if (!isset($post['description_en']) && !empty($post['description'] ?? $post['description_de'] ?? '')) {
            echo "  - Translating description...";
            $updateFields['description_en'] = aiTranslate($post['description'] ?? $post['description_de'] ?? '', 'English');
            echo " ✓\n";
            sleep(1);
        }
        
        // Translate content
        if (!isset($post['content_en'])) {
            echo "  - Translating content...";
            $updateFields['content_en'] = aiTranslate($post['content'] ?? $post['content_de'] ?? '', 'English');
            echo " ✓\n";
            sleep(1);
        }
        
        // Save German versions if not already saved
        if (!isset($post['title_de'])) {
            $updateFields['title_de'] = $post['title'];
        }
        if (!isset($post['description_de']) && !empty($post['description'])) {
            $updateFields['description_de'] = $post['description'];
        }
        if (!isset($post['content_de'])) {
            $updateFields['content_de'] = $post['content'];
        }
        
        // Update the post
        if (!empty($updateFields)) {
            $db->blog->updateOne(
                ['_id' => $post['_id']],
                ['$set' => $updateFields]
            );
            echo "  ✓ Saved translations for post!\n\n";
        }
        
    } catch (Exception $e) {
        echo "  ✗ Error: " . $e->getMessage() . "\n\n";
        echo "Pausing for 60 seconds to avoid rate limits...\n";
        sleep(60);
    }
}

echo "\n=== Translation Complete! ===\n";
echo "Translated $count blog posts.\n";
echo "All translations are now saved and will be reused automatically.\n";
