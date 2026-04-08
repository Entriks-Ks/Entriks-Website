<?php

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;


require_once dirname(__DIR__) . '/session_config.php';
require '../database.php';
require '../config.php';


if (!class_exists('MongoDB\BSON\ObjectId')) {
    die('MongoDB PHP extension is not installed or properly configured');
}



if (!isset($_SESSION['admin'])) {
    header('Location: ../login.php');
    exit;
}



$userPerms = $_SESSION['admin']['permissions'] ?? [];
if ($userPerms instanceof \MongoDB\Model\BSONArray) {
    $userPerms = $userPerms->getArrayCopy();
} else {
    $userPerms = (array) $userPerms;
}

$isAdmin = ($_SESSION['admin']['role'] ?? 'admin') === 'admin';
$userRole = $_SESSION['admin']['position'] ?? 'Editor';
if ($isAdmin)

    $userRole = 'Admin';
$hasBlogAccess = $isAdmin;


// Only admin has access to the comments management page

if (!$hasBlogAccess) {
    header('Location: ../dashboard.php');
    exit;
}



if (!isset($db)) {
    die('Database connection not available');
}



$filterCategory = trim($_GET['filter_category'] ?? '');

$filterPost = trim($_GET['filter_post'] ?? '');

$filterBlogType = trim($_GET['filter_blog_type'] ?? '');

// Fetch all available blog types from database
$allBlogTypes = $db->blog->distinct('blog_type') ?: [];
$validBlogTypes = array_filter($allBlogTypes, function($type) {
    return !empty($type);
});

if (!in_array($filterBlogType, $validBlogTypes)) {
    $filterBlogType = '';

}


// Fetch posts and categories for filter dropdowns

$allPostsCursor = $db->blog->find([], ['sort' => ['created_at' => -1]]);
$allPosts = iterator_to_array($allPostsCursor);
$categoriesA = $db->blog->distinct('categories') ?: [];
$categoriesB = $db->blog->distinct('category') ?: [];
$allCategories = array_values(array_unique(array_merge(is_array($categoriesA) ? $categoriesA : [], is_array($categoriesB) ? $categoriesB : [])));


// Build aggregation pipeline with optional filters

$pipeline = [

    [

        '$lookup' => [

            'from' => 'blog',

            'localField' => 'post_id',

            'foreignField' => '_id',

            'as' => 'post_info'

        ]

    ],

    [

        '$addFields' => [

            'post_title' => [

                '$ifNull' => [

                    '$post_info.title_de',

                    [

                        '$ifNull' => [

                            '$post_info.title_en',

                            [

                                '$ifNull' => [

                                    '$post_info.title',

                                    ['Post Deleted']

                                ]

                            ]

                        ]

                    ]

                ]

            ]

        ]

    ]

];


// Apply post filter (by ObjectId) if provided

if ($filterPost !== '') {
    try {
        $postObj = new ObjectId($filterPost);
        $pipeline[] = ['$match' => ['post_id' => $postObj]];
    } catch (Exception $e) {
        // ignore invalid id

    }

} elseif ($filterCategory !== '') {
    // Match comments whose looked-up post contains the category

    $pipeline[] = [

        '$match' => [

            '$or' => [

                ['post_info.categories' => $filterCategory],

                ['post_info.category' => $filterCategory]

            ]

        ]

    ];
}

// Apply blog type filter if provided
if ($filterBlogType !== '') {
    $pipeline[] = [
        '$match' => [
            'post_info.blog_type' => $filterBlogType
        ]
    ];
}


// Final sort

// Pagination setup

$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$limit = 9;
$skip = ($page - 1) * $limit;


// Use Facet for count and data

$pipeline[] = [

    '$facet' => [

        'metadata' => [['$count' => 'total']],

        'data' => [

            ['$sort' => ['created_at' => -1]],

            ['$skip' => $skip],

            ['$limit' => $limit],

            [

                '$lookup' => [

                    'from' => 'comments',

                    'localField' => '_id',

                    'foreignField' => 'parent_id',

                    'as' => 'replies_list'

                ]

            ],

            [

                '$addFields' => [

                    'reply_count' => ['$size' => '$replies_list']

                ]

            ],

            [

                '$project' => ['replies_list' => 0]

            ]

        ]

    ]

];


// Execute aggregation

$result = $db->comments->aggregate($pipeline)->toArray();
$totalComments = $result[0]['metadata'][0]['total'] ?? 0;
$comments = $result[0]['data'] ?? [];
$totalPages = ceil($totalComments / $limit);


// Build map of parent comments (for reply indicators)

$parentIds = [];
foreach ($comments as $c) {
    if (!empty($c['parent_id']))

        $parentIds[(string) $c['parent_id']] = $c['parent_id'];
}

$parentMap = [];
if (!empty($parentIds)) {
    $parentObjIds = array_values(array_map(function ($v) {
        return $v instanceof ObjectId ? $v : new ObjectId((string) $v);
    }, $parentIds));
    $parentsCursor = $db->comments->find(['_id' => ['$in' => $parentObjIds]]);
    foreach ($parentsCursor as $p) {
        $parentMap[(string) $p['_id']] = $p;
    }

}

?>

<!DOCTYPE html>

<html>



