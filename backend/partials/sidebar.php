<?php
if (!isset($sidebarVariant)) {
    $sidebarVariant = 'dashboard';
}
if (!isset($activeMenu)) {
    $activeMenu = 'dashboard';
}
$sidebarClass = $sidebarVariant === 'blog' ? 'sidebar sidebar--blog' : 'sidebar sidebar--dashboard';
$basePrefix = ($sidebarVariant === 'blog') ? '../' : '';

// Use logo URL from config.php (already loaded with proper path handling)
// Don't add basePrefix since config.php already handles the correct path
$logoUrl = $siteLogoUrl;
?>

<link rel="stylesheet" href="<?= $basePrefix ?>assets/css/toast.css">

<link rel="stylesheet" href="<?= $basePrefix ?>assets/css/ai-assistant.css?v=<?= time() ?>">

<?php

if (php_sapi_name() !== 'cli') {

    $autoPub = __DIR__ . '/../blog/auto_publish_on_pageview.php';

    if (file_exists($autoPub)) {

        try {

            @include_once $autoPub;

        } catch (Throwable $e) {  /* ignore */

        }

    }

}

?>



<div class="mobile-header">

    <img src="<?= $logoUrl ?>" alt="ENTRIKS" class="logo-img">

    <button id="mobile-hamburger" class="mobile-hamburger" aria-label="Open Menu">

        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round"

            stroke-linejoin="round" class="icon" xmlns="http://www.w3.org/2000/svg">

            <path d="M4 6h16M4 12h16M4 18h16"></path>

        </svg>

    </button>

</div>



<div class="sidebar-overlay" id="sidebar-overlay"></div>

<div class="toast-container" id="toast-container"></div>



