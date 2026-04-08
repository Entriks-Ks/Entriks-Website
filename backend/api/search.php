<?php
// backend/api/search.php
require_once '../session_config.php';
require_once '../config.php';
// require_once '../database.php'; // Already included in config.php

header('Content-Type: application/json');

if (!isset($_SESSION['admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$results = [];

if (strlen($query) < 2) {
    echo json_encode(['success' => true, 'results' => []]);
    exit;
}

// 1. Defined Pages & Actions
$params = [
    // 1. Dashboard Analysis (Deep Links)
    ['title' => $lang['dash_traffic_analysis'] ?? 'Traffic Analysis', 'category' => 'Dashboard', 'url' => 'dashboard.php#tour-traffic-chart', 'keywords' => 'analytics stats views traffic visitor'],
    ['title' => $lang['dash_system_alerts'] ?? 'System Alerts', 'category' => 'Dashboard', 'url' => 'dashboard.php#tour-system-alerts', 'keywords' => 'alerts notifications warnings system'],
    ['title' => $lang['dash_recent_posts'] ?? 'Recent Posts', 'category' => 'Dashboard', 'url' => 'dashboard.php#panel-recent-posts', 'keywords' => 'recent latest new posts dashboard'],
    ['title' => $lang['dash_most_viewed'] ?? 'Most Viewed Posts', 'category' => 'Dashboard', 'url' => 'dashboard.php#panel-most-viewed', 'keywords' => 'popular best top viewed posts'],
    ['title' => $lang['dash_scheduled_posts'] ?? 'Scheduled Posts', 'category' => 'Dashboard', 'url' => 'dashboard.php#tour-scheduled-posts', 'keywords' => 'scheduled planned future posts'],
    // 2. CMS (Deep Links)
    ['title' => $lang['cms_edit_german'] ?? 'German Homepage Editor', 'category' => 'CMS', 'url' => 'cms_manager.php#card-edit-de', 'keywords' => 'edit de german homepage deutsch bearbeiten startseite'],
    ['title' => $lang['cms_edit_english'] ?? 'English Homepage Editor', 'category' => 'CMS', 'url' => 'cms_manager.php#card-edit-en', 'keywords' => 'edit en english homepage englisch bearbeiten startseite'],
    // 3. Blog & Comments (Tools)
    ['title' => $lang['create_post_title'] ?? 'New Post', 'category' => 'Blog', 'url' => 'blog/create.php', 'keywords' => 'write add new article post create'],
    ['title' => ($lang['menu_blog_manager'] ?? 'Blog Manager') . ' Filters', 'category' => 'Blog', 'url' => 'blog/index.php#filtersToggleBtn', 'keywords' => 'filter sort search blog posts category status'],
    ['title' => ($lang['menu_comments'] ?? 'Comments') . ' Filters', 'category' => 'Comments', 'url' => 'blog/comments.php#filterBtn', 'keywords' => 'filter sort search comments moderation'],
    ['title' => $lang['menu_archived_posts'] ?? 'Archived Posts', 'category' => 'Blog', 'url' => 'blog/archived.php', 'keywords' => 'archive history old posts deleted'],
    // 4. Account Settings (Deep Links - Complete)
    ['title' => $lang['settings_title'] ?? 'Account Settings', 'category' => 'Pages', 'url' => 'account-settings.php', 'keywords' => 'profile user config'],
    ['title' => $lang['label_contact_email'] ?? 'Contact Email', 'category' => 'Settings', 'url' => 'account-settings.php#contact_email', 'keywords' => 'email contact mail adresse'],
    ['title' => $lang['label_display_name'] ?? 'Display Name', 'category' => 'Settings', 'url' => 'account-settings.php#display_name', 'keywords' => 'name user profile anzeige name'],
    ['title' => $lang['label_site_name'] ?? 'Site Name', 'category' => 'Settings', 'url' => 'account-settings.php#site_name', 'keywords' => 'website title name titel branding'],
    ['title' => $lang['label_logo_url'] ?? 'Logo URL', 'category' => 'Settings', 'url' => 'account-settings.php#logo_url', 'keywords' => 'branding image site logo bild'],
    ['title' => $lang['label_favicon_url'] ?? 'Favicon URL', 'category' => 'Settings', 'url' => 'account-settings.php#favicon_url', 'keywords' => 'icon browser tab symbol'],
    ['title' => $lang['label_footer_logo_url'] ?? 'Footer Logo URL', 'category' => 'Settings', 'url' => 'account-settings.php#footer_logo_url', 'keywords' => 'footer bild logo image unten'],
    ['title' => $lang['label_footer_de'] ?? 'Footer Text (DE)', 'category' => 'Settings', 'url' => 'account-settings.php#footer_text_de', 'keywords' => 'footer text deutsch footertext'],
    ['title' => $lang['label_footer_en'] ?? 'Footer Text (EN)', 'category' => 'Settings', 'url' => 'account-settings.php#footer_text_en', 'keywords' => 'footer text english englisch'],
    ['title' => $lang['label_meta_de'] ?? 'Meta Description (De)', 'category' => 'Settings', 'url' => 'account-settings.php#meta_description_de', 'keywords' => 'seo meta description deutsch haupt beschreibung'],
    ['title' => $lang['label_meta_en'] ?? 'Meta Description (En)', 'category' => 'Settings', 'url' => 'account-settings.php#meta_description_en', 'keywords' => 'seo meta description english main'],
    ['title' => $lang['label_phone'] ?? 'Phone Number', 'category' => 'Settings', 'url' => 'account-settings.php#contact_phone', 'keywords' => 'phone telefon nummer kontakt'],
    ['title' => $lang['label_address'] ?? 'Address', 'category' => 'Settings', 'url' => 'account-settings.php#contact_address', 'keywords' => 'address adresse standort location'],
    ['title' => $lang['label_fb'] ?? 'Facebook URL', 'category' => 'Settings', 'url' => 'account-settings.php#social_facebook', 'keywords' => 'facebook social media link fb'],
    ['title' => $lang['label_insta'] ?? 'Instagram URL', 'category' => 'Settings', 'url' => 'account-settings.php#social_instagram', 'keywords' => 'instagram social media link insta'],
    ['title' => $lang['label_linkedin'] ?? 'LinkedIn URL', 'category' => 'Settings', 'url' => 'account-settings.php#social_linkedin', 'keywords' => 'linkedin social media link'],
    ['title' => $lang['label_cookie_consent'] ?? 'Cookie Consent', 'category' => 'Settings', 'url' => 'account-settings.php#cookie_consent_enabled', 'keywords' => 'cookie consent banner dsgvo privacy'],
    ['title' => $lang['label_back_to_top'] ?? 'Back to Top', 'category' => 'Settings', 'url' => 'account-settings.php#back_to_top_enabled', 'keywords' => 'scroll top button oben'],
    ['title' => ($lang['label_back_to_top'] ?? 'Back to Top') . ' (Mobile)', 'category' => 'Settings', 'url' => 'account-settings.php#back_to_top_mobile_enabled', 'keywords' => 'scroll top mobile handy'],
    ['title' => $lang['label_posts_per_page'] ?? 'Posts Per Page', 'category' => 'Settings', 'url' => 'account-settings.php#posts_per_page', 'keywords' => 'blog pagination posts anzahl pro seite'],
    ['title' => $lang['label_enable_comments'] ?? 'Enable Comments', 'category' => 'Settings', 'url' => 'account-settings.php#comments_enabled', 'keywords' => 'blog comments kommentare aktivieren'],
    ['title' => $lang['label_show_recent'] ?? 'Show Recent Posts', 'category' => 'Settings', 'url' => 'account-settings.php#blog_show_recent_posts', 'keywords' => 'blog recent sidebar letzte beiträge'],
    ['title' => $lang['label_show_categories'] ?? 'Show Categories', 'category' => 'Settings', 'url' => 'account-settings.php#blog_show_categories', 'keywords' => 'blog categories sidebar kategorien'],
    ['title' => $lang['label_show_tags'] ?? 'Show Tags', 'category' => 'Settings', 'url' => 'account-settings.php#blog_show_tags', 'keywords' => 'blog tags sidebar schlagworte'],
    ['title' => $lang['label_default_lang'] ?? 'Default Language', 'category' => 'Settings', 'url' => 'account-settings.php#default_language', 'keywords' => 'language sprache default standard'],
    // 5. Analytics (Detailed Deep Links)
    ['title' => $lang['menu_analytics'] ?? 'Analytics', 'category' => 'Analytics', 'url' => 'analytics.php', 'keywords' => 'stats traffic views visitors analysis'],
    ['title' => $lang['ana_traffic_overview'] ?? 'Traffic Overview', 'category' => 'Analytics', 'url' => 'analytics.php#ana-traffic-overview', 'keywords' => 'chart graph traffic views daily'],
    ['title' => $lang['ana_traffic_sources'] ?? 'Traffic Sources', 'category' => 'Analytics', 'url' => 'analytics.php#ana-traffic-sources', 'keywords' => 'direct search social referral sources'],
    ['title' => $lang['ana_device_breakdown'] ?? 'Device Breakdown', 'category' => 'Analytics', 'url' => 'analytics.php#ana-devices', 'keywords' => 'mobile desktop tablet devices'],
    ['title' => $lang['ana_browsers'] ?? 'Browsers', 'category' => 'Analytics', 'url' => 'analytics.php#ana-browsers', 'keywords' => 'chrome safari firefox edge browser'],
    ['title' => $lang['ana_os'] ?? 'Operating Systems', 'category' => 'Analytics', 'url' => 'analytics.php#ana-os', 'keywords' => 'windows macos android ios os'],
    ['title' => $lang['ana_top_languages'] ?? 'Top Languages', 'category' => 'Analytics', 'url' => 'analytics.php#ana-languages', 'keywords' => 'browser language de en fr'],
    // 6. Team Management
    ['title' => $lang['menu_team_management'] ?? 'Team Management', 'category' => 'Team', 'url' => 'team_management.php', 'keywords' => 'invite members edit users role position'],
];

foreach ($params as $item) {
    if (stripos($item['title'], $query) !== false || stripos($item['keywords'], $query) !== false) {
        $results[] = $item;
    }
}

// 2. Search Team Members (MongoDB)
try {
    $regex = new MongoDB\BSON\Regex($query, 'i');
    $cursor = $db->admins->find(
        ['$or' => [
            ['display_name' => $regex],
            ['email' => $regex],
            ['position' => $regex]
        ]],
        [
            'limit' => 5,
            'projection' => ['display_name' => 1, 'email' => 1, 'position' => 1, '_id' => 1]
        ]
    );

    foreach ($cursor as $doc) {
        $results[] = [
            'title' => $doc['display_name'] ?? 'Pending Member',
            'category' => 'Team Members',
            'url' => 'team_management.php#team-list',  // Navigate to team page
            'subtitle' => ($doc['position'] ?? 'Member') . ' (' . $doc['email'] . ')'
        ];
    }
} catch (Exception $e) {
    // Ignore db errors
}

// 3. Search Blog Posts (MongoDB) - DISABLED

/*
 * try {
 *     $regex = new MongoDB\BSON\Regex($query, 'i');
 *     $cursor = $db->blog->find(
 *         ['$or' => [
 *             ['title' => $regex],
 *             ['title_de' => $regex],
 *             ['title_en' => $regex]
 *         ]],
 *         [
 *             'limit' => 5,
 *             'projection' => ['title' => 1, 'title_de' => 1, 'title_en' => 1, '_id' => 1, 'status' => 1]
 *         ]
 *     );
 *
 *     foreach ($cursor as $doc) {
 *         $title = $doc['title_de'] ?? $doc['title_en'] ?? $doc['title'] ?? 'Untitled';
 *         $results[] = [
 *             'title' => $title,
 *             'category' => 'Blog Posts',
 *             'url' => 'blog.php?id=' . (string) $doc['_id'],
 *             'subtitle' => isset($doc['status']) ? ucfirst($doc['status']) : ''
 *         ];
 *     }
 * } catch (Exception $e) {
 *     // Ignore db errors for search
 * }
 */

// Group results
$grouped = [];
foreach ($results as $res) {
    $cat = $res['category'];
    if (!isset($grouped[$cat])) {
        $grouped[$cat] = [];
    }
    $grouped[$cat][] = $res;
}

echo json_encode(['success' => true, 'results' => $grouped]);
