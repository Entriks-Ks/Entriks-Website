(function () {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initOnboarding);
    } else {
        initOnboarding();
    }

    // --- Helpers ---
    function getApiPath() {
        if (window.onboardingApiUrl) return window.onboardingApiUrl;
        if (window.location.pathname.includes('/blog/')) return '../update_onboarding.php';
        return 'update_onboarding.php';
    }

    function t(key, fallback) {
        if (fallback === undefined) fallback = '';
        return (window.onboardingTranslations && window.onboardingTranslations[key]) || fallback;
    }

    function getRole() {
        return (window.adminUserRole || 'admin').trim().toLowerCase();
    }

    function getUserId() {
        return window.adminUserId || 'default';
    }
    function lsKey(name) {
        return 'entriks_' + name + '_' + getUserId();
    }

    function getRoleAccess() {
        if (window.onboardingAccess) {
            return window.onboardingAccess;
        }
        var role = getRole();
        var isAdmin = role === 'admin';
        return {
            hasBlog: isAdmin || role === 'content manager' || role === 'author',
            hasCms: isAdmin || role === 'editor' || role === 'content manager',
            hasComments: isAdmin,
            hasAnalytics: isAdmin,
            hasTeam: isAdmin,
            hasArchived: isAdmin || role === 'content manager' || role === 'author'
        };
    }

    // --- Preloader ---
    function showPreloader() {
        var preloader = document.getElementById('preloader');
        if (preloader) {
            preloader.style.display = 'flex';
            preloader.style.zIndex = '1000001';
            preloader.classList.remove('fade-out');
            preloader.style.opacity = '1';
        }
    }

    function safeNavigate(url) {
        showPreloader();
        setTimeout(function () { window.location.href = url; }, 50);
    }

    function initFastTransitions() {
        document.addEventListener('click', function (e) {
            var link = e.target.closest('a');
            if (!link) return;
            var href = link.getAttribute('href');
            var target = link.getAttribute('target');
            if (!href || href.startsWith('#') || href.startsWith('javascript:') || target === '_blank') return;
            if (href.includes('logout.php')) return;
            showPreloader();
        });
    }

    // --- Init ---
    function initOnboarding() {
        initFastTransitions();
        if (!window.adminOnboardingStatus) return;

        var currentPath = window.location.pathname;
        var pageName = getPageName(currentPath);

        var serverStatus = window.adminOnboardingStatus || {};
        var serverFinished = serverStatus.finished === true;
        var serverWelcome = serverStatus.welcome_seen === true;
        var serverPages = Array.isArray(serverStatus.viewed_pages) ? serverStatus.viewed_pages : [];

        var localWelcome = localStorage.getItem(lsKey('welcome_seen')) === 'true';
        var localDismissed = localStorage.getItem(lsKey('tour_dismissed')) === 'true';
        var localViewedRaw = localStorage.getItem(lsKey('viewed_pages'));
        var localPages = JSON.parse(localViewedRaw || '[]');

        var wasReset = localStorage.getItem(lsKey('welcome_seen')) === null &&
            localStorage.getItem(lsKey('tour_dismissed')) === null;

        if (wasReset) {
            showWelcomeModal();
            return;
        }

        var merged = false;
        for (var i = 0; i < serverPages.length; i++) {
            if (!localPages.includes(serverPages[i])) {
                localPages.push(serverPages[i]);
                merged = true;
            }
        }
        if (merged) localStorage.setItem(lsKey('viewed_pages'), JSON.stringify(localPages));
        if (serverWelcome && !localWelcome) {
            localStorage.setItem(lsKey('welcome_seen'), 'true');
            localWelcome = true;
        }
        if (serverFinished && !localDismissed) {
            localStorage.setItem(lsKey('tour_dismissed'), 'true');
            localDismissed = true;
        }

        localPages = JSON.parse(localStorage.getItem(lsKey('viewed_pages')) || '[]');

        if (localDismissed) return;
        if (!localWelcome) {
            showWelcomeModal();
            return;
        }

        var seenLocally = localPages.includes(pageName);
        if (pageName && !seenLocally) {
            setTimeout(function () { startPageGuide(pageName); }, 500);
        }
    }

    // --- Page Detection ---
    function getPageName(path) {
        if (!path) return 'dashboard';
        var p = path.toLowerCase();
        if (p.includes('dashboard') || p.endsWith('/backend/')) return 'dashboard';
        if (p.includes('/blog/index') || p.includes('/blog/list_posts')) return 'blog_index';
        if (p.includes('/blog/create') || p.includes('/blog/edit') || p.includes('/blog/create_post')) return 'blog_editor';
        if (p.includes('/blog/comments')) return 'comments';
        if (p.includes('/blog/archived')) return 'archived';
        if (p.includes('cms_manager')) return 'cms_manager';
        if (p.includes('analytics')) return 'analytics';
        if (p.includes('team_management')) return 'team_management';
        if (p.includes('account-settings')) return 'settings';
        return 'dashboard';
    }

    // --- Welcome Modal ---
    function showWelcomeModal() {
        var overlay = document.createElement('div');
        overlay.className = 'onboarding-welcome-overlay';
        overlay.innerHTML =
            '<div class="onboarding-welcome-card">' +
            '<button class="onboarding-welcome-close" id="onboarding-welcome-close" aria-label="Close">&times;</button>' +
            '<div class="welcome-hero">' +
            '<div class="welcome-icon-large">' +
            '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">' +
            '<path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.499 5.258 50.55 50.55 0 00-2.658.813m-15.482 0A50.923 50.923 0 0112 13.489a50.92 50.92 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5" />' +
            '</svg>' +
            '</div>' +
            '</div>' +
            '<div class="onboarding-welcome-body">' +
            '<h2 class="onboarding-welcome-title">' + t('tour_welcome_title', "Hi! I'm your Entriks Tutor.") + '</h2>' +
            '<p class="welcome-desc">' + t('tour_welcome_text', "I'm here to personally guide you through your new dashboard. We'll cover everything from creating content to securing your account. It takes just 2 minutes to become an expert — shall we begin?") + '</p>' +
            '<button class="onboarding-welcome-btn" id="onboarding-get-started">' + t('tour_welcome_btn', 'Start Masterclass') + '</button>' +
            '<div style="margin-top:16px;">' +
            '<button id="onboarding-skip-link" class="onboarding-btn-link">' + t('tour_welcome_skip', 'Skip for now') + '</button>' +
            '</div>' +
            '</div>' +
            '</div>';

        document.body.appendChild(overlay);
        requestAnimationFrame(function () { overlay.classList.add('active'); });

        document.getElementById('onboarding-get-started').onclick = function () {
            overlay.classList.remove('active');
            setTimeout(function () { overlay.remove(); }, 500);

            localStorage.setItem(lsKey('welcome_seen'), 'true');
            localStorage.removeItem(lsKey('tour_dismissed'));
            localStorage.setItem(lsKey('viewed_pages'), '[]');
            localStorage.removeItem(lsKey('tour_progress'));

            fetch(getApiPath(), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'reset_tour' }),
                keepalive: true
            }).then(function () {
                fetch(getApiPath(), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'welcome_seen' }),
                    keepalive: true
                }).catch(function () { });
            }).catch(function () { });

            if (window.adminOnboardingStatus) {
                window.adminOnboardingStatus.viewed_pages = [];
                window.adminOnboardingStatus.finished = false;
            }

            var path = window.location.pathname;
            if (getPageName(path) === 'dashboard') {
                setTimeout(function () { startPageGuide('dashboard'); }, 600);
            } else {
                var dashFile = getDashboardFile();
                if (path.includes('/blog/')) {
                    safeNavigate('../' + dashFile);
                } else {
                    safeNavigate(dashFile);
                }
            }
        };

        var performDismissals = function () {
            overlay.classList.remove('active');
            setTimeout(function () { overlay.remove(); }, 400);
            localStorage.setItem(lsKey('welcome_seen'), 'true');
            localStorage.setItem(lsKey('tour_dismissed'), 'true');
            fetch(getApiPath(), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'welcome_seen' }),
                keepalive: true
            }).catch(function () { });
            fetch(getApiPath(), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'finish_all' }),
                keepalive: true
            }).catch(function () { });
        };

        document.getElementById('onboarding-welcome-close').onclick = performDismissals;
        document.getElementById('onboarding-skip-link').onclick = performDismissals;
    }

    function getDashboardFile() {
        var role = getRole();
        if (role === 'author') return 'dashboard-author.php';
        if (role === 'editor') return 'dashboard-editor.php';
        if (role === 'content manager') return 'dashboard-contentmanager.php';
        return 'dashboard.php';
    }

    // --- Reset / Restart ---
    window.resetEntriksTour = function () {
        localStorage.removeItem(lsKey('welcome_seen'));
        localStorage.removeItem(lsKey('viewed_pages'));
        localStorage.removeItem(lsKey('tour_progress'));
        localStorage.removeItem(lsKey('tour_dismissed'));
        alert('Tour has been reset! Reloading...');
        window.location.reload();
    };

    window.restartEntriksTour = function () {
        localStorage.removeItem(lsKey('welcome_seen'));
        localStorage.removeItem(lsKey('viewed_pages'));
        localStorage.removeItem(lsKey('tour_progress'));
        localStorage.removeItem(lsKey('tour_dismissed'));
        fetch(getApiPath(), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'reset_tour' }),
            keepalive: true
        }).catch(function () { });
        window.location.reload();
    };

    // --- Tour Engine ---
    var currentTourSteps = [];
    var currentStepIndex = 0;
    var backdropElement = null;
    var cardElement = null;

    function startPageGuide(page) {
        currentTourSteps = getStepsForPage(page);
        if (!currentTourSteps.length) return;

        var savedProgress = JSON.parse(localStorage.getItem(lsKey('tour_progress')) || '{}');
        var savedIndex = savedProgress[page] || 0;
        currentStepIndex = (savedIndex < currentTourSteps.length) ? savedIndex : 0;

        createTourElements();
        showStep(currentStepIndex);
    }

    function createTourElements() {
        document.querySelectorAll('.onboarding-backdrop, .onboarding-card').forEach(function (e) { e.remove(); });

        backdropElement = document.createElement('div');
        backdropElement.className = 'onboarding-backdrop';
        document.body.appendChild(backdropElement);
        requestAnimationFrame(function () { backdropElement.classList.add('active'); });

        cardElement = document.createElement('div');
        cardElement.className = 'onboarding-card';
        document.body.appendChild(cardElement);
    }

    function endTour(page) {
        if (backdropElement) {
            backdropElement.classList.remove('active');
            setTimeout(function () { if (backdropElement && backdropElement.parentElement) backdropElement.remove(); }, 300);
        }
        if (cardElement) {
            cardElement.classList.remove('active');
            setTimeout(function () { if (cardElement && cardElement.parentElement) cardElement.remove(); }, 300);
        }
        document.querySelectorAll('.onboarding-spotlight-element').forEach(function (el) {
            el.classList.remove('onboarding-spotlight-element');
        });

        updateStatus('complete_page', page);
        var savedProgress = JSON.parse(localStorage.getItem(lsKey('tour_progress')) || '{}');
        delete savedProgress[page];
        localStorage.setItem(lsKey('tour_progress'), JSON.stringify(savedProgress));
    }

    function showStep(index) {
        var step = currentTourSteps[index];
        if (!step) return;

        if (step.beforeShowPromise) {
            step.beforeShowPromise().then(function () {
                renderStep(index);
            });
        } else {
            renderStep(index);
        }
    }

    function renderStep(index) {
        var step = currentTourSteps[index];
        if (!step) return;

        if (index > 0) {
            var prevStep = currentTourSteps[index - 1];
            if (prevStep && prevStep._targetEl && prevStep._advanceHandler) {
                prevStep._targetEl.removeEventListener('click', prevStep._advanceHandler);
            }
        }

        document.querySelectorAll('.onboarding-spotlight-element').forEach(function (el) {
            el.classList.remove('onboarding-spotlight-element');
        });

        var savedProgress = JSON.parse(localStorage.getItem(lsKey('tour_progress')) || '{}');
        savedProgress[getPageName(window.location.pathname)] = index;
        localStorage.setItem(lsKey('tour_progress'), JSON.stringify(savedProgress));

        var targetEl = null;
        if (step.selector) {
            if (typeof step.selector === 'function') {
                targetEl = step.selector();
            } else {
                targetEl = document.querySelector(step.selector);
            }

            if (targetEl && targetEl.classList) {
                targetEl.classList.add('onboarding-spotlight-element');
                targetEl.scrollIntoView({ behavior: 'smooth', block: 'center' });

                var advanceHandler = function () {
                    if (index < currentTourSteps.length - 1) {
                        setTimeout(function () { showStep(index + 1); }, 200);
                    }
                    targetEl.removeEventListener('click', advanceHandler);
                };
                targetEl.addEventListener('click', advanceHandler);
                step._advanceHandler = advanceHandler;
                step._targetEl = targetEl;
            }
        }

        var isLast = index === currentTourSteps.length - 1;
        var nextBtnText = step.ctaLabel || (isLast ? t('tour_btn_finish', 'Finish') : t('tour_btn_next', 'Next'));
        var backBtnText = t('tour_btn_back', 'Back');
        var skipBtnText = t('tour_btn_skip', 'Skip');

        cardElement.innerHTML =
            '<div class="onboarding-header">' +
            '<div class="onboarding-title">' + step.title + '</div>' +
            '<button class="onboarding-close" id="tour-close-btn" aria-label="Close tour">&times;</button>' +
            '</div>' +
            '<div class="onboarding-body">' +
            '<p style="margin:0 0 16px 0;">' + step.text + '</p>' +
            (step.highlights ? step.highlights.map(function (h) {
                return '<div class="onboarding-highlight-item">' +
                    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="onboarding-icon">' +
                    '<path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />' +
                    '</svg>' +
                    '<span>' + h + '</span>' +
                    '</div>';
            }).join('') : '') +
            '<div class="onboarding-progress-container" style="margin-top:20px;">' +
            '<div class="onboarding-progress-text">' + (index + 1) + ' / ' + currentTourSteps.length + '</div>' +
            '<div class="onboarding-progress-bar">' +
            currentTourSteps.map(function (_, i) {
                return '<div class="progress-segment ' + (i <= index ? 'active' : '') + '"></div>';
            }).join('') +
            '</div>' +
            '</div>' +
            '</div>' +
            '<div class="onboarding-footer">' +
            (!isLast
                ? '<button class="onboarding-btn-link" id="tour-skip-btn">' + skipBtnText + '</button>'
                : '<div></div>') +
            '<div class="onboarding-actions-group">' +
            (index > 0
                ? '<button class="onboarding-btn secondary" id="tour-back-btn">' + backBtnText + '</button>'
                : '') +
            '<button class="onboarding-btn primary" id="tour-next-btn"><span>' + nextBtnText + '</span></button>' +
            '</div>' +
            '</div>';

        requestAnimationFrame(function () {
            cardElement.classList.add('active');
            placeCardNear(targetEl, step.preferredPosition);
        });

        var backBtn = document.getElementById('tour-back-btn');
        if (backBtn) backBtn.onclick = function () { showStep(index - 1); };

        var finishEntireTour = function () {
            if (window._tourKeyHandler) document.removeEventListener('keydown', window._tourKeyHandler);
            var page = getPageName(window.location.pathname);

            if (backdropElement) {
                backdropElement.classList.remove('active');
                setTimeout(function () { if (backdropElement && backdropElement.parentElement) backdropElement.remove(); }, 300);
            }
            if (cardElement) {
                cardElement.classList.remove('active');
                setTimeout(function () { if (cardElement && cardElement.parentElement) cardElement.remove(); }, 300);
            }
            document.querySelectorAll('.onboarding-spotlight-element').forEach(function (el) {
                el.classList.remove('onboarding-spotlight-element');
            });

            localStorage.setItem(lsKey('tour_dismissed'), 'true');
            fetch(getApiPath(), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'finish_all' }),
                keepalive: true
            }).catch(function () { });
        };

        document.getElementById('tour-next-btn').onclick = function () {
            var pageName = getPageName(window.location.pathname);

            if (isLast) {
                endTour(pageName);
                if (step.ctaAction) {
                    step.ctaAction();
                } else {
                    showCompletionCelebration(pageName);
                }
            } else if (step.ctaAction) {
                endTour(pageName);
                step.ctaAction();
            } else {
                showStep(index + 1);
            }
        };

        document.getElementById('tour-close-btn').onclick = function () {
            finishEntireTour();
        };
        var skipBtn = document.getElementById('tour-skip-btn');
        if (skipBtn) {
            skipBtn.onclick = function () {
                finishEntireTour();
            };
        }

        var keyHandler = function (e) {
            if (!cardElement || !cardElement.classList.contains('active')) return;
            if (e.key === 'ArrowRight' || e.key === 'Enter') {
                var nextBtn2 = document.getElementById('tour-next-btn');
                if (nextBtn2) nextBtn2.click();
            } else if (e.key === 'ArrowLeft') {
                if (index > 0) showStep(index - 1);
            } else if (e.key === 'Escape') {
                endTourSilent(getPageName(window.location.pathname));
            }
        };
        if (window._tourKeyHandler) document.removeEventListener('keydown', window._tourKeyHandler);
        window._tourKeyHandler = keyHandler;
        document.addEventListener('keydown', keyHandler);
    }

    function endTourSilent(page) {
        if (window._tourKeyHandler) document.removeEventListener('keydown', window._tourKeyHandler);
        endTour(page);
    }

    // --- Completion Celebration ---
    function showCompletionCelebration(pageName) {
        var access = getRoleAccess();
        var nextPage = getNextTourPage(pageName, access);

        var overlay = document.createElement('div');
        overlay.className = 'onboarding-welcome-overlay';

        var bodyContent = '';
        if (nextPage) {
            bodyContent =
                '<h2 class="onboarding-welcome-title">' + t('tour_complete_title', 'Page Tour Complete!') + '</h2>' +
                '<p class="welcome-desc">' + t('tour_complete_text', 'Great job! You\'ve mastered this section. Ready to explore the next one?') + '</p>' +
                '<button class="onboarding-welcome-btn" id="tour-goto-next">' + t('tour_complete_next', 'Continue Tour') + '</button>' +
                '<div style="margin-top:16px;">' +
                '<button id="tour-stay-here" class="onboarding-btn-link">' + t('tour_complete_stay', 'Stay here') + '</button>' +
                '</div>';
        } else {
            bodyContent =
                '<h2 class="onboarding-welcome-title">' + t('tour_all_done_title', 'You\'re All Set!') + '</h2>' +
                '<p class="welcome-desc">' + t('tour_all_done_text', 'Congratulations! You\'ve completed the full tour. You\'re now ready to use Entriks like a pro.') + '</p>' +
                '<button class="onboarding-welcome-btn" id="tour-stay-here">' + t('tour_all_done_btn', 'Let\'s Go!') + '</button>';

            localStorage.setItem(lsKey('tour_dismissed'), 'true');
            fetch(getApiPath(), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'finish_all' }),
                keepalive: true
            }).catch(function () { });
        }

        overlay.innerHTML =
            '<div class="onboarding-welcome-card">' +
            '<div class="welcome-hero">' +
            '<div class="welcome-icon-large celebration">' +
            '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">' +
            '<path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" />' +
            '</svg>' +
            '</div>' +
            '</div>' +
            '<div class="onboarding-welcome-body">' + bodyContent + '</div>' +
            '</div>';

        document.body.appendChild(overlay);
        requestAnimationFrame(function () { overlay.classList.add('active'); });

        var stayBtn = document.getElementById('tour-stay-here');
        if (stayBtn) {
            stayBtn.onclick = function () {
                overlay.classList.remove('active');
                setTimeout(function () { overlay.remove(); }, 400);
            };
        }

        var nextBtn = document.getElementById('tour-goto-next');
        if (nextBtn) {
            nextBtn.onclick = function () {
                overlay.classList.remove('active');
                setTimeout(function () { overlay.remove(); }, 400);
                navigateToPage(nextPage);
            };
        }
    }

    function getNextTourPage(currentPage, access) {
        var flow = ['dashboard'];
        if (access.hasBlog) flow.push('blog_index', 'blog_editor');
        if (access.hasArchived) flow.push('archived');
        if (access.hasComments) flow.push('comments');
        if (access.hasCms) flow.push('cms_manager');
        if (access.hasAnalytics) flow.push('analytics');
        if (access.hasTeam) flow.push('team_management');
        flow.push('settings');

        var viewedPages = JSON.parse(localStorage.getItem(lsKey('viewed_pages')) || '[]');
        var currentIdx = flow.indexOf(currentPage);

        for (var i = currentIdx + 1; i < flow.length; i++) {
            if (!viewedPages.includes(flow[i])) {
                return flow[i];
            }
        }
        return null;
    }

    function navigateToPage(page) {
        var inBlog = window.location.pathname.includes('/blog/');
        var prefix = inBlog ? '../' : '';
        var blogPrefix = inBlog ? '' : 'blog/';

        var urls = {
            'dashboard': prefix + getDashboardFile(),
            'blog_index': prefix + blogPrefix + 'index.php',
            'blog_editor': prefix + blogPrefix + 'create.php',
            'archived': prefix + blogPrefix + 'archived.php',
            'comments': prefix + blogPrefix + 'comments.php',
            'cms_manager': prefix + 'cms_manager.php',
            'analytics': prefix + 'analytics.php',
            'team_management': prefix + 'team_management.php',
            'settings': prefix + 'account-settings.php'
        };

        safeNavigate(urls[page] || prefix + getDashboardFile());
    }

    // --- Card Positioning ---
    function placeCardNear(targetEl, preferredPosition) {
        var card = cardElement;
        if (!card) return;

        if (!targetEl || typeof targetEl.getBoundingClientRect !== 'function') {
            card.style.top = '50%';
            card.style.left = '50%';
            card.style.transform = 'translate(-50%, -50%)';
            return;
        }

        var rect = targetEl.getBoundingClientRect();
        if (rect.width === 0 && rect.height === 0) {
            card.style.top = '50%';
            card.style.left = '50%';
            card.style.transform = 'translate(-50%, -50%)';
            return;
        }
        var cardRect = card.getBoundingClientRect();
        var margin = 16;
        var vw = window.innerWidth;
        var vh = window.innerHeight;
        var top, left;

        if (preferredPosition === 'top') {
            top = rect.top - cardRect.height - margin;
            left = rect.left + (rect.width / 2) - (cardRect.width / 2);
        } else {
            top = rect.top + (rect.height / 2) - (cardRect.height / 2);
            left = rect.right + margin;
            if (left + cardRect.width > vw - 20) left = rect.left - cardRect.width - margin;
            if (left < 20) {
                left = Math.max(20, rect.left);
                top = rect.bottom + margin;
            }
        }

        if (left < 20) left = 20;
        if (left + cardRect.width > vw - 20) left = vw - cardRect.width - 20;
        if (top < 20) top = 20;
        if (top + cardRect.height > vh - 20) top = vh - cardRect.height - 20;

        card.style.top = (top + window.scrollY) + 'px';
        card.style.left = (left + window.scrollX) + 'px';
        card.style.transform = 'none';
        card.style.margin = '0';
    }

    // --- Status Updater ---
    function updateStatus(action, value) {
        if (action === 'welcome_seen') {
            localStorage.setItem(lsKey('welcome_seen'), 'true');
        } else if (action === 'complete_page') {
            var pages = JSON.parse(localStorage.getItem(lsKey('viewed_pages')) || '[]');
            if (!pages.includes(value)) {
                pages.push(value);
                localStorage.setItem(lsKey('viewed_pages'), JSON.stringify(pages));
            }
        }

        fetch(getApiPath(), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: action, value: value }),
            keepalive: true
        }).catch(function (e) { console.error('Guide update failed', e); });

        if (window.adminOnboardingStatus) {
            if (action === 'welcome_seen') window.adminOnboardingStatus.welcome_seen = true;
            if (action === 'complete_page') {
                if (!window.adminOnboardingStatus.viewed_pages) window.adminOnboardingStatus.viewed_pages = [];
                window.adminOnboardingStatus.viewed_pages.push(value);
            }
        }
    }

    function getStepsForPage(page) {
        var role = getRole();
        var access = getRoleAccess();

        if (page === 'dashboard') return getDashboardSteps(role, access);
        if (page === 'blog_index') return getBlogIndexSteps(access);
        if (page === 'blog_editor') return getBlogEditorSteps(access);
        if (page === 'archived') return getArchivedSteps(access);
        if (page === 'comments') return getCommentsSteps(access);
        if (page === 'cms_manager') return getCmsSteps(access);
        if (page === 'analytics') return getAnalyticsSteps(access);
        if (page === 'team_management') return getTeamSteps(access);
        if (page === 'settings') return getSettingsSteps();
        return [];
    }

    // ------- DASHBOARD -------
    function getDashboardSteps(role, access) {
        var steps = [];

        steps.push({
            title: t('tour_dash_overview_title', 'Your Command Center'),
            text: t('tour_dash_overview_text', "This dashboard is your mission control. Get a bird's-eye view of your entire operation."),
            selector: null
        });

        steps.push({
            title: t('tour_dash_stats_title', 'Pulse Check'),
            text: t('tour_dash_stats_text', 'Instantly see your key performance metrics at a glance.'),
            selector: '#tour-stats-row'
        });

        if (role === 'admin') {
            steps.push({
                title: t('tour_dash_alerts_title', 'System Health'),
                text: t('tour_dash_alerts_text', 'Stay ahead of issues. Critical system notifications appear here.'),
                selector: '#tour-system-alerts'
            });
            steps.push({
                title: t('tour_dash_charts_title', 'Growth Trajectory'),
                text: t('tour_dash_charts_text', 'Visualize your traffic trends and see the impact of your campaigns.'),
                selector: '#tour-traffic-chart',
                preferredPosition: 'top'
            });
            steps.push({
                title: t('tour_dash_scheduled_title', 'Future Content'),
                text: t('tour_dash_scheduled_text', "See what's queued up. Your content strategy is visualized here."),
                selector: '#tour-scheduled-posts'
            });
        }

        if (role === 'content manager' || role === 'editor') {
            steps.push({
                title: t('tour_dash_charts_title', 'Growth Trajectory'),
                text: t('tour_dash_charts_text', 'Visualize your traffic trends and see the impact of your campaigns.'),
                selector: '#tour-traffic-chart',
                preferredPosition: 'top'
            });
        }

        if (role !== 'admin' && (role === 'author' || role === 'content manager' || role === 'editor')) {
            steps.push({
                title: t('tour_dash_recent_title', 'Recent Posts'),
                text: t('tour_dash_recent_text', 'Quickly see your latest content. Click any post to edit it.'),
                selector: '#tour-recent-posts'
            });
        }

        if (role === 'author') {
            steps.push({
                title: t('tour_dash_mini_chart_title', 'Your Views'),
                text: t('tour_dash_mini_chart_text', 'Track the engagement on your posts over the last 7 days.'),
                selector: '#tour-mini-chart'
            });
        }

        if (role === 'content manager') {
            steps.push({
                title: t('tour_dash_scheduled_title', 'Future Content'),
                text: t('tour_dash_scheduled_text', "See what's queued up. Your content strategy is visualized here."),
                selector: '#tour-scheduled-posts'
            });
        }

        if (access.hasBlog) {
            steps.push({
                title: t('tour_dash_cta_blog_title', 'Manage Blog'),
                text: t('tour_dash_cta_blog_text', 'Head to the blog manager to write and publish your next piece of content.'),
                selector: null,
                ctaLabel: t('tour_dash_cta_btn', 'Manage Blog'),
                ctaAction: function () { safeNavigate('blog/index.php'); }
            });
        } else if (access.hasCms) {
            steps.push({
                title: t('tour_dash_cta_title', 'Start Editing'),
                text: t('tour_dash_cta_text_cms', 'Ready to update your website? Head to the Website Editor.'),
                selector: null,
                ctaLabel: t('tour_btn_continue_cms', 'Website Editor'),
                ctaAction: function () { safeNavigate('cms_manager.php'); }
            });
        } else {
            steps.push({
                title: t('tour_dash_cta_title', 'Explore Settings'),
                text: t('tour_dash_cta_text_settings', 'Check out your account settings to make sure everything is configured.'),
                selector: null,
                ctaLabel: t('tour_settings_continue', 'Go to Settings'),
                ctaAction: function () { safeNavigate('account-settings.php'); }
            });
        }

        return steps;
    }

    // ------- BLOG INDEX -------
    function getBlogIndexSteps(access) {
        if (!access.hasBlog) return [];
        return [
            {
                title: t('tour_blog_index_title', 'Content Hub'),
                text: t('tour_blog_index_text', 'Welcome to your content library. Manage, filter, and organize all your articles.'),
                selector: null
            },
            {
                title: t('tour_blog_status_opt_title', 'Publish or Archive'),
                text: t('tour_blog_status_opt_text', 'Click here to change a post status. Publish it or archive it.'),
                selector: '#tour-status-dropdown'
            },
            {
                title: t('tour_blog_featured_title', 'Go Global'),
                text: t('tour_blog_featured_text', 'Click the Earth icon to push this post to the main page. Tip: You can only feature two blogs at a time.'),
                selector: '#tour-featured-btn'
            },
            {
                title: t('step_archive_title', 'Archive Power'),
                text: t('step_archive_desc', 'Move outdated posts out of sight without permanently deleting them.'),
                selector: '#tour-archive-btn'
            },
            {
                title: t('tour_blog_add_title', 'Create New Story'),
                text: t('tour_blog_add_text', 'Ready to write? Click here to start drafting your next story.'),
                selector: '.add-btn',
                ctaLabel: t('tour_blog_add_btn', 'Start Writing'),
                ctaAction: function () { safeNavigate('create.php'); }
            }
        ];
    }

    // ------- BLOG EDITOR -------
    function getBlogEditorSteps(access) {
        if (!access.hasBlog) return [];
        var steps = [
            {
                title: t('tour_edit_page_section_title', 'Choose Your Page'),
                text: t('tour_edit_page_section_text', 'Select which page this post will appear on. Talent Hub posts show on the Talent Hub page, Banking posts on the Banking page, etc.'),
                selector: 'select[name="blog_type"]'
            },
            {
                title: t('tour_edit_title_title', 'Headline Hero'),
                text: t('tour_edit_title_text', 'Craft a compelling headline — the first thing your readers see.'),
                selector: '#title'
            },
            {
                title: t('tour_edit_tags_title', 'Smart Tagging'),
                text: t('tour_edit_tags_text', 'Boost discoverability with relevant tags. Type and hit Enter.'),
                selector: '#tagContainer'
            },
            {
                title: t('tour_edit_image_title', 'Visual Impact'),
                text: t('tour_edit_image_text', 'Upload a cover image to grab attention in social feeds and search results.'),
                selector: '#tour-image-upload'
            },
            {
                title: t('tour_edit_palette_title', 'Building Blocks'),
                text: t('tour_edit_palette_text', 'Drag and drop elements — text, images, quotes — to build engaging layouts.'),
                selector: '.block-palette'
            },
            {
                title: t('tour_edit_ai_title', 'AI Blogging Assistant'),
                text: t('tour_edit_ai_text', 'Need inspiration or a full draft? Just give our AI a prompt or some rough notes, and it will generate a complete, professional blog post for you in seconds!'),
                selector: '#aiFloatingBtn',
                preferredPosition: 'top',
                beforeShowPromise: function () {
                    return new Promise(function (resolve) {
                        const panel = document.querySelector('.ai-chat-panel');
                        const btn = document.getElementById('aiFloatingBtn');
                        if (panel && panel.classList.contains('open') && btn) {
                            btn.click();
                        }
                        setTimeout(resolve, 300);
                    });
                }
            },
            {
                title: t('tour_edit_ai_input_title', 'Tell the AI what you want'),
                text: t('tour_edit_ai_input_text', 'This is where the magic happens. Type a topic or some notes here, and the AI will draft a complete blog post for you instantly!'),
                selector: '#aiInput',
                preferredPosition: 'top',
                beforeShowPromise: function () {
                    return new Promise(function (resolve) {
                        const btn = document.getElementById('aiFloatingBtn');
                        const panel = document.querySelector('.ai-chat-panel');
                        if (btn && panel && !panel.classList.contains('open')) {
                            btn.click();
                        }
                        setTimeout(resolve, 400);
                    });
                }
            },
            {
                title: t('step_preview_title', 'Preview Mode'),
                text: t('step_preview_desc', 'Visualize your post as readers will see it before publishing.'),
                selector: '.btn-preview',
                beforeShowPromise: function () {
                    return new Promise(function (resolve) {
                        const panel = document.querySelector('.ai-chat-panel');
                        const btn = document.getElementById('aiFloatingBtn');
                        if (panel && panel.classList.contains('open') && btn) {
                            btn.click();
                        }
                        setTimeout(resolve, 300);
                    });
                }
            }
        ];

        if (access.hasArchived) {
            steps.push({
                title: t('tour_edit_publish_title', 'Launch It'),
                text: t('tour_edit_publish_text', 'Hit Publish to go live, or schedule it for the perfect time.'),
                selector: '.btn-publish',
                ctaLabel: t('tour_btn_continue', 'See Archives'),
                ctaAction: function () { safeNavigate('archived.php'); }
            });
        } else {
            steps.push({
                title: t('tour_edit_publish_title', 'Launch It'),
                text: t('tour_edit_publish_text', 'Hit Publish to go live, or schedule it for the perfect time.'),
                selector: '.btn-publish'
            });
        }
        return steps;
    }

    // ------- ARCHIVED -------
    function getArchivedSteps(access) {
        if (!access.hasArchived) return [];
        var nextUrl = access.hasComments ? 'blog/comments.php' : (access.hasCms ? 'cms_manager.php' : 'account-settings.php');
        var nextLabel = access.hasComments ? t('tour_btn_continue_comments', 'Manage Comments') : (access.hasCms ? t('tour_btn_continue_cms', 'Website Editor') : t('tour_settings_continue', 'Go to Settings'));

        return [
            {
                title: t('tour_archived_grid_title', 'The Archive Vault'),
                text: t('tour_archived_grid_text', 'Securely stored posts that are no longer public but fully accessible.'),
                selector: '#tour-archive-grid',
                preferredPosition: 'top'
            },
            {
                title: t('tour_archived_status_title', 'Restore Control'),
                text: t('tour_archived_status_text', 'Breathe life back into old posts or permanently remove them.'),
                selector: '#tour-restore-dropdown',
                ctaLabel: nextLabel,
                ctaAction: function () { safeNavigate(nextUrl); }
            }
        ];
    }

    // ------- COMMENTS -------
    function getCommentsSteps(access) {
        if (!access.hasComments) return [];
        var nextUrl = access.hasCms ? 'cms_manager.php' : 'account-settings.php';
        var nextLabel = access.hasCms ? t('tour_btn_continue_cms', 'Website Editor') : t('tour_settings_continue', 'Go to Settings');

        return [
            {
                title: t('tour_comments_title', 'Community Hub'),
                text: t('tour_comments_text', 'Moderate and respond to reader feedback.'),
                selector: null
            },
            {
                title: t('tour_comments_filter_title', 'Focus Filter'),
                text: t('tour_comments_filter_text', 'Zero in on discussions from specific posts or categories.'),
                selector: '#filterBtn'
            },
            {
                title: t('tour_comments_table_title', 'Feedback Stream'),
                text: t('tour_comments_table_text', 'Scan comments, see who wrote them, and check their sentiment.'),
                selector: '#tour-comments-table',
                preferredPosition: 'top'
            },
            {
                title: t('tour_comments_bulk_title', 'Bulk Power'),
                text: t('tour_comments_bulk_text', 'Select multiple items to approve or delete them at once.'),
                selector: '#bulkActionsBar',
                ctaLabel: nextLabel,
                ctaAction: function () { safeNavigate(nextUrl); }
            }
        ];
    }

    // ------- CMS MANAGER -------
    function getCmsSteps(access) {
        if (!access.hasCms) return [];
        var nextUrl = access.hasAnalytics ? 'analytics.php' : 'account-settings.php';
        var nextLabel = access.hasAnalytics ? t('tour_btn_continue_analytics', 'View Analytics') : t('tour_settings_continue', 'Go to Settings');

        return [
            {
                title: t('tour_cms_title', 'Website Editor'),
                text: t('tour_cms_text', 'Manage your site structure. Edit the German and English versions of your homepage.'),
                selector: null
            },
            {
                title: t('tour_cms_de_title', 'German Homepage'),
                text: t('tour_cms_de_text', 'Open the visual editor for the German version of your website.'),
                selector: '#card-edit-de'
            },
            {
                title: t('tour_cms_en_title', 'English Homepage'),
                text: t('tour_cms_en_text', 'Open the visual editor for the English version of your website.'),
                selector: '#card-edit-en',
                ctaLabel: nextLabel,
                ctaAction: function () { safeNavigate(nextUrl); }
            }
        ];
    }

    // ------- ANALYTICS (Admin only) -------
    function getAnalyticsSteps(access) {
        if (!access.hasAnalytics) return [];
        return [
            {
                title: t('tour_analytics_title', 'Analytics Dashboard'),
                text: t('tour_analytics_text', 'Get a comprehensive view of your website performance with real-time data.'),
                selector: null
            },
            {
                title: t('tour_analytics_views_title', 'Traffic Overview'),
                text: t('tour_analytics_views_text', 'Monitor views, unique visitors, pages per session, and bounce rate.'),
                selector: '.ana-overview-grid'
            },
            {
                title: t('tour_analytics_widgets_title', 'Detailed Insights'),
                text: t('tour_analytics_widgets_text', 'Dive into traffic sources, device breakdowns, browsers, and language data.'),
                selector: '.ana-widgets-grid',
                preferredPosition: 'top',
                ctaLabel: t('tour_btn_continue_team', 'Team Management'),
                ctaAction: function () { safeNavigate('team_management.php'); }
            }
        ];
    }

    // ------- TEAM MANAGEMENT (Admin only) -------
    function getTeamSteps(access) {
        if (!access.hasTeam) return [];
        return [
            {
                title: t('tour_team_title', 'Team Management'),
                text: t('tour_team_text', 'View and manage all team members. Invite new people, assign roles, and control permissions.'),
                selector: null
            },
            {
                title: t('tour_team_grid_title', 'Your Team'),
                text: t('tour_team_grid_text', 'Each card shows a member with their role, status, and available actions.'),
                selector: '.team-grid'
            },
            {
                title: t('tour_team_invite_title', 'Invite Members'),
                text: t('tour_team_invite_text', 'Send an invitation to a new team member with their role and permissions.'),
                selector: '.invite-btn',
                ctaLabel: t('tour_settings_continue', 'Go to Settings'),
                ctaAction: function () { safeNavigate('account-settings.php'); }
            }
        ];
    }

    // ------- SETTINGS -------
    function getSettingsSteps() {
        return [
            {
                title: t('tour_settings_nav_title', 'Control Center'),
                text: t('tour_settings_nav_text', 'Everything from your profile to security settings lives here.'),
                selector: '#tour-settings-nav'
            },
            {
                title: t('tour_settings_title', 'Your Profile'),
                text: t('tour_settings_text', 'Keep your personal details and security settings up to date.'),
                selector: '#profile',
                ctaLabel: t('tour_btn_finish', 'Complete Tour'),
                ctaAction: function () {
                    endTour('settings');
                    showCompletionCelebration('settings');
                }
            }
        ];
    }

})();