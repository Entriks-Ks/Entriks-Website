/**
 * Responsive Editor Device Detection
 * Disables CMS editing on screens < 768px and shows SaaS-style overlay
 */

(function () {
    'use strict';

    const BREAKPOINT = 768;
    let overlay = null;

    // Create overlay HTML
    function createOverlay() {
        const overlayHTML = `
            <div class="responsive-editor-overlay" id="responsiveEditorOverlay">
                <div class="responsive-editor-content">
                    <div class="responsive-editor-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <h2>Desktop erforderlich</h2>
                    <p>Der Blog-Editor ben&ouml;tigt f&uuml;r ein optimales Bearbeitungserlebnis einen gr&ouml;&szlig;eren Bildschirm. Bitte verwenden Sie einen Desktop-Computer oder ein Tablet (768px oder breiter).</p>
                    <div class="responsive-editor-actions">
                        <a href="index.php" class="responsive-editor-btn responsive-editor-btn-primary">
                            Zur&uuml;ck zum Blog
                        </a>
                    </div>
                </div>
            </div>
        `;

        const div = document.createElement('div');
        div.innerHTML = overlayHTML;
        document.body.appendChild(div.firstElementChild);
        overlay = document.getElementById('responsiveEditorOverlay');
    }

    // Check screen size and toggle overlay
    function checkScreenSize() {
        const width = window.innerWidth;

        if (width < BREAKPOINT) {
            // Small screen - show overlay and disable editing
            if (!overlay) {
                createOverlay();
            }
            overlay.classList.add('active');
            disableEditing();
        } else {
            // Large screen - hide overlay and enable editing
            if (overlay) {
                overlay.classList.remove('active');
            }
            enableEditing();
        }
    }

    // Disable all editing functionality
    function disableEditing() {
        // Disable contenteditable
        document.querySelectorAll('[contenteditable="true"]').forEach(el => {
            el.setAttribute('contenteditable', 'false');
            el.dataset.wasEditable = 'true';
        });

        // Disable input fields
        document.querySelectorAll('input:not([type="hidden"]), textarea, select').forEach(el => {
            if (!el.disabled) {
                el.disabled = true;
                el.dataset.wasEnabled = 'true';
            }
        });

        // Disable buttons except preview
        document.querySelectorAll('button:not(.btn-preview)').forEach(btn => {
            if (!btn.disabled) {
                btn.disabled = true;
                btn.dataset.wasEnabled = 'true';
            }
        });

        // Disable draggable
        document.querySelectorAll('[draggable="true"]').forEach(el => {
            el.setAttribute('draggable', 'false');
            el.dataset.wasDraggable = 'true';
        });

        // Disable file inputs
        document.querySelectorAll('input[type="file"]').forEach(el => {
            el.disabled = true;
            el.dataset.wasEnabled = 'true';
        });
    }

    // Re-enable all editing functionality
    function enableEditing() {
        // Re-enable contenteditable
        document.querySelectorAll('[data-was-editable="true"]').forEach(el => {
            el.setAttribute('contenteditable', 'true');
            delete el.dataset.wasEditable;
        });

        // Re-enable input fields
        document.querySelectorAll('[data-was-enabled="true"]').forEach(el => {
            el.disabled = false;
            delete el.dataset.wasEnabled;
        });

        // Re-enable draggable
        document.querySelectorAll('[data-was-draggable="true"]').forEach(el => {
            el.setAttribute('draggable', 'true');
            delete el.dataset.wasDraggable;
        });
    }

    // Enable preview-only mode
    function enablePreviewMode() {
        // Removed - not needed
    }

    // Show toast notification
    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${type === 'info' ? '#667eea' : '#10b981'};
            color: white;
            padding: 16px 24px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            z-index: 100000;
            max-width: 350px;
            font-size: 14px;
            line-height: 1.5;
            animation: slideInRight 0.3s ease;
        `;
        toast.textContent = message;
        document.body.appendChild(toast);

        setTimeout(() => {
            toast.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }

    // Add CSS animations
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideInRight {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);

    // Initialize on DOM ready
    function init() {
        checkScreenSize();

        // Listen for window resize with debounce
        let resizeTimer;
        window.addEventListener('resize', function () {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function () {
                checkScreenSize();

                // Show notification when resizing to desktop
                if (window.innerWidth >= BREAKPOINT) {
                    enableEditing();
                    const isDe = (window.entraLanguage === 'de') || document.documentElement.lang === 'de' || window.location.href.includes('-de');
                    const msg = isDe ? 'Bearbeiten aktiviert! Der Bildschirm ist nun breit genug.' : 'Editing enabled! Screen is now wide enough.';
                    showToast(msg, 'success');
                }
            }, 250);
        });
    }

    // Expose public API
    window.responsiveEditor = {
        enablePreviewMode: enablePreviewMode,
        checkScreenSize: checkScreenSize
    };

    // Auto-initialize
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
