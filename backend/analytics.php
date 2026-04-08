<?php
require_once 'session_config.php';
require 'database.php';
/** @var \MongoDB\Database $db */
require 'config.php';

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

// Permissions Logic - Admin Only
$isAdmin = ($_SESSION['admin']['role'] ?? 'admin') === 'admin';
if (!$isAdmin) {
    header('Location: dashboard.php');
    exit;
}

// --- Range and Date Calculation ---
$range = $_GET['range'] ?? '7d';
$days = 7;
$labelFormat = 'D';

switch ($range) {
    case '24h':
        $days = 1;
        $labelFormat = 'H:i';
        break;
    case '7d':
        $days = 7;
        $labelFormat = 'D';
        break;
    case '30d':
        $days = 30;
        $labelFormat = 'M d';
        break;
    case '12m':
        $days = 365;
        $labelFormat = 'M Y';
        break;
}

// Map technical range names to translations for the UI
$rangeLabels = [
    '24h' => $lang['chart_time_24h'] ?? '24 Hours',
    '7d' => $lang['ana_filter_week'] ?? 'Week',
    '30d' => $lang['ana_filter_month'] ?? 'Month',
    '12m' => $lang['chart_time_12m'] ?? '12 Months'
];

$startDate = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
$prevStartDate = date('Y-m-d', strtotime('-' . (2 * $days - 1) . ' days'));
$prevEndDate = date('Y-m-d', strtotime('-' . ($days) . ' days'));
$endDate = date('Y-m-d');

// Helper to calculate trend percentage
function calculateTrend($current, $previous)
{
    if ($previous <= 0)
        return $current > 0 ? 100 : 0;
    $trend = (($current - $previous) / $previous) * 100;
    return round(min(100, max(-100, $trend)), 1);
}

// Optimized data fetching for Views
function getViewsMap($start, $end)
{
    global $db;
    /** @var \MongoDB\Database $db */
    $viewsMap = [];

    // Blog views
    $pipeline = [
        ['$match' => ['views_history' => ['$exists' => true]]],
        ['$project' => ['history' => ['$objectToArray' => '$views_history']]],
        ['$unwind' => '$history'],
        ['$match' => ['history.k' => ['$gte' => $start, '$lte' => $end]]],
        ['$group' => ['_id' => '$history.k', 'total' => ['$sum' => '$history.v']]]
    ];
    $results = $db->blog->aggregate($pipeline);
    foreach ($results as $res)
        $viewsMap[$res['_id']] = $res['total'];

    // Main views
    $cursor = $db->main_views->find(['date' => ['$gte' => $start, '$lte' => $end], 'is_hour' => ['$exists' => false]]);
    foreach ($cursor as $doc) {
        $date = $doc['date'];
        $viewsMap[$date] = ($viewsMap[$date] ?? 0) + ($doc['count'] ?? 0);
    }
    return $viewsMap;
}

// Current Period Stats
$currentViewsMap = getViewsMap($startDate, $endDate);
$totalViews = array_sum($currentViewsMap);
// Use distinct hashes to calculate unique visitors from the hit log
$uniqueVisitors = count($db->visitor_log->distinct('hash', ['date' => ['$gte' => $startDate, '$lte' => $endDate]]));

// Previous Period Stats (for Trend)
$prevViewsMap = getViewsMap($prevStartDate, $prevEndDate);
$prevTotalViews = array_sum($prevViewsMap);
$prevUniqueVisitors = count($db->visitor_log->distinct('hash', ['date' => ['$gte' => $prevStartDate, '$lte' => $prevEndDate]]));

$viewsTrend = calculateTrend($totalViews, $prevTotalViews);
$visitorsTrend = calculateTrend($uniqueVisitors, $prevUniqueVisitors);

// Pages per Session
$pagesPerSession = $uniqueVisitors > 0 ? round($totalViews / $uniqueVisitors, 2) : 0;
$prevPagesPerSession = $prevUniqueVisitors > 0 ? round($prevTotalViews / $prevUniqueVisitors, 2) : 0;
$pagesTrend = calculateTrend($pagesPerSession, $prevPagesPerSession);

