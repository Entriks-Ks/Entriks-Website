<?php

require_once __DIR__ . '/session_config.php';

require_once 'config.php';

/** @var \MongoDB\Database $db */

if (!isset($_SESSION['admin'])) {

    header('Location: login.php');

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

$hasCmsAccess = $isAdmin || in_array($userRole, ['Editor', 'Content Manager']);



if (!$hasCmsAccess) {

    header('Location: dashboard.php');

    exit;

}



// Handle Form Submission

$message = '';

$messageType = '';



if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $newContent = [];

    foreach ($_POST as $key => $value) {

        if (strpos($key, 'content_') === 0) {

            $realKey = substr($key, 8);  // remove 'content_' prefix

            $newContent[$realKey] = $value;

        }

    }



    // Fetch existing DRAFT content first to merge

    $currentContent = [];

    try {

        $doc = $db->page_content->findOne(['page_id' => 'home']);

        if ($doc) {

            // Start with live content, overwrite with existing draft if any

            if (isset($doc['content'])) {

                $currentContent = json_decode(json_encode($doc['content']), true);

            }

            if (isset($doc['draft_content'])) {

                $draft = json_decode(json_encode($doc['draft_content']), true);

                $currentContent = array_merge($currentContent, $draft);

            }

        }

    } catch (Exception $e) {

    }



    // Merge new form data into current draft state

    $finalContent = array_merge($currentContent, $newContent);



    try {

        // SAVE TO DRAFT

        $db->page_content->updateOne(

            ['page_id' => 'home'],

            [

                '$set' => [

                    'draft_content' => $finalContent,

                    'last_draft_save' => new MongoDB\BSON\UTCDateTime()

                ]

            ],

            ['upsert' => true]

        );

        $message = 'Draft saved successfully! Click Publish to make life.';

        $messageType = 'success';

    } catch (Exception $e) {

        $message = 'Error saving draft: ' . $e->getMessage();

        $messageType = 'error';

    }

}



// Fetch Current Content (Prioritize Draft)

$pageContent = [];

try {

    $doc = $db->page_content->findOne(['page_id' => 'home']);

    if ($doc) {

        if (isset($doc['content'])) {

            $pageContent = json_decode(json_encode($doc['content']), true);

        }

        if (isset($doc['draft_content'])) {

            $draft = json_decode(json_encode($doc['draft_content']), true);

            $pageContent = array_merge($pageContent, $draft);

        }

    }

} catch (Exception $e) {

}



// Fetch Settings for Favicon

$siteFaviconUrl = '../assets/img/favicon.png';

try {

    $settings = $db->settings->findOne(['type' => 'global_config']);

    if ($settings && !empty($settings['favicon_url'])) {

        $siteFaviconUrl = '../' . $settings['favicon_url'];

    }

} catch (Exception $e) {

}



function get_val($key, $default = '')

{

    global $pageContent;

    return isset($pageContent[$key]) ? $pageContent[$key] : $default;

}



?>

<!DOCTYPE html>

<html>