<aside class="<?= $sidebarClass ?>" id="main-sidebar">

    <div class="brand">

        <img src="<?= $logoUrl ?>" alt="ENTRIKS" class="logo-img">



        <button id="sidebar-toggle" aria-label="Toggle Menu">

            <svg id="desktop-toggle-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon desktop-only" style="display: none;">

                <path d="M9 3H3C1.89543 3 1 3.89543 1 5V19C1 20.1046 1.89543 21 3 21H9V3Z" fill="currentColor" stroke="none"></path>

                <path d="M21 3H3C1.89543 3 1 3.89543 1 5V19C1 20.1046 1.89543 21 3 21H21C22.1046 21 23 20.1046 23 19V5C23 3.89543 22.1046 3 21 3Z"></path>

                <path d="M9 3V21"></path>

            </svg>

            <svg id="mobile-close-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round"

                stroke-linejoin="round" class="icon icon-mobile-close mobile-only" xmlns="http://www.w3.org/2000/svg" width="18" height="18">

                <path d="M6 18L18 6M6 6l12 12"></path>

            </svg>

        </button>

    </div>



    <nav class="menu">

        <?php

        $userRole = $_SESSION['admin']['position'] ?? 'Editor';

        $isAdmin = ($_SESSION['admin']['role'] ?? 'admin') === 'admin';



        // Finalize role if admin role is set in session but position is different (fix for admin@entriks.com)

        if ($isAdmin)

            $userRole = 'Admin';



        // Role-based Access Logic

        $hasCmsAccess = $isAdmin || in_array($userRole, ['Editor', 'Content Manager']);

        $hasBlogAccess = $isAdmin || in_array($userRole, ['Content Manager', 'Author']);

        $hasCommentsAccess = $isAdmin;  // User said editor is for cms, author for blog. Comments/Analytics left for Admin (or specified later)

        $hasAnalyticsAccess = $isAdmin;

        $hasTeamAccess = $isAdmin;

        ?>



        <?php

        // Route to role-specific dashboard

        $dashboardFile = 'dashboard.php';

        if (!$isAdmin) {

            $pos = strtolower($userRole);

            if ($pos === 'author')

                $dashboardFile = 'dashboard-author.php';

            elseif ($pos === 'editor')

                $dashboardFile = 'dashboard-editor.php';

            elseif ($pos === 'content manager')

                $dashboardFile = 'dashboard-contentmanager.php';

        }

        ?>

        <a href="<?= ($sidebarVariant === 'dashboard') ? $dashboardFile : '../' . $dashboardFile ?>"

            class="menu-item <?= $activeMenu === 'dashboard' ? 'active' : '' ?>">

            <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="24" height="24">

                <path fill-rule="evenodd" d="M3 6a3 3 0 0 1 3-3h2.25a3 3 0 0 1 3 3v2.25a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V6Zm9.75 0a3 3 0 0 1 3-3H18a3 3 0 0 1 3 3v2.25a3 3 0 0 1-3 3h-2.25a3 3 0 0 1-3-3V6ZM3 15.75a3 3 0 0 1 3-3h2.25a3 3 0 0 1 3 3V18a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3v-2.25Zm9.75 0a3 3 0 0 1 3-3H18a3 3 0 0 1 3 3V18a3 3 0 0 1-3 3h-2.25a3 3 0 0 1-3-3v-2.25Z" clip-rule="evenodd" />

            </svg>

            <span><?= $lang['menu_dashboard'] ?></span>

        </a>



        <?php if ($hasCmsAccess || $hasBlogAccess): ?>

        <?php endif; ?>



        <?php if ($hasCmsAccess): ?>

        <a href="<?= ($sidebarVariant === 'dashboard') ? 'cms_manager.php' : '../cms_manager.php' ?>"

            class="menu-item <?= $activeMenu === 'cms_manager' ? 'active' : '' ?>" style="display:none">

            <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="24" height="24">

                <path d="M21.721 12.752a9.711 9.711 0 0 0-.945-5.003 12.754 12.754 0 0 1-4.339 2.708 18.991 18.991 0 0 1-.214 4.772 17.165 17.165 0 0 0 5.498-2.477ZM14.634 15.55a17.324 17.324 0 0 0 .332-4.647c-.952.227-1.945.347-2.966.347-1.021 0-2.014-.12-2.966-.347a17.515 17.515 0 0 0 .332 4.647 17.385 17.385 0 0 0 5.268 0ZM9.772 17.119a18.963 18.963 0 0 0 4.456 0A17.182 17.182 0 0 1 12 21.724a17.18 17.18 0 0 1-2.228-4.605ZM7.777 15.23a18.87 18.87 0 0 1-.214-4.774 12.753 12.753 0 0 1-4.34-2.708 9.711 9.711 0 0 0-.944 5.004 17.165 17.165 0 0 0 5.498 2.477ZM21.356 14.752a9.765 9.765 0 0 1-7.478 6.817 18.64 18.64 0 0 0 1.988-4.718 18.627 18.627 0 0 0 5.49-2.098ZM2.644 14.752c1.682.971 3.53 1.688 5.49 2.099a18.64 18.64 0 0 0 1.988 4.718 9.765 9.765 0 0 1-7.478-6.816ZM13.878 2.43a9.755 9.755 0 0 1 6.116 3.986 11.267 11.267 0 0 1-3.746 2.504 18.63 18.63 0 0 0-2.37-6.49ZM12 2.276a17.152 17.152 0 0 1 2.805 7.121c-.897.23-1.837.353-2.805.353-.968 0-1.908-.122-2.805-.353A17.151 17.151 0 0 1 12 2.276ZM10.122 2.43a18.629 18.629 0 0 0-2.37 6.49 11.266 11.266 0 0 1-3.746-2.504 9.754 9.754 0 0 1 6.116-3.985Z" />

            </svg>

            <span><?= $lang['menu_website_editor'] ?></span>

        </a>

        <?php endif; ?>



        <?php if ($hasBlogAccess): ?>

        <a href="<?= ($sidebarVariant === 'dashboard') ? 'blog/index.php' : 'index.php' ?>"

            class="menu-item <?= $activeMenu === 'blog' ? 'active' : '' ?>">

            <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="24" height="24">

                <path fill-rule="evenodd" d="M4.125 3C3.089 3 2.25 3.84 2.25 4.875V18a3 3 0 0 0 3 3h15a3 3 0 0 1-3-3V4.875C17.25 3.839 16.41 3 15.375 3H4.125ZM12 9.75a.75.75 0 0 0 0 1.5h1.5a.75.75 0 0 0 0-1.5H12Zm-.75-2.25a.75.75 0 0 1 .75-.75h1.5a.75.75 0 0 1 0 1.5H12a.75.75 0 0 1-.75-.75ZM6 12.75a.75.75 0 0 0 0 1.5h7.5a.75.75 0 0 0 0-1.5H6Zm-.75 3.75a.75.75 0 0 1 .75-.75h7.5a.75.75 0 0 1 0 1.5H6a.75.75 0 0 1-.75-.75ZM6 6.75a.75.75 0 0 0-.75.75v3c0 .414.336.75.75.75h3a.75.75 0 0 0 .75-.75v-3A.75.75 0 0 0 9 6.75H6Z" clip-rule="evenodd" />

                <path d="M18.75 6.75h1.875c.621 0 1.125.504 1.125 1.125V18a1.5 1.5 0 0 1-3 0V6.75Z" />

            </svg>

            <span><?= $lang['menu_blog_manager'] ?></span>

        </a>



        <a href="<?= ($sidebarVariant === 'dashboard') ? 'blog/archived.php' : 'archived.php' ?>" class="menu-item <?= $activeMenu === 'archived' ? 'active' : '' ?>">

            <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="24" height="24">

                <path d="M3.375 3C2.339 3 1.5 3.84 1.5 4.875v.75c0 1.036.84 1.875 1.875 1.875h17.25c1.035 0 1.875-.84 1.875-1.875v-.75C22.5 3.839 21.66 3 20.625 3H3.375Z" />

                <path fill-rule="evenodd" d="m3.087 9 .54 9.176A3 3 0 0 0 6.62 21h10.757a3 3 0 0 0 2.995-2.824L20.913 9H3.087Zm6.163 3.75A.75.75 0 0 1 10 12h4a.75.75 0 0 1 0 1.5h-4a.75.75 0 0 1-.75-.75Z" clip-rule="evenodd" />

            </svg>

            <span><?= $lang['menu_archived_posts'] ?></span>

        </a>

        <?php endif; ?>



        <a href="<?= ($sidebarVariant === 'dashboard') ? 'blog/comments.php' : 'comments.php' ?>"

            class="menu-item <?= $activeMenu === 'comments' ? 'active' : '' ?>"

            <?= !$hasCommentsAccess ? 'style="display:none"' : '' ?>>

            <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="24" height="24">

                <path fill-rule="evenodd" d="M12 2.25c-2.429 0-4.817.178-7.152.521C2.87 3.061 1.5 4.795 1.5 6.741v6.018c0 1.946 1.37 3.68 3.348 3.97.877.129 1.761.234 2.652.316V21a.75.75 0 0 0 1.28.53l4.184-4.183a.39.39 0 0 1 .266-.112c2.006-.05 3.982-.22 5.922-.506 1.978-.29 3.348-2.023 3.348-3.97V6.741c0-1.947-1.37-3.68-3.348-3.97A49.145 49.145 0 0 0 12 2.25ZM8.25 8.625a1.125 1.125 0 1 0 0 2.25 1.125 1.125 0 0 0 0-2.25Zm2.625 1.125a1.125 1.125 0 1 1 2.25 0 1.125 1.125 0 0 1-2.25 0Zm4.875-1.125a1.125 1.125 0 1 0 0 2.25 1.125 1.125 0 0 0 0-2.25Z" clip-rule="evenodd" />

            </svg>

            <span><?= $lang['menu_comments'] ?? 'Kommentare' ?></span>

        </a>



        <a href="<?= ($sidebarVariant === 'dashboard') ? 'analytics.php' : '../analytics.php' ?>"

            class="menu-item <?= $activeMenu === 'analytics' ? 'active' : '' ?>"

            <?= !$hasAnalyticsAccess ? 'style="display:none"' : '' ?>>

            <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="24" height="24">

                <path d="M18.375 2.25c-1.035 0-1.875.84-1.875 1.875v15.75c0 1.035.84 1.875 1.875 1.875h.75c1.035 0 1.875-.84 1.875-1.875V4.125c0-1.036-.84-1.875-1.875-1.875h-.75ZM9.75 8.625c0-1.036.84-1.875 1.875-1.875h.75c1.036 0 1.875.84 1.875 1.875v9.375c0 1.035-.84 1.875-1.875 1.875h-.75A1.875 1.875 0 0 1 9.75 18V8.625ZM3 13.125c0-1.036.84-1.875 1.875-1.875h.75c1.036 0 1.875.84 1.875 1.875v4.875c0 1.035-.84 1.875-1.875 1.875h-.75A1.875 1.875 0 0 1 3 18v-4.875Z" />

            </svg>

            <span><?= $lang['menu_analytics'] ?? 'Analytics' ?></span>

        </a>



        <a href="<?= ($sidebarVariant === 'dashboard') ? 'team_management.php' : '../team_management.php' ?>"

            class="menu-item <?= $activeMenu === 'team_management' ? 'active' : '' ?>"

            <?= !$hasTeamAccess ? 'style="display:none"' : '' ?>>

            <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="24" height="24">

                <path d="M4.5 6.375a4.125 4.125 0 1 1 8.25 0 4.125 4.125 0 0 1-8.25 0ZM14.25 8.625a3.375 3.375 0 1 1 6.75 0 3.375 3.375 0 0 1-6.75 0ZM1.5 19.125a7.125 7.125 0 0 1 14.25 0v.003l-.001.119a.75.75 0 0 1-.363.63 13.067 13.067 0 0 1-6.761 1.873c-2.472 0-4.786-.684-6.76-1.873a.75.75 0 0 1-.364-.63l-.001-.122ZM17.25 19.128l-.001.144a2.25 2.25 0 0 1-.233.96 10.088 10.088 0 0 0 5.06-1.01.75.75 0 0 0 .42-.643 4.875 4.875 0 0 0-6.957-4.611 8.586 8.586 0 0 1 1.71 5.157v.003Z" />

            </svg>

            <span><?= $lang['menu_team_management'] ?></span>

        </a>



    </nav>



    <div class="admin-box">

        <?php $adminEmail = $_SESSION['admin']['email'] ?? null; ?>

        <a href="<?= ($sidebarVariant === 'dashboard') ? 'logout.php' : '../logout.php' ?>" class="logout-btn">

            <svg fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24"

                stroke="currentColor" class="icon">

                <path d="M17 16l4-4m0 0l-4-4m4 4h-14m5 8H6a3 3 0 01-3-3V7a3 3 0 013-3h7" />

            </svg>

            <span><?= $lang['menu_logout'] ?></span>

        </a>

    </div>