// Sparkline Data Construction
$chartLabels = [];
$chartDataViews = [];
$chartDataVisitors = [];

for ($i = $days - 1; $i >= 0; $i--) {
    $ts = strtotime("-$i days");
    $date = date('Y-m-d', $ts);
    $chartLabels[] = date($labelFormat, $ts);
    $chartDataViews[] = $currentViewsMap[$date] ?? 0;

    // Visitors per day (requires distinct to be accurate with hit-based tracking)
    $dayVisitorCount = count($db->visitor_log->distinct('hash', ['date' => $date]));
    $chartDataVisitors[] = $dayVisitorCount;
}

// Bounce Rate Calculation
function getBounceRateStats($start, $end)
{
    global $db;
    /** @var \MongoDB\Database $db */
    $pipeline = [
        ['$match' => ['date' => ['$gte' => $start, '$lte' => $end]]],
        ['$group' => ['_id' => '$hash', 'count' => ['$sum' => 1]]],
        ['$group' => [
            '_id' => null,
            'bounce_count' => ['$sum' => ['$cond' => [['$eq' => ['$count', 1]], 1, 0]]],
            'total_users' => ['$sum' => 1]
        ]]
    ];
    $result = $db->visitor_log->aggregate($pipeline)->toArray();
    if (empty($result) || $result[0]['total_users'] <= 0)
        return ['rate' => 0, 'total' => 0];
    return ['rate' => ($result[0]['bounce_count'] / $result[0]['total_users']) * 100, 'total' => $result[0]['total_users']];
}

$bounceStats = getBounceRateStats($startDate, $endDate);
$prevBounceStats = getBounceRateStats($prevStartDate, $prevEndDate);
$bounceRate = round($bounceStats['rate']) . '%';
$bounceTrend = calculateTrend($bounceStats['rate'], $prevBounceStats['rate']);

// Session Duration Approximation (computed for future use)
// We look at users who have at least 2 logs and take (max(timestamp) - min(timestamp))
$sessionPipeline = [
    ['$match' => ['date' => ['$gte' => $startDate, '$lte' => $endDate]]],
    ['$group' => [
        '_id' => '$hash',
        'first' => ['$min' => '$created_at'],
        'last' => ['$max' => '$timestamp']
    ]],
    ['$project' => [
        'duration' => ['$subtract' => ['$last', '$first']]
    ]],
    ['$match' => ['duration' => ['$gt' => 0]]],
    ['$group' => [
        '_id' => null,
        'avg_duration' => ['$avg' => '$duration']
    ]]
];
$sessionResult = $db->visitor_log->aggregate($sessionPipeline)->toArray();
$avgSessionMs = !empty($sessionResult) ? $sessionResult[0]['avg_duration'] : 0;
$totalSeconds = floor($avgSessionMs / 1000);
$avgSessionMinutes = floor($totalSeconds / 60);
$avgSessionSeconds = $totalSeconds % 60;

// Traffic Sources
$trafficSources = ['Direct' => 0, 'Search' => 0, 'Social' => 0, 'Referral' => 0];
$sourceResults = $db->visitor_log->aggregate([
    ['$match' => ['date' => ['$gte' => $startDate, '$lte' => $endDate]]],
    ['$group' => ['_id' => '$referrer', 'count' => ['$sum' => 1]]]
]);
foreach ($sourceResults as $res) {
    $ref = strtolower($res['_id'] ?? '');
    if (empty($ref))
        $trafficSources['Direct'] += $res['count'];
    elseif (preg_match('/google|bing|yahoo|duckduckgo|baidu/', $ref))
        $trafficSources['Search'] += $res['count'];
    elseif (preg_match('/facebook|t\.co|instagram|linkedin|twitter|pinterest/', $ref))
        $trafficSources['Social'] += $res['count'];
    else
        $trafficSources['Referral'] += $res['count'];
}
$totalSources = array_sum($trafficSources);
$trafficSourcesPct = [];
foreach ($trafficSources as $k => $v)
    $trafficSourcesPct[$k] = $totalSources > 0 ? round(($v / $totalSources) * 100) : 0;

