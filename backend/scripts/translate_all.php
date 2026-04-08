<?php
/**
 * Master translation script - Translates all existing content once
 * Run this once: php backend/scripts/translate_all.php
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../database.php';
require __DIR__ . '/../translate.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║         ENTRIKS Translation System - One-Time Setup       ║\n";
echo "║    Translate once → Save forever → Reuse automatically    ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";
echo "\n";

// ====== TRANSLATE BLOG POSTS ======
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "1. TRANSLATING BLOG POSTS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$postsToTranslate = $db->blog->find([
    '$or' => [
        ['title_en' => ['$exists' => false]],
        ['content_en' => ['$exists' => false]]
    ]
]);

$postCount = 0;
$postTotal = iterator_count($db->blog->find([
    '$or' => [
        ['title_en' => ['$exists' => false]],
        ['content_en' => ['$exists' => false]]
    ]
]));

if ($postTotal === 0) {
    echo "✓ All blog posts already have translations!\n\n";
} else {
    echo "Found $postTotal posts to translate...\n\n";
    
    foreach ($postsToTranslate as $post) {
        $postCount++;
        $title = $post['title'] ?? $post['title_de'] ?? 'Untitled';
        
        echo "[$postCount/$postTotal] $title\n";
        
        try {
            $updateFields = [];
            
            if (!isset($post['title_en'])) {
                echo "  → Translating title...";
                $updateFields['title_en'] = aiTranslate($post['title'] ?? $post['title_de'] ?? '', 'English');
                echo " ✓\n";
                sleep(1);
            }
            
            if (!isset($post['description_en']) && !empty($post['description'] ?? $post['description_de'] ?? '')) {
                echo "  → Translating description...";
                $updateFields['description_en'] = aiTranslate($post['description'] ?? $post['description_de'] ?? '', 'English');
                echo " ✓\n";
                sleep(1);
            }
            
            if (!isset($post['content_en'])) {
                echo "  → Translating content...";
                $updateFields['content_en'] = aiTranslate($post['content'] ?? $post['content_de'] ?? '', 'English');
                echo " ✓\n";
                sleep(1);
            }
            
            if (!isset($post['title_de'])) {
                $updateFields['title_de'] = $post['title'];
            }
            if (!isset($post['description_de']) && !empty($post['description'])) {
                $updateFields['description_de'] = $post['description'];
            }
            if (!isset($post['content_de'])) {
                $updateFields['content_de'] = $post['content'];
            }
            
            if (!empty($updateFields)) {
                $db->blog->updateOne(
                    ['_id' => $post['_id']],
                    ['$set' => $updateFields]
                );
                echo "  ✓ Saved! Will be reused for all future page loads.\n\n";
            }
            
        } catch (Exception $e) {
            echo "  ✗ Error: " . $e->getMessage() . "\n";
            echo "  ⏸  Pausing 60s to avoid rate limits...\n\n";
            sleep(60);
        }
    }
}

// ====== TRANSLATE COMMENTS ======
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "2. TRANSLATING COMMENTS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$commentsCollection = $db->comments ?? $db->selectCollection('comments');
$commentsToTranslate = $commentsCollection->find([
    'message_en' => ['$exists' => false]
]);

$commentCount = 0;
$commentTotal = $commentsCollection->countDocuments([
    'message_en' => ['$exists' => false]
]);

if ($commentTotal === 0) {
    echo "✓ All comments already have translations!\n\n";
} else {
    echo "Found $commentTotal comments to translate...\n\n";
    
    foreach ($commentsToTranslate as $comment) {
        $commentCount++;
        $name = $comment['name'] ?? 'Anonymous';
        $message = $comment['message'] ?? '';
        
        echo "[$commentCount/$commentTotal] Comment by: $name\n";
        
        if (empty($message)) {
            echo "  ⊘ Skipping empty comment\n\n";
            continue;
        }
        
        try {
            echo "  → Translating message...";
            $message_en = aiTranslate($message, 'English');
            
            $commentsCollection->updateOne(
                ['_id' => $comment['_id']],
                ['$set' => ['message_en' => $message_en]]
            );
            
            echo " ✓\n";
            echo "  ✓ Saved! Will be reused for all future page loads.\n\n";
            
            sleep(1);
            
        } catch (Exception $e) {
            echo "  ✗ Error: " . $e->getMessage() . "\n";
            echo "  ⏸  Pausing 60s to avoid rate limits...\n\n";
            sleep(60);
        }
    }
}

// ====== SUMMARY ======
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "TRANSLATION COMPLETE!\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
echo "✓ Blog Posts Translated: $postCount\n";
echo "✓ Comments Translated: $commentCount\n\n";
echo "All translations are now saved in the database.\n";
echo "Future page loads will use these cached translations.\n";
echo "No more API calls needed for existing content!\n\n";
echo "New posts and comments will be automatically translated\n";
echo "when created, then cached for future reuse.\n\n";
