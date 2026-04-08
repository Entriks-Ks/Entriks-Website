<?php
// Scheduled posts for dashboard
$scheduledPosts = $db->blog->find([
    'status' => 'scheduled'
], [
    'sort' => ['publish_at' => 1],
    'projection' => ['title_de' => 1, 'title_en' => 1, 'publish_at' => 1]
]);
?>
<?php
$count = 0;
foreach ($scheduledPosts as $sp) {
    $count++;
    $postId = (string) $sp->_id;
    echo '<li class="dash-list-item">';
    echo '<a href="../blog.php?id=' . $postId . '" class="dash-list-link" target="_blank">';
    echo '<span style="display:flex;align-items:center;">';
    echo '<span class="rank-badge">' . $count . '</span>';
    $titleFull = $sp->title_de ?? $sp->title_en ?? 'Untitled';
    $parts = explode(' ', $titleFull);
    $title = htmlspecialchars(count($parts) > 2 ? implode(' ', array_slice($parts, 0, 2)) . '...' : $titleFull);
    echo '<span class="item-title" style="max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">' . $title . '</span>';
    echo '</span>';
    echo '<span class="item-meta">';
    if ($sp->publish_at) {
        $dt = $sp->publish_at->toDateTime();
        $dt->setTimezone(new DateTimeZone('Europe/Berlin'));
        echo $dt->format('M d, Y H:i');
    } else {
        echo 'Unknown';
    }
    echo '</span>';
    echo '</a>';
    echo '</li>';
}
if ($count === 0) {
    echo '<li class="dash-list-item no-scheduled-posts">' . $lang['dash_no_scheduled'] . '</li>';
}
