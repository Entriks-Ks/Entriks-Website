<?php
date_default_timezone_set('Europe/Amsterdam');
require_once dirname(__DIR__) . '/session_config.php';
require '../database.php';
require '../config.php';

if (!isset($_SESSION['admin'])) {
    header('Location: ../login.php');
    exit;
}

// Role-based Access & Filtering
$userRole = $_SESSION['admin']['position'] ?? 'Editor';
$isAdmin = ($_SESSION['admin']['role'] ?? 'admin') === 'admin';
if ($isAdmin)
    $userRole = 'Admin';

$hasBlogAccess = $isAdmin || in_array($userRole, ['Content Manager', 'Author']);

if (!$hasBlogAccess) {
    header('Location: ../dashboard.php');
    exit;
}

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$skip = ($page - 1) * $perPage;

// Filters
$statusFilter = $_GET['status'] ?? 'all';
$categoryFilter = $_GET['category'] ?? 'all';
$blogType = $_GET['type'] ?? 'talent_hub'; // Default to talent_hub

// Validate blog type
$validBlogTypes = ['talent_hub', 'banking', 'karriere'];
if (!in_array($blogType, $validBlogTypes)) {
    $blogType = 'talent_hub';
}

$filter = ['status' => ['$ne' => 'archived'], 'blog_type' => $blogType];

// Author Restriction: Only see their own posts
if ($userRole === 'Author') {
    $filter['author_email'] = $_SESSION['admin']['email'] ?? 'unknown';
}

if ($statusFilter !== 'all') {
    $filter['status'] = $statusFilter;
}
if ($categoryFilter !== 'all') {
    if ($categoryFilter === 'uncategorized') {
        $filter['categories'] = ['$size' => 0];
    } else {
        $filter['categories'] = $categoryFilter;
    }
}

// Sorting
$sortValue = $_GET['sort'] ?? 'newest';
$sortStage = [];

if ($sortValue === 'popular') {
    $sortStage = ['views' => -1];
} elseif ($sortValue === 'oldest') {
    $sortStage = ['effective_date' => 1];
} else {
    // Default 'newest'
    $sortStage = ['effective_date' => -1];
}

$totalCount = $db->blog->countDocuments($filter);
$totalPages = ($perPage > 0) ? (int) ceil($totalCount / $perPage) : 1;

// Fetch all distinct categories for the filter dropdown
$allCategoriesRaw = $db->blog->distinct('categories');
$allCategories = [];
foreach ($allCategoriesRaw as $cat) {
    if (!empty($cat)) {
        $allCategories[] = $cat;
    }
}
sort($allCategories);

// Use aggregation to sort by "publish_at if exists, else created_at"
$pipeline = [    ['$match' => $filter],
    ['$addFields' => [

        'effective_date' => ['$ifNull' => ['$publish_at', '$created_at']]
    ]],
    ['$sort' => $sortStage],
    ['$skip' => $skip],
    ['$limit' => $perPage],
    ['$project' => [        'title_de' => 1,
        'status' => 1,
        'image' => 1,
        'created_at' => 1,
        'publish_at' => 1,
        'translation_status' => 1,
        'categories' => 1,
        'featured' => 1,
        'views' => 1,
        'effective_date' => 1
    ]]
];

$posts = $db->blog->aggregate($pipeline);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $lang['blog_manager_title'] ?></title>
    <link rel="shortcut icon" href="<?= htmlspecialchars($siteFaviconUrl) ?>" type="image/x-icon">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/blog.css">
    <script src="../assets/js/global-search.js?v=<?= time() ?>"></script>