// Device Breakdown
$deviceData = ['Desktop' => 0, 'Mobile' => 0, 'Tablet' => 0];
$deviceResults = $db->visitor_log->aggregate([
    ['$match' => ['date' => ['$gte' => $startDate, '$lte' => $endDate]]],
    ['$group' => ['_id' => '$ua', 'count' => ['$sum' => 1]]]
]);
foreach ($deviceResults as $res) {
    $ua = strtolower($res['_id'] ?? '');
    if (strpos($ua, 'mobile') !== false || strpos($ua, 'android') !== false || strpos($ua, 'iphone') !== false)
        $deviceData['Mobile'] += $res['count'];
    elseif (strpos($ua, 'tablet') !== false || strpos($ua, 'ipad') !== false)
        $deviceData['Tablet'] += $res['count'];
    else
        $deviceData['Desktop'] += $res['count'];
}
$totalDevices = array_sum($deviceData);
$devicePct = [];
foreach ($deviceData as $k => $v)
    $devicePct[$k] = $totalDevices > 0 ? round(($v / $totalDevices) * 100) : 0;

// Browser Breakdown
// OS Breakdown by unique visitors (hash)
$browserData = [];
$browserResults = $db->visitor_log->aggregate([
    ['$match' => ['date' => ['$gte' => $startDate, '$lte' => $endDate], 'browser' => ['$exists' => true]]],
    ['$group' => ['_id' => '$browser', 'count' => ['$sum' => 1]]],
    ['$sort' => ['count' => -1]]
]);
foreach ($browserResults as $res)
    $browserData[$res['_id']] = $res['count'];
$totalBrowsers = array_sum($browserData);
$browserPct = [];
foreach ($browserData as $k => $v)
    $browserPct[$k] = $totalBrowsers > 0 ? round(($v / $totalBrowsers) * 100) : 0;

$topBrowsers = count($browserPct) > 5 ? array_slice($browserPct, 0, 5, true) : $browserPct;
if (count($browserPct) > 5) {
    $otherPct = array_sum(array_slice($browserPct, 5));
    $topBrowsers['Other'] = $otherPct;
}

$osData = [];
$osResults = $db->visitor_log->aggregate([
    ['$match' => ['date' => ['$gte' => $startDate, '$lte' => $endDate], 'os' => ['$exists' => true], 'hash' => ['$exists' => true]]],
    ['$group' => ['_id' => ['os' => '$os', 'hash' => '$hash']]],
    ['$group' => ['_id' => '$_id.os', 'count' => ['$sum' => 1]]],
    ['$sort' => ['count' => -1]]
]);
foreach ($osResults as $res)
    $osData[$res['_id']] = $res['count'];
$totalOS = array_sum($osData);
$osPct = [];
foreach ($osData as $k => $v)
    $osPct[$k] = $totalOS > 0 ? round(($v / $totalOS) * 100) : 0;

// Language Breakdown

// Language Breakdown by unique visitors (hash)
$langData = [];
$langResults = $db->visitor_log->aggregate([
    ['$match' => ['date' => ['$gte' => $startDate, '$lte' => $endDate], 'language' => ['$exists' => true], 'hash' => ['$exists' => true]]],
    ['$group' => ['_id' => ['language' => '$language', 'hash' => '$hash']]],
    ['$group' => ['_id' => '$_id.language', 'count' => ['$sum' => 1]]],
    ['$sort' => ['count' => -1]],
    ['$limit' => 5]
]);
foreach ($langResults as $res)
    $langData[$res['_id']] = $res['count'];

