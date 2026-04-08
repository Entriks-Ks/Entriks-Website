<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/database.php';

// Insert test notifications
try {
    $db->notifications->insertOne([
        'type' => 'watched_comments',
        'item_title' => 'Should NOT see this',
        'timestamp' => new MongoDB\BSON\UTCDateTime(),
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    $db->notifications->insertOne([
        'type' => 'blog_view',
        'item_title' => 'Should SEE this',
        'timestamp' => new MongoDB\BSON\UTCDateTime(),
        'created_at' => date('Y-m-d H:i:s')
    ]);
} catch (Exception $e) {
    echo "Setup failed: " . $e->getMessage() . "\n";
    exit;
}

// Simulate get_notifications.php logic
$filter = [];
$filter['type'] = ['$ne' => 'watched_comments'];

try {
    $cursor = $db->notifications->find($filter, [
        'sort' => ['timestamp' => -1],
        'limit' => 5
    ]);
    
    $foundForbidden = false;
    $foundAllowed = false;
    
    foreach ($cursor as $doc) {
        if ($doc['type'] === 'watched_comments' && $doc['item_title'] === 'Should NOT see this') {
            $foundForbidden = true;
        }
        if ($doc['type'] === 'blog_view' && $doc['item_title'] === 'Should SEE this') {
            $foundAllowed = true;
        }
    }
    
    if (!$foundForbidden && $foundAllowed) {
        echo "VERIFICATION PASSED: 'watched_comments' filtered out, 'blog_view' present.\n";
    } else {
        echo "VERIFICATION FAILED: Forbidden found: " . ($foundForbidden ? 'YES' : 'NO') . ", Allowed found: " . ($foundAllowed ? 'YES' : 'NO') . "\n";
    }

} catch (Exception $e) {
    echo "Query failed: " . $e->getMessage() . "\n";
}
