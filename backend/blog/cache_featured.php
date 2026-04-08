<?php
// This script regenerates the static cache for featured blog posts
// It should be included whenever a post's featured status, title, content, or image changes.

if (!isset($db)) {
    require_once __DIR__ . "/../database.php";
}

if (!$db) {
    return; // Cannot cache if DB is unreachable
}

try {
    $collection = $db->blog;
    // We always cache the max allowed (6) to be safe, though UI usually shows 2 or 3.
    $cursor = $collection->find(
        ['featured' => true, 'status' => 'published'],
        ['sort' => ['date' => -1], 'limit' => 6]
    );

    $cachedPosts = [];
    foreach ($cursor as $doc) {
        // Date formatting (check both created_at and date fields)
        $dateObj = $doc['created_at'] ?? ($doc['date'] ?? null);
        $date_de = '';
        $date_en = '';
        
        if ($dateObj) {
            $dt = ($dateObj instanceof MongoDB\BSON\UTCDateTime) ? $dateObj->toDateTime() : new DateTime((string)$dateObj);
            $dt->setTimezone(new DateTimeZone('Europe/Amsterdam'));
            
            // English Format: 23 December, 2025
            $date_en = $dt->format('d F, Y');
            
            // German Format: 23. Dezember 2025
            $months_de = [
                'January' => 'Januar', 'February' => 'Februar', 'March' => 'März',
                'April' => 'April', 'May' => 'Mai', 'June' => 'Juni',
                'July' => 'Juli', 'August' => 'August', 'September' => 'September',
                'October' => 'Oktober', 'November' => 'November', 'December' => 'Dezember'
            ];
            $month_en = $dt->format('F');
            $date_de = $dt->format('d. ') . ($months_de[$month_en] ?? $month_en) . $dt->format(' Y');
        }

        // Image URL - Check all possible fields
        $imgUrl = '';
        if (!empty($doc['image'])) {
            $imgUrl = "backend/image.php?id=" . (string)$doc['image'];
        } elseif (!empty($doc['image_id'])) {
            $imgUrl = "backend/image.php?id=" . (string)$doc['image_id'];
        } elseif (!empty($doc['image_url'])) {
            $imgUrl = $doc['image_url'];
        }

        $cachedPosts[] = [
            'id' => (string)$doc['_id'],
            'author' => $doc['author'] ?? 'Admin',
            'date_de' => $date_de,
            'date_en' => $date_en,
            'image_url' => $imgUrl,
            'title_de' => $doc['title_de'] ?? $doc['title'] ?? '(Kein Titel)',
            'title_en' => $doc['title_en'] ?? ($doc['title'] ?? '(No Title)'),
            'excerpt_de' => mb_substr(strip_tags($doc['content_de'] ?? $doc['content'] ?? ''), 0, 100) . '...',
            'excerpt_en' => mb_substr(strip_tags($doc['content_en'] ?? $doc['content'] ?? ''), 0, 100) . '...',
            'category' => $doc['categories'][0] ?? ($doc['category'] ?? 'Nearshoring')
        ];
    }

    $staticDir = __DIR__ . "/../static";
    if (!is_dir($staticDir)) {
        mkdir($staticDir, 0755, true);
    }

    file_put_contents($staticDir . "/featured.json", json_encode([
        'success' => true,
        'last_updated' => date('c'),
        'posts' => $cachedPosts
    ], JSON_PRETTY_PRINT));

} catch (Exception $e) {
    error_log("Static Cache Error: " . $e->getMessage());
}
