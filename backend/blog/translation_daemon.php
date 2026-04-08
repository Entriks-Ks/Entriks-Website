<?php
/**
 * Lightweight PHP Translation Daemon
 * Runs in background automatically - no cron/Task Scheduler needed
 * 
 * This file can be included in a startup script or kept running via a process manager
 * For now, we'll use a faster approach: trigger translation on page load (cached)
 */

class TranslationDaemon {
    private $db;
    private $lockFile = __DIR__ . '/.translation_lock';
    private $lastRun = 0;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Non-blocking translation processor
     * Call this on page load - it processes 1 translation then exits
     */
    public function processOneTranslation() {
        // Check if there ARE any pending translations (fast query)
        $pendingCount = $this->db->translation_queue->countDocuments(['status' => 'pending']);
        if ($pendingCount === 0) {
            return; // Nothing to do, exit immediately
        }
        
        // Prevent hammering - only run if 15+ seconds since last check
        if (file_exists($this->lockFile)) {
            $lastRun = (int)file_get_contents($this->lockFile);
            if (time() - $lastRun < 5) {  // Reduced from 15 to 5 seconds for faster processing
                return;
            }
        }
        
        // Update lock
        file_put_contents($this->lockFile, time());
        
        try {
            // Process ONE pending translation
            $queueItem = $this->db->translation_queue->findOne(['status' => 'pending']);
            
            if (!$queueItem) {
                return;
            }
            
            $postId = $queueItem['post_id'];
            $post = $this->db->blog->findOne(['_id' => $postId]);
            
            if (!$post || (isset($post['title_en']) && !empty($post['title_en']))) {
                // Already translated or post deleted
                $this->db->translation_queue->updateOne(
                    ['_id' => $queueItem['_id']],
                    ['$set' => ['status' => 'completed']]
                );
                return;
            }
            
            // Translate (with timeout)
            require_once 'translation.php';
            
            // Combine title + description for one API call if possible
            $textToTranslate = ($post['title_de'] ?? '') . "\n---\n" . ($post['description_de'] ?? '');
            if (!empty($textToTranslate)) {
                $translated = translateToEnglish($textToTranslate);
                [$title_en, $description_en] = explode("\n---\n", $translated, 2);
            } else {
                $title_en = '';
                $description_en = '';
            }
            
            // Translate content separately (if large)
            $content_en = translateToEnglish($post['content_de'] ?? '');
            
            // Update MongoDB
            $this->db->blog->updateOne(
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
            
            $this->db->translation_queue->updateOne(
                ['_id' => $queueItem['_id']],
                ['$set' => ['status' => 'completed', 'completed_at' => new MongoDB\BSON\UTCDateTime()]]
            );
            
        } catch (Exception $e) {
            // Silent fail - don't break page load
        }
    }
}

// Auto-process on any page load that includes this
if (!function_exists('autoProcessTranslations')) {
    function autoProcessTranslations($db) {
        $daemon = new TranslationDaemon($db);
        $daemon->processOneTranslation();
    }
}
