<?php
/**
 * Dashboard for Editor role
 * Shows: Post stats, latest posts, most viewed, basic chart
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

$userRole = $_SESSION['admin']['position'] ?? 'Editor';
$isAdmin = ($_SESSION['admin']['role'] ?? 'admin') === 'admin';

if ($isAdmin) {
    header('Location: dashboard.php');
    exit;
}

// Editor stats
$totalPages = 0;
try { $totalPages = $db->page_content->countDocuments(); } catch (Exception $e) {}

$totalPosts = 0;
$publishedCount = 0;
$draftCount = 0;
try {
    $totalPosts = $db->blog->countDocuments();
    $publishedCount = $db->blog->countDocuments(['status' => 'published']);
    $draftCount = $db->blog->countDocuments(['status' => ['$in' => ['draft', null]]]);
} catch (Exception $e) {}

// Latest posts
$recentPostsCursor = $db->blog->find([], [
    'limit' => 3,
    'sort' => ['created_at' => -1],
    'projection' => ['title_de' => 1, 'title_en' => 1, 'created_at' => 1, 'status' => 1]
]);
$recentPostsArray = iterator_to_array($recentPostsCursor, false);

// Most viewed posts
$topPostsCursor = $db->blog->find(
    ['views' => ['$gt' => 0]],
    [
        'limit' => 3,
        'sort' => ['views' => -1],
        'projection' => ['title_de' => 1, 'title_en' => 1, 'title' => 1, 'views' => 1]
    ]
);
$topPostsArray = iterator_to_array($topPostsCursor, false);

// Basic chart: 7d and 30d blog views
$chart7d = getViewsData($db, $lang, 7, 'D');
$chart30d = getViewsData($db, $lang, 30, 'M d');
$totalViews7d = array_sum($chart7d['data']);
$totalViews30d = array_sum($chart30d['data']);

$adminDisplayName = $_SESSION['admin']['name'] ?? ucfirst(explode('@', $_SESSION['admin']['email'] ?? 'Editor')[0]);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard - Editor</title>
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
            <div class="blur-bg-theme top-left"></div>

            <?php $showWelcomeMessage = true; include __DIR__ . '/partials/topbar.php'; ?>

            <!-- Stats Cards: Total Pages, Total Posts, Published, Drafts -->
            <div class="role-stats-row" id="tour-stats-row">
                <!-- Total Website Pages -->
                <div class="role-stat-card horizontal" style="flex: 1; min-width: 200px;">
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
                    <div class="card-icon-wrapper" style="background:rgba(16,185,129,0.15);">
                        <svg style="width:20px; height:20px; color:#10b981;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                            <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm13.36-1.814a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="card-info">
                        <div class="card-label"><?= $lang['dash_published'] ?? 'Published' ?></div>
                        <div class="card-value"><?= number_format($publishedCount) ?></div>
                    </div>
                </div>

                <!-- Drafts -->
                <div class="role-stat-card horizontal" style="flex: 1; min-width: 200px;">
                    <div class="card-icon-wrapper" style="background:rgba(249,115,22,0.15);">
                        <svg style="width:20px; height:20px; color:#f97316;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.625 1.5c-1.036 0-1.875.84-1.875 1.875v17.25c0 1.035.84 1.875 1.875 1.875h12.75c1.035 0 1.875-.84 1.875-1.875V12.75A3.75 3.75 0 0 0 16.5 9h-1.875a1.875 1.875 0 0 1-1.875-1.875V5.25A3.75 3.75 0 0 0 9 1.5H5.625ZM7.5 15a.75.75 0 0 1 .75-.75h7.5a.75.75 0 0 1 0 1.5h-7.5A.75.75 0 0 1 7.5 15Zm.75 2.25a.75.75 0 0 0 0 1.5H12a.75.75 0 0 0 0-1.5H8.25Z" clip-rule="evenodd" />
                            <path d="M12.971 1.816A5.23 5.23 0 0 1 14.25 5.25v1.875c0 .207.168.375.375.375H16.5a5.23 5.23 0 0 1 3.434 1.279 9.768 9.768 0 0 0-6.963-6.963Z" />
                        </svg>
                    </div>
                    <div class="card-info">
                        <div class="card-label"><?= $lang['dash_my_drafts'] ?? 'Drafts' ?></div>
                        <div class="card-value"><?= number_format($draftCount) ?></div>
                    </div>
                </div>
            </div>

            <!-- Latest Posts + Most Viewed Posts -->
            <div class="role-middle-row">
                <!-- Latest Posts -->
                <div class="role-panel" id="tour-recent-posts">
                    <div class="role-panel-header">
                        <svg class="icon" style="color:#7675EC;margin-right:8px;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="20" height="20">
                            <path fill-rule="evenodd" d="M4.125 3C3.089 3 2.25 3.84 2.25 4.875V18a3 3 0 0 0 3 3h15a3 3 0 0 1-3-3V4.875C17.25 3.839 16.41 3 15.375 3H4.125ZM12 9.75a.75.75 0 0 0 0 1.5h1.5a.75.75 0 0 0 0-1.5H12Zm-.75-2.25a.75.75 0 0 1 .75-.75h1.5a.75.75 0 0 1 0 1.5H12a.75.75 0 0 1-.75-.75ZM6 12.75a.75.75 0 0 0 0 1.5h7.5a.75.75 0 0 0 0-1.5H6Zm-.75 3.75a.75.75 0 0 1 .75-.75h7.5a.75.75 0 0 1 0 1.5H6a.75.75 0 0 1-.75-.75ZM6 6.75a.75.75 0 0 0-.75.75v3c0 .414.336.75.75.75h3a.75.75 0 0 0 .75-.75v-3A.75.75 0 0 0 9 6.75H6Z" clip-rule="evenodd" />
                            <path d="M18.75 6.75h1.875c.621 0 1.125.504 1.125 1.125V18a1.5 1.5 0 0 1-3 0V6.75Z" />
                        </svg>
                        <h3 style="font-weight:600; font-size:1.1rem; color:#fff; margin:0;"><?= $lang['dash_recent_posts'] ?? 'Latest Posts' ?></h3>
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

                <!-- Most Viewed Posts -->
                <div class="role-panel" id="panel-most-viewed">
                    <div class="role-panel-header" style="margin-bottom:24px; display:flex; align-items:center; gap:10px;">
                        <svg style="width:20px; height:20px; color:#7675EC;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" />
                            <path fill-rule="evenodd" d="M1.323 11.447C2.811 6.976 7.028 3.75 12.001 3.75c4.97 0 9.185 3.223 10.675 7.69.12.362.12.752 0 1.113-1.487 4.471-5.705 7.697-10.677 7.697-4.97 0-9.186-3.223-10.675-7.69a1.762 1.762 0 0 1 0-1.113ZM17.25 12a5.25 5.25 0 1 1-10.5 0 5.25 5.25 0 0 1 10.5 0Z" clip-rule="evenodd" />
                        </svg>
                        <h3 style="font-weight:600; font-size:1.1rem; color:#fff; margin:0;"><?= $lang['dash_most_viewed'] ?? 'Most Viewed Posts' ?></h3>
                    </div>
                    <div class="top-posts-list">
                        <?php if (empty($topPostsArray)): ?>
                            <div style="text-align:center;color:rgba(255,255,255,0.5);padding:32px 0;"><?= $lang['dash_no_views'] ?? 'No posts with views yet' ?></div>
                        <?php else: ?>
                            <?php
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
                                                echo htmlspecialchars(count($parts) > 3 ? implode(' ', array_slice($parts, 0, 3)) . '...' : $titleFull);
                                                ?>
                                            </span>
                                        </a>
                                        <span style="font-size:0.8rem; color:#9ca3af; font-weight:500;"><?= number_format($views) ?> <?= $lang['chart_views_label'] ?? 'Views' ?></span>
                                    </div>
                                    <div style="width:100%; height:4px; background:#1a1a1a; border-radius:10px; overflow:hidden;">
                                        <div style="width:<?= $percentage ?>%; height:100%; background:#7675EC; border-radius:10px; transition:width 0.3s ease;"></div>
                                    </div>
                                </div>
                            <?php $rank++; endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Basic Chart (7d / 30d toggle) -->
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
                                        <button class="chart-filter-btn active" data-range="7d">
                                            <span class="label-desktop"><?= $lang['chart_time_7d'] ?? '7 Days' ?></span>
                                            <span class="label-mobile"><?= $lang['chart_time_7d_short'] ?? '7D' ?></span>
                                        </button>
                                        <button class="chart-filter-btn" data-range="30d">
                                            <span class="label-desktop"><?= $lang['chart_time_30d'] ?? '30 Days' ?></span>
                                            <span class="label-mobile"><?= $lang['chart_time_30d_short'] ?? '30D' ?></span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="modern-chart-canvas" style="width:100%;height:280px;">
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
        // Basic chart with 7d/30d toggle
        (function() {
            const datasets = {
                '7d': { labels: <?= json_encode($chart7d['labels']) ?>, data: <?= json_encode($chart7d['data']) ?>, total: <?= $totalViews7d ?> },
                '30d': { labels: <?= json_encode($chart30d['labels']) ?>, data: <?= json_encode($chart30d['data']) ?>, total: <?= $totalViews30d ?> }
            };

            const ctx = document.getElementById('trafficChart').getContext('2d');
            const totalDisplay = document.getElementById('total-views-count');

            function hexToRgba(hex, alpha) {
                const h = hex.replace('#', '');
                const bigint = parseInt(h, 16);
                return `rgba(${(bigint >> 16) & 255}, ${(bigint >> 8) & 255}, ${bigint & 255}, ${alpha})`;
            }

            function makeDataset(data, color) {
                const gradient = ctx.createLinearGradient(0, 0, 0, 280);
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
                }
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
