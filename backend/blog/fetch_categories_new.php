<?php
require_once dirname(__DIR__) . '/session_config.php';
require_once dirname(__DIR__) . '/database.php';
if (!isset($_SESSION['admin'])) {
    header('Content-Type: application/json', true, 403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userPerms = $_SESSION['admin']['permissions'] ?? [];
if ($userPerms instanceof \MongoDB\Model\BSONArray) {
    $userPerms = $userPerms->getArrayCopy();
} else {
    $userPerms = (array) $userPerms;
}
$isAdmin = ($_SESSION['admin']['role'] ?? 'admin') === 'admin';
$hasBlogAccess = $isAdmin || in_array('blog', $userPerms);

if (!$hasBlogAccess) {
    header('Content-Type: application/json', true, 403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    $names = [];
    try {
        $cursor = $db->categories->find([], ['projection' => ['name' => 1]]);
        foreach ($cursor as $doc) {
            if (!empty($doc->name) && is_string($doc->name)) {
                $names[] = trim($doc->name);
            }
        }
    } catch (Exception $e) {
    }

    if (empty($names)) {
        $cats = $db->blog->distinct('categories');
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
        $names = array_values(array_unique(array_filter(array_map('trim', $flat))));
    } else {
        $names = array_values(array_unique(array_filter($names)));
    }
} catch (\Exception $e) {
    header('Content-Type: application/json', true, 500);
    echo json_encode(['error' => 'Failed to fetch categories']);
    exit;
}

header('Content-Type: application/json');
echo json_encode($names);
