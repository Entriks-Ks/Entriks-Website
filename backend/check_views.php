<?php
require __DIR__ . '/database.php';

echo "=== CHECKING BLOG POSTS VIEWS & HISTORY ===\n\n";

$posts = $db->blog->find([], ['projection' => ['title' => 1, 'views' => 1, 'views_history' => 1, 'status' => 1]]);

foreach ($posts as $post) {
    echo "Post: " . ($post['title'] ?? 'Untitled') . "\n";
    echo "  Status: " . ($post['status'] ?? 'N/A') . "\n";
    echo "  Total views: " . ($post['views'] ?? 0) . "\n";
    echo "  views_history: ";
    if (isset($post['views_history'])) {
        print_r($post['views_history']);
    } else {
        echo "NOT SET\n";
    }
    echo "\n";
}

echo "\n=== DASHBOARD CALCULATION (Last 7 Days) ===\n";
$trafficData = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dayLabel = date('D', strtotime("-$i days"));
    
    $posts = $db->blog->find([], ['projection' => ['views_history' => 1]]);
    $dailyViews = 0;
    
    foreach ($posts as $post) {
        if (isset($post['views_history'][$date])) {
            $dailyViews += (int)$post['views_history'][$date];
        }
    }
    
    $trafficData[$date] = $dailyViews;
    echo "$dayLabel ($date): $dailyViews views\n";
}

echo "\nToday's date: " . date('Y-m-d') . "\n";
