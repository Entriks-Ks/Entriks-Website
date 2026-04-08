<?php
require_once __DIR__ . "/../database.php";
require_once __DIR__ . "/../gridfs.php";

// Process pending image uploads from queue
$imageQueue = $db->image_queue->find(['status' => 'pending']);

foreach ($imageQueue as $queueItem) {
    try {
        $tempPath = $queueItem->temp_path;
        
        // Check if temp file still exists
        if (!file_exists($tempPath)) {
            // Mark as failed - file not found
            $db->image_queue->updateOne(
                ['_id' => $queueItem->_id],
                ['$set' => ['status' => 'failed', 'reason' => 'temp file not found']]
            );
            continue;
        }
        
        // Create a fake $_FILES array for uploadImageToGridFS
        $fakeFile = [
            'tmp_name' => $tempPath,
            'name' => $queueItem->file_name,
            'type' => mime_content_type($tempPath),
            'size' => filesize($tempPath),
            'error' => 0
        ];
        
        // Upload to GridFS
        $imageId = uploadImageToGridFS($fakeFile);
        
        if ($imageId) {
            // Update blog post with new image
            $db->blog->updateOne(
                ['_id' => $queueItem->post_id],
                ['$set' => ['image' => $imageId]]
            );
            
            // Mark queue item as completed
            $db->image_queue->updateOne(
                ['_id' => $queueItem->_id],
                ['$set' => ['status' => 'completed', 'image_id' => $imageId, 'completed_at' => new MongoDB\BSON\UTCDateTime()]]
            );
            
            // Delete temp file
            @unlink($tempPath);
        } else {
            throw new Exception("GridFS upload failed");
        }
        
    } catch (Exception $e) {
        error_log("Image queue error: " . $e->getMessage());
        
        // Mark as failed after 3 attempts
        $attempts = ($queueItem->attempts ?? 0) + 1;
        if ($attempts >= 3) {
            $db->image_queue->updateOne(
                ['_id' => $queueItem->_id],
                ['$set' => ['status' => 'failed', 'attempts' => $attempts, 'error' => $e->getMessage()]]
            );
            // Clean up temp file
            @unlink($queueItem->temp_path);
        } else {
            $db->image_queue->updateOne(
                ['_id' => $queueItem->_id],
                ['$inc' => ['attempts' => 1]]
            );
        }
    }
}

// Clean up old completed items (older than 24 hours)
$oneDayAgo = new MongoDB\BSON\UTCDateTime((time() - 86400) * 1000);
$db->image_queue->deleteMany([
    'status' => 'completed',
    'completed_at' => ['$lt' => $oneDayAgo]
]);

echo "Image queue processed at " . date('Y-m-d H:i:s');
?>
