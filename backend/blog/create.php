<?php
require_once dirname(__DIR__) . '/session_config.php';
require '../database.php';
require '../gridfs.php';
require '../config.php';
require 'translation.php';

if (!isset($_SESSION['admin'])) {
    header('Location: ../login.php');
    exit;
}

$userRole = $_SESSION['admin']['position'] ?? 'Editor';
$userPerms = $_SESSION['admin']['permissions'] ?? [];
if ($userPerms instanceof \MongoDB\Model\BSONArray) {
    $userPerms = $userPerms->getArrayCopy();
} else {
    $userPerms = (array) $userPerms;
}
$isAdmin = ($_SESSION['admin']['role'] ?? 'admin') === 'admin';
if ($isAdmin) {
    $userRole = 'Admin';
}
$hasBlogAccess = $isAdmin || in_array($userRole, ['Content Manager', 'Author']);

// Get blog type from URL
$blogType = $_GET['type'] ?? 'talent_hub';
$validBlogTypes = ['talent_hub', 'banking', 'karriere'];
if (!in_array($blogType, $validBlogTypes)) {
    $blogType = 'talent_hub';
}

if (!$hasBlogAccess) {
    header('Location: ../dashboard.php');
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    set_time_limit(300);

    $submitToken = $_POST['submit_token'] ?? null;
    if (!$submitToken) {
        $_SESSION['error_message'] = 'Invalid form submission.';
        header('Location: create.php');
        exit;
    }
    if (!isset($_SESSION['used_submit_tokens'])) {
        $_SESSION['used_submit_tokens'] = [];
    }
    if (in_array($submitToken, $_SESSION['used_submit_tokens'], true)) {
        $_SESSION['toast_message'] = $lang['msg_submission_already_processed'] ?? 'Submission already processed.';
        $_SESSION['toast_type'] = 'info';
        header('Location: index.php');
        exit;
    }
    $_SESSION['used_submit_tokens'][] = $submitToken;
    $title_de = trim($_POST['title']);
    $content_de = $_POST['content'];
    $content_de = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $content_de);
    $content_de = preg_replace('/\s{2,}/', ' ', $content_de);
    if (preg_match_all('/<img[^>]+src=["\'](https?:\/\/[^"\']+)["\'][^>]*>/i', $content_de, $matches)) {
        require_once __DIR__ . '/../gridfs.php';
        foreach ($matches[1] as $remoteUrl) {
            try {
                $data = null;
                if (function_exists('downloadRemoteUrl')) {
                    $data = downloadRemoteUrl($remoteUrl, 5242880);
                }
                if ($data === false || $data === null) {
                    $opts = [
                        'http' => [
                            'method' => 'GET',
                            'timeout' => 8,
                            'header' => "User-Agent: EntriksBot/1.0\r\n"
                        ],
                        'ssl' => [
                            'verify_peer' => false,
                            'verify_peer_name' => false
                        ]
                    ];
                    $context = stream_context_create($opts);
                    $data = @file_get_contents($remoteUrl, false, $context, 0, 5242880);
                }
                if ($data === false || $data === '') {
                    error_log('Failed to download remote image: ' . $remoteUrl);
                    continue;
                }

                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_buffer($finfo, $data);
                finfo_close($finfo);
                if (!$mime || strpos($mime, 'image/') === false) {
                    error_log('Remote URL not an image: ' . $remoteUrl . ' mime=' . var_export($mime, true));
                    continue;
                }

                $fname = basename(parse_url($remoteUrl, PHP_URL_PATH) ?: 'remote.jpg');

                $newId = uploadImageDataToGridFS($data, $fname);
                if ($newId) {
                    $local = 'backend/image.php?id=' . urlencode($newId);
                    $content_de = str_replace($remoteUrl, $local, $content_de);
                }
            } catch (Exception $e) {
                error_log('Error processing remote image ' . $remoteUrl . ': ' . $e->getMessage());
                continue;
            }
        }
    }
    $publish_at = isset($_POST['publish_at']) ? trim($_POST['publish_at']) : '';
    $status = 'draft';
    $publishAtMongo = null;
    $status = $_POST['status'] ?? 'draft';

    if ($status === 'published') {
        $publishAtMongo = null;
    } elseif ($publish_at) {
        $userTz = new DateTimeZone('Europe/Berlin');
        $dt = DateTime::createFromFormat('Y-m-d\TH:i', $publish_at, $userTz);
        if ($dt && $dt->getTimestamp() > time()) {
            $status = 'scheduled';
            $dtUtc = clone $dt;
            $dtUtc->setTimezone(new DateTimeZone('UTC'));
            $publishAtMongo = new MongoDB\BSON\UTCDateTime($dtUtc->getTimestamp() * 1000);
        } else {
            $publishAtMongo = null;
        }
    }
    $author = $_POST['author'] ?? $_SESSION['admin']['name'] ?? 'René Schirner';

    $tags = !empty($_POST['tags']) ? array_map('trim', explode(',', $_POST['tags'])) : [];
    if (!empty($_POST['categories'])) {
        if (is_array($_POST['categories'])) {
            $categories = array_map('trim', $_POST['categories']);
        } else {
            $categories = array_map('trim', explode(',', $_POST['categories']));
        }
    } else {
        $categories = [];
    }

    if (strlen($content_de) > 2097152) {
        $_SESSION['error_message'] = 'Content too large (max 2MB). Please reduce content or split into multiple posts.';
        header('Location: create.php');
        exit;
    }

    $imageId = null;
    if (isset($_FILES['image']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
        if ($_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $imageIdString = uploadImageToGridFS($_FILES['image']);
            if ($imageIdString) {
                $imageId = new MongoDB\BSON\ObjectId($imageIdString);
            }
        }
    }

    require_once __DIR__ . '/../load_env.php';
    if (empty($_ENV['GOOGLE_API_KEY']) && empty(getenv('GOOGLE_API_KEY'))) {
        $_SESSION['warning_message'] = 'Translation failed: Google API Key is missing or not loaded.';
    }
    $title_en = translateToEnglish($title_de);
    $content_en = translateToEnglish($content_de);

    $blogData = [
        'title_de' => $title_de,
        'title_en' => $title_en,
        'content_de' => $content_de,
        'content_en' => $content_en,
        'status' => $status,
        'author' => $author,
        'author_email' => $_SESSION['admin']['email'] ?? null,
        'tags' => $tags,
        'categories' => $categories,
        'views' => 0,
        'created_at' => new MongoDB\BSON\UTCDateTime(),
        'translation_status' => 'completed',
        'translated_at' => new MongoDB\BSON\UTCDateTime(),
        'blog_type' => $_POST['blog_type'] ?? 'talent_hub'
    ];
    if ($publishAtMongo) {
        $blogData['publish_at'] = $publishAtMongo;
    }
    if ($imageId) {
        $blogData['image'] = $imageId;
    }
    $result = $db->blog->insertOne($blogData);
    $postId = $result->getInsertedId();

    $_SESSION['toast_message'] = $lang['msg_post_created_and_translated'] ?? 'Blog post created and translated successfully!';
    $_SESSION['toast_type'] = 'success';
    $_SESSION['pending_post_id'] = (string) $postId;

    include 'cache_featured.php';

    header('Location: index.php?type=' . urlencode($blogType));
    exit;
}
?>
<!DOCTYPE html>
<html>

