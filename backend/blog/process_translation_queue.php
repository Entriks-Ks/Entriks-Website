<?php
/**
 * Translation Queue Processor - Google Gemini AI
 * Run via CLI: php process_translation_queue.php
 * Set up a cron job: * * * * * php /path/to/process_translation_queue.php
 */

require_once "../database.php";
require_once "translation.php";

use MongoDB\BSON\ObjectId;

// Process up to 5 pending translations per run
$pendingPosts = $db->translation_queue->find(
    ['status' => 'pending'],
    ['limit' => 5]
);

$processed = 0;

foreach ($pendingPosts as $queueItem) {
    $postId = $queueItem['post_id'];
    try {
        $post = $db->blog->findOne(['_id' => $postId]);
        if (!$post) {
            $db->translation_queue->updateOne(
                ['_id' => $queueItem['_id']],
                ['$set' => ['status' => 'failed', 'error' => 'Post not found']]
            );
            continue;
        }
        // Skip if already has English translation
        if (isset($post['title_en']) && !empty($post['title_en']) && isset($post['content_en']) && !empty($post['content_en'])) {
            $db->translation_queue->updateOne(
                ['_id' => $queueItem['_id']],
                ['$set' => ['status' => 'completed', 'completed_at' => new MongoDB\BSON\UTCDateTime()]]
            );
            continue;
        }
        // Translate
        $title_en = translateToEnglish($post['title_de'] ?? '');
        $content_en = translateToEnglish($post['content_de'] ?? '');

        // Check if translation actually changed
        $translationSuccess = (
            ($post['title_de'] ?? '') !== $title_en && !empty($title_en)
            || ($post['content_de'] ?? '') !== $content_en && !empty($content_en)
        );

        if ($translationSuccess) {
            // Update MongoDB (no description_en)
            $db->blog->updateOne(
                ['_id' => $postId],
                [
                    '$set' => [
                        'title_en' => $title_en,
                        'content_en' => $content_en,
                        'translated_at' => new MongoDB\BSON\UTCDateTime(),
                        'translation_status' => 'completed'
                    ],
                    '$unset' => [ 'description_en' => "" ]
                ]
            );
            // Mark as completed in queue
            $db->translation_queue->updateOne(
                ['_id' => $queueItem['_id']],
                ['$set' => ['status' => 'completed', 'completed_at' => new MongoDB\BSON\UTCDateTime()]]
            );
            $processed++;
        } else {
            // Mark as failed and log error for retry
            $db->translation_queue->updateOne(
                ['_id' => $queueItem['_id']],
                ['$set' => [
                    'status' => 'failed',
                    'error' => 'Translation did not change text or returned empty',
                    'failed_at' => new MongoDB\BSON\UTCDateTime()
                ]]
            );
        }
    } catch (Exception $e) {
        $db->translation_queue->updateOne(
            ['_id' => $queueItem['_id']],
            ['$set' => ['status' => 'failed', 'error' => $e->getMessage()]]
        );
    }
}

echo "Processed $processed translations\n";
