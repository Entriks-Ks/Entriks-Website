<?php
require_once dirname(__DIR__) . '/session_config.php';

use MongoDB\BSON\ObjectId;

require "../database.php";
require "translation.php";

if (!isset($_SESSION["admin"])) {
    http_response_code(403);
    echo "Unauthorized";
    exit;
}

// Force translate a specific post
if (isset($_POST['post_id'])) {
    try {
        $postId = new ObjectId($_POST['post_id']);
        $post = $db->blog->findOne(['_id' => $postId]);
        
        if (!$post) {
            echo json_encode(['error' => 'Post not found']);
            exit;
        }
        
        $updates = [];
        
        // Force translate title
        if (!empty($post->title_de)) {
            $title_en = translateToEnglish($post->title_de);
            $updates['title_en'] = $title_en;
            echo "Title translated: " . substr($title_en, 0, 50) . "...\n";
        }
        
        // Force translate content
        if (!empty($post->content_de)) {
            $content_en = translateToEnglish($post->content_de);
            $updates['content_en'] = $content_en;
            echo "Content translated: " . strlen($content_en) . " chars\n";
        }
        
        if (!empty($updates)) {
            $updates['translation_status'] = 'completed';
            $db->blog->updateOne(['_id' => $postId], ['$set' => $updates]);
            echo json_encode(['success' => true, 'message' => 'Translation completed']);
        } else {
            echo json_encode(['error' => 'No content to translate']);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    echo "No post_id provided";
}
?>