<head>
    <title><?= $lang['create_post_title'] ?></title>
    <link rel="shortcut icon" href="<?= htmlspecialchars($siteFaviconUrl) ?>" type="image/x-icon">
    <link rel="stylesheet" href="../assets/css/dashboard.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../assets/css/post-editor.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../assets/css/datetime-picker.css">
    <link rel="stylesheet" href="../assets/css/ai-assistant.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../assets/css/responsive-editor.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="../assets/js/global-search.js?v=<?= time() ?>" defer></script>
    <script src="../assets/js/datetime-picker.js" defer></script>
    <script src="../assets/js/responsive-editor.js?v=<?= time() ?>" defer></script>
    <style>
        .rich-text-content a {
            color: #ffffff !important;
            text-decoration: underline !important;
        }
    </style>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>

    <div class="layout">
        <?php $sidebarVariant = 'blog';
        $activeMenu = 'blog';
        include __DIR__ . '/../partials/sidebar.php'; ?>

        <main class="content">
            <!-- Blur Background Theme -->
            <div class="blur-bg-theme bottom-right"></div>

            <?php
            $pageTitle = $lang['create_post_title'];
            include __DIR__ . '/../partials/topbar.php';
            ?>
            <?php $__minPublish = (new DateTime('now', new DateTimeZone('Europe/Berlin')))->format('Y-m-d\TH:i'); ?>
            <form method="POST" enctype="multipart/form-data" id="blogForm">
                <?php
                try {
                    $__submit_token = bin2hex(random_bytes(16));
                } catch (Exception $e) {
                    $__submit_token = uniqid('', true);
                }
                $_SESSION['last_submit_token'] = $__submit_token;
                ?>
                <input type="hidden" name="submit_token" value="<?= $__submit_token ?>">

                <div style="margin-bottom:20px; width:280px;">
                    <label style="display:flex;align-items:center;gap:8px; margin-bottom:8px; color:#9ca3af; font-size:13px; font-weight:500;">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width:18px;height:18px;color:#7675ec;">
                            <path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25ZM12.75 6a.75.75 0 0 0-1.5 0v6c0 .414.336.75.75.75h4.5a.75.75 0 0 0 0-1.5h-3.75V6Z" clip-rule="evenodd" />
                        </svg>
                        <?= $lang['label_page_section'] ?? 'Page Section' ?>
                    </label>
                    <div class="select-wrapper" style="position:relative; width:100%;">
                        <select name="blog_type" style="width:100%; background:rgba(255,255,255,0.05); color:#fff; border:1px solid rgba(255,255,255,0.1); border-radius:8px; padding:12px 40px 12px 16px; font-size:14px; cursor:pointer; appearance:none; -webkit-appearance:none;">
                            <option value="talent_hub" <?= $blogType === 'talent_hub' ? 'selected' : '' ?> style="background:#1a1a2e; color:#fff;"><?= $lang['page_talent_hub'] ?? 'Talent Hub' ?></option>
                            <option value="banking" <?= $blogType === 'banking' ? 'selected' : '' ?> style="background:#1a1a2e; color:#fff;"><?= $lang['page_banking'] ?? 'Banking' ?></option>
                            <option value="karriere" <?= $blogType === 'karriere' ? 'selected' : '' ?> style="background:#1a1a2e; color:#fff;"><?= $lang['page_karriere'] ?? 'Karriere' ?></option>
                        </select>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width:20px; height:20px; position:absolute; right:12px; top:50%; transform:translateY(-50%); color:#7675ec; pointer-events:none;">
                            <path fill-rule="evenodd" d="M12.53 16.28a.75.75 0 0 1-1.06 0l-7.5-7.5a.75.75 0 0 1 1.06-1.06L12 14.69l6.97-6.97a.75.75 0 1 1 1.06 1.06l-7.5 7.5Z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="page-section-hint" style="margin-top:8px; position:relative;">
                        <div class="hint-trigger" style="display:flex; align-items:center; gap:6px; color:#9ca3af; font-size:12px; cursor:pointer;">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width:16px; height:16px; color:#7675ec;">
                                <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm8.706-1.442c1.146-.573 2.437.463 2.126 1.706l-.709 2.836.042-.02a.75.75 0 0 1 .671 1.34l-.041.022c-1.147.573-2.438-.463-2.127-1.706l.71-2.836-.042.02a.75.75 0 0 1-.671-1.34l.041-.022ZM12 9a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z" clip-rule="evenodd" />
                            </svg>
                            <span><?= $lang['page_section_hint_short'] ?? 'Where will this post appear?' ?></span>
                        </div>
                        <div class="hint-tooltip" style="display:none; position:absolute; left:0; top:100%; margin-top:8px; background:#1a1a2e; border:1px solid rgba(255,255,255,0.1); border-radius:8px; padding:12px 16px; max-width:280px; z-index:100; box-shadow:0 4px 12px rgba(0,0,0,0.3);">
                            <p style="margin:0; color:#d1d5db; font-size:13px; line-height:1.5;"><?= $lang['page_section_hint'] ?? 'Selecting Talent Hub will display this post on the Talent Hub page. Each page section shows posts on its respective page.' ?></p>
                        </div>
                    </div>
                    <script>
                        (function() {
                            var hint = document.querySelector('.page-section-hint');
                            var trigger = hint.querySelector('.hint-trigger');
                            var tooltip = hint.querySelector('.hint-tooltip');
                            var show = function() { tooltip.style.display = 'block'; };
                            var hide = function() { tooltip.style.display = 'none'; };
                            trigger.addEventListener('mouseenter', show);
                            trigger.addEventListener('mouseleave', hide);
                            trigger.addEventListener('click', function(e) {
                                e.preventDefault();
                                tooltip.style.display = tooltip.style.display === 'block' ? 'none' : 'block';
                            });
                        })();
                    </script>
                </div>

                <div class="metadata-section">
                    <div>
                        <label style="display:flex;align-items:center;gap:8px;">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24" class="icon"
                                style="width:18px;height:18px;flex:0 0 auto;color:#7675ec;">
                                <path
                                    d="M4.5 4.5h15a.75.75 0 0 1 .75.75v1.5a.75.75 0 0 1-.75.75H13.5v11.25h3.75a.75.75 0 0 1 .75.75v1.5a.75.75 0 0 1-.75.75H6.75a.75.75 0 0 1-.75-.75v-1.5c0-.414.336-.75.75-.75H10.5V7.5H4.5a.75.75 0 0 1-.75-.75v-1.5c0-.414.336-.75.75-.75Z" />
                            </svg>
                            <?= $lang['label_post_title'] ?>
                        </label>
                        <input type="text" name="title" id="title" class="input-field" required
                            placeholder="<?= $lang['input_title_placeholder'] ?>">
                    </div>
                    <div>
                        <label style="display:flex;align-items:center;gap:8px;">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                                class="size-6" style="width:18px;height:18px;color:#7675ec;flex:0 0 auto;">
                                <path fill-rule="evenodd"
                                    d="M7.5 6a4.5 4.5 0 1 1 9 0 4.5 4.5 0 0 1-9 0ZM3.751 20.105a8.25 8.25 0 0 1 16.498 0 .75.75 0 0 1-.437.695A18.683 18.683 0 0 1 12 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 0 1-.437-.695Z"
                                    clip-rule="evenodd" />
                            </svg>
                            <?= $lang['label_author'] ?>
                        </label>
                        <input type="text" name="author" class="input-field" placeholder="<?= htmlspecialchars($_SESSION['admin']['name'] ?? 'René Schirner') ?>"
                            value="<?= htmlspecialchars($_SESSION['admin']['name'] ?? 'René Schirner') ?>">
                    </div>
                    <div>
                        <label style="display:flex;align-items:center;">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                                class="size-6"
                                style="width:18px;height:18px;margin-right:6px;flex:0 0 auto;color:#7675ec;">
                                <path fill-rule="evenodd"
                                    d="M5.25 2.25a3 3 0 0 0-3 3v4.318a3 3 0 0 0 .879 2.121l9.58 9.581c.92.92 2.39 1.186 3.548.428a18.849 18.849 0 0 0 5.441-5.44c.758-1.16.492-2.629-.428-3.548l-9.58-9.581a3 3 0 0 0-2.122-.879H5.25ZM6.375 7.5a1.125 1.125 0 1 0 0-2.25 1.125 1.125 0 0 0 0 2.25Z"
                                    clip-rule="evenodd" />
                            </svg>
                            <?= $lang['label_tags'] ?>
                        </label>
                        <div class="tag-input-wrapper">
                            <div class="tag-container" id="tagContainer" onclick="document.getElementById('tagInput').focus()" style="position: relative;">
                                <input type="text" id="tagInput" class="tag-input-field" placeholder="<?= $lang['input_tags_placeholder'] ?>" list="availableTags" style="padding-right: 35px;">
                                <datalist id="availableTags"></datalist>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6" style="width: 18px; height: 18px; position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: rgba(255,255,255,0.5); pointer-events: none;">
                                    <path fill-rule="evenodd" d="M12.53 16.28a.75.75 0 0 1-1.06 0l-7.5-7.5a.75.75 0 0 1 1.06-1.06L12 14.69l6.97-6.97a.75.75 0 1 1 1.06 1.06l-7.5 7.5Z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <input type="hidden" name="tags" id="hiddenTagsInput">
                            <div class="tag-helper" style="display:flex; align-items:center; gap:8px;">
                                <span class="helper-text" style="display:flex; align-items:center; gap:4px;">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
                                    </svg>
                                    <?= $lang['hint_tag_backspace'] ?>
                                </span>
                                <span id="tagCount" class="tag-count">0 <?= $lang['tag_count_plural'] ?></span>
                            </div>
                        </div>
                    </div>
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const tagContainer = document.getElementById('tagContainer');
                            const tagInput = document.getElementById('tagInput');
                            const hiddenInput = document.getElementById('hiddenTagsInput');
                            const tagCountSpan = document.getElementById('tagCount');
                            const availableTagsList = document.getElementById('availableTags');
                            
                            let tags = [];

                            fetch('get_tags.php')
                                .then(res => res.json())
                                .then(data => {
                                    if (Array.isArray(data)) {
                                        data.forEach(t => {
                                            const opt = document.createElement('option');
                                            opt.value = t;
                                            availableTagsList.appendChild(opt);
                                        });
                                    }
                                })
                                .catch(err => console.error('Failed to fetch tags', err));

                            function renderTags() {
                                const pills = tagContainer.querySelectorAll('.tag-pill');
                                pills.forEach(p => p.remove());

                                tags.forEach((tag, index) => {
                                    const pill = document.createElement('div');
                                    pill.className = 'tag-pill';
                                    pill.innerHTML = `#${tag} <button type="button" class="tag-remove" onclick="removeTag(${index})">×</button>`;
                                    tagContainer.insertBefore(pill, tagInput);
                                });

                                hiddenInput.value = tags.join(',');
                                const tagWord = tags.length !== 1 ? '<?= $lang['tag_count_plural'] ?>' : '<?= $lang['tag_count_singular'] ?>';
                                tagCountSpan.textContent = `${tags.length} ${tagWord}`;
                            }

                            window.removeTag = function(index) {
                                tags.splice(index, 1);
                                renderTags();
                            };

                            function showToast(msg, type = 'info') {
                                let container = document.getElementById('js-toast-container');
                                if (!container) {
                                    container = document.createElement('div');
                                    container.id = 'js-toast-container';
                                    container.style.position = 'fixed';
                                    container.style.top = '20px';
                                    container.style.right = '20px';
                                    container.style.zIndex = '9999';
                                    container.style.display = 'flex';
                                    container.style.flexDirection = 'column';
                                    container.style.gap = '10px';
                                    document.body.appendChild(container);
                                }
                                
                                const toast = document.createElement('div');
                                toast.style.minWidth = '250px';
                                toast.style.padding = '12px 16px';
                                toast.style.borderRadius = '8px';
                                toast.style.color = '#fff';
                                toast.style.fontSize = '14px';
                                toast.style.boxShadow = '0 4px 12px rgba(0,0,0,0.3)';
                                toast.style.opacity = '0';
                                toast.style.transition = 'opacity 0.3s ease';
                                toast.style.display = 'flex';
                                toast.style.alignItems = 'center';
                                toast.style.justifyContent = 'space-between';
                                
                                if (type === 'warning') {
                                    toast.style.background = '#D225D7';
                                } else if (type === 'error') {
                                    toast.style.background = '#ef4444';
                                } else {
                                    toast.style.background = '#10b981';
                                }
                                
                                toast.innerHTML = `<span>${msg}</span><button style="background:none;border:none;color:white;cursor:pointer;font-size:16px;margin-left:10px;">&times;</button>`;
                                
                                container.appendChild(toast);
                                
                                requestAnimationFrame(() => {
                                    toast.style.opacity = '1';
                                });
                                
                                toast.querySelector('button').onclick = () => {
                                    toast.style.opacity = '0';
                                    setTimeout(() => toast.remove(), 300);
                                };

                                setTimeout(() => {
                                    if (toast.isConnected) {
                                        toast.style.opacity = '0';
                                        setTimeout(() => toast.remove(), 300);
                                    }
                                }, 3000);
                            }

                            tagInput.addEventListener('keydown', function(e) {
                                if (e.key === 'Enter' || e.key === ',') {
                                    e.preventDefault();
                                    const val = this.value.trim().replace(/,/g, '');
                                    if (val && !tags.includes(val)) {
                                        if (tags.length >= 7) {
                                            showToast('Maximum 7 tags allowed.', 'warning');
                                            return;
                                        }
                                        tags.push(val);
                                        this.value = '';
                                        renderTags();
                                    }
                                } else if (e.key === 'Backspace' && !this.value) {
                                    if (tags.length > 0) {
                                        tags.pop();
                                        renderTags();
                                    }
                                }
                            });

                            tagContainer.addEventListener('click', function(e) {
                                if (e.target !== tagInput && !e.target.classList.contains('tag-remove')) {
                                    tagInput.focus();
                                }
                            });
                        });
                    </script>
                    <div>
                        <label style="display:flex;align-items:center;gap:8px;">
                            <button type="button" id="openCategoryModalBtn"
                                style="background:none;border:none;cursor:pointer;padding:0;display:flex;align-items:center;"
                                aria-label="Manage categories" title="Manage categories">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                                    class="size-6" style="width:18px;height:18px;color:#7675ec;">
                                    <path
                                        d="M6 3a3 3 0 0 0-3 3v2.25a3 3 0 0 0 3 3h2.25a3 3 0 0 0 3-3V6a3 3 0 0 0-3-3H6ZM15.75 3a3 3 0 0 0-3 3v2.25a3 3 0 0 0 3 3H18a3 3 0 0 0 3-3V6a3 3 0 0 0-3-3h-2.25ZM6 12.75a3 3 0 0 0-3 3V18a3 3 0 0 0 3 3h2.25a3 3 0 0 0 3-3v-2.25a3 3 0 0 0-3-3H6ZM17.625 13.5a.75.75 0 0 0-1.5 0v2.625H13.5a.75.75 0 0 0 0 1.5h2.625v2.625a.75.75 0 0 0 1.5 0v-2.625h2.625a.75.75 0 0 0 0-1.5h-2.625V13.5Z" />
                                </svg>
                            </button>
                            <?= $lang['label_categories'] ?>
                        </label>
                        <div class="select-wrapper">
                            <select name="categories" id="categoriesDropdown" class="input-field"></select>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="select-icon">
                                <path fill-rule="evenodd" d="M12.53 16.28a.75.75 0 0 1-1.06 0l-7.5-7.5a.75.75 0 0 1 1.06-1.06L12 14.69l6.97-6.97a.75.75 0 1 1 1.06 1.06l-7.5 7.5Z" clip-rule="evenodd" />
                            </svg>
                        </div>

                        <div id="categoryModal" class="modal-overlay"
                            style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.7);z-index:999;align-items:center;justify-content:center;">
                            <div
                                style="background:#222;padding:32px 24px;border-radius:12px;min-width:320px;max-width:90vw;box-shadow:0 4px 32px rgba(0,0,0,0.2);">
                                <h3 style="color:#fff;margin-bottom:18px;"><?= $lang['label_categories'] ?></h3>
                                <input type="text" id="modalCategoryInput" class="input-field"
                                    placeholder="<?= $lang['label_categories'] ?>" style="width:100%;margin-bottom:16px;">
                                <div style="display:flex;gap:12px;justify-content:flex-end;">
                                    <button type="button" id="closeCategoryModalBtn"
                                        style="padding:8px 20px;background:#e5e7eb;border:1px solid #d1d5db;border-radius:8px;color:#222;cursor:pointer;font-size:14px;font-weight:500;"><?= $lang['action_cancel'] ?></button>
                                    <button type="button" id="addCategoryModalBtn"
                                        style="padding:8px 20px;background:#7675ec;border-radius:8px;color:#fff;cursor:pointer;font-size:14px;font-weight:600;border:none;"><?= $lang['action_apply'] ?></button>
                                </div>
                            </div>
                        </div>

                        <div id="linkModal" class="modal-overlay"
                            style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.7);z-index:999;align-items:center;justify-content:center;">
                            <div
                                style="background:#222;padding:32px 24px;border-radius:12px;min-width:320px;max-width:90vw;box-shadow:0 4px 32px rgba(0,0,0,0.2);">
                                <h3 style="color:#fff;margin-bottom:18px;"><?= $lang['modal_link_title'] ?></h3>
                                <input type="text" id="modalLinkInput" class="input-field"
                                    placeholder="<?= $lang['modal_link_placeholder'] ?>" style="width:100%;margin-bottom:16px;">
                                <div style="display:flex;gap:12px;justify-content:flex-end;">
                                    <button type="button" id="closeLinkModalBtn"
                                        style="padding:8px 20px;background:#e5e7eb;border:1px solid #d1d5db;border-radius:8px;color:#222;cursor:pointer;font-size:14px;font-weight:500;"><?= $lang['action_cancel'] ?></button>
                                    <button type="button" id="addLinkModalBtn"
                                        style="padding:8px 20px;background:#7675ec;border-radius:8px;color:#fff;cursor:pointer;font-size:14px;font-weight:600;border:none;"><?= $lang['action_add'] ?></button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label style="display:flex;align-items:center;gap:8px;">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                                class="size-6" style="width:18px;height:18px;color:#7675ec;flex:0 0 auto;">
                                <path fill-rule="evenodd"
                                    d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25ZM12.75 6a.75.75 0 0 0-1.5 0v6c0 .414.336.75.75.75h4.5a.75.75 0 0 0 0-1.5h-3.75V6Z"
                                    clip-rule="evenodd" />
                            </svg>
                            <?= $lang['label_schedule_date'] ?>
                        </label>
                        <div class="datetime-wrapper">
                            <input id="publishAtInput" type="datetime-local" name="publish_at"
                                class="input-field no-native" min="<?= $__minPublish ?>"
                                data-locale="<?= $_SESSION['lang'] ?? 'en' ?>">
                            <button type="button" class="datetime-icon" aria-label="<?= $lang['action_open_picker'] ?>"
                                title="<?= $lang['action_open_picker'] ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                                    class="size-6" style="width:20px;height:20px;">
                                    <path
                                        d="M12.75 12.75a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM7.5 15.75a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5ZM8.25 17.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM9.75 15.75a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5ZM10.5 17.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM12 15.75a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5ZM12.75 17.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM14.25 15.75a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5ZM15 17.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM16.5 15.75a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5ZM15 12.75a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM16.5 13.5a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z" />
                                    <path fill-rule="evenodd"
                                        d="M6.75 2.25A.75.75 0 0 1 7.5 3v1.5h9V3A.75.75 0 0 1 18 3v1.5h.75a3 3 0 0 1 3 3v11.25a3 3 0 0 1-3 3H5.25a3 3 0 0 1-3-3V7.5a3 3 0 0 1 3-3H6V3a.75.75 0 0 1 .75-.75Zm13.5 9a1.5 1.5 0 0 0-1.5-1.5H5.25a1.5 1.5 0 0 0-1.5 1.5v7.5a1.5 1.5 0 0 0 1.5 1.5h13.5a1.5 1.5 0 0 0 1.5-1.5v-7.5Z"
                                        clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                        <div class="field-helper">
                            <span class="helper-text">
                                <?= $lang['hint_schedule_blank'] ?>
                            </span>
                        </div>
                    </div>
                    <div>
                        <label style="display:flex;align-items:center;gap:8px;">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                                class="size-6" style="width:18px;height:18px;color:#7675ec;flex:0 0 auto;">
                                <path fill-rule="evenodd"
                                    d="M1.5 6a2.25 2.25 0 0 1 2.25-2.25h16.5A2.25 2.25 0 0 1 22.5 6v12a2.25 2.25 0 0 1-2.25 2.25H3.75A2.25 2.25 0 0 1 1.5 18V6ZM3 16.06V18c0 .414.336.75.75.75h16.5A.75.75 0 0 0 21 18v-1.94l-2.69-2.689a1.5 1.5 0 0 0-2.12 0l-.88.879.97.97a.75.75 0 1 1-1.06 1.06l-5.16-5.159a1.5 1.5 0 0 0-2.12 0L3 16.061Zm10.125-7.81a1.125 1.125 0 1 1 2.25 0 1.125 1.125 0 0 1-2.25 0Z"
                                    clip-rule="evenodd" />
                            </svg>
                            <?= $lang['label_upload_image'] ?>
                        </label>
                        <div class="datetime-wrapper" id="tour-image-upload" onclick="document.getElementById('imageInput').click()" style="cursor: pointer;">
                            <input type="text" id="fileTextDisplay" class="input-field" readonly placeholder="<?= $lang['btn_browse_files'] ?>" style="cursor: pointer; padding-left: 48px;">
                            <div id="thumbnailPreview" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); display: flex; align-items: center;">
                                <img id="thumbnailImg" src="" alt="Thumbnail" style="width: 28px; height: 28px; border-radius: 4px; object-fit: cover; border: 1px solid rgba(255,255,255,0.1); display: none;">
                            </div>
                            <button type="button" class="datetime-icon" style="pointer-events: none;">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6" style="width:18px;height:18px;color:rgba(255,255,255,0.5);">
                                    <path fill-rule="evenodd" d="M11.47 2.47a.75.75 0 0 1 1.06 0l4.5 4.5a.75.75 0 0 1-1.06 1.06l-3.22-3.22V16.5a.75.75 0 0 1-1.5 0V4.81L8.03 8.03a.75.75 0 0 1-1.06-1.06l4.5-4.5ZM3 15.75a.75.75 0 0 1 .75.75v2.25a1.5 1.5 0 0 0 1.5 1.5h13.5a1.5 1.5 0 0 0 1.5-1.5V16.5a.75.75 0 0 1 1.5 0v2.25a3 3 0 0 1-3 3H5.25a3 3 0 0 1-3-3V16.5a.75.75 0 0 1 .75-.75Z" clip-rule="evenodd" />
                                </svg>
                            </button>
                            <input type="file" name="image" accept="image/*" id="imageInput" style="display:none;">
                        </div>
                        <div class="field-helper">
                            <span class="helper-text">
                                <?= $lang['hint_thumbnail_create'] ?>
                            </span>
                        </div>
                    </div>
                </div>


                <div class="create-layout">
                    <div>
                        <div class="block-palette">
                            <h3 style="display:flex;align-items:center;gap:8px;">
                                <?= $lang['block_palette_title'] ?>
                            </h3>
                            <div class="block-item" draggable="true" data-type="heading">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24"
                                    class="icon" style="width:18px;height:18px;color:#7675ec;flex:0 0 auto;">
                                    <path
                                        d="M5.25 4.5a.75.75 0 0 1 .75-.75h1.5a.75.75 0 0 1 .75.75v6.75h7.5V4.5a.75.75 0 0 1 .75-.75h1.5a.75.75 0 0 1 .75.75v15a.75.75 0 0 1-.75.75h-1.5a.75.75 0 0 1-.75-.75v-6.75h-7.5v6.75a.75.75 0 0 1-.75.75h-1.5a.75.75 0 0 1-.75-.75v-15Z" />
                                </svg>
                                <?= $lang['block_heading'] ?>
                            </div>
                            <div class="block-item" draggable="true" data-type="paragraph">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                                    class="size-6">
                                    <path fill-rule="evenodd"
                                        d="M3 6.75A.75.75 0 0 1 3.75 6h16.5a.75.75 0 0 1 0 1.5H3.75A.75.75 0 0 1 3 6.75ZM3 12a.75.75 0 0 1 .75-.75h16.5a.75.75 0 0 1 0 1.5H3.75A.75.75 0 0 1 3 12Zm0 5.25a.75.75 0 0 1 .75-.75H12a.75.75 0 0 1 0 1.5H3.75a.75.75 0 0 1-.75-.75Z"
                                        clip-rule="evenodd" />
                                </svg>
                                <?= $lang['block_paragraph'] ?>
                            </div>
                            <div class="block-item" draggable="true" data-type="image">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                                    class="size-6">
                                    <path fill-rule="evenodd"
                                        d="M1.5 6a2.25 2.25 0 0 1 2.25-2.25h16.5A2.25 2.25 0 0 1 22.5 6v12a2.25 2.25 0 0 1-2.25 2.25H3.75A2.25 2.25 0 0 1 1.5 18V6ZM3 16.06V18c0 .414.336.75.75.75h16.5A.75.75 0 0 0 21 18v-1.94l-2.69-2.689a1.5 1.5 0 0 0-2.12 0l-.88.879.97.97a.75.75 0 1 1-1.06 1.06l-5.16-5.159a1.5 1.5 0 0 0-2.12 0L3 16.061Zm10.125-7.81a1.125 1.125 0 1 1 2.25 0 1.125 1.125 0 0 1-2.25 0Z"
                                        clip-rule="evenodd" />
                                </svg>
                                <?= $lang['block_image'] ?>
                            </div>
                            <div class="block-item" draggable="true" data-type="quote">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor"
                                    class="size-4">
                                    <path fill-rule="evenodd"
                                        d="M1 8c0-3.43 3.262-6 7-6s7 2.57 7 6-3.262 6-7 6c-.423 0-.838-.032-1.241-.094-.9.574-1.941.948-3.06 1.06a.75.75 0 0 1-.713-1.14c.232-.378.395-.804.469-1.26C1.979 11.486 1 9.86 1 8Z"
                                        clip-rule="evenodd" />
                                </svg>
                                <?= $lang['block_quote'] ?>
                            </div>
                            <div class="block-item" draggable="true" data-type="list">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                                    class="size-6">
                                    <path fill-rule="evenodd"
                                        d="M2.625 6.75a1.125 1.125 0 1 1 2.25 0 1.125 1.125 0 0 1-2.25 0Zm4.875 0A.75.75 0 0 1 8.25 6h12a.75.75 0 0 1 0 1.5h-12a.75.75 0 0 1-.75-.75ZM2.625 12a1.125 1.125 0 1 1 2.25 0 1.125 1.125 0 0 1-2.25 0ZM7.5 12a.75.75 0 0 1 .75-.75h12a.75.75 0 0 1 0 1.5h-12A.75.75 0 0 1 7.5 12Zm-4.875 5.25a1.125 1.125 0 1 1 2.25 0 1.125 1.125 0 0 1-2.25 0Zm4.875 0a.75.75 0 0 1 .75-.75h12a.75.75 0 0 1 0 1.5h-12a.75.75 0 0 1-.75-.75Z"
                                        clip-rule="evenodd" />
                                </svg>
                                <?= $lang['block_list_ul'] ?>
                            </div>
                            <div class="block-item" draggable="true" data-type="orderedlist">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                                    class="size-6">
                                    <path fill-rule="evenodd"
                                        d="M7.491 5.992a.75.75 0 0 1 .75-.75h12a.75.75 0 1 1 0 1.5h-12a.75.75 0 0 1-.75-.75ZM7.49 11.995a.75.75 0 0 1 .75-.75h12a.75.75 0 0 1 0 1.5h-12a.75.75 0 0 1-.75-.75ZM7.491 17.994a.75.75 0 0 1 .75-.75h12a.75.75 0 1 1 0 1.5h-12a.75.75 0 0 1-.75-.75ZM2.24 3.745a.75.75 0 0 1 .75-.75h1.125a.75.75 0 0 1 .75.75v3h.375a.75.75 0 0 1 0 1.5H2.99a.75.75 0 0 1 0-1.5h.375v-2.25H2.99a.75.75 0 0 1-.75-.75ZM2.79 10.602a.75.75 0 0 1 0-1.06 1.875 1.875 0 1 1 2.652 2.651l-.55.55h.35a.75.75 0 0 1 0 1.5h-2.16a.75.75 0 0 1-.53-1.281l1.83-1.83a.375.375 0 0 0-.53-.53.75.75 0 0 1-1.062 0ZM2.24 15.745a.75.75 0 0 1 .75-.75h1.125a1.875 1.875 0 0 1 1.501 2.999 1.875 1.875 0 0 1-1.501 3H2.99a.75.75 0 0 1 0-1.501h1.125a.375.375 0 0 0 .036-.748H3.74a.75.75 0 0 1-.75-.75v-.002a.75.75 0 0 1 .75-.75h.411a.375.375 0 0 0-.036-.748H2.99a.75.75 0 0 1-.75-.75Z"
                                        clip-rule="evenodd" />
                                </svg>
                                <?= $lang['block_list_ol'] ?>
                            </div>
                            <div class="block-item" draggable="true" data-type="divider">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                                    class="size-6">
                                    <path fill-rule="evenodd"
                                        d="M4.25 12a.75.75 0 0 1 .75-.75h14a.75.75 0 0 1 0 1.5H5a.75.75 0 0 1-.75-.75Z"
                                        clip-rule="evenodd" />
                                </svg>
                                <?= $lang['block_divider'] ?>
                            </div>
                        </div>
                    </div>

                    <div class="editor-container">
                        <div class="content-editor" id="contentEditor">
                            <div class="drop-zone" id="dropZone">
                                <p><?= $lang['editor_drag_hint'] ?></p>
                            </div>
                        </div>

                        <input type="hidden" name="content" id="contentInput">

                        <div class="form-actions" style="display:flex; gap:12px; margin-top:20px;">
                            <button type="button" class="btn-preview" onclick="previewPost()">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width:16px; height:16px;">
                                    <path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" />
                                    <path fill-rule="evenodd" d="M1.323 11.447C2.811 6.976 7.028 3.75 12.001 3.75c4.97 0 9.185 3.223 10.675 7.69.12.362.12.752 0 1.113-1.487 4.471-5.705 7.697-10.677 7.697-4.97 0-9.186-3.223-10.675-7.69a1.762 1.762 0 0 1 0-1.113ZM17.25 12a5.25 5.25 0 1 1-10.5 0 5.25 5.25 0 0 1 10.5 0Z" clip-rule="evenodd" />
                                </svg>
                                <?= $lang['action_preview'] ?>
                            </button>
                            <button type="submit" name="status" value="draft" class="btn-save">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width:16px; height:16px;">
                                    <path fill-rule="evenodd" d="M5.625 1.5H9a3.75 3.75 0 0 1 3.75 3.75v1.875c0 1.036.84 1.875 1.875 1.875H16.5a3.75 3.75 0 0 1 3.75 3.75v7.875c0 1.035-.84 1.875-1.875 1.875H5.625a1.875 1.875 0 0 1-1.875-1.875V3.375c0-1.036.84-1.875 1.875-1.875Z" clip-rule="evenodd" />
                                    <path d="M12.971 1.816A5.23 5.23 0 0 1 14.25 5.25v1.875c0 .207.168.375.375.375H16.5a5.23 5.23 0 0 1 3.434 1.279 9.768 9.768 0 0 0-6.963-6.963Z" />
                                </svg>
                                <?= $lang['action_save_draft'] ?>
                            </button>
                            <button type="submit" name="status" value="published" class="btn-publish">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width:16px; height:16px;">
                                    <path fill-rule="evenodd" d="M9.315 7.584C12.195 3.883 16.695 1.5 21.75 1.5a.75.75 0 0 1 .75.75c0 5.056-2.383 9.555-6.084 12.436A6.75 6.75 0 0 1 9.75 22.5a.75.75 0 0 1-.75-.75v-4.131A15.838 15.838 0 0 1 6.382 15H2.25a.75.75 0 0 1-.75-.75 6.75 6.75 0 0 1 7.815-6.666ZM15 6.75a2.25 2.25 0 1 0 0 4.5 2.25 2.25 0 0 0 0-4.5Z" clip-rule="evenodd" />
                                    <path d="M5.26 17.242a.75.75 0 1 0-.897-1.203 5.243 5.243 0 0 0-2.05 5.022.75.75 0 0 0 .625.627 5.243 5.243 0 0 0 5.022-2.051.75.75 0 1 0-1.202-.897 3.744 3.744 0 0 1-3.008 1.51c0-1.23.592-2.323 1.51-3.008Z" />
                                </svg>
                                <?= $lang['action_publish'] ?>
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    fetch('fetch_categories_new.php')
                        .then(res => res.json())
                        .then(categories => {
                            const dropdown = document.getElementById('categoriesDropdown');
                            categories.forEach(cat => {
                                const option = document.createElement('option');
                                option.value = cat;
                                option.textContent = cat;
                                dropdown.appendChild(option);
                            });
                        });

                        const addCategoryBtn = document.getElementById('addCategoryBtn');
                    if (addCategoryBtn) {
                        addCategoryBtn.addEventListener('click', function () {
                            const input = document.getElementById('newCategoryInput');
                            const dropdown = document.getElementById('categoriesDropdown');
                            const newCat = input ? input.value.trim() : '';
                            if (newCat && dropdown && !Array.from(dropdown.options).some(opt => opt.value === newCat)) {
                                const option = document.createElement('option');
                                option.value = newCat;
                                option.textContent = newCat;
                                dropdown.appendChild(option);
                                dropdown.value = newCat;
                                if (input) input.value = '';
                            }
                        });
                    }
                    const openCatBtn = document.getElementById('openCategoryModalBtn');
                    const dropdown = document.getElementById('categoriesDropdown');
                    const modal = document.getElementById('categoryModal');
                    const modalInput = document.getElementById('modalCategoryInput');
                    const addModalBtn = document.getElementById('addCategoryModalBtn');
                    const closeModalBtn = document.getElementById('closeCategoryModalBtn');

                    if (openCatBtn && modal) {
                        openCatBtn.addEventListener('click', function () {
                            modal.style.display = 'flex';
                            if (modalInput) modalInput.focus();
                        });
                    } else if (openCatBtn) {
                        openCatBtn.addEventListener('click', function () {
                            const name = prompt('Add new category');
                            if (!name) return;
                            const newCat = name.trim();
                            if (!newCat) return;
                            if (dropdown && !Array.from(dropdown.options).some(opt => opt.value === newCat)) {
                                const option = document.createElement('option');
                                option.value = newCat;
                                option.textContent = newCat;
                                dropdown.appendChild(option);
                            }
                            if (dropdown) dropdown.value = newCat;
                        });
                    }

                    if (addModalBtn && modalInput && dropdown) {
                        addModalBtn.addEventListener('click', function () {
                            const newCat = modalInput.value.trim();
                            if (newCat) {
                                if (!Array.from(dropdown.options).some(opt => opt.value === newCat)) {
                                    const option = document.createElement('option');
                                    option.value = newCat;
                                    option.textContent = newCat;
                                    dropdown.appendChild(option);
                                }
                                dropdown.value = newCat;
                                fetch('save_category.php', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify({ name: newCat })
                                }).catch(err => console.error('Failed to save category', err));
                                modalInput.value = '';
                                modal.style.display = 'none';
                            }
                        });
                        modalInput.addEventListener('keydown', function (e) {
                            if (e.key === 'Enter') {
                                e.preventDefault();
                                addModalBtn.click();
                            }
                        });
                    }

                    if (closeModalBtn && modal) {
                        closeModalBtn.addEventListener('click', function () {
                            modal.style.display = 'none';
                        });
                    }
                });

                document.addEventListener('DOMContentLoaded', function() {
                    const blogForm = document.getElementById('blogForm');
                    if (blogForm) {
                        blogForm.addEventListener('submit', function(e) {
                            const imageInput = document.getElementById('imageInput');
                            if (imageInput && !imageInput.files.length) {
                                e.preventDefault();
                                
                                const msg = window.entraLanguage === 'de' 
                                    ? 'Bitte wählen Sie ein Beitragsbild aus.' 
                                    : 'Please select a thumbnail image for your post.';
                                
                                if (typeof showToast === 'function') {
                                    showToast(msg, 'error');
                                } else if (window.showNotification) {
                                    window.showNotification({ title: 'ENTRIKS AI', message: msg, type_class: 'error' });
                                } else {
                                    alert(msg);
                                }
                                
                                const uploadSection = document.getElementById('tour-image-upload');
                                if (uploadSection) {
                                    uploadSection.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                    uploadSection.style.borderColor = '#ef4444';
                                    uploadSection.style.boxShadow = '0 0 20px rgba(239, 68, 68, 0.3)';
                                    setTimeout(() => {
                                        uploadSection.style.borderColor = '';
                                        uploadSection.style.boxShadow = '';
                                    }, 3000);
                                }
                            }
                        });
                    }
                });
            </script>
        </main>
    </div>

    <script>
        document.getElementById('imageInput').addEventListener('change', function (e) {
            const file = e.target.files[0];
            const thumbImg = document.getElementById('thumbnailImg');
            const fileDisplay = document.getElementById('fileTextDisplay');
            if (file) {
                if (fileDisplay) fileDisplay.value = file.name;
                const reader = new FileReader();
                reader.onload = function (event) {
                    if (thumbImg) {
                        thumbImg.src = event.target.result;
                        thumbImg.style.display = 'block';
                    }
                };
                reader.readAsDataURL(file);

                if (typeof showToast === 'function') {
                    const successMsg = window.entraLanguage === 'de'
                        ? 'Beitragsbild erfolgreich ausgewählt.'
                        : 'Cover image selected successfully.';
                    showToast(successMsg, 'success');
                }
            }
        });

        function removeThumbnail() {
            const imageInput = document.getElementById('imageInput');
            const fileDisplay = document.getElementById('fileTextDisplay');
            const thumbImg = document.getElementById('thumbnailImg');
            if (imageInput) imageInput.value = '';
            if (fileDisplay) fileDisplay.value = '';
            if (thumbImg) {
                thumbImg.src = '';
                thumbImg.style.display = 'none';
            }
        }

        document.addEventListener('click', function(e) {
            const link = e.target.closest('.rich-text-content a');
            if (link) {
                const url = link.getAttribute('href');
                if (url && (e.ctrlKey || e.metaKey)) {
                    window.open(url, '_blank');
                    e.preventDefault();
                }
            }
        });

        let savedSelectionRange = null;
        function saveSelection() {
            const sel = window.getSelection();
            if (sel.rangeCount > 0) {
                return sel.getRangeAt(0);
            }
            return null;
        }
        function restoreSelection(range) {
            if (range) {
                const sel = window.getSelection();
                sel.removeAllRanges();
                sel.addRange(range);
            }
        }

        function showLinkModal(callback) {
            const modal = document.getElementById('linkModal');
            const input = document.getElementById('modalLinkInput');
            const addBtn = document.getElementById('addLinkModalBtn');
            const closeBtn = document.getElementById('closeLinkModalBtn');
            
            if (!modal || !input || !addBtn || !closeBtn) return;

            savedSelectionRange = saveSelection();
            
            modal.style.display = 'flex';
            input.value = '';
            setTimeout(() => input.focus(), 10);

            const handleAdd = () => {
                const url = input.value.trim();
                modal.style.display = 'none';
                cleanup();
                if (url) {
                    restoreSelection(savedSelectionRange);
                    callback(url);
                }
            };

            const handleCancel = () => {
                modal.style.display = 'none';
                cleanup();
            };

            const handleKey = (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    handleAdd();
                } else if (e.key === 'Escape') {
                    handleCancel();
                }
            };

            function cleanup() {
                addBtn.removeEventListener('click', handleAdd);
                closeBtn.removeEventListener('click', handleCancel);
                input.removeEventListener('keydown', handleKey);
            }

            addBtn.addEventListener('click', handleAdd);
            closeBtn.addEventListener('click', handleCancel);
            input.addEventListener('keydown', handleKey);
            addBtn.addEventListener('click', handleAdd);
            closeBtn.addEventListener('click', handleCancel);
            input.addEventListener('keydown', handleKey);
        }
        
        document.addEventListener('focus', function(e) {
            if (e.target.classList.contains('rich-text-content')) {
                const block = e.target.closest('.editor-block');
                if (block) {
                    block._lastActiveEditor = e.target;
                }
            }
        }, true);
        
        function formatText(command, btn) {
            if (btn) {
                const block = btn.closest('.editor-block');
                if (block) {
                    let editor = block._lastActiveEditor;
                    if (!editor || !block.contains(editor)) {
                        editor = block.querySelector('.rich-text-content');
                    }
                    
                    if (editor && document.activeElement !== editor) {
                        editor.focus();
                    }
                    
                    if (command.startsWith('justify')) {
                        const type = block.getAttribute('data-type');
                        if (type === 'heading' || type.startsWith('heading')) {
                            const alignment = command === 'justifyLeft' ? 'left' : 
                                            command === 'justifyCenter' ? 'center' : 
                                            command === 'justifyRight' ? 'right' : '';
                            const wrapper = block.querySelector('.heading-input-wrapper') || block.querySelector('h1, h2, h3, h4, h5, h6');
                            if (wrapper) {
                                wrapper.style.textAlign = alignment;
                                const heading = wrapper.tagName.match(/^H[1-6]$/) ? wrapper : wrapper.querySelector('h1, h2, h3, h4, h5, h6');
                                if (heading) heading.style.textAlign = alignment;
                                const input = block.querySelector('input');
                                if (input) input.style.textAlign = alignment;
                            }
                        }

                        const alignBtns = block.querySelectorAll('.formatting-toolbar .format-btn');
                        alignBtns.forEach(b => {
                            const t = b.getAttribute('title') || '';
                            if (t.includes('Align') || t.includes('Justify')) {
                                b.classList.remove('active');
                            }
                        });
                    }
                }
            }
            
            if (command === 'createLink') {
                showLinkModal((url) => {
                    document.execCommand(command, false, url);
                    updateContent();
                });
            } else {
                document.execCommand(command, false, null);
                updateContent();
            }
            
            if (btn) {
                const isActive = document.queryCommandState(command);
                btn.classList.toggle('active', isActive);
            }
        }
        
        document.addEventListener('selectionchange', function() {
            const selection = document.getSelection();
            if (!selection.rangeCount) return;
            
            const anchorNode = selection.anchorNode;
            const activeBlock = anchorNode ? (anchorNode.nodeType === 1 ? anchorNode.closest('.editor-block') : anchorNode.parentElement.closest('.editor-block')) : null;

            document.querySelectorAll('.formatting-toolbar .format-btn').forEach(btn => btn.classList.remove('active'));

            if (activeBlock) {
                let currentElement = anchorNode;
                if (currentElement && currentElement.nodeType === 3) {
                    currentElement = currentElement.parentElement;
                }
                let isInsideLink = false;
                while (currentElement && currentElement !== activeBlock) {
                    if (currentElement.tagName === 'A') {
                        isInsideLink = true;
                        break;
                    }
                    currentElement = currentElement.parentElement;
                }
                
                 const buttons = activeBlock.querySelectorAll('.formatting-toolbar .format-btn');
                 buttons.forEach(btn => {
                    const title = btn.getAttribute('title') || '';
                    let command = '';
                    if (title.includes('Bold')) command = 'bold';
                    else if (title.includes('Italic')) command = 'italic';
                    else if (title.includes('Underline')) command = 'underline';
                    else if (title.includes('Strikethrough')) command = 'strikeThrough';
                    else if (title.includes('Link')) command = 'createLink';
                    else if (title.includes('Left')) command = 'justifyLeft';
                    else if (title.includes('Center')) command = 'justifyCenter';
                    else if (title.includes('Right')) command = 'justifyRight';
                    else if (title.includes('Justify')) command = 'justifyFull';
                    
                    if (command) {
                        try {
                            if (command === 'underline' && isInsideLink) {
                                return;
                            }
                            if (command === 'createLink') {
                                if (isInsideLink) {
                                    btn.classList.add('active');
                                }
                            } else if (document.queryCommandState(command)) {
                                btn.classList.add('active');
                            }
                        } catch(e) {}
                    }
                 });
                 
                 const fontSizeSelect = activeBlock.querySelector('.font-size-select');
                 if (fontSizeSelect && selection.rangeCount > 0) {
                     const range = selection.getRangeAt(0);
                     
                     let fontSizes = new Set();
                     
                     if (range.collapsed) {
                         let element = anchorNode.nodeType === 3 ? anchorNode.parentElement : anchorNode;
                         if (element) {
                             const fontSize = window.getComputedStyle(element).fontSize;
                             fontSizes.add(parseInt(fontSize));
                         }
                     } else {
                         const container = range.commonAncestorContainer;
                         const walker = document.createTreeWalker(
                             container.nodeType === 3 ? container.parentElement : container,
                             NodeFilter.SHOW_TEXT,
                             null
                         );
                         
                         let node;
                         while (node = walker.nextNode()) {
                             if (range.intersectsNode(node)) {
                                const element = node.parentElement;
                                if (element) {
                                    const fontSize = window.getComputedStyle(element).fontSize;
                                    fontSizes.add(parseInt(fontSize));
                                }
                            }
                        }
                    }
                     
                     if (fontSizes.size > 1) {
                         fontSizeSelect.value = '';
                     } else if (fontSizes.size === 1) {
                         const pxSize = Array.from(fontSizes)[0];
                         
                         let sizeValue = '3';
                         if (pxSize <= 12) sizeValue = '1';
                         else if (pxSize <= 14) sizeValue = '2';
                         else if (pxSize <= 16) sizeValue = '3';
                         else if (pxSize <= 18) sizeValue = '4';
                         else if (pxSize <= 24) sizeValue = '5';
                         else if (pxSize <= 32) sizeValue = '6';
                         else if (pxSize > 32) sizeValue = '7';
                         
                         fontSizeSelect.value = sizeValue;
                     }
                 }
            }
        });

        function applyFontSize(select) {
            const size = select.value;
            if (size) {

                const block = select.closest('.editor-block');
                if (block) {
                    let editor = block._lastActiveEditor;
                    if (!editor || !block.contains(editor)) {
                        editor = block.querySelector('.rich-text-content');
                    }

                    if (editor) {
                        editor.focus();
                    }
                }
                
                document.execCommand('fontSize', false, size);
                updateContent();
            }
        }

        function applyTextColor(color, input) {
            document.execCommand('foreColor', false, color);

            const wrapper = input.closest('.color-picker-wrapper');
            if (wrapper) {
                const textInput = wrapper.querySelector('.color-input');
                if (textInput) textInput.value = color;
            }
            updateContent();
        }

        function applyColorFromInput(input) {
            let color = input.value.trim();
            if (!color) return;
            

            if (color.toLowerCase().startsWith('rgb')) {
                document.execCommand('foreColor', false, color);
            } else {
                if (!color.startsWith('#')) {
                    color = '#' + color;
                }
                if (/^#([0-9A-Fa-f]{3}){1,2}$/.test(color)) {
                    document.execCommand('foreColor', false, color);
                    const wrapper = input.closest('.color-picker-wrapper');
                    if (wrapper) {
                        const colorPicker = wrapper.querySelector('input[type="color"]');
                        if (colorPicker) colorPicker.value = color;
                    }
                }
            }
            updateContent();
        }

        const contentEditor = document.getElementById('contentEditor');
        const dropZone = document.getElementById('dropZone');
        const blockPalette = document.querySelectorAll('.block-item');


        let draggedType = null;

        const blockTemplates = {
            paragraph: () => `
        <div class="editor-block" draggable="true" data-type="paragraph">
            <div class="block-controls">
                <button type="button" class="block-btn drag-handle" title="Drag to reorder" draggable="false" style="cursor:grab;">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6">
                        <path fill-rule="evenodd" d="M3 6a3 3 0 0 1 3-3h2.25a3 3 0 0 1 3 3v2.25a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V6Zm9.75 0a3 3 0 0 1 3-3H18a3 3 0 0 1 3 3v2.25a3 3 0 0 1-3 3h-2.25a3 3 0 0 1-3-3V6ZM3 15.75a3 3 0 0 1 3-3h2.25a3 3 0 0 1 3 3V18a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3v-2.25Zm9.75 0a3 3 0 0 1 3-3H18a3 3 0 0 1 3 3V18a3 3 0 0 1-3 3h-2.25a3 3 0 0 1-3-3v-2.25Z" clip-rule="evenodd" />
                    </svg>
                </button>
                <button type="button" class="block-btn delete" onclick="deleteBlock(this)">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="formatting-toolbar">
                <button type="button" class="format-btn" onmousedown="event.preventDefault();" onclick="formatText('bold', this)" title="Bold (Ctrl+B)"><b>B</b></button>
                <button type="button" class="format-btn" onmousedown="event.preventDefault();" onclick="formatText('italic', this)" title="Italic (Ctrl+I)"><i>I</i></button>
                <button type="button" class="format-btn" onmousedown="event.preventDefault();" onclick="formatText('underline', this)" title="Underline (Ctrl+U)"><u>U</u></button>
                <button type="button" class="format-btn" onmousedown="event.preventDefault();" onclick="formatText('strikeThrough', this)" title="Strikethrough"><s>S</s></button>
                <button type="button" class="format-btn" onmousedown="event.preventDefault();" onclick="formatText('createLink', this)" title="Link">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:14px;height:14px;">
                        <path d="M12.232 4.232a2.5 2.5 0 013.536 3.536l-1.225 1.224a.75.75 0 001.061 1.06l1.224-1.224a4 4 0 00-5.656-5.656l-3 3a4 4 0 00.225 5.865.75.75 0 00.977-1.138 2.5 2.5 0 01-.142-3.667l3-3z" />
                        <path d="M11.603 7.963a.75.75 0 00-.977 1.138 2.5 2.5 0 01.142 3.667l-3 3a2.5 2.5 0 01-3.536-3.536l1.225-1.224a.75.75 0 00-1.061-1.06l-1.224 1.224a4 4 0 105.656 5.656l3-3a4 4 0 00-.225-5.865z" />
                    </svg>
                </button>
                <span class="format-divider"></span>
                <button type="button" class="format-btn" onmousedown="event.preventDefault();" onclick="formatText('justifyLeft', this)" title="Align Left">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M3 6.75A.75.75 0 0 1 3.75 6h16.5a.75.75 0 0 1 0 1.5H3.75A.75.75 0 0 1 3 6.75ZM3 12a.75.75 0 0 1 .75-.75h16.5a.75.75 0 0 1 0 1.5H3.75A.75.75 0 0 1 3 12Zm0 5.25a.75.75 0 0 1 .75-.75H12a.75.75 0 0 1 0 1.5H3.75a.75.75 0 0 1-.75-.75Z" /></svg>
                </button>
                <button type="button" class="format-btn" onmousedown="event.preventDefault();" onclick="formatText('justifyCenter', this)" title="Align Center">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M3 6.75A.75.75 0 0 1 3.75 6h16.5a.75.75 0 0 1 0 1.5H3.75A.75.75 0 0 1 3 6.75ZM6 12a.75.75 0 0 1 .75-.75h10.5a.75.75 0 0 1 0 1.5H6.75A.75.75 0 0 1 6 12Zm3 5.25a.75.75 0 0 1 .75-.75h6.5a.75.75 0 0 1 0 1.5H9.75a.75.75 0 0 1-.75-.75Z" /></svg>
                </button>
                <button type="button" class="format-btn" onmousedown="event.preventDefault();" onclick="formatText('justifyRight', this)" title="Align Right">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M3 6.75A.75.75 0 0 1 3.75 6h16.5a.75.75 0 0 1 0 1.5H3.75A.75.75 0 0 1 3 6.75ZM3 12a.75.75 0 0 1 .75-.75h16.5a.75.75 0 0 1 0 1.5H3.75A.75.75 0 0 1 3 12Zm12 5.25a.75.75 0 0 1 .75-.75h6.5a.75.75 0 0 1 0 1.5H15.75a.75.75 0 0 1-.75-.75Z" /></svg>
                </button>
                <button type="button" class="format-btn" onmousedown="event.preventDefault();" onclick="formatText('justifyFull', this)" title="Justify">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:14px;height:14px;"><path fill-rule="evenodd" d="M2.75 5.75a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H3.5a.75.75 0 01-.75-.75zm0 8.5a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H3.5a.75.75 0 01-.75-.75zM2.75 10a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H3.5A.75.75 0 012.75 10z" clip-rule="evenodd" /></svg>
                </button>
                <span class="format-divider"></span>
                <select class="font-size-select" onchange="applyFontSize(this)">
                    <option value="1">12px</option>
                    <option value="2">14px</option>
                    <option value="3" selected>16px</option>
                    <option value="4">18px</option>
                    <option value="5">24px</option>
                    <option value="6">32px</option>
                    <option value="7">48px</option>
                </select>
            </div>
            <div class="rich-text-content" contenteditable="true" data-placeholder="<?= $lang['placeholder_paragraph'] ?>" oninput="updateContent()" onkeydown="handleParagraphKeydown(event, this)"></div>
        </div>
    `,
            heading: () => `
        <div class="editor-block" draggable="true" data-type="heading">
            <div class="block-controls">
                                <button type="button" class="block-btn drag-handle" title="Drag to reorder" draggable="false" style="cursor:grab;">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6">
    <path fill-rule="evenodd" d="M3 6a3 3 0 0 1 3-3h2.25a3 3 0 0 1 3 3v2.25a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V6Zm9.75 0a3 3 0 0 1 3-3H18a3 3 0 0 1 3 3v2.25a3 3 0 0 1-3 3h-2.25a3 3 0 0 1-3-3V6ZM3 15.75a3 3 0 0 1 3-3h2.25a3 3 0 0 1 3 3V18a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3v-2.25Zm9.75 0a3 3 0 0 1 3-3H18a3 3 0 0 1 3 3V18a3 3 0 0 1-3 3h-2.25a3 3 0 0 1-3-3v-2.25Z" clip-rule="evenodd" />
</svg>
                                </button>
                <button type="button" class="block-btn delete" onclick="deleteBlock(this)">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="formatting-toolbar">
                <select class="heading-level-select" onchange="changeHeadingLevel(this)">
                    <option value="1">H1</option>
                    <option value="2">H2</option>
                    <option value="3">H3</option>
                    <option value="4">H4</option>
                    <option value="5">H5</option>
                    <option value="6">H6</option>
                </select>
                <span class="format-divider" style="margin: 0 8px; border-left: 1px solid rgba(255,255,255,0.1); height: 20px;"></span>
                <button type="button" class="format-btn" onmousedown="event.preventDefault();" onclick="formatText('justifyLeft', this)" title="Align Left">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M3 6.75A.75.75 0 0 1 3.75 6h16.5a.75.75 0 0 1 0 1.5H3.75A.75.75 0 0 1 3 6.75ZM3 12a.75.75 0 0 1 .75-.75h16.5a.75.75 0 0 1 0 1.5H3.75A.75.75 0 0 1 3 12Zm0 5.25a.75.75 0 0 1 .75-.75H12a.75.75 0 0 1 0 1.5H3.75a.75.75 0 0 1-.75-.75Z" /></svg>
                </button>
                <button type="button" class="format-btn" onmousedown="event.preventDefault();" onclick="formatText('justifyCenter', this)" title="Align Center">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M3 6.75A.75.75 0 0 1 3.75 6h16.5a.75.75 0 0 1 0 1.5H3.75A.75.75 0 0 1 3 6.75ZM6 12a.75.75 0 0 1 .75-.75h10.5a.75.75 0 0 1 0 1.5H6.75A.75.75 0 0 1 6 12Zm3 5.25a.75.75 0 0 1 .75-.75h6.5a.75.75 0 0 1 0 1.5H9.75a.75.75 0 0 1-.75-.75Z" /></svg>
                </button>
                <button type="button" class="format-btn" onmousedown="event.preventDefault();" onclick="formatText('justifyRight', this)" title="Align Right">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M3 6.75A.75.75 0 0 1 3.75 6h16.5a.75.75 0 0 1 0 1.5H3.75A.75.75 0 0 1 3 6.75ZM3 12a.75.75 0 0 1 .75-.75h16.5a.75.75 0 0 1 0 1.5H3.75A.75.75 0 0 1 3 12Zm12 5.25a.75.75 0 0 1 .75-.75h6.5a.75.75 0 0 1 0 1.5H15.75a.75.75 0 0 1-.75-.75Z" /></svg>
                </button>
            </div>
            <div class="heading-input-wrapper">
                <h1><input type="text" placeholder="<?= $lang['placeholder_heading'] ?> 1" oninput="updateContent()"></h1>
            </div>
        </div>
    `,
            heading2: () => `
        <div class="editor-block" draggable="true" data-type="heading2">
            <div class="block-controls">
                <button type="button" class="block-btn drag-handle" title="Drag to reorder" draggable="false" style="cursor:grab;">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6" style="width:18px;height:18px;">
                      <path fill-rule="evenodd" d="M3 6a3 3 0 0 1 3-3h2.25a3 3 0 0 1 3 3v2.25a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V6Zm9.75 0a3 3 0 0 1 3-3H18a3 3 0 0 1 3 3v2.25a3 3 0 0 1-3 3h-2.25a3 3 0 0 1-3-3V6ZM3 15.75a3 3 0 0 1 3-3h2.25a3 3 0 0 1 3 3V18a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3v-2.25Zm9.75 0a3 3 0 0 1 3-3H18a3 3 0 0 1 3 3V18a3 3 0 0 1-3 3h-2.25a3 3 0 0 1-3-3v-2.25Z" clip-rule="evenodd" />
                    </svg>
                </button>
                <button type="button" class="block-btn delete" onclick="deleteBlock(this)">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <h2><input type="text" placeholder="<?= $lang['placeholder_heading'] ?> 2" oninput="updateContent()"></h2>
        </div>
    `,
            heading3: () => `
        <div class="editor-block" draggable="true" data-type="heading3">
            <div class="block-controls">
                <button type="button" class="block-btn drag-handle" title="Drag to reorder" draggable="false" style="cursor:grab;">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6">
                      <path fill-rule="evenodd" d="M3 6a3 3 0 0 1 3-3h2.25a3 3 0 0 1 3 3v2.25a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V6Zm9.75 0a3 3 0 0 1 3-3H18a3 3 0 0 1 3 3v2.25a3 3 0 0 1-3 3h-2.25a3 3 0 0 1-3-3V6ZM3 15.75a3 3 0 0 1 3-3h2.25a3 3 0 0 1 3 3V18a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3v-2.25Zm9.75 0a3 3 0 0 1 3-3H18a3 3 0 0 1 3 3V18a3 3 0 0 1-3 3h-2.25a3 3 0 0 1-3-3v-2.25Z" clip-rule="evenodd" />
                    </svg>
                </button>
                <button type="button" class="block-btn delete" onclick="deleteBlock(this)">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <h3><input type="text" placeholder="<?= $lang['placeholder_heading'] ?> 3" oninput="updateContent()"></h3>
        </div>
    `,
            quote: () => `
        <div class="editor-block" draggable="true" data-type="quote">
            <div class="block-controls">
                    <button type="button" class="block-btn drag-handle" title="Drag to reorder" draggable="false" style="cursor:grab;">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6" style="width:18px;height:18px;">
                            <path fill-rule="evenodd" d="M3 6a3 3 0 0 1 3-3h2.25a3 3 0 0 1 3 3v2.25a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V6Zm9.75 0a3 3 0 0 1 3-3H18a3 3 0 0 1 3 3v2.25a3 3 0 0 1-3 3h-2.25a3 3 0 0 1-3-3V6ZM3 15.75a3 3 0 0 1 3-3h2.25a3 3 0 0 1 3 3V18a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3v-2.25Zm9.75 0a3 3 0 0 1 3-3H18a3 3 0 0 1 3 3V18a3 3 0 0 1-3 3h-2.25a3 3 0 0 1-3-3v-2.25Z" clip-rule="evenodd" />
                        </svg>
                    </button>
                    <button type="button" class="block-btn delete" onclick="deleteBlock(this)">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
            </div>
            <div class="formatting-toolbar">
                <button type="button" class="format-btn" onmousedown="event.preventDefault();" onclick="formatText('bold', this)" title="Bold (Ctrl+B)"><b>B</b></button>
                <button type="button" class="format-btn" onmousedown="event.preventDefault();" onclick="formatText('italic', this)" title="Italic (Ctrl+I)"><i>I</i></button>
                <button type="button" class="format-btn" onmousedown="event.preventDefault();" onclick="formatText('strikeThrough', this)" title="Strikethrough"><s>S</s></button>
                <button type="button" class="format-btn" onmousedown="event.preventDefault();" onclick="formatText('createLink', this)" title="Link">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:14px;height:14px;">
                        <path d="M12.232 4.232a2.5 2.5 0 013.536 3.536l-1.225 1.224a.75.75 0 001.061 1.06l1.224-1.224a4 4 0 00-5.656-5.656l-3 3a4 4 0 00.225 5.865.75.75 0 00.977-1.138 2.5 2.5 0 01-.142-3.667l3-3z" />
                        <path d="M11.603 7.963a.75.75 0 00-.977 1.138 2.5 2.5 0 01.142 3.667l-3 3a2.5 2.5 0 01-3.536-3.536l1.225-1.224a.75.75 0 00-1.061-1.06l-1.224 1.224a4 4 0 105.656 5.656l3-3a4 4 0 00-.225-5.865z" />
                    </svg>
                </button>
                <span class="format-divider"></span>
                <button type="button" class="format-btn" onmousedown="event.preventDefault();" onclick="formatText('justifyLeft', this)" title="Align Left">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:14px;height:14px;"><path fill-rule="evenodd" d="M2.75 5.75a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H3.5a.75.75 0 01-.75-.75zm0 8.5a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H3.5a.75.75 0 01-.75-.75zM2.75 10a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H3.5A.75.75 0 012.75 10z" clip-rule="evenodd" /></svg>
                </button>
                <button type="button" class="format-btn" onmousedown="event.preventDefault();" onclick="formatText('justifyCenter', this)" title="Align Center">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:14px;height:14px;"><path fill-rule="evenodd" d="M3.75 5.75a.75.75 0 01.75-.75h11a.75.75 0 010 1.5h-11a.75.75 0 01-.75-.75zm0 8.5a.75.75 0 01.75-.75h11a.75.75 0 010 1.5h-11a.75.75 0 01-.75-.75zM2.75 10a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H3.5A.75.75 0 012.75 10z" clip-rule="evenodd" /></svg>
                </button>
                <button type="button" class="format-btn" onmousedown="event.preventDefault();" onclick="formatText('justifyRight', this)" title="Align Right">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:14px;height:14px;"><path fill-rule="evenodd" d="M6.5 5.75a.75.75 0 01.75-.75h11a.75.75 0 010 1.5h-11a.75.75 0 01-.75-.75zm0 8.5a.75.75 0 01.75-.75h11a.75.75 0 010 1.5h-11a.75.75 0 01-.75-.75zM2.75 10a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H3.5A.75.75 0 012.75 10z" clip-rule="evenodd" /></svg>
                </button>
                <button type="button" class="format-btn" onmousedown="event.preventDefault();" onclick="formatText('justifyFull', this)" title="Justify">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:14px;height:14px;"><path fill-rule="evenodd" d="M2.75 5.75a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H3.5a.75.75 0 01-.75-.75zm0 8.5a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H3.5a.75.75 0 01-.75-.75zM2.75 10a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H3.5A.75.75 0 012.75 10z" clip-rule="evenodd" /></svg>
                </button>
            </div>
            <blockquote>
                <div class="rich-text-content" contenteditable="true" data-placeholder="<?= $lang['placeholder_quote'] ?>" oninput="updateContent()" style="min-height: 1em; outline: none; color: #fff;"></div>
            </blockquote>
        </div>
    `,
            list: () => `
        <div class="editor-block" draggable="true" data-type="list">
            <div class="block-controls">
                <button type="button" class="block-btn drag-handle" title="Drag to reorder" draggable="false" style="cursor:grab;">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6" style="width:18px;height:18px;">
                      <path fill-rule="evenodd" d="M3 6a3 3 0 0 1 3-3h2.25a3 3 0 0 1 3 3v2.25a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V6Zm9.75 0a3 3 0 0 1 3-3H18a3 3 0 0 1 3 3v2.25a3 3 0 0 1-3 3h-2.25a3 3 0 0 1-3-3V6ZM3 15.75a3 3 0 0 1 3-3h2.25a3 3 0 0 1 3 3V18a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3v-2.25Zm9.75 0a3 3 0 0 1 3-3H18a3 3 0 0 1 3 3V18a3 3 0 0 1-3 3h-2.25a3 3 0 0 1-3-3v-2.25Z" clip-rule="evenodd" />
                    </svg>
                </button>
                <button type="button" class="block-btn delete" onclick="deleteBlock(this)">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="formatting-toolbar">
                <button type="button" class="format-btn" onmousedown="event.preventDefault();" onclick="formatText('bold', this)" title="Bold (Ctrl+B)"><b>B</b></button>
                <button type="button" class="format-btn" onmousedown="event.preventDefault();" onclick="formatText('italic', this)" title="Italic (Ctrl+I)"><i>I</i></button>
                <button type="button" class="format-btn" onmousedown="event.preventDefault();" onclick="formatText('underline', this)" title="Underline (Ctrl+U)"><u>U</u></button>
                <button type="button" class="format-btn" onmousedown="event.preventDefault();" onclick="formatText('strikethrough', this)" title="Strikethrough"><s>S</s></button>
                <span class="format-divider"></span>
                <select class="list-style-select" onchange="changeListStyle(this)" style="padding:6px 8px; border-radius:6px; background:#1a1a1a; border:1px solid #333; color:#fff; cursor:pointer;">
                    <option value="disc">● Disc</option>
                    <option value="circle">○ Circle</option>
                    <option value="square">■ Square</option>
                    <option value="'◆'">◆ Diamond</option>
                    <option value="'✓'">✓ Checkmark</option>
                    <option value="'➤'">➤ Arrow</option>
                    <option value="'★'">★ Star</option>
                    <option value="none">— None</option>
                </select>
                <div class="color-palette">
                    <div class="color-swatch" style="background-color: #20c1f5" onclick="applyTextColor('#20c1f5', this)" title="#20c1f5"></div>
                    <div class="color-swatch" style="background-color: #49b9f2" onclick="applyTextColor('#49b9f2', this)" title="#49b9f2"></div>
                    <div class="color-swatch" style="background-color: #7675ec" onclick="applyTextColor('#7675ec', this)" title="#7675ec"></div>
                    <div class="color-swatch" style="background-color: #a04ee1" onclick="applyTextColor('#a04ee1', this)" title="#a04ee1"></div>
                    <div class="color-swatch" style="background-color: #d225d7" onclick="applyTextColor('#d225d7', this)" title="#d225d7"></div>
                    <div class="color-swatch" style="background-color: #f009d5" onclick="applyTextColor('#f009d5', this)" title="#f009d5"></div>
                    <div class="color-swatch" style="background-color: #ffffff" onclick="applyTextColor('#ffffff', this)" title="#ffffff"></div>
                </div>
            </div>
            <ul class="editor-list">
                <li class="editor-list-item">
                    <div class="li-content-wrapper">
                        <div class="rich-text-content" contenteditable="true" data-placeholder="<?= $lang['placeholder_list_item'] ?> 1" oninput="updateContent()"></div>
                        <button type="button" class="remove-list-item" title="Remove item">×</button>
                    </div>
                </li>
                <li class="editor-list-item">
                    <div class="li-content-wrapper">
                        <div class="rich-text-content" contenteditable="true" data-placeholder="<?= $lang['placeholder_list_item'] ?> 2" oninput="updateContent()"></div>
                        <button type="button" class="remove-list-item" title="Remove item">×</button>
                    </div>
                </li>
                <li class="editor-list-item">
                    <div class="li-content-wrapper">
                        <div class="rich-text-content" contenteditable="true" data-placeholder="<?= $lang['placeholder_list_item'] ?> 3" oninput="updateContent()"></div>
                        <button type="button" class="remove-list-item" title="Remove item">×</button>
                    </div>
                </li>
            </ul>
            <div class="list-footer"><button type="button" class="add-list-item">+ <?= $lang['action_add_item'] ?></button></div>
        </div>
    `,
            orderedlist: () => `
        <div class="editor-block" draggable="true" data-type="orderedlist">
            <div class="block-controls">
                <button type="button" class="block-btn drag-handle" title="Drag to reorder" draggable="false" style="cursor:grab;">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6" style="width:18px;height:18px;">
                      <path fill-rule="evenodd" d="M3 6a3 3 0 0 1 3-3h2.25a3 3 0 0 1 3 3v2.25a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V6Zm9.75 0a3 3 0 0 1 3-3H18a3 3 0 0 1 3 3v2.25a3 3 0 0 1-3 3h-2.25a3 3 0 0 1-3-3V6ZM3 15.75a3 3 0 0 1 3-3h2.25a3 3 0 0 1 3 3V18a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3v-2.25Zm9.75 0a3 3 0 0 1 3-3H18a3 3 0 0 1 3 3V18a3 3 0 0 1-3 3h-2.25a3 3 0 0 1-3-3v-2.25Z" clip-rule="evenodd" />
                    </svg>
                </button>
                <button type="button" class="block-btn delete" onclick="deleteBlock(this)">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="formatting-toolbar">
                <button type="button" class="format-btn" onmousedown="event.preventDefault();" onclick="formatText('bold', this)" title="Bold (Ctrl+B)"><b>B</b></button>
                <button type="button" class="format-btn" onmousedown="event.preventDefault();" onclick="formatText('italic', this)" title="Italic (Ctrl+I)"><i>I</i></button>
                <button type="button" class="format-btn" onmousedown="event.preventDefault();" onclick="formatText('underline', this)" title="Underline (Ctrl+U)"><u>U</u></button>
                <button type="button" class="format-btn" onmousedown="event.preventDefault();" onclick="formatText('strikethrough', this)" title="Strikethrough"><s>S</s></button>
                <span class="format-divider"></span>
                <select class="list-style-select" onchange="changeListStyle(this)" style="padding:6px 8px; border-radius:6px; background:#1a1a1a; border:1px solid #333; color:#fff; cursor:pointer;">
                    <option value="decimal">1. Decimal</option>
                    <option value="decimal-leading-zero">01. Zero-pad</option>
                    <option value="lower-alpha">a. Lower Alpha</option>
                    <option value="upper-alpha">A. Upper Alpha</option>
                    <option value="lower-roman">i. Lower Roman</option>
                    <option value="upper-roman">I. Upper Roman</option>
                </select>
                <div class="color-palette">
                    <div class="color-swatch" style="background-color: #20c1f5" onclick="applyTextColor('#20c1f5', this)" title="#20c1f5"></div>
                    <div class="color-swatch" style="background-color: #49b9f2" onclick="applyTextColor('#49b9f2', this)" title="#49b9f2"></div>
                    <div class="color-swatch" style="background-color: #7675ec" onclick="applyTextColor('#7675ec', this)" title="#7675ec"></div>
                    <div class="color-swatch" style="background-color: #a04ee1" onclick="applyTextColor('#a04ee1', this)" title="#a04ee1"></div>
                    <div class="color-swatch" style="background-color: #d225d7" onclick="applyTextColor('#d225d7', this)" title="#d225d7"></div>
                    <div class="color-swatch" style="background-color: #f009d5" onclick="applyTextColor('#f009d5', this)" title="#f009d5"></div>
                    <div class="color-swatch" style="background-color: #ffffff" onclick="applyTextColor('#ffffff', this)" title="#ffffff"></div>
                </div>
            </div>
            <ol class="editor-list">
                <li class="editor-list-item">
                    <div class="li-content-wrapper">
                        <div class="rich-text-content" contenteditable="true" data-placeholder="<?= $lang['placeholder_list_item'] ?> 1" oninput="updateContent()" onkeydown="handleListKeydown(event, this)"></div>
                        <button type="button" class="remove-list-item" title="Remove item">×</button>
                    </div>
                </li>
                <li class="editor-list-item">
                    <div class="li-content-wrapper">
                        <div class="rich-text-content" contenteditable="true" data-placeholder="<?= $lang['placeholder_list_item'] ?> 2" oninput="updateContent()" onkeydown="handleListKeydown(event, this)"></div>
                        <button type="button" class="remove-list-item" title="Remove item">×</button>
                    </div>
                </li>
                <li class="editor-list-item">
                    <div class="li-content-wrapper">
                        <div class="rich-text-content" contenteditable="true" data-placeholder="<?= $lang['placeholder_list_item'] ?> 3" oninput="updateContent()" onkeydown="handleListKeydown(event, this)"></div>
                        <button type="button" class="remove-list-item" title="Remove item">×</button>
                    </div>
                </li>
            </ol>
            <div class="list-footer"><button type="button" class="add-list-item">+ <?= $lang['action_add_item'] ?></button></div>
        </div>
    `,
            heading4: () => `
        <div class="editor-block" draggable="true" data-type="heading4">
            <div class="block-controls">
                <button type="button" class="block-btn delete" onclick="deleteBlock(this)">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <h4><input type="text" placeholder="<?= $lang['placeholder_heading'] ?> 4" oninput="updateContent()"></h4>
        </div>
    `,
            heading5: () => `
        <div class="editor-block" draggable="true" data-type="heading5">
            <div class="block-controls">
                <button type="button" class="block-btn delete" onclick="deleteBlock(this)">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <h5><input type="text" placeholder="<?= $lang['placeholder_heading'] ?> 5" oninput="updateContent()"></h5>
        </div>
    `,
            image: () => {
                const uniqueId = 'img_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
                return `
        <div class="editor-block" draggable="true" data-type="image" data-id="${uniqueId}">
            <div class="block-controls">
                <button type="button" class="block-btn drag-handle" title="Drag to reorder" draggable="false" style="cursor:grab;">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6">
                      <path fill-rule="evenodd" d="M3 6a3 3 0 0 1 3-3h2.25a3 3 0 0 1 3 3v2.25a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V6Zm9.75 0a3 3 0 0 1 3-3H18a3 3 0 0 1 3 3v2.25a3 3 0 0 1-3 3h-2.25a3 3 0 0 1-3-3V6ZM3 15.75a3 3 0 0 1 3-3h2.25a3 3 0 0 1 3 3V18a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3v-2.25Zm9.75 0a3 3 0 0 1 3-3H18a3 3 0 0 1 3 3V18a3 3 0 0 1-3 3h-2.25a3 3 0 0 1-3-3v-2.25Z" clip-rule="evenodd" />
                    </svg>
                </button>
                <button type="button" class="block-btn delete" onclick="deleteBlock(this)">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="image-block modern-image-block">
                <div class="file-upload-card">
                    <div class="file-upload-header">
                        <h3 style="margin:0 0 4px 0;font-size:1.1rem;font-weight:600;"><?= $lang['label_file_upload'] ?></h3>
                        <div style="color:#888;font-size:13px;margin-bottom:12px;"><?= $lang['hint_max_size'] ?></div>
                    </div>
                    <div class="img-content upload-content">
                        <div class="dropzone" id="dropzone_${uniqueId}" onclick="document.getElementById('fileInput_${uniqueId}').click()">
                            <input type="file" accept="image/*" onchange="handleImageUpload(this, '${uniqueId}')" style="display:none;" id="fileInput_${uniqueId}">
                            <div class="dropzone-inner">
                                <div class="dropzone-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#7675ec">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                                    </svg>
                                </div>
                                <div class="dropzone-text"><?= $lang['drag_drop_files'] ?></div>
                                <div class="dropzone-formats"><?= $lang['supported_formats'] ?></div>
                                <button type="button" class="select-file-btn"><?= $lang['action_select_file'] ?></button>
                            </div>
                        </div>
                        <div style="text-align:left; color:#888; margin-top: 10px; font-size:15px;"><?= $lang['or_upload_url'] ?></div>
                        <div class="url-upload-row">
                            <input type="url" placeholder="<?= $lang['placeholder_file_url'] ?>" class="url-upload-input">
                            <button type="button" class="url-upload-btn"><?= $lang['action_upload'] ?></button>
                        </div>
                        <div class="meta-controls" style="margin-top:12px; display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                            <div class="meta-field">
                                <label style="display:block; color:#aaa; font-size:12px; margin-bottom:4px; display:flex; align-items:center; gap:4px;">
                                    <?= $lang['label_alt_text'] ?>
                                    <span title="<?= $lang['tooltip_alt_text'] ?? 'Describe the image' ?>" style="cursor:help; display:inline-flex; align-items:center; justify-content:center; width:14px; height:14px; border-radius:50%; background:#444; color:#fff; font-size:10px;">?</span>
                                </label>
                                <input type="text" placeholder="<?= $lang['placeholder_alt_example'] ?>" class="img-alt-input" style="width:100%; padding:8px; border-radius:6px; background:#111; border:1px solid #333; color:#fff;" oninput="updateContent()">
                            </div>
                            <div class="meta-field">
                                <label style="display:block; color:#aaa; font-size:12px; margin-bottom:4px; display:flex; align-items:center; gap:4px;">
                                    <?= $lang['label_caption'] ?>
                                    <span title="<?= $lang['tooltip_caption'] ?? 'Text displayed centered' ?>" style="cursor:help; display:inline-flex; align-items:center; justify-content:center; width:14px; height:14px; border-radius:50%; background:#444; color:#fff; font-size:10px;">?</span>
                                </label>
                                <div class="img-caption-content" contenteditable="true" data-placeholder="<?= $lang['placeholder_caption'] ?>" oninput="updateContent()" style="width:100%; padding:8px; border-radius:6px; background:rgba(0,0,0,0.2); border:1px solid #333; color:#ccc; font-size:14px; min-height:36px; outline:none; text-align: left;"></div>
                            </div>
                        </div>
                        <div class="size-controls" style="display:flex; gap:8px; margin-top:12px; align-items:center;">
                            <label style="color:#aaa;font-size:13px;margin-right:6px;"><?= $lang['label_width'] ?></label>
                            <input type="text" placeholder="<?= $lang['placeholder_width_hint'] ?>" class="img-width-input" style="width:120px;padding:6px;border-radius:6px;background:#111;border:1px solid #333;color:#fff;" oninput="updateContent()">
                            <label style="color:#aaa;font-size:13px;margin-left:12px;margin-right:6px;"><?= $lang['label_height'] ?></label>
                            <input type="text" placeholder="<?= $lang['placeholder_height_hint'] ?>" class="img-height-input" style="width:120px;padding:6px;border-radius:6px;background:#111;border:1px solid #333;color:#fff;" oninput="updateContent()">
                        </div>
                        <div class="uploaded-section" id="uploadedSection_${uniqueId}" style="display:none;">
                            <div style="font-weight:600; color:#fff; margin: 22px 0 10px 0; font-size:16px;"><?= $lang['label_uploaded_files'] ?></div>
                            <div class="uploaded-files-list" id="uploadedFiles_${uniqueId}"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
       
            },
            divider: () => `
        <div class="editor-block" draggable="true" data-type="divider">
            <div class="block-controls">
                <button type="button" class="block-btn drag-handle" title="Drag to reorder" draggable="false" style="cursor:grab;">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6" style="width:18px;height:18px;">
                      <path fill-rule="evenodd" d="M3 6a3 3 0 0 1 3-3h2.25a3 3 0 0 1 3 3v2.25a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V6Zm9.75 0a3 3 0 0 1 3-3H18a3 3 0 0 1 3 3v2.25a3 3 0 0 1-3 3h-2.25a3 3 0 0 1-3-3V6ZM3 15.75a3 3 0 0 1 3-3h2.25a3 3 0 0 1 3 3V18a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3v-2.25Zm9.75 0a3 3 0 0 1 3-3H18a3 3 0 0 1 3 3V18a3 3 0 0 1-3 3h-2.25a3 3 0 0 1-3-3v-2.25Z" clip-rule="evenodd" />
                    </svg>
                </button>
                <button type="button" class="block-btn delete" onclick="deleteBlock(this)">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <hr style="border: none; border-top: 2px solid rgba(118, 117, 236, 0.3); margin: 16px 0;">
        </div>
    `
        };

        blockPalette.forEach(item => {
            item.addEventListener('dragstart', (e) => {
                draggedType = e.target.getAttribute('data-type');
            });
            
            item.addEventListener('click', (e) => {
                const blockType = e.currentTarget.getAttribute('data-type');
                
                const dz = document.getElementById('dropZone');
                if (dz) {
                    dz.remove();
                }
                
                if (blockType && blockTemplates[blockType]) {
                    if (blockType === 'heading') {
                        const newBlock = document.createElement('div');
                        newBlock.innerHTML = blockTemplates.heading();
                        contentEditor.appendChild(newBlock.firstElementChild);
                        makeBlockDraggable(contentEditor.lastElementChild);
                        const block = contentEditor.lastElementChild;
                        block.querySelector('.heading-level-select').addEventListener('change', function () {
                            const lvl = this.value;
                            const input = block.querySelector('input');
                            let headingTag = `h${lvl}`;
                            let headingElem = block.querySelector('h1, h2, h3, h4, h5, h6');
                            if (headingElem) {
                                const newHeading = document.createElement(headingTag);
                                newHeading.appendChild(input);
                                headingElem.replaceWith(newHeading);
                                const headingText = <?= json_encode($lang['placeholder_heading']) ?>;
                                input.placeholder = headingText + ` ${lvl}`;
                            }
                        });
                    } else {
                        const newBlock = document.createElement('div');
                        newBlock.innerHTML = blockTemplates[blockType]();
                        contentEditor.appendChild(newBlock.firstElementChild);
                        makeBlockDraggable(contentEditor.lastElementChild);
                    }
                    updateContent();
                }
            });
        });

        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('drag-over');
        });

        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('drag-over');
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('drag-over');

            if (draggedType && blockTemplates[draggedType]) {
                if (dropZone.parentElement) {
                    dropZone.remove();
                }
                if (draggedType === 'heading') {
                    const newBlock = document.createElement('div');
                    newBlock.innerHTML = blockTemplates.heading();
                    contentEditor.appendChild(newBlock.firstElementChild);
                    makeBlockDraggable(contentEditor.lastElementChild);
                    const block = contentEditor.lastElementChild;
                    block.querySelector('.heading-level-select').addEventListener('change', function () {
                        const lvl = this.value;
                        block.querySelector('.heading-label').textContent = 'Heading ' + lvl;
                        const input = block.querySelector('input');
                        const text = input.value;
                        let headingTag = `h${lvl}`;
                        let headingElem = block.querySelector('h1, h2, h3, h4, h5, h6');
                        if (headingElem) {
                            const newHeading = document.createElement(headingTag);
                            newHeading.appendChild(input);
                            headingElem.replaceWith(newHeading);
                            input.placeholder = `Heading ${lvl}`;
                        }
                    });
                } else {
                    const newBlock = document.createElement('div');
                    newBlock.innerHTML = blockTemplates[draggedType]();
                    contentEditor.appendChild(newBlock.firstElementChild);
                    makeBlockDraggable(contentEditor.lastElementChild);
                }
                updateContent();
                draggedType = null;
            }
        });

        contentEditor.addEventListener('dragover', (e) => {
            e.preventDefault();
        });

        contentEditor.addEventListener('drop', (e) => {
            e.preventDefault();

            if (draggedType && blockTemplates[draggedType]) {
                const dz = document.getElementById('dropZone');
                if (dz && dz.parentElement) {
                    dz.remove();
                }

                if (draggedType === 'heading') {
                    const newBlock = document.createElement('div');
                    newBlock.innerHTML = blockTemplates.heading();
                    contentEditor.appendChild(newBlock.firstElementChild);
                    makeBlockDraggable(contentEditor.lastElementChild);
                    const block = contentEditor.lastElementChild;
                    block.querySelector('.heading-level-select').addEventListener('change', function () {
                        const lvl = this.value;
                        const input = block.querySelector('input');
                        let headingTag = `h${lvl}`;
                        let headingElem = block.querySelector('h1, h2, h3, h4, h5, h6');
                        if (headingElem) {
                            const newHeading = document.createElement(headingTag);
                            newHeading.appendChild(input);
                            headingElem.replaceWith(newHeading);
                            input.placeholder = `Heading ${lvl}`;
                        }
                    });
                } else {
                    const newBlock = document.createElement('div');
                    newBlock.innerHTML = blockTemplates[draggedType]();
                    contentEditor.appendChild(newBlock.firstElementChild);
                    makeBlockDraggable(contentEditor.lastElementChild);
                }
                updateContent();
                draggedType = null;
            }
        });

        function applyTextColor(color, swatch) {
            console.log('[DEBUG] applyTextColor called with color:', color, 'swatch:', swatch);
            const block = swatch.closest('.editor-block');
            console.log('[DEBUG] Found block:', block);
            if (!block) {
                console.error('[DEBUG] No editor-block found for swatch');
                return;
            }
            block.dataset.textColor = color;
            const list = block.querySelector('.editor-list');
            console.log('[DEBUG] Found list:', list);
            if (list) {
                list.style.color = color;
            }
            const richTexts = block.querySelectorAll('.rich-text-content');
            console.log('[DEBUG] Found rich-text-content elements:', richTexts.length);
            richTexts.forEach(el => el.style.color = color);
            const swatches = block.querySelectorAll('.color-swatch');
            swatches.forEach(s => s.classList.remove('active'));
            swatch.classList.add('active');
            updateContent();
        }

        function handleParagraphKeydown(e, el) {
            if (e.key === 'Enter' && !e.shiftKey) {
                 e.preventDefault();
                 document.execCommand('insertLineBreak');
                 updateContent();
            }
        }

        function handleListKeydown(e, el) {
            const li = el.closest('.editor-list-item');
            const list = li.parentElement;
            const block = list.closest('.editor-block');

            if (e.shiftKey && e.key === 'Enter') {
                return;
            }

            if (e.key === 'Enter') {
                e.preventDefault();
                e.stopPropagation();

                const text = el.textContent;
                const isEmpty = text.trim() === '';

                if (isEmpty) {
                    return false;
                } else {
                    const savedColor = block.dataset.textColor || '';
                    const styleAttr = savedColor ? `style="color: ${savedColor}"` : '';

                    const newLi = document.createElement('li');
                    newLi.className = 'editor-list-item';
                    newLi.innerHTML = `
                        <div class="li-content-wrapper">
                            <div class="rich-text-content" contenteditable="true" ${styleAttr} data-placeholder="<?= $lang['placeholder_list_item'] ?>" oninput="updateContent()" onkeydown="handleListKeydown(event, this)"></div>
                            <button type="button" class="remove-list-item" title="Remove item">×</button>
                        </div>
                    `;

                    newLi.querySelector('.remove-list-item').addEventListener('click', function() {
                        const pList = newLi.parentElement;
                        const pBlock = pList.closest('.editor-block');
                        newLi.remove();
                        if (pList.children.length === 0) {
                            pBlock.remove();
                        }
                        updateContent();
                    });

                    if (li.nextSibling) {
                        list.insertBefore(newLi, li.nextSibling);
                    } else {
                        list.appendChild(newLi);
                    }

                    newLi.querySelector('.rich-text-content').focus();
                    updateContent();
                }
            }

            if (e.key === 'Backspace') {
                const text = el.innerText.replace(/[\n\r]+$/, '');
                const selection = window.getSelection();
                const isAtStart = selection.anchorOffset === 0 && selection.isCollapsed;

                if (text === '') {
                    e.preventDefault();
                    const prev = li.previousElementSibling;
                    const next = li.nextElementSibling;
                    const parentBlock = list.closest('.editor-block');

                    li.remove();

                    if (list.children.length === 0) {
                        parentBlock.remove();
                    } else if (prev) {
                        const prevContent = prev.querySelector('.rich-text-content');
                        if (prevContent) {
                            const range = document.createRange();
                            const sel = window.getSelection();
                            range.selectNodeContents(prevContent);
                            range.collapse(false);
                            sel.removeAllRanges();
                            sel.addRange(range);
                            prevContent.focus();
                        }
                    } else if (next) {
                        next.querySelector('.rich-text-content').focus();
                    }
                    updateContent();
                } else if (isAtStart) {
                    e.preventDefault();
                    const parentBlock = list.closest('.editor-block');
                    const currentContent = el.innerHTML;

                    const newBlock = document.createElement('div');
                    
                    if (typeof blockTemplates !== 'undefined' && blockTemplates.paragraph) {
                         const tempDiv = document.createElement('div');
                         tempDiv.innerHTML = blockTemplates.paragraph();
                         const templateBlock = tempDiv.firstElementChild;
                         newBlock.className = templateBlock.className;
                         newBlock.dataset.type = templateBlock.dataset.type;
                         newBlock.draggable = templateBlock.draggable;
                         newBlock.innerHTML = templateBlock.innerHTML;
                    } else {
                         newBlock.className = 'editor-block';
                         newBlock.dataset.type = 'paragraph';
                         newBlock.innerHTML = `
                            <div class="block-controls">
                                <div class="drag-handle"><i class="fas fa-grip-vertical"></i></div>
                                <button class="delete-btn" onclick="deleteBlock(this)"><i class="fas fa-trash-alt"></i></button>
                            </div>
                            <div class="rich-text-content" contenteditable="true" oninput="updateContent()"></div>
                         `;
                    }
                    
                    const richText = newBlock.querySelector('.rich-text-content');
                    richText.innerHTML = currentContent;
                    
                    makeBlockDraggable(newBlock);

                    const nextItems = [];
                    let sibling = li.nextElementSibling;
                    while (sibling) {
                        nextItems.push(sibling);
                        sibling = sibling.nextElementSibling;
                    }

                    parentBlock.parentNode.insertBefore(newBlock, parentBlock.nextSibling);

                    if (nextItems.length > 0) {
                        const newListBlock = parentBlock.cloneNode(false);
                        newListBlock.innerHTML = parentBlock.innerHTML; 
                        const newListUl = newListBlock.querySelector('ul, ol');
                        newListUl.innerHTML = ''; 
                        
                        nextItems.forEach(item => newListUl.appendChild(item));
                        
                        newBlock.parentNode.insertBefore(newListBlock, newBlock.nextSibling);
                        makeBlockDraggable(newListBlock);
                    }

                    li.remove();

                    if (list.children.length === 0) {
                        parentBlock.remove();
                    }

                    richText.focus();
                    updateContent();
                }
            }
        }

        function changeHeadingLevel(select) {
            const block = select.closest('.editor-block');
            const level = select.value;
            const input = block.querySelector('input');
            let headingTag = `h${level}`;
            let headingElem = block.querySelector('h1, h2, h3, h4, h5, h6');
            if (headingElem) {
                const newHeading = document.createElement(headingTag);
                newHeading.appendChild(input);
                headingElem.replaceWith(newHeading);

                input.placeholder = `Heading ${level}`;
            }
            updateContent();
        }
        
        function changeListStyle(select) {
            const block = select.closest('.editor-block');
            const style = select.value;
            const list = block.querySelector('.editor-list');
            if (list) {
                list.style.listStyleType = style;
            }
            block.dataset.listStyle = style;
            updateContent();
        }

        function deleteBlock(btn) {
            const block = btn.closest('.editor-block');
            block.remove();

            if (contentEditor.querySelectorAll('.editor-block').length === 0) {
                contentEditor.innerHTML = '<div class="drop-zone" id="dropZone"><p>Drag blocks here to start building your content</p></div>';
                const newDropZone = document.getElementById('dropZone');

                newDropZone.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    newDropZone.classList.add('drag-over');
                });
                newDropZone.addEventListener('dragleave', () => {
                    newDropZone.classList.remove('drag-over');
                });
                newDropZone.addEventListener('drop', (e) => {
                    e.preventDefault();
                    newDropZone.classList.remove('drag-over');
                    if (draggedType && blockTemplates[draggedType]) {
                        newDropZone.remove();
                        const newBlock = document.createElement('div');
                        newBlock.innerHTML = blockTemplates[draggedType]();
                        contentEditor.appendChild(newBlock.firstElementChild);
                        updateContent();
                        draggedType = null;
                    }
                });
            }
            updateContent();
        }

        function addParagraphBlock(editor, text, alignment = '') {
            const temp = document.createElement('div');
            if (typeof blockTemplates !== 'undefined' && blockTemplates.paragraph) {
                temp.innerHTML = blockTemplates.paragraph();
                const block = temp.firstElementChild;
                const richText = block.querySelector('.rich-text-content');
                if (richText) {
                    richText.innerHTML = text;
                    if (alignment) richText.style.textAlign = alignment;
                }
                editor.appendChild(block);
                if (typeof makeBlockDraggable === 'function') makeBlockDraggable(block);
            }
        }

        function addHeadingBlock(editor, type, text, alignment = '') {
            const temp = document.createElement('div');
            const level = type.replace('heading', '');
            if (typeof blockTemplates !== 'undefined' && blockTemplates['heading' + level]) {
                temp.innerHTML = blockTemplates['heading' + level]();
            } else if (typeof blockTemplates !== 'undefined' && blockTemplates.heading) {
                temp.innerHTML = blockTemplates.heading();
            }
            if (temp.firstElementChild) {
                const block = temp.firstElementChild;
                const input = block.querySelector('input');
                if (input) input.value = text;
                const wrapper = block.querySelector('.heading-input-wrapper') || block.querySelector('h1, h2, h3, h4, h5, h6');
                if (wrapper && alignment) wrapper.style.textAlign = alignment;
                editor.appendChild(block);
                if (typeof makeBlockDraggable === 'function') makeBlockDraggable(block);
            }
        }

        function addQuoteBlock(editor, text, alignment = '') {
            const temp = document.createElement('div');
            if (typeof blockTemplates !== 'undefined' && blockTemplates.quote) {
                temp.innerHTML = blockTemplates.quote();
                const block = temp.firstElementChild;
                const richText = block.querySelector('.rich-text-content');
                if (richText) {
                    richText.innerHTML = text;
                    if (alignment) richText.style.textAlign = alignment;
                }
                editor.appendChild(block);
                if (typeof makeBlockDraggable === 'function') makeBlockDraggable(block);
            }
        }

        function addDividerBlock(editor) {
            const temp = document.createElement('div');
            if (typeof blockTemplates !== 'undefined' && blockTemplates.divider) {
                temp.innerHTML = blockTemplates.divider();
                const block = temp.firstElementChild;
                editor.appendChild(block);
                if (typeof makeBlockDraggable === 'function') makeBlockDraggable(block);
            }
        }

        function addListBlock(editor, type, items, alignment = '', listStyle = '', color = '') {
            const temp = document.createElement('div');
            const templateFunc = type === 'orderedlist' ? blockTemplates.orderedlist : blockTemplates.list;
            if (typeof blockTemplates !== 'undefined' && templateFunc) {
                temp.innerHTML = templateFunc();
                const block = temp.firstElementChild;
                const list = block.querySelector('.editor-list');
                if (list) {
                    list.innerHTML = '';
                    items.forEach((item, index) => {
                        const li = document.createElement('li');
                        li.className = 'editor-list-item';
                        const styleAttr = color ? `style="color: ${color}"` : '';
                        li.innerHTML = `
                            <div class="li-content-wrapper">
                                <div class="rich-text-content" contenteditable="true" ${styleAttr} data-placeholder="List item ${index + 1}" oninput="updateContent()" onkeydown="handleListKeydown(event, this)">${item.content}</div>
                                <button type="button" class="remove-list-item" title="Remove item">×</button>
                            </div>
                        `;
                        list.appendChild(li);
                    });
                    if (alignment) list.style.textAlign = alignment;
                    if (listStyle) list.style.listStyleType = listStyle;
                    if (color) {
                        list.style.color = color;
                        block.dataset.textColor = color;
                    }
                }
                editor.appendChild(block);
                if (typeof makeBlockDraggable === 'function') makeBlockDraggable(block);
            }
        }

        function processNodes(nodes) {
            const editor = document.getElementById('contentEditor');

            const dz = document.getElementById('dropZone');
            if (dz) dz.remove();

            nodes.forEach(node => {
                if (node.nodeType === Node.TEXT_NODE) {
                    const text = node.textContent.trim();
                    if (text) addParagraphBlock(editor, text);
                } else if (node.nodeType === Node.ELEMENT_NODE) {
                    const tag = node.tagName.toLowerCase();
                    if (tag === 'p' || tag === 'div' || tag === 'span') {
                        const content = node.innerHTML.trim();
                        if (content) addParagraphBlock(editor, content, node.style.textAlign);
                    } else if (/^h[1-6]$/.test(tag)) {
                        addHeadingBlock(editor, 'heading' + tag.slice(1), node.textContent, node.style.textAlign);
                    } else if (tag === 'blockquote') {
                        const p = node.querySelector('p');
                        const text = p ? p.innerHTML : node.innerHTML;
                        addQuoteBlock(editor, text, node.style.textAlign);
                    } else if (tag === 'hr') {
                        addDividerBlock(editor);
                    } else if (tag === 'ul' || tag === 'ol') {
                        const type = tag === 'ol' ? 'orderedlist' : 'list';
                        const items = Array.from(node.children).map(li => ({
                            content: li.innerHTML.replace(/<span>|<\/span>/g, '').trim()
                        }));
                        addListBlock(editor, type, items, node.style.textAlign, node.style.listStyleType, node.style.color);
                    } else {
                        const content = node.innerHTML.trim();
                        if (content) addParagraphBlock(editor, content);
                    }
                }
            });
            updateContent();
        }

        function updateContent() {
            const blocks = contentEditor.querySelectorAll('.editor-block');
            let html = '';

            blocks.forEach(block => {
                const type = block.getAttribute('data-type');
                if (type === 'paragraph') {
                    const richTextDiv = block.querySelector('.rich-text-content');
                    const content = richTextDiv ? richTextDiv.innerHTML : '';
                    let alignment = '';
                    if (richTextDiv && richTextDiv.style.textAlign) {
                        alignment = ` style="text-align: ${richTextDiv.style.textAlign}"`;
                    }
                    if (content.trim() && content.trim() !== '<br>') html += `<p${alignment}>${content}</p>\n\n`;
                } else if (type === 'heading') {
                    const select = block.querySelector('.heading-level-select');
                    const wrapper = block.querySelector('.heading-input-wrapper') || block.querySelector('h1, h2, h3, h4, h5, h6');
                    const input = block.querySelector('input');
                    const level = select ? select.value : '1';
                    const text = input ? input.value : '';
                    let alignment = '';
                    if (wrapper && wrapper.style.textAlign) {
                        alignment = ` style="text-align: ${wrapper.style.textAlign}"`;
                    }
                    if (text.trim()) html += `<h${level}${alignment}>${text}</h${level}>\n\n`;
                } else if (type === 'heading1' || type === 'heading2' || type === 'heading3' || type === 'heading4' || type === 'heading5') {
                    const input = block.querySelector('input');
                    const text = input ? input.value : '';
                    const level = type.replace('heading', '');
                    let alignment = '';
                    const headingElem = block.querySelector('h1, h2, h3, h4, h5, h6');
                    if (headingElem && headingElem.style.textAlign) {
                        alignment = ` style="text-align: ${headingElem.style.textAlign}"`;
                    }
                    if (text.trim()) html += `<h${level}${alignment}>${text}</h${level}>\n\n`;
                } else if (type === 'quote') {
                    const richTextDiv = block.querySelector('.rich-text-content');
                    const content = richTextDiv ? richTextDiv.innerHTML : '';
                    if (content.trim() && content.trim() !== '<br>') {
                        let alignment = '';
                        if (richTextDiv.style.textAlign) {
                            alignment = `text-align: ${richTextDiv.style.textAlign};`;
                        }
                        html += `<blockquote style="${alignment}"><p>${content}</p></blockquote>\n\n`;
                    }
                } else if (type === 'list' || type === 'orderedlist') {
                    const listTag = type === 'orderedlist' ? 'ol' : 'ul';
                    const listContainer = block.querySelector('.editor-list');
                    const items = Array.from(block.querySelectorAll('.editor-list-item'))
                        .map(li => {
                            const richText = li.querySelector('.rich-text-content');
                            return {
                                content: richText ? richText.innerHTML.trim() : '',
                                padding: li.style.paddingLeft || '0px'
                            };
                        })
                        .filter(item => item.content && item.content !== '<br>');
                    
                    if (items.length > 0) {
                        const style = block.dataset.listStyle || (type === 'orderedlist' ? 'decimal' : 'disc');
                        let alignment = '';
                        if (listContainer && listContainer.style.textAlign) {
                            alignment = `text-align: ${listContainer.style.textAlign};`;
                        }
                        
                        let colorStyle = '';
                        if (block.dataset.textColor) {
                            colorStyle = `color: ${block.dataset.textColor};`;
                        }
                        
                        html += `<${listTag} style="list-style-type: ${style}; ${alignment} ${colorStyle}">\n`;
                        items.forEach(item => {
                            let liStyle = '';
                            if (item.padding && item.padding !== '0px') {
                                liStyle = ` style="padding-left: ${item.padding}"`;
                            }
                            html += `    <li${liStyle}><span>${item.content}</span></li>\n`;
                        });
                        html += `</${listTag}>\n\n`;
                    }
                } else if (type === 'image') {
                    const urlInput = block.querySelector('input[type="url"]');
                    const attached = block.dataset.attachedFile || block.dataset.attachedUrl || '';
                    let imgSrc = '';
                    if (attached) {
                        imgSrc = attached;
                    } else if (urlInput) {
                        imgSrc = urlInput.value.trim();
                    }
                    if (imgSrc) {
                        const altInput = block.querySelector('.img-alt-input');
                        const alt = altInput ? altInput.value.trim() : 'Blog image';
                        const captionDiv = block.querySelector('.img-caption-content');
                        const caption = captionDiv ? captionDiv.innerHTML.trim() : '';

                        const alignment = 'center';

                        const widthVal = (block.querySelector('.img-width-input')?.value || '').trim();
                        const heightVal = (block.querySelector('.img-height-input')?.value || '').trim();
                        const styles = [];
                        if (widthVal) {
                            const w = /%$|px$|em$|rem$|vw$|vh$|auto$/i.test(widthVal) ? widthVal : (/^\d+$/.test(widthVal) ? widthVal + '%' : widthVal);
                            styles.push(`width: ${w}`);
                        } else {
                            styles.push('max-width: 100%');
                        }
                        if (heightVal) {
                            const h = /%$|px$|em$|rem$|vw$|vh$|auto$/i.test(heightVal) ? heightVal : (/^\d+$/.test(heightVal) ? heightVal + 'px' : heightVal);
                            styles.push(`height: ${h}`);
                        } else {
                            styles.push('height: auto');
                        }
                        styles.push('border-radius: 8px');
                        styles.push('display: block');
                        styles.push('margin-left: auto', 'margin-right: auto');
                        
                        let imageHtml = `<img src="${imgSrc}" alt="${escapeHtml(alt)}" style="${styles.join('; ')}">`;
                        
                        if (caption && caption !== '<br>') {
                            html += `<div style="text-align: center; margin: 16px 0; width: 100%;">\n    ${imageHtml}\n    <p style="margin-top: 8px; color: #888; font-size: 14px; font-style: italic; text-align: center; width: 100%;">${caption}</p>\n</div>\n\n`;
                        } else {
                            html += `<div style="text-align: center; margin: 16px 0; width: 100%;">\n    ${imageHtml}\n</div>\n\n`;
                        }
                    } else {
                        html += '<!-- Image block: no image attached -->\n';
                    }
                } else if (type === 'divider') {
                    html += '<hr style="border: none; border-top: 2px solid rgba(118, 117, 236, 0.3); margin: 32px 0;">\n\n';
                }
            });

            document.getElementById('contentInput').value = html;
        }

        document.getElementById('blogForm').addEventListener('submit', (e) => {
            updateContent();
            if (!document.getElementById('contentInput').value.trim()) {
                e.preventDefault();
                const noContentMsg = "<?= addslashes($lang['error_no_content'] ?? 'Please add content blocks to your blog post.') ?>";
                if (typeof showToast === 'function') {
                    showToast(noContentMsg, 'error');
                } else {
                    alert(noContentMsg);
                }
                return false;
            }
            const imageInput = document.getElementById('imageInput');
            if (imageInput && !imageInput.files.length) {
                return false;
            }
            const submitBtn = document.querySelector('#blogForm button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = '<?= $lang['action_creating'] ?>';
            }
        });

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function switchImageTab(blockId, tab) {
            const block = document.querySelector(`[data-id="${blockId}"]`);
            if (!block) return;

            const uploadContent = block.querySelector('.upload-content');
            const urlContent = block.querySelector('.url-content');
            const uploadBtn = block.querySelector('[data-tab="upload"]');
            const urlBtn = block.querySelector('[data-tab="url"]');

            if (tab === 'upload') {
                uploadContent.style.display = 'block';
                urlContent.style.display = 'none';
                uploadBtn.style.background = '#7675ec';
                uploadBtn.style.color = 'white';
                urlBtn.style.background = '#1a1a1a';
                urlBtn.style.color = 'rgba(255,255,255,0.7)';
            } else {
                uploadContent.style.display = 'none';
                urlContent.style.display = 'block';
                urlBtn.style.background = '#7675ec';
                urlBtn.style.color = 'white';
                uploadBtn.style.background = '#1a1a1a';
                uploadBtn.style.color = 'rgba(255,255,255,0.7)';
            }

            updateContent();
        }

        function compressImage(file, maxWidth, quality, callback) {
            const reader = new FileReader();
            reader.readAsDataURL(file);
            reader.onload = event => {
                const img = new Image();
                img.src = event.target.result;
                img.onload = () => {
                    let width = img.width;
                    let height = img.height;

                    if (width > maxWidth) {
                        height = Math.round((height * maxWidth) / width);
                        width = maxWidth;
                    }

                    const canvas = document.createElement('canvas');
                    canvas.width = width;
                    canvas.height = height;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, width, height);
                    
                    callback(canvas.toDataURL(file.type, quality));
                };
                img.onerror = error => console.error('Image compression error:', error);
            };
            reader.onerror = error => console.error('File reading error:', error);
        }

        function handleImageUpload(input, uniqueId) {
            const file = input.files[0];
            if (!file) return;

            const fileList = document.getElementById(`uploadedFiles_${uniqueId}`);

            const row = document.createElement("div");
            row.classList.add("uploaded-file-row");

            const icon = `
                <span class="uploaded-file-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#635bff" style="width: 20px; height: 20px;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                </span>
            `;

            const fileName = file.name;
            const fileSize = (file.size / (1024 * 1024)).toFixed(2) + " MB";

            row.innerHTML = `
                <img class="uploaded-file-thumb" src="" alt="Preview" style="width:40px; height:40px; border-radius:4px; margin-right:10px; display:none;">
                <span class="uploaded-file-name" style="flex:1;">${fileName}</span>
                <button class="uploaded-file-delete" onclick="this.parentElement.remove(); const b=this.closest('.editor-block'); if(b){ delete b.dataset.attachedFile; delete b.dataset.attachedFileName; delete b.dataset.attachedUrl; if(typeof updateContent === 'function') updateContent(); }" style="background:none; border:none; color:#ff6b6b; cursor:pointer; padding:4px;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 16px; height: 16px;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            `;

            try { if (fileList) fileList.innerHTML = ''; } catch (e) {}
            fileList.appendChild(row);

            const uploadedSection = document.getElementById(`uploadedSection_${uniqueId}`);
            if (uploadedSection) uploadedSection.style.display = 'block';

            const thumb = row.querySelector('.uploaded-file-thumb');
            if (thumb) {
                thumb.style.display = 'block';
                thumb.src = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCI+PHBhdGggZmlsbD0iI2ZmZiIgZD0iTTEyIDJhMTAgMTAgMCAxIDAgMTAgMTBAMTAgMTAgMCAwIDAtMTAtMTB6bTAgMThhOCA4IDAgMSAxIDgtOCA4IDggMCAwIDEtOCA4eiIvPjwvc3ZnPg==';
            }

            compressImage(file, 1920, 0.8, (compressedDataUrl) => {
                 const fetchResponse = fetch(compressedDataUrl);
                 fetchResponse.then(res => res.blob()).then(blob => {
                     const formData = new FormData();
                     formData.append('image', blob, file.name);
                     fetch('ajax_upload_image.php', {
                         method: 'POST',
                         body: formData
                     })
                     .then(response => response.json())
                     .then(data => {
                         if (data.success) {
                             row.dataset.remoteUrl = data.url;
                             if (thumb) {
                                 thumb.src = data.url;
                             }
                             const block = document.querySelector(`[data-id="${uniqueId}"]`);
                             if (block) {
                                 block.dataset.attachedFile = data.url;
                                 block.dataset.attachedFileName = fileName;
                                 if (typeof updateContent === 'function') updateContent();
                             }
                         } else {
                             alert("<?= $lang['error_upload_failed'] ?>: " + (data.error || 'Unknown error'));
                             row.remove();
                         }
                     })
                     .catch(err => {
                         console.error('Upload error', err);
                         alert("<?= $lang['error_upload_failed'] ?>.");
                         row.remove();
                     });
                 });
            });
        }

        document.addEventListener('click', function (e) {
            if (e.target.classList.contains('attach-btn')) {
                let block = e.target.closest('.image-block');
                let fileRows = [];

                if (block) {
                    fileRows = block.querySelectorAll('.uploaded-file-row');
                } else {
                    const fileListEl = e.target.closest('.file-upload-card')?.querySelector('.uploaded-files-list');
                    if (fileListEl) {
                        fileRows = fileListEl.querySelectorAll('.uploaded-file-row');
                        const m = (fileListEl.id || '').match(/^uploadedFiles_(.+)$/);
                        if (m) {
                            const uniqueId = m[1];
                            block = document.querySelector(`[data-id="${uniqueId}"]`);
                        }
                    }
                }

                if (!fileRows || fileRows.length === 0) {
                    alert("Please upload a file first.");
                    return;
                }

                const fileName = fileRows[0].querySelector('.uploaded-file-name').textContent;
                if (block) {
                    block.dataset.attachedFileName = fileName;
                }

                const remoteUrl = fileRows[0].dataset.remoteUrl;
                if (remoteUrl) {
                    if (remoteUrl.indexOf('data:') === 0) {
                        if (block) block.dataset.attachedFile = remoteUrl;
                    } else {
                        if (block) block.dataset.attachedUrl = remoteUrl;
                        let urlInput = block ? block.querySelector('input[type="url"]') : null;
                        if (!urlInput) {
                            const urlRow = e.target.closest('.file-upload-card')?.querySelector('.url-upload-row');
                            urlInput = urlRow ? urlRow.querySelector('input[type="url"]') : null;
                        }
                        if (urlInput) urlInput.value = remoteUrl;
                    }
                }
                e.target.textContent = "<?= $lang['action_attached'] ?>";
                e.target.disabled = true;

                if (typeof updateContent === 'function') updateContent();
                try { console.debug('attach-btn: attached', { fileName, remoteUrl }); } catch(e) {}
            }
        });

        document.addEventListener('click', function (e) {
            if (!e.target.classList.contains('url-upload-btn')) return;
            const block = e.target.closest('.image-block');
            if (!block) return;
            const urlInput = block.querySelector('.url-upload-input');
            if (!urlInput) return;
            const url = (urlInput.value || '').trim();
            if (!url) {
                alert("<?= $lang['error_enter_url'] ?>");
                return;
            }
            try {
                const parsed = new URL(url);
                if (!/^https?:$/i.test(parsed.protocol)) throw new Error('Invalid protocol');
            } catch (err) {
                alert("<?= $lang['error_invalid_protocol'] ?>");
                return;
            }

            const fileList = block.querySelector('.uploaded-files-list');
            if (!fileList) return;

            try { fileList.innerHTML = ''; } catch (e) {}

            const row = document.createElement('div');
            row.classList.add('uploaded-file-row');
            
            block.dataset.attachedUrl = url;
            delete block.dataset.attachedFile;
            delete block.dataset.attachedFileName;
            row.innerHTML = `
                <img class="uploaded-file-thumb" src="${url}" alt="Preview" style="width:40px; height:40px; border-radius:4px; object-fit:cover; margin-right:10px;">
                <span class="uploaded-file-name" style="flex:1;">${url}</span>
                <button class="uploaded-file-delete" onclick="this.parentElement.remove(); const b=this.closest('.editor-block'); if(b){ delete b.dataset.attachedFile; delete b.dataset.attachedFileName; delete b.dataset.attachedUrl; if(typeof updateContent === 'function') updateContent(); }" style="background:none; border:none; color:#ff6b6b; cursor:pointer; padding:4px;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 16px; height: 16px;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            `;
            fileList.appendChild(row);
            urlInput.value = '';
            row.dataset.remoteUrl = url;
            
            const editorBlock = block.closest('.editor-block');
            if (editorBlock) {
                const uniqueId = editorBlock.dataset.id;
                const uploadedSection = document.getElementById(`uploadedSection_${uniqueId}`);
                const uploadFooter = document.getElementById(`uploadFooter_${uniqueId}`);
                if (uploadedSection) uploadedSection.style.display = 'block';
                if (uploadFooter) uploadFooter.style.display = 'flex';
            }
            if (typeof updateContent === 'function') updateContent();
            try { console.debug('url-upload: added remote url row', url); } catch(e) {}
        });


        function removeUploadedImage(blockId) {
            const block = document.querySelector(`[data-id="${blockId}"]`);
            if (!block) return;

            const preview = block.querySelector('.upload-preview');
            const fileInput = block.querySelector('input[type="file"]');
            const img = preview.querySelector('img');

            img.src = '';
            img.removeAttribute('data-file');
            fileInput.value = '';
            preview.style.display = 'none';

            updateContent();
        }

        function renumberListItems(block) {
            if (!block) return;
            const items = block.querySelectorAll('.editor-list-item');
            const placeholderBase = <?= json_encode($lang['placeholder_list_item'] ?? 'List item') ?>;
            items.forEach((li, index) => {
                const richText = li.querySelector('.rich-text-content');
                if (richText) {
                    richText.setAttribute('data-placeholder', placeholderBase + " " + (index + 1));
                }
            });
        }

        document.addEventListener('click', function (e) {
            if (e.target.classList.contains('add-list-item')) {
                e.preventDefault();
                e.stopImmediatePropagation();
                const block = e.target.closest('.editor-block');
                if (!block) return;
                const list = block.querySelector('.editor-list');
                if (!list) return;
                const itemCount = list.querySelectorAll('.editor-list-item').length;
                
                const savedColor = block.dataset.textColor || '';
                const styleAttr = savedColor ? `style="color: ${savedColor}"` : '';

                const li = document.createElement('li');
                li.className = 'editor-list-item';
                const placeholderBase = <?= json_encode($lang['placeholder_list_item'] ?? 'List item') ?>;
                li.innerHTML = `
                    <div class="li-content-wrapper">
                        <div class="rich-text-content" contenteditable="true" ${styleAttr} data-placeholder="${placeholderBase} ${itemCount + 1}" oninput="updateContent()" onkeydown="handleListKeydown(event, this)"></div>
                        <button type="button" class="remove-list-item" title="Remove item">×</button>
                    </div>
                `;
                list.appendChild(li);
                li.querySelector('.rich-text-content').focus();
                updateContent();
            }
            if (e.target.classList.contains('remove-list-item')) {
                const li = e.target.closest('.editor-list-item');
                if (li) {
                    const block = li.closest('.editor-block');
                    li.remove();
                    renumberListItems(block);
                    updateContent();
                }
            }
        });

        function makeBlockDraggable(block) {
            if (!block || !block.classList || !block.classList.contains('editor-block')) return;
            block.setAttribute('draggable', 'true');

            block.addEventListener('dragstart', function (e) {
                e.stopPropagation();
                window.__draggingBlock = this;
                this.classList.add('dragging');
                try { e.dataTransfer.setData('text/plain', ''); } catch (err) {}
                e.dataTransfer.effectAllowed = 'move';
            });

            block.addEventListener('dragend', function (e) {
                e.stopPropagation();
                this.classList.remove('dragging');
                window.__draggingBlock = null;
                updateContent();
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
             const form = document.getElementById('blogForm');
             if (form) {
                 form.addEventListener('keydown', (e) => {
                     if (e.key === 'Enter') {
                         const target = e.target;
                         if (target.tagName.toLowerCase() === 'textarea' || target.classList.contains('allow-enter') || target.isContentEditable) {
                             return;
                         }
                         e.preventDefault();
                     }
                 });
             }
        });

        const editor = document.getElementById('contentEditor');
        if (editor) {
            editor.addEventListener('dragover', function (e) {
                e.preventDefault();
                const dragging = window.__draggingBlock;
                if (!dragging) return;
                const target = e.target.closest('.editor-block');
                if (!target || target === dragging) return;
                const rect = target.getBoundingClientRect();
                const after = (e.clientY - rect.top) / rect.height > 0.5;
                try {
                    target.parentNode.insertBefore(dragging, after ? target.nextSibling : target);
                } catch (err) {}
            });
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Tab') {
                const richText = e.target.closest('.editor-list-item .rich-text-content');
                if (richText) {
                    e.preventDefault();
                    const li = richText.closest('.editor-list-item');
                    const currentPadding = parseInt(li.style.paddingLeft || '0');
                    if (e.shiftKey) {
                        if (currentPadding >= 20) {
                            li.style.paddingLeft = (currentPadding - 20) + 'px';
                        }
                    } else {
                        li.style.paddingLeft = (currentPadding + 20) + 'px';
                    }
                    updateContent();
                }
            }
        });

        document.addEventListener('DOMContentLoaded', function () {
            const blocks = document.querySelectorAll('.editor-block');
            blocks.forEach(b => makeBlockDraggable(b));
        });
    </script>

    <script>
        (function () {
            function toPartsForTZ(date, tz) {
                const fmt = new Intl.DateTimeFormat('en-GB', { timeZone: tz, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hour12: false });
                const parts = fmt.formatToParts(date);
                const obj = {};
                parts.forEach(p => { if (p.type && p.type !== 'literal') obj[p.type] = p.value; });
                return obj;
            }
            function makeLocalDatetimeStringFromParts(p) {
                return `${p.year}-${p.month}-${p.day}T${p.hour}:${p.minute}`;
            }

        let savedSelectionRange = null;
        function saveSelection() {
            const sel = window.getSelection();
            if (sel.rangeCount > 0) {
                return sel.getRangeAt(0);
            }
            return null;
        }
        function restoreSelection(range) {
            if (range) {
                const sel = window.getSelection();
                sel.removeAllRanges();
                sel.addRange(range);
            }
        }

        document.addEventListener('focus', function(e) {
            if (e.target.classList.contains('rich-text-content')) {
                const block = e.target.closest('.editor-block');
                if (block) {
                    block._lastActiveEditor = e.target;
                }
            }
        }, true);

        function formatText(command, btn) {
            if (btn) {
                const block = btn.closest('.editor-block');
                if (block) {
                    let editor = block._lastActiveEditor;
                    if (!editor || !block.contains(editor)) {
                        editor = block.querySelector('.rich-text-content');
                    }
                    
                    if (editor && document.activeElement !== editor) {
                        editor.focus();
                    }
                    
                    if (command.startsWith('justify')) {
                        const type = block.getAttribute('data-type');
                        if (type === 'heading' || type.startsWith('heading')) {
                            const alignment = command === 'justifyLeft' ? 'left' : 
                                            command === 'justifyCenter' ? 'center' : 
                                            command === 'justifyRight' ? 'right' : '';
                            const wrapper = block.querySelector('.heading-input-wrapper') || block.querySelector('h1, h2, h3, h4, h5, h6');
                            if (wrapper) {
                                wrapper.style.textAlign = alignment;
                                const heading = wrapper.tagName.match(/^H[1-6]$/) ? wrapper : wrapper.querySelector('h1, h2, h3, h4, h5, h6');
                                if (heading) heading.style.textAlign = alignment;
                                const input = block.querySelector('input');
                                if (input) input.style.textAlign = alignment;
                            }
                        }

                        const alignBtns = block.querySelectorAll('.formatting-toolbar .format-btn');
                        alignBtns.forEach(b => {
                            const t = b.getAttribute('title') || '';
                            if (t.includes('Align') || t.includes('Justify')) {
                                b.classList.remove('active');
                            }
                        });
                    }
                }
            }
            
            if (command === 'createLink') {
                showLinkModal((url) => {
                    document.execCommand(command, false, url);
                    updateContent();
                });
            } else {
                document.execCommand(command, false, null);
                updateContent();
            }
            
            if (btn) {
                const isActive = document.queryCommandState(command);
                btn.classList.toggle('active', isActive);
            }
        }
        
        document.addEventListener('selectionchange', function() {
            const selection = document.getSelection();
            if (!selection.rangeCount) return;
            
            const anchorNode = selection.anchorNode;
            const activeBlock = anchorNode ? (anchorNode.nodeType === 1 ? anchorNode.closest('.editor-block') : anchorNode.parentElement.closest('.editor-block')) : null;

            document.querySelectorAll('.formatting-toolbar .format-btn').forEach(btn => btn.classList.remove('active'));

            if (activeBlock) {
                let currentElement = anchorNode;
                if (currentElement && currentElement.nodeType === 3) {
                    currentElement = currentElement.parentElement;
                }
                let isInsideLink = false;
                while (currentElement && currentElement !== activeBlock) {
                    if (currentElement.tagName === 'A') {
                        isInsideLink = true;
                        break;
                    }
                    currentElement = currentElement.parentElement;
                }
                
                 const buttons = activeBlock.querySelectorAll('.formatting-toolbar .format-btn');
                 buttons.forEach(btn => {
                    const title = btn.getAttribute('title') || '';
                    let command = '';
                 });
                 
                 const fontSizeSelect = activeBlock.querySelector('.font-size-select');
                 if (fontSizeSelect && selection.rangeCount > 0) {
                     const range = selection.getRangeAt(0);
                     let fontSizes = new Set();
                     if (range.collapsed) {
                         let element = anchorNode.nodeType === 3 ? anchorNode.parentElement : anchorNode;
                         if (element) {
                             const fontSize = window.getComputedStyle(element).fontSize;
                             fontSizes.add(parseInt(fontSize));
                         }
                     } else {
                         const container = range.commonAncestorContainer;
                         const walker = document.createTreeWalker(
                             container.nodeType === 3 ? container.parentElement : container,
                             NodeFilter.SHOW_TEXT,
                             null
                         );
                         let node;
                         while (node = walker.nextNode()) {
                             if (range.intersectsNode(node)) {
                                 const element = node.parentElement;
                                 if (element) {
                                     const fontSize = window.getComputedStyle(element).fontSize;
                                     fontSizes.add(parseInt(fontSize));
                                 }
                             }
                         }
                     }
                     if (fontSizes.size > 1) {
                         fontSizeSelect.value = '';
                     } else if (fontSizes.size === 1) {
                         const pxSize = Array.from(fontSizes)[0];
                         let sizeValue = '3';
                         if (pxSize <= 12) sizeValue = '1';
                         else if (pxSize <= 14) sizeValue = '2';
                         else if (pxSize <= 16) sizeValue = '3';
                         else if (pxSize <= 18) sizeValue = '4';
                         else if (pxSize <= 24) sizeValue = '5';
                         else if (pxSize <= 32) sizeValue = '6';
                         else if (pxSize > 32) sizeValue = '7';
                         fontSizeSelect.value = sizeValue;
                     }
                 }
            }
        });

        function applyFontSize(select) {
            const size = select.value;
            if (size) {
                const block = select.closest('.editor-block');
                if (block) {
                    let editor = block._lastActiveEditor;
                    if (!editor || !block.contains(editor)) {
                        editor = block.querySelector('.rich-text-content');
                    }
                    if (editor) {
                        editor.focus();
                    }
                }
                document.execCommand('fontSize', false, size);
                updateContent();
            }
        }
        
        function applyTextColor(color, swatch) {
            const block = swatch.closest('.editor-block');
            block.dataset.textColor = color;
            const list = block.querySelector('.editor-list');
            if (list) {
                list.style.color = color;
            }
            const richTexts = block.querySelectorAll('.rich-text-content');
            richTexts.forEach(el => el.style.color = color);
            const swatches = block.querySelectorAll('.color-swatch');
            swatches.forEach(s => s.classList.remove('active'));
            if (swatch) swatch.classList.add('active');
            updateContent();
        }

        function handleParagraphKeydown(e, el) {
            if (e.key === 'Enter' && !e.shiftKey) {
                 e.preventDefault();
                 document.execCommand('insertLineBreak');
                 updateContent();
            }
        }

        function handleListKeydown(e, el) {
            const li = el.closest('.editor-list-item');
            const list = li.parentElement;
            const block = list.closest('.editor-block');
            
            if (e.shiftKey && e.key === 'Enter') {
                return;
            }

            if (e.key === 'Enter') {
                e.preventDefault();
                e.stopPropagation();
                
                const text = el.textContent;
                const isEmpty = text.trim() === '';

                if (isEmpty) {
                    return false;
                } else {
                    const savedColor = block.dataset.textColor || '';
                    const styleAttr = savedColor ? `style="color: ${savedColor}"` : '';
                    
                    const newLi = document.createElement('li');
                    newLi.className = 'editor-list-item';
                    const placeholderBase = <?= json_encode($lang['placeholder_list_item'] ?? 'List item') ?>;
                    newLi.innerHTML = `
                        <div class="li-content-wrapper">
                            <div class="rich-text-content" contenteditable="true" ${styleAttr} data-placeholder="${placeholderBase}" oninput="updateContent()" onkeydown="handleListKeydown(event, this)"></div>
                            <button type="button" class="remove-list-item" title="Remove item">×</button>
                        </div>
                    `;
                    
                    if (li.nextSibling) {
                        list.insertBefore(newLi, li.nextSibling);
                    } else {
                        list.appendChild(newLi);
                    }
                    
                    newLi.querySelector('.rich-text-content').focus();
                    updateContent();
                }
            }

            if (e.key === 'Backspace') {
                const text = el.innerText.replace(/[\n\r]+$/, '');
                const selection = window.getSelection();
                const isAtStart = selection.anchorOffset === 0 && selection.isCollapsed;

                if (text === '') {
                    e.preventDefault();
                    
                    const prev = li.previousElementSibling;
                    const next = li.nextElementSibling;
                    const parentBlock = list.closest('.editor-block');

                    li.remove();
                    
                    if (list.children.length === 0) {
                        parentBlock.remove();
                    }
                    else if (prev) {
                        const prevInput = prev.querySelector('.rich-text-content');
                        prevInput.focus();
                        const range = document.createRange();
                        range.selectNodeContents(prevInput);
                        range.collapse(false);
                        const sel = window.getSelection();
                        sel.removeAllRanges();
                        sel.addRange(range);
                    } else if (next) {
                        next.querySelector('.rich-text-content').focus();
                    }
                    updateContent();
                } else if (isAtStart) {
                    e.preventDefault();
                    const prev = li.previousElementSibling;
                    
                    if (prev) {
                        const prevInput = prev.querySelector('.rich-text-content');
                        const currentText = el.innerHTML;
                        prevInput.innerHTML += currentText;
                        li.remove();
                        prevInput.focus();
                        updateContent();
                    } else {
                         const parentBlock = list.closest('.editor-block');
                         li.remove();
                         if (list.children.length === 0) parentBlock.remove();
                         updateContent();
                    }
                }
            }
        }

        function showLinkModal(callback) {
            const modal = document.getElementById('linkModal');
            const input = document.getElementById('modalLinkInput');
            const addBtn = document.getElementById('addLinkModalBtn');
            const closeBtn = document.getElementById('closeLinkModalBtn');
            
            if (!modal || !input || !addBtn || !closeBtn) return;

            savedSelectionRange = saveSelection();
            
            modal.style.display = 'flex';
            input.value = '';
            setTimeout(() => input.focus(), 10);

            const handleAdd = () => {
                const url = input.value.trim();
                modal.style.display = 'none';
                cleanup();
                if (url) {
                    restoreSelection(savedSelectionRange);
                    callback(url);
                }
            };

            const handleCancel = () => {
                modal.style.display = 'none';
                cleanup();
            };

            const handleKey = (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    handleAdd();
                } else if (e.key === 'Escape') {
                    handleCancel();
                }
            };

            function cleanup() {
                addBtn.removeEventListener('click', handleAdd);
                closeBtn.removeEventListener('click', handleCancel);
                input.removeEventListener('keydown', handleKey);
            }

            addBtn.addEventListener('click', handleAdd);
            closeBtn.addEventListener('click', handleCancel);
            input.addEventListener('keydown', handleKey);
        }

            document.addEventListener('DOMContentLoaded', function () {
                const input = document.getElementById('publishAtInput');
                if (!input) return;
                try {
                    const now = new Date();
                    const berlinParts = toPartsForTZ(now, 'Europe/Berlin');
                    input.min = makeLocalDatetimeStringFromParts(berlinParts);
                } catch (e) {
                }

                const form = document.getElementById('blogForm');
                if (form) {
                    form.addEventListener('submit', function () {
                        if (!input.value) return;
                        try {
                            const [date, time] = input.value.split('T');
                            if (!date || !time) return;
                            const [y, mo, d] = date.split('-').map(Number);
                            const [h, mi] = time.split(':').map(Number);
                            const localDate = new Date(y, mo - 1, d, h, mi);
                            const berlinParts = toPartsForTZ(localDate, 'Europe/Berlin');
                            input.value = makeLocalDatetimeStringFromParts(berlinParts);
                        } catch (e) { }
                    });
                }
            });
        })();
    </script>

    <div id="previewModal" class="modal-overlay" style="display: none; z-index: 10000; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); align-items: center; justify-content: center;">
        <div class="modal-card-flow" style="width: 90%; max-width: 900px; height: 90vh; display: flex; flex-direction: column; background: #000; color: #fff; border-radius: 12px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);">
            <div class="modal-header" style="border-bottom: 1px solid #333; padding: 16px 24px; display: flex; justify-content: space-between; align-items: center; background: #1a1a1a; border-radius: 12px 12px 0 0;">
                <h3 class="modal-title" style="color: #fff; margin: 0; font-size: 1.25rem; font-weight: 600;"><?= $lang['btn_preview'] ?? 'Post Preview' ?></h3>
                <button onclick="closePreviewModal()" style="background: none; border: none; color: #9ca3af; cursor: pointer; padding: 4px; border-radius: 4px; display: flex; align-items: center; justify-content: center; transition: background 0.2s;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width: 24px; height: 24px;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="modal-body" style="flex: 1; overflow-y: auto; padding: 40px; background: #000;">
                 <div id="previewContainer" class="blog-content-white" style="max-width: 800px; margin: 0 auto; color: #fff;">
                 </div>
            </div>
        </div>
    </div>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&display=swap');

        .blog-content-white {
            font-family: 'Manrope', sans-serif !important;
        }

        .blog-content-white p,
        .blog-content-white span,
        .blog-content-white a,
        .blog-content-white li {
            font-family: 'Manrope', sans-serif !important;
            font-size: 1rem !important;
        }
        
        .blog-content-white h1, .blog-content-white h2, .blog-content-white h3,
        .blog-content-white h4, .blog-content-white h5, .blog-content-white h6 {
            font-family: 'Manrope', sans-serif !important;
            font-weight: 700;
        }

        .blog-content-white p {
            font-size: 1rem;
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
            background: url('../../assets/img/quote-icon.png') no-repeat left top;
            background-size: contain;
        }

        .blog-content-white blockquote p {
            font-family: inherit;
            font-weight: 400;
            line-height: 1.7;
            margin-bottom: 0;
            color: #ffffff !important;
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

        .blog-content-white li::marker {
            color: inherit;
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
    </style>

    <script>
        window.adminName = <?= json_encode($_SESSION['admin']['name'] ?? 'User') ?>;
        window.entraLanguage = <?= json_encode($defaultLanguage) ?>;
        <?php
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        ?>
        window.csrfToken = <?= json_encode($_SESSION['csrf_token']) ?>;

        window.formatText = function(command, btn) {
            if (command === 'createLink' && typeof showLinkModal === 'function') {
                showLinkModal((url) => {
                    document.execCommand(command, false, url);
                    if (typeof updateContent === 'function') updateContent();
                });
                if (btn) btn.classList.toggle('active', true);
            } else {
                 document.execCommand(command, false, null);
                 if (btn) btn.classList.toggle('active', document.queryCommandState(command));
                 if (typeof updateContent === 'function') updateContent();
            }
        }

    function closePreviewModal() {
        document.getElementById('previewModal').style.display = 'none';
        document.body.style.overflow = '';
    }

    function previewPost() {
        if (typeof updateContent === 'function') {
            updateContent();
        }
        
        const content = document.getElementById('contentInput').value;
        const container = document.getElementById('previewContainer');
        
        container.innerHTML = content;
        
        document.getElementById('previewModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
    
    document.getElementById('previewModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closePreviewModal();
        }
    });
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closePreviewModal();
        }
    });

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/dompurify@3.0.8/dist/purify.min.js"></script>
    <script src="../assets/js/ai-assistant.js?v=<?= time() ?>"></script>
</body>
</html>