<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/backend/database.php';
require __DIR__ . '/backend/config.php';
require __DIR__ . '/backend/gridfs.php';
require __DIR__ . '/backend/blog/translation.php';

require_once __DIR__ . '/backend/blog/related_posts.php';

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

$isAdmin = isset($_SESSION['admin']);
$isEditorMode = $isAdmin && isset($_GET['edit']) && $_GET['edit'] === 'true';
$lang = $_GET['lang'] ?? $_COOKIE['lang'] ?? 'en';
setcookie('lang', $lang, time() + 86400 * 365, '/');

$siteName = 'ENTRIKS';
$contactEmail = 'info@entriks.com';
$contactPhone = '+383 43 889 344';
$contactAddress = 'Lot Vaku L2.1, 10000 Pristina, Kosovo';
$socialLinkedin = 'https://www.linkedin.com/company/entriks';
$socialFacebook = 'https://www.facebook.com/ENTRIKS/';
$socialInstagram = 'https://www.instagram.com/entriks_/';
$siteFaviconUrl = 'assets/img/favicon.png';
$logoUrl = 'assets/img/logo.png';
$blogShowRecent = true;
$blogShowCategories = true;
$blogShowTags = true;
$postsPerPage = 3;

if (isset($db)) {
    try {
        $settings = $db->settings->findOne(['type' => 'global_config']);
        if ($settings) {
            if (!empty($settings['site_name']))
                $siteName = $settings['site_name'];
            if (!empty($settings['contact_email']))
                $contactEmail = $settings['contact_email'];
            if (!empty($settings['contact_phone']))
                $contactPhone = $settings['contact_phone'];
            if (!empty($settings['contact_address']))
                $contactAddress = $settings['contact_address'];
            if (!empty($settings['social_linkedin']))
                $socialLinkedin = $settings['social_linkedin'];
            if (!empty($settings['social_facebook']))
                $socialFacebook = $settings['social_facebook'];
            if (!empty($settings['social_instagram']))
                $socialInstagram = $settings['social_instagram'];
            if (!empty($settings['favicon_url']))
                $siteFaviconUrl = $settings['favicon_url'];
            if (!empty($settings['logo_url']))
                $logoUrl = $settings['logo_url'];
            if (!empty($settings['footer_logo_url']))
                $footerLogoUrl = $settings['footer_logo_url'];
            if (!empty($settings['footer_text_de']))
                $footerTextDe = $settings['footer_text_de'];
            if (!empty($settings['footer_text_en']))
                $footerTextEn = $settings['footer_text_en'];
            if (isset($settings['blog_show_recent_posts']))
                $blogShowRecent = $settings['blog_show_recent_posts'];
            if (isset($settings['blog_show_categories']))
                $blogShowCategories = $settings['blog_show_categories'];
            if (isset($settings['blog_show_tags']))
                $blogShowTags = $settings['blog_show_tags'];
            if (isset($settings['posts_per_page']))
                $postsPerPage = (int) $settings['posts_per_page'];
        }
    } catch (Exception $e) {
    }
}

$translations = [
    'de' => [
        'nav_startseite' => 'Startseite',
        'nav_ueber_uns' => 'Über Uns',
        'nav_leistungen' => 'Leistungen',
        'nav_projekte' => 'Projekte',
        'nav_kontakt' => 'Kontakt',
        'breadcrumb_blog' => 'Blog',
        'author' => 'Autor',
        'admin' => 'Admin',
        'page_title' => 'Blog',
        'date' => 'Datum',
        'date_format' => 'd. M Y',
        'tags' => 'Schlagwörter',
        'share' => 'Teilen',
        'comment_section' => 'Kommentare',
        'leave_comment' => 'Hinterlassen Sie einen Kommentar',
        'name' => 'Name',
        'email' => 'E-Mail',
        'message' => 'Nachricht',
        'send' => 'Absenden',
        'recent_posts' => 'Neueste Beiträge',
        'search' => 'Suchen',
        'no_comments' => 'Noch keine Kommentare. Sei der Erste!',
        'reply' => 'Antworten',
        'comment_count' => 'Kommentar',
        'comment_count_plural' => 'Kommentare',
        'comment_placeholder' => 'Kommentar',
        'send_comment' => 'Kommentar senden',
        'footer_company' => 'Unternehmen',
        'footer_contact' => 'Kontaktinformationen',
        'footer_description' => 'ENTRIKS Talent Hub verbindet DACH-Unternehmen mit hochqualifizierten Fachkräften aus dem Kosovo – durch Nearshoring und Active Sourcing. Wir bieten Lösungen für Unternehmen, die nach qualifizierten Fachkräften suchen.',
        'address' => 'Adresse',
        'phone' => 'Telefon',
        'categories' => 'Kategorien',
        'follow_us' => 'Folgen Sie uns',
        'tags_title' => 'Schlagwörter',
        'no_posts_found' => 'Keine Beiträge gefunden',
        'no_posts_desc' => 'Es wurden keine Beiträge gefunden.',
        'back_to_overview' => 'Zurück zur Übersicht',
        'read_more' => 'Mehr lesen'
    ],
    'en' => [
        'nav_startseite' => 'Home',
        'nav_ueber_uns' => 'About Us',
        'nav_leistungen' => 'Services',
        'nav_projekte' => 'Projects',
        'nav_kontakt' => 'Contact',
        'breadcrumb_blog' => 'Blog',
        'author' => 'Author',
        'admin' => 'Admin',
        'page_title' => 'Blog',
        'date' => 'Date',
        'date_format' => 'd M, Y',
        'tags' => 'Tags',
        'share' => 'Share',
        'comment_section' => 'Comments',
        'leave_comment' => 'Leave a Comment',
        'name' => 'Name',
        'email' => 'Email',
        'message' => 'Message',
        'send' => 'Send',
        'recent_posts' => 'Recent Posts',
        'search' => 'Search',
        'no_comments' => 'No comments yet. Be the first!',
        'reply' => 'Reply',
        'comment_count' => 'Comment',
        'comment_count_plural' => 'Comments',
        'comment_placeholder' => 'Comment',
        'send_comment' => 'Send Comment',
        'footer_company' => 'Company',
        'footer_contact' => 'Contact Info',
        'footer_description' => 'ENTRIKS Talent Hub connects DACH companies with highly qualified professionals from Kosovo through Nearshoring and Active Sourcing. We offer solutions for companies looking for qualified professionals.',
        'address' => 'Address',
        'phone' => 'Phone',
        'categories' => 'Categories',
        'follow_us' => 'Follow Us',
        'tags_title' => 'Tags',
        'no_posts_found' => 'No Posts Found',
        'no_posts_desc' => 'No posts found.',
        'back_to_overview' => 'Back to Overview',
        'read_more' => 'Read More'
    ]
];

$translations_extra = [
    'de' => [
        'see_replies' => 'Siehe %d Antworten',
        'hide_replies' => 'Antworten verbergen'
    ],
    'en' => [
        'see_replies' => 'See %d Replies',
        'hide_replies' => 'Hide Replies'
    ]
];

foreach ($translations_extra as $k => $v) {
    $translations[$k] = array_merge($translations[$k] ?? [], $v);
}

$footer_content = [
    'de' => [
        'nav_links' => ['Nearshoring', 'Active Sourcing', 'Blog', 'Über uns', 'Kontakt'],
        'footer' => [
            'desc' => 'ENTRIKS Talent Hub verbindet DACH-Unternehmen mit hochqualifizierten Fachkräften aus dem Kosovo – durch Nearshoring und Active Sourcing.',
            'note' => 'Teil der ENTRIKS Group',
            'contact' => 'Kontakt',
            'services' => 'Leistungen',
            'company' => 'Unternehmen',
            'links' => [
                'Nearshoring Dedicated',
                'Nearshoring Team',
                'Active Sourcing',
                'Kosovo Standort'
            ],
            'company_links' => [
                'ENTRIKS Group',
                'ENTRIKS Karriere',
                'ENTRIKS Banking',
                'ENTRIKS Sales',
                'ENTRIKS Software'
            ],
            'legal' => ['Impressum', 'Datenschutz', 'AGB'],
            'copyright' => '© ' . date('Y') . ' ENTRIKS Talent Hub | Teil der ENTRIKS Group'
        ],
        'modal' => [
            'email_address' => 'info@entriks.com',
            'phone' => '+38343889344',
            'phone_display' => '+383 43 889 344'
        ],
        'about' => [
            'location_name' => 'ENTRIKS Talent Hub',
            'location_city' => '10000 Pristina, Kosovo'
        ]
    ],
    'en' => [
        'nav_links' => ['Nearshoring', 'Active Sourcing', 'Blog', 'About Us', 'Contact'],
        'footer' => [
            'desc' => 'ENTRIKS Talent Hub connects DACH companies with highly qualified professionals from Kosovo through Nearshoring and Active Sourcing.',
            'note' => 'Part of the ENTRIKS Group',
            'contact' => 'Contact',
            'services' => 'Services',
            'company' => 'Company',
            'links' => [
                'Nearshoring Dedicated',
                'Nearshoring Team',
                'Active Sourcing',
                'Kosovo Location'
            ],
            'company_links' => [
                'ENTRIKS Group',
                'ENTRIKS Career',
                'ENTRIKS Banking',
                'ENTRIKS Sales',
                'ENTRIKS Software'
            ],
            'legal' => ['Legal Notice', 'Privacy', 'Terms'],
            'copyright' => '© ' . date('Y') . ' ENTRIKS Talent Hub | Part of the ENTRIKS Group'
        ],
        'modal' => [
            'email_address' => 'info@entriks.com',
            'phone' => '+38343889344',
            'phone_display' => '+383 43 889 344'
        ],
        'about' => [
            'location_name' => 'ENTRIKS Talent Hub',
            'location_city' => '10000 Pristina, Kosovo'
        ]
    ]
];
$fc = $footer_content[$lang];
$t = $translations[$lang] ?? $translations['de'];
$c = $t; // Alias for template compatibility

function formatDate($mongoDate, $lang, $translations)
{
    if (!isset($mongoDate)) {
        return 'N/A';
    }
    $dateTime = $mongoDate->toDateTime();
    $format = $translations[$lang]['date_format'] ?? 'd M, Y';

    if ($lang === 'de') {
        $months = ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];
        $day = $dateTime->format('d');
        $month = $months[$dateTime->format('n') - 1];
        $year = $dateTime->format('Y');
        return $day . '. ' . $month . ' ' . $year;
    } else {
        return $dateTime->format($format);
    }
}

$commentsCollection = $db->comments ?? $db->selectCollection('comments');

$isSingle = isset($_GET['id']);
$listingCategory = $_GET['category'] ?? null;
$listingTag = $_GET['tag'] ?? null;
$isListing = !$isSingle && ($listingCategory || $listingTag);

if (!$isSingle && !$isListing) {
    header('Location: index.php');
    exit;
}

$post = null;
$listingPosts = [];
$pageTitle = 'Blog';
$postObjectId = null;
$shouldIncrementView = false;