</head>
<body>
    <!-- Preloader -->
    <div class="preloader" id="preloader">
        <div class="preloader-spinner"></div>
    </div>

    <?php if (isset($_SESSION['pending_post_id'])): ?>
        <script>
            sessionStorage.setItem('pendingTranslationId', '<?= $_SESSION['pending_post_id'] ?>');
        </script>
        <?php unset($_SESSION['pending_post_id']); ?>
    <?php endif; ?>

    <?php
    // Store toast data for later use after sidebar loads
    $toast_message = $_SESSION['toast_message'] ?? null;
    $toast_type = $_SESSION['toast_type'] ?? 'success';
    unset($_SESSION['toast_message'], $_SESSION['toast_type']);
    ?>

    <div class="layout">
        <?php $sidebarVariant = 'blog';
        $activeMenu = 'blog';
        include __DIR__ . '/../partials/sidebar.php'; ?>

        <?php if ($toast_message): ?>
            <script>
                // Wait for sidebar to load showNotification function
                document.addEventListener('DOMContentLoaded', () => {
                    if (typeof showNotification === 'function') {
                        showNotification({
                            message: "<?= addslashes($toast_message) ?>",
                            type_class: "<?= addslashes($toast_type) ?>",
                            title: "<?= addslashes($lang['toast_title'] ?? 'Information') ?>"
                        });
                    }
                });
            </script>
        <?php endif; ?>

        <main class="content">
            <!-- Blur Background Theme -->
            <div class="blur-bg-theme bottom-right"></div>

            <?php
            // Fix relative path for search.js in topbar
            // Blog type titles
            $blogTypeTitles = [
                'talent_hub' => $lang['blog_talent_hub_title'] ?? 'Talent Hub Blog',
                'banking' => $lang['blog_banking_title'] ?? 'Banking Blog',
                'karriere' => $lang['blog_karriere_title'] ?? 'Karriere Blog'
            ];
            $pageTitle = $blogTypeTitles[$blogType] ?? $lang['blog_manager_title'];
            include __DIR__ . '/../partials/topbar.php';
            ?>

            <!-- Fix relative path for global search script since we are in /blog/ -->
            <!-- Action Bar: Add Post & Filters -->
            <div style="margin-bottom:24px; display:flex; justify-content:space-between; align-items:center;">
                <!-- Add New Post Button -->
                <a href="create.php?type=<?= $blogType ?>" class="add-btn" style="display:inline-flex; align-items:center; gap:8px;">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="icon">
                        <path fill-rule="evenodd" d="M5.478 5.559A1.5 1.5 0 0 1 6.912 4.5H9A.75.75 0 0 0 9 3H6.912a3 3 0 0 0-2.868 2.118l-2.411 7.838a3 3 0 0 0-.133.882V18a3 3 0 0 0 3 3h15a3 3 0 0 0 3-3v-4.162c0-.299-.045-.596-.133-.882l-2.412-7.838A3 3 0 0 0 17.088 3H15a.75.75 0 0 0 0 1.5h2.088a1.5 1.5 0 0 1 1.434 1.059l2.213 7.191H17.89a3 3 0 0 0-2.684 1.658l-.256.513a1.5 1.5 0 0 1-1.342.829h-3.218a1.5 1.5 0 0 1-1.342-.83l-.256-.512a3 3 0 0 0-2.684-1.658H3.265l2.213-7.191Z" clip-rule="evenodd" />
                        <path fill-rule="evenodd" d="M12 2.25a.75.75 0 0 1 .75.75v6.44l1.72-1.72a.75.75 0 1 1 1.06 1.06l-3 3a.75.75 0 0 1-1.06 0l-3-3a.75.75 0 0 1 1.06-1.06l1.72 1.72V3a.75.75 0 0 1 .75-.75Z" clip-rule="evenodd" />
                    </svg>
                    <?= $lang['action_add_post'] ?>
                </a>
                <!-- Filters Button & Dropdown -->
                <div style="display:flex; gap:12px; align-items:center; position:relative;">
                    <button id="filtersToggleBtn" style="display:flex; align-items:center; gap:8px; padding:8px 16px; background:#232323; color:#fff; border:1px solid #333; border-radius:6px; font-size:14px; font-weight:500; cursor:pointer; z-index:20;">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="18" height="18">
                            <path d="M18.75 12.75h1.5a.75.75 0 0 0 0-1.5h-1.5a.75.75 0 0 0 0 1.5ZM12 6a.75.75 0 0 1 .75-.75h7.5a.75.75 0 0 1 0 1.5h-7.5A.75.75 0 0 1 12 6ZM12 18a.75.75 0 0 1 .75-.75h7.5a.75.75 0 0 1 0 1.5h-7.5A.75.75 0 0 1 12 18ZM3.75 6.75h1.5a.75.75 0 1 0 0-1.5h-1.5a.75.75 0 0 0 0 1.5ZM5.25 18.75h-1.5a.75.75 0 0 1 0-1.5h1.5a.75.75 0 0 1 0 1.5ZM3 12a.75.75 0 0 1 .75-.75h7.5a.75.75 0 0 1 0 1.5h-7.5A.75.75 0 0 1 3 12ZM9 3.75a2.25 2.25 0 1 0 0 4.5 2.25 2.25 0 0 0 0-4.5ZM12.75 12a2.25 2.25 0 1 1 4.5 0 2.25 2.25 0 0 1-4.5 0ZM9 15.75a2.25 2.25 0 1 0 0 4.5 2.25 2.25 0 0 0 0-4.5Z" />
                        </svg>
                        <?= $lang['filter_toggle'] ?>
                    </button>
                    <!-- Filters Dropdown Panel -->
                    <div id="filtersDropdown" class="filters-dropdown" style="display:none; position:fixed; background:#1a1a1a; border:1px solid #333; border-radius:8px; padding:16px; min-width:280px; z-index:9999; box-shadow:0 10px 40px rgba(0,0,0,0.5);">
                        <div style="font-size:15px; font-weight:600; color:#fff; margin-bottom:12px;"><?= $lang['filter_dialog_title'] ?></div>

                        <div style="margin-bottom:16px;">
                            <label style="display:block; font-size:13px; color:#9ca3af; margin-bottom:8px; font-weight:500;"><?= $lang['filter_label_blog_type'] ?? 'Blog Section' ?></label>
                            <select id="filter-type-dropdown" class="filter-select" style="width:100%; padding:8px 12px; border:1px solid #333; border-radius:6px; background:#232323; color:#fff; font-size:14px; cursor:pointer;">
                                <option value="talent_hub" <?= $blogType === 'talent_hub' ? 'selected' : '' ?>><?= $lang['blog_type_talent_hub'] ?? 'Talent Hub' ?></option>
                                <option value="banking" <?= $blogType === 'banking' ? 'selected' : '' ?>><?= $lang['blog_type_banking'] ?? 'Banking' ?></option>
                                <option value="karriere" <?= $blogType === 'karriere' ? 'selected' : '' ?>><?= $lang['blog_type_karriere'] ?? 'Karriere' ?></option>
                            </select>
                        </div>

                        <div style="margin-bottom:16px;">
                            <label style="display:block; font-size:13px; color:#9ca3af; margin-bottom:8px; font-weight:500;"><?= $lang['filter_label_sort'] ?></label>
                            <select id="filter-sort-dropdown" class="filter-select" style="width:100%; padding:8px 12px; border:1px solid #333; border-radius:6px; background:#232323; color:#fff; font-size:14px; cursor:pointer;">
                                <option value="newest" <?= $sortValue === 'newest' ? 'selected' : '' ?>><?= $lang['sort_newest'] ?></option>
                                <option value="oldest" <?= $sortValue === 'oldest' ? 'selected' : '' ?>><?= $lang['sort_oldest'] ?></option>
                                <option value="popular" <?= $sortValue === 'popular' ? 'selected' : '' ?>><?= $lang['sort_popular'] ?></option>
                            </select>
                        </div>

                        <div style="margin-bottom:16px;">
                            <label style="display:block; font-size:13px; color:#9ca3af; margin-bottom:8px; font-weight:500;"><?= $lang['filter_label_status'] ?></label>
                            <select id="filter-status-dropdown" class="filter-select" style="width:100%; padding:8px 12px; border:1px solid #333; border-radius:6px; background:#232323; color:#fff; font-size:14px; cursor:pointer;">
                                <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>><?= $lang['status_all'] ?></option>
                                <option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>><?= $lang['status_draft'] ?></option>
                                <option value="published" <?= $statusFilter === 'published' ? 'selected' : '' ?>><?= $lang['status_published'] ?></option>
                                <option value="scheduled" <?= $statusFilter === 'scheduled' ? 'selected' : '' ?>><?= $lang['status_scheduled'] ?></option>
                            </select>
                        </div>

                        <div style="margin-bottom:16px;">
                            <label style="display:block; font-size:13px; color:#9ca3af; margin-bottom:8px; font-weight:500;"><?= $lang['filter_label_category'] ?></label>
                            <select id="filter-category-dropdown" class="filter-select" style="width:100%; padding:8px 12px; border:1px solid #333; border-radius:6px; background:#232323; color:#fff; font-size:14px; cursor:pointer;">
                                <option value="all"><?= $lang['category_select'] ?></option>
                            </select>
                        </div>

                        <div style="display:flex; gap:8px;">
                            <button id="apply-filters" style="flex:1; padding:8px; background:linear-gradient(135deg, #7675ec 0%, #d225d7 100%); color:#fff; border:none; border-radius:6px; font-size:14px; font-weight:500; cursor:pointer;"><?= $lang['action_apply'] ?></button>
                            <button id="clear-filters" style="padding:8px 16px; background:#333; color:#fff; border:none; border-radius:6px; font-size:14px; cursor:pointer;"><?= $lang['action_clear'] ?></button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid-container">

                <?php foreach ($posts as $index => $post): ?>
                    <?php
                    // Normalize categories which may be stored as MongoDB BSONArray, PHP array, or single string
                    $postCategoriesRaw = $post->categories ?? [];
                    if ($postCategoriesRaw instanceof \MongoDB\Model\BSONArray) {
                        $postCategoriesArr = $postCategoriesRaw->getArrayCopy();
                    } elseif (is_array($postCategoriesRaw)) {
                        $postCategoriesArr = $postCategoriesRaw;
                    } elseif (is_string($postCategoriesRaw)) {
                        $postCategoriesArr = [$postCategoriesRaw];
                    } else {
                        $postCategoriesArr = [];
                    }
                    $catDataAttr = htmlspecialchars(implode(',', $postCategoriesArr));
                    $statusAttr = htmlspecialchars($post->status ?? 'draft');
                    ?>

                    <div class="card" <?php if ($index === 0) echo 'id="tour-first-post-card"'; ?> data-post-id="<?= (string) $post->_id ?>"
                        data-translation-status="<?= $post->translation_status ?? 'pending' ?>"
                        data-status="<?= $statusAttr ?>" data-categories="<?= $catDataAttr ?>"
                        data-views="<?= $post->views ?? 0 ?>"
                        data-created="<?= isset($post->created_at) ? (is_object($post->created_at) ? $post->created_at->toDateTime()->format('Y-m-d H:i:s') : $post->created_at) : '' ?>"
                        style="display: flex; flex-direction: column;">
                        <div class="thumb">
                            <?php if (!empty($post->image)): ?>
                                <img src="../image.php?id=<?= $post->image ?>" alt="Thumbnail" loading="lazy">
                            <?php else: ?>
                                <span style="color: #9ca3af; font-size: 15px;"><?= $lang['no_image'] ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="card-body" style="display: flex; flex-direction: column; flex: 1;">
                            <div
                                style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px; gap: 8px; flex-wrap: nowrap;">
                                <div style="min-width: 0; flex: 1;">
                                    <?php
                                    $titleWords = explode(' ', trim($post->title_de ?? ''));
                                    $truncatedTitle = implode(' ', array_slice($titleWords, 0, 2));
                                    if (count($titleWords) > 2) {
                                        $truncatedTitle .= ' ...';
                                    }
                                    ?>
                                    <h3 style="margin: 0;"><?= $truncatedTitle ?></h3>
                                    <!-- Categories are not shown here. use the left filters to narrow posts by category/status -->
                                </div>
                                <?php if (($post->status ?? 'draft') === 'scheduled'): ?>
                                    <div class="status-dropdown-toggle" style="cursor:default;">
                                        <span class="status-dot scheduled"></span>
                                        <span><?= $lang['status_scheduled'] ?></span>
                                    </div>
                                <?php else: ?>
                                    <div class="status-dropdown" style="position: relative; display: inline-block;">
                                        <div class="status-dropdown">
                                            <button class="status-dropdown-toggle" <?php if ($index === 0) echo 'id="tour-status-dropdown"'; ?> onclick="toggleStatusDropdown(this)">
                                                <span class="status-dot <?= ($post->status ?? 'draft') === 'published' ? 'published' : 'draft' ?>"></span>
                                                <?= $lang['status_' . ($post->status ?? 'draft')] ?? ucfirst($post->status ?? 'Draft') ?>
                                                <svg style="margin-left:4px;width:14px;height:14px;vertical-align:middle;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                </svg>
                                            </button>
                                            <div class="status-dropdown-menu">
                                                <div class="status-dropdown-item" onclick="changeStatusDropdown(this, 'draft', '<?= $post->_id ?>')">
                                                    <span class="status-dot draft"></span> <?= $lang['status_draft'] ?>
                                                </div>
                                                <div class="status-dropdown-item" onclick="changeStatusDropdown(this, 'published', '<?= $post->_id ?>')">
                                                    <span class="status-dot published"></span> <?= $lang['status_published'] ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php if ((($post->status ?? 'draft') === 'published')): ?>
                                        <!-- Featured toggle moved to the view (eye) icon area; removed switch here -->
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>

                            <!-- Translation Status Badge -->
                            <?php if (($post->translation_status ?? 'pending') === 'pending'): ?>
                                <div class="translation-badge"
                                    style="display: flex; align-items: center; gap: 8px; padding: 8px 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 8px; margin-bottom: 12px;">
                                    <div class="translation-spinner"
                                        style="width: 14px; height: 14px; border: 2px solid rgba(255,255,255,0.3); border-top-color: white; border-radius: 50%; animation: spin 1s linear infinite;">
                                    </div>
                                    <span style="color: white; font-size: 12px; font-weight: 600;"><?= $lang['js_translating'] ?></span>
                                </div>
                            <?php endif; ?>

                            <div style="margin-top: auto;">
                                <p style="color: #ccc; font-size: 13px; margin-top: 8px;">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                                    style="width: 18px; height: 18px; display: inline-block; vertical-align: middle; margin-right: 4px;">
                                    <path
                                        d="M12.75 12.75a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM7.5 15.75a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5ZM8.25 17.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM9.75 15.75a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5ZM10.5 17.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM12 15.75a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5ZM12.75 17.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM14.25 15.75a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5ZM15 17.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM16.5 15.75a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5ZM15 12.75a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM16.5 13.5a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z" />
                                    <path fill-rule="evenodd"
                                        d="M6.75 2.25A.75.75 0 0 1 7.5 3v1.5h9V3A.75.75 0 0 1 18 3v1.5h.75a3 3 0 0 1 3 3v11.25a3 3 0 0 1-3 3H5.25a3 3 0 0 1-3-3V7.5a3 3 0 0 1 3-3H6V3a.75.75 0 0 1 .75-.75Zm13.5 9a1.5 1.5 0 0 0-1.5-1.5H5.25a1.5 1.5 0 0 0-1.5 1.5v7.5a1.5 1.5 0 0 0 1.5 1.5h13.5a1.5 1.5 0 0 0 1.5-1.5v-7.5Z"
                                        clip-rule="evenodd" />
                                </svg>
                                <?php
                                // If a post is published but has a publish_at (it was scheduled),
                                // show the scheduled publish time instead of the created time.
                                if ((($post->status ?? '') === 'published') && !empty($post->publish_at)) {
                                    $dt = $post->publish_at->toDateTime();
                                    $dt->setTimezone(new DateTimeZone('Europe/Amsterdam'));
                                    if (class_exists('IntlDateFormatter')) {
                                        $locale = (isset($defaultLanguage) && $defaultLanguage === 'de') ? 'de_DE' : 'en_US';
                                        $pattern = (isset($defaultLanguage) && $defaultLanguage === 'de') ? 'dd MMMM, yyyy HH:mm' : 'MMMM dd, yyyy HH:mm';
                                        $fmt = new IntlDateFormatter($locale, IntlDateFormatter::LONG, IntlDateFormatter::SHORT, 'Europe/Amsterdam', IntlDateFormatter::GREGORIAN, $pattern);
                                        echo $fmt->format($dt);
                                    } else {
                                        if (isset($defaultLanguage) && $defaultLanguage === 'de') {
                                            $months_full = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
                                            $mIdx = max(1, (int) $dt->format('n')) - 1;
                                            $monthLabel = $months_full[$mIdx] ?? $dt->format('F');
                                            echo $dt->format('d') . ' ' . $monthLabel . ', ' . $dt->format('Y H:i');
                                        } else {
                                            $months_full = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                                            $mIdx = max(1, (int) $dt->format('n')) - 1;
                                            $monthLabel = $months_full[$mIdx] ?? $dt->format('F');
                                            echo $monthLabel . ' ' . $dt->format('d, Y H:i');
                                        }
                                    }
                                } elseif (!empty($post->created_at)) {
                                    $dt = $post->created_at->toDateTime();
                                    $dt->setTimezone(new DateTimeZone('Europe/Amsterdam'));
                                    if (class_exists('IntlDateFormatter')) {
                                        $locale = (isset($defaultLanguage) && $defaultLanguage === 'de') ? 'de_DE' : 'en_US';
                                        $pattern = (isset($defaultLanguage) && $defaultLanguage === 'de') ? 'dd MMMM, yyyy HH:mm' : 'MMMM dd, yyyy HH:mm';
                                        $fmt = new IntlDateFormatter($locale, IntlDateFormatter::LONG, IntlDateFormatter::SHORT, 'Europe/Amsterdam', IntlDateFormatter::GREGORIAN, $pattern);
                                        echo $fmt->format($dt);
                                    } else {
                                        if (isset($defaultLanguage) && $defaultLanguage === 'de') {
                                            $months_full = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
                                            $mIdx = max(1, (int) $dt->format('n')) - 1;
                                            $monthLabel = $months_full[$mIdx] ?? $dt->format('F');
                                            echo $dt->format('d') . ' ' . $monthLabel . ', ' . $dt->format('Y H:i');
                                        } else {
                                            $months_full = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                                            $mIdx = max(1, (int) $dt->format('n')) - 1;
                                            $monthLabel = $months_full[$mIdx] ?? $dt->format('F');
                                            echo $monthLabel . ' ' . $dt->format('d, Y H:i');
                                        }
                                    }
                                } else {
                                    echo 'Unknown Date';
                                }
                                ?>
                            </p>

                            <div class="card-actions">
                                <div style="display: flex; justify-content: space-between; align-items: center; gap: 8px;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <a href="../../blog.php?id=<?= $post->_id ?>" target="_blank" class="view-btn"
                                            style="display:inline-flex;align-items:center;position:relative;">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white"
                                                class="icon">
                                                <path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" />
                                                <path fill-rule="evenodd"
                                                    d="M1.323 11.447C2.811 6.976 7.028 3.75 12.001 3.75c4.97 0 9.185 3.223 10.675 7.69.12.362.12.752 0 1.113-1.487 4.471-5.705 7.697-10.677 7.697-4.97 0-9.186-3.223-10.675-7.69a1.762 1.762 0 0 1 0-1.113ZM17.25 12a5.25 5.25 0 1 1-10.5 0 5.25 5.25 0 0 1 10.5 0Z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                        </a>
                                        <?php if ((($post->status ?? '') === 'published')): ?>
                                            <!-- Main Page icon toggle -->
                                            <button type="button" 
                                                    class="featured-btn <?= !empty($post->featured) ? 'active' : '' ?>" 
                                                    <?php if ($index === 0) echo 'id="tour-featured-btn"'; ?>
                                                    onclick="toggleFeatured('<?= $post->_id ?>', this)" 
                                                    title="<?= !empty($post->featured) ? $lang['tooltip_remove_featured'] : $lang['tooltip_add_featured'] ?>">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="icon">
                                                    <path d="M21.721 12.752a9.711 9.711 0 0 0-.945-5.003 12.754 12.754 0 0 1-4.339 2.708 18.991 18.991 0 0 1-.214 4.772 17.165 17.165 0 0 0 5.498-2.477ZM14.634 15.55a17.324 17.324 0 0 0 .332-4.647c-.952.227-1.945.347-2.966.347-1.021 0-2.014-.12-2.966-.347a17.515 17.515 0 0 0 .332 4.647 17.385 17.385 0 0 0 5.268 0ZM9.772 17.119a18.963 18.963 0 0 0 4.456 0A17.182 17.182 0 0 1 12 21.724a17.18 17.18 0 0 1-2.228-4.605ZM7.777 15.23a18.87 18.87 0 0 1-.214-4.774 12.753 12.753 0 0 1-4.34-2.708 9.711 9.711 0 0 0-.944 5.004 17.165 17.165 0 0 0 5.498 2.477ZM21.356 14.752a9.765 9.765 0 0 1-7.478 6.817 18.64 18.64 0 0 0 1.988-4.718 18.627 18.627 0 0 0 5.49-2.098ZM2.644 14.752c1.682.971 3.53 1.688 5.49 2.099a18.64 18.64 0 0 0 1.988 4.718 9.765 9.765 0 0 1-7.478-6.816ZM13.878 2.43a9.755 9.755 0 0 1 6.116 3.986 11.267 11.267 0 0 1-3.746 2.504 18.63 18.63 0 0 0-2.37-6.49ZM12 2.276a17.152 17.152 0 0 1 2.805 7.121c-.897.23-1.837.353-2.805.353-.968 0-1.908-.122-2.805-.353A17.151 17.151 0 0 1 12 2.276ZM10.122 2.43a18.629 18.629 0 0 0-2.37 6.49 11.266 11.266 0 0 1-3.746-2.504 9.754 9.754 0 0 1 6.116-3.985Z" />
                                                </svg>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                    <div style="display: flex; gap: 8px;">
                                        <a href="edit.php?id=<?= $post->_id ?>&type=<?= $blogType ?>" class="edit-btn"
                                            style="display:inline-flex;align-items:center;">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                                                class="icon">
                                                <path
                                                    d="M21.731 2.269a2.625 2.625 0 0 0-3.712 0l-1.157 1.157 3.712 3.712 1.157-1.157a2.625 2.625 0 0 0 0-3.712ZM19.513 8.199l-3.712-3.712-8.4 8.4a5.25 5.25 0 0 0-1.32 2.214l-.8 2.685a.75.75 0 0 0 .933.933l2.685-.8a5.25 5.25 0 0 0 2.214-1.32l8.4-8.4Z" />
                                                <path
                                                    d="M5.25 5.25a3 3 0 0 0-3 3v10.5a3 3 0 0 0 3 3h10.5a3 3 0 0 0 3-3V13.5a.75.75 0 0 0-1.5 0v5.25a1.5 1.5 0 0 1-1.5 1.5H5.25a1.5 1.5 0 0 1-1.5-1.5V8.25a1.5 1.5 0 0 1 1.5-1.5h5.25a.75.75 0 0 0 0-1.5H5.25Z" />
                                            </svg>
                                        </a>
                                        <a href="#" class="archive-btn" style="display:inline-flex;align-items:center;"
                                            <?php if ($index === 0) echo 'id="tour-archive-btn"'; ?>
                                            onclick="confirmArchive('<?= $post->_id ?>', '<?= addslashes($post->title_de ?? '') ?>'); return false;">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white"
                                                class="icon">
                                                <path
                                                    d="M3.375 3C2.339 3 1.5 3.84 1.5 4.875v.75c0 1.036.84 1.875 1.875 1.875h17.25c1.035 0 1.875-.84 1.875-1.875v-.75C22.5 3.839 21.66 3 20.625 3H3.375Z" />
                                                <path fill-rule="evenodd"
                                                    d="m3.087 9 .54 9.176A3 3 0 0 0 6.62 21h10.757a3 3 0 0 0 2.995-2.824L20.913 9H3.087Zm6.163 3.75A.75.75 0 0 1 10 12h4a.75.75 0 0 1 0 1.5h-4a.75.75 0 0 1-.75-.75Z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

            </div>

            <!-- Pagination Controls -->
             <?php if (!empty($totalPages) && $totalPages > 1): ?>
                <?php
                $queryParams = $_GET;
                unset($queryParams['page']);
                $queryStr = http_build_query($queryParams);
                if ($queryStr)
                    $queryStr = '&' . $queryStr;
                ?>
                <div style="display:flex;justify-content:center;gap:8px;margin-top:18px;">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 . $queryStr ?>" class="status-btn">&larr; Prev</a>
                    <?php endif; ?>

                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <?php if ($p === $page): ?>
                            <span class="status-btn" style="opacity:0.85;background:#6366f1;"><?= $p ?></span>
                        <?php else: ?>
                            <a href="?page=<?= $p . $queryStr ?>" class="status-btn" style="background:#232428;color:#fff;"><?= $p ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 . $queryStr ?>" class="status-btn">Next &rarr;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['pending_post_id'])): ?>
                <script>
                    // Persist pending post id so we can show progress until translation finishes
                    sessionStorage.setItem('pendingTranslationId', '<?= $_SESSION['pending_post_id'] ?>');
                    sessionStorage.setItem('translationStartTime', Date.now().toString());
                </script>
                <?php unset($_SESSION['pending_post_id']);
