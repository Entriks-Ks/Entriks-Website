<?php
// CRON-compatible script to publish scheduled posts
// Try to require composer autoload if present, but do not fail if missing
$autoload = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
    $autoload_missing = false;
} else {
    // We'll continue without vendor autoload — fine if no composer deps are required
    $autoload_missing = true;
}
require_once __DIR__ . '/../database.php';

// Find posts with status='scheduled' and publish_at <= now
$now = new MongoDB\BSON\UTCDateTime();
$filter = [
    'status' => 'scheduled',
    'publish_at' => ['$lte' => $now]
];
$update = [
    '$set' => ['status' => 'published'],
    '$unset' => ['publish_at' => '']
];

$scheduledPosts = $db->blog->find($filter);
$count = 0;
foreach ($scheduledPosts as $post) {
    $db->blog->updateOne(['_id' => $post->_id], $update);
    $count++;
}
// Expose published count when included
$publishedCount = $count;
// If run from CLI, print what happened; if autoload missing, warn on CLI too
if (php_sapi_name() === 'cli') {
    if ($autoload_missing) {
        echo "Warning: vendor/autoload.php not found — running without composer autoload.\n";
    }
    echo "Published $count scheduled posts.\n";
}
?>