<head>

    <meta charset="utf-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title><?= $lang['cms_title'] ?></title>

    <link rel="shortcut icon" href="<?= htmlspecialchars($siteFaviconUrl) ?>" type="image/x-icon">

    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?= time() ?>">

    <script src="assets/js/global-search.js?v=<?= time() ?>" defer></script>

    <style>

        .portal-grid {

            display: grid;

            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); /* Slightly wider minimum */

            gap: 32px;

            margin-top: 30px;

        }



        .portal-card {

            border-radius: 20px;

            padding: 40px 32px;

            text-align: center;

            text-decoration: none;

            color: #fff;

            border: 2px dashed rgba(255, 255, 255, 0.2);

            box-shadow: 0 10px 30px -5px rgba(0, 0, 0, 0.3);

            display: flex;

            flex-direction: column;

            align-items: center;

            justify-content: center;

            min-height: 320px;

            position: relative;

            overflow: hidden;

        }

        

        .portal-card:hover {

            background: rgba(255, 255, 255, 0.03);

        }



        .portal-card svg {

            width: 64px;

            height: 64px;

            margin-bottom: 24px;

            filter: drop-shadow(0 4px 6px rgba(0,0,0,0.3));

        }

        

        .svg-flag {

            width: 80px;

            height: 80px;

            margin-bottom: 25px;

            border-radius: 50%;

            box-shadow: 0 4px 15px rgba(0,0,0,0.3);

        }



        .card-title {

            font-size: 1.4rem;

            font-weight: 700;

            margin-bottom: 12px;

            color: #fff;

            letter-spacing: -0.02em;

        }



        .card-desc {

            font-size: 0.95rem;

            color: #9ca3af;

            margin-bottom: 32px;

            line-height: 1.6;

            max-width: 90%;

        }



        .btn-launch {

            display: inline-flex;

            align-items: center;

            gap: 8px;

            background: rgba(255, 255, 255, 0.05);

            color: #fff;

            padding: 12px 24px;

            border-radius: 12px;

            font-weight: 600;

            font-size: 0.95rem;

            transition: all 0.3s ease;

            border: 1px solid rgba(255, 255, 255, 0.1);

        }



        .portal-card:hover .btn-launch {

            background: #d225d7;

        }

        

        .portal-header {

           margin-bottom: 24px;

        }

        

        .portal-title {

            font-size: 1.8rem; 

            font-weight: 700; 

            color: #fff; 

            margin-bottom: 8px;

        }

        

        .section-divider {

            margin-top: 80px; 

            margin-bottom: 40px; 

            border-top: 1px solid #333; 

            padding-top: 40px;

        }



        .h-icon {

            font-size: 32px;

            margin-right: 20px;

            width: 50px;

            text-align: center;

        }



        .h-content {

            flex: 1;

            padding-left: 20px;

        }



        .h-content h3 {

            font-size: 1.15rem;

            font-weight: 600;

            margin: 0 0 6px 0;

            color: #fff;

            letter-spacing: -0.01em;

        }



        .h-content p {

            font-size: 0.85rem;

            color: #9ca3af;

            margin: 0;

        }



        .h-action {

            width: 42px;

            height: 42px;

            background: rgba(255, 255, 255, 0.05);

            border-radius: 12px;

            display: flex;

            align-items: center;

            justify-content: center;

            color: #9ca3af;

            transition: all 0.3s ease;

            border: 1px solid rgba(255, 255, 255, 0.05);

        }



        .horizontal-card:hover .h-action {

            background: #d225d7;

            color: #fff;

            border-color: #d225d7;

            box-shadow: 0 4px 12px rgba(210, 37, 215, 0.2);

        }

    </style>

</head>