endif; ?>

        </main>
    </div>

    <div id="archiveModal" class="modal-overlay">
        <div class="modal-card-flow" style="background:#262525;color:#fff;">
            <div class="modal-header" style="border-bottom:1px solid #e5e7eb;">
                <h3 class="modal-title" style="color:#fff;font-weight:700;display:flex;align-items:center;gap:10px;">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#6366f1"
                        style="width:24px;height:24px;vertical-align:middle;">
                        <path
                            d="M3.375 3C2.339 3 1.5 3.84 1.5 4.875v.75c0 1.036.84 1.875 1.875 1.875h17.25c1.035 0 1.875-.84 1.875-1.875v-.75C22.5 3.839 21.66 3 20.625 3H3.375Z" />
                        <path fill-rule="evenodd"
                            d="m3.087 9 .54 9.176A3 3 0 0 0 6.62 21h10.757a3 3 0 0 0 2.995-2.824L20.913 9H3.087Zm6.163 3.75A.75.75 0 0 1 10 12h4a.75.75 0 0 1 0 1.5h-4a.75.75 0 0 1-.75-.75Z"
                            clip-rule="evenodd" />
                    </svg>
                    <?= $lang['modal_archive_title'] ?>
                </h3>
                <button class="modal-close" onclick="closeModal()"
                    style="background:none;border:none;padding:6px;cursor:pointer;border-radius:6px;transition:background 0.15s;">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon" fill="none" viewBox="0 0 24 24" stroke="#fff"
                        style="width:20px;height:20px;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="modal-body" style="font-size:15px;line-height:1.6;color:#fff;text-align:left;">
                <p style="color: white">
                    <?= $lang['modal_archive_confirm'] ?>
                    <br>
                    <span style="color:#ccc;"><?= $lang['modal_archive_desc'] ?></span>
                </p>
            </div>
            <div class="modal-footer"
                style="padding:16px 28px 22px 28px;display:flex;justify-content:flex-end;gap:12px;">
                <button class="btn-cancel" onclick="closeModal()"
                    style="padding:8px 20px;background:#e5e7eb;border:1px solid #d1d5db;border-radius:8px;color:#222;cursor:pointer;font-size:14px;font-weight:500;transition:background 0.15s, border 0.15s;"><?= $lang['action_cancel'] ?></button>
                <button class="btn-archive" onclick="proceedArchive()"
                    style="padding:8px 20px;background:#6366f1;border-radius:8px;color:#fff;cursor:pointer;font-size:14px;font-weight:600;border:none;box-shadow:0 2px 8px rgba(99,102,241,0.08);transition:background 0.15s;"><?= $lang['action_confirm_archive'] ?></button>
            </div>
        </div>
    </div>
    <script>
        let archivePostId = null;

        function confirmArchive(postId, postTitle) {
            archivePostId = postId;
            document.getElementById('postTitle').textContent = postTitle;
            document.getElementById('archiveModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('archiveModal').style.display = 'none';
            archivePostId = null;
        }

        function proceedArchive() {
            if (archivePostId) {
                fetch('archive.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'id=' + encodeURIComponent(archivePostId)
                })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            closeModal();
                            if (typeof showNotification === 'function') {
                                showNotification({ type_class: 'success', message: '<?= $lang['msg_post_archived'] ?>' });
                            }
                            setTimeout(() => location.reload(), 2000);
                        } else {
                            if (typeof showNotification === 'function') {
                                showNotification({ type_class: 'error', message: '<?= $lang['msg_archive_failed'] ?>' });
                            } else {
                                alert('Failed to archive post');
                            }
                        }
                    })
                    .catch(() => {
                        if (typeof showNotification === 'function') {
                            showNotification({ type_class: 'error', message: '<?= $lang['msg_archive_failed'] ?>' });
                        } else {
                            alert('Failed to archive post');
                        }
                    });
            }
        }

        // Close modal on ESC key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });

        // Close modal on background click
        var archiveModal = document.getElementById('archiveModal');
        if (archiveModal) {
            archiveModal.addEventListener('click', function (e) {
                if (e.target === this) {
                    closeModal();
                }
            });
        }

        // Populate categories for the filter dropdown
        (function () {
            const categorySelect = document.getElementById('filter-category-dropdown');
            
            if (categorySelect) {
                 // Remove existing options (except "All Categories")
                 categorySelect.querySelectorAll('option:not([value="all"])').forEach(o => o.remove());
                 
                 const currentCategory = new URLSearchParams(window.location.search).get('category') || 'all';
                 
                 // Add "Uncategorized" option
                 const optUncat = document.createElement('option');
                 optUncat.value = 'uncategorized';
                 optUncat.textContent = 'Uncategorized';
                 if ('uncategorized' === currentCategory) optUncat.selected = true;
                 categorySelect.appendChild(optUncat);

                 // Add categories from PHP
                 const categories = <?= json_encode($allCategories) ?>;
                 categories.forEach(cat => {
                    const opt = document.createElement('option');
                    opt.value = cat;
                    opt.textContent = cat;
                    if (cat === currentCategory) opt.selected = true;
                    categorySelect.appendChild(opt);
                 });
            }
        })();

        // Check for pending translations and show toast when complete
        (function checkTranslationStatus() {
            const pendingPostId = sessionStorage.getItem('pendingTranslationId');
            if (!pendingPostId) return;

            const startTime = parseInt(sessionStorage.getItem('translationStartTime') || Date.now());
            if (!sessionStorage.getItem('translationStartTime')) {
                sessionStorage.setItem('translationStartTime', startTime);
            }

            // Show progress indicator
            const progressDiv = document.createElement('div');
            progressDiv.className = 'translation-progress';
            progressDiv.id = 'translation-progress-indicator';
            progressDiv.innerHTML = `
        <div class="translation-progress-header">
            <div class="translation-progress-spinner"></div>
            <div class="translation-progress-title">Translating...</div>
        </div>
        <div class="translation-progress-time">Elapsed: <span id="elapsed-time">0s</span></div>
        <div class="translation-progress-bar">
            <div class="translation-progress-bar-fill" id="translation-progress-fill" style="width: 10%;"></div>
        </div>
        <div class="translation-progress-percent" id="translation-progress-percent">10%</div>
    `;
            document.body.appendChild(progressDiv);

            // Update elapsed time every second
            const updateElapsedTime = setInterval(() => {
                const elapsed = Math.floor((Date.now() - startTime) / 1000);
                const timeSpan = document.getElementById('elapsed-time');
                if (timeSpan) {
                    timeSpan.textContent = `${elapsed}s`;
                } else {
                    clearInterval(updateElapsedTime);
                }
            }, 1000);

            fetch('check_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'post_id=' + pendingPostId
            })
                .then(response => response.json())
                .then(data => {
                    const elapsedSeconds = data.elapsed_seconds || 0;
                    const attempts = data.attempts || 0;

                    // Simple progress heuristic: pending -> 20-80% based on time, completed -> 100%
                    const fill = document.getElementById('translation-progress-fill');
                    const percentText = document.getElementById('translation-progress-percent');
                    if (fill && percentText) {
                        let percent = 20 + Math.min(60, Math.round((elapsedSeconds / 15) * 60)); // assumes ~15s typical
                        if (data.translation_status === 'completed') percent = 100;
                        if (data.translation_status === 'failed') percent = 100;
                        fill.style.width = `${percent}%`;
                        percentText.textContent = `${percent}%`;
                    }

                    if (data.translation_status === 'completed' && data.has_english_content) {
                        // Translation complete - show toast and hide progress
                        const totalTime = Math.round((Date.now() - startTime) / 1000);                        // Remove progress indicator
                        clearInterval(updateElapsedTime);
                        document.getElementById('translation-progress-indicator')?.remove();

                        // Update the card badge to show completion
                        const card = document.querySelector(`[data-post-id="${pendingPostId}"]`);
                        if (card) {
                            const badge = card.querySelector('.translation-badge');
                            if (badge) {
                                badge.innerHTML = `
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width: 16px; height: 16px; color: #10b981;">
                            <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm13.36-1.814a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z" clip-rule="evenodd" />
                        </svg>
                        <span style="color: #10b981; font-size: 12px; font-weight: 600;"><?= $lang['js_translation_complete'] ?></span>
                    `;
                                badge.style.background = '#d1fae5';
                                badge.style.border = '1px solid #10b981';

                                // Remove badge after 3 seconds
                                setTimeout(() => badge.remove(), 3000);
                            }
                        }

                        showTranslationToast(totalTime);
                        sessionStorage.removeItem('pendingTranslationId');
                        sessionStorage.removeItem('translationStartTime');
                    } else if (data.translation_status === 'failed') {

                        // Remove progress indicator
                        clearInterval(updateElapsedTime);
                        document.getElementById('translation-progress-indicator')?.remove();

                        // Update card badge to show failure
                        const card = document.querySelector(`[data-post-id="${pendingPostId}"]`);
                        if (card) {
                            const badge = card.querySelector('.translation-badge');
                            if (badge) {
                                badge.innerHTML = `
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width: 16px; height: 16px; color: #ef4444;">
                            <path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25Zm-1.72 6.97a.75.75 0 1 0-1.06 1.06L10.94 12l-1.72 1.72a.75.75 0 1 0 1.06 1.06L12 13.06l1.72 1.72a.75.75 0 1 0-1.06-1.06L13.06 12l1.72-1.72a.75.75 0 1 0-1.06-1.06L12 10.94l-1.72-1.72Z" clip-rule="evenodd" />
                        </svg>
                        <span style="color: #ef4444; font-size: 12px; font-weight: 600;"><?= $lang['js_translation_failed'] ?></span>
                    `;
                                badge.style.background = '#fee2e2';
                                badge.style.border = '1px solid #ef4444';
                            }
                        }

                        sessionStorage.removeItem('pendingTranslationId');
                        sessionStorage.removeItem('translationStartTime');
                    } else {
                        // Still pending - check again in 2 seconds
                        setTimeout(checkTranslationStatus, 2000);
                    }
                })
                .catch(err => {
                    console.error('Error checking translation status:', err);
                    clearInterval(updateElapsedTime);
                    document.getElementById('translation-progress-indicator')?.remove();
                    sessionStorage.removeItem('pendingTranslationId');
                });
        })();

        function showTranslationToast(totalSeconds = 0) {
            const backdrop = document.createElement('div');
            backdrop.className = 'toast-backdrop';
            backdrop.onclick = function () { this.remove(); document.querySelector('.toast')?.remove(); };

            const toast = document.createElement('div');
            toast.className = 'toast success';
            const timeText = totalSeconds > 0 ? ` in ${totalSeconds}s` : '';
            toast.innerHTML = `
        <div class="toast-icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm13.36-1.814a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z" clip-rule="evenodd" />
            </svg>
        </div>
        <h3>Translation Complete!</h3>
        <p>Your blog post has been translated to English${timeText}</p>
    `;

            document.body.appendChild(backdrop);
            document.body.appendChild(toast);

            setTimeout(() => {
                toast.remove();
                backdrop.remove();
            }, 3000);
        }

        // Sort by functionality
        // Sort by functionality moved to Apply Filters
        /*
         document.getElementById('sort-by')?.addEventListener('change', function() {
            const url = new URL(window.location.href);
            url.searchParams.set('sort', this.value);
            url.searchParams.delete('page'); // Reset to first page
            window.location.href = url.toString();
        });
        */
        // Apply filters
         document.getElementById('apply-filters')?.addEventListener('click', function() {
            const typeFilter = document.getElementById('filter-type-dropdown').value;
            const statusFilter = document.getElementById('filter-status-dropdown').value;
            const categoryFilter = document.getElementById('filter-category-dropdown').value;
            const sortFilter = document.getElementById('filter-sort-dropdown').value;
            
            const url = new URL(window.location.href);
            url.searchParams.set('type', typeFilter);
            url.searchParams.set('status', statusFilter);
            url.searchParams.set('category', categoryFilter);
            url.searchParams.set('sort', sortFilter);
            url.searchParams.delete('page'); // Reset to first page
            window.location.href = url.toString();
        });

        // Clear filters
         document.getElementById('clear-filters')?.addEventListener('click', function() {
            const url = new URL(window.location.href);
            url.searchParams.delete('type');
            url.searchParams.delete('status');
            url.searchParams.delete('category');
            url.searchParams.delete('sort');
            url.searchParams.delete('page'); // Reset to first page
            window.location.href = url.toString();
        });

        // Filter Dropdown Toggle
        const filtersToggleBtn = document.getElementById('filtersToggleBtn');
        const filtersDropdown = document.getElementById('filtersDropdown');
        if (filtersToggleBtn && filtersDropdown) {
          filtersToggleBtn.addEventListener('click', (e) => {
             e.stopPropagation();
             if (filtersDropdown.style.display === 'block') {
                 filtersDropdown.style.display = 'none';
                 filtersToggleBtn.style.borderColor = '#333';
             } else {
                 const rect = filtersToggleBtn.getBoundingClientRect();
                 filtersDropdown.style.top = (rect.bottom + 8) + 'px';
                 filtersDropdown.style.left = 'auto';
                 filtersDropdown.style.right = (window.innerWidth - rect.right) + 'px';
                 
                 filtersDropdown.style.display = 'block';
                 filtersToggleBtn.style.borderColor = '#777';
             }
          });
          document.addEventListener('click', (e) => {
             if (!filtersToggleBtn.contains(e.target) && !filtersDropdown.contains(e.target)) {
                 filtersDropdown.style.display = 'none';
                 filtersToggleBtn.style.borderColor = '#333';
             }
          });
        }
    </script>

    <script>
        // Compact Dropdown logic
        function toggleStatusDropdown(btn) {
            const menu = btn.nextElementSibling;
            document.querySelectorAll('.status-dropdown-menu.show').forEach(m => {
                if (m !== menu) m.classList.remove('show');
            });
            menu.classList.toggle('show');
        }
        document.addEventListener('click', function (e) {
            if (!e.target.closest('.status-dropdown')) {
                document.querySelectorAll('.status-dropdown-menu.show').forEach(m => m.classList.remove('show'));
            }
        });
        function changeStatusDropdown(item, status, postId) {
            const dropdown = item.closest('.status-dropdown');
            const toggle = dropdown.querySelector('.status-dropdown-toggle');
            toggle.innerHTML = `<span class="status-dot ${status}"></span> ${status.charAt(0).toUpperCase() + status.slice(1)} <svg style=\"margin-left:4px;width:14px;height:14px;vertical-align:middle;\" xmlns=\"http://www.w3.org/2000/svg\" fill=\"none\" viewBox=\"0 0 24 24\" stroke=\"currentColor\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M19 9l-7 7-7-7\" /></svg>`;
            dropdown.querySelector('.status-dropdown-menu').classList.remove('show');
            // AJAX request
            fetch('update_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id=' + encodeURIComponent(postId) + '&status=' + encodeURIComponent(status)
            })
                .then(r => r.json())
                .then(data => {
                    if (!data.success) {
                        alert('Failed to update status');
                    }
                })
                .catch(() => alert('Failed to update status'));
        }
        function showToast(message, type = 'info') {
            // Use the global showNotification system from sidebar
            if (typeof showNotification === 'function') {
                showNotification({
                    type: type,
                    type_class: type,
                    title: type === 'featured' ? '<?= $lang['toast_main_page_title'] ?>' : (type === 'error' ? '<?= $lang['toast_error_title'] ?>' : '<?= $lang['toast_success_title'] ?>'),
                    message: message,
                    item_title: message
                });
            } else {
                // Fallback: simple alert
                alert(message);
            }
        }
        // Dropdown functions
        function toggleStatusDropdown(btn) {
            // Close other open dropdowns first
            document.querySelectorAll('.status-dropdown-menu.show').forEach(m => {
                if (m !== btn.nextElementSibling) m.classList.remove('show');
            });
            const menu = btn.nextElementSibling;
            if (menu) {
                menu.classList.toggle('show');
            }
            // Add click outside listener if not already there
            if (!window._statusDropdownListener) {
                window._statusDropdownListener = true;
                document.addEventListener('click', function(e) {
                    if (!e.target.closest('.status-dropdown')) {
                        document.querySelectorAll('.status-dropdown-menu.show').forEach(el => el.classList.remove('show'));
                    }
                });
            }
        }

        function changeStatusDropdown(link, newStatus, postId) {
            const menu = link.closest('.status-dropdown-menu');
            if (menu) menu.classList.remove('show');
            
            // Allow immediate UI feedback if desired, or wait for fetch
            // Let's implement fetch update
            fetch('update_status.php', {
                 method: 'POST',
                 headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                 body: 'id=' + encodeURIComponent(postId) + '&status=' + encodeURIComponent(newStatus)
            })
            .then(r => r.json())
            .then(data => {
                 if (data.success) {
                     const statusTranslations = {
                         'published': '<?= $lang['status_published'] ?>',
                         'draft': '<?= $lang['status_draft'] ?>',
                         'scheduled': '<?= $lang['status_scheduled'] ?>',
                         'archived': '<?= $lang['status_archived'] ?>'
                     };
                     const translatedStatus = statusTranslations[newStatus] || newStatus;
                     showToast('<?= $lang['toast_status_updated'] ?>'.replace('%s', translatedStatus));
                     // Update the button UI
                     const dropdown = link.closest('.status-dropdown');
                     if (dropdown) {
                         const btn = dropdown.querySelector('.status-dropdown-toggle');
                         // Simplified UI update - ideally checks status colors
                         // For now just reload or specific DOM manipulation
                          setTimeout(() => window.location.reload(), 500);
                     }
                 } else {
                     showToast('<?= $lang['toast_failed_update'] ?>: ' + data.message, 'error');
                 }
            })
            .catch(e => showToast('Network error', 'error'));
        }

        function toggleFeatured(postId, btn) {
            const isActive = btn.classList.contains('active');
            const newStatus = isActive ? '0' : '1';
            
            // Optimistic UI Update: Toggle immediately
            btn.classList.toggle('active');
            const nowActive = btn.classList.contains('active');
            btn.title = nowActive ? 'Remove from Main Page' : 'Show on Main Page';

            fetch('update_featured.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id=' + encodeURIComponent(postId) + '&featured=' + encodeURIComponent(newStatus)
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    // Show pink toast for featured success
                    if (nowActive) {
                        showToast('<?= $lang['toast_added_featured'] ?>', 'featured');
                    } else {
                        showToast('<?= $lang['toast_removed_featured'] ?>', 'info');
                    }
                } else {
                    // Revert UI on failure
                    btn.classList.toggle('active');
                    btn.title = isActive ? 'Remove from Main Page' : 'Show on Main Page'; // status was reverted
                    showToast(data.message || '<?= $lang['toast_failed_update'] ?>', 'error');
                }
            })
            .catch(err => {
                // Revert UI on network error
                btn.classList.toggle('active');
                btn.title = isActive ? 'Remove from Main Page' : 'Show on Main Page';
                showToast('<?= $lang['toast_network_error'] ?>', 'error');
            });
        }
    </script>
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
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const hash = window.location.hash.substring(1);
            if (hash) {
                const target = document.getElementById(hash);
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    
                    // Simple highlight for buttons like 'filtersToggleBtn'
                    target.style.transition = 'all 0.5s ease';
                    target.style.boxShadow = '0 0 0 4px rgba(210, 37, 215, 0.4)';
                    
                    setTimeout(() => {
                        target.style.boxShadow = '';
                    }, 2000);

                    // If it's the filter button, maybe we want to shake it or something, but simplified is fine.
                    if(hash === 'filtersToggleBtn') {
                        target.click(); // Auto-open filters if linked? optional but nice
                    }
                }
            }
        });
    </script>
</body>

</html>