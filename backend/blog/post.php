<?php
require_once dirname(__DIR__) . '/session_config.php';
require_once '../../database.php';

// Get post ID and language
$id = $_GET['id'] ?? null;
$lang = $_GET['lang'] ?? 'de';

if (!$id) {
    http_response_code(404);
    echo 'Post not found.';
    exit;
}

use MongoDB\BSON\ObjectId;

try {
    $postId = new ObjectId($id);
} catch (Exception $e) {
    http_response_code(404);
    echo 'Invalid post ID.';
    exit;
}

$post = $db->blog->findOne(['_id' => $postId]);
if (!$post || ($post['status'] ?? 'published') === 'scheduled') {
    http_response_code(404);
    echo 'Post not found.';
    exit;
}

// Select fields based on language
$title = ($lang === 'en') ? ($post['title_en'] ?? $post['title_de']) : ($post['title_de'] ?? $post['title_en']);
$content = ($lang === 'en') ? ($post['content_en'] ?? $post['content_de']) : ($post['content_de'] ?? $post['content_en']);
$description = ($lang === 'en') ? ($post['description_en'] ?? $post['description_de']) : ($post['description_de'] ?? $post['description_en']);

?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($title); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($description); ?>">
    <link rel="stylesheet" href="../../assets/css/style.css">
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Send AJAX request to increment view count
            const url = '../content_sync.php?id=<?php echo urlencode($id); ?>';

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    // console.log('View incremented:', data); // Removed console.log
                });
        });
        </script>
</head>
<body>
    <article>
        <h1><?php echo htmlspecialchars($title); ?></h1>
        <div class="blog-content">
            <?php echo $content; ?>
        </div>
    </article>
</body>
</html>