</aside>



<script>

    // Restore sidebar state immediately to prevent flash of unstyled content

    (function() {

        try {

            const sidebar = document.getElementById('main-sidebar');

            const savedState = localStorage.getItem('sidebar-state');

            const vw = (window.visualViewport && window.visualViewport.width) || window.innerWidth || document.documentElement.clientWidth;

            const isMobile = vw <= 900;

            const isLocked = vw > 900 && vw < 1505;

            if (sidebar) {

                if (isLocked) {

                    sidebar.classList.add('collapsed');

                } else if (vw >= 1505 && savedState === 'collapsed') {

                    sidebar.classList.add('collapsed');

                }

                if (isMobile) {

                    sidebar.classList.remove('collapsed');

                }

            }

        } catch (e) { console.error(e); }

    })();

</script>



<script>

    document.addEventListener('DOMContentLoaded', function () {

        const sidebar = document.getElementById('main-sidebar');

        const toggleButton = document.getElementById('sidebar-toggle');

        const hamburgerButton = document.getElementById('mobile-hamburger');

        const overlay = document.getElementById('sidebar-overlay');



        const isMobile = () => {

            if (window.visualViewport && window.visualViewport.width) return window.visualViewport.width <= 900;

            return window.matchMedia('(max-width: 900px)').matches || document.documentElement.clientWidth <= 900;

        };



        const openMobileSidebar = () => {

            sidebar.classList.add('mobile-open');

            overlay.classList.add('active');

            document.body.style.overflow = 'hidden';

            toggleButton.setAttribute('aria-expanded', 'true');

        };



        const closeMobileSidebar = () => {

            sidebar.classList.remove('mobile-open');

            overlay.classList.remove('active');

            document.body.style.overflow = '';

            toggleButton.setAttribute('aria-expanded', 'false');

        };



        const toggleDesktopSidebar = () => {

            const vw = window.innerWidth;

            if (vw >= 1505) {

                sidebar.classList.toggle('collapsed');

                const isCollapsed = sidebar.classList.contains('collapsed');

                localStorage.setItem('sidebar-state', isCollapsed ? 'collapsed' : 'expanded');

            }

        };



        if (hamburgerButton) {

            hamburgerButton.addEventListener('click', openMobileSidebar);

        }



        if (toggleButton && sidebar) {

            toggleButton.addEventListener('click', function() {

                if (isMobile()) {

                    closeMobileSidebar();

                } else {

                    toggleDesktopSidebar();

                }

            });

            

            const vw = window.innerWidth;

            const isLocked = vw > 900 && vw < 1505;

            if (isLocked) {

                sidebar.classList.add('collapsed');

            } else if (vw >= 1505) {

                const savedState = localStorage.getItem('sidebar-state');

                if (savedState === 'collapsed') {

                    sidebar.classList.add('collapsed');

                }

            }

        }



        if (overlay) {

            overlay.addEventListener('click', closeMobileSidebar);

        }



        document.addEventListener('keydown', function (e) {

            if (e.key === 'Escape' && sidebar.classList.contains('mobile-open')) {

                closeMobileSidebar();

            }

        });



        window.addEventListener('resize', function() {

            const vw = window.innerWidth;

            const isLocked = vw > 900 && vw < 1505;

            if (!isMobile() && sidebar.classList.contains('mobile-open')) {

                closeMobileSidebar();

            }

            if (isLocked) {

                sidebar.classList.add('collapsed');

            } else if (vw >= 1505) {

                const savedState = localStorage.getItem('sidebar-state');

                if (savedState === 'collapsed') {

                    sidebar.classList.add('collapsed');

                } else {

                    sidebar.classList.remove('collapsed');

                }

            }

        });



        const dashboardMenu = document.getElementById('dashboard-menu');

        const dashboardSubmenu = document.getElementById('dashboard-submenu');

        const dashboardLink = dashboardMenu ? dashboardMenu.querySelector('.menu-link') : null;

        if (dashboardLink && dashboardSubmenu) {

            dashboardLink.addEventListener('click', function (e) {

                e.preventDefault();

                const isOpen = dashboardSubmenu.style.display === 'block';

                dashboardSubmenu.style.display = isOpen ? 'none' : 'block';

                dashboardMenu.classList.toggle('submenu-open', !isOpen);

            });

        }

    });



    // --- GLOBAL NOTIFICATION SYSTEM ---

    (function() {

        let lastPollTime = <?= class_exists('MongoDB\BSON\UTCDateTime') ? (string) new MongoDB\BSON\UTCDateTime() : 'Date.now()' ?>;

        const basePrefix = '<?= ($sidebarVariant === 'blog') ? '../' : '' ?>';

        

        if ("Notification" in window) {

            if (Notification.permission !== "granted" && Notification.permission !== "denied") {

                Notification.requestPermission();

            }

        }



        function showNotification(notification) {

            let title = '';

            let message = '';

            let type = notification.type_class || 'info';

            let iconSvg = '';



            const icons = {

                success: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm13.36-1.814a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z" clip-rule="evenodd" /></svg>`,

                error: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25Zm-1.72 6.97a.75.75 0 1 0-1.06 1.06L10.94 12l-1.72 1.72a.75.75 0 1 0 1.06 1.06L12 13.06l1.72 1.72a.75.75 0 1 0 1.06-1.06L13.06 12l1.72-1.72a.75.75 0 1 0-1.06-1.06L12 10.94l-1.72-1.72Z" clip-rule="evenodd" /></svg>`,

                warning: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M9.401 3.003c1.155-2 4.043-2 5.197 0l7.355 12.748c1.154 2-.29 4.5-2.599 4.5H4.645c-2.309 0-3.752-2.5-2.598-4.5L9.401 3.003ZM12 8.25a.75.75 0 0 1 .75.75v3.75a.75.75 0 0 1-1.5 0V9a.75.75 0 0 1 .75-.75Zm0 8.25a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z" clip-rule="evenodd" /></svg>`,

                info: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm8.706-1.442c1.146-.573 2.437.463 2.126 1.706l-.709 2.836c-.149.598.013 1.147.338 1.393a.75.75 0 1 1-.942 1.164c-.78-.609-1.048-1.618-.73-2.893l.707-2.829c.041-.164-.006-.285-.035-.31-.03-.027-.144-.059-.303.02a.75.75 0 0 1-.693-1.328ZM12 7a.75.75 0 1 1 0 1.5.75.75 0 0 1 0-1.5Z" clip-rule="evenodd" /></svg>`,

                featured: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M21.721 12.752a9.711 9.711 0 0 0-.945-5.003 12.754 12.754 0 0 1-4.339 2.708 18.991 18.991 0 0 1-.214 4.772 17.165 17.165 0 0 0 5.498-2.477ZM14.634 15.55a17.324 17.324 0 0 0 .332-4.647c-.952.227-1.945.347-2.966.347-1.021 0-2.014-.12-2.966-.347a17.515 17.515 0 0 0 .332 4.647 17.385 17.385 0 0 0 5.268 0ZM9.772 17.119a18.963 18.963 0 0 0 4.456 0A17.182 17.182 0 0 1 12 21.724a17.18 17.18 0 0 1-2.228-4.605ZM7.777 15.23a18.87 18.87 0 0 1-.214-4.774 12.753 12.753 0 0 1-4.34-2.708 9.711 9.711 0 0 0-.944 5.004 17.165 17.165 0 0 0 5.498 2.477ZM21.356 14.752a9.765 9.765 0 0 1-7.478 6.817 18.64 18.64 0 0 0 1.988-4.718 18.627 18.627 0 0 0 5.49-2.098ZM2.644 14.752c1.682.971 3.53 1.688 5.49 2.099a18.64 18.64 0 0 0 1.988 4.718 9.765 9.765 0 0 1-7.478-6.816ZM13.878 2.43a9.755 9.755 0 0 1 6.116 3.986 11.267 11.267 0 0 1-3.746 2.504 18.63 18.63 0 0 0-2.37-6.49ZM12 2.276a17.152 17.152 0 0 1 2.805 7.121c-.897.23-1.837.353-2.805.353-.968 0-1.908-.122-2.805-.353A17.151 17.151 0 0 1 12 2.276ZM10.122 2.43a18.629 18.629 0 0 0-2.37 6.49 11.266 11.266 0 0 1-3.746-2.504 9.754 9.754 0 0 1 6.116-3.985Z" /></svg>`

            };



            // Localized defaults from PHP

            const localized = {

                infoTitle: '<?= addslashes($lang['toast_title'] ?? 'Information') ?>',

                newView: '<?= addslashes($lang['tour_dash_stats_title'] ?? 'New View') ?>',

                newComment: '<?= addslashes($lang['msg_reply_sent'] ?? 'New Comment') ?>',

                newHome: '<?= addslashes($lang['tour_dash_overview_title'] ?? 'New Home Visit') ?>',

                errorTitle: '<?= addslashes($lang['login_error_incorrect'] ?? 'Error') ?>'

            };



            switch(notification.type) {

                case 'blog_view':

                    title = notification.title || localized.newView;

                    message = notification.item_title || 'Someone viewed a post.';

                    type = 'info';

                    break;

                case 'new_comment':

                    title = notification.title || localized.newComment;

                    message = notification.message || ('From: ' + (notification.item_title || 'a reader'));

                    type = 'success';

                    break;

                case 'main_view':

                    title = notification.title || localized.newHome;

                    message = notification.item_title || 'Someone visited the main page';

                    type = 'info';

                    break;

                case 'error':

                    title = notification.title || localized.errorTitle;

                    message = notification.message || 'Something went wrong.';

                    type = 'error';

                    break;

                default:

                    title = notification.title || localized.infoTitle;

                    message = notification.message || notification.item_title || '';

            }



            iconSvg = icons[type] || icons.info;



            const container = document.getElementById('toast-container');

            if (!container) return;



            // LIMIT: Ensure max 3 toasts on screen

            while (container.childElementCount >= 3) {

                 const first = container.firstElementChild;

                 if (first) first.remove();

            }



            const toast = document.createElement('div');

            toast.className = `premium-toast premium-toast--${type}`;

            

            toast.innerHTML = `

                <div class="toast-icon">${iconSvg}</div>

                <div class="toast-content">

                    <div class="toast-title">${title}</div>

                    <div class="toast-message">${message}</div>

                </div>

                <button class="toast-close" style="margin-left:auto; background:none; border:none; color:#9ca3af; cursor:pointer; padding:4px; display:flex; align-items:center;">

                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:16px;height:16px;">

                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />

                    </svg>

                </button>

            `;



            container.appendChild(toast);

            

            // Trigger animation

            setTimeout(() => toast.classList.add('active'), 10);



            const closeBtn = toast.querySelector('.toast-close');

            closeBtn.onclick = () => {

                toast.classList.remove('active');

                toast.classList.add('hide');

                setTimeout(() => toast.remove(), 400);

            };



            // Auto remove

            setTimeout(() => {

                if (toast.parentElement) {

                    toast.classList.remove('active');

                    toast.classList.add('hide');

                    setTimeout(() => toast.remove(), 400);

                }

            }, 5000);

        }



        // Expose globally so other scripts can use it

        window.showNotification = showNotification;



        function pollNotifications() {

            // STOP polling if we are inside the editor (edit=true)

            if (window.location.search.includes('edit=true')) return;



            fetch(`${basePrefix}get_notifications.php?since=${lastPollTime}`)

                .then(res => res.json())

                .then(data => {

                    if (data.success && data.notifications.length > 0) {

                        // LIMIT: Only show the 3 newest notifications from the batch

                        const newNotifications = data.notifications.slice(0, 3);

                        newNotifications.reverse().forEach(n => { // Reverse to show oldest of the batch first, so newest ends up last/on top/bottom depending on display

                            showNotification(n);

                        });

                        lastPollTime = data.server_time;

                    } else if (data.success) {

                        lastPollTime = data.server_time;

                    }

                })

                .catch(err => console.error('Poll error:', err));

        }



        // Poll every 5 seconds

        setInterval(pollNotifications, 5000);

        // Initial poll after short delay

        setTimeout(pollNotifications, 1000);

    })();

