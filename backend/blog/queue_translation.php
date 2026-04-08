<?php
/**
 * Queue a post for background translation
 * Called after a blog post is created or updated
 */

function queueTranslation($postId) {
    // Option 1: Use Windows Task Scheduler (Windows)
    // Uncomment the line below to trigger async translation via Windows
    if (php_uname('s') === 'Windows NT') {
        $cmd = "schtasks /run /tn \"BlogTranslation\" 2>nul";
        exec($cmd);
    } 
    // Option 2: Use cron on Linux (if available)
    else {
        // For Linux/Mac: ensure a cron job runs every minute to check translation queue
        $cmd = "nohup php " . __DIR__ . "/async_translate.php " . escapeshellarg($postId) . " > /dev/null 2>&1 &";
        exec($cmd);
    }
    
    // Option 3: Store in database for manual processing
    global $db;
    $db->translation_queue->insertOne([
        'post_id' => $postId,
        'status' => 'pending',
        'created_at' => new MongoDB\BSON\UTCDateTime()
    ]);
}
