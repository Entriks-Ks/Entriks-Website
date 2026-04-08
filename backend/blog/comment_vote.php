<?php
// comment_vote.php
// Handles like/dislike for comments, per user (IP-based)

// Disable error reporting for API to avoid breaking JSON
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$commentIdRaw = $_POST['comment_id'] ?? '';
$action = $_POST['action'] ?? ''; // 'like' or 'dislike'

if (!preg_match('/^[a-f\d]{24}$/i', $commentIdRaw) || !in_array($action, ['like', 'dislike'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

try {
    $commentId = new MongoDB\BSON\ObjectId($commentIdRaw);
    $ip = $_SERVER['REMOTE_ADDR']; // Simple IP tracking
    
    if (!$db) {
        echo json_encode(['success' => false, 'error' => 'Database connection failed']);
        exit;
    }

    // Collections
    $commentsCollection = $db->comments ?? $db->selectCollection('comments');
    $votesCollection = $db->comment_votes ?? $db->selectCollection('comment_votes');

    // Check if this IP has already voted on this comment
    $existingVote = $votesCollection->findOne([
        'comment_id' => $commentId,
        'ip' => $ip
    ]);

    if ($existingVote) {
        // User has voted before
        $previousAction = $existingVote['type'];

        if ($previousAction === $action) {
            // TOGGLE OFF: User clicked the same action again -> Remove vote
            $votesCollection->deleteOne(['_id' => $existingVote['_id']]);
            
            // Decrement the count
            $field = ($action === 'like') ? 'likes' : 'dislikes';
            $commentsCollection->updateOne(
                ['_id' => $commentId],
                ['$inc' => [$field => -1]]
            );

        } else {
            // SWITCH VOTE: User clicked different action (e.g. was like, now dislike)
            $votesCollection->updateOne(
                ['_id' => $existingVote['_id']],
                ['$set' => ['type' => $action, 'updated_at' => new MongoDB\BSON\UTCDateTime()]]
            );

            // Decrement old field, Increment new field
            $oldField = ($previousAction === 'like') ? 'likes' : 'dislikes';
            $newField = ($action === 'like') ? 'likes' : 'dislikes';
            
            $commentsCollection->updateOne(
                ['_id' => $commentId],
                ['$inc' => [$oldField => -1, $newField => 1]]
            );
        }
    } else {
        // NEW VOTE: User has not voted yet
        $votesCollection->insertOne([
            'comment_id' => $commentId,
            'ip' => $ip,
            'type' => $action,
            'created_at' => new MongoDB\BSON\UTCDateTime()
        ]);

        $field = ($action === 'like') ? 'likes' : 'dislikes';
        $commentsCollection->updateOne(
            ['_id' => $commentId],
            ['$inc' => [$field => 1]]
        );
    }

    // Return new counts
    $comment = $commentsCollection->findOne(['_id' => $commentId]);
    
    // Ensure counts are non-negative (sanity check, though logic should prevent it)
    $likes = max(0, intval($comment['likes'] ?? 0));
    $dislikes = max(0, intval($comment['dislikes'] ?? 0));

    // Optional: Reset if negative (self-healing)
    if (($comment['likes'] ?? 0) < 0 || ($comment['dislikes'] ?? 0) < 0) {
        $commentsCollection->updateOne(
            ['_id' => $commentId],
            ['$set' => ['likes' => $likes, 'dislikes' => $dislikes]]
        );
    }

    echo json_encode([
        'success' => true,
        'likes' => $likes,
        'dislikes' => $dislikes
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}