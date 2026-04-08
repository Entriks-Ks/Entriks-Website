<?php
// backend/seed_content.php
require_once __DIR__ . '/database.php';

header('Content-Type: text/plain');

if (!isset($db)) {
    die("Database connection failed.\n");
}

function extractDefaults($filePath)
{
    $content = file_get_contents($filePath);
    // Regex to find get_content('key', 'default value')
    // Supports single or double quotes
    // Note: This is a simple regex and might miss complex cases, but sufficient for the current file structure.
    $pattern = '/get_content\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*[\'"]((?:[^\'"\\\\]|\\\\.)*)[\'"]\s*\)/s';

    preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

    $results = [];
    foreach ($matches as $match) {
        $key = $match[1];
        $value = stripslashes($match[2]);  // Handle escaped quotes
        $results[$key] = $value;
    }
    return $results;
}

function seedPage($pageId, $filePath, $db)
{
    echo "Seeding page: $pageId from $filePath...\n";

    $defaults = extractDefaults($filePath);
    $collection = $db->content_blocks;

    $count = 0;
    foreach ($defaults as $key => $content) {
        // Check if exists
        $exists = $collection->findOne(['page_id' => $pageId, 'block_key' => $key]);

        if (!$exists) {
            $type = 'text';
            // Guess type based on key or content
            if (strpos($key, '_img') !== false || strpos($key, '_icon') !== false || strpos($key, '_bg') !== false) {
                $type = 'image';
            } elseif (strpos($key, '_href') !== false || strpos($key, 'link') !== false) {
                $type = 'link_href';  // Special marking
            } elseif (strpos($content, '<') !== false && strpos($content, '>') !== false) {
                $type = 'html';
            }

            $collection->insertOne([
                'page_id' => $pageId,
                'block_key' => $key,
                'content' => $content,
                'type' => $type,
                'last_updated' => new MongoDB\BSON\UTCDateTime()
            ]);
            echo "  Inserted: $key\n";
            $count++;
        } else {
            echo "  Skipped (Exists): $key\n";
        }
    }
    echo "Completed $pageId. Inserted $count blocks.\n\n";
}

// Seed Index (German)
seedPage('home', '../index.php', $db);

// Seed Index (English)
seedPage('home_en', '../index-en.php', $db);

echo 'Seeding Complete.';
?>