<head>

    <meta charset="utf-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title><?= $lang['comments_title'] ?></title>

    <link rel="shortcut icon" href="<?= htmlspecialchars($siteFaviconUrl) ?>" type="image/x-icon">

    <link rel="stylesheet" href="../assets/css/dashboard.css?v=<?= time() ?>">

    <script src="../assets/js/global-search.js?v=<?= time() ?>" defer></script>



    <style>

        :root {
            --table-bg: #1a1a1a;
            --table-hover: rgba(255, 255, 255, 0.03);
            --text-main: #ffffff;
            --text-sub: #9ca3af;
            --border-color: rgba(255, 255, 255, 0.1);
            --primary-accent: #38bdf8;
            --status-bg-active: rgba(52, 211, 153, 0.1);
            --status-text-active: #34d399;
            --status-bg-pending: rgba(251, 191, 36, 0.1);
            --status-text-pending: #fbbf24;
        }



        /* Unified Pink Scrollbar */

        html::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }



        html::-webkit-scrollbar-track {
            background: #0f0f0f;
        }



        html::-webkit-scrollbar-thumb {
            background: #d225d7;
            border-radius: 10px;
            border: 2px solid #0f0f0f;
        }



        html::-webkit-scrollbar-thumb:hover {
            background: #e82be0;
        }



        body {
            margin: 0;
            padding: 0;
        }



        .comments-table-wrapper {
            background: var(--table-bg);
            border-radius: 16px;
            padding: 0;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            margin-bottom: 30px;
            position: relative;
        }



        .comments-table-scroll {
            overflow-x: auto;
            border-radius: 16px;
            scrollbar-width: thin;
            scrollbar-color: #d225d7 rgba(255, 255, 255, 0.05);
        }



        /* WebKit browsers (Chrome, Safari, Edge) */

        .comments-table-scroll::-webkit-scrollbar {
            height: 8px;
        }



        .comments-table-scroll::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
        }



        .comments-table-scroll::-webkit-scrollbar-thumb {
            background: #d225d7;
            border-radius: 10px;
            transition: background 0.3s;
        }



        .comments-table-scroll::-webkit-scrollbar-thumb:hover {
            background: #b01eb4;
        }



        .comments-table {
            width: 100%;
            min-width: 1400px;
            border-collapse: collapse;
            margin-top: 0;
        }



        @media (max-width: 700px) {
            .comments-table {
                min-width: unset;
            }

            .comments-table td, .comments-table th {
                padding: 20px 12px;
                font-size: 14px;
            }

        }



        .comments-table thead tr {
            background: #1e1e1e;
        }



        .comments-table th {
            font-weight: 600;
            font-size: 14px;
            color: #fff;
            text-align: left;
            padding: 12px 24px;


            border-bottom: 1px solid rgba(255, 255, 255, 0.12);
            white-space: nowrap;
        }



        .comments-table tbody tr {
            border-bottom: 1px solid var(--border-color);
            transition: all 0.2s ease;
        }



        .comments-table tbody tr:last-child {
            border-bottom: none;
        }



        .comments-table tbody tr:hover {
            background: var(--table-hover);
        }



        .comments-table td {
            padding: 24px;
            /* Increased from 20px 24px for 'huger' rows */

            color: var(--text-main);
            font-size: 14px;
            vertical-align: middle;
            transition: background 0.2s;
        }

        /* Checkbox Style */

        .custom-checkbox {
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            position: relative;
        }

        .custom-checkbox input {
            appearance: none;
            -webkit-appearance: none;
            width: 18px;
            height: 18px;
            border: 2px solid #52525b;
            border-radius: 5px;
            background: transparent;
            /* Default un-checked background */

            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }



        .custom-checkbox input:checked {
            background: linear-gradient(135deg, #38bdf8, #60a5fa);
            border-color: #38bdf8;
        }
        .custom-checkbox input:checked::after {
            content: '';
            position: absolute;
            left: 50%;
            top: 48%;
            /* Visually centered */

            width: 4px;
            height: 8px;
            border: solid white;
            border-width: 0 2px 2px 0;
            transform: translate(-50%, -50%) rotate(45deg);
        }
        /* Author Column */

        .author-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .author-avatar {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: linear-gradient(135deg, #6366f1, #d225d7);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: white;
            font-size: 14px;
            box-shadow: 0 4px 10px rgba(99, 102, 241, 0.2);
        }
        .author-text {
            display: flex;
            flex-direction: column;
        }
        .author-name {
            font-weight: 600;
            color: var(--text-main);
        }
        .author-email {
            font-size: 13px;
            color: var(--text-sub);
        }
        /* Comment Preview */

        .comment-preview {
            max-width: 100%;
            color: #fff;
            font-size: 13.5px;
            line-height: 1.6;
            padding: 0;
            word-break: break-word;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            display: -webkit-box;
            display: -moz-box;
            display: -ms-flexbox;
            display: box;
            -webkit-box-orient: vertical;
            -moz-box-orient: vertical;
            box-orient: vertical;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            max-height: 3.2em;
            text-overflow: ellipsis;
        }
        .comment-preview-bg {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            padding: 10px 14px;
            display: block;
            width: 100%;
        }
        .post-preview {
            max-width: 100%;
            color: #d1d5db;
            font-size: 13.5px;
            line-height: 1.6;
            padding: 0;
            word-break: break-word;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            display: -webkit-box;
            display: -moz-box;
            display: -ms-flexbox;
            display: box;
            -webkit-box-orient: vertical;
            -moz-box-orient: vertical;
            box-orient: vertical;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            max-height: 3.2em;
            text-overflow: ellipsis;
        }        .reply-indicator {
            font-size: 11px;
            color: #38bdf8;
            background: rgba(56, 189, 248, 0.1);
            padding: 2px 8px;
            border-radius: 12px;
            display: inline-block;
            margin-bottom: 4px;
        }
        /* Status Badge */

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .status-active {
            background: var(--status-bg-active);
            color: var(--status-text-active);
        }
        .status-badge::before {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
        }
        /* Actions */

        .action-btn {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            cursor: pointer;
            padding: 8px;
            border-radius: 10px;
            color: #d1d5db;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .action-btn:hover {
            background: rgba(56, 189, 248, 0.1);
            color: var(--primary-accent);
            border-color: rgba(56, 189, 248, 0.2);
        }
        .action-btn.delete:hover {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border-color: rgba(239, 68, 68, 0.2);
        }        /* Modal Styles */

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(2px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }
        .modal-overlay.active {
            display: flex;
        }
        .modal-card-flow {
            width: 440px;
            background: #1a1a1a;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 0;
            text-align: left;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            transform: scale(0.95) translateY(20px);
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            overflow: hidden;
            color: #fff;
        }
        .modal-overlay.active .modal-card-flow {
            transform: scale(1) translateY(0);
            opacity: 1;
        }
        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(255, 255, 255, 0.02);
        }
        .modal-title {
            font-size: 16px;
            font-weight: 600;
            color: white;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .modal-close {
            background: none;
            border: none;
            padding: 4px;
            cursor: pointer;
            color: #9ca3af;
            transition: color 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-close:hover {
            color: white;
        }
        .modal-body {
            padding: 24px;
            font-size: 14px;
            line-height: 1.6;
            color: #e5e7eb;
        }
        .modal-footer {
            padding: 16px 24px;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            background: #232323;
            border-top: 1px solid #333;
        }
        .btn-cancel,

        .btn-delete {
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 500;
            font-size: 14px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-cancel {
            background: #e5e7eb;
            color: #1f2937;
        }
        .btn-cancel:hover {
            background: #d1d5db;
        }
        .btn-delete {
            background: #6366f1;
            color: white;
        }
        .btn-delete:hover {
            background: #4f46e5;
        }
        .btn-delete.danger {
            background: #ef4444;
        }
        .btn-delete.danger:hover {
            background: #dc2626;
        }
        /* Pagination */

        .pagination-wrapper {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 20px;
            gap: 12px;
            flex-wrap: wrap;
        }
        .pagination-info {
            color: #888;
            font-size: 14px;
        }
        .pagination-controls {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
        }
        .page-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            padding: 0 10px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.04);
            color: #9ca3af;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid rgba(255, 255, 255, 0.08);
            transition: background 0.15s, color 0.15s, border-color 0.15s;
        }
        .page-btn:hover {
            background: rgba(255, 255, 255, 0.08);
            color: #fff;
            border-color: rgba(255, 255, 255, 0.15);
        }
        .page-btn.active {
            background: linear-gradient(135deg, #6366f1, #a855f7);
            color: #fff;
            border-color: transparent;
        }
        .page-btn.disabled {
            opacity: 0.3;
            pointer-events: none;
        }
        @media (max-width: 900px) {
            .pagination-wrapper {
                flex-direction: column;
                align-items: center;
            }

            .pagination-info {
                width: 100%;
                text-align: center;
            }

        }
        .bulk-actions-bar {
            position: fixed;
            bottom: 32px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: #1a1a1a;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 100px;
            padding: 8px 12px 8px 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.6);
            z-index: 9990;
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            opacity: 0;
            pointer-events: none;
            backdrop-filter: blur(12px);
            width: max-content;
        }
        .bulk-actions-bar.active {
            transform: translateX(-50%) translateY(0);
            opacity: 1;
            pointer-events: all;
        }
        .bulk-info {
            font-weight: 600;
            color: #fff;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .bulk-info::before {
            display: none;
            /* Removed circle */

        }
        .bulk-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .bulk-btn-delete {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.2);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .bulk-btn-delete:hover {
            background: rgba(239, 68, 68, 0.2);
            border-color: rgba(239, 68, 68, 0.3);
        }
        .bulk-close {
            background: transparent;
            border: none;
            color: #9ca3af;
            cursor: pointer;
            padding: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            margin-left: 4px;
            border-left: 1px solid rgba(255, 255, 255, 0.1);
            padding-left: 12px;
        }
        .bulk-close:hover {
            color: #fff;
        }



        .pagination-controls {
            display: flex;
            gap: 6px;
        }



        .page-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            padding: 0 10px;
            border-radius: 8px;
            background: transparent;
            color: #9ca3af;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            border: none;
            transition: color 0.15s;
        }
        .page-btn:hover {
            color: #ffffff;
        }
        .page-btn.active {
            background: linear-gradient(135deg, #6366f1, #a855f7);
            color: #ffffff;
            border-radius: 8px;
        }
        @media (max-width: 900px) {
            .comments-table-wrapper {
                border-radius: 10px;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            .comments-table {
                min-width: 1400px;
            }
            .pagination-wrapper {
                flex-direction: column;
                gap: 12px;
                align-items: flex-start;
            }

        }

        @media (max-width: 600px) {
            .pagination-wrapper {
                justify-content: center;
                align-items: center;
                padding-top: 16px;
            }
            .pagination-info {
                width: 100%;
                text-align: center;
                margin-bottom: 12px;
            }
            .pagination-controls {
                justify-content: center;
                width: 100%;
            }
            .page-btn {
                min-width: 40px;
                height: 40px;
                font-size: 14px;
            }

        }
        /* Vote Pills */

        .vote-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        :root {
            --panel-bg: #1a1a1a;
            --border-color: rgba(255, 255, 255, 0.08);
            --border-color-strong: rgba(255, 255, 255, 0.12);
            --primary-accent: #6366f1;
            --primary-gradient: linear-gradient(135deg, #6366f1, #a855f7);
            --text-main: #f3f4f6;
            --text-sub: #9ca3af;
            --card-bg: #222222;
        }
        .avatar-circle {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 18px;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
            flex-shrink: 0;
        }
        .custom-checkbox input:checked {
            background: var(--primary-gradient);
            border-color: var(--primary-accent);
        }
        .bulk-actions-bar {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #1a1a1a;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 12px 24px;
            display: flex;
            align-items: center;
            gap: 20px;
            z-index: 1000;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
            transform: translateY(120%);
            transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            backdrop-filter: blur(10px);
        }
        @media (max-width: 600px) {
            .bulk-actions-bar {
                left: 50%;
                right: auto;
                transform: translateX(-50%) translateY(120%);
                bottom: 20px;
                padding: 10px 16px;
                gap: 12px;
            }

            .bulk-actions-bar.active {
                transform: translateX(-50%) translateY(0);
            }

        }
        .vote-pill.like {
            background: rgba(34, 197, 94, 0.1);
            color: #22c55e;
            border: 1px solid rgba(34, 197, 94, 0.15);
        }
        .vote-pill.dislike {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.15);
        }
        .vote-pill svg {
            width: 14px;
            height: 14px;
        }
        /* Filter button */

        .filter-action-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            height: 42px;
            padding: 0 16px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.06);
            color: #d1d5db;
            font-size: 14px;
            font-weight: 500;
            border: 1px solid rgba(255, 255, 255, 0.1);
            cursor: pointer;
            transition: background 0.15s, border-color 0.15s;
        }
        .filter-action-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.18);
            color: #fff;
        }

    </style>

