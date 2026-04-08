<?php
/**
 * Async Translation Worker - Google Gemini AI
 * Run via CLI: php async_translate.php [post_id]
 * Or called by create/edit after blog is saved
 */

use MongoDB\BSON\ObjectId;

require_once "../database.php";
require_once "translation.php";

// Get post ID from command line or parameter
$postId = $argv[1] ?? $_GET['post_id'] ?? null;

if (!$postId) {
    exit("No post ID provided.\n");
}

try {
    $postId = new ObjectId($postId);
} catch (Exception $e) {
    exit("Invalid post ID.\n");
}

$post = $db->blog->findOne(['_id' => $postId]);
if (!$post) {
    exit("Post not found.\n");
}

// Skip if already translated
if (isset($post['title_en']) && !empty($post['title_en'])) {
    exit("Post already translated.\n");
}

// Translate
$title_en = translateToEnglish($post['title_de'] ?? '');
$description_en = isset($post['description_de']) && !empty($post['description_de']) 
    ? translateToEnglish($post['description_de']) 
    : "";
$content_en = translateToEnglish($post['content_de'] ?? '');

// Update MongoDB
$db->blog->updateOne(
    ['_id' => $postId],
    [
        '$set' => [
            'title_en' => $title_en,
            'description_en' => $description_en,
            'content_en' => $content_en,
            'translated_at' => new MongoDB\BSON\UTCDateTime()
        ]
    ]
);

echo "Translation completed for post: " . $postId . "\n";
