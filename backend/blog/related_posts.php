<?php
// Reusable helper to fetch related posts by category/categories while excluding current post.
function get_related_posts($db, $postObjectId, $post = null, $limit = 5) {
    // Ensure we have the post document if not provided
    if ($post === null) {
        try {
            $post = $db->blog->findOne(['_id' => $postObjectId], ['projection' => ['category' => 1, 'categories' => 1]]);
        } catch (Exception $e) {
            $post = null;
        }
    }

    $categoryCandidates = [];
    if (!empty($post['categories']) && is_array($post['categories'])) {
        $categoryCandidates = array_values($post['categories']);
    } elseif (!empty($post['category'])) {
        $categoryCandidates = [(string)$post['category']];
    }

    $baseFilter = ['status' => 'published', '_id' => ['$ne' => $postObjectId]];

    if (!empty($categoryCandidates)) {
        $filter = $baseFilter;
        $filter['$or'] = [
            ['category' => ['$in' => $categoryCandidates]],
            ['categories' => ['$in' => $categoryCandidates]]
        ];
    } else {
        $filter = $baseFilter;
    }

    try {
        $recentPosts = iterator_to_array(
            $db->blog->find(
                $filter,
                [
                    'sort' => ['created_at' => -1],
                    'limit' => $limit,
                    'projection' => ['title' => 1, 'title_en' => 1, 'title_de' => 1, 'created_at' => 1, '_id' => 1, 'image' => 1, 'category' => 1, 'categories' => 1]
                ]
            ),
            false
        );
    } catch (Exception $e) {
        // Fallback: latest posts excluding current
        $recentPosts = iterator_to_array(
            $db->blog->find(
                ['status' => 'published', '_id' => ['$ne' => $postObjectId]],
                [
                    'sort' => ['created_at' => -1],
                    'limit' => $limit,
                    'projection' => ['title' => 1, 'title_en' => 1, 'title_de' => 1, 'created_at' => 1, '_id' => 1, 'image' => 1]
                ]
            ),
            false
        );
    }

    return $recentPosts;
}
