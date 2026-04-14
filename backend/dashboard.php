<?php
require_once 'session_config.php';
require 'database.php';
/** @var \MongoDB\Database $db */
require 'config.php';
require_once 'includes/dashboard_functions.php';

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

$userRole = $_SESSION['admin']['position'] ?? 'Editor';
$isAdmin = ($_SESSION['admin']['role'] ?? 'admin') === 'admin';
if ($isAdmin)
    $userRole = 'Admin';

// Redirect non-admin users to their role-specific dashboard
if (!$isAdmin) {
    $pos = strtolower($userRole);
    if ($pos === 'author') {
        header('Location: dashboard-author.php');
        exit;
    } elseif ($pos === 'editor') {
        header('Location: dashboard-editor.php');
        exit;
    } elseif ($pos === 'content manager') {
        header('Location: dashboard-contentmanager.php');
        exit;
    }
}

$hasCmsAccess = $isAdmin || in_array($userRole, ['Editor', 'Content Manager']);
$hasBlogAccess = $isAdmin || in_array($userRole, ['Content Manager', 'Author']);
$hasAnalyticsAccess = $isAdmin;
$hasSystemAlerts = $isAdmin;

$totalPosts = 0;
$totalComments = 0;
$publishedCount = 0;
$draftCount = 0;

if ($hasBlogAccess) {
    $blogQuery = [];
    if ($userRole === 'Author') {
        $authorEmail = $_SESSION['admin']['email'] ?? 'unknown';
        $blogQuery['author_email'] = $authorEmail;
        $totalPosts = $db->blog->countDocuments($blogQuery);
        $totalComments = $db->comments->countDocuments(['author_email' => $authorEmail]);  // Assuming comments or linked
        $publishedCount = $db->blog->countDocuments(array_merge($blogQuery, ['status' => 'published']));
        $draftCount = $db->blog->countDocuments(array_merge($blogQuery, ['status' => ['$in' => ['draft', null]]]));
    } else {
        $totalPosts = $db->blog->countDocuments();
        $totalComments = $db->comments->countDocuments([]);
        $publishedCount = $db->blog->countDocuments(['status' => 'published']);
        $draftCount = $db->blog->countDocuments(['status' => ['$in' => ['draft', null]]]);
    }
}

$totalPages = 0;
if ($userRole === 'Editor' || $isAdmin) {
    $totalPages = $db->page_content->countDocuments();
}

$chart7d = getViewsData($db, $lang, 7, 'D');

$chart7d_main = getMainViewsData($db, $lang, 7, 'D');

$totalBlogViews7d = array_sum($chart7d['data']);
$totalMainViews7d = array_sum($chart7d_main['data']);
$totalViews = $totalBlogViews7d + $totalMainViews7d;

$chart30d = getViewsData($db, $lang, 30, 'M d');
$chart30d_main = getMainViewsData($db, $lang, 30, 'M d');

$startMonthDate = date('Y-m-01', strtotime('-11 months'));
$endMonthDate = date('Y-m-t');

$monthsMap = [];
for ($i = 11; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("-$i months"));
    $monthsMap[$m] = 0;
}

$pipeline12m = [
    ['$match' => ['views_history' => ['$exists' => true]]],
    ['$project' => ['history' => ['$objectToArray' => '$views_history']]],
    ['$unwind' => '$history'],
    ['$match' => [
        'history.k' => ['$gte' => $startMonthDate],
        '$expr' => ['$eq' => [['$strLenCP' => '$history.k'], 10]]
    ]],
    ['$group' => [
        '_id' => ['$substr' => ['$history.k', 0, 7]],
        'totalViews' => ['$sum' => '$history.v']
    ]]
];

$results12m = $db->blog->aggregate($pipeline12m);
foreach ($results12m as $res) {
    if (isset($monthsMap[$res['_id']])) {
        $monthsMap[$res['_id']] = $res['totalViews'];
    }
}
$chart12m_labels = [];
$chart12m_data = [];
foreach ($monthsMap as $m => $val) {
    $ts = strtotime($m . '-01');
    $mLabel = date('M Y', $ts);

    $enM = date('M', $ts);
    $transM = $lang['month_' . strtolower($enM)] ?? $enM;
    $mLabel = str_replace($enM, $transM, $mLabel);

    $chart12m_labels[] = $mLabel;
    $chart12m_data[] = $val;
}
$chart12m = ['labels' => $chart12m_labels, 'data' => $chart12m_data];

$mainMonthsMap = [];
foreach (array_keys($monthsMap) as $m)
    $mainMonthsMap[$m] = 0;
$cursor = $db->main_views->find([
    'date' => ['$gte' => $startMonthDate]
]);

foreach ($cursor as $doc) {
    $d = $doc['date'];
    if (strlen($d) === 10) {
        $month = substr($d, 0, 7);
        if (isset($mainMonthsMap[$month])) {
            $mainMonthsMap[$month] += (int) ($doc['count'] ?? 0);
        }
    }
}

$chart12m_main_labels = $chart12m_labels;
$chart12m_main_data = array_values($mainMonthsMap);
$chart12m_main = ['labels' => $chart12m_main_labels, 'data' => $chart12m_main_data];

$totalBlogViews12m = array_sum($chart12m['data']);
$totalMainViews12m = array_sum($chart12m_main['data']);
$totalViews12m = $totalBlogViews12m + $totalMainViews12m;

$startHour = date('Y-m-d H', strtotime('-23 hours'));
$pipeline24h = [
    ['$match' => ['views_history' => ['$exists' => true]]],
    ['$project' => ['history' => ['$objectToArray' => '$views_history']]],
    ['$unwind' => '$history'],
    ['$match' => [
        'history.k' => ['$gte' => $startHour],
        '$expr' => ['$gt' => [['$strLenCP' => '$history.k'], 10]]  // Only hourly keys
    ]],
    ['$group' => [
        '_id' => '$history.k',
        'totalViews' => ['$sum' => '$history.v']
    ]]
];

$results24h = $db->blog->aggregate($pipeline24h);
$viewsMap24h = [];
foreach ($results24h as $res) {
    $viewsMap24h[$res['_id']] = $res['totalViews'];
}

$chart24h_labels = [];
$chart24h_data = [];
for ($i = 23; $i >= 0; $i--) {
    $hour = date('Y-m-d H', strtotime("-$i hours"));
    $chart24h_labels[] = date('H:00', strtotime("-$i hours"));
    $chart24h_data[] = $viewsMap24h[$hour] ?? 0;
}
$chart24h = ['labels' => $chart24h_labels, 'data' => $chart24h_data];

$chart24h_main_labels = $chart24h_labels;
$chart24h_main_data = [];
$cursor24hMain = $db->main_views->find(['date' => ['$gte' => $startHour]]);
$mainViewsMap24h = [];
foreach ($cursor24hMain as $doc) {
    if (strlen($doc['date']) > 10) {
        $mainViewsMap24h[$doc['date']] = $doc['count'] ?? 0;
    }
}

for ($i = 23; $i >= 0; $i--) {
    $hour = date('Y-m-d H', strtotime("-$i hours"));
    $chart24h_main_data[] = $mainViewsMap24h[$hour] ?? 0;
}
$chart24h_main = ['labels' => $chart24h_main_labels, 'data' => $chart24h_main_data];

$listQuery = [];
if ($userRole === 'Author') {
    $listQuery['author_email'] = $_SESSION['admin']['email'] ?? 'unknown';
}

$topPostsCursor = $db->blog->find(
    array_merge($listQuery, ['views' => ['$gt' => 0]]),
    [
        'limit' => 3,
        'sort' => ['views' => -1],
        'projection' => ['title' => 1, 'title_de' => 1, 'title_en' => 1, 'views' => 1]
    ]
);
$topPostsArray = iterator_to_array($topPostsCursor, false);

$recentPostsCursor = $db->blog->find(
    $listQuery,
    [
        'limit' => 3,
        'sort' => ['created_at' => -1],
        'projection' => ['title_de' => 1, 'title_en' => 1, 'created_at' => 1]
    ]
);
$recentPostsArray = iterator_to_array($recentPostsCursor, false);

