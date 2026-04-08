<?php
// Public Endpoint - No Admin Session Check (safe reading only)
require "../database.php";

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *"); 

try {
    // Fetch featured posts that are also published
    // Allow optional `limit` query parameter (GET). Default 3, maximum 6 to avoid heavy responses.
    $requestedLimit = isset($_GET['limit']) ? intval($_GET['limit']) : 3;
    $limit = max(1, min(6, $requestedLimit));

    // Optimization: Try to load from static cache first
    $cacheFile = __DIR__ . "/../static/featured.json";
    if (file_exists($cacheFile)) {
        $cacheData = json_decode(file_get_contents($cacheFile), true);
        if ($cacheData && !empty($cacheData['posts'])) {
            // Filter by limit for API response
            $cachedPosts = array_slice($cacheData['posts'], 0, $limit);
            // Map cache fields to expected API fields (localized)
            $posts = array_map(function($p) {
                return [
                    'id' => $p['id'],
                    'title' => $p['title_de'],
                    'title_en' => $p['title_en'],
                    'author' => $p['author'],
                    'date' => $p['date'],
                    'image_url' => $p['image_url'],
                    'excerpt' => $p['excerpt_de'] // API usually serves for current lang, but here we keep de as default
                ];
            }, $cachedPosts);
            echo json_encode(['success' => true, 'posts' => $posts, 'cached' => true]);
            exit;
        }
    }

    if (!$db) {
        throw new Exception("Database connection not established");
    }

    $cursor = $db->blog->find(
        [
            'status' => 'published',
            'featured' => true
        ],
        [
            'sort' => ['created_at' => -1],
            'limit' => $limit
        ]
    );

    $posts = [];
    foreach ($cursor as $doc) {
        // Prepare image
        $imgUrl = null;
        if (isset($doc['image'])) {
            $imgUrl = "backend/image.php?id=" . (string)$doc['image'];
        }

        // Format Date
        $dateStr = '';
        if (isset($doc['created_at'])) {
            $dt = $doc['created_at']->toDateTime();
            $dt->setTimezone(new DateTimeZone('Europe/Amsterdam'));
            $dateStr = $dt->format('d F, Y');
        }

        $posts[] = [
            'id' => (string)$doc['_id'],
            'title' => $doc['title_de'] ?? $doc['title'] ?? 'Untitled',
            'title_en' => $doc['title_en'] ?? $doc['title'] ?? 'Untitled',
            'author' => $doc['author'] ?? 'Admin',
            'date' => $dateStr,
            'image_url' => $imgUrl,
            'excerpt' => mb_substr(strip_tags($doc['content_de'] ?? $doc['content'] ?? ''), 0, 100) . '...'
        ];
    }
    
    echo json_encode(['success' => true, 'posts' => $posts]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