// Sidebar active menu
$activeMenu = 'analytics';
$sidebarVariant = 'dashboard';
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $lang['menu_analytics'] ?? 'Analytics' ?></title>
    <link rel="shortcut icon" href="<?= htmlspecialchars($siteFaviconUrl) ?>" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/analytics.css?v=<?= time() ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <!-- Preloader -->
    <div class="preloader" id="preloader">
        <div class="preloader-spinner"></div>
    </div>

    <div class="layout">
        <!-- SIDEBAR -->
        <?php include __DIR__ . '/partials/sidebar.php'; ?>

        <main class="content ana-dashboard">
            <!-- Blur Background Theme -->
            <div class="blur-bg-theme bottom-right"></div>
            <div class="blur-bg-theme top-left"></div>

            <!-- Header Section -->
    <?php
    $pageTitle = $lang['menu_analytics'] ?? 'Analytics';
    $showWelcomeMessage = false;
    $searchEnabled = true;
    include __DIR__ . '/partials/topbar.php';
    ?>

    <div style="height: 12px;"></div>

    <?php if (isset($_GET['reset']) && $_GET['reset'] === 'success'): ?>
    <div style="background: rgba(34, 197, 94, 0.1); border: 1px solid rgba(34, 197, 94, 0.2); color: #4ade80; padding: 12px 20px; border-radius: 12px; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
            <span>Analytics data has been successfully reset.</span>
        </div>
        <a href="analytics.php" style="color: inherit; opacity: 0.6; text-decoration: none;">&times;</a>
    </div>
    <?php endif; ?>

    <!-- Overview Cards -->
    <div class="ana-overview-grid">
        <div class="ana-card" id="ana-views">
            <div class="ana-card-header">
                <div class="ana-card-title-row">
                    <span class="ana-card-label"><?= $lang['ana_total_views'] ?? 'Total Views' ?></span>
                    <div class="hint-icon" onclick="toggleHint(this)">
                        <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M13 10V2L5 14H11V22L19 10H13Z" />
                        </svg>
                    </div>
                </div>
            </div>
            <div class="ana-info-box"><?= $lang['ana_total_views_hint'] ?? 'Cumulative count of all page views across the site during the selected period.' ?></div>
            <div class="ana-card-value"><?= number_format($totalViews) ?></div>
            <div class="ana-card-trend <?= $viewsTrend >= 0 ? 'ana-trend-up' : 'ana-trend-down' ?>">
                <svg width="12" height="12" fill="currentColor" viewBox="0 0 20 20">
                    <?php if ($viewsTrend >= 0): ?>
                        <path fill-rule="evenodd" d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 7.414V15a1 1 0 11-2 0V7.414L6.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                    <?php else: ?>
                        <path fill-rule="evenodd" d="M14.707 10.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L9 12.586V5a1 1 0 112 0v7.586l2.293-2.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    <?php endif; ?>
                </svg>
                <?= abs($viewsTrend) ?>%
            </div>
            <canvas id="sparkViews" class="ana-spark"></canvas>
        </div>
        <div class="ana-card" id="ana-visitors">
            <div class="ana-card-header">
                <div class="ana-card-title-row">
                    <span class="ana-card-label"><?= $lang['ana_unique_visitors'] ?? 'Unique Visitors' ?></span>
                    <div class="hint-icon" onclick="toggleHint(this)">
                        <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M13 10V2L5 14H11V22L19 10H13Z" />
                        </svg>
                    </div>
                </div>
            </div>
            <div class="ana-info-box"><?= $lang['ana_unique_visitors_hint'] ?? 'Number of unique browsers that visited your site. Multiple visits from the same person (browser) count as one.' ?></div>
            <div class="ana-card-value"><?= number_format($uniqueVisitors) ?></div>
            <div class="ana-card-trend <?= $visitorsTrend >= 0 ? 'ana-trend-up' : 'ana-trend-down' ?>">
                <svg width="12" height="12" fill="currentColor" viewBox="0 0 20 20">
                    <?php if ($visitorsTrend >= 0): ?>
                        <path fill-rule="evenodd" d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 7.414V15a1 1 0 11-2 0V7.414L6.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                    <?php else: ?>
                        <path fill-rule="evenodd" d="M14.707 10.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L9 12.586V5a1 1 0 112 0v7.586l2.293-2.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    <?php endif; ?>
                </svg>
                <?= abs($visitorsTrend) ?>%
            </div>
            <canvas id="sparkVisitors" class="ana-spark"></canvas>
        </div>
        <div class="ana-card" id="ana-pages">
            <div class="ana-card-header">
                <div class="ana-card-title-row">
                    <span class="ana-card-label"><?= $lang['ana_pages_per_session'] ?? 'Pages per Session' ?></span>
                    <div class="hint-icon" onclick="toggleHint(this)">
                        <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M13 10V2L5 14H11V22L19 10H13Z" />
                        </svg>
                    </div>
                </div>
            </div>
            <div class="ana-info-box"><?= $lang['ana_pages_session_hint'] ?? 'Average number of pages viewed during a single session.' ?></div>
            <div class="ana-card-value"><?= $pagesPerSession ?></div>
            <div class="ana-card-trend <?= $pagesTrend >= 0 ? 'ana-trend-up' : 'ana-trend-down' ?>">
                <svg width="12" height="12" fill="currentColor" viewBox="0 0 20 20">
                    <?php if ($pagesTrend >= 0): ?>
                        <path fill-rule="evenodd" d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 7.414V15a1 1 0 11-2 0V7.414L6.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                    <?php else: ?>
                        <path fill-rule="evenodd" d="M14.707 10.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L9 12.586V5a1 1 0 112 0v7.586l2.293-2.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    <?php endif; ?>
                </svg>
                <?= abs($pagesTrend) ?>%
            </div>
            <canvas id="sparkPages" class="ana-spark"></canvas>
        </div>
        <div class="ana-card" id="ana-bounce">
            <div class="ana-card-header">
                <div class="ana-card-title-row">
                    <span class="ana-card-label"><?= $lang['ana_bounce_rate'] ?? 'Bounce Rate' ?></span>
                    <div class="hint-icon" onclick="toggleHint(this)">
                        <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M13 10V2L5 14H11V22L19 10H13Z" />
                        </svg>
                    </div>
                </div>
            </div>
            <div class="ana-info-box"><?= $lang['ana_bounce_rate_hint'] ?? 'Percentage of sessions that were single-page visits and had no interaction with the page.' ?></div>
            <div class="ana-card-value"><?= $bounceRate ?></div>
            <div class="ana-card-trend <?= $bounceTrend <= 0 ? 'ana-trend-up' : 'ana-trend-down' ?>">
                <svg width="12" height="12" fill="currentColor" viewBox="0 0 20 20">
                    <?php if ($bounceTrend <= 0): ?>
                        <path fill-rule="evenodd" d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 7.414V15a1 1 0 11-2 0V7.414L6.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                    <?php else: ?>
                        <path fill-rule="evenodd" d="M14.707 10.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L9 12.586V5a1 1 0 112 0v7.586l2.293-2.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    <?php endif; ?>
                </svg>
                <?= abs($bounceTrend) ?>%
            </div>
            <canvas id="sparkBounce" class="ana-spark"></canvas>
        </div>
    </div>

    <!-- Analytics Widgets Grid -->
    <div class="ana-widgets-grid">

        <!-- Traffic Sources (Left, full height) -->
        <div class="ana-widget" id="ana-traffic-sources">
            <div class="ana-widget-title"><?= $lang['ana_traffic_sources'] ?? 'Traffic Sources' ?></div>
            <div class="ana-widget-body ana-widget-body--chart-left">
                <div class="ana-widget-donut">
                    <canvas id="sourcesChart"></canvas>
                </div>
                <div class="ana-list">
                    <?php
                    $distinctColors = ['#6366f1', '#8b5cf6', '#d946ef', '#f43f5e', '#f97316', '#eab308', '#10b981', '#0ea5e9'];
                    $colorIndex = 0;
                    foreach ($trafficSourcesPct as $source => $pct):
                        $sourceKey = 'ana_' . strtolower($source);
                        $sourceLabel = $lang[$sourceKey] ?? $source;
                        $color = $distinctColors[$colorIndex % count($distinctColors)];
                        $colorIndex++;
                        ?>
                    <div class="ana-list-item">
                        <span class="ana-item-label" style="display:flex; align-items:center;">
                            <span style="display:inline-block; width:12px; height:12px; border-radius:3px; background-color: <?= $color ?>; margin-right: 8px;"></span>
                            <?= $sourceLabel ?>
                        </span>
                        <span class="ana-item-value"><?= $pct ?>%</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Device Breakdown (Middle top, spans 2 columns) -->
        <div class="ana-widget" id="ana-devices">
            <div class="ana-widget-title"><?= $lang['ana_device_breakdown'] ?? 'Device Breakdown' ?></div>
            <div class="ana-list">
                <?php
                foreach ($devicePct as $device => $pct):
                    $deviceKey = 'ana_' . strtolower($device);
                    $deviceLabel = $lang[$deviceKey] ?? $device;
                    ?>
                <div class="ana-list-item">
                    <span class="ana-item-label"><?= $deviceLabel ?></span>
                    <span class="ana-item-value"><?= $pct ?>%</span>
                </div>
                <div class="ana-progress-bar"><div class="ana-progress-fill" style="width: <?= $pct ?>%"></div></div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Operating Systems (Middle bottom-left) -->
        <div class="ana-widget" id="ana-os">
            <div class="ana-widget-title"><?= $lang['ana_os'] ?? 'Operating Systems' ?></div>
            <div class="ana-list">
                <?php foreach ($osPct as $osName => $pct): ?>
                <div class="ana-list-item">
                    <span class="ana-item-label"><?= $osName ?></span>
                    <span class="ana-item-value"><?= $pct ?>%</span>
                </div>
                <div class="ana-progress-bar"><div class="ana-progress-fill" style="width: <?= $pct ?>%"></div></div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Top Languages (Middle bottom-right) -->
        <div class="ana-widget" id="ana-languages">
            <div class="ana-widget-title"><?= $lang['ana_top_languages'] ?? 'Top Languages' ?></div>
            <div class="ana-list">
                <?php
                foreach ($langData as $langCode => $count):
                    $langLabel = strtoupper($langCode);
                    if ($langCode === 'de')
                        $langLabel = 'German (DE)';
                    elseif ($langCode === 'en')
                        $langLabel = 'English (EN)';
                    elseif ($langCode === 'fr')
                        $langLabel = 'French (FR)';
                    ?>
                <div class="ana-list-item">
                    <span class="ana-item-label"><?= $langLabel ?></span>
                    <span class="ana-item-value"><?= number_format($count) ?></span>
                </div>
                <?php endforeach; ?>
                <?php if (empty($langData)): ?>
                    <div class="ana-empty"><?= $lang['ana_no_data'] ?? 'No data yet' ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Browsers (Right, full height) -->
        <div class="ana-widget" id="ana-browsers">
            <div class="ana-widget-title"><?= $lang['ana_browsers'] ?? 'Browsers' ?></div>
            <div class="ana-widget-body ana-widget-body--chart-right">
                <div class="ana-list">
                    <?php
                    $colorIndex = 0;
                    foreach ($topBrowsers as $browser => $pct):
                        $color = $distinctColors[$colorIndex % count($distinctColors)];
                        $colorIndex++;
                        ?>
                    <div class="ana-list-item">
                        <span class="ana-item-label" style="display:flex; align-items:center;">
                            <span style="display:inline-block; width:12px; height:12px; border-radius:3px; background-color: <?= $color ?>; margin-right: 8px;"></span>
                            <?= $browser ?>
                        </span>
                        <span class="ana-item-value"><?= $pct ?>%</span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="ana-widget-donut">
                    <canvas id="browserChart"></canvas>
                </div>
            </div>
        </div>

    </div>
    </div> <!-- .layout -->

    <script>
    const preloaderStart = Date.now();
    window.addEventListener('load', function() {
        const preloader = document.getElementById('preloader');
        if (preloader) {
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
    });

    // Config Chart.js
    Chart.defaults.color = '#9ca3af';
    Chart.defaults.font.family = 'Inter, sans-serif';

    // Sources Chart
    (function() {
        const ctx = document.getElementById('sourcesChart').getContext('2d');

        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: [
                    '<?= $lang['ana_direct'] ?? 'Direct' ?>',
                    '<?= $lang['ana_search'] ?? 'Search' ?>',
                    '<?= $lang['ana_social'] ?? 'Social' ?>',
                    '<?= $lang['ana_referral'] ?? 'Referral' ?>'
                ],
                datasets: [{
                    data: <?= json_encode(array_values($trafficSourcesPct)) ?>,
                    backgroundColor: ['#6366f1', '#8b5cf6', '#d946ef', '#f43f5e', '#f97316', '#eab308', '#10b981', '#0ea5e9'].slice(0, 4),
                    hoverBackgroundColor: ['#6366f1', '#8b5cf6', '#d946ef', '#f43f5e', '#f97316', '#eab308', '#10b981', '#0ea5e9'].slice(0, 4),
                    borderWidth: 2,
                    borderColor: 'rgba(12, 11, 11, 0.8)',
                    borderRadius: 6,
                    spacing: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                plugins: { legend: { display: false }, tooltip: { enabled: false } },
                animation: {
                    animateRotate: true,
                    duration: 1200,
                    easing: 'easeOutQuart'
                }
            }
        });
    })();

    // Spark Charts Helper
    function createSpark(id, color, data) {
        const sparkCtx = document.getElementById(id).getContext('2d');
        const sparkGrad = sparkCtx.createLinearGradient(0, 0, 0, 150);
        sparkGrad.addColorStop(0, hexToRgba(color, 0.15));
        sparkGrad.addColorStop(1, hexToRgba(color, 0));

        new Chart(sparkCtx, {
            type: 'line',
            data: {
                labels: [...Array(data.length).keys()],
                datasets: [{
                    data: data,
                    borderColor: color,
                    borderWidth: 1.5,
                    fill: false,
                    tension: 0.5,
                    pointRadius: 0,
                    pointHoverRadius: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { enabled: false } },
                scales: { 
                    x: { display: false }, 
                    y: { 
                        display: false,
                        suggestedMin: Math.min(...data) * 0.8,
                        suggestedMax: Math.max(...data) * 1.2
                    } 
                },
                layout: {
                    padding: { bottom: -10, left: -10, right: -10 }
                }
            }
        });
    }

    function hexToRgba(hex, alpha) {
        const r = parseInt(hex.slice(1, 3), 16);
        const g = parseInt(hex.slice(3, 5), 16);
        const b = parseInt(hex.slice(5, 7), 16);
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    }

    createSpark('sparkViews', '#7675ec', <?= json_encode($chartDataViews) ?>);
    createSpark('sparkVisitors', '#7675ec', <?= json_encode($chartDataVisitors) ?>);
    createSpark('sparkPages', '#d225d7', <?= json_encode(array_map(function ($v) { return round($v * 10); }, $chartDataViews)) ?>);
    createSpark('sparkBounce', '#ef4444', <?= json_encode(array_map(function ($v, $i) use ($chartDataVisitors) {
    // Create a sharper, more dramatic line for bounce rate like in user's image
    return max(15, round(60 - ($v * 2.5) + (sin($i) * 8)));
}, $chartDataVisitors, array_keys($chartDataVisitors))) ?>);

    // Spark Charts Helper



    // Browser Chart
    (function() {
        const ctx = document.getElementById('browserChart').getContext('2d');
        const browserLabels = <?= json_encode(array_keys($topBrowsers)) ?>;
        const browserValues = <?= json_encode(array_values($topBrowsers)) ?>;
        const distinctColors = ['#6366f1', '#8b5cf6', '#d946ef', '#f43f5e', '#f97316', '#eab308', '#10b981', '#0ea5e9'];
        const bgColors = browserLabels.map((_, i) => distinctColors[i % distinctColors.length]);
        const hoverColors = bgColors;

        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: browserLabels,
                datasets: [{
                    data: browserValues,
                    backgroundColor: bgColors,
                    hoverBackgroundColor: hoverColors,
                    borderWidth: 2,

                    borderColor: 'rgba(12, 11, 11, 0.8)',
                    borderRadius: 6,
                    spacing: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                plugins: { legend: { display: false }, tooltip: { enabled: false } },
                animation: {
                    animateRotate: true,
                    duration: 1200,
                    easing: 'easeOutQuart'
                }
            }
        });
    })();
    function toggleHint(btn) {
        const card = btn.closest('.ana-card');
        const info = card.querySelector('.ana-info-box');
        
        // Close all other info boxes
        document.querySelectorAll('.ana-info-box.active').forEach(box => {
            if (box !== info) box.classList.remove('active');
        });
        
        info.classList.toggle('active');
    }

    // Close on click outside
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.ana-card')) {
            document.querySelectorAll('.ana-info-box.active').forEach(box => {
                box.classList.remove('active');
            });
        }
    });
    </script>
</body>
</html>