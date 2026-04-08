<?php
// Increase execution time for background translation processing
set_time_limit(300); // 5 minutes max
ini_set('max_execution_time', 300);

require_once __DIR__ . "/../vendor/autoload.php";

// Load environment variables manually (Dotenv not always reliable)
$envFile = __DIR__ . "/../../.env";
if (file_exists($envFile)) {
    $lines = explode("\n", file_get_contents($envFile));
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

require_once __DIR__ . "/../database.php";
require_once __DIR__ . "/translation.php";

// Process pending translations from queue (limit to 5 at a time to avoid timeout)
$pendingQueue = $db->translation_queue->find(
    ['status' => 'pending'],
    ['limit' => 5, 'sort' => ['created_at' => 1]]
);

$processed = 0;
foreach ($pendingQueue as $queueItem) {
    $processed++;
    echo "Processing queue item " . $processed . ": Post ID " . (string)$queueItem->post_id . "\n";
    
    try {
        $post = $db->blog->findOne(['_id' => $queueItem->post_id]);
        
        if (!$post) {
            // Post deleted, remove queue item
            $db->translation_queue->deleteOne(['_id' => $queueItem->_id]);
            continue;
        }
        
        // Only translate what changed
        $titleChanged = $queueItem->title_changed ?? true;  // Default true for backward compatibility
        $contentChanged = $queueItem->content_changed ?? true;
        
        $updateData = [];
        
        // Prepare texts to translate
        $textsToTranslate = [];
        if ($titleChanged && !empty($post->title_de)) {
            $textsToTranslate['title'] = $post->title_de;
            echo "Queuing title for translation: " . substr($post->title_de, 0, 50) . "...\n";
        }
        if ($contentChanged && !empty($post->content_de)) {
            $textsToTranslate['content'] = $post->content_de;
            echo "Queuing content for translation (" . strlen($post->content_de) . " chars)...\n";
        }
        
        // Translate all in parallel (much faster!)
        if (!empty($textsToTranslate)) {
            echo "Translating " . count($textsToTranslate) . " items in parallel...\n";
            $translations = translateMultipleToEnglish($textsToTranslate);
            
            // Update title if translated
            if (isset($translations['title']) && trim($translations['title']) !== trim($post->title_de)) {
                $updateData['title_en'] = $translations['title'];
                echo "Title translated to: " . substr($translations['title'], 0, 50) . "...\n";
            }
            
            // Update content if translated
            if (isset($translations['content']) && trim($translations['content']) !== trim($post->content_de)) {
                $updateData['content_en'] = $translations['content'];
                echo "Content translated (" . strlen($translations['content']) . " chars)\n";
            }
        }
        
        // Only update if there's something to update
        if (!empty($updateData)) {
            $updateData['translation_status'] = 'completed';
            
            echo "Updating post with translated content...\n";
            $db->blog->updateOne(
                ['_id' => $post->_id],
                ['$set' => $updateData]
            );
            echo "Post updated successfully!\n";
        }
        
        // Mark queue item as completed
        echo "Marking queue item as completed...\n";
        $db->translation_queue->updateOne(
            ['_id' => $queueItem->_id],
            ['$set' => ['status' => 'completed', 'completed_at' => new MongoDB\BSON\UTCDateTime()]]
        );
    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n\n";
        error_log("Translation queue error: " . $e->getMessage());
        // Mark as failed after 3 attempts
        $attempts = ($queueItem->attempts ?? 0) + 1;
        if ($attempts >= 3) {
            $db->translation_queue->updateOne(
                ['_id' => $queueItem->_id],
                ['$set' => ['status' => 'failed', 'attempts' => $attempts, 'error' => $e->getMessage()]]
            );
        } else {
            $db->translation_queue->updateOne(
                ['_id' => $queueItem->_id],
                ['$inc' => ['attempts' => 1]]
            );
        }
    }
}

echo "\nProcessed $processed queue items.\n";

// Clean up old completed items (older than 7 days)
$sevenDaysAgo = new MongoDB\BSON\UTCDateTime((time() - 604800) * 1000);
$db->translation_queue->deleteMany([
    'status' => 'completed',
    'completed_at' => ['$lt' => $sevenDaysAgo]
]);

// Also process image uploads
require_once __DIR__ . "/process_image_queue.php";

echo "Queue processed at " . date('Y-m-d H:i:s');
?>