try {
    $commentQuery = [];
    if ($userRole === 'Author') {
        // Simple filter for Author: comments on their posts (if post_id mapping exists)
        // For now, if Author email matches, though usually comments don't have author_email of the post author.
        // We'll leave it global or empty if we don't have the mapping here easily.
    }
    $recentCommentsCursor = $db->comments->find(
        $commentQuery,
        [
            'limit' => 3,
            'sort' => ['created_at' => -1],
            'projection' => ['name' => 1, 'message' => 1, 'created_at' => 1]
        ]
    );
    $recentComments = iterator_to_array($recentCommentsCursor, false);
} catch (Exception $e) {
    $recentComments = [];
}

// --- Fetch Admin Display Name ---
$adminDisplayName = 'Admin';
try {
    // 1. Check if name is in session
    if (isset($_SESSION['admin']['name']) && !empty($_SESSION['admin']['name'])) {
        $adminDisplayName = $_SESSION['admin']['name'];
    }
    // 2. Check global settings for a display name
    else {
        $settings = $db->settings->findOne(['type' => 'global_config']);
        if ($settings && isset($settings['display_name'])) {
            $adminDisplayName = $settings['display_name'];
        }
        // 3. Fallback to email prefix
        else {
            $email = $_SESSION['admin']['email'] ?? 'Admin';
            $namePart = explode('@', $email)[0];
            $adminDisplayName = ucfirst($namePart);
        }
    }
} catch (Exception $e) {
}

// --- 4. SYSTEM ALERTS LOGIC (CRITICAL ONLY) ---
$systemAlerts = [];

// D. BUSINESS CRITICAL (Homepage Stagnation - Only if severe)
try {
    $latestPost = $db->blog->findOne(['status' => 'published'], ['sort' => ['created_at' => -1]]);
    $daysSinceUpdate = 0;
    if ($latestPost && isset($latestPost['created_at'])) {
        $lastDate = ($latestPost['created_at'] instanceof MongoDB\BSON\UTCDateTime)
            ? $latestPost['created_at']->toDateTime()
            : new DateTime($latestPost['created_at']);

        $daysSinceUpdate = (int) $lastDate->diff(new DateTime())->format('%a');
    }

    if ($daysSinceUpdate >= 30) {  // Only showing if > 30 days (Very Critical)
        $systemAlerts[] = [
            'severity' => 'orange',
            'icon' => 'clock',
            'title' => sprintf($lang['alert_site_dormant'], $daysSinceUpdate),
            'cta' => $lang['action_write_post'],
            'link' => 'blog/create_post.php'
        ];
    }
} catch (Exception $e) {
}

// SORTING & LIMITING
$severityWeights = ['red' => 3, 'orange' => 2, 'yellow' => 1];
usort($systemAlerts, function ($a, $b) use ($severityWeights) {
    return $severityWeights[$b['severity']] <=> $severityWeights[$a['severity']];
});