</head>
<body>

    <!-- Preloader -->

    <div class="preloader" id="preloader">

        <div class="preloader-spinner"></div>

    </div>
    <div class="layout">
        <?php $sidebarVariant = 'blog';
        $activeMenu = 'comments';
        include __DIR__ . '/../partials/sidebar.php'; ?>
        <?php

        $toast_message = $_SESSION['toast_message'] ?? null;
        $toast_type = $_SESSION['toast_type'] ?? 'success';
        unset($_SESSION['toast_message'], $_SESSION['toast_type']);
        ?>
        <main class="content">
            <!-- Blur Background Theme -->
            <div class="blur-bg-theme bottom-right"></div>
            <div class="blur-bg-theme top-left"></div>

            <?php

            $pageTitle = $lang['menu_comments'];
            include __DIR__ . '/../partials/topbar.php';
            ?>
            <div style="margin-bottom:24px; display:flex; justify-content: space-between; align-items:center;">

                <div style="display:flex; gap:12px;">

                    <!-- Could add bulk actions here later -->

                </div>

                <div style="display:flex; gap:12px; align-items:center;">

                    <!-- Bulk Buttons Removed -->
                    <div class="filter-wrapper" style="position: relative;">

                        <button class="filter-action-btn" id="filterBtn">

                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="18"

                                height="18">

                                <path

                                    d="M18.75 12.75h1.5a.75.75 0 0 0 0-1.5h-1.5a.75.75 0 0 0 0 1.5ZM12 6a.75.75 0 0 1 .75-.75h7.5a.75.75 0 0 1 0 1.5h-7.5A.75.75 0 0 1 12 6ZM12 18a.75.75 0 0 1 .75-.75h7.5a.75.75 0 0 1 0 1.5h-7.5A.75.75 0 0 1 12 18ZM3.75 6.75h1.5a.75.75 0 1 0 0-1.5h-1.5a.75.75 0 0 0 0 1.5ZM5.25 18.75h-1.5a.75.75 0 0 1 0-1.5h1.5a.75.75 0 0 1 0 1.5ZM3 12a.75.75 0 0 1 .75-.75h7.5a.75.75 0 0 1 0 1.5h-7.5A.75.75 0 0 1 3 12ZM9 3.75a2.25 2.25 0 1 0 0 4.5 2.25 2.25 0 0 0 0-4.5ZM12.75 12a2.25 2.25 0 1 1 4.5 0 2.25 2.25 0 0 1-4.5 0ZM9 15.75a2.25 2.25 0 1 0 0 4.5 2.25 2.25 0 0 0 0-4.5Z" />

                            </svg>

                            <?= $lang['action_filters'] ?>

                        </button>

                        <div class="filters-dropdown" id="filtersDropdown"

                            style="display:none; position:fixed; background:#1a1a1a; border:1px solid rgba(255, 255, 255, 0.1); border-radius:16px; padding:24px; min-width:320px; z-index:9999; box-shadow:0 25px 50px rgba(0,0,0,0.6); backdrop-filter: blur(10px);">

                            <div class="filter-group-title"

                                style="margin-bottom:20px; font-weight:700; color:white; font-size:16px;">

                                <?= $lang['filter_comments_title'] ?></div>
                            <form method="GET" action="comments.php">

                                <div class="filter-section">

                                    <div class="filter-subtitle"

                                        style="color:#9ca3af; font-size:12px; margin-bottom:6px; font-weight:600;">

                                        <?= $lang['filter_category_subtitle'] ?></div>

                                    <select name="filter_category" class="filter-select"

                                        style="width:100%; background:#1a1a1a; border:1px solid #3f3f46; color:white; padding:10px; border-radius:10px; outline:none;">

                                        <option value=""><?= $lang['filter_all_categories'] ?></option>

                                        <?php foreach ($allCategories as $cat): ?>

                                            <option value="<?= htmlspecialchars($cat) ?>" <?= ($filterCategory === $cat) ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>
                                <div class="filter-section" style="margin-top: 16px;">

                                    <div class="filter-subtitle"

                                        style="color:#9ca3af; font-size:12px; margin-bottom:6px; font-weight:600;">

                                        <?= $lang['filter_blog_type_subtitle'] ?? 'Blog Section' ?></div>

                                    <select name="filter_blog_type" class="filter-select"

                                        style="width:100%; background:#1a1a1a; border:1px solid #3f3f46; color:white; padding:10px; border-radius:10px; outline:none;">

                                        <option value=""><?= $lang['filter_all_sections'] ?? 'All Sections' ?></option>

                                        <?php foreach ($validBlogTypes as $blogType): ?>
                                            <option value="<?= htmlspecialchars($blogType) ?>" <?= ($filterBlogType === $blogType) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $blogType))) ?>
                                            </option>
                                        <?php endforeach; ?>

                                    </select>

                                </div>
                                <div class="filter-actions" style="margin-top:24px; display:flex; gap:12px;">

                                    <button type="submit"

                                        style="flex: 1; padding: 10px; border-radius: 10px; background: var(--primary-accent); color: white; border: none; cursor: pointer; font-weight: 600; font-size:14px;"><?= $lang['action_apply'] ?></button>

                                    <a href="comments.php"

                                        style="padding: 10px 16px; border-radius: 10px; background: #3f3f46; color: white; text-decoration: none; font-size: 14px; display: flex; align-items: center; font-weight:500;"><?= $lang['action_clear'] ?></a>

                                </div>

                            </form>

                        </div>

                    </div>

                </div>

            </div>
            <script>

                // Toggle Filter Dropdown

                const filterBtn = document.getElementById('filterBtn');
                const filtersDropdown = document.getElementById('filtersDropdown');


                if (filterBtn && filtersDropdown) {
                    filterBtn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        if (filtersDropdown.style.display === 'block') {
                            filtersDropdown.style.display = 'none';
                            filterBtn.classList.remove('active');
                        } else {
                            const rect = filterBtn.getBoundingClientRect();
                            filtersDropdown.style.top = (rect.bottom + 12) + 'px';
                            filtersDropdown.style.right = (window.innerWidth - rect.right) + 'px';
                            filtersDropdown.style.left = 'auto';
                            filtersDropdown.style.display = 'block';
                            filterBtn.classList.add('active');
                        }

                    });


                    document.addEventListener('click', (e) => {
                        if (!filterBtn.contains(e.target) && !filtersDropdown.contains(e.target)) {
                            filtersDropdown.style.display = 'none';
                            filterBtn.classList.remove('active');
                        }

                    });
                }

            </script>



            <div class="comments-table-wrapper" id="tour-comments-table">

                <div class="comments-table-scroll">

                <table class="comments-table">

                    <thead>

                        <tr>

                            <th style="width: 50px;">

                                <label class="custom-checkbox">

                                    <input type="checkbox" id="selectAll">

                                </label>

                            </th>

                            <th style="min-width: 200px;"><?= $lang['th_author'] ?></th>

                            <th style="min-width: 400px;"><?= $lang['th_comment'] ?></th>

                            <th style="min-width: 150px;"><?= $lang['th_votes'] ?></th>

                            <th style="min-width: 300px;"><?= $lang['th_post'] ?></th>

                            <th style="min-width: 120px;"><?= $lang['th_date'] ?></th>



                            <th style="width: 80px; text-align:right; padding-right:12px;"><?= $lang['th_actions'] ?>

                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php if (empty($comments)): ?>

                            <tr>

                                <td colspan="7" style="text-align: center; padding: 60px; color: rgba(255,255,255,0.4);">

                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"

                                        stroke-width="1.5" stroke="currentColor"

                                        style="width:48px;height:48px;display:block;margin:0 auto 16px;opacity:0.5;">

                                        <path stroke-linecap="round" stroke-linejoin="round"

                                            d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z" />

                                    </svg>

                                    <?= $lang['no_comments_found'] ?>

                                </td>

                            </tr>

                        <?php else: ?>

                            <?php

                            foreach ($comments as $comment):

                                $name = $comment['name'] ?? 'Anonymous';
                                $initials = strtoupper(substr($name, 0, 1));
                                ?>

                                <tr data-reply-count="<?= $comment['reply_count'] ?? 0 ?>">

                                    <td style="padding-left:8px;">

                                        <label class="custom-checkbox">

                                            <input type="checkbox" name="comment_ids[]" value="<?= (string) $comment['_id'] ?>">

                                        </label>

                                    </td>

                                    <td>

                                        <div class="author-info">

                                            <div class="author-avatar"><?= $initials ?></div>

                                            <div class="author-text">

                                                <span class="author-name"><?= htmlspecialchars($name) ?></span>

                                                <span

                                                    class="author-email"><?= htmlspecialchars($comment['email'] ?? 'N/A') ?></span>

                                            </div>

                                        </div>

                                    </td>

                                    <td style="max-width: none;">

                                        <?php

                                        $fullComment = $comment['message'] ?? ($comment['comment'] ?? '');


                                        // Reply indicator

                                        if (!empty($comment['parent_id'])):

                                            $parentKey = (string) $comment['parent_id'];
                                            $parentInfo = $parentMap[$parentKey] ?? null;
                                            if ($parentInfo):

                                                $parentAuthor = htmlspecialchars($parentInfo['name'] ?? 'Anonymous');
                                                echo '<div class="reply-indicator">' . $lang['reply_to'] . ' ' . $parentAuthor . '</div>';
                                            else:

                                                echo '<div class="reply-indicator">' . $lang['reply_to_deleted'] . '</div>';
                                            endif;
                                        endif;
                                        ?>

                                        <span class="comment-preview-bg">

                                            <span class="comment-preview" title="<?= htmlspecialchars($fullComment) ?>">

                                                <?= htmlspecialchars($fullComment) ?>

                                            </span>

                                        </span>

                                    </td>

                                    <td>

                                        <div style="display:flex; gap:8px; align-items:center;">

                                            <div class="vote-pill like">

                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">

                                                    <path

                                                        d="M1 21h4V9H1v12zm22-11c0-1.1-.9-2-2-2h-6.31l.95-4.57.03-.32c0-.41-.17-.79-.44-1.06L14.17 1 7.59 7.59C7.22 7.95 7 8.45 7 9v10c0 1.1.9 2 2 2h9c.83 0 1.54-.5 2.14-1.17l4.35-10.16c.12-.28.17-.59.17-.89V10z" />

                                                </svg>

                                                <?= number_format($comment['likes'] ?? 0) ?>

                                            </div>

                                            <div class="vote-pill dislike">

                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">

                                                    <path

                                                        d="M15 3H6c-.83 0-1.54.5-2.14 1.17L.51 14.33c-.12.28-.17.59-.17.89V16c0 1.1.9 2 2 2h6.31l-.95 4.57-.03.32c0 .41.17.79.44 1.06L8.83 23l6.59-6.59c.36-.36.58-.86.58-1.41V5c0-1.1-.9-2-2-2zm4 0v12h4V3h-4z" />

                                                </svg>

                                                <?= number_format($comment['dislikes'] ?? 0) ?>

                                            </div>

                                        </div>

                                    </td>

                                    <td style="max-width: none;">

                                        <?php if (!empty($comment['post_info'][0])): ?>

                                            <a href="../../blog.php?id=<?= (string) ($comment['post_id']) ?>" target="_blank" style="text-decoration: none;">

                                                <span class="post-preview" title="<?= htmlspecialchars($comment['post_title'][0] ?? ($lang['unknown_post'] ?? 'Unknown Post')) ?>">

                                                    <?= htmlspecialchars($comment['post_title'][0] ?? ($lang['unknown_post'] ?? 'Unknown Post')) ?>

                                                </span>

                                            </a>

                                        <?php else: ?>

                                            <span class="post-preview" style="color: rgba(255,255,255,0.3); font-style:italic;">

                                                <?= $lang['post_deleted'] ?>

                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    <td style="color:#d1d5db; font-size:13px;">

                                        <?php

                                        if (isset($comment['created_at']) && $comment['created_at'] instanceof UTCDateTime) {
                                            echo $comment['created_at']->toDateTime()->format('M d, Y');
                                        } else {
                                            echo 'N/A';
                                        }

                                        ?>

                                    </td>



                                    <td style="text-align:right; padding-right:24px;">

                                        <div style="display:inline-flex; gap:4px;">

                                            <button class="action-btn delete"

                                                onclick="confirmDeleteComment('<?= (string) $comment['_id'] ?>', '<?= addslashes($comment['name'] ?? 'Anonymous') ?>')"

                                                title="<?= $lang['action_delete'] ?>">

                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"

                                                    width="18" height="18">

                                                    <path fill-rule="evenodd"

                                                        d="M16.5 4.478v.227a48.816 48.816 0 0 1 3.878.512.75.75 0 1 1-.256 1.478l-.209-.035-1.005 13.07a3 3 0 0 1-2.991 2.77H8.084a3 3 0 0 1-2.991-2.77L4.087 6.66l-.209.035a.75.75 0 0 1-.256-1.478A48.567 48.567 0 0 1 7.5 4.705v-.227c0-1.564 1.213-2.9 2.816-2.951a52.662 52.662 0 0 1 3.369 0c1.603.051 2.815 1.387 2.815 2.951Zm-6.136-1.452a51.196 51.196 0 0 1 3.273 0C14.39 3.05 15 3.684 15 4.478v.113a49.488 49.488 0 0 0-6 0v-.113c0-.794.609-1.428 1.364-1.452Zm-.355 5.945a.75.75 0 1 0-1.5.058l.347 9a.75.75 0 1 0 1.499-.058l-.346-9Zm5.48.058a.75.75 0 1 0-1.498-.058l-.347 9a.75.75 0 0 0 1.5.058l.345-9Z"

                                                        clip-rule="evenodd" />

                                                </svg>

                                            </button>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </tbody>

                </table>

                </div>

            </div>



            <!-- Pagination -->

            <?php if ($totalComments > 0): ?>

                <div class="pagination-wrapper">

                    <div class="pagination-info">

                        <?= $lang['pagination_showing'] ?> <span

                            style="font-weight: 600; color: white;"><?= $skip + 1 ?>-<?= min($skip + count($comments), $totalComments) ?></span>

                        <?= $lang['pagination_of'] ?> <span

                            style="font-weight: 600; color: white;"><?= $totalComments ?></span>

                        <?= $lang['pagination_entries'] ?>

                    </div>

                    <div class="pagination-controls">



                        <?php

                        $queryParams = $_GET;
                        unset($queryParams['page']);
                        $queryString = http_build_query($queryParams);
                        $baseUrl = '?' . ($queryString ? $queryString . '&' : '');
                        ?>



                        <!-- Previous -->

                        <?php if ($page > 1): ?>

                            <a href="<?= $baseUrl ?>page=<?= $page - 1 ?>" class="page-btn"

                                style="min-width:40px; padding:0; justify-content:center;">&lt;</a>

                        <?php else: ?>

                            <span class="page-btn disabled"

                                style="opacity:0.3; min-width:40px; padding:0; justify-content:center;">&lt;</span>

                        <?php endif; ?>



                        <!-- Numbers -->

                        <?php

                        $range = 2;  // How many pages to show around current page

                        $showDots = true;


                        for ($i = 1; $i <= $totalPages; $i++) {
                            if ($i == 1 || $i == $totalPages || ($i >= $page - $range && $i <= $page + $range)) {
                                echo '<a href="' . $baseUrl . 'page=' . $i . '" class="page-btn ' . ($i == $page ? 'active' : '') . '">' . $i . '</a>';
                                $showDots = true;
                            } elseif ($showDots) {
                                echo '<span class="page-btn disabled" style="background:transparent; border:none;">...</span>';
                                $showDots = false;
                            }

                        }

                        ?>



                        <!-- Next -->

                        <?php if ($page < $totalPages): ?>

                            <a href="<?= $baseUrl ?>page=<?= $page + 1 ?>" class="page-btn"

                                style="min-width:40px; padding:0; justify-content:center;">&gt;</a>

                        <?php else: ?>

                            <span class="page-btn disabled"

                                style="opacity:0.3; min-width:40px; padding:0; justify-content:center;">&gt;</span>

                        <?php endif; ?>

                    </div>

                </div>

            <?php endif; ?>



        </main>

    </div>



    <!-- Delete Modal -->

    <div id="deleteModal" class="modal-overlay">

        <div class="modal-card-flow">

            <div class="modal-header">

                <h3 class="modal-title">

                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"

                        stroke="currentColor" width="20" height="20" style="color: #ef4444;">

                        <path stroke-linecap="round" stroke-linejoin="round"

                            d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />

                    </svg>

                    <span class="modal-title-text">Delete Comment?</span>

                </h3>

                <button class="modal-close" onclick="closeDeleteModal()">

                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"

                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20">

                        <line x1="18" y1="6" x2="6" y2="18"></line>

                        <line x1="6" y1="6" x2="18" y2="18"></line>

                    </svg>

                </button>

            </div>

            <div class="modal-body">

                Are you sure you want to delete this comment? This action cannot be undone.

            </div>

            <div class="modal-footer">

                <button class="btn-cancel" onclick="closeDeleteModal()">Cancel</button>

                <button id="confirmDeleteBtn" class="btn-delete danger" onclick="proceedDeleteComment()">Delete</button>

            </div>

        </div>

    </div>



    <!-- Bulk Action Floating Bar -->

    <div class="bulk-actions-bar" id="bulkActionsBar">

        <div class="bulk-info">

            <span id="bulkSelectedCount">0</span> <?= $lang['label_selected'] ?>

        </div>

        <div class="bulk-actions">

            <button class="bulk-btn-delete" onclick="confirmBulkDelete()">

                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"

                    stroke="currentColor" width="16" height="16">

                    <path stroke-linecap="round" stroke-linejoin="round"

                        d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />

                </svg>

                <?= $lang['action_delete'] ?>

            </button>

            <button class="bulk-close" onclick="clearSelection()" title="Clear Selection">

                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"

                    stroke="currentColor" width="16" height="16">

                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />

                </svg>

            </button>

        </div>

    </div>



    <script>

        let deleteCommentId = null;


        function confirmDeleteComment(commentId, authorName, btnElement) {
            deleteCommentId = commentId;
            isBulkDelete = false;


            // Find reply count from the row

            const row = document.querySelector(`tr input[value="${commentId}"]`)?.closest('tr');
            const replyCount = row ? parseInt(row.getAttribute('data-reply-count') || '0') : 0;


            // Reset modal content

            document.querySelector('.modal-title-text').textContent = "<?= addslashes($lang['comment_delete_title']) ?>";
            let bodyHtml = "<?= addslashes($lang['comment_delete_confirm']) ?>";


            if (replyCount > 0) {
                bodyHtml += `<br><span style="color:#ef4444; font-size:13px; font-weight:600; margin-top:12px; display:block; background:rgba(239,68,68,0.1); padding:8px; border-radius:6px; border:1px solid rgba(239,68,68,0.2);"><?= addslashes($lang['comment_replies_warning']) ?></span>`.replace('%d', replyCount);
            }



            document.querySelector('.modal-body').innerHTML = bodyHtml;
            document.getElementById('confirmDeleteBtn').textContent = "<?= addslashes($lang['action_delete']) ?>";


            const deleteModal = document.getElementById('deleteModal');
            deleteModal.classList.add('active');
        }



        function closeDeleteModal() {
            const deleteModal = document.getElementById('deleteModal');
            deleteModal.classList.remove('active');
            setTimeout(() => { deleteCommentId = null; }, 200);
        }



        function proceedDeleteComment() {
            if (isBulkDelete) {
                proceedBulkDelete();
                return;
            }



            if (!deleteCommentId) return;


            const formData = new FormData();
            formData.append('comment_id', deleteCommentId);


            fetch('delete_comment.php', {
                method: 'POST',

                body: formData

            })

                .then(response => {
                    if (response.redirected) {
                        window.location.href = response.url;
                    } else {
                        window.location.reload();
                    }

                })

                .catch(error => {
                    console.error('Error:', error);
                    showToast("<?= addslashes($lang['msg_status_fail'] ?? 'Failed to delete comment') ?>", 'error');
                });
        }



        let isBulkDelete = false;


        // Duplicate confirmedBulkDelete Removed



        function proceedBulkDelete() {
            const checked = document.querySelectorAll('input[name="comment_ids[]"]:checked');
            const formData = new FormData();
            checked.forEach(cb => formData.append('comment_ids[]', cb.value));


            fetch('delete_comment.php', {
                method: 'POST',

                body: formData

            })

                .then(response => {
                    if (response.redirected) {
                        window.location.href = response.url;
                    } else {
                        window.location.reload();
                    }

                })

                .catch(error => {
                    console.error('Error:', error);
                    showToast("<?= addslashes($lang['msg_status_fail'] ?? 'Failed to delete comments') ?>", 'error');
                });
        }



        // Checkbox Logic

        const selectAllInfo = document.getElementById('selectAll');
        const commentCheckboxes = document.querySelectorAll('input[name="comment_ids[]"]');
        const bulkActionsBar = document.getElementById('bulkActionsBar');
        const bulkSelectedCount = document.getElementById('bulkSelectedCount');


        function updateBulkActionState() {
            const checkedBoxes = document.querySelectorAll('input[name="comment_ids[]"]:checked');
            const checkedCount = checkedBoxes.length;


            if (checkedCount > 0) {
                bulkActionsBar.classList.add('active');
                bulkSelectedCount.textContent = checkedCount;
            } else {
                bulkActionsBar.classList.remove('active');
            }

        }



        function confirmBulkDelete() {
            const checked = document.querySelectorAll('input[name="comment_ids[]"]:checked');
            if (checked.length === 0) return;


            isBulkDelete = true;


            // Check for replies in selection

            let hasReplies = false;
            let totalReplies = 0;


            checked.forEach(cb => {
                const row = cb.closest('tr');
                if (row) {
                    const count = parseInt(row.getAttribute('data-reply-count') || '0');
                    if (count > 0) {
                        hasReplies = true;
                        totalReplies += count;
                    }

                }

            });


            // Update modal content

            document.querySelector('.modal-title-text').textContent = "<?= addslashes($lang['comment_bulk_delete_title']) ?>";
            let bodyHtml = "<?= addslashes($lang['comment_bulk_delete_confirm']) ?>".replace('%d', checked.length);


            if (hasReplies) {
                bodyHtml += `<br><span style="color:#ef4444; font-size:13px; font-weight:600; margin-top:12px; display:block; background:rgba(239,68,68,0.1); padding:8px; border-radius:6px; border:1px solid rgba(239,68,68,0.2);"><?= addslashes($lang['comment_replies_warning']) ?></span>`;
            } else {
                bodyHtml += `<br><span style="color:#ef4444; font-size:13px; margin-top:8px; display:block;"><?= addslashes($lang['modal_delete_perm_desc'] ?? 'This action cannot be undone.') ?></span>`;
            }



            document.querySelector('.modal-body').innerHTML = bodyHtml;
            document.getElementById('confirmDeleteBtn').textContent = "<?= addslashes($lang['action_delete_all']) ?>";


            const deleteModal = document.getElementById('deleteModal');
            deleteModal.classList.add('active');
        }



        function clearSelection() {
            commentCheckboxes.forEach(cb => cb.checked = false);
            if (selectAllInfo) selectAllInfo.checked = false;
            updateBulkActionState();
        }



        if (selectAllInfo) {
            selectAllInfo.addEventListener('change', function () {
                commentCheckboxes.forEach(cb => cb.checked = this.checked);
                updateBulkActionState();
            });
        }



        commentCheckboxes.forEach(cb => {
            cb.addEventListener('change', updateBulkActionState);
        });




        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeDeleteModal();
        });


        document.getElementById('deleteModal').addEventListener('click', (e) => {
            if (e.target.id === 'deleteModal') closeDeleteModal();
        });


        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = 'inline-toast inline-toast--' + type;


            // Define icons based on type

            let icon = '';
            if (type === 'success') {
                icon = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="20" height="20"><path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm13.36-1.814a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z" clip-rule="evenodd" /></svg>`;
            } else if (type === 'error') {
                icon = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="20" height="20"><path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75 9.75S17.385 2.25 12 2.25Zm-1.72 6.97a.75.75 0 1 0-1.06 1.06L10.94 12l-1.72 1.72a.75.75 0 1 0 1.06 1.06L12 13.06l1.72 1.72a.75.75 0 1 0-1.06-1.06L13.06 12l1.72-1.72a.75.75 0 1 0-1.06-1.06L12 10.94l-1.72-1.72Z" clip-rule="evenodd" /></svg>`;
            }



            toast.innerHTML = `

            <div class="toast-icon">${icon}</div>

            <div class="toast-text">${message}</div>

            <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>

        `;


            document.body.appendChild(toast);


            // Trigger reflow

            toast.offsetHeight;


            // Show

            setTimeout(() => toast.classList.add('show'), 10);


            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }



        <?php if ($toast_message): ?>

            window.addEventListener('load', () => showToast("<?= htmlspecialchars($toast_message) ?>", "<?= htmlspecialchars($toast_type) ?>"));
        <?php endif; ?>

    </script>

    <script>

        const preloaderStart = Date.now();
        window.addEventListener('load', function () {
            const preloader = document.getElementById('preloader');
            if (preloader) {
                // Ensure preloader shows for at least 500ms

                const elapsed = Date.now() - preloaderStart;
                const minDisplayTime = 100;
                const remainingTime = Math.max(0, minDisplayTime - elapsed);


                setTimeout(() => {
                    preloader.classList.add('fade-out');
                    setTimeout(() => {
                        preloader.style.display = 'none';
                    }, 300);
                }, remainingTime);
            }

        });
    </script>

</body>



</html>