</script>



<!-- Onboarding System -->

<?php

// Fetch onboarding status

$onboardingStatus = ['welcome_seen' => false, 'viewed_pages' => []];

if (isset($db) && isset($_SESSION['admin']['email'])) {

    try {

        $adm = $db->admins->findOne(

            ['email' => $_SESSION['admin']['email']],

            ['projection' => ['onboarding' => 1]]

        );

        if ($adm && isset($adm->onboarding)) {

            $welcome = $adm->onboarding->welcome_seen ?? false;

            $pages = $adm->onboarding->viewed_pages ?? [];



            // robust BSON handling

            if ($pages instanceof \MongoDB\Model\BSONArray) {

                $pages = $pages->getArrayCopy();

            } else {

                $pages = (array) $pages;

            }



            $onboardingStatus = [

                'welcome_seen' => $welcome,

                'viewed_pages' => $pages

            ];

        }

    } catch (Exception $e) {  /* ignore */

    }

}

?>

<link rel="stylesheet" href="<?= $basePrefix ?>assets/css/onboarding.css?v=<?= time() ?>">

<script>

    window.adminOnboardingStatus = <?= json_encode($onboardingStatus) ?>;

    window.adminUserRole = "<?= $userRole ?>";

    window.onboardingApiUrl = "<?= $basePrefix ?>update_onboarding.php";

    window.onboardingAccess = <?= json_encode([
        'hasBlog' => $hasBlogAccess,
        'hasCms' => $hasCmsAccess,
        'hasComments' => $hasCommentsAccess,
        'hasAnalytics' => $hasAnalyticsAccess,
        'hasTeam' => $hasTeamAccess
    ]) ?>;

    window.onboardingTranslations = <?= json_encode(array_filter($lang, function ($key) {

    return strpos($key, 'tour_') === 0;

}, ARRAY_FILTER_USE_KEY)) ?>;

</script>

<script src="<?= $basePrefix ?>assets/js/onboarding.js?v=<?= time() ?>" defer></script>

