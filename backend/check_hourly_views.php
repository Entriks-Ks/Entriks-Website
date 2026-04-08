<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/database.php';

$hour = date('Y-m-d H');
echo "Checking for hour key: $hour\n";

// Check main_views
$mainDoc = $db->main_views->findOne(['date' => $hour]);
if ($mainDoc) {
    echo "Main Views for $hour: " . ($mainDoc['count'] ?? 0) . "\n";
} else {
    echo "No Main Views document found for $hour\n";
}

// Check blog views_history
$blogPost = $db->blog->findOne(['views_history.' . $hour => ['$exists' => true]]);
if ($blogPost) {
    echo "Found blog post with hourly views. Title: " . ($blogPost['title_de'] ?? 'Untitled') . "\n";
    echo "Views for $hour: " . $blogPost['views_history'][$hour] . "\n";
} else {
    echo "No blog post found with hourly views for $hour\n";
}