try {
    if ($isSingle) {
        $postId = $_GET['id'];
        $postObjectId = new ObjectId($postId);

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment_submit'])) {
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $message = trim($_POST['message'] ?? '');
            $parentRaw = trim($_POST['parent_id'] ?? '');

            error_log('[REPLY DEBUG] Full POST data: ' . print_r($_POST, true));

            $parentId = null;
            if ($parentRaw && preg_match('/^[a-f\d]{24}$/i', $parentRaw)) {
                try {
                    $parentId = new ObjectId($parentRaw);
                } catch (Exception $e) {
                    $parentId = null;
                }
            }

            if ($name && $email && $message) {
                $commentData = [
                    'post_id' => $postObjectId,
                    'name' => $name,
                    'email' => $email,
                    'message' => $message,
                    'message_en' => $message,
                    'created_at' => new UTCDateTime(),
                    'lang' => $lang
                ];
                if ($parentId) {
                    $commentData['parent_id'] = $parentId;
                }
                $commentsCollection->insertOne($commentData);
            }

            header('Location: blog.php?id=' . urlencode($postId) . '&lang=' . urlencode($lang) . '#comments');
            exit;
        }

        $post = $db->blog->findOne(['_id' => $postObjectId]);

        if (!$post) {
            echo 'Beitrag nicht gefunden.';
            exit;
        }

        if ($lang === 'en') {
            if (!isset($post['title_en']) || empty($post['title_en']))
                $post['title_en'] = $post['title_de'] ?? 'Untitled';
            if (!isset($post['content_en']) || empty($post['content_en']))
                $post['content_en'] = $post['content_de'] ?? '';
        }

        $shouldIncrementView = true;

        $imageData = null;
        $imageMimeType = 'image/jpeg';
        $hasImage = isset($post['image']);
        if ($hasImage) {
            try {
                $bucket = $db->selectGridFSBucket();
                $imageId = $post['image'] instanceof ObjectId ? $post['image'] : new ObjectId((string) $post['image']);
                $stream = $bucket->openDownloadStream($imageId);
                $rawImageData = stream_get_contents($stream);
                if ($rawImageData !== false) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $detectedMime = finfo_buffer($finfo, $rawImageData);
                    if ($detectedMime && strpos($detectedMime, 'image/') !== false)
                        $imageMimeType = $detectedMime;
                    $imageData = base64_encode($rawImageData);
                }
            } catch (Exception $e) {
                $imageData = null;
            }
        }

        $comments = iterator_to_array(
            $commentsCollection->find(
                [
                    'post_id' => $postObjectId,
                    'deleted' => ['$ne' => true],
                    'status' => ['$nin' => ['deleted', 'removed', 'trashed']],
                ],
                ['sort' => ['created_at' => -1], 'limit' => 100]
            ),
            false
        );

        $totalComments = count($comments);
        $topLevelCommentsCount = count(array_filter($comments, function ($c) {
            return !isset($c['parent_id']);
        }));
        $replyCommentsCount = max(0, $totalComments - $topLevelCommentsCount);

        function render_comment_branch($comments, $parentId = null, &$index = 0, $avatarCount = 3, $level = 0, $lang = 'de', $translations = [])
        {
            foreach ($comments as $comment) {
                $isChild = isset($comment['parent_id']) && ($parentId !== null) && ((string) $comment['parent_id'] === (string) $parentId);
                $isTop = !isset($comment['parent_id']) && $parentId === null;
                if (!($isTop || $isChild))
                    continue;

                $commentMessage = $comment['message'] ?? '';
                $avatarNum = ($index % $avatarCount) + 1;
                $avatarSrc = "assets/img/avatar-img-{$avatarNum}.png";

                $score = (int) ($comment['likes'] ?? 0) - (int) ($comment['dislikes'] ?? 0);
                $likesCount = (int) ($comment['likes'] ?? 0);
                $createdTimestamp = $comment['created_at'] instanceof UTCDateTime
                    ? $comment['created_at']->toDateTime()->getTimestamp()
                    : 0;
                echo '<div class="modern-comment-item" data-score="' . $score . '" data-likes="' . $likesCount . '" data-created="' . $createdTimestamp . '" style="margin-left:' . (int) ($level * 32) . 'px">';
                echo '<img src="' . htmlspecialchars($avatarSrc) . '" class="user-avatar-placeholder" alt="Profile">';
                echo '<div class="comment-content-wrapper">';
                echo '<div class="comment-author-row">';
                echo '<div class="comment-author-name">' . htmlspecialchars($comment['name'] ?? 'Anonymous') . ' <span class="comment-date" style="margin-left:8px;font-weight:400;">' . htmlspecialchars(formatDate($comment['created_at'] ?? null, $lang, $translations)) . '</span></div>';
                echo '</div>';
                echo '<div class="comment-text">' . htmlspecialchars($commentMessage) . '</div>';
                echo '<div class="comment-actions">';
                echo '<button type="button" class="action-btn vote-btn" data-comment-id="' . (string) $comment['_id'] . '" data-action="like">';
                echo '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:18px;height:18px;"><path stroke-linecap="round" stroke-linejoin="round" d="M6.633 10.25c.806 0 1.533-.446 2.031-1.08a9.041 9.041 0 0 1 2.861-2.4c.723-.384 1.35-.956 1.653-1.715a4.498 4.498 0 0 0 .322-1.672V2.75a.75.75 0 0 1 .75-.75 2.25 2.25 0 0 1 2.25 2.25c0 1.152-.26 2.243-.723 3.218-.266.558.107 1.282.725 1.282m0 0h3.126c1.026 0 1.945.694 2.054 1.715.045.422.068.85.068 1.285a11.95 11.95 0 0 1-2.649 7.521c-.388.482-.987.729-1.605.729H13.48c-.483 0-.964-.078-1.423-.23l-3.114-1.04a4.501 4.501 0 0 0-1.423-.23H5.904m10.598-9.75H14.25M5.904 18.5c.083.205.173.405.27.602.197.4-.078.898-.523.898h-.908c-.889 0-1.713-.518-1.972-1.368a12 12 0 0 1-.521-3.507c0-1.553.295-3.036.831-4.398C3.387 9.953 4.167 9.5 5 9.5h1.053c.472 0 .745.556.5.96a8.958 8.958 0 0 0-1.302 4.665c0 1.194.232 2.333.654 3.375Z" /></svg>';
                echo '<span class="count">' . intval($comment['likes'] ?? 0) . '</span>';
                echo '</button>';
                echo '<button type="button" class="action-btn vote-btn" data-comment-id="' . (string) $comment['_id'] . '" data-action="dislike">';
                echo '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:18px;height:18px;"><path stroke-linecap="round" stroke-linejoin="round" d="M7.498 15.25H4.372c-1.026 0-1.945-.694-2.054-1.715a12.137 12.137 0 0 1-.068-1.285c0-2.848.992-5.464 2.649-7.521C5.287 4.247 5.886 4 6.504 4h4.016a4.5 4.5 0 0 1 1.423.23l3.114 1.04a4.5 4.5 0 0 0 1.423.23h1.294M7.498 15.25c.618 0 .991.724.725 1.282A7.471 7.471 0 0 0 7.5 19.75 2.25 2.25 0 0 0 9.75 22a.75.75 0 0 0 .75-.75v-.633c0-.573.11-1.14.322-1.672.304-.76.93-1.33 1.653-1.715a9.04 9.04 0 0 0 2.86-2.4c.498-.634 1.226-1.08 2.032-1.08h.384m-10.253 1.5H9.7m8.075-9.75c.01.05.027.1.05.148.593 1.2.925 2.55.925 3.977 0 1.487-.36 2.89-.999 4.125m.023-8.25c-.076-.365.183-.75.575-.75h.908c.889 0 1.713.518 1.972 1.368.339 1.11.521 2.287.521 3.507 0 1.553-.295 3.036-.831 4.398-.306.774-1.086 1.227-1.918 1.227h-1.053c-.472 0-.745-.556-.5-.96a8.95 8.95 0 0 0 .303-.54" /></svg>';
                echo '<span class="count">' . intval($comment['dislikes'] ?? 0) . '</span>';
                echo '</button>';
                echo '<button type="button" class="action-btn reply reply-link" data-target="' . (string) $comment['_id'] . '" data-author="' . htmlspecialchars($comment['name'] ?? 'Anonymous') . '">';
                echo '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:18px;height:18px;margin-right:4px;"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 9.75a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375m-13.5 3.01c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 0 1 .778-.332 48.294 48.294 0 0 0 5.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" /></svg>' . ($translations['reply'] ?? 'Reply');
                echo '</button>';
                echo '</div>';

                $children = array_filter($comments, function ($c) use ($comment) {
                    return isset($c['parent_id']) && ((string) $c['parent_id'] === (string) $comment['_id']);
                });
                $childCount = count($children);
                if ($childCount > 0) {
                    $repliesId = 'replies-' . (string) $comment['_id'];
                    $showLabel = sprintf($translations['see_replies'] ?? 'See %d Replies', $childCount);
                    $hideLabel = $translations['hide_replies'] ?? 'Hide Replies';
                    echo '<a href="#" class="reply-toggle-modern toggle-replies-link" data-target="' . htmlspecialchars($repliesId) . '" data-count="' . (int) $childCount . '" data-show-label="' . htmlspecialchars($showLabel) . '" data-hide-label="' . htmlspecialchars($hideLabel) . '" style="display:inline-flex; margin-top:8px;">' . htmlspecialchars($showLabel) . '</a>';
                    echo '<div id="' . htmlspecialchars($repliesId) . '" style="display:none; margin-top: 12px;">';

                    $index++;
                    render_comment_branch($comments, $comment['_id'], $index, $avatarCount, $level + 1, $lang, $translations);
                    echo '</div>';
                } else {
                    $index++;
                }

                echo '</div>';
                echo '</div>';
            }
        }

        $pageTitle = htmlspecialchars($post['title_' . $lang] ?? $post['title'] ?? 'ENTRIKS');
    } else {
        $filter = ['status' => 'published'];

        if ($listingCategory) {
            $filter['$or'] = [
                ['categories' => $listingCategory],
                ['category' => $listingCategory]
            ];
            $pageTitle = ($lang === 'de' ? 'Kategorie: ' : 'Category: ') . htmlspecialchars($listingCategory);
        } elseif ($listingTag) {
            $filter['tags'] = $listingTag;
            $pageTitle = ($lang === 'de' ? 'Tag: ' : 'Tag: ') . htmlspecialchars($listingTag);
        }

        $cursor = $db->blog->find($filter, ['sort' => ['created_at' => -1]]);
        $listingPosts = iterator_to_array($cursor);
    }
} catch (Exception $e) {
    echo 'Fehler: ' . $e->getMessage();
    exit;
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">

<head>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('consent', 'default', {
        'ad_storage': 'denied',
        'ad_user_data': 'denied',
        'ad_personalization': 'denied',
        'analytics_storage': 'denied'
      });
    </script>
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-5CPLN3G8NT"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());

      gtag('config', 'G-5CPLN3G8NT');
    </script>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="ENTRIKS">

    <title><?php echo $pageTitle; ?></title>

    <link rel="shortcut icon" href="<?= htmlspecialchars($siteFaviconUrl) ?>" type="image/x-icon">

    <style>
        *,
        *::before,
        *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box
        }

        :root {
            --gold: #c9a227;
            --gold-hover: #b8911f;
            --cyan: #20c1f5;
            --bg-black: #000;
            --text-white: #fff;
            --text-muted: #888;
            --border: #2a2a2a
        }

        a {
            text-decoration: none;
            color: inherit
        }

        button {
            cursor: pointer;
            border: none;
            outline: none;
            font-family: inherit
        }

        img {
            display: block
        }

        .container,
        body .container {
            max-width: 1400px !important;
            margin: 0 auto;
            padding: 0 2rem
        }

        .logo-wrap {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 0.2rem;
            cursor: pointer
        }

        .logo-img {
            height: 28px;
            width: auto;
            display: block
        }

        .logo-sub {
            font-family: 'Orbitron', 'Inter', sans-serif;
            font-size: 0.65rem;
            font-weight: 700;
            color: #fff;
            letter-spacing: 0.25em;
            line-height: 1;
            text-transform: uppercase;
            padding-left: 2px;
            margin-top: 2px
        }

        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1000;
            padding: 1.1rem 0;
            transition: all .3s;
            background: rgba(8, 8, 8, .95);
            transform: translateY(0)
        }

        .navbar.hidden {
            transform: translateY(-100%)
        }

        .navbar.scrolled {
            background: rgba(8, 8, 8, .95);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(255, 255, 255, .06)
        }

        .nav-inner {
            display: flex;
            align-items: center;
            justify-content: space-between
        }

        .nav-links {
            display: flex;
            gap: 2.25rem
        }

        .nav-links a {
            font-size: .88rem;
            color: #bbb;
            font-weight: 500;
            transition: color .2s;
            white-space: nowrap
        }

        .nav-links a:hover {
            color: #fff
        }

        .lang-dropdown-wrap {
            position: relative
        }

        .lang-globe-btn {
            display: flex;
            align-items: center;
            gap: .4rem;
            background: rgba(255, 255, 255, .06);
            border: 1px solid rgba(255, 255, 255, .12);
            border-radius: 2rem;
            padding: .35rem .75rem;
            cursor: pointer;
            color: #ccc;
            font-size: .78rem;
            font-weight: 700;
            letter-spacing: .06em;
            transition: background .2s, border-color .2s
        }

        .lang-globe-btn:hover {
            background: rgba(255, 255, 255, .1);
            border-color: rgba(255, 255, 255, .25);
            color: #fff
        }

        .lang-globe-btn svg {
            width: 15px;
            height: 15px;
            flex-shrink: 0
        }

        .lang-globe-btn .lang-caret {
            width: 10px;
            height: 10px;
            transition: transform .25s
        }

        .lang-dropdown-wrap.open .lang-caret {
            transform: rotate(180deg)
        }

        .lang-dropdown {
            display: none;
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: .65rem;
            overflow: hidden;
            min-width: 130px;
            box-shadow: 0 12px 32px rgba(0, 0, 0, .45);
            z-index: 2000
        }

        .lang-dropdown-wrap.open .lang-dropdown {
            display: block
        }

        .lang-option {
            display: flex;
            align-items: center;
            gap: .6rem;
            width: 100%;
            padding: .7rem 1rem;
            background: none;
            border: none;
            color: #bbb;
            font-size: .85rem;
            font-weight: 500;
            cursor: pointer;
            text-align: left;
            transition: background .2s, color .2s;
            text-decoration: none
        }

        .lang-option:hover {
            background: rgba(255, 255, 255, .06);
            color: #fff
        }

        .lang-option.active {
            color: var(--gold);
            font-weight: 700
        }

        .lang-option .lang-flag {
            font-size: 1.1rem;
            line-height: 1
        }

        .lang-option .lang-check {
            margin-left: auto;
            color: var(--gold);
            display: none
        }

        .lang-option.active .lang-check {
            display: block
        }

        .lang-btn {
            background: none;
            border: none;
            color: #888;
            font-size: .78rem;
            font-weight: 700;
            letter-spacing: .08em;
            cursor: pointer;
            padding: .2rem .4rem;
            border-radius: 1rem;
            transition: color .2s;
            text-decoration: none
        }

        .lang-btn.active {
            color: var(--gold)
        }

        .mob-btn {
            display: none;
            background: none;
            color: #fff;
            padding: .25rem
        }

        .nav-mobile-group {
            display: none;
            align-items: center;
            gap: .75rem
        }

        .mob-menu {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            width: 100%;
            background: #111;
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--border);
            flex-direction: column;
            gap: 1.1rem;
            z-index: 999
        }

        .mob-menu.open {
            display: flex
        }

        @media(max-width:999px) {
            .nav-links,
            .nav-cta {
                display: none
            }

            .mob-btn {
                display: block
            }

            .nav-mobile-group {
                display: flex;
                align-items: center;
                gap: .75rem
            }

            .nav-mobile-group .lang-globe-btn {
                display: flex
            }
        }

        .page-header {
            margin-bottom: 3rem;
            padding: 3rem 0 2.5rem;
            position: relative
        }

        .page-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 120px;
            height: 4px;
            background: var(--gold);
            border-radius: 2px;
            display: var(--gold-line-display, block)
        }

        .page-header h1 {
            font-family: 'Playfair Display', serif;
            font-size: clamp(2.2rem, 5vw, 3.5rem);
            color: #1a1a1a;
            margin-bottom: 0.75rem;
            font-weight: 700;
            line-height: 1.2;
            letter-spacing: -0.02em
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            color: #888;
            margin-bottom: 1rem
        }

        .breadcrumb a {
            color: #666;
            text-decoration: none;
            transition: color 0.2s
        }

        .breadcrumb a:hover {
            color: var(--gold)
        }

        .breadcrumb-separator {
            color: #ccc;
            font-size: 0.75rem
        }

        .breadcrumb-current {
            color: #1a1a1a;
            font-weight: 500
        }

        .page-header-subtitle {
            font-size: 1.1rem;
            color: #666;
            font-weight: 400;
            margin-top: 0.5rem
        }

        .back-to-blog-link:hover {
            color: var(--gold) !important
        }

        .footer {
            background: #070707;
            padding: 4rem 0 0;
            border-top: 1px solid var(--border)
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 1.5fr 1fr 1fr 0.8fr;
            gap: 2.5rem;
            padding-bottom: 3.5rem
        }

        .footer-desc {
            color: #888;
            font-size: .85rem;
            line-height: 1.75;
            margin: 1rem 0 1.1rem
        }

        .footer-note {
            font-size: .78rem;
            color: #C9A227;
            margin-bottom: 1rem
        }

        .footer-social {
            display: flex;
            gap: .65rem
        }

        .footer-social a {
            color: #888;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            border: 1px solid #2a2a2a;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all .2s
        }

        .footer-social a:hover {
            color: var(--cyan);
            border-color: var(--cyan)
        }

        .fcol h4 {
            font-size: .65rem;
            font-weight: 700;
            letter-spacing: .18em;
            text-transform: uppercase;
            color: var(--cyan);
            margin-bottom: 1.25rem
        }

        .fcol ul {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: .55rem
        }

        .fcol ul li a {
            font-size: .85rem;
            color: #888;
            transition: color .2s
        }

        .fcol ul li a:hover {
            color: #fff
        }

        .footer-bottom {
            border-top: 1px solid var(--border);
            padding: 1.4rem 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: .78rem;
            color: #888;
            flex-wrap: wrap;
            gap: .5rem
        }

        .fbl {
            display: flex;
            gap: 1.4rem
        }

        .fbl a {
            color: #888;
            font-size: .78rem;
            transition: color .2s
        }

        .fbl a:hover {
            color: #fff
        }

        .back-to-top {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: var(--gold);
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #000;
            opacity: 0;
            visibility: hidden;
            transform: translateY(20px);
            transition: all 0.3s ease;
            z-index: 999
        }

        .back-to-top.visible {
            opacity: 1;
            visibility: visible;
            transform: translateY(0)
        }

        .back-to-top:hover {
            transform: translateY(-4px)
        }

        .back-to-top svg {
            width: 24px;
            height: 24px
        }

        @media(max-width:1200px) {
            .footer-grid {
                grid-template-columns: 1fr 1fr 1fr
            }
        }

        @media(max-width:968px) {
            .nav-links {
                display: none
            }

            .mob-btn {
                display: block
            }

            .footer-grid {
                grid-template-columns: 1fr 1fr
            }
        }

        @media(max-width:480px) {
            .container {
                padding: 0 20px
            }

            .footer-grid {
                grid-template-columns: 1fr
            }

            .footer-bottom {
                flex-direction: column;
                text-align: center;
                gap: 1rem
            }

            .fbl {
                justify-content: center;
                flex-wrap: wrap
            }
        }

        html {
            scroll-behavior: smooth
        }

        body {
            margin: 0;
            font-family: 'Manrope', sans-serif;
            line-height: 1.5;
            color: #333;
            background: #f5f4f1;
        }

        .breadcrumb-area {
            padding: 60px 0 40px;
            background: #f8f9fa
        }

        .blog-area {
            padding: 80px 0
        }

        /* Modern Comment System */
        .modern-comments-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-top: 20px;
        }

        .modern-comments-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1a1a1a !important;
            margin: 0;
        }

        .modern-comments-sort {
            display: flex;
            gap: 10px;
        }

        .modern-sort-btn {
            background: #f0f0f0;
            border: none;
            color: #555;
            padding: 8px 16px;
            border-radius: 10px;
            font-size: 0.85rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .modern-sort-btn.active {
            background: #1a1a1a;
            color: #fff;
        }

        .modern-comment-form-card {
            background: #f8f9fa;
            padding: 24px;
            border-radius: 10px;
            margin-bottom: 40px;
            display: flex;
            gap: 16px;
            align-items: flex-start;
            border: 1px solid #e0e0e0;
        }

        /* Enforce no hover change for sort buttons */
        .modern-sort-btn:hover {
            background: #e0e0e0 !important;
            color: #555 !important;
        }

        .modern-sort-btn.active:hover {
            background: #1a1a1a !important;
            color: #fff !important;
        }

        .user-avatar-placeholder {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            flex-shrink: 0;
        }

        .form-inputs-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .form-row-inputs {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .form-input-modern {
            flex: 1;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 12px 16px;
            color: #333;
            font-size: 0.95rem;
            outline: none;
            min-width: 200px;
            width: 100%;
        }

        .form-input-modern:focus {
            border-color: #c9a227;
        }

        .form-textarea-wrapper {
            position: relative;
        }

        .form-textarea-modern {
            width: 100%;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 16px 60px 16px 16px;
            color: #333;
            min-height: 100px;
            resize: vertical;
            outline: none;
            font-family: inherit;
        }

        .form-textarea-modern:focus {
            border-color: #c9a227;
        }

        .send-btn-modern {
            position: absolute;
            bottom: 12px;
            right: 12px;
            background: #c9a227;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 10px;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(201, 162, 39, 0.4);
        }

        .modern-comment-item {
            display: flex;
            gap: 16px;
            margin-bottom: 28px;
        }

        .comment-content-wrapper {
            flex: 1;
        }

        .comment-author-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .comment-author-name {
            color: #1a1a1a;
            font-weight: 700;
            font-size: 1rem;
        }

        .comment-date {
            color: #888;
            font-size: 0.85rem;
        }

        .comment-text {
            color: #555;
            font-size: 0.98rem;
            line-height: 1.6;
            margin-bottom: 12px;
        }

        .comment-actions {
            display: flex;
            align-items: center;
            gap: 24px;
        }

        .action-btn {
            background: none;
            border: none;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #888;
            font-size: 0.95rem;
            cursor: pointer;
            padding: 0;
        }

        .action-btn i {
            font-size: 1.1rem;
        }

        .action-btn.reply {
            color: #1a1a1a;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .reply-toggle-modern {
            color: #c9a227;
            font-size: 0.95rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 10px;
        }

        #replying-to {
            background: transparent;
            padding: 0;
            border: none;
            margin-bottom: 12px;
            display: none;
            color: #666;
            font-size: 0.9rem;
        }

        #cancel-reply {
            background: #ddd;
            color: #333;
            border: none;
            border-radius: 10px;
            width: 20px;
            height: 20px;
            line-height: 18px;
            margin-left: 10px;
            cursor: pointer;
        }

        @media(max-width: 768px) {
            .modern-comment-form-card {
                flex-direction: column;
                padding: 16px;
            }

            .user-avatar-placeholder {
                display: none;
            }

            .form-row-inputs {
                flex-direction: column;
                gap: 10px;
                width: 100%;
            }

            .form-input-modern {
                flex: none;
                min-width: 100%;
                width: 100%;
                box-sizing: border-box;
            }

            #default-form-location .modern-comment-form-card {
                padding: 12px;
            }

            #default-form-location .form-inputs-wrapper {
                width: 100%;
                min-width: 100%;
            }

            .blog-grid[style*="grid-template-columns"] {
                grid-template-columns: 1fr !important;
            }

            /* Comment header responsive */
            .modern-comments-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }

            .modern-comments-sort {
                width: 100%;
                justify-content: flex-start;
            }

            /* Comment items responsive */
            .modern-comment-item {
                gap: 12px;
            }

            .modern-comment-item[style*="margin-left"] {
                margin-left: 16px !important;
            }

            .comment-actions {
                flex-wrap: wrap;
                gap: 12px 24px;
            }

            /* Comment author row */
            .comment-author-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 4px;
            }

            /* Send button on small screens */
            .send-btn-modern {
                width: 36px;
                height: 36px;
                bottom: 8px;
                right: 8px;
            }

            .form-textarea-modern {
                padding: 12px 50px 12px 12px;
                min-height: 80px;
            }
        }

        @media(max-width: 480px) {
            .modern-sort-btn {
                padding: 6px 12px;
                font-size: 0.8rem;
            }

            .modern-comments-title {
                font-size: 1.25rem;
            }

            .comment-text {
                font-size: 0.9rem;
            }

            .action-btn {
                font-size: 0.85rem;
            }
        }

        @media (min-width: 769px) and (max-width: 1024px) {
            .blog-grid[style*="grid-template-columns"] {
                grid-template-columns: repeat(2, 1fr) !important;
            }
        }

        @media (max-width: 1024px) {
            .blog-grid[style*="grid-template-columns"] {
                grid-template-columns: repeat(2, 1fr) !important;
            }
        }

        @media (max-width: 640px) {
            .blog-grid[style*="grid-template-columns"] {
                grid-template-columns: 1fr !important;
            }
        }


        .blog-style-one .blog-item-box {
            overflow: hidden;
            position: relative;
        }

        .blog-style-one .thumb {
            margin: 0 !important;
            padding: 0 !important;
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
            max-width: 100%;
        }

        .blog-content-white {
            font-size: inherit !important;
        }

        .blog-content-white p {
            line-height: 1.7;
            margin-bottom: 1.5em;
        }

        .blog-content-white blockquote {
            position: relative;
            margin: 45px 0;
            padding: 0 0 0 60px;
            background: transparent !important;
            border: none !important;
            border-left: none !important;
            border-radius: 0;
            box-shadow: none;
            color: #fff;
            overflow: visible;
            min-height: 40px;
        }

        .blog-content-white .quote-icon {
            display: none !important;
        }


        .blog-content-white blockquote::after {
            content: none !important;
        }

        .blog-content-white blockquote::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 35px;
            height: 30px;
            background: url('assets/img/quote-icon.png') no-repeat left top;
            background-size: contain;
        }

        .blog-content-white blockquote p {
            font-family: inherit;
            /* font-size removed */
            font-weight: 400;
            line-height: 1.7;
            margin-bottom: 0;
            color: #000 !important;
            position: relative;
            z-index: 2;
            text-align: inherit;
        }

        .blog-content-white blockquote p::before,
        .blog-content-white blockquote p::after {
            content: none !important;
        }

        .blog-content-white blockquote footer {
            display: none;
        }

        .blog-content-white ul,
        .blog-content-white ol {
            padding-left: 24px !important;
            margin-bottom: 30px !important;
            color: inherit;
        }

        .blog-content-white ul {
            list-style-type: disc;
        }

        .blog-content-white ol {
            list-style-type: decimal;
        }

        .blog-content-white li {
            margin-bottom: 12px !important;
            line-height: inherit;
            color: inherit;
            display: list-item !important;
            list-style: inherit !important;
        }

        .blog-content-white li span {
            color: #ffffff;
        }

        /* Ensure list markers inherit color */
        .blog-content-white li::marker {
            color: inherit;
        }

        /* Font size mappings for <font size="x"> from editor */
        .blog-content-white font[size="1"] {
            font-size: 12px !important;
        }

        .blog-content-white font[size="2"] {
            font-size: 14px !important;
        }

        .blog-content-white font[size="3"] {
            font-size: 16px !important;
        }

        .blog-content-white font[size="4"] {
            font-size: 18px !important;
        }

        .blog-content-white font[size="5"] {
            font-size: 24px !important;
        }

        .blog-content-white font[size="6"] {
            font-size: 32px !important;
        }

        .blog-content-white font[size="7"] {
            font-size: 48px !important;
        }

        .blog-content-white blockquote+p {
            margin-top: 30px;
        }

        .blog-content-white ul li strong,
        .blog-content-white ol li strong,
        .blog-content-white ul li b,
        .blog-content-white ol li b {
            font-weight: 700 !important;
            color: inherit;
        }

        .blog-content-white ul li em,
        .blog-content-white ol li em,
        .blog-content-white ul li i,
        .blog-content-white ol li i {
            font-style: italic !important;
        }

        .blog-content-white ul li u,
        .blog-content-white ol li u {
            text-decoration: underline !important;
        }

        .blog-content-white ul li s,
        .blog-content-white ol li s,
        .blog-content-white ul li strike,
        .blog-content-white ol li strike,
        .blog-content-white ul li del,
        .blog-content-white ol li del {
            text-decoration: line-through !important;
        }

        .sidebar-item.recent-post {
            background: #f8f9fa;
            box-shadow: none;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            margin-bottom: 24px;
        }

        .sidebar-item.recent-post ul li {
            display: flex;
            flex-direction: column;
            margin-bottom: 30px;
            padding-bottom: 0;
            border-bottom: none;
        }

        .sidebar-item.recent-post .thumb {
            width: 100% !important;
            max-width: 100% !important;
            margin: 0 0 10px 0 !important;
            padding: 0 !important;
            background: transparent !important;
            box-shadow: none !important;
            border: none !important;
            border-radius: 10px !important;
            overflow: hidden;
            display: block;
        }

        .sidebar-item.recent-post .thumb a {
            display: block;
            width: 100%;
        }

        .sidebar-item.recent-post .thumb img {
            width: 100% !important;
            height: 80px !important;
            object-fit: cover;
            border-radius: 10px !important;
            transition: transform 0.3s ease;
            display: block;
        }

        .sidebar-item.recent-post .thumb:hover img {
            transform: scale(1.03);
        }

        .sidebar-item.recent-post .info {
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
        }

        .sidebar-item.recent-post .meta-title {
            margin-bottom: 8px;
        }

        .sidebar-item.recent-post .post-date {
            color: #888;
            font-size: 0.95rem;
            font-weight: 400;
        }

        .sidebar-item.recent-post .info a {
            color: #000;
            font-size: 1.1rem;
            font-weight: 600;
            line-height: 1.4;
            text-decoration: none;
            display: block;
        }

        /* Sidebar Categories & Tags - Light Theme */
        .sidebar-item.category,
        .sidebar-item.tags {
            background: #f8f9fa !important;
            border: 1px solid #e0e0e0 !important;
            border-radius: 12px !important;
            padding: 24px !important;
            margin-bottom: 20px !important
        }

        .sidebar-item.category .title,
        .sidebar-item.tags .title {
            color: #1a1a1a !important;
            font-size: 1.3rem !important;
            font-weight: 700 !important;
            margin-bottom: 20px !important;
            display: inline-block !important
        }

        .sidebar-item.category .title::after,
        .sidebar-item.tags .title::after {
            content: '' !important;
            display: block !important;
            width: 50px !important;
            height: 3px !important;
            background: var(--gold) !important;
            margin-top: 8px !important;
            border-radius: 2px !important
        }

        .sidebar-item.category .sidebar-info ul,
        .sidebar-item.tags .sidebar-info ul {
            list-style: none !important;
            padding: 0 !important;
            margin: 0 !important;
            display: flex !important;
            flex-direction: column !important;
            gap: 4px !important
        }

        .sidebar-item.category .sidebar-info ul li,
        .sidebar-item.tags .sidebar-info ul li {
            margin: 0 !important;
            padding: 0 !important;
            border: none !important
        }

        .sidebar-item.category .sidebar-info ul li a {
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            padding: 12px 16px !important;
            color: #333 !important;
            text-decoration: none !important;
            font-size: 0.95rem !important;
            font-weight: 500 !important;
            border-radius: 8px !important;
            border: 1px solid #e0e0e0 !important;
        }

        .sidebar-item.category .sidebar-info ul li a span {
            background: #1a1a1a !important;
            color: #fff !important;
            font-size: 0.8rem !important;
            font-weight: 600 !important;
            padding: 4px 12px !important;
            border-radius: 20px !important;
            min-width: 28px !important;
            text-align: center !important
        }

        .sidebar-item.tags .sidebar-info ul {
            flex-direction: row !important;
            flex-wrap: wrap !important;
            gap: 10px !important
        }

        .sidebar-item.tags .sidebar-info ul li a {
            display: inline-flex !important;
            align-items: center !important;
            padding: 10px 18px !important;
            background: #fff !important;
            color: #333 !important;
            text-decoration: none !important;
            font-size: 0.9rem !important;
            font-weight: 500 !important;
            border-radius: 25px !important;
            border: 1px solid #e0e0e0 !important;
        }

        .sidebar-item.tags .sidebar-info ul li a::before {
            content: '#' !important;
            font-weight: 600 !important;
            margin-right: 2px !important
        }

    </style>

    <link href="assets/css/blog.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>
    <nav class="navbar" id="navbar">
        <div class="container nav-inner">
            <div class="logo-wrap">
                <img src="<?= htmlspecialchars($logoUrl) ?>" alt="<?= htmlspecialchars($siteName) ?> Logo" class="logo-img">
                <div class="logo-sub">TALENT HUB</div>
            </div>
            <div class="nav-links">
                <a href="index.php#nearshoring"><?php echo $fc['nav_links'][0]; ?></a>
                <a href="index.php#active-sourcing"><?php echo $fc['nav_links'][1]; ?></a>
                <a href="index.php#blog"><?php echo $fc['nav_links'][2]; ?></a>
                <a href="index.php#about"><?php echo $fc['nav_links'][3]; ?></a>
                <a href="index.php#kontakt"><?php echo $fc['nav_links'][4]; ?></a>
            </div>
            <div class="nav-cta">
                <div class="lang-dropdown-wrap" id="langDropdownWrap">
                    <button class="lang-globe-btn" id="langGlobeBtn" aria-haspopup="true" aria-expanded="false">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10" />
                            <path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" />
                        </svg>
                        <span id="langCurrentLabel"><?php echo strtoupper($lang); ?></span>
                        <svg class="lang-caret" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div class="lang-dropdown" id="langDropdown" role="menu">
                        <a href="blog.php?<?php echo http_build_query(array_merge($_GET, ['lang' => 'de'])); ?>" class="lang-option <?php echo $lang === 'de' ? 'active' : ''; ?>" data-lang="de" role="menuitem">
                            <span class="lang-flag">🇩🇪</span>
                            Deutsch
                            <?php if ($lang === 'de'): ?>
                            <svg class="lang-check" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:14px;height:14px;">
                                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                            </svg>
                            <?php endif; ?>
                        </a>
                        <a href="blog.php?<?php echo http_build_query(array_merge($_GET, ['lang' => 'en'])); ?>" class="lang-option <?php echo $lang === 'en' ? 'active' : ''; ?>" data-lang="en" role="menuitem">
                            <span class="lang-flag">🇬🇧</span>
                            English
                            <?php if ($lang === 'en'): ?>
                            <svg class="lang-check" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:14px;height:14px;">
                                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                            </svg>
                            <?php endif; ?>
                        </a>
                    </div>
                </div>
            </div>
            <div class="nav-mobile-group">
                <div class="lang-dropdown-wrap" id="langDropdownWrapMobile">
                    <button class="lang-globe-btn" id="langGlobeBtnMobile" aria-haspopup="true" aria-expanded="false">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10" />
                            <path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" />
                        </svg>
                        <span id="langCurrentLabelMobile"><?php echo strtoupper($lang); ?></span>
                        <svg class="lang-caret" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div class="lang-dropdown" id="langDropdownMobile" role="menu">
                        <a href="blog.php?<?php echo http_build_query(array_merge($_GET, ['lang' => 'de'])); ?>" class="lang-option <?php echo $lang === 'de' ? 'active' : ''; ?>" data-lang="de" role="menuitem">
                            <span class="lang-flag">🇩🇪</span>
                            Deutsch
                            <?php if ($lang === 'de'): ?>
                            <svg class="lang-check" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:14px;height:14px;">
                                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                            </svg>
                            <?php endif; ?>
                        </a>
                        <a href="blog.php?<?php echo http_build_query(array_merge($_GET, ['lang' => 'en'])); ?>" class="lang-option <?php echo $lang === 'en' ? 'active' : ''; ?>" data-lang="en" role="menuitem">
                            <span class="lang-flag">🇬🇧</span>
                            English
                            <?php if ($lang === 'en'): ?>
                            <svg class="lang-check" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:14px;height:14px;">
                                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                            </svg>
                            <?php endif; ?>
                        </a>
                    </div>
                </div>
                <button class="mob-btn" id="mobBtn">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:24px;height:24px;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>
            </div>
        </div>
        <div class="mob-menu" id="mobMenu">
            <a href="index.php#nearshoring"><?php echo $fc['nav_links'][0]; ?></a>
            <a href="index.php#active-sourcing"><?php echo $fc['nav_links'][1]; ?></a>
            <a href="index.php#blog"><?php echo $fc['nav_links'][2]; ?></a>
            <a href="index.php#about"><?php echo $fc['nav_links'][3]; ?></a>
            <a href="index.php#kontakt"><?php echo $fc['nav_links'][4]; ?></a>
            <div style="display:flex;align-items:center;gap:.75rem;margin-top:.5rem;">
                <a href="?lang=de" class="lang-btn <?php echo $lang === 'de' ? 'active' : ''; ?>" data-lang="de" style="color:<?php echo $lang === 'de' ? 'var(--gold)' : '#888'; ?>;font-weight:<?php echo $lang === 'de' ? '700' : '400'; ?>">DE</a>
                <span style="color:#555;">|</span>
                <a href="?lang=en" class="lang-btn <?php echo $lang === 'en' ? 'active' : ''; ?>" data-lang="en" style="color:<?php echo $lang === 'en' ? 'var(--gold)' : '#888'; ?>;font-weight:<?php echo $lang === 'en' ? '700' : '400'; ?>">EN</a>
            </div>
        </div>
    </nav>
    
    <div class="blog-area single full-blog right-sidebar full-blog default-padding">
        <div class="container">
            <div class="page-header" <?php if (isset($_GET['category'])): ?>style="--gold-line-display: none;"<?php endif; ?>>
                <a href="index.php" class="back-to-blog-link" style="display:inline-flex;align-items:center;gap:6px;color:#666;font-size:0.9rem;text-decoration:none;margin-bottom:12px;transition:color 0.2s;">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width:16px;height:16px;"><path fill-rule="evenodd" d="M7.72 12.53a.75.75 0 010-1.06l7.5-7.5a.75.75 0 111.06 1.06L9.31 12l6.97 6.97a.75.75 0 11-1.06 1.06l-7.5-7.5z" clip-rule="evenodd"/></svg>
                    <?php echo $lang === 'de' ? 'Startseite' : 'Home'; ?>
                </a>
                <?php if (!isset($_GET['category'])): ?>
                <h1><?php echo $isSingle ? htmlspecialchars($post['title_' . $lang] ?? $post['title'] ?? '') : $c['page_title']; ?></h1>
                <?php endif; ?>
            </div>
            <div class="blog-items">
                <div class="row">
                    <div class="blog-content <?php echo $isListing ? 'col-lg-12 col-md-12' : 'col-xl-8 col-lg-7 col-md-12 pr-35 pr-md-15 pl-md-15 pr-xs-15 pl-xs-15'; ?>"
                        style="background:none; box-shadow:none;">
                        <?php if ($isSingle): ?>
                            <div class="blog-style-one item" style="background:none; box-shadow:none;">
                                <div class="blog-item-box" style="background:none; box-shadow:none;">
                                    <?php if (!empty($post['image'])): ?>
                                        <div class="thumb">
                                            <img src="backend/image.php?id=<?php echo $post['image']; ?>&width=800"
                                                alt="<?php echo htmlspecialchars($post['title_' . $lang] ?? $post['title'] ?? ''); ?>"
                                                loading="eager"
                                                style="width:100%;height:450px;object-fit:cover;border-radius:12px;">
                                        </div>
                                    <?php else: ?>
                                        <div class="thumb">
                                            <img src="assets/img/logo.png" alt="No thumbnail available"
                                                style="width:100%;max-width:320px;object-fit:cover;opacity:0.5;">
                                        </div>
                                    <?php endif; ?>

                                    <div class="info">
                                        <div class="meta">
                                            <ul style="display:flex;align-items:center;gap:12px;margin-top:15px;">
                                                <li style="color:#555;display:inline-flex;align-items:center;gap:8px;">
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width:16px;height:16px;color:#c9a227;"><path fill-rule="evenodd" d="M7.5 6a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0zM3.751 20.105a8.25 8.25 0 0116.498 0 .75.75 0 01-.437.7A18.683 18.683 0 0112 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 01-.437-.7z" clip-rule="evenodd"/></svg>
                                                    <a href="#" style="color:#555;"><?php echo htmlspecialchars($post['author'] ?? $t['admin']); ?></a>
                                                </li>
                                                <li style="color:#555;display:inline-flex;align-items:center;gap:8px;">
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width:16px;height:16px;color:#c9a227;"><path d="M12.75 12.75a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM7.5 15.75a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5ZM8.25 17.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM9.75 15.75a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5ZM10.5 17.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM12 15.75a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5ZM12.75 17.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM14.25 15.75a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5ZM15 17.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM16.5 15.75a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5ZM15 12.75a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM16.5 13.5a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z" /><path fill-rule="evenodd" d="M6.75 2.25A.75.75 0 0 1 7.5 3v1.5h9V3A.75.75 0 0 1 18 3v1.5h.75a3 3 0 0 1 3 3v11.25a3 3 0 0 1-3 3H5.25a3 3 0 0 1-3-3V7.5a3 3 0 0 1 3-3H6V3a.75.75 0 0 1 .75-.75Zm13.5 9a1.5 1.5 0 0 0-1.5-1.5H5.25a1.5 1.5 0 0 0-1.5 1.5v7.5a1.5 1.5 0 0 0 1.5 1.5h13.5a1.5 1.5 0 0 0 1.5-1.5v-7.5Z" clip-rule="evenodd"/></svg>
                                                    <?php echo formatDate($post['created_at'] ?? null, $lang, translations: $translations); ?>
                                                </li>
                                                <?php
                                                $catName = null;
                                                $postCatsRaw = $post['categories'] ?? ($post['category'] ?? []);
                                                if ($postCatsRaw instanceof \MongoDB\Model\BSONArray) {
                                                    $postCatsArr = $postCatsRaw->getArrayCopy();
                                                } elseif (is_array($postCatsRaw)) {
                                                    $postCatsArr = $postCatsRaw;
                                                } elseif (is_string($postCatsRaw)) {
                                                    $postCatsArr = [$postCatsRaw];
                                                } else {
                                                    $postCatsArr = [];
                                                }
                                                $catName = $postCatsArr[0] ?? null;
                                                $displayCat = $catName ?? 'Uncategorized';
                                                ?>
                                                <li style="color:#555;display:inline-flex;align-items:center;gap:8px;">
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width:16px;height:16px;color:#c9a227;"><path fill-rule="evenodd" d="M3 6a3 3 0 0 1 3-3h2.25a3 3 0 0 1 3 3v2.25a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V6Zm9.75 0a3 3 0 0 1 3-3H18a3 3 0 0 1 3 3v2.25a3 3 0 0 1-3 3h-2.25a3 3 0 0 1-3-3V6ZM3 15.75a3 3 0 0 1 3-3h2.25a3 3 0 0 1 3 3V18a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3v-2.25Zm9.75 0a3 3 0 0 1 3-3H18a3 3 0 0 1 3 3V18a3 3 0 0 1-3 3h-2.25a3 3 0 0 1-3-3v-2.25Z" clip-rule="evenodd"/></svg>
                                                    <a href="blog.php?category=<?php echo urlencode($displayCat); ?>&lang=<?php echo $lang; ?>"
                                                        style="color:#555;text-decoration:none;">
                                                        <?php echo htmlspecialchars($displayCat); ?>
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>

                                        <?php
                                        $langKey = 'content_' . $lang;
                                        $content = null;

                                        if (isset($post[$langKey]) && !empty($post[$langKey])) {
                                            $content = $post[$langKey];
                                        } elseif (isset($post['content_de']) && !empty($post['content_de'])) {
                                            $content = $post['content_de'];
                                        } elseif (isset($post['content']) && !empty($post['content'])) {
                                            $content = $post['content'];
                                        }

                                        if ($content) {
                                            echo '<div class="blog-content-white">';
                                            if (strpos($content, '<') !== false) {
                                                echo $content;
                                            } else {
                                                echo nl2br(htmlspecialchars($content));
                                            }
                                            echo '</div>';
                                            echo '<style>
                                                    .blog-content-white { color: #333 !important; }
                                                    .blog-content-white * { color: #333 !important; }
                                                    .blog-content-white p, .blog-content-white span, .blog-content-white div { color: #333 !important; }
                                                    .blog-content-white ul, .blog-content-white ol { 
                                                        margin: 1em 0; 
                                                        padding-left: 2em !important; 
                                                        list-style-position: outside !important;
                                                    }
                                                    .blog-content-white li { 
                                                        margin-bottom: 0.5em; 
                                                        display: list-item !important;
                                                        color: #333 !important;
                                                    }
                                                    .blog-content-white strong, .blog-content-white b { color: #1a1a1a !important; }
                                                    /* Default types if not specified */
                                                    .blog-content-white ul { list-style-type: disc; }
                                                    .blog-content-white ol { list-style-type: decimal; }
                                                    
                                                    /* Ensure list markers inherit color */
                                                    .blog-content-white li::marker {
                                                        color: #333 !important;
                                                    }

                                                    /* Font size mappings for <font size="x"> from editor */
                                                    .blog-content-white font[size="1"] { font-size: 12px !important; color: #333 !important; }
                                                    .blog-content-white font[size="2"] { font-size: 14px !important; color: #333 !important; }
                                                    .blog-content-white font[size="3"] { font-size: 16px !important; color: #333 !important; }
                                                    .blog-content-white font[size="4"] { font-size: 18px !important; color: #333 !important; }
                                                    .blog-content-white font[size="5"] { font-size: 24px !important; color: #333 !important; }
                                                    .blog-content-white font[size="6"] { font-size: 32px !important; color: #333 !important; }
                                                    .blog-content-white font[size="7"] { font-size: 48px !important; color: #333 !important; }

                                                    /* Hide effectively empty list items */
                                                    .blog-content-white li:empty { display: none !important; }
                                                    .blog-content-white li > p:empty { display: none !important; }
                                                </style>';
                                        } else {
                                            echo '<p style="color:#555;">No content available.</p>';
                                        }
                                        ?>

                                    </div>
                                </div>
                            </div>

                            <div class="post-tags share"
                                style="background:none; box-shadow:none; display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: nowrap; gap: 20px; padding: 25px 0; border: none; margin-top: 40px;">
                                <div class="tags"
                                    style="display: flex; align-items: center; flex-wrap: wrap; gap: 10px; flex-grow: 1;">
                                    <h4 style="color:#1a1a1a; margin:0; font-size: 1.1rem; font-weight: 700; margin-right: 8px; white-space: nowrap;">
                                        <?php echo $t['tags']; ?>:
                                    </h4>
                                    <?php
                                    $tagsToCheck = $post['tags'] ?? [];
                                    if ($tagsToCheck instanceof \MongoDB\Model\BSONArray) {
                                        $tagsToCheck = $tagsToCheck->getArrayCopy();
                                    } elseif (!is_array($tagsToCheck)) {
                                        $tagsToCheck = [$tagsToCheck];
                                    }
                                    $tagsToCheck = array_filter($tagsToCheck, function ($t) {
                                        return !empty(trim($t));
                                    });
                                    $tagsToCheck = array_slice($tagsToCheck, 0, 7);
                                    ?>
                                    <?php if (!empty($tagsToCheck)): ?>
                                        <?php foreach ($tagsToCheck as $tag): ?>
                                            <a href="blog.php?tag=<?php echo urlencode($tag); ?>&lang=<?php echo $lang; ?>"
                                                class="modern-tag-badge">#<?php echo htmlspecialchars($tag); ?></a>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>

                                <div class="social" style="display: flex; align-items: center; gap: 15px; flex-shrink: 0;">
                                    <h4 style="color:#1a1a1a; margin:0; font-size: 1.1rem; font-weight: 700;">
                                        <?php echo $t['share']; ?>:
                                    </h4>
                                    <?php
                                    $currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                                    $shareTitle = htmlspecialchars($post['title_' . $lang] ?? $post['title'] ?? 'Blog Post');
                                    $encodedUrl = urlencode($currentUrl);
                                    $encodedTitle = urlencode($shareTitle);
                                    ?>
                                    <ul style="display: flex; align-items: center; gap: 10px; margin: 0;">
                                        <li><a href="https://www.facebook.com/share.php?u=<?php echo $encodedUrl; ?>"
                                                target="_blank" rel="noopener noreferrer" class="share-icon-btn"
                                                onclick="window.open(this.href, 'facebook-share', 'width=626,height=436'); return false;"
                                                title="Share on Facebook">
                                                <i class="fab fa-facebook-f" style="font-size: 16px;"></i>
                                            </a></li>
                                        <li><a href="https://twitter.com/intent/tweet?url=<?php echo $encodedUrl; ?>&text=<?php echo $encodedTitle; ?>"
                                                target="_blank" rel="noopener noreferrer" class="share-icon-btn"
                                                onclick="window.open(this.href, 'twitter-share', 'width=550,height=420'); return false;"
                                                title="Share on Twitter">
                                                <i class="fab fa-x-twitter" style="font-size: 16px;"></i>
                                            </a></li>
                                        <li><a href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo $encodedUrl; ?>"
                                                target="_blank" rel="noopener noreferrer" class="share-icon-btn"
                                                onclick="window.open(this.href, 'linkedin-share', 'width=550,height=420'); return false;"
                                                title="Share on LinkedIn">
                                                <i class="fab fa-linkedin-in" style="font-size: 16px;"></i>
                                            </a></li>
                                        <li><a href="https://wa.me/?text=<?php echo $encodedTitle . '%20' . $encodedUrl; ?>"
                                                target="_blank" rel="noopener noreferrer" class="share-icon-btn"
                                                title="Share on WhatsApp">
                                                <i class="fab fa-whatsapp" style="font-size: 16px;"></i>
                                            </a></li>
                                        <li><a href="mailto:?subject=<?php echo $encodedTitle; ?>&body=<?php echo $encodedTitle . '%20' . $encodedUrl; ?>"
                                                class="share-icon-btn" title="Share via Email">
                                                <i class="fas fa-envelope" style="font-size: 16px;"></i>
                                            </a></li>
                                    </ul>
                                </div>
                            </div>

                            <style>
                                .modern-tag-badge {
                                    display: inline-block;
                                    padding: 5px 14px;
                                    background: rgba(0, 0, 0, 0.05);
                                    border: none;
                                    border-radius: 20px;
                                    color: #555 !important;
                                    font-size: 0.9rem;
                                    font-weight: 500;
                                    text-decoration: none !important;
                                    line-height: 1.2;
                                }

                                .modern-tag-badge:hover {
                                    background: #1a1a1a;
                                    color: #ffffff !important;
                                }

                                .share-icon-btn {
                                    display: flex;
                                    align-items: center;
                                    justify-content: center;
                                    width: 42px;
                                    height: 42px;
                                    background: #1a1a1a;
                                    border-radius: 50%;
                                    color: #fff !important;
                                    transition: all 0.2s ease;
                                }

                                .share-icon-btn:hover {
                                    background: #333;
                                    transform: translateY(-2px);
                                }

                                @media (max-width: 991px) {
                                    .post-tags.share {
                                        flex-wrap: wrap !important;
                                        gap: 20px;
                                    }

                                    .social {
                                        width: 100%;
                                        justify-content: flex-start;
                                        margin-left: 0;
                                    }
                                }
                            </style>

                            <?php if ($siteCommentsEnabled ?? true): ?>
                                <div class="blog-comments" id="comments">
                                    <div class="comments-area">
                                        <div class="modern-comments-header">
                                            <h3 class="modern-comments-title"><span
                                                    class="comments-count"><?php echo $totalComments ?? 0; ?></span> <span
                                                    class="comments-label"><?php echo $t['comment_count_plural']; ?></span></h3>
                                            <div class="modern-comments-sort">
                                                <button class="modern-sort-btn"><svg xmlns="http://www.w3.org/2000/svg"
                                                        fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                                                        style="width:16px;height:16px;">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941" />
                                                    </svg> Popular</button>
                                                <button class="modern-sort-btn active"><svg xmlns="http://www.w3.org/2000/svg"
                                                        fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                                                        style="width:16px;height:16px;">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                                                    </svg> Newest</button>
                                            </div>
                                        </div>
                                        <div id="default-form-location">
                                            <div class="modern-comment-form-card">
                                                <img src="assets/img/avatar-img.png" class="user-avatar-placeholder" alt="You">
                                                <div class="form-inputs-wrapper">
                                                    <form method="POST"
                                                        action="blog.php?id=<?php echo htmlspecialchars($postId); ?>&lang=<?php echo $lang; ?>#comments"
                                                        id="comment-form" onsubmit="return injectParentId();">
                                                        <input type="hidden" name="parent_id" id="parent_id" value="">
                                                        <div id="replying-to">
                                                            <?php echo $t['reply']; ?>: <span id="replying-to-name"></span>
                                                            <button type="button" id="cancel-reply">×</button>
                                                        </div>
                                                        <div class="form-row-inputs">
                                                            <input name="name" class="form-input-modern"
                                                                placeholder="<?php echo $t['name']; ?> *" required>
                                                            <input name="email" class="form-input-modern"
                                                                placeholder="<?php echo $t['email']; ?> *" required>
                                                        </div>
                                                        <div class="form-textarea-wrapper" style="margin-top:12px;">
                                                            <textarea name="message" class="form-textarea-modern"
                                                                placeholder="<?php echo $t['comment_placeholder']; ?>..."
                                                                required></textarea>
                                                            <button type="submit" name="comment_submit" value="1"
                                                                class="send-btn-modern icon-btn-pink" aria-label="Send comment">
                                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width:20px;height:20px;">
                                                                    <path d="M3.478 2.404a.75.75 0 0 0-.926.941l2.432 7.905H13.5a.75.75 0 0 1 0 1.5H4.984l-2.432 7.905a.75.75 0 0 0 .926.94 60.519 60.519 0 0 0 18.445-8.986.75.75 0 0 0 0-1.218A60.517 60.517 0 0 0 3.478 2.404Z" />
                                                                </svg>
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="comments-list">
                                            <?php
                                            $avatarCount = 3;
                                            $commentIndex = 0;
                                            render_comment_branch($comments, null, $commentIndex, $avatarCount, 0, $lang, $t);
                                            ?>
                                            <?php if (empty($comments)): ?>
                                                <p style="color:#888; text-align:center; margin-top:40px;">
                                                    <?php echo $t['no_comments']; ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                        <?php elseif ($isListing): ?>
                            <?php if (empty($listingPosts)): ?>
                                <div
                                    style="background:#f8f9fa; padding:40px; border-radius:16px; text-align:center; border:1px solid #e0e0e0;">
                                    <div
                                        style="margin-bottom:20px; display: inline-flex; align-items: center; justify-content: center; width: 80px; height: 80px; background: rgba(201, 162, 39, 0.1); border-radius: 50%;">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#c9a227"
                                            style="width: 40px; height: 40px;">
                                            <path fill-rule="evenodd"
                                                d="M4.125 3C3.089 3 2.25 3.84 2.25 4.875V18a3 3 0 0 0 3 3h15a3 3 0 0 1-3-3V4.875C17.25 3.839 16.41 3 15.375 3H4.125ZM12 9.75a.75.75 0 0 0 0 1.5h1.5a.75.75 0 0 0 0-1.5H12Zm-.75-2.25a.75.75 0 0 1 .75-.75h1.5a.75.75 0 0 1 0 1.5H12a.75.75 0 0 1-.75-.75ZM6 12.75a.75.75 0 0 0 0 1.5h7.5a.75.75 0 0 0 0-1.5H6Zm-.75 3.75a.75.75 0 0 1 .75-.75h7.5a.75.75 0 0 1 0 1.5H6a.75.75 0 0 1-.75-.75ZM6 6.75a.75.75 0 0 0-.75.75v3c0 .414.336.75.75.75h3a.75.75 0 0 0 .75-.75v-3A.75.75 0 0 0 9 6.75H6Z"
                                                clip-rule="evenodd" />
                                            <path
                                                d="M18.75 6.75h1.875c.621 0 1.125.504 1.125 1.125V18a1.5 1.5 0 0 1-3 0V6.75Z" />
                                        </svg>
                                    </div>
                                    <h3 style="color:#1a1a1a; margin-bottom:12px;"><?php echo $t['no_posts_found']; ?></h3>
                                    <p style="color:#555; margin-bottom:24px;"><?php echo $t['no_posts_desc']; ?>
                                    </p>
                                    <a href="blog.php?lang=<?php echo $lang; ?>"
                                        style="color:#c9a227; text-decoration:underline;"><?php echo $t['back_to_overview']; ?></a>
                                </div>
                            <?php else: ?>
                                <div class="blog-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin: 0 auto;"> 
                                    <?php
                                    foreach ($listingPosts as $lPost):
                                        $lPostId = (string) ($lPost['_id'] ?? '');
                                        $lContent = $lPost['content_' . $lang] ?? $lPost['content_de'] ?? $lPost['content'] ?? '';
                                        $lExcerpt = mb_substr(strip_tags($lContent), 0, 120) . '...';
                                        $wordCount = str_word_count(strip_tags($lContent));
                                        $readTime = ceil($wordCount / 200);
                                        $lPostCats = $lPost['categories'] ?? ($lPost['category'] ? [$lPost['category']] : []);
                                        if ($lPostCats instanceof \MongoDB\Model\BSONArray) {
                                            $lPostCats = $lPostCats->getArrayCopy();
                                        }
                                        $lPostCat = is_array($lPostCats) && !empty($lPostCats) ? $lPostCats[0] : '';
                                        ?>
                                        <div class="blog-card" style="background: #fff; border: 1px solid #e5e7eb; border-radius: 1rem; overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 1px 3px rgba(0,0,0,0.05); transition: all 0.3s ease;">
                                            <div class="bimg" style="height: 200px; overflow: hidden; position: relative; background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);">
                                                <a href="blog.php?id=<?php echo $lPostId; ?>&lang=<?php echo $lang; ?>" style="display: block;">
                                                    <?php if (isset($lPost['image'])): ?>
                                                        <img src="backend/image.php?id=<?php echo $lPost['image']; ?>&width=400" alt="Thumb" loading="lazy" style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease;">
                                                    <?php else: ?>
                                                        <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center;">
                                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width:48px;height:48px;color:#d1d5db;">
                                                                <path fill-rule="evenodd" d="M1.5 6a2.25 2.25 0 012.25-2.25h16.5A2.25 2.25 0 0122.5 6v12a2.25 2.25 0 01-2.25 2.25H3.75A2.25 2.25 0 011.5 18V6zM3 16.06V18c0 .414.336.75.75.75h16.5A.75.75 0 0021 18v-1.94l-5.69-5.689a.75.75 0 00-1.06 0l-1.94 1.939 4.57 4.569a.75.75 0 11-1.06 1.06l-5.845-5.845a.75.75 0 00-1.06 0L3 16.06z" clip-rule="evenodd"/>
                                                                <path d="M15.75 9a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z"/>
                                                            </svg>
                                                        </div>
                                                    <?php endif; ?>
                                                </a>
                                                <?php if ($lPostCat): ?>
                                                <span class="bcat" style="position: absolute; top: 1rem; left: 1rem; background: #0ea5e9; color: #fff; font-size: 0.7rem; font-weight: 600; padding: 0.35rem 0.9rem; border-radius: 100px; text-transform: uppercase; letter-spacing: 0.05em; box-shadow: 0 2px 8px rgba(14, 165, 233, 0.3);"><?php echo htmlspecialchars($lPostCat); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="bbody" style="padding: 1.25rem; display: flex; flex-direction: column; flex: 1;">
                                                <div class="bmeta" style="display: flex; gap: 1rem; font-size: 0.8rem; color: #9ca3af; margin-bottom: 0.75rem; align-items: center;">
                                                    <span style="display: inline-flex; align-items: center; gap: 0.35rem;">
                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width: 1rem; height: 1rem; color: #9ca3af;">
                                                            <path d="M12.75 12.75a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM7.5 15.75a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5ZM8.25 17.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM9.75 15.75a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5ZM10.5 17.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM12 15.75a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5ZM12.75 17.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM14.25 15.75a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5ZM15 17.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM16.5 15.75a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5ZM15 12.75a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM16.5 13.5a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z" />
                                                            <path fill-rule="evenodd" d="M6.75 2.25A.75.75 0 0 1 7.5 3v1.5h9V3A.75.75 0 0 1 18 3v1.5h.75a3 3 0 0 1 3 3v11.25a3 3 0 0 1-3 3H5.25a3 3 0 0 1-3-3V7.5a3 3 0 0 1 3-3H6V3a.75.75 0 0 1 .75-.75Zm13.5 9a1.5 1.5 0 0 0-1.5-1.5H5.25a1.5 1.5 0 0 0-1.5 1.5v7.5a1.5 1.5 0 0 0 1.5 1.5h13.5a1.5 1.5 0 0 0 1.5-1.5v-7.5Z" clip-rule="evenodd" />
                                                        </svg>
                                                        <?php echo formatDate($lPost['created_at'] ?? null, $lang, $translations); ?>
                                                    </span>
                                                    <span style="display: inline-flex; align-items: center; gap: 0.35rem;">
                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width: 1rem; height: 1rem; color: #9ca3af;">
                                                            <path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25zM12.75 6a.75.75 0 00-1.5 0v6c0 .414.336.75.75.75h4.5a.75.75 0 000-1.5h-3.75V6z" clip-rule="evenodd"/>
                                                        </svg>
                                                        <?php echo $readTime; ?> min
                                                    </span>
                                                </div>
                                                <h3 style="font-size: 1.05rem; font-weight: 700; margin-bottom: 0.75rem; line-height: 1.4; color: #111827;">
                                                    <a href="blog.php?id=<?php echo $lPostId; ?>&lang=<?php echo $lang; ?>" style="color: #111827; text-decoration: none; transition: color 0.2s ease;">
                                                        <?php echo htmlspecialchars($lPost['title_' . $lang] ?? $lPost['title'] ?? 'Untitled'); ?>
                                                    </a>
                                                </h3>
                                                <p style="font-size: 0.875rem; color: #6b7280; line-height: 1.6; margin-bottom: 1rem; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; flex: 1;">
                                                    <?php echo htmlspecialchars($lExcerpt); ?>
                                                </p>
                                                <a href="blog.php?id=<?php echo $lPostId; ?>&lang=<?php echo $lang; ?>" class="blink" style="font-size: 0.875rem; font-weight: 600; color: #0ea5e9; display: inline-flex; align-items: center; gap: 0.4rem; text-decoration: none; transition: all 0.2s ease;">
                                                    <?php echo $t['read_more']; ?>
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width: 1rem; height: 1rem;">
                                                        <path fill-rule="evenodd" d="M16.28 11.47a.75.75 0 010 1.06l-7.5 7.5a.75.75 0 01-1.06-1.06L14.69 12 7.72 5.03a.75.75 0 011.06-1.06l7.5 7.5z" clip-rule="evenodd"/>
                                                    </svg>
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div> 
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <?php if (!$isListing): ?>
                        <div class="sidebar col-xl-4 col-lg-5 col-md-12 mt-xs-50"
                            style="background:none; box-shadow:none;">
                            <aside>
                                <?php if ($blogShowRecent): ?>
                                    <div class="sidebar-item recent-post">
                                        <h4 class="title" style="color:#000;"><?php echo $t['recent_posts']; ?></h4>
                                        <ul>
                                            <?php
                                            $recentPosts = get_related_posts($db, $postObjectId, $post, $postsPerPage);

                                            foreach ($recentPosts as $recentPost):
                                                if ($postObjectId && isset($recentPost['_id']) && (string) $recentPost['_id'] === (string) $postObjectId) {
                                                    continue;
                                                }
                                                if (isset($recentPost['status']) && $recentPost['status'] !== 'published') {
                                                    continue;
                                                }
                                                ?>
                                                <li>
                                                    <div class="thumb">
                                                        <a
                                                            href="blog.php?id=<?php echo urlencode((string) $recentPost['_id']); ?>&lang=<?php echo $lang; ?>">
                                                            <?php if (isset($recentPost['image'])): ?>
                                                                <img src="backend/image.php?id=<?php echo $recentPost['image']; ?>&width=100"
                                                                    alt="Thumb" loading="lazy">
                                                            <?php else: ?>
                                                                <span
                                                                    style="width:100%;height:200px;background:#333;display:block;border-radius:12px;"></span>
                                                            <?php endif; ?>
                                                        </a>
                                                    </div>
                                                    <div class="info">
                                                        <div class="meta-title">
                                                            <span
                                                                class="post-date"><?php echo formatDate($recentPost['created_at'] ?? null, $lang, $translations); ?></span>
                                                        </div>
                                                        <a
                                                            href="blog.php?id=<?php echo urlencode((string) $recentPost['_id']); ?>&lang=<?php echo $lang; ?>"><?php echo htmlspecialchars($recentPost['title_' . $lang] ?? $recentPost['title'] ?? 'MBlog'); ?></a>
                                                    </div>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>

                                <?php if ($blogShowCategories): ?>
                                    <div class="sidebar-item category">
                                        <h4 class="title"><?php echo $t['categories']; ?></h4>
                                        <div class="sidebar-info">
                                            <ul>
                                                <?php
                                                $merged = [];

                                                try {
                                                    $catsArray = $db->blog->aggregate([
                                                        ['$match' => ['status' => 'published', 'categories' => ['$exists' => true]]],
                                                        ['$unwind' => '$categories'],
                                                        ['$group' => ['_id' => '$categories', 'count' => ['$sum' => 1]]],
                                                        ['$sort' => ['count' => -1]]
                                                    ])->toArray();
                                                } catch (Exception $e) {
                                                    $catsArray = [];
                                                }
                                                foreach ($catsArray as $c) {
                                                    $name = $c['_id'] ?? 'Uncategorized';
                                                    $merged[$name] = ($merged[$name] ?? 0) + ($c['count'] ?? 0);
                                                }

                                                try {
                                                    $catsScalar = $db->blog->aggregate([
                                                        ['$match' => ['status' => 'published', 'category' => ['$exists' => true]]],
                                                        ['$group' => ['_id' => '$category', 'count' => ['$sum' => 1]]],
                                                        ['$sort' => ['count' => -1]]
                                                    ])->toArray();
                                                } catch (Exception $e) {
                                                    $catsScalar = [];
                                                }
                                                foreach ($catsScalar as $c) {
                                                    $name = $c['_id'] ?? 'Uncategorized';
                                                    $merged[$name] = ($merged[$name] ?? 0) + ($c['count'] ?? 0);
                                                }

                                                try {
                                                    $uncatFilter = [
                                                        'status' => 'published',
                                                        '$and' => [
                                                            [
                                                                '$or' => [
                                                                    ['categories' => ['$exists' => false]],
                                                                    ['categories' => null],
                                                                    ['categories' => ['$size' => 0]],
                                                                    ['categories' => ['$elemMatch' => ['$eq' => '']]]
                                                                ]
                                                            ],
                                                            [
                                                                '$or' => [
                                                                    ['category' => ['$exists' => false]],
                                                                    ['category' => null],
                                                                    ['category' => '']
                                                                ]
                                                            ]
                                                        ]
                                                    ];
                                                    $uncatCount = $db->blog->countDocuments($uncatFilter);
                                                } catch (Exception $e) {
                                                    $uncatCount = 0;
                                                }
                                                if ($uncatCount > 0) {
                                                    $merged['Uncategorized'] = ($merged['Uncategorized'] ?? 0) + $uncatCount;
                                                }

                                                arsort($merged);

                                                foreach ($merged as $catName => $catCount):
                                                    ?>
                                                    <li><a
                                                            href="blog.php?category=<?php echo urlencode($catName); ?>&lang=<?php echo $lang; ?>"><?php echo htmlspecialchars($catName); ?>
                                                            <span><?php echo $catCount; ?></span></a></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if ($blogShowTags): ?>
                                    <div class="sidebar-item tags">
                                        <h4 class="title"><?php echo $t['tags_title']; ?></h4>
                                        <div class="sidebar-info">
                                            <ul>
                                                <?php
                                                $tagAggregation = $db->blog->aggregate([
                                                    ['$match' => ['status' => 'published', 'tags' => ['$exists' => true]]],
                                                    ['$unwind' => '$tags'],
                                                    ['$group' => ['_id' => '$tags', 'count' => ['$sum' => 1]]],
                                                    ['$sort' => ['count' => -1]],
                                                    ['$limit' => 10]
                                                ])->toArray();

                                                foreach ($tagAggregation as $tagData):
                                                    $tag = $tagData['_id'];
                                                    $count = $tagData['count'];
                                                    ?>
                                                    <li><a
                                                            href="blog.php?tag=<?php echo urlencode($tag); ?>&lang=<?php echo $lang; ?>"><?php echo htmlspecialchars($tag); ?></a>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </div>
                                <?php endif; ?>

                            </aside>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/jquery-3.6.0.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script defer src="assets/js/jquery.appear.js"></script>
    <script defer src="assets/js/swiper-bundle.min.js"></script>
    <script defer src="assets/js/progress-bar.min.js"></script>
    <script defer src="assets/js/circle-progress.js"></script>
    <script defer src="assets/js/isotope.pkgd.min.js"></script>
    <script defer src="assets/js/imagesloaded.pkgd.min.js"></script>
    <script defer src="assets/js/magnific-popup.min.js"></script>
    <script defer src="assets/js/count-to.js"></script>
    <script defer src="assets/js/jquery.scrolla.min.js"></script>
    <script defer src="assets/js/ScrollOnReveal.js"></script>
    <script defer src="assets/js/YTPlayer.min.js"></script>
    <script defer src="assets/js/validnavs.js"></script>
    <script defer src="assets/js/gsap.js"></script>
    <script defer src="assets/js/ScrollTrigger.min.js"></script>
    <script defer src="assets/js/main.js"></script>

    <script>
        window.savedParentId = '';

        function injectParentId() {
            const pi = document.getElementById('parent_id');
            if (pi && window.savedParentId) {
                pi.value = window.savedParentId;
            }
            return true;
        }

        document.addEventListener('DOMContentLoaded', function () {
            const replyLinks = document.querySelectorAll('.reply-link');
            const form = document.getElementById('comment-form');
            const cancelReply = document.getElementById('cancel-reply');

            function clearReply() {
                window.savedParentId = '';
                const pi = document.getElementById('parent_id');
                if (pi) pi.value = '';
                const rb = document.getElementById('replying-to');
                if (rb) rb.style.display = 'none';
                const rn = document.getElementById('replying-to-name');
                if (rn) rn.textContent = '';

                const defaultLocation = document.getElementById('default-form-location');
                const mainFormCard = document.querySelector('.modern-comment-form-card');
                if (defaultLocation && mainFormCard) {
                    defaultLocation.appendChild(mainFormCard);
                    mainFormCard.style.marginTop = '';
                    mainFormCard.style.marginBottom = '40px';
                }
            }

            replyLinks.forEach(link => {
                link.addEventListener('click', function (e) {
                    e.preventDefault();
                    const targetId = this.getAttribute('data-target');
                    const author = this.getAttribute('data-author') || '';

                    window.savedParentId = targetId;

                    const commentItem = this.closest('.modern-comment-item');
                    const mainFormCard = document.querySelector('.modern-comment-form-card');

                    if (commentItem && mainFormCard) {
                        const contentWrapper = commentItem.querySelector('.comment-content-wrapper');
                        if (contentWrapper) {
                            const actionsDiv = contentWrapper.querySelector('.comment-actions');
                            if (actionsDiv) {
                                actionsDiv.parentNode.insertBefore(mainFormCard, actionsDiv.nextSibling);
                                mainFormCard.style.marginTop = '20px';
                                mainFormCard.style.marginBottom = '20px';
                            }
                        }
                    }

                    const pi = document.getElementById('parent_id');
                    if (pi) pi.value = targetId;

                    const rn = document.getElementById('replying-to-name');
                    const rb = document.getElementById('replying-to');
                    if (rn) rn.textContent = author;
                    if (rb) rb.style.display = 'inline-flex';

                    if (mainFormCard) {
                        mainFormCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    const nameInput = document.querySelector('#comment-form input[name="name"]');
                    if (nameInput) nameInput.focus();
                });
            });

            if (cancelReply) {
                cancelReply.addEventListener('click', function () {
                    clearReply();
                });
            }

            const toggleLinks = document.querySelectorAll('.toggle-replies-link');
            toggleLinks.forEach(link => {
                link.addEventListener('click', function (e) {
                    e.preventDefault();
                    const targetId = this.getAttribute('data-target');
                    const targetDiv = document.getElementById(targetId);
                    const showLabel = this.getAttribute('data-show-label') || this.dataset.showLabel || null;
                    const hideLabel = this.getAttribute('data-hide-label') || this.dataset.hideLabel || null;
                    if (targetDiv) {
                        if (targetDiv.style.display === 'none') {
                            targetDiv.style.display = 'block';
                            this.innerHTML = hideLabel || this.innerHTML;
                        } else {
                            targetDiv.style.display = 'none';
                            this.innerHTML = showLabel || this.innerHTML;
                        }
                    }
                });
            });

            const blogContentLinks = document.querySelectorAll('.blog-content-white a');
            blogContentLinks.forEach(link => {
                if (!link.hasAttribute('target')) {
                    link.setAttribute('target', '_blank');
                }
                if (!link.hasAttribute('rel')) {
                    link.setAttribute('rel', 'noopener noreferrer');
                }
            });

            const commentsSection = document.getElementById('comments');
            if (commentsSection) {
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            fetch('backend/notify_event.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: 'type=watched_comments&item_title=' + encodeURIComponent(<?= json_encode($pageTitle) ?>)
                            }).catch(() => { });
                            observer.unobserve(commentsSection);
                        }
                    });
                }, { threshold: 0.1 });
                observer.observe(commentsSection);
            }

            const cForm = document.getElementById('comment-form');
            if (cForm) {
                cForm.addEventListener('submit', function () {
                    const name = this.querySelector('[name="name"]').value;
                    const message = this.querySelector('[name="message"]').value;
                    if (name && message) {
                        fetch('backend/notify_event.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'type=new_comment&item_title=' + encodeURIComponent(<?= json_encode($pageTitle) ?>) + '&item_id=' + encodeURIComponent(<?= json_encode((string) $postObjectId) ?>)
                        }).catch(() => { });
                    }
                });
            }

            const sortBtns = document.querySelectorAll('.modern-sort-btn');
            const commentsList = document.querySelector('.comments-list');

            if (sortBtns.length > 0 && commentsList) {
                sortBtns.forEach(btn => {
                    btn.addEventListener('click', function () {
                        sortBtns.forEach(b => b.classList.remove('active'));
                        this.classList.add('active');

                        const isPopular = this.textContent.trim().toLowerCase().includes('popular');

                        const items = Array.from(commentsList.children).filter(el => el.classList.contains('modern-comment-item'));

                        items.forEach(item => {
                            if (isPopular) {
                                const score = parseInt(item.getAttribute('data-score') || '0');
                                if (score <= 0) {
                                    item.style.display = 'none';
                                } else {
                                    item.style.display = '';
                                }
                            } else {
                                item.style.display = '';
                            }
                        });

                        items.sort((a, b) => {
                            if (isPopular) {
                                const scoreA = parseInt(a.getAttribute('data-score') || '0');
                                const scoreB = parseInt(b.getAttribute('data-score') || '0');

                                if (scoreB !== scoreA) {
                                    return scoreB - scoreA;
                                }

                                const timeA = parseInt(a.getAttribute('data-created') || '0');
                                const timeB = parseInt(b.getAttribute('data-created') || '0');
                                return timeB - timeA;
                            } else {
                                const timeA = parseInt(a.getAttribute('data-created') || '0');
                                const timeB = parseInt(b.getAttribute('data-created') || '0');
                                return timeB - timeA;
                            }
                        });

                        items.forEach(item => commentsList.appendChild(item));
                    });
                });
            }
        });
    </script>

    <?php
    $isPublished = ($post['status'] ?? '') === 'published';
    if ($isPublished && !isset($_SESSION['admin'])):
        $jsCookieName = 'viewed_post_' . (string) $postObjectId;
        ?>
        <script>
            (function () {
                const cookieName = "<?php echo $jsCookieName; ?>";
                function getCookie(name) {
                    const value = `; ${document.cookie}`;
                    const parts = value.split(`; ${name}=`);
                    if (parts.length === 2) return parts.pop().split(';').shift();
                }

                if (getCookie(cookieName)) {
                    return;
                }

                const url = 'backend/content_sync.php?id=<?php echo urlencode((string) $postObjectId); ?>';

                fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin'
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            document.cookie = `${cookieName}=1; max-age=31536000; path=/`;
                        }
                    })
                    .catch(function () { });
            })();
        </script>
    <?php else: ?>
        <script>
            (function () {
                const cookieName = 'last_main_view_tracked';
                function getCookie(name) {
                    const value = `; ${document.cookie}`;
                    const parts = value.split(`; ${name}=`);
                    if (parts.length === 2) return parts.pop().split(';').shift();
                }
                if (getCookie(cookieName)) return;

                const fd = new FormData();
                fd.append('title', 'Blog Übersicht - ENTRIKS');

                fetch('backend/sync_data.php', {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin'
                }).then(r => r.json()).then(data => {
                    if (data.success) {
                        document.cookie = `${cookieName}=1; max-age=1800; path=/`;
                    }
                }).catch(() => { });
            })();
        </script>
    <?php endif; ?>

    <script>
        (function () {
            const container = document.getElementById('home-blog-container');
            if (!container) return;
            const urlParams = new URLSearchParams(window.location.search);
            const lang = urlParams.get('lang') || '<?php echo $lang; ?>';
            const requestedLimit = parseInt(container.dataset.featuredLimit || '3', 10) || 3;
            const limit = Math.max(1, Math.min(6, requestedLimit));
            fetch('backend/blog/get_featured.php?limit=' + encodeURIComponent(limit))
                .then(r => r.json())
                .then(data => {
                    if (!data.success || !data.posts || data.posts.length === 0) return;
                    let html = '';
                    data.posts.forEach(post => {
                        const title = (lang === 'en' && post.title_en) ? post.title_en : post.title;
                        const img = post.image_url ? `<img src="${post.image_url}" alt="${title}" loading="lazy">` : `<span style="width:100%;height:100%;background:#333;display:block;"></span>`;
                        html += `
                            <div class="col-xl-6 col-md-6 mb-30">
                                <div class="home-blog-style-one">
                                    <div class="thumb"><a href="blog.php?id=${post.id}&lang=${lang}">${img}</a></div>
                                    <div class="info">
                                        <div class="meta"><ul><li><a href="#">${post.author}</a></li><li>${post.date}</li></ul></div>
                                        <h4 class="post-title"><a href="blog.php?id=${post.id}&lang=${lang}">${title}</a></h4>
                                    </div>
                                </div>
                            </div>`;
                    });
                    container.innerHTML = html;
                }).catch(() => { });
        })();
    </script>

    <?php if (isset($_SESSION['admin'])): ?>
        <script>
            (function () {
                if ("Notification" in window) {
                    if (Notification.permission !== "granted" && Notification.permission !== "denied") {
                        Notification.requestPermission();
                    }
                }

                let lastPollTime = <?= class_exists('MongoDB\BSON\UTCDateTime') ? (string) new UTCDateTime() : 'Date.now()' ?>;
                const basePrefix = 'backend/';

                function showNotification(notification) {
                    let title = '';
                    let body = '';
                    let iconUrl = 'assets/img/favicon.png';

                    switch (notification.type) {
                        case 'blog_view':
                            title = (lang === 'de') ? 'Neuer Blog-Besuch' : 'New Blog View';
                            body = notification.item_title || (lang === 'de' ? 'Blog-Beitrag' : 'Blog Post');
                            break;
                        case 'main_view':
                            title = (lang === 'de') ? 'Neuer Website-Besuch' : 'New Website Visit';
                            body = (lang === 'de') ? 'Startseite' : 'Home Page';
                            break;
                        case 'new_comment':
                            title = (lang === 'de') ? 'Neuer Kommentar' : 'New Comment';
                            body = notification.item_title || (lang === 'de' ? 'Blog-Beitrag' : 'Blog Post');
                            break;
                        case 'watched_comments':
                            title = (lang === 'de') ? 'Kommentare angesehen' : 'Comments Watched';
                            body = notification.item_title || (lang === 'de' ? 'Blog-Beitrag' : 'Blog Post');
                            break;
                        default:
                            title = (lang === 'de') ? 'Benachrichtigung' : 'Notification';
                            body = notification.item_title || (lang === 'de' ? 'Neue Aktivität' : 'New Activity');
                    }

                    if ("Notification" in window && Notification.permission === "granted") {
                        try {
                            new Notification(title, {
                                body: body,
                                icon: iconUrl,
                                tag: notification.id
                            });
                        } catch (e) { console.error('Notification error:', e); }
                    }
                }

                function pollNotifications() {
                    fetch(`${basePrefix}get_notifications.php?since=${lastPollTime}`)
                        .then(res => res.json())
                        .then(data => {
                            if (data.success && data.notifications.length > 0) {
                                data.notifications.forEach(n => {
                                    showNotification(n);
                                });
                                lastPollTime = data.server_time;
                            } else if (data.success) {
                                lastPollTime = data.server_time;
                            }
                        })
                        .catch(err => console.error('Poll error:', err));
                }

                setInterval(pollNotifications, 5000);
                setTimeout(pollNotifications, 1000);
            })();
        </script>
    <?php endif; ?>
    <link rel="stylesheet" href="assets/css/cookie-consent.css">
    <script>
        window.cookieConsentEnabled = <?php echo (isset($settings['cookie_consent_enabled']) ? $settings['cookie_consent_enabled'] : true) ? 'true' : 'false'; ?>;
    </script>
    <script src="assets/js/cookie-consent.js" defer></script>

    <?php if ($isEditorMode): ?>
        <div id="admin-editor-toolbar" class="admin-editor-toolbar">
            <button id="admin-save-btn" class="admin-editor-btn btn-save">Save Changes</button>
            <input type="file" id="admin-image-upload" accept="image/*">
        </div>
        <script>
            window.editorPageId = 'blog';
        </script>
        <script src="assets/js/admin_editor.js"></script>
        <?php if (isset($_GET['focus'])): ?>
            <style>
                .focus-mode-hidden {
                    opacity: 0.15;
                    pointer-events: none;
                    filter: grayscale(100%);
                    transition: all 0.5s ease;
                }
            </style>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const focusType = '<?php echo htmlspecialchars($_GET['focus']); ?>';
                    const targetId = (focusType === 'header') ? 'site-header' : 'site-footer';
                    const target = document.getElementById(targetId);

                    if (target) {
                        let current = target;
                        while (current && current.tagName !== 'BODY') {
                            const parent = current.parentElement;
                            if (!parent) break;

                            Array.from(parent.children).forEach(child => {
                                if (child !== current &&
                                    child.id !== 'admin-editor-toolbar' &&
                                    !child.classList.contains('admin-editor-sidebar') &&
                                    child.tagName !== 'SCRIPT' &&
                                    child.tagName !== 'STYLE' &&
                                    child.tagName !== 'LINK') {
                                    child.classList.add('focus-mode-hidden');
                                }
                            });
                            current = parent;
                        }
                    }
                });
            </script>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($isAdmin): ?>
        <link rel="stylesheet" href="assets/css/admin_editor.css">
        <script>
            window.editorPageId = 'blog_<?php echo $lang; ?>';
            document.body.classList.add('admin-editor-active');
        </script>
        <script src="assets/js/admin_editor.js"></script>
        <?php if (isset($_GET['focus'])): ?>
            <style>
                .focus-mode-hidden {
                    display: none !important;
                }
            </style>
        <?php endif; ?>
    <?php endif; ?>
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div>
                    <div class="logo-wrap" style="margin-bottom:1rem">
                        <img src="assets/img/logo.png" alt="ENTRIKS Logo" class="logo-img">
                        <div class="logo-sub">TALENT HUB</div>
                    </div>
                    <p class="footer-desc"><?php echo $fc['footer']['desc']; ?></p>
                    <p class="footer-note"><?php echo $fc['footer']['note']; ?></p>
                    <div class="footer-social">
              <a href="https://www.facebook.com/ENTRIKS/" aria-label="Facebook">
                <i class="fab fa-facebook-f"></i>
              </a>
              <a href="https://www.instagram.com/entriks_/" aria-label="Instagram">
                <i class="fab fa-instagram"></i>
              </a>
              <a href="https://www.linkedin.com/company/entriks" aria-label="LinkedIn">
                <i class="fab fa-linkedin-in"></i>
              </a>
            </div>
                </div>
                <div class="fcol">
                    <h4><?php echo $fc['footer']['contact']; ?></h4>
                    <ul>
                        <li><a href="mailto:<?php echo $fc['modal']['email_address']; ?>"><?php echo $fc['modal']['email_address']; ?></a></li>
                        <li><a href="tel:<?php echo $fc['modal']['phone']; ?>"><?php echo $fc['modal']['phone_display']; ?></a></li>
                        <li><a href="#"><?php echo $fc['about']['location_name']; ?><br><?php echo $fc['about']['location_city']; ?></a></li>
                    </ul>
                </div>
                <div class="fcol">
                    <h4><?php echo $fc['footer']['services']; ?></h4>
                    <ul>
                        <?php foreach ($fc['footer']['links'] as $i => $link): ?>
                        <li><a href="index.php#<?php echo $i < 2 ? 'nearshoring' : ($i == 2 ? 'active-sourcing' : 'kosovo'); ?><?php echo $lang === 'en' ? '?lang=en' : ''; ?>"><?php echo $link; ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="fcol">
                    <h4><?php echo $fc['footer']['company']; ?></h4>
                    <ul>
                        <?php foreach ($fc['footer']['company_links'] as $link): ?>
                        <li><a href="#"><?php echo $link; ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <span><?php echo $fc['footer']['copyright']; ?></span>
                <div class="fbl">
                    <a href="impressum.php<?php echo $lang === 'en' ? '?lang=en' : ''; ?>"><?php echo $fc['footer']['legal'][0]; ?></a>
                    <a href="datenschutz.php<?php echo $lang === 'en' ? '?lang=en' : ''; ?>"><?php echo $fc['footer']['legal'][1]; ?></a>
                    <a href="agb.php<?php echo $lang === 'en' ? '?lang=en' : ''; ?>"><?php echo $fc['footer']['legal'][2]; ?></a>
                </div>
            </div>
        </div>
    </footer>

    <button class="back-to-top" id="backToTop" aria-label="Back to top" onclick="window.scrollTo({top:0,behavior:'smooth'})">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="18 15 12 9 6 15" />
        </svg>
    </button>

    <script>
        (function(){const btn=document.getElementById('backToTop');if(!btn)return;window.addEventListener('scroll',function(){btn.classList.toggle('visible',window.scrollY>400)});})();
        (function(){const nav=document.getElementById('navbar');if(!nav)return;let lastScrollY=window.scrollY,ticking=false;function updateNavbar(){const currentScrollY=window.scrollY;if(currentScrollY>lastScrollY&&currentScrollY>100){nav.classList.add('hidden');}else{nav.classList.remove('hidden');}nav.classList.toggle('scrolled',currentScrollY>30);lastScrollY=currentScrollY;ticking=false;}window.addEventListener('scroll',()=>{if(!ticking){window.requestAnimationFrame(updateNavbar);ticking=true;}},{passive:true});})();
        const langWrap=document.getElementById('langDropdownWrap'),langGlobeBtn=document.getElementById('langGlobeBtn'),langDropdown=document.getElementById('langDropdown');
        if(langGlobeBtn&&langWrap){langGlobeBtn.addEventListener('click',(e)=>{e.stopPropagation();const isOpen=langWrap.classList.toggle('open');langGlobeBtn.setAttribute('aria-expanded',isOpen);});document.addEventListener('click',()=>{langWrap.classList.remove('open');langGlobeBtn.setAttribute('aria-expanded','false');});langDropdown&&langDropdown.addEventListener('click',(e)=>e.stopPropagation());}
        const langWrapMobile=document.getElementById('langDropdownWrapMobile'),langGlobeBtnMobile=document.getElementById('langGlobeBtnMobile'),langDropdownMobile=document.getElementById('langDropdownMobile');
        if(langGlobeBtnMobile&&langWrapMobile){langGlobeBtnMobile.addEventListener('click',(e)=>{e.stopPropagation();const isOpen=langWrapMobile.classList.toggle('open');langGlobeBtnMobile.setAttribute('aria-expanded',isOpen);});document.addEventListener('click',()=>{langWrapMobile.classList.remove('open');langGlobeBtnMobile.setAttribute('aria-expanded','false');});langDropdownMobile&&langDropdownMobile.addEventListener('click',(e)=>e.stopPropagation());}
        const mobBtn=document.getElementById('mobBtn'),mobMenu=document.getElementById('mobMenu');
        if(mobBtn&&mobMenu){mobBtn.addEventListener('click',()=>mobMenu.classList.toggle('open'));document.querySelectorAll('.mob-menu a').forEach(a=>{a.addEventListener('click',()=>mobMenu.classList.remove('open'));});}
    </script>

</body>
</html>