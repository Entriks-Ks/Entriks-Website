<?php
// backend/partials/topbar.php
if (!isset($pageTitle)) {
    $pageTitle = 'Dashboard';  // Fallback
}

// Check if search is enabled
$searchEnabled = true;
try {
    if (isset($db)) {
        $settings = $db->settings->findOne(['type' => 'global_config']);
        if ($settings && isset($settings['dashboard_search_enabled'])) {
            $searchEnabled = $settings['dashboard_search_enabled'];
        }
    }
} catch (Exception $e) {
}
?>
<style>
    body.sidebar-expanded .topbar-center {
        flex: 0 0 auto !important;
        justify-content: flex-end !important;
        margin-right: 12px !important;
    }
    body.sidebar-expanded .global-search-container {
        max-width: 320px !important;
    }
    @media (min-width: 1025px) and (max-width: 1400px) {
        .topbar-left h1 {
            font-size: 18px !important;
        }
        body.sidebar-expanded .topbar-center {
            flex: 0 0 auto !important;
            justify-content: flex-end !important;
        }
        body.sidebar-collapsed .topbar-center {
            flex: 1 !important;
            justify-content: center !important;
        }
    }
    @media (min-width: 1024px) and (max-width: 1208px) {
        .topbar-center {
            flex: 0 0 auto !important;
            justify-content: flex-end !important;
            margin: 0 8px !important;
            transition: all 0.3s ease;
        }
        .topbar-center.search-active {
            flex: 1 !important;
        }
        .global-search-container {
            max-width: 40px !important;
            width: 40px !important;
            transition: all 0.3s ease;
        }
        .search-active .global-search-container {
            max-width: 300px !important;
            width: 100% !important;
        }
        .search-input-wrapper {
            width: 40px !important;
            height: 40px !important;
            background: #1a1a1a !important;
            border: 1px solid #333 !important;
            border-radius: 50% !important;
            transition: all 0.3s ease;
        }
        .search-active .search-input-wrapper {
            width: 100% !important;
            height: auto !important;
            border-radius: 99px !important;
        }
        .search-input-wrapper input {
            opacity: 0 !important;
            width: 40px !important;
            height: 40px !important;
            padding: 0 !important;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .search-active .search-input-wrapper input {
            opacity: 1 !important;
            width: 100% !important;
            height: auto !important;
            padding: 8px 12px 8px 40px !important;
            cursor: text;
        }
        .search-input-wrapper svg {
            pointer-events: none;
            position: absolute !important;
            left: 50% !important;
            top: 50% !important;
            transform: translate(-50%, -50%) !important;
        }
        .search-active .search-input-wrapper svg {
            left: 12px !important;
            top: 50% !important;
            transform: translateY(-50%) !important;
        }
        .search-kbd {
            display: none !important;
        }
        .topbar-right {
            flex: 0 0 auto !important;
        }
    }
    @media (max-width: 1023px) {
        .topbar {
            gap: 8px !important;
        }
        .topbar-left {
            flex: 1 !important;
            min-width: 0 !important;
        }
        .topbar-left h1 {
            font-size: 18px !important;
        }
        .topbar-center {
            flex: 1 !important;
            display: flex !important;
            justify-content: flex-end !important;
            margin: 0 8px !important;
        }
        .topbar-center.search-active {
            flex: 1 !important;
        }
        .topbar-right {
            flex: 0 0 auto !important;
            margin-left: 8px !important;
        }
        .global-search-container {
            max-width: 40px !important;
            width: 40px !important;
            height: 40px !important;
            transition: all 0.3s ease;
        }
        .search-active .global-search-container {
            max-width: 300px !important;
            width: 100% !important;
        }
        .search-input-wrapper {
            width: 40px !important;
            height: 40px !important;
            background: #1a1a1a !important;
            border: 1px solid #333 !important;
            border-radius: 50% !important;
            transition: all 0.3s ease;
        }
        .search-active .search-input-wrapper {
            width: 100% !important;
            height: auto !important;
            border-radius: 99px !important;
        }
        .search-input-wrapper input {
            opacity: 0 !important;
            width: 40px !important;
            height: 40px !important;
            padding: 0 !important;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .search-active .search-input-wrapper input {
            opacity: 1 !important;
            width: 100% !important;
            height: auto !important;
            padding: 8px 12px 8px 40px !important;
            cursor: text;
        }
        .search-input-wrapper svg {
            pointer-events: none;
            z-index: 1;
            position: absolute !important;
            left: 50% !important;
            top: 50% !important;
            transform: translate(-50%, -50%) !important;
        }
        .search-active .search-input-wrapper svg {
            left: 12px !important;
            top: 50% !important;
            transform: translateY(-50%) !important;
        }
        .search-kbd {
            display: none !important;
        }
        .topbar-left .wave-icon {
            display: flex !important;
        }
        .topbar-left h1 {
            font-size: 16px !important;
        }
        .welcome-desktop {
            display: none !important;
        }
        .welcome-mobile {
            display: none !important;
        }
    }
    @media (min-width: 1025px) {
        .welcome-desktop {
            display: inline !important;
        }
        .welcome-mobile {
            display: none !important;
        }
    }
    @media (max-width: 1024px) {
        .welcome-desktop {
            display: none !important;
        }
        .welcome-mobile {
            display: inline !important;
        }
    }
</style>
<header class="topbar" style="margin-bottom:32px; padding-bottom:24px; border-bottom:1px solid #1f2937; display: flex; justify-content: space-between; align-items: center; flex-wrap: nowrap;">
    <div class="topbar-left" style="flex: 1; min-width: 0;">
        <!-- Welcome Message -->
        <div style="display:flex; align-items:center; gap:12px;">
            <?php if (isset($showWelcomeMessage) && $showWelcomeMessage): ?>
                <div class="wave-icon" style="color:#49b9f2; display:flex; align-items:center;">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="28" height="28">
                        <path d="M10.5 1.875a1.125 1.125 0 0 1 2.25 0v8.219c.517.162 1.02.382 1.5.659V3.375a1.125 1.125 0 0 1 2.25 0v10.937a4.505 4.505 0 0 0-3.25 2.373 8.963 8.963 0 0 1 4-.935A.75.75 0 0 0 18 15v-2.266a3.368 3.368 0 0 1 .988-2.37 1.125 1.125 0 0 1 1.591 1.59 1.118 1.118 0 0 0-.329.79v3.006h-.005a6 6 0 0 1-1.752 4.007l-1.736 1.736a6 6 0 0 1-4.242 1.757H10.5a7.5 7.5 0 0 1-7.5-7.5V6.375a1.125 1.125 0 0 1 2.25 0v5.519c.46-.452.965-.832 1.5-1.141V3.375a1.125 1.125 0 0 1 2.25 0v6.526c.495-.1.997-.151 1.5-.151V1.875Z" />
                    </svg>
                </div>
                <h1 style="margin:0; font-size:20px; font-weight:700; color:#fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                    <span class="welcome-desktop">
                        <span class="welcome-text"><?= $lang['dash_welcome_back'] ?? 'Welcome back' ?>, </span><?= htmlspecialchars($adminDisplayName ?? 'Admin') ?>
                    </span>
                    <span class="welcome-mobile">Hi, <?= htmlspecialchars($adminDisplayName ?? 'Admin') ?></span>
                </h1>
            <?php else: ?>
                <h1 style="margin:0; font-size:20px; font-weight:700; color:#fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= $pageTitle ?></h1>
            <?php endif; ?>
        </div>
    </div>

    <!-- CENTER SEARCH BAR -->
    <?php if ($searchEnabled): ?>
    <div class="topbar-center" style="flex: 1; display: flex; justify-content: center; margin: 0 12px;">
        <div class="global-search-container" style="position: relative; width: 100%; max-width: 320px;">
            <div class="search-input-wrapper" style="position: relative; display: flex; align-items: center;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="18" height="18" style="position: absolute; left: 12px; color: #9ca3af; pointer-events: none;">
                    <path fill-rule="evenodd" d="M10.5 3.75a6.75 6.75 0 1 0 0 13.5 6.75 6.75 0 0 0 0-13.5ZM2.25 10.5a8.25 8.25 0 1 1 14.59 5.28l4.69 4.69a.75.75 0 1 1-1.06 1.06l-4.69-4.69A8.25 8.25 0 0 1 2.25 10.5Z" clip-rule="evenodd" />
                </svg>
                <kbd class="search-kbd" id="search-shortcut-hint" style="position: absolute; right: 12px; font-size: 11px; color: #6b7280; border: 1px solid #333; border-radius: 4px; padding: 2px 6px; pointer-events: none;">
                    Ctrl + K
                </kbd>
                <script>
                    // Detect OS and set appropriate keyboard shortcut
                    const isMac = navigator.platform.toUpperCase().indexOf('MAC') >= 0;
                    document.getElementById('search-shortcut-hint').textContent = isMac ? '⌘ + K' : 'Ctrl + K';
                </script>
                <input 
                    type="text" 
                    id="globalSearchInput" 
                    placeholder="<?= $lang['search_placeholder'] ?? 'Search...' ?>" 
                    autocomplete="off"
                    style="width: 100%; background: #1a1a1a; border: 1px solid #333; color: #fff; padding: 8px 12px 8px 40px; border-radius: 99px; outline: none; transition: all 0.2s;"
                    onfocus="if(window.innerWidth <= 1208) document.querySelector('.topbar-center').classList.add('search-active')"
                    onblur="if(window.innerWidth <= 1208 && !this.value) document.querySelector('.topbar-center').classList.remove('search-active')"
                >
            </div>
            
            <!-- DROPDOWN RESULTS -->
            <div id="globalSearchResults" class="search-results-dropdown" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: #1a1a1a; border: 1px solid #333; border-radius: 12px; margin-top: 8px; max-height: 400px; overflow-y: auto; z-index: 1000; box-shadow: 0 10px 25px rgba(0,0,0,0.5); scrollbar-width: thin; scrollbar-color: #333 #1a1a1a;">
                <!-- Results injected via JS -->
                 <div style="padding: 12px; text-align: center; color: #6b7280; font-size: 13px;"><?= $lang['search_type_to_search'] ?? 'Type to search...' ?></div>
            </div>
            <style>
                #globalSearchResults::-webkit-scrollbar {
                    width: 8px;
                }
                #globalSearchResults::-webkit-scrollbar-track {
                    background: #1a1a1a;
                }
                #globalSearchResults::-webkit-scrollbar-thumb {
                    background: #333;
                    border-radius: 4px;
                }
                #globalSearchResults::-webkit-scrollbar-thumb:hover {
                    background: #444;
                }
            </style>
        </div>
    </div>
    <?php endif; ?>

    <div class="topbar-right" style="flex: 1; display: flex; justify-content: flex-end; flex-shrink: 0; align-items: center; gap: 18px;">
        <!-- User Icon -->
        <?php $settingsUrl = (isset($basePrefix) ? $basePrefix : '') . 'account-settings.php'; ?>
        <a href="<?= $settingsUrl ?>" style="display:flex; align-items:center; text-decoration:none;">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#fff" width="20" height="20">
                <path fill-rule="evenodd"
                    d="M7.5 6a4.5 4.5 0 1 1 9 0 4.5 4.5 0 0 1-9 0ZM3.751 20.105a8.25 8.25 0 0 1 16.498 0 .75.75 0 0 1-.437.695A18.683 18.683 0 0 1 12 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 0 1-.437-.695Z"
                    clip-rule="evenodd" />
            </svg>
        </a>
    </div>
