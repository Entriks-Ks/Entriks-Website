<?php
/** Dashboard Helper Functions */

/**
 * Gets views data for a range (OPTIMIZED with Aggregation)
 */
function getViewsData($db, $lang, $days, $labelFormat = 'D', $step = 1)
{
    $startDate = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
    $endDate = date('Y-m-d');

    $pipeline = [
        ['$match' => ['views_history' => ['$exists' => true]]],
        ['$project' => ['history' => ['$objectToArray' => '$views_history']]],
        ['$unwind' => '$history'],
        ['$match' => [
            'history.k' => ['$gte' => $startDate, '$lte' => $endDate],
            '$expr' => ['$eq' => [['$strLenCP' => '$history.k'], 10]]
        ]],
        ['$group' => [
            '_id' => '$history.k',
            'totalViews' => ['$sum' => '$history.v']
        ]]
    ];

    $results = $db->blog->aggregate($pipeline);
    $viewsMap = [];
    foreach ($results as $res) {
        $viewsMap[$res['_id']] = $res['totalViews'];
    }

    $labels = [];
    $data = [];
    for ($i = $days - 1; $i >= 0; $i -= $step) {
        $ts = strtotime("-$i days");
        $date = date('Y-m-d', $ts);
        $dayLabel = date($labelFormat, $ts);

        // Translate Day (D)
        if (strpos($labelFormat, 'D') !== false) {
            $en = date('D', $ts);
            $trans = $lang['day_' . strtolower($en)] ?? $en;
            $dayLabel = str_replace($en, $trans, $dayLabel);
        }

        // Translate Month (M)
        if (strpos($labelFormat, 'M') !== false) {
            $en = date('M', $ts);
            $trans = $lang['month_' . strtolower($en)] ?? $en;
            $dayLabel = str_replace($en, $trans, $dayLabel);
        }

        $labels[] = $dayLabel;
        $data[] = $viewsMap[$date] ?? 0;
    }
    return ['labels' => $labels, 'data' => $data];
}

/**
 * Main site views (OPTIMIZED with range query)
 */
function getMainViewsData($db, $lang, $days, $labelFormat = 'D', $step = 1)
{
    $startDate = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));

    $cursor = $db->main_views->find(['date' => ['$gte' => $startDate]]);
    $viewsMap = [];
    foreach ($cursor as $doc) {
        if (strlen($doc['date']) === 10) {
            $viewsMap[$doc['date']] = $doc['count'] ?? 0;
        }
    }

    $labels = [];
    $data = [];
    for ($i = $days - 1; $i >= 0; $i -= $step) {
        $ts = strtotime("-$i days");
        $date = date('Y-m-d', $ts);
        $dayLabel = date($labelFormat, $ts);

        // Translate Day (D)
        if (strpos($labelFormat, 'D') !== false) {
            $en = date('D', $ts);
            $trans = $lang['day_' . strtolower($en)] ?? $en;
            $dayLabel = str_replace($en, $trans, $dayLabel);
        }

        // Translate Month (M)
        if (strpos($labelFormat, 'M') !== false) {
            $en = date('M', $ts);
            $trans = $lang['month_' . strtolower($en)] ?? $en;
            $dayLabel = str_replace($en, $trans, $dayLabel);
        }

        $labels[] = $dayLabel;
        $data[] = $viewsMap[$date] ?? 0;
    }
    return ['labels' => $labels, 'data' => $data];
}

/**
 * Helper to format numbers (1K, 1M)
 */
function formatNumberShort($num)
{
    if ($num >= 1000000) {
        return round($num / 1000000, 1) . 'M';
    }
    if ($num >= 1000) {
        return round($num / 1000, 1) . 'K';
    }
    return number_format($num);
}