// Strictly limit to 2 alerts
$systemAlerts = array_slice($systemAlerts, 0, 2);
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard</title>
    <link rel="shortcut icon" href="<?= htmlspecialchars($siteFaviconUrl) ?>" type="image/x-icon">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?= time() ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <!-- Preloader -->
    <div class="preloader" id="preloader">
        <div class="preloader-spinner"></div>
    </div>

    <div class="layout">
        <!-- SIDEBAR -->
        <?php $sidebarVariant = 'dashboard';
        $activeMenu = 'dashboard';
        include __DIR__ . '/partials/sidebar.php'; ?>

        <main class="content">
            <!-- Blur Background Theme -->
            <div class="blur-bg-theme bottom-right"></div>

            <?php
            $showWelcomeMessage = true;
            include __DIR__ . '/partials/topbar.php';
            ?>

            <div class="stats-cards-row" id="tour-stats-row" style="display: flex; gap: 20px; flex-wrap: wrap;">
                <?php if ($hasBlogAccess && $userRole !== 'Editor'): ?>
                <!-- Total Posts Card -->
                <div class="stat-card-new horizontal" style="flex: 1; min-width: 200px;">
                    <div class="card-icon-wrapper" style="background:rgba(99,102,241,0.15);">
                        <svg style="width:20px; height:20px; color:#6366f1;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                            <path fill-rule="evenodd" d="M4.125 3C3.089 3 2.25 3.84 2.25 4.875V18a3 3 0 0 0 3 3h15a3 3 0 0 1-3-3V4.875C17.25 3.839 16.41 3 15.375 3H4.125ZM12 9.75a.75.75 0 0 0 0 1.5h1.5a.75.75 0 0 0 0-1.5H12Zm-.75-2.25a.75.75 0 0 1 .75-.75h1.5a.75.75 0 0 1 0 1.5H12a.75.75 0 0 1-.75-.75ZM6 12.75a.75.75 0 0 0 0 1.5h7.5a.75.75 0 0 0 0-1.5H6Zm-.75 3.75a.75.75 0 0 1 .75-.75h7.5a.75.75 0 0 1 0 1.5H6a.75.75 0 0 1-.75-.75ZM6 6.75a.75.75 0 0 0-.75.75v3c0 .414.336.75.75.75h3a.75.75 0 0 0 .75-.75v-3A.75.75 0 0 0 9 6.75H6Z" clip-rule="evenodd" />
                            <path d="M18.75 6.75h1.875c.621 0 1.125.504 1.125 1.125V18a1.5 1.5 0 0 1-3 0V6.75Z" />
                        </svg>
                    </div>
                    <div class="card-info">
                        <div class="card-label"><?= $lang['dash_total_posts'] ?></div>
                        <div class="card-value"><?= number_format($totalPosts) ?></div>
                    </div>
                </div>

                <!-- Published Card -->
                <div class="stat-card-new horizontal" style="flex: 1; min-width: 200px;">
                    <div class="card-icon-wrapper" style="background:rgba(118,117,236,0.15);">
                        <svg style="width:20px; height:20px; color:#7675ec;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                            <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm13.36-1.814a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="card-info">
                        <div class="card-label"><?= $lang['dash_published'] ?></div>
                        <div class="card-value"><?= number_format($publishedCount) ?></div>
                    </div>
                </div>

                <!-- Comments Card -->
                <div class="stat-card-new horizontal" style="flex: 1; min-width: 200px;">
                    <div class="card-icon-wrapper" style="background:rgba(210,37,215,0.15);">
                        <svg style="width:20px; height:20px; color:#d225d7;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                            <path fill-rule="evenodd" d="M12 2.25c-2.429 0-4.817.178-7.152.521C2.87 3.061 1.5 4.795 1.5 6.741v6.018c0 1.946 1.37 3.68 3.348 3.97.877.129 1.761.234 2.652.316V21a.75.75 0 0 0 1.28.53l4.184-4.183a.39.39 0 0 1 .266-.112c2.006-.05 3.982-.22 5.922-.506 1.978-.29 3.348-2.023 3.348-3.97V6.741c0-1.947-1.37-3.68-3.348-3.97A49.145 49.145 0 0 0 12 2.25ZM8.25 8.625a1.125 1.125 0 1 0 0 2.25 1.125 1.125 0 0 0 0-2.25Zm2.625 1.125a1.125 1.125 0 1 1 2.25 0 1.125 1.125 0 0 1-2.25 0Zm4.875-1.125a1.125 1.125 0 1 0 0 2.25 1.125 1.125 0 0 0 0-2.25Z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="card-info">
                        <div class="card-label"><?= $lang['dash_comments'] ?></div>
                        <div class="card-value"><?= number_format($totalComments) ?></div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($userRole === 'Editor'): ?>
                <!-- Total Pages (CMS) Card -->
                <div class="stat-card-new horizontal" style="flex: 1; min-width: 200px;">
                    <div class="card-icon-wrapper" style="background:rgba(118,117,236,0.15);">
                        <svg style="width:20px; height:20px; color:#7675ec;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M11.47 3.84a.75.75 0 011.06 0l8.69 8.69a.75.75 0 101.06-1.06l-8.69-8.69a2.25 2.25 0 00-3.18 0l-8.69 8.69a.75.75 0 001.06 1.06l8.69-8.69z" />
                            <path d="M12 5.432l8.159 8.159c.03.03.06.058.091.086v6.198c0 1.035-.84 1.875-1.875 1.875H15a.75.75 0 01-.75-.75v-4.5a.75.75 0 00-.75-.75h-3a.75.75 0 00-.75.75v4.5a.75.75 0 01-.75.75H5.625a1.875 1.875 0 01-1.875-1.875v-6.198a2.29 2.29 0 00.091-.086L12 5.432z" />
                        </svg>
                    </div>
                    <div class="card-info">
                        <div class="card-label"><?= $lang['dash_total_pages'] ?? 'Total Website Pages' ?></div>
                        <div class="card-value"><?= number_format($totalPages) ?></div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($hasAnalyticsAccess): ?>
                <!-- Total Views Card -->
                <div class="stat-card-new horizontal" style="flex: 1; min-width: 200px;">
                    <div class="card-icon-wrapper" style="background:rgba(118,117,236,0.15);">
                        <svg style="width:20px; height:20px; color:#7675ec;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" />
                            <path fill-rule="evenodd" d="M1.323 11.447C2.811 6.976 7.028 3.75 12.001 3.75c4.97 0 9.185 3.223 10.675 7.69.12.362.12.752 0 1.113-1.487 4.471-5.705 7.697-10.677 7.697-4.97 0-9.186-3.223-10.675-7.69a1.762 1.762 0 0 1 0-1.113ZM17.25 12a5.25 5.25 0 1 1-10.5 0 5.25 5.25 0 0 1 10.5 0Z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="card-info">
                        <div class="card-label"><?= $lang['dash_total_views'] ?></div>
                        <div class="card-value"><?= formatNumberShort($totalViews12m) ?></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="middle-row">
                <?php if ($hasSystemAlerts): ?>
                <!-- System Alerts Panel -->
                <div class="dash-panel system-alerts-panel" id="tour-system-alerts">
                    <div class="panel-header">
                        <svg class="icon" style="color:#7675EC;margin-right:8px;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="20" height="20">
                            <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm8.706-1.442c1.146-.573 2.437.463 2.126 1.706l-.709 2.836.042-.02a.75.75 0 0 1 .67 1.34l-.04.022c-1.147.573-2.438-.463-2.127-1.706l.71-2.836-.042.02a.75.75 0 1 1-.671-1.34l.041-.022ZM12 9a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z" clip-rule="evenodd" />
                        </svg>
                        <h3><?= $lang['dash_system_alerts'] ?></h3>
                    </div>

                    <div class="alerts-list">
                        <?php if (empty($systemAlerts)): ?>
                            <div class="empty-alerts">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="24" height="24" style="color:#10b981; margin-bottom:12px;">
                                    <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm13.36-1.814a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z" clip-rule="evenodd" />
                                </svg>
                                <p><?= $lang['dash_no_alerts'] ?></p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($systemAlerts as $alert): ?>
                                <div class="alert-item severity-<?= $alert['severity'] ?>">
                                    <div class="alert-icon">
                                        <?php if ($alert['icon'] === 'clock'): ?>
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="18" height="18">
                                                <path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25ZM12.75 6a.75.75 0 0 0-1.5 0v6c0 .414.336.75.75.75h4.5a.75.75 0 0 0 0-1.5h-3.75V6Z" clip-rule="evenodd" />
                                            </svg>
                                        <?php elseif ($alert['icon'] === 'database'): ?>
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="18" height="18">
                                                <path d="M21 6.3c0 2.26-4.03 4.1-9 4.1s-9-1.84-9-4.1V10c0 2.26 4.03 4.1 9 4.1s9-1.84 9-4.1V6.3z" />
                                                <path d="M21 14.1c0 2.26-4.03 4.1-9 4.1s-9-1.84-9-4.1V17c0 2.26 4.03 4.1 9 4.1s9-1.84 9-4.1v-2.9z" />
                                                <path d="M12 2.2c-4.97 0-9 1.84-9 4.1s4.03 4.1 9 4.1 9-1.84 9-4.1-4.03-4.1-9-4.1z" />
                                            </svg>
                                        <?php elseif ($alert['icon'] === 'server'): ?>
                                            <!-- Server Icon -->
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="18" height="18">
                                                <path d="M2.25 13.5a8.25 8.25 0 0 1 8.25-8.25.75.75 0 0 1 .75.75v6.75H12a.75.75 0 0 1 0 1.5h-.75v6.75a.75.75 0 0 1-.75.75 8.25 8.25 0 0 1-8.25-8.25Z" />
                                                <path d="M22.5 13.5a8.25 8.25 0 0 0-8.25-8.25.75.75 0 0 0-.75.75v6.75H12a.75.75 0 0 0 0 1.5h.75v6.75a.75.75 0 0 0 .75.75 8.25 8.25 0 0 0 8.25-8.25Z" />
                                            </svg>
                                        <?php elseif ($alert['icon'] === 'document-text'): ?>
                                            <!-- Document Text Icon -->
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="18" height="18">
                                                <path fill-rule="evenodd" d="M5.625 1.5H9a3.75 3.75 0 0 1 3.75 3.75v1.875c0 1.036.84 1.875 1.875 1.875H16.5a3.75 3.75 0 0 1 3.75 3.75v7.875c0 1.035-.84 1.875-1.875 1.875H5.625a1.875 1.875 0 0 1-1.875-1.875V3.375c0-1.036.84-1.875 1.875-1.875ZM12.75 12a.75.75 0 0 0-1.5 0v2.25H9a.75.75 0 0 0 0 1.5h2.25V18a.75.75 0 0 0 1.5 0v-2.25H15a.75.75 0 0 0 0-1.5h-2.25V12Z" clip-rule="evenodd" />
                                                <path d="M14.25 5.25a5.23 5.23 0 0 0-1.279-3.434 9.768 9.768 0 0 1 6.963 6.963A5.23 5.23 0 0 0 16.5 7.5h-1.875a.375.375 0 0 1-.375-.375V5.25Z" />
                                            </svg>
                                        <?php elseif ($alert['icon'] === 'eye-off'): ?>
                                            <!-- Eye Off Icon -->
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="18" height="18">
                                                <path d="M3.53 2.47a.75.75 0 0 0-1.06 1.06l18 18a.75.75 0 1 0 1.06-1.06l-18-18ZM22.676 12.553a11.249 11.249 0 0 1-2.631 4.31l-3.099-3.099a5.25 5.25 0 0 0-6.71-6.71L7.759 4.577a11.217 11.217 0 0 1 4.242-.827c4.97 0 9.185 3.223 10.675 7.69.12.362.12.752 0 1.113Z" />
                                                <path d="M15.75 12c0 .18-.013.357-.037.53l-4.244-4.243A3.75 3.75 0 0 1 15.75 12ZM12.53 15.713l-4.243-4.244a3.75 3.75 0 0 0 4.244 4.243Z" />
                                            </svg>
                                        <?php elseif ($alert['icon'] === 'archive'): ?>
                                            <!-- Archive Box Icon -->
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="18" height="18">
                                                <path d="M3.375 3C2.339 3 1.5 3.84 1.5 4.875v.75c0 1.036.84 1.875 1.875 1.875h17.25c1.035 0 1.875-.84 1.875-1.875v-.75C22.5 3.839 21.66 3 20.625 3H3.375Z" />
                                                <path fill-rule="evenodd" d="M3.087 9l.54 9.176A3 3 0 0 0 6.62 21h10.757a3 3 0 0 0 2.995-2.824L20.913 9H3.087Zm6.163 3.75A.75.75 0 0 1 10 12h4a.75.75 0 0 1 0 1.5h-4a.75.75 0 0 1-.75-.75Z" clip-rule="evenodd" />
                                            </svg>
                                        <?php elseif ($alert['icon'] === 'globe'): ?>
                                            <!-- Globe Icon -->
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="18" height="18">
                                                <path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25ZM6.03 8.25a.75.75 0 0 1 .651.375 12.015 12.015 0 0 0 2.018 2.502.75.75 0 0 1-.703 1.25 13.515 13.515 0 0 1-2.22-2.751.75.75 0 0 1 .255-1.376Z" clip-rule="evenodd" />
                                                <path fill-rule="evenodd" d="M11.25 12a.75.75 0 0 1 .75-.75h.008a.75.75 0 0 1 .75.75v3a.75.75 0 0 1-.75.75h-.008a.75.75 0 0 1-.75-.75v-3Z" clip-rule="evenodd" />
                                                <path d="M12.375 9a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z" />
                                            </svg>
                                        <?php else: ?>
                                            <!-- Default Document Icon -->
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="18" height="18">
                                                <path fill-rule="evenodd" d="M5.625 1.5c-1.036 0-1.875.84-1.875 1.875v17.25c0 1.035.84 1.875 1.875 1.875h12.75c1.035 0 1.875-.84 1.875-1.875V12.75A3.75 3.75 0 0 0 16.5 9h-1.875V1.5H5.625ZM7.5 15a.75.75 0 0 1 .75-.75h7.5a.75.75 0 0 1 0 1.5h-7.5A.75.75 0 0 1 7.5 15Zm.75 2.25a.75.75 0 0 0 0 1.5H12a.75.75 0 0 0 0-1.5H8.25Z" clip-rule="evenodd" />
                                                <path d="M12.971 1.816A5.23 5.23 0 0 1 14.25 5.25v1.875c0 .207.168.375.375.375H16.5a5.23 5.23 0 0 1 3.434 1.279 9.768 9.768 0 0 0-6.963-6.963Z" />
                                            </svg>
                                        <?php endif; ?>
                                    </div>
                                    <div class="alert-content">
                                        <p class="alert-text"><?= htmlspecialchars($alert['title']) ?></p>
                                        <a href="<?= $alert['link'] ?>" class="alert-cta"><?= $alert['cta'] ?></a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>


                <?php if ($hasBlogAccess): ?>
                <!-- Recent Posts (Preserved Style) -->
                <div class="dash-panel" id="tour-recent-posts">
                    <div class="panel-header">
                        <svg class="icon" style="color:#7675EC;margin-right:8px;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="20" height="20">
                            <path fill-rule="evenodd" d="M4.125 3C3.089 3 2.25 3.84 2.25 4.875V18a3 3 0 0 0 3 3h15a3 3 0 0 1-3-3V4.875C17.25 3.839 16.41 3 15.375 3H4.125ZM12 9.75a.75.75 0 0 0 0 1.5h1.5a.75.75 0 0 0 0-1.5H12Zm-.75-2.25a.75.75 0 0 1 .75-.75h1.5a.75.75 0 0 1 0 1.5H12a.75.75 0 0 1-.75-.75ZM6 12.75a.75.75 0 0 0 0 1.5h7.5a.75.75 0 0 0 0-1.5H6Zm-.75 3.75a.75.75 0 0 1 .75-.75h7.5a.75.75 0 0 1 0 1.5H6a.75.75 0 0 1-.75-.75ZM6 6.75a.75.75 0 0 0-.75.75v3c0 .414.336.75.75.75h3a.75.75 0 0 0 .75-.75v-3A.75.75 0 0 0 9 6.75H6Z" clip-rule="evenodd" />
                            <path d="M18.75 6.75h1.875c.621 0 1.125.504 1.125 1.125V18a1.5 1.5 0 0 1-3 0V6.75Z" />
                        </svg>
                        <h3 style="font-weight:600; font-size:1.1rem; color:#fff; margin:0;"><?= $lang['dash_recent_posts'] ?></h3>
                    </div>
                    <ul class="dash-list">
                        <?php
                        $r = 1;
                        foreach ($recentPostsArray as $rp):
                            $postId = (string) $rp->_id;
                            ?>
                            <li class="dash-list-item">
                                <a href="../blog.php?id=<?= $postId ?>" class="dash-list-link" target="_blank">
                                    <span style="display:flex;align-items:center;">
                                        <span class="rank-badge"><?= $r++ ?></span>
                                        <span class="item-title">
                                            <?php
                                            $titleFull = $rp->title_de ?? $rp->title_en ?? 'Untitled';
                                            $parts = explode(' ', $titleFull);
                                            echo htmlspecialchars(count($parts) > 2 ? implode(' ', array_slice($parts, 0, 2)) . '...' : $titleFull);
                                            ?>
                                        </span>
                                    </span>
                                    <span class="item-meta">
                                        <?php $dtr = $rp->created_at->toDateTime();
                                        $dtr->setTimezone(new DateTimeZone('Europe/Berlin'));
                                        echo $dtr->format('M d'); ?>
                                    </span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- Most Viewed Posts (Preserved Style) -->
                <div class="dash-panel" id="panel-most-viewed">
                    <div class="panel-header" style="margin-bottom:24px; display:flex; align-items:center; gap:10px;">
                        <svg style="width:20px; height:20px; color:#7675EC;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" />
                            <path fill-rule="evenodd" d="M1.323 11.447C2.811 6.976 7.028 3.75 12.001 3.75c4.97 0 9.185 3.223 10.675 7.69.12.362.12.752 0 1.113-1.487 4.471-5.705 7.697-10.677 7.697-4.97 0-9.186-3.223-10.675-7.69a1.762 1.762 0 0 1 0-1.113ZM17.25 12a5.25 5.25 0 1 1-10.5 0 5.25 5.25 0 0 1 10.5 0Z" clip-rule="evenodd" />
                        </svg>
                        <h3 style="font-weight:600; font-size:1.1rem; color:#fff; margin:0;"><?= $lang['dash_most_viewed'] ?></h3>
                    </div>
                    <div class="top-posts-list">
                        <?php
                        if (empty($topPostsArray)):
                            ?>
                            <div style="text-align:center;color:rgba(255,255,255,0.5);padding:32px 0;"><?= $lang['dash_no_views'] ?></div>
                        <?php
                        else:
                            $maxViews = $topPostsArray[0]->views ?? 1;
                            $rank = 1;
                            foreach ($topPostsArray as $tp):
                                $postId = (string) $tp->_id;
                                $views = $tp->views ?? 0;
                                $percentage = $maxViews > 0 ? ($views / $maxViews) * 100 : 0;
                                ?>
                                <div class="top-post-item" style="margin-bottom:20px;">
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                                        <a href="../blog.php?id=<?= $postId ?>" target="_blank" style="text-decoration:none; color:#fff; display:flex; align-items:center; gap:12px; flex:1;">
                                            <span style="color:#6b7280; font-size:0.9rem; font-weight:600; min-width:20px;"><?= $rank ?>.</span>
                                            <span style="font-size:1rem; font-weight:500; color:#fff;">
                                                <?php
                                                $titleFull = $tp->title_de ?? $tp->title_en ?? $tp->title ?? 'Untitled';
                                                $parts = explode(' ', $titleFull);
                                                echo htmlspecialchars(count($parts) > 2 ? implode(' ', array_slice($parts, 0, 2)) . '...' : $titleFull);
                                                ?>
                                            </span>
                                        </a>
                                        <span style="font-size:0.8rem; color:#9ca3af; font-weight:500;"><?= number_format($views) ?> <?= $lang['chart_views_label'] ?></span>
                                    </div>
                                    <div style="width:100%; height:4px; background:#1a1a1a; border-radius:10px; overflow:hidden;">
                                        <div style="width:<?= $percentage ?>%; height:100%; background:#7675EC; border-radius:10px; transition:width 0.3s ease;"></div>
                                    </div>
                                </div>
                            <?php $rank++;
                            endforeach;
                        endif; ?>
                    </div>
                </div>

                <!-- Scheduled Posts (Preserved Style) -->
                <div class="dash-panel" id="tour-scheduled-posts">
                    <div class="panel-header">
                        <svg class="icon" style="color:#7675EC;margin-right:8px;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="20" height="20">
                            <path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25ZM12.75 6a.75.75 0 0 0-1.5 0v6c0 .414.336.75.75.75h4.5a.75.75 0 0 0 0-1.5h-3.75V6Z" clip-rule="evenodd" />
                        </svg>
                        <h3 style="font-weight:600; font-size:1.1rem; color:#fff; margin:0;"><?= $lang['dash_scheduled_posts'] ?></h3>
                    </div>
                    <ul class="dash-list">
                        <?php
                        // Load scheduled posts from snippet (assume it sets $scheduledPostsArray)
                        $scheduledPostsArray = [];
                        ob_start();
                        include __DIR__ . '/dashboard_scheduled_posts_snippet.php';
                        $scheduledPostsHtml = ob_get_clean();
                        // Remove duplicate header/icon if present
                        $scheduledPostsHtml = preg_replace('/<div[^>]*panel-header[^>]*>.*?<\/div>/is', '', $scheduledPostsHtml);
                        $scheduledPostsHtml = preg_replace('/<h3[^>]*>.*?Scheduled Posts.*?<\/h3>/is', '', $scheduledPostsHtml);
                        if (!empty(trim($scheduledPostsHtml))) {
                            echo $scheduledPostsHtml;
                        } else {
                            echo '<div style="text-align:center;color:rgba(255,255,255,0.3);padding:32px 0;font-size:0.9rem;">' . $lang['dash_no_scheduled'] . '</div>';
                        }
                        ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($hasAnalyticsAccess): ?>
            <!-- CHART SECTION (Full Width Row) -->
            <div class="chart-row" style="margin-bottom:32px;">
                <div class="modern-dashboard-fullwidth" id="tour-traffic-chart" style="width:100%;max-width:100vw;margin:0 auto;">
                    <div class="grouped-card"
                        style="margin-bottom:32px;width:100%;box-sizing:border-box; background: #262525; border-radius: 16px; padding: 32px;">
                        <div style="width:100%;">
                            <div class="modern-chart-header">
                                <div>
                                    <div class="modern-chart-value">
                                        <span id="total-views-count" style="background: linear-gradient(135deg, #6366f1 0%, #d225d7 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; color: #d225d7;"><?= number_format($totalViews) ?></span>
                                        <span class="views-label"><?= $lang['chart_views_label'] ?></span>
                                    </div>
                                </div>
                                <div class="modern-filter-bar-row">
                                    <div class="modern-filter-bar-group">
                                        <button class="chart-filter-btn" data-range="12m">
                                            <span class="label-desktop"><?= $lang['chart_time_12m'] ?></span>
                                            <span class="label-mobile"><?= $lang['chart_time_12m_short'] ?></span>
                                        </button>
                                        <button class="chart-filter-btn" data-range="30d">
                                            <span class="label-desktop"><?= $lang['chart_time_30d'] ?></span>
                                            <span class="label-mobile"><?= $lang['chart_time_30d_short'] ?></span>
                                        </button>
                                        <button class="chart-filter-btn active" data-range="7d">
                                            <span class="label-desktop"><?= $lang['chart_time_7d'] ?></span>
                                            <span class="label-mobile"><?= $lang['chart_time_7d_short'] ?></span>
                                        </button>
                                        <button class="chart-filter-btn" data-range="24h">
                                            <span class="label-desktop"><?= $lang['chart_time_24h'] ?></span>
                                            <span class="label-mobile"><?= $lang['chart_time_24h_short'] ?></span>
                                        </button>
                                    </div>
                                    <div style="position:relative; display:inline-block;">
                                        <button class="chart-filter-btn filter-btn" id="filtersToggleBtn">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                fill="currentColor"
                                                style="width:18px;height:18px;vertical-align:middle;">
                                                <path
                                                    d="M18.75 12.75h1.5a.75.75 0 0 0 0-1.5h-1.5a.75.75 0 0 0 0 1.5ZM12 6a.75.75 0 0 1 .75-.75h7.5a.75.75 0 0 1 0 1.5h-7.5A.75.75 0 0 1 12 6ZM12 18a.75.75 0 0 1 .75-.75h7.5a.75.75 0 0 1 0 1.5h-7.5A.75.75 0 0 1 12 18ZM3.75 6.75h1.5a.75.75 0 1 0 0-1.5h-1.5a.75.75 0 0 0 0 1.5ZM5.25 18.75h-1.5a.75.75 0 0 1 0-1.5h1.5a.75.75 0 0 1 0 1.5ZM3 12a.75.75 0 0 1 .75-.75h7.5a.75.75 0 0 1 0 1.5h-7.5A.75.75 0 0 1 3 12ZM9 3.75a2.25 2.25 0 1 0 0 4.5 2.25 2.25 0 0 0 0-4.5ZM12.75 12a2.25 2.25 0 1 1 4.5 0 2.25 2.25 0 0 1-4.5 0ZM9 15.75a2.25 2.25 0 1 0 0 4.5 2.25 2.25 0 0 0 0-4.5Z" />
                                            </svg>
                                            <?= $lang['chart_filters'] ?>
                                        </button>
                                        <div id="filtersDropdown" class="filters-dropdown"
                                            style="top:calc(100% + 8px); right:0; min-width:200px; display:none;">
                                            <div class="filter-section">
                                                <label class="custom-radio-option">
                                                    <input type="radio" name="viewSource" value="blog">
                                                    <div class="option-content">
                                                        <span class="radio-mark"></span>
                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                            fill="currentColor" class="option-icon">
                                                            <path fill-rule="evenodd"
                                                                d="M4.125 3C3.089 3 2.25 3.84 2.25 4.875V18a3 3 0 0 0 3 3h15a3 3 0 0 1-3-3V4.875C17.25 3.839 16.41 3 15.375 3H4.125ZM12 9.75a.75.75 0 0 0 0 1.5h1.5a.75.75 0 0 0 0-1.5H12Zm-.75-2.25a.75.75 0 0 1 .75-.75h1.5a.75.75 0 0 1 0 1.5H12a.75.75 0 0 1-.75-.75ZM6 12.75a.75.75 0 0 0 0 1.5h7.5a.75.75 0 0 0 0-1.5H6Zm-.75 3.75a.75.75 0 0 1 .75-.75h7.5a.75.75 0 0 1 0 1.5H6a.75.75 0 0 1-.75-.75ZM6 6.75a.75.75 0 0 0-.75.75v3c0 .414.336.75.75.75h3a.75.75 0 0 0 .75-.75v-3A.75.75 0 0 0 9 6.75H6Z"
                                                                clip-rule="evenodd" />
                                                            <path
                                                                d="M18.75 6.75h1.875c.621 0 1.125.504 1.125 1.125V18a1.5 1.5 0 0 1-3 0V6.75Z" />
                                                        </svg>
                                                        <span class="radio-label"><?= $lang['chart_source_blog'] ?></span>
                                                    </div>
                                                </label>
                                                <label class="custom-radio-option">
                                                    <input type="radio" name="viewSource" value="main">
                                                    <div class="option-content">
                                                        <span class="radio-mark"></span>
                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                            fill="currentColor" class="option-icon">
                                                            <path
                                                                d="M21.721 12.752a9.711 9.711 0 0 0-.945-5.003 12.754 12.754 0 0 1-4.339 2.708 18.991 18.991 0 0 1-.214 4.772 17.165 17.165 0 0 0 5.498-2.477ZM14.634 15.55a17.324 17.324 0 0 0 .332-4.647c-.952.227-1.945.347-2.966.347-1.021 0-2.014-.12-2.966-.347a17.515 17.515 0 0 0 .332 4.647 17.385 17.385 0 0 0 5.268 0ZM9.772 17.119a18.963 18.963 0 0 0 4.456 0A17.182 17.182 0 0 1 12 21.724a17.18 17.18 0 0 1-2.228-4.605ZM7.777 15.23a18.87 18.87 0 0 1-.214-4.774 12.753 12.753 0 0 1-4.34-2.708 9.711 9.711 0 0 0-.944 5.004 17.165 17.165 0 0 0 5.498 2.477ZM21.356 14.752a9.765 9.765 0 0 1-7.478 6.817 18.64 18.64 0 0 0 1.988-4.718 18.627 18.627 0 0 0 5.49-2.098ZM2.644 14.752c1.682.971 3.53 1.688 5.49 2.099a18.64 18.64 0 0 0 1.988 4.718 9.765 9.765 0 0 1-7.478-6.816ZM13.878 2.43a9.755 9.755 0 0 1 6.116 3.986 11.267 11.267 0 0 1-3.746 2.504 18.63 18.63 0 0 0-2.37-6.49ZM12 2.276a17.152 17.152 0 0 1 2.805 7.121c-.897.23-1.837.353-2.805.353-.968 0-1.908-.122-2.805-.353A17.151 17.151 0 0 1 12 2.276ZM10.122 2.43a18.629 18.629 0 0 0-2.37 6.49 11.266 11.266 0 0 1-3.746-2.504 9.754 9.754 0 0 1 6.116-3.985Z" />
                                                        </svg>
                                                        <span class="radio-label"><?= $lang['chart_source_main'] ?></span>
                                                    </div>
                                                </label>
                                                <label class="custom-radio-option">
                                                    <input type="radio" name="viewSource" value="both" checked>
                                                    <div class="option-content">
                                                        <span class="radio-mark"></span>
                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                            fill="currentColor" class="option-icon" style="overflow: visible;">
                                                            <defs>
                                                                <linearGradient id="purplePink" x1="0%" y1="0%" x2="100%" y2="100%">
                                                                    <stop offset="0%" style="stop-color:#6366f1;stop-opacity:1" />
                                                                    <stop offset="100%" style="stop-color:#d225d7;stop-opacity:1" />
                                                                </linearGradient>
                                                            </defs>
                                                            <path
                                                                d="M11.644 1.59a.75.75 0 0 1 .712 0l9.75 5.25a.75.75 0 0 1 0 1.32l-9.75 5.25a.75.75 0 0 1-.712 0l-9.75-5.25a.75.75 0 0 1 0-1.32l9.75-5.25Z" />
                                                            <path
                                                                d="m3.265 10.602 7.668 4.129a2.25 2.25 0 0 0 2.134 0l7.668-4.13 1.37.739a.75.75 0 0 1 0 1.32l-9.75 5.25a.75.75 0 0 1-.71 0l-9.75-5.25a.75.75 0 0 1 0-1.32l1.37-.738Z" />
                                                            <path
                                                                d="m10.933 19.231-7.668-4.13-1.37.739a.75.75 0 0 0 0 1.32l9.75 5.25c.221.12.489.12.71 0l9.75-5.25a.75.75 0 0 0 0-1.32l-1.37-.738-7.668 4.13a2.25 2.25 0 0 1-2.134-.001Z" />
                                                        </svg>
                                                        <span class="radio-label"><?= $lang['chart_source_both'] ?></span>
                                                    </div>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Chart Canvas -->
                            <div class="modern-chart-canvas" style="width:100%;height:320px;">
                                <canvas id="trafficChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>




    <!-- CHART SCRIPT -->
    <!-- DEBUG: Traffic Data = <?php echo json_encode($trafficData); ?> -->
    <!-- DEBUG: Labels = <?php echo json_encode($chartLabels); ?> -->
    <!-- GLOBAL SEARCH -->
    <script src="assets/js/global-search.js?v=<?= time() ?>"></script>

    <script>
        // Chart datasets: blog and main (main uses optional collection `main_views`)
        const chartDatasetsBlog = {
            '7d': { labels: <?php echo json_encode($chart7d['labels']); ?>, data: <?php echo json_encode($chart7d['data']); ?> },
            '30d': { labels: <?php echo json_encode($chart30d['labels']); ?>, data: <?php echo json_encode($chart30d['data']); ?> },
            '12m': { labels: <?php echo json_encode($chart12m['labels']); ?>, data: <?php echo json_encode($chart12m['data']); ?> },
            '24h': { labels: <?php echo json_encode($chart24h['labels']); ?>, data: <?php echo json_encode($chart24h['data']); ?> }
        };

        const chartDatasetsMain = {
            '7d': { labels: <?php echo json_encode($chart7d_main['labels']); ?>, data: <?php echo json_encode($chart7d_main['data']); ?> },
            '30d': { labels: <?php echo json_encode($chart30d_main['labels']); ?>, data: <?php echo json_encode($chart30d_main['data']); ?> },
            '12m': { labels: <?php echo json_encode($chart12m_main['labels']); ?>, data: <?php echo json_encode($chart12m_main['data']); ?> },
            '24h': { labels: <?php echo json_encode($chart24h_main['labels']); ?>, data: <?php echo json_encode($chart24h_main['data']); ?> }
        };

        // Chart.js setup
        const ctx = document.getElementById('trafficChart').getContext('2d');
        const totalDisplay = document.getElementById('total-views-count');
        const blogColor = '#6366f1';
        const mainColor = '#d225d7';

        function createGradient(color) {
            let g = ctx.createLinearGradient(0, 0, 0, 300);
            g.addColorStop(0, color.replace('#', 'rgba(').replace(')', '') + ',0.45)');
            // fallback - simple semi-transparent
            try { g = ctx.createLinearGradient(0, 0, 0, 300); } catch (e) { }
            return g;
        }

        // Helper to combine two arrays elementwise
        function combineArrays(a, b) {
            const len = Math.max(a.length, b.length);
            const out = new Array(len).fill(0);
            for (let i = 0; i < len; i++) {
                out[i] = (a[i] || 0) + (b[i] || 0);
            }
            return out;
        }

        // initial dataset - 12m and default to BOTH views
        let activeRange = '7d';
        let activeSource = 'both';
        const initialLabels = chartDatasetsBlog[activeRange].labels;

        function hexToRgba(hex, alpha) {
            const h = hex.replace('#', '');
            const bigint = parseInt(h, 16);
            const r = (bigint >> 16) & 255;
            const g = (bigint >> 8) & 255;
            const b = bigint & 255;
            return `rgba(${r}, ${g}, ${b}, ${alpha})`;
        }

        function datasetFor(label, data, color) {
            // Create gradient fill for the chart
            const gradient = ctx.createLinearGradient(0, 0, 0, 320);
            gradient.addColorStop(0, hexToRgba(color, 0.4));
            gradient.addColorStop(0.5, hexToRgba(color, 0.15));
            gradient.addColorStop(1, hexToRgba(color, 0));

            return {
                label: label,
                data: data,
                borderColor: color,
                backgroundColor: gradient,
                borderWidth: 2.5,
                tension: 0.4,
                fill: true,
                pointRadius: 0,
                pointHoverRadius: 6,
                pointHoverBackgroundColor: '#ffffff',
                pointHoverBorderColor: color,
                pointHoverBorderWidth: 3
            };
        }

        // Build initial datasets depending on activeSource
        let initialDatasets = [];
        if (activeSource === 'both') {
            initialDatasets.push(datasetFor('Blog Views', chartDatasetsBlog[activeRange].data, blogColor));
            initialDatasets.push(datasetFor('Main Views', chartDatasetsMain[activeRange].data, mainColor));
        } else if (activeSource === 'main') {
            initialDatasets.push(datasetFor('Main Views', chartDatasetsMain[activeRange].data, mainColor));
        } else {
            initialDatasets.push(datasetFor('Blog Views', chartDatasetsBlog[activeRange].data, blogColor));
        }

        // Custom plugin for vertical hover line
        const verticalLinePlugin = {
            id: 'verticalLine',
            afterDraw: function (chart) {
                const activeElements = chart.getActiveElements();
                if (activeElements && activeElements.length > 0) {
                    const activePoint = activeElements[0];
                    const ctx = chart.ctx;
                    const x = activePoint.element.x;
                    const topY = chart.scales.y.top;
                    const bottomY = chart.scales.y.bottom;

                    // Check if both datasets are shown
                    let lineColor;
                    if (chart.data.datasets.length > 1) {
                        // Create gradient for both views
                        const gradient = ctx.createLinearGradient(0, topY, 0, bottomY);
                        gradient.addColorStop(0, '#7675ec');
                        gradient.addColorStop(1, '#d225d7');
                        lineColor = gradient;
                    } else {
                        // Single dataset - use its color
                        const datasetIndex = activePoint.datasetIndex;
                        lineColor = chart.data.datasets[datasetIndex].borderColor;
                    }

                    ctx.save();
                    ctx.beginPath();
                    ctx.moveTo(x, topY);
                    ctx.lineTo(x, bottomY);
                    ctx.lineWidth = 2;
                    ctx.strokeStyle = lineColor;
                    ctx.setLineDash([6, 6]);
                    ctx.stroke();
                    ctx.restore();
                }
            }
        };

        let trafficChart = new Chart(ctx, {
            type: 'line',
            plugins: [verticalLinePlugin],
            data: {
                labels: initialLabels,
                datasets: initialDatasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        enabled: true,
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            title: function (context) {
                                return context[0].label;
                            },
                            label: function (context) {
                                let value = context.parsed.y;
                                return '<?= $lang['chart_views_label'] ?> : ' + value;
                            },
                            labelTextColor: function (context) {
                                return context.dataset.borderColor;
                            }
                        },
                        backgroundColor: '#1a1a1a',
                        titleColor: '#fff',
                        titleFont: { size: 14, weight: 'bold' },
                        bodyFont: { size: 13 },
                        borderColor: '#333',
                        borderWidth: 1,
                        padding: 12,
                        displayColors: false,
                        caretSize: 6
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false,
                            drawTicks: true,
                            tickColor: '#444'
                        },
                        ticks: { 
                            color: '#9ca3af',
                            padding: 12,
                            autoSkip: true,
                            maxRotation: 0
                        },
                        border: { 
                            display: true,
                            color: '#444'
                        }
                    },
                    y: {
                        min: 0,
                        border: { 
                            display: true,
                            color: '#444'
                        },
                        grid: {
                            display: false,
                            drawTicks: true,
                            tickColor: '#444'
                        },
                        ticks: {
                            color: '#9ca3af',
                            padding: 12,
                            stepSize: 1,
                            callback: function (value) {
                                return (Number.isInteger(value) && value >= 0) ? value : '';
                            }
                        }
                    }
                }
            }
        });

        // Adjust chart appearance for small screens: padding, legend visibility, and tooltip sizing
        let adaptTimeout = null;
        let lastBreakpoint = null;
        function adaptChartForScreen() {
            if (adaptTimeout) {
                clearTimeout(adaptTimeout);
            }
            
            adaptTimeout = setTimeout(() => {
                try {
                    const w = window.visualViewport ? window.visualViewport.width : window.innerWidth;
                    let breakpoint = 'desktop';
                    if (w <= 640) breakpoint = 'mobile';
                    else if (w <= 900) breakpoint = 'tablet';

                    // Only update if breakpoint changed to avoid loops and jitter
                    if (breakpoint === lastBreakpoint) return;
                    lastBreakpoint = breakpoint;

                    // Deep clean options to avoid proxy loops
                    const opt = trafficChart.options;
                    if (!opt.layout) opt.layout = {};
                    if (!opt.plugins) opt.plugins = {};
                    if (!opt.plugins.legend) opt.plugins.legend = {};
                    if (!opt.plugins.tooltip) opt.plugins.tooltip = {};

                    if (breakpoint === 'mobile') {
                        opt.layout.padding = { top: 6, right: 8, bottom: 6, left: 6 };
                        opt.plugins.legend.display = false;
                        opt.plugins.tooltip.padding = 8;
                        // Reduce axis tick paddings and font sizes on mobile to save left space
                        if (!opt.scales) opt.scales = {};
                        if (!opt.scales.y) opt.scales.y = {};
                        if (!opt.scales.y.ticks) opt.scales.y.ticks = {};
                        if (!opt.scales.x) opt.scales.x = {};
                        if (!opt.scales.x.ticks) opt.scales.x.ticks = {};
                        opt.scales.y.ticks.padding = 6;
                        opt.scales.y.ticks.font = Object.assign({}, opt.scales.y.ticks.font || {}, { size: 11 });
                        opt.scales.x.ticks.padding = 6;
                        opt.scales.x.ticks.font = Object.assign({}, opt.scales.x.ticks.font || {}, { size: 11 });
                        // For very small phones, hide y-axis labels to reclaim left space
                        const smallPhone = (window.visualViewport ? window.visualViewport.width : window.innerWidth) <= 420;
                        if (smallPhone) {
                            opt.layout.padding.left = 4;
                            opt.scales.y.ticks.display = false;
                            opt.scales.y.grid.drawTicks = false;
                            opt.scales.y.beginAtZero = true;
                        } else {
                            opt.scales.y.ticks.display = true;
                        }
                    } else if (breakpoint === 'tablet') {
                        opt.layout.padding = { top: 10, right: 12, bottom: 10, left: 12 };
                        opt.plugins.legend.display = false;
                        opt.plugins.tooltip.padding = 12;
                    } else {
                        opt.layout.padding = { top: 12, right: 16, bottom: 12, left: 16 };
                        opt.plugins.legend.display = false;
                        opt.plugins.tooltip.padding = 12;
                    }
                    
                    if (opt.scales && opt.scales.x) {
                        opt.scales.x.ticks.autoSkip = true;
                        opt.scales.x.ticks.maxRotation = (breakpoint === 'mobile') ? 0 : 45;
                    }
                    
                    trafficChart.update('none');
                } catch (e) { 
                    console.warn('adaptChartForScreen error', e); 
                }
            }, 100); 
        }

        // call once and on resize/orientation change
        adaptChartForScreen();
        let resizeTimeout;
        window.addEventListener('resize', function () { 
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(adaptChartForScreen, 150);
        });
        if (window.visualViewport) {
            window.visualViewport.addEventListener('resize', function() {
                clearTimeout(resizeTimeout);
                resizeTimeout = setTimeout(adaptChartForScreen, 150);
            });
        }

        // Update helper
        function updateChartFor(range, source) {
            let labels = chartDatasetsBlog[range].labels;
            let datasets = [];
            let rangeTotal = 0;

            if (source === 'blog') {
                const data = chartDatasetsBlog[range].data;
                datasets.push(datasetFor('Blog Views', data, blogColor));
                rangeTotal = data.reduce((a, b) => a + b, 0);
            } else if (source === 'main') {
                const data = chartDatasetsMain[range].data;
                datasets.push(datasetFor('Main Views', data, mainColor));
                rangeTotal = data.reduce((a, b) => a + b, 0);
            } else if (source === 'both') {
                const blogData = chartDatasetsBlog[range].data;
                const mainData = chartDatasetsMain[range].data;
                datasets.push(datasetFor('Blog Views', blogData, blogColor));
                datasets.push(datasetFor('Main Views', mainData, mainColor));
                rangeTotal = blogData.reduce((a, b) => a + b, 0) + mainData.reduce((a, b) => a + b, 0);
            }

            // Update chart labels and datasets
            trafficChart.data.labels = labels;
            trafficChart.data.datasets = datasets;
            trafficChart.update();

            // Update Total Views text to reflect the selected RANGE
            if (totalDisplay) {
                totalDisplay.innerText = rangeTotal.toLocaleString();
                
                // Update color based on source
                totalDisplay.style.background = 'none';
                totalDisplay.style.webkitBackgroundClip = 'none';
                totalDisplay.style.webkitTextFillColor = 'initial';
                
                if (source === 'blog') {
                    totalDisplay.style.color = '#7675ec'; // Matches button purple
                } else if (source === 'main') {
                    totalDisplay.style.color = '#d225d7';
                } else if (source === 'both') {
                    totalDisplay.style.background = 'linear-gradient(135deg, #6366f1 0%, #d225d7 100%)';
                    totalDisplay.style.webkitBackgroundClip = 'text';
                    totalDisplay.style.webkitTextFillColor = 'transparent';
                    totalDisplay.style.color = '#d225d7'; // Fallback
                }
            }
            updateFilterButtonStyle(source);
        }

        function updateFilterButtonStyle(source) {
            document.querySelectorAll('.chart-filter-btn').forEach(b => {
                b.style.background = '';
                b.style.color = '';
                // Ensure we clean up any transition artifacts if previously set
                b.style.transition = 'background 0.3s ease'; 
            });
            
            const activeBtn = document.querySelector(`.chart-filter-btn[data-range="${activeRange}"]`);
            if (activeBtn) {
                if (source === 'blog') {
                    activeBtn.style.background = '#7675ec';
                    activeBtn.style.color = '#fff';
                } else if (source === 'main') {
                    activeBtn.style.background = '#d225d7';
                    activeBtn.style.color = '#fff';
                } else {
                    activeBtn.style.background = 'linear-gradient(135deg, #6366f1 0%, #d225d7 100%)';
                    activeBtn.style.color = '#fff';
                }
            }
        }

        // Range buttons
        document.querySelectorAll('.chart-filter-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                if (this.hasAttribute('data-range')) {
                    document.querySelectorAll('.chart-filter-btn[data-range]').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    activeRange = this.getAttribute('data-range');
                    const checked = document.querySelector('input[name="viewSource"]:checked');
                    activeSource = checked ? checked.value : 'blog';
                    updateChartFor(activeRange, activeSource);
                }
            });
        });

        // View source radios
        document.querySelectorAll('input[name="viewSource"]').forEach(r => {
            r.addEventListener('change', function () {
                activeSource = this.value;
                updateChartFor(activeRange, activeSource);
                // close dropdown if open
                const dd = document.getElementById('filtersDropdown');
                if (dd) dd.style.display = 'none';
            });
        });

        // Filters dropdown logic
        const filtersBtn = document.getElementById('filtersToggleBtn');
        const filtersDropdown = document.getElementById('filtersDropdown');
        if (filtersBtn && filtersDropdown) {
            filtersBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                filtersDropdown.style.display = (filtersDropdown.style.display === 'none' || filtersDropdown.style.display === '') ? 'block' : 'none';
            });
            document.addEventListener('click', function (e) {
                if (!filtersDropdown.contains(e.target) && e.target !== filtersBtn) {
                    filtersDropdown.style.display = 'none';
                }
            });
        }
    </script>
    <?php
    // Read scheduled publish status for debugging console output
    $statusFile = __DIR__ . '/blog/.last_scheduled_status.json';
    $scheduleStatus = null;
    if (file_exists($statusFile)) {
        $raw = @file_get_contents($statusFile);
        $scheduleStatus = json_decode($raw, true);
    }
    ?>

    <style>
        .modern-dashboard-fullwidth {
            width: 100%;
            max-width: 100vw;
            margin: 0 auto;
            margin-top: 32px;
        }

        .modern-chart-panel.fullwidth {
            background: #232323;
            border-radius: 16px;
            padding: 32px 32px 24px 32px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
            width: 100%;
            box-sizing: border-box;
        }

        .modern-chart-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 32px;
        }

        @media (max-width: 900px) {
            .modern-chart-header {
                flex-direction: column;
                gap: 16px;
            }

            .modern-filter-bar-row {
                width: 100%;
                justify-content: space-between;
            }
            
            /* On very small screens, allow filter bar to wrap if needed */
            @media (max-width: 480px) {
                .modern-filter-bar-row {
                    flex-wrap: wrap;
                }
            }
        }

        .modern-chart-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #fff;
            margin-bottom: 8px;
        }

        .modern-chart-value {
            font-size: 2.2rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 2px;
        }

        .modern-chart-growth {
            font-size: 1rem;
            color: #7c3aed;
            font-weight: 600;
            margin-left: 8px;
        }

        .modern-filter-bar-row {
            display: flex;
            gap: 12px;
            align-items: center;
            margin-top: 4px;
        }

        .modern-filter-bar-group {
            display: flex;
            gap: 4px;
            background: #232323;
            border-radius: 12px;
            padding: 4px 12px;
            border: 1px solid #333;
        }

        /* Filter styles moved to dashboard.css */
        .chart-filter-btn {
            background: none;
            border: 1px solid transparent;
            color: #9ca3af;
            font-weight: 500;
            font-size: 0.95rem;
            padding: 0 16px;
            height: 38px;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.2s, color 0.2s, box-shadow 0.2s, border-color 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            white-space: nowrap;
        }

        .chart-filter-btn.active {
            background: linear-gradient(135deg, #7675ec 0%, #d225d7 100%);
            color: #fff;
            border: none;
            box-shadow: 0 2px 8px rgba(118, 117, 236, 0.3);
            border-radius: 8px;
        }

        .chart-filter-btn:not(.active):hover {
            color: #fff;
            background: #232323;
        }

        .modern-chart-canvas {
            width: 100%;
            height: auto;
            aspect-ratio: 16/9;
            padding: 24px;
            background: #1b1b1b;
            border-radius: 12px;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.45);
            overflow: hidden;
            box-sizing: border-box;
        }

        .modern-chart-canvas canvas {
            width: 100% !important;
            height: 100% !important;
            display: block;
        }

        @media (max-width: 600px) {
            .modern-chart-canvas {
                /* reduce padding to give more space for the left axis labels */
                padding: 10px;
                aspect-ratio: unset;
                height: 220px;
            }
            .modern-chart-canvas canvas {
                height: 220px !important;
            }
            /* slightly reduce spacing in filter bar for small screens */
            .modern-filter-bar-group {
                gap: 6px;
                padding: 4px 8px;
            }
            .modern-chart-value { font-size: 1.6rem; }
        }

        .dashboard-3box-row {
            display: flex;
            gap: 32px;
            width: 100%;
            max-width: 100vw;
            box-sizing: border-box;
            margin-top: 32px;
        }

        .dashboard-card {
            background: #262525;
            padding: 24px;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            flex: 1;
            min-width: 0;
            box-sizing: border-box;
        }

        .panel-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 18px;
        }

        .panel-header h3 {
            margin: 0;
            font-size: 1.1rem;
            color: #fff;
            font-weight: 600;
        }

        .dash-list {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .dash-list-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #333;
        }

        .dash-list-item:last-child {
            border-bottom: none;
        }

        .no-scheduled-posts {
            justify-content: center !important;
            align-items: center !important;
            text-align: center;
            color: rgba(255, 255, 255, 0.5);
            height: 120px;
            display: flex !important;
            border-bottom: none !important;
        }

        .item-title {
            font-weight: 500;
            font-size: 1rem;
            color: #fff;
        }

        .item-meta {
            font-size: 0.95rem;
            /* color: #a3a3c2; */
        }

        .rank-badge {
            background: #333;
            color: #fff;
            width: 24px;
            height: 24px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            font-size: 0.9rem;
            margin-right: 12px;
            font-weight: 700;
        }

        @media (max-width: 900px) {
            .dashboard-3box-row {
                flex-direction: column;
                gap: 24px;
            }

            .dashboard-card {
                width: 100%;
                min-width: 0;
            }

            .modern-dashboard-fullwidth {
                padding: 0;
            }

            .grouped-card {
                flex-direction: column;
                padding: 24px;
            }

            .modern-stats-side {
                border-left: none !important;
                padding-left: 0 !important;
                width: 100% !important;
                padding-top: 24px;
                border-top: 1px solid #333;
            }
        }

        .dash-list .scheduled-list-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #333;
        }

        .dash-list .scheduled-list-item:last-child {
            border-bottom: none;
        }

        .scheduled-title {
            font-weight: 500;
            font-size: 1rem;
            color: #fff;
        }

        .scheduled-meta {
            font-size: 0.95rem;
            color: #a3a3c2;
        }

        /* TOAST NOTIFICATION STYLES */
        #toast-container {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .toast-notification {
            background: #2a2a2a;
            border-left: 4px solid #7675ec;
            padding: 16px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            width: 320px;
            display: flex;
            gap: 12px;
            animation: slideInToast 0.3s ease-out forwards;
            opacity: 0;
            transform: translateX(20px);
        }

        @keyframes slideInToast {
            from { opacity: 0; transform: translateX(20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        @keyframes slideOutToast {
            from { opacity: 1; transform: translateX(0); }
            to { opacity: 0; transform: translateX(20px); }
        }
        
        .toast-content h4 {
            margin: 0 0 4px 0;
            font-size: 1rem;
            color: #fff;
            font-weight: 600;
        }
        
        .toast-content p {
            margin: 0;
            font-size: 0.9rem;
            color: #ccc;
            line-height: 1.4;
        }
        
        .toast-icon {
            color: #7675ec;
            flex-shrink: 0;
            padding-top: 2px;
        }
    </style>

    <!-- Toast Container -->
    <div id="toast-container"></div>

    <script>
        // Hide preloader after page loads with minimum display time
        const preloaderStart = Date.now();
        window.addEventListener('load', function() {
            const preloader = document.getElementById('preloader');
            if (preloader) {
                // Ensure preloader shows for at least 500ms
                const elapsed = Date.now() - preloaderStart;
                const minDisplayTime = 500;
                const remainingTime = Math.max(0, minDisplayTime - elapsed);
                
                setTimeout(() => {
                    preloader.classList.add('fade-out');
                    setTimeout(() => {
                        preloader.style.display = 'none';
                    }, 300);
                }, remainingTime);
            }

            // Asynchronous Queue Processing (OPTIMIZATION)
            // Trigger background optimization tasks after the UI is interactive
            setTimeout(() => {
                fetch('blog/process_queue.php')
                    .then(r => r.text())
                    .then(out => {}) // Silence output
                    .catch(e => console.warn('Queue error:', e));
            }, 2000); // 2 second delay to avoid impacting initial paint
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const hash = window.location.hash.substring(1);
            if (hash) {
                const target = document.getElementById(hash);
                if (target) {
                    // Scroll
                    target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    
                    // Highlight effect
                    target.style.transition = 'all 0.5s ease';
                    const originalBorder = target.style.borderColor;
                    const originalShadow = target.style.boxShadow;
                    
                    target.style.borderColor = '#d225d7'; // Accent
                    target.style.boxShadow = '0 0 0 4px rgba(210, 37, 215, 0.2)';
                    
                    setTimeout(() => {
                        target.style.borderColor = originalBorder;
                        target.style.boxShadow = originalShadow;
                    }, 2000);
                }
            }
        });

    </script>
</body>

</html>