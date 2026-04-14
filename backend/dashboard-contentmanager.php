<?php
/**
 * Dashboard for Content Manager role
 * Shows: All content stats, comments count, planned posts, full chart
 */
require_once 'session_config.php';
require 'database.php';
/** @var \MongoDB\Database $db */
require 'config.php';
require_once 'includes/dashboard_functions.php';

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

$userRole = $_SESSION['admin']['position'] ?? 'Content Manager';
$isAdmin = ($_SESSION['admin']['role'] ?? 'admin') === 'admin';

if ($isAdmin) {
    header('Location: dashboard.php');
    exit;
}

// Content Manager has both CMS and Blog access
$totalPages = 0;
try { $totalPages = $db->page_content->countDocuments(); } catch (Exception $e) {}

$totalPosts = 0;
$publishedCount = 0;
$draftCount = 0;
$totalComments = 0;
try {
    $totalPosts = $db->blog->countDocuments();
    $publishedCount = $db->blog->countDocuments(['status' => 'published']);
    $draftCount = $db->blog->countDocuments(['status' => ['$in' => ['draft', null]]]);
    $totalComments = $db->comments->countDocuments([]);
} catch (Exception $e) {}

// Scheduled / Planned posts
$scheduledPostsCursor = $db->blog->find(
    ['status' => 'scheduled'],
    [
        'limit' => 3,
        'sort' => ['scheduled_at' => 1],
        'projection' => ['title_de' => 1, 'title_en' => 1, 'scheduled_at' => 1, 'author_email' => 1]
    ]
);
$scheduledPostsArray = iterator_to_array($scheduledPostsCursor, false);

// Recent posts (for context)
$recentPostsCursor = $db->blog->find([], [
    'limit' => 3,
    'sort' => ['created_at' => -1],
    'projection' => ['title_de' => 1, 'title_en' => 1, 'created_at' => 1, 'status' => 1, 'author_email' => 1, 'views' => 1]
]);
$recentPostsArray = iterator_to_array($recentPostsCursor, false);

// Full chart data: 7d, 30d, 12m, 24h (blog views only)
$chart7d = getViewsData($db, $lang, 7, 'D');
$chart30d = getViewsData($db, $lang, 30, 'M d');

// 12 months
$startMonthDate = date('Y-m-01', strtotime('-11 months'));
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
try {
    $results12m = $db->blog->aggregate($pipeline12m);
    foreach ($results12m as $res) {
        if (isset($monthsMap[$res['_id']])) $monthsMap[$res['_id']] = $res['totalViews'];
    }
} catch (Exception $e) {}
$chart12m_labels = [];
$chart12m_data = [];
foreach ($monthsMap as $m => $val) {
    $ts = strtotime($m . '-01');
    $enM = date('M', $ts);
    $transM = $lang['month_' . strtolower($enM)] ?? $enM;
    $chart12m_labels[] = str_replace($enM, $transM, date('M Y', $ts));
    $chart12m_data[] = $val;
}
$chart12m = ['labels' => $chart12m_labels, 'data' => $chart12m_data];

// 24h
$startHour = date('Y-m-d H', strtotime('-23 hours'));
$pipeline24h = [
    ['$match' => ['views_history' => ['$exists' => true]]],
    ['$project' => ['history' => ['$objectToArray' => '$views_history']]],
    ['$unwind' => '$history'],
    ['$match' => [
        'history.k' => ['$gte' => $startHour],
        '$expr' => ['$gt' => [['$strLenCP' => '$history.k'], 10]]
    ]],
    ['$group' => [
        '_id' => '$history.k',
        'totalViews' => ['$sum' => '$history.v']
    ]]
];
$viewsMap24h = [];
try {
    $results24h = $db->blog->aggregate($pipeline24h);
    foreach ($results24h as $res) { $viewsMap24h[$res['_id']] = $res['totalViews']; }
} catch (Exception $e) {}
$chart24h_labels = [];
$chart24h_data = [];
for ($i = 23; $i >= 0; $i--) {
    $hour = date('Y-m-d H', strtotime("-$i hours"));
    $chart24h_labels[] = date('H:00', strtotime("-$i hours"));
    $chart24h_data[] = $viewsMap24h[$hour] ?? 0;
}
$chart24h = ['labels' => $chart24h_labels, 'data' => $chart24h_data];