<body>

    <!-- Preloader -->

    <div class="preloader" id="preloader">

        <div class="preloader-spinner"></div>

    </div>





    <div class="layout">



        <!-- SIDEBAR -->

        <?php $sidebarVariant = 'dashboard';

        $activeMenu = 'cms_manager';

        include __DIR__ . '/partials/sidebar.php'; ?>



        <main class="content">



            <!-- TOPBAR (Copied from Dashboard) -->

            <!-- TOPBAR -->

            <?php

            $pageTitle = $lang['cms_title'];

            include __DIR__ . '/partials/topbar.php';

            ?>



            <div class="portal-header">

            </div>



            <div class="portal-grid">



                <!-- German Homepage Card -->

                <a href="../index.php?edit=true" target="_blank" class="portal-card" id="card-edit-de">

                    <div style="width: 60px; height: 60px; border-radius: 8px; overflow: hidden; margin-bottom: 20px;">

                        <svg viewBox="0 0 5 3" style="width: 100%; height: 100%;">

                            <rect width="5" height="1" y="0" fill="#000"/>

                            <rect width="5" height="1" y="1" fill="#D00"/>

                            <rect width="5" height="1" y="2" fill="#FFCE00"/>

                        </svg>

                    </div>

                    <h3 class="card-title"><?= $lang['cms_german_homepage'] ?></h3>

                    <p class="card-desc"><?= $lang['cms_german_desc'] ?></p>

                    <span class="btn-launch"><i class="fas fa-edit"></i> <?= $lang['cms_edit_german'] ?></span>

                </a>



                <!-- English Homepage Card -->

                <a href="../index-en.php?edit=true" target="_blank" class="portal-card" id="card-edit-en">

                    <div style="width: 60px; height: 60px; border-radius: 8px; overflow: hidden; margin-bottom: 20px;">

                        <svg viewBox="0 0 60 30" style="width: 100%; height: 100%;">

                            <clipPath id="s">

                                <path d="M0,0 v30 h60 v-30 z" />

                            </clipPath>

                            <clipPath id="t">

                                <path d="M30,15 h30 v15 z v-30 h-30 z h-30 v30 z v-15 z" />

                            </clipPath>

                            <g clip-path="url(#s)">

                                <path fill="#012169" d="M0,0 v30 h60 v-30 z" />

                                <path stroke="#fff" stroke-width="6" d="M0,0 L60,30 M60,0 L0,30" />

                                <path stroke="#C8102E" stroke-width="4" clip-path="url(#t)" d="M0,0 L60,30 M60,0 L0,30" />

                                <path stroke="#fff" stroke-width="10" d="M30,0 v30 M0,15 h60" />

                                <path stroke="#C8102E" stroke-width="6" d="M30,0 v30 M0,15 h60" />

                            </g>

                        </svg>

                    </div>

                    <h3 class="card-title"><?= $lang['cms_english_homepage'] ?></h3>

                    <p class="card-desc"><?= $lang['cms_english_desc'] ?></p>

                    <span class="btn-launch"><i class="fas fa-edit"></i> <?= $lang['cms_edit_english'] ?></span>

                </a>

            </div>



            <!-- ─── TALENT HUB SITES ─── -->

            <div class="section-divider">

                <h2 class="portal-title" style="font-size:1.3rem;margin-bottom:4px;">🏢 ENTRIKS Talent Hub – Websites</h2>

                <p style="color:#9ca3af;font-size:0.9rem;">Click a site to open the inline editor</p>

            </div>



            <div class="portal-grid">



                <!-- Sales Intelligence -->

                <a href="../sales.php?edit=true" target="_blank" class="portal-card" style="background:linear-gradient(135deg,rgba(0,200,255,0.08),rgba(124,58,237,0.12));">

                    <div style="width:64px;height:64px;border-radius:16px;background:rgba(0,200,255,0.15);display:flex;align-items:center;justify-content:center;margin-bottom:20px;">

                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#00c8ff" style="width:32px;height:32px;">

                          <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />

                        </svg>

                    </div>

                    <h3 class="card-title">Sales Intelligence</h3>

                    <p class="card-desc">KI-gestütztes Dialog Marketing – Outbound, Terminierung, Closing</p>

                    <span class="btn-launch"><i class="fas fa-edit"></i> Edit Page</span>

                </a>



                <!-- Banking & Insurance -->

                <a href="../banking.php?edit=true" target="_blank" class="portal-card" style="background:linear-gradient(135deg,rgba(201,168,76,0.08),rgba(10,22,40,0.3));">

                    <div style="width:64px;height:64px;border-radius:16px;background:rgba(201,168,76,0.15);display:flex;align-items:center;justify-content:center;margin-bottom:20px;">

                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#C9A84C" style="width:32px;height:32px;">

                          <path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0012 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75z" />

                        </svg>

                    </div>

                    <h3 class="card-title">Banking &amp; Insurance</h3>

                    <p class="card-desc">Premium Nearshoring für Finanzdienstleister – BaFin-kompatibel, DSGVO-konform</p>

                    <span class="btn-launch"><i class="fas fa-edit"></i> Edit Page</span>

                </a>



                <!-- Karriere -->

                <a href="../karriere.php?edit=true" target="_blank" class="portal-card" style="background:linear-gradient(135deg,rgba(123,47,255,0.1),rgba(0,200,255,0.08));">

                    <div style="width:64px;height:64px;border-radius:16px;background:rgba(123,47,255,0.15);display:flex;align-items:center;justify-content:center;margin-bottom:20px;">

                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#7B2FFF" style="width:32px;height:32px;">

                          <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 .621-.504 1.125-1.125 1.125H4.875c-.621 0-1.125-.504-1.125-1.125v-4.25m16.5 0a2.25 2.25 0 00-2.25-2.25H4.875a2.25 2.25 0 00-2.25 2.25m16.5 0V9.45c0-.621-.504-1.125-1.125-1.125h-14.25c-.621 0-1.125.504-1.125 1.125V14.15m16.5 0h.008v.008h-.008v-.008zm-16.5 0h.008v.008h-.008v-.008zM12 3v3m0 0l-1.5-1.5M12 6l1.5-1.5" />

                        </svg>

                    </div>

                    <h3 class="card-title">Karriere bei ENTRIKS</h3>

                    <p class="card-desc">Karriere-Website – Jobs, Kultur, Testimonials &amp; Bewerbungsformular</p>

                    <span class="btn-launch"><i class="fas fa-edit"></i> Edit Page</span>

                </a>



                <!-- Software -->

                <a href="../software.php?edit=true" target="_blank" class="portal-card" style="background:linear-gradient(135deg,rgba(204,0,255,0.08),rgba(10,255,176,0.06));">

                    <div style="width:64px;height:64px;border-radius:16px;background:rgba(204,0,255,0.12);display:flex;align-items:center;justify-content:center;margin-bottom:20px;">

                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#CC00FF" style="width:32px;height:32px;">

                          <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 16.5" />

                        </svg>

                    </div>

                    <h3 class="card-title">ENTRIKS Software</h3>

                    <p class="card-desc">SaaS-Produkte &amp; App-Entwicklung – ElevateSites, ClosrAI, Perso360</p>

                    <span class="btn-launch"><i class="fas fa-edit"></i> Edit Page</span>

                </a>



                <!-- TalentHub -->

                <a href="../talenthub.php?edit=true" target="_blank" class="portal-card" style="background:linear-gradient(135deg,rgba(0,200,255,0.08),rgba(201,168,76,0.08));">

                    <div style="width:64px;height:64px;border-radius:16px;background:rgba(0,200,255,0.1);display:flex;align-items:center;justify-content:center;margin-bottom:20px;">

                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#00c8ff" style="width:32px;height:32px;">

                          <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />

                        </svg>

                    </div>

                    <h3 class="card-title">ENTRIKS Talent Hub</h3>

                    <p class="card-desc">Nearshoring &amp; Active Sourcing – Fachkräfte aus dem Kosovo für DACH</p>

                    <span class="btn-launch"><i class="fas fa-edit"></i> Edit Page</span>

                </a>



            </div>







        </main>

    </div>



    <script>

        const preloaderStart = Date.now();

        window.addEventListener('load', function() {

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

    <script>

        document.addEventListener('DOMContentLoaded', () => {

            const hash = window.location.hash.substring(1);

            if (hash) {

                const target = document.getElementById(hash);

                if (target) {

                    target.scrollIntoView({ behavior: 'smooth', block: 'center' });

                    // Visual highlight

                    target.style.transition = 'all 0.5s ease';

                    target.style.position = 'relative';

                    target.style.zIndex = '10';

                    target.style.boxShadow = '0 0 0 4px rgba(210, 37, 215, 0.4), 0 10px 30px rgba(0,0,0,0.5)';

                    target.style.transform = 'scale(1.02)';

                    

                    setTimeout(() => {

                        target.style.boxShadow = '';

                        target.style.transform = '';

                        target.style.zIndex = '';

                    }, 2000);

                }

            }

        });

    </script>

</body>

</html>

