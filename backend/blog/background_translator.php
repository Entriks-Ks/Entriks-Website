<?php
/**
 * Background Translation Worker - Google Gemini AI
 * Runs in completely separate PHP process
 * Never interferes with page load
 * 
 * Run via: php background_translator.php [post_id]
 */

// Allow unlimited execution time in background
set_time_limit(300);
ignore_user_abort(true);

require_once __DIR__ . "/../database.php";
require_once __DIR__ . "/translation.php";

use MongoDB\BSON\ObjectId;

$postId = $argv[1] ?? null;

if (!$postId) {
    exit(0);
}

try {
    $postId = new ObjectId($postId);
} catch (Exception $e) {
    exit(0);
}

$post = $db->blog->findOne(['_id' => $postId]);
if (!$post) {
    exit(0);
}

// Skip if already translated
if (isset($post['title_en']) && !empty($post['title_en'])) {
    exit(0);
}

// Translate title + description together
$textToTranslate = ($post['title_de'] ?? '') . "\n---\n" . ($post['description_de'] ?? '');
$translated = translateToEnglish($textToTranslate);
[$title_en, $description_en] = explode("\n---\n", $translated, 2);

// Translate content
$content_en = translateToEnglish($post['content_de'] ?? '');

// Update MongoDB
$db->blog->updateOne(
    ['_id' => $postId],
    [
        '$set' => [
            'title_en' => $title_en,
            'description_en' => $description_en,
            'content_en' => $content_en,
            'translated_at' => new MongoDB\BSON\UTCDateTime(),
            'translation_status' => 'completed'
        ]
    ]
);

exit(0);
