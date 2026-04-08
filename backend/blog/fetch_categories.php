<?php
require_once __DIR__ . '/../database.php';

// Return a flat JSON array of all unique category names used in any post.
try {
    $cats = $db->blog->distinct('categories');
} catch (\Exception $e) {
    header('Content-Type: application/json', true, 500);
    echo json_encode(['error' => 'Failed to fetch categories']);
    exit;
}

$flat = [];
foreach ($cats as $catArr) {
    if (is_array($catArr)) {
        foreach ($catArr as $c) {
            $flat[] = $c;
        }
    } elseif (is_string($catArr) && trim($catArr) !== '') {
        $flat[] = $catArr;
    }
}

$flat = array_values(array_unique(array_filter(array_map('trim', $flat))));

header('Content-Type: application/json');
echo json_encode($flat);
        echo json_encode($flat);