$totalViews7d = array_sum($chart7d['data']);
$totalViews30d = array_sum($chart30d['data']);
$totalViews12m = array_sum($chart12m['data']);
$totalViews24h = array_sum($chart24h['data']);

$adminDisplayName = $_SESSION['admin']['name'] ?? ucfirst(explode('@', $_SESSION['admin']['email'] ?? 'Manager')[0]);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard - Content Manager</title>
    <link rel="shortcut icon" href="<?= htmlspecialchars($siteFaviconUrl) ?>" type="image/x-icon">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?= time() ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Role dashboard styles - matching admin dashboard.css */
        .role-stats-row { display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 32px; }
        .role-middle-row { display: grid; grid-template-columns: repeat(2, 1fr); gap: 24px; margin-bottom: 32px; }
        @media (max-width: 1600px) { .role-middle-row { grid-template-columns: repeat(2, 1fr) !important; } }
        @media (max-width: 640px) { .role-middle-row { grid-template-columns: 1fr; gap: 16px; } }
        .role-stat-card { background: #262525; border-radius: 16px; padding: 24px; display: flex; flex-direction: column; justify-content: space-between; min-height: 140px; position: relative; border: 1px solid rgba(255,255,255,0.1); transition: transform 0.3s ease, box-shadow 0.3s ease; }
        .role-stat-card.horizontal { flex-direction: row; align-items: center; justify-content: flex-start; gap: 20px; min-height: auto; padding: 20px 24px; }
        .role-stat-card .card-label { font-size: 0.9rem; color: #9ca3af; font-weight: 500; }
        .role-stat-card .card-icon-wrapper { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; }
        .role-stat-card.horizontal .card-icon-wrapper { width: 48px; height: 48px; border-radius: 12px; flex-shrink: 0; }
        .role-stat-card .card-value { font-size: 2rem; font-weight: 700; color: #fff; margin-top: auto; }
        .role-stat-card.horizontal .card-value { margin-top: 0; line-height: 1.1; font-size: 1.6rem; }
        .role-stat-card.horizontal .card-label { margin-bottom: 2px; }
        .role-stat-card .card-info { display: flex; flex-direction: column; gap: 4px; }
        .role-panel { background: #262525; padding: 24px; border-radius: 16px; border: 1px solid rgba(255,255,255,0.15); box-shadow: none; }
        .role-panel-header { display: flex; align-items: center; gap: 10px; margin-bottom: 20px; }
        .role-list { list-style: none; margin: 0; padding: 0; }
        .role-list-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #333333; }
        .role-list-item:last-child { border-bottom: none; }
        .role-rank-badge { background: #333333; color: #ffffff; width: 24px; height: 24px; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px; font-size: 0.8rem; margin-right: 12px; font-weight: 700; }
        .role-list-link { display: flex; justify-content: space-between; align-items: center; width: 100%; text-decoration: none; color: inherit; padding: 4px 8px; margin: -4px -8px; border-radius: 8px; transition: all 0.2s ease; }
        .role-list-link:hover { background: rgba(118, 117, 236, 0.1); }
        .role-list-link:hover .item-title { color: #d225d7; }
        .role-list-link:hover .item-meta { color: #9ca3af; }
        .role-grouped-card { background: #262525; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.08); padding: 32px 32px 24px 32px; display: flex; gap: 48px; align-items: center; width: 100%; margin-bottom: 32px; }
    </style>
</head>
<body>
    <!-- Preloader -->
    <div class="preloader" id="preloader">
        <div class="preloader-spinner"></div>
    </div>

    <div class="layout">
        <?php $sidebarVariant = 'dashboard'; $activeMenu = 'dashboard'; include __DIR__ . '/partials/sidebar.php'; ?>

        <main class="content">
            <!-- Blur Background Theme -->
            <div class="blur-bg-theme bottom-right"></div>

            <?php $showWelcomeMessage = true; include __DIR__ . '/partials/topbar.php'; ?>

            <!-- Stats Cards: Total Posts, Published, Comments, Total Pages -->
            <div class="role-stats-row" id="tour-stats-row">
                <!-- Total Posts -->
                <div class="role-stat-card horizontal" style="flex: 1; min-width: 200px;">
                    <div class="card-icon-wrapper" style="background:rgba(99,102,241,0.15);">
                        <svg style="width:20px; height:20px; color:#6366f1;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                            <path fill-rule="evenodd" d="M4.125 3C3.089 3 2.25 3.84 2.25 4.875V18a3 3 0 0 0 3 3h15a3 3 0 0 1-3-3V4.875C17.25 3.839 16.41 3 15.375 3H4.125ZM12 9.75a.75.75 0 0 0 0 1.5h1.5a.75.75 0 0 0 0-1.5H12Zm-.75-2.25a.75.75 0 0 1 .75-.75h1.5a.75.75 0 0 1 0 1.5H12a.75.75 0 0 1-.75-.75ZM6 12.75a.75.75 0 0 0 0 1.5h7.5a.75.75 0 0 0 0-1.5H6Zm-.75 3.75a.75.75 0 0 1 .75-.75h7.5a.75.75 0 0 1 0 1.5H6a.75.75 0 0 1-.75-.75ZM6 6.75a.75.75 0 0 0-.75.75v3c0 .414.336.75.75.75h3a.75.75 0 0 0 .75-.75v-3A.75.75 0 0 0 9 6.75H6Z" clip-rule="evenodd" />
                            <path d="M18.75 6.75h1.875c.621 0 1.125.504 1.125 1.125V18a1.5 1.5 0 0 1-3 0V6.75Z" />
                        </svg>
                    </div>
                    <div class="card-info">
                        <div class="card-label"><?= $lang['dash_total_posts'] ?? 'Total Posts' ?></div>
                        <div class="card-value"><?= number_format($totalPosts) ?></div>
                    </div>
                </div>

                <!-- Published -->
                <div class="role-stat-card horizontal" style="flex: 1; min-width: 200px;">
                    <div class="card-icon-wrapper" style="background:rgba(118,117,236,0.15);">
                        <svg style="width:20px; height:20px; color:#7675ec;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                            <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm13.36-1.814a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="card-info">
                        <div class="card-label"><?= $lang['dash_published'] ?? 'Published' ?></div>
                        <div class="card-value"><?= number_format($publishedCount) ?></div>
                    </div>
                </div>

                <!-- Comments -->
                <div class="role-stat-card horizontal" style="flex: 1; min-width: 200px;">
                    <div class="card-icon-wrapper" style="background:rgba(210,37,215,0.15);">
                        <svg style="width:20px; height:20px; color:#d225d7;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                            <path fill-rule="evenodd" d="M12 2.25c-2.429 0-4.817.178-7.152.521C2.87 3.061 1.5 4.795 1.5 6.741v6.018c0 1.946 1.37 3.68 3.348 3.97.877.129 1.761.234 2.652.316V21a.75.75 0 0 0 1.28.53l4.184-4.183a.39.39 0 0 1 .266-.112c2.006-.05 3.982-.22 5.922-.506 1.978-.29 3.348-2.023 3.348-3.97V6.741c0-1.947-1.37-3.68-3.348-3.97A49.145 49.145 0 0 0 12 2.25ZM8.25 8.625a1.125 1.125 0 1 0 0 2.25 1.125 1.125 0 0 0 0-2.25Zm2.625 1.125a1.125 1.125 0 1 1 2.25 0 1.125 1.125 0 0 1-2.25 0Zm4.875-1.125a1.125 1.125 0 1 0 0 2.25 1.125 1.125 0 0 0 0-2.25Z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="card-info">
                        <div class="card-label"><?= $lang['dash_comments'] ?? 'Comments' ?></div>
                        <div class="card-value"><?= number_format($totalComments) ?></div>
                    </div>
                </div>

                <!-- Total Website Pages -->
                <div class="role-stat-card horizontal" style="flex: 1; min-width: 200px;">
                    <div class="card-icon-wrapper" style="background:rgba(16,185,129,0.15);">
                        <svg style="width:20px; height:20px; color:#10b981;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M11.47 3.84a.75.75 0 011.06 0l8.69 8.69a.75.75 0 101.06-1.06l-8.69-8.69a2.25 2.25 0 00-3.18 0l-8.69 8.69a.75.75 0 001.06 1.06l8.69-8.69z" />
                            <path d="M12 5.432l8.159 8.159c.03.03.06.058.091.086v6.198c0 1.035-.84 1.875-1.875 1.875H15a.75.75 0 01-.75-.75v-4.5a.75.75 0 00-.75-.75h-3a.75.75 0 00-.75.75v4.5a.75.75 0 01-.75.75H5.625a1.875 1.875 0 01-1.875-1.875v-6.198a2.29 2.29 0 00.091-.086L12 5.432z" />
                        </svg>
                    </div>
                    <div class="card-info">
                        <div class="card-label"><?= $lang['dash_total_pages'] ?? 'Total Website Pages' ?></div>
                        <div class="card-value"><?= number_format($totalPages) ?></div>
                    </div>
                </div>
            </div>

            <!-- Recent Posts + Planned Posts -->
            <div class="role-middle-row">
                <!-- Recent Posts -->
                <div class="role-panel" id="tour-recent-posts">
                    <div class="role-panel-header">
                        <svg class="icon" style="color:#7675EC;margin-right:8px;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="20" height="20">
                            <path fill-rule="evenodd" d="M4.125 3C3.089 3 2.25 3.84 2.25 4.875V18a3 3 0 0 0 3 3h15a3 3 0 0 1-3-3V4.875C17.25 3.839 16.41 3 15.375 3H4.125ZM12 9.75a.75.75 0 0 0 0 1.5h1.5a.75.75 0 0 0 0-1.5H12Zm-.75-2.25a.75.75 0 0 1 .75-.75h1.5a.75.75 0 0 1 0 1.5H12a.75.75 0 0 1-.75-.75ZM6 12.75a.75.75 0 0 0 0 1.5h7.5a.75.75 0 0 0 0-1.5H6Zm-.75 3.75a.75.75 0 0 1 .75-.75h7.5a.75.75 0 0 1 0 1.5H6a.75.75 0 0 1-.75-.75ZM6 6.75a.75.75 0 0 0-.75.75v3c0 .414.336.75.75.75h3a.75.75 0 0 0 .75-.75v-3A.75.75 0 0 0 9 6.75H6Z" clip-rule="evenodd" />
                            <path d="M18.75 6.75h1.875c.621 0 1.125.504 1.125 1.125V18a1.5 1.5 0 0 1-3 0V6.75Z" />
                        </svg>
                        <h3 style="font-weight:600; font-size:1.1rem; color:#fff; margin:0;"><?= $lang['dash_recent_posts'] ?? 'Recent Posts' ?></h3>
                    </div>
                    <ul class="role-list">
                        <?php if (empty($recentPostsArray)): ?>
                            <li class="role-list-item" style="justify-content:center; color:rgba(255,255,255,0.4); padding:32px 0;">
                                <?= $lang['dash_no_posts'] ?? 'No posts yet' ?>
                            </li>
                        <?php else: ?>
                            <?php $r = 1; foreach ($recentPostsArray as $rp): 
                                $postId = (string) $rp->_id;
                            ?>
                                <li class="role-list-item">
                                    <a href="../blog.php?id=<?= $postId ?>" class="role-list-link" target="_blank">
                                        <span style="display:flex;align-items:center;">
                                            <span class="role-rank-badge"><?= $r++ ?></span>
                                            <span class="item-title">
                                                <?php
                                                $titleFull = $rp->title_de ?? $rp->title_en ?? 'Untitled';
                                                $parts = explode(' ', $titleFull);
                                                echo htmlspecialchars(count($parts) > 4 ? implode(' ', array_slice($parts, 0, 4)) . '...' : $titleFull);
                                                ?>
                                            </span>
                                        </span>
                                        <span style="display:flex; align-items:center; gap:12px;">
                                            <span style="font-size:0.8rem; padding:3px 10px; border-radius:6px; background:<?= ($rp->status ?? 'draft') === 'published' ? 'rgba(16,185,129,0.15)' : 'rgba(249,115,22,0.15)' ?>; color:<?= ($rp->status ?? 'draft') === 'published' ? '#10b981' : '#f97316' ?>;">
                                                <?= ($rp->status ?? 'draft') === 'published' ? ($lang['dash_published'] ?? 'Published') : ($lang['dash_my_drafts'] ?? 'Draft') ?>
                                            </span>
                                            <span class="item-meta">
                                                <?php 
                                                if (isset($rp->created_at) && $rp->created_at instanceof MongoDB\BSON\UTCDateTime) {
                                                    $dtr = $rp->created_at->toDateTime();
                                                    $dtr->setTimezone(new DateTimeZone('Europe/Berlin'));
                                                    echo $dtr->format('M d');
                                                }
                                                ?>
                                            </span>
                                        </span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>

                <!-- Planned / Scheduled Posts -->
                <div class="role-panel" id="tour-scheduled-posts">
                    <div class="role-panel-header">
                        <svg class="icon" style="color:#7675EC;margin-right:8px;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="20" height="20">
                            <path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25ZM12.75 6a.75.75 0 0 0-1.5 0v6c0 .414.336.75.75.75h4.5a.75.75 0 0 0 0-1.5h-3.75V6Z" clip-rule="evenodd" />
                        </svg>
                        <h3 style="font-weight:600; font-size:1.1rem; color:#fff; margin:0;"><?= $lang['dash_planned_posts'] ?? 'Planned Posts' ?></h3>
                    </div>
                    <ul class="role-list">
                        <?php if (empty($scheduledPostsArray)): ?>
                            <div style="text-align:center;color:rgba(255,255,255,0.3);padding:32px 0;font-size:0.9rem;">
                                <?= $lang['dash_no_scheduled'] ?? 'No scheduled posts' ?>
                            </div>
                        <?php else: ?>
                            <?php foreach ($scheduledPostsArray as $sp): ?>
                                <li class="role-list-item">
                                    <span style="display:flex; align-items:center; gap:8px;">
                                        <span class="item-title"><?= htmlspecialchars($sp->title_de ?? $sp->title_en ?? 'Untitled') ?></span>
                                    </span>
                                    <span class="item-meta">
                                        <?php 
                                        if (isset($sp->scheduled_at) && $sp->scheduled_at instanceof MongoDB\BSON\UTCDateTime) {
                                            $dt = $sp->scheduled_at->toDateTime();
                                            $dt->setTimezone(new DateTimeZone('Europe/Berlin'));
                                            echo $dt->format('M d, H:i');
                                        }
                                        ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <!-- Full Chart (24h / 7d / 30d / 12m) -->
            <div class="chart-row" style="margin-bottom:32px;">
                <div class="modern-dashboard-fullwidth" id="tour-traffic-chart" style="width:100%;max-width:100vw;margin:0 auto;">
                    <div class="role-grouped-card" style="margin-bottom:0;width:100%;box-sizing:border-box; background:#262525; border-radius:16px; padding:32px;">
                        <div style="width:100%;">
                            <div class="modern-chart-header">
                                <div>
                                    <div class="modern-chart-value">
                                        <span id="total-views-count" style="background:linear-gradient(135deg, #6366f1 0%, #d225d7 100%); -webkit-background-clip:text; -webkit-text-fill-color:transparent; color:#d225d7;"><?= number_format($totalViews7d) ?></span>
                                        <span class="views-label"><?= $lang['chart_views_label'] ?? 'Views' ?></span>
                                    </div>
                                </div>
                                <div class="modern-filter-bar-row">
                                    <div class="modern-filter-bar-group">
                                        <button class="chart-filter-btn" data-range="12m">
                                            <span class="label-desktop"><?= $lang['chart_time_12m'] ?? '12 Months' ?></span>
                                            <span class="label-mobile"><?= $lang['chart_time_12m_short'] ?? '12M' ?></span>
                                        </button>
                                        <button class="chart-filter-btn" data-range="30d">
                                            <span class="label-desktop"><?= $lang['chart_time_30d'] ?? '30 Days' ?></span>
                                            <span class="label-mobile"><?= $lang['chart_time_30d_short'] ?? '30D' ?></span>
                                        </button>
                                        <button class="chart-filter-btn active" data-range="7d">
                                            <span class="label-desktop"><?= $lang['chart_time_7d'] ?? '7 Days' ?></span>
                                            <span class="label-mobile"><?= $lang['chart_time_7d_short'] ?? '7D' ?></span>
                                        </button>
                                        <button class="chart-filter-btn" data-range="24h">
                                            <span class="label-desktop"><?= $lang['chart_time_24h'] ?? '24 Hours' ?></span>
                                            <span class="label-mobile"><?= $lang['chart_time_24h_short'] ?? '24h' ?></span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="modern-chart-canvas" style="width:100%;height:320px;">
                                <canvas id="trafficChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <!-- GLOBAL SEARCH -->
    <script src="assets/js/global-search.js?v=<?= time() ?>"></script>

    <script>
        // Full chart with 24h/7d/30d/12m toggle
        (function() {
            const datasets = {
                '7d': { labels: <?= json_encode($chart7d['labels']) ?>, data: <?= json_encode($chart7d['data']) ?>, total: <?= $totalViews7d ?> },
                '30d': { labels: <?= json_encode($chart30d['labels']) ?>, data: <?= json_encode($chart30d['data']) ?>, total: <?= $totalViews30d ?> },
                '12m': { labels: <?= json_encode($chart12m['labels']) ?>, data: <?= json_encode($chart12m['data']) ?>, total: <?= $totalViews12m ?> },
                '24h': { labels: <?= json_encode($chart24h['labels']) ?>, data: <?= json_encode($chart24h['data']) ?>, total: <?= $totalViews24h ?> }
            };

            const ctx = document.getElementById('trafficChart').getContext('2d');
            const totalDisplay = document.getElementById('total-views-count');

            function hexToRgba(hex, alpha) {
                const h = hex.replace('#', '');
                const bigint = parseInt(h, 16);
                return `rgba(${(bigint >> 16) & 255}, ${(bigint >> 8) & 255}, ${bigint & 255}, ${alpha})`;
            }

            function makeDataset(data, color) {
                const gradient = ctx.createLinearGradient(0, 0, 0, 320);
                gradient.addColorStop(0, hexToRgba(color, 0.4));
                gradient.addColorStop(0.5, hexToRgba(color, 0.15));
                gradient.addColorStop(1, hexToRgba(color, 0));
                return {
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

            // Custom plugin for vertical hover line
            const verticalLinePlugin = {
                id: 'verticalLine',
                afterDraw: function(chart) {
                    const activeElements = chart.getActiveElements();
                    if (activeElements.length > 0) {
                        const ctx = chart.ctx;
                        const x = activeElements[0].element.x;
                        const topY = chart.scales.y.top;
                        const bottomY = chart.scales.y.bottom;
                        ctx.save();
                        ctx.beginPath();
                        ctx.moveTo(x, topY);
                        ctx.lineTo(x, bottomY);
                        ctx.lineWidth = 1;
                        ctx.strokeStyle = 'rgba(118, 117, 236, 0.3)';
                        ctx.stroke();
                        ctx.restore();
                    }
                }
            };

            const chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: datasets['7d'].labels,
                    datasets: [makeDataset(datasets['7d'].data, '#6366f1')]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { intersect: false, mode: 'index' },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#1f2937', titleColor: '#e5e7eb', bodyColor: '#e5e7eb',
                            borderColor: 'rgba(118,117,236,0.3)', borderWidth: 1, cornerRadius: 8, padding: 12,
                            callbacks: { label: function(ctx) { return ctx.parsed.y + ' <?= $lang['chart_views_label'] ?? 'Views' ?>'; } }
                        }
                    },
                    scales: {
                        x: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#6b7280', font: { size: 11 }, maxRotation: 0 } },
                        y: { min: 0, grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#6b7280', font: { size: 11 }, precision: 0, callback: function(v) { return v >= 0 ? v : ''; } } }
                    }
                },
                plugins: [verticalLinePlugin]
            });

            document.querySelectorAll('.chart-filter-btn[data-range]').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('.chart-filter-btn[data-range]').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    const range = this.dataset.range;
                    const ds = datasets[range];
                    chart.data.labels = ds.labels;
                    chart.data.datasets = [makeDataset(ds.data, '#6366f1')];
                    chart.update();
                    totalDisplay.textContent = ds.total.toLocaleString();
                });
            });
        })();

        const preloaderStart = Date.now();
        window.addEventListener('load', function() {
            const preloader = document.getElementById('preloader');
            if (preloader) {
                const elapsed = Date.now() - preloaderStart;
                const remainingTime = Math.max(0, 500 - elapsed);
                setTimeout(() => {
                    preloader.classList.add('fade-out');
                    setTimeout(() => { preloader.style.display = 'none'; }, 300);
                }, remainingTime);
            }
        });
    </script>
</body>
</html>
