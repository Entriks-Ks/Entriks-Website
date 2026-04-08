<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/database.php';

$today = date('Y-m-d');
$hour = date('Y-m-d H');

// 1. Mock Blog View
$post = $db->blog->findOne(['status' => 'published']);
if ($post) {
    $objectId = $post['_id'];
    $db->blog->updateOne(
        ['_id' => $objectId],
        [
            '$inc' => [
                'views' => 1,
                "views_history.$today" => 1,
                "views_history.$hour" => 1
            ]
        ]
    );
    $db->notifications->insertOne([
        'type' => 'blog_view',
        'item_id' => $objectId,
        'item_title' => $post['title_de'] ?? 'Test Blog',
        'timestamp' => new MongoDB\BSON\UTCDateTime(),
        'created_at' => date('Y-m-d H:i:s')
    ]);
    echo "Mocked blog view for " . $objectId . "\n";
}

// 2. Mock Main View
$db->main_views->updateOne(
    ['date' => $today],
    ['$inc' => ['count' => 1], '$setOnInsert' => ['created_at' => new MongoDB\BSON\UTCDateTime()]],
    ['upsert' => true]
);
$db->main_views->updateOne(
    ['date' => $hour],
    ['$inc' => ['count' => 1], '$setOnInsert' => ['created_at' => new MongoDB\BSON\UTCDateTime(), 'is_hour' => true]],
    ['upsert' => true]
);
$db->notifications->insertOne([
    'type' => 'main_view',
    'item_title' => 'Main Page',
    'timestamp' => new MongoDB\BSON\UTCDateTime(),
    'created_at' => date('Y-m-d H:i:s')
]);
echo "Mocked main view.\n";