</header>

<script>
    window.searchTranslations = {
        noResults: <?= json_encode($lang['search_no_results'] ?? 'No results found.') ?>
    };
</script>

<div id="toast-container" style="position: fixed; top: 20px; right: 20px; z-index: 10000; display: flex; flex-direction: column; gap: 10px;"></div>

<script>
function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    if(!container) return;

    const toasts = container.children;
    if (toasts.length >= 3) {
        toasts[0].remove();
    }

    const toast = document.createElement('div');
    toast.className = `premium-toast premium-toast--${type} active`;
    toast.style.display = 'flex';
    toast.style.alignItems = 'center';
    toast.style.gap = '12px';
    toast.style.background = '#262525';
    toast.style.border = '1px solid rgba(255,255,255,0.1)';
    toast.style.padding = '16px 20px';
    toast.style.borderRadius = '12px';
    toast.style.boxShadow = '0 10px 40px rgba(0,0,0,0.3)';
    toast.style.minWidth = '300px';
    toast.style.animation = 'slideIn 0.3s cubic-bezier(0.16, 1, 0.3, 1)';
    toast.style.marginBottom = '10px';
    toast.style.color = '#fff';
    toast.style.zIndex = '10001';
    
    const color = type === 'success' ? '#10b981' : '#ef4444';
    
    toast.innerHTML = `
        <div style="width:32px; height:32px; border-radius:50%; background:${color}20; display:flex; align-items:center; justify-content:center; color:${color}; flex-shrink:0;">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:20px; height:20px;">
                ${type === 'success' 
                    ? '<path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />' 
                    : '<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 10-2 0 1 1 0 002 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />'}
            </svg>
        </div>
        <div>
            <div style="font-weight:600; font-size:14px; margin-bottom:2px;">${type === 'success' ? '<?= $lang['toast_success_title'] ?? 'Success' ?>' : '<?= $lang['toast_error_title'] ?? 'Error' ?>'}</div>
            <div style="font-size:13px; color:#9ca3af;">${message}</div>
        </div>
        <button onclick="this.closest('.premium-toast').remove()" style="margin-left:auto; background:none; border:none; color:#ffffff; cursor:pointer; padding:4px; display:flex; align-items:center; opacity: 0.8; transition: opacity 0.2s;">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:18px; height:18px;">
                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
            </svg>
        </button>
    `;

    container.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(-10px)';
        setTimeout(() => toast.remove(), 300);
    }, 5000);
}
</script>

<style>
@keyframes slideIn {
    from { opacity: 0; transform: translateX(20px); }
    to { opacity: 1; transform: translateX(0); }
}
</style>
