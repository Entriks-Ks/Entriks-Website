<!DOCTYPE html>
<html>
<head>
    <title>Real-Time Views Monitor</title>
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%); 
            color: #fff; 
            padding: 20px; 
            margin: 0;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        h1 { 
            text-align: center; 
            font-size: 2.5rem; 
            margin-bottom: 10px;
            background: linear-gradient(135deg, #6366f1 0%, #d225d7 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .subtitle { text-align: center; color: #9ca3af; margin-bottom: 30px; }
        .card { 
            background: #262626; 
            border-radius: 16px; 
            padding: 24px; 
            margin-bottom: 20px; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.3);
        }
        .stat-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 16px; 
            margin-bottom: 20px;
        }
        .stat-card { 
            background: linear-gradient(135deg, #6366f1 0%, #a04ee1 100%); 
            padding: 20px; 
            border-radius: 12px; 
            text-align: center;
        }
        .stat-value { font-size: 2.5rem; font-weight: bold; margin: 10px 0; }
        .stat-label { font-size: 0.9rem; opacity: 0.9; }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 16px;
        }
        th, td { 
            padding: 12px; 
            text-align: left; 
            border-bottom: 1px solid #444;
        }
        th { 
            background: #333; 
            font-weight: 600;
            color: #6366f1;
        }
        .badge { 
            display: inline-block; 
            padding: 4px 12px; 
            border-radius: 20px; 
            font-size: 0.85rem; 
            font-weight: 600;
        }
        .badge-success { background: #10b981; color: white; }
        .badge-warning { background: #f59e0b; color: white; }
        .badge-info { background: #3b82f6; color: white; }
        .refresh-btn { 
            display: block; 
            margin: 20px auto; 
            padding: 12px 32px; 
            background: linear-gradient(135deg, #6366f1 0%, #d225d7 100%); 
            color: white; 
            border: none; 
            border-radius: 8px; 
            font-size: 1rem; 
            cursor: pointer; 
            transition: transform 0.2s;
        }
        .refresh-btn:hover { transform: translateY(-2px); }
        pre { 
            background: #1a1a1a; 
            padding: 16px; 
            border-radius: 8px; 
            overflow-x: auto; 
            font-size: 0.9rem;
            border-left: 4px solid #6366f1;
        }
        .chart-preview { 
            background: #1a1a1a; 
            padding: 20px; 
            border-radius: 8px; 
            margin-top: 16px;
        }
        .day-bar { 
            display: flex; 
            align-items: center; 
            margin: 8px 0;
        }
        .day-label { width: 60px; font-weight: 600; }
        .bar-container { 
            flex: 1; 
            background: #333; 
            height: 30px; 
            border-radius: 4px; 
            overflow: hidden; 
            position: relative;
        }
        .bar-fill { 
            height: 100%; 
            background: linear-gradient(90deg, #6366f1 0%, #d225d7 100%); 
            transition: width 0.3s;
            display: flex;
            align-items: center;
            padding: 0 10px;
            font-weight: 600;
        }
        .timestamp { 
            text-align: center; 
            color: #9ca3af; 
            font-size: 0.85rem; 
            margin-top: 20px;
        }
    </style>
</head>
<body>

<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require __DIR__ . "/database.php";

// Get current stats
$totalPosts = $db->blog->countDocuments();
$postsWithHistory = $db->blog->countDocuments(['views_history' => ['$exists' => true]]);
$totalViews = 0;
$allPosts = $db->blog->find([], ['projection' => ['views' => 1]]);
foreach ($allPosts as $post) {
    $totalViews += $post['views'] ?? 0;
}

// Calculate 7-day traffic
$trafficData = [];
$chartLabels = [];
$today = date('Y-m-d');

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dayLabel = date('D', strtotime("-$i days"));
    $chartLabels[] = $dayLabel;
    
    $posts = $db->blog->find([], ['projection' => ['views_history' => 1]]);
    $dailyViews = 0;
    
    foreach ($posts as $post) {
        if (isset($post['views_history'][$date])) {
            $dailyViews += (int)$post['views_history'][$date];
        }
    }
    
    $trafficData[] = $dailyViews;
}

$totalLast7Days = array_sum($trafficData);
$maxViews = max($trafficData) ?: 1;
?>

<div class="container">
    <h1>📊 Real-Time Views Monitor</h1>
    <p class="subtitle">Live MongoDB Analytics Dashboard</p>

    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-label">Total Posts</div>
            <div class="stat-value"><?= $totalPosts ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">All-Time Views</div>
            <div class="stat-value"><?= number_format($totalViews) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Last 7 Days</div>
            <div class="stat-value"><?= number_format($totalLast7Days) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Posts Tracking</div>
            <div class="stat-value"><?= $postsWithHistory ?></div>
        </div>
    </div>

    <div class="card">
        <h2>📈 7-Day Traffic Breakdown</h2>
        <div class="chart-preview">
            <?php for ($i = 0; $i < 7; $i++): ?>
                <?php 
                    $date = date('Y-m-d', strtotime("-" . (6-$i) . " days"));
                    $views = $trafficData[$i];
                    $percentage = ($views / $maxViews) * 100;
                ?>
                <div class="day-bar">
                    <div class="day-label"><?= $chartLabels[$i] ?></div>
                    <div class="bar-container">
                        <div class="bar-fill" style="width: <?= $percentage ?>%;">
                            <?= $views ?> views
                        </div>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
        
        <h3 style="margin-top: 30px;">Raw Chart Data:</h3>
        <pre><?php 
echo "Labels: " . json_encode($chartLabels) . "\n";
echo "Data:   " . json_encode($trafficData);
        ?></pre>
    </div>

    <div class="card">
        <h2>📝 Posts with Views History</h2>
        <?php 
        $postsWithData = $db->blog->find(
            ['views_history' => ['$exists' => true]], 
            ['limit' => 10, 'sort' => ['views' => -1]]
        );
        $postsArray = iterator_to_array($postsWithData);
        ?>
        
        <?php if (empty($postsArray)): ?>
            <div style="text-align: center; padding: 40px; color: #9ca3af;">
                <p>⚠️ No posts have views_history data yet.</p>
                <p style="margin-top: 16px;">Visit a blog post to start tracking!</p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Post Title</th>
                        <th>Total Views</th>
                        <th>Today</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($postsArray as $post): ?>
                    <tr>
                        <td><?= htmlspecialchars(substr($post['title'] ?? 'Untitled', 0, 50)) ?></td>
                        <td><strong><?= number_format($post['views'] ?? 0) ?></strong></td>
                        <td><strong><?= ($post['views_history'][$today] ?? 0) ?></strong></td>
                        <td>
                            <?php if (isset($post['views_history'][$today]) && $post['views_history'][$today] > 0): ?>
                                <span class="badge badge-success">Active Today</span>
                            <?php else: ?>
                                <span class="badge badge-info">Tracking</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>🔍 System Status</h2>
        <table>
            <tr>
                <td><strong>Implementation Status</strong></td>
                <td><span class="badge badge-success">✅ Active</span></td>
            </tr>
            <tr>
                <td><strong>blog.php View Counter</strong></td>
                <td><span class="badge badge-success">✅ Working</span></td>
            </tr>
            <tr>
                <td><strong>Dashboard Integration</strong></td>
                <td><span class="badge badge-success">✅ Connected</span></td>
            </tr>
            <tr>
                <td><strong>Today's Date</strong></td>
                <td><code><?= $today ?></code></td>
            </tr>
            <tr>
                <td><strong>Posts Tracking Daily</strong></td>
                <td><?= $postsWithHistory ?> / <?= $totalPosts ?></td>
            </tr>
        </table>
    </div>

    <button class="refresh-btn" onclick="location.reload()">🔄 Refresh Data</button>

    <div class="timestamp">
        Last updated: <?= date('Y-m-d H:i:s') ?> | 
        <a href="dashboard.php" style="color: #6366f1;">Go to Dashboard</a> | 
        <a href="debug_views.php" style="color: #6366f1;">Debug Report</a>
    </div>
</div>

</body>
</html>
