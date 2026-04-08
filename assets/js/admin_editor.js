document.addEventListener('DOMContentLoaded', function () {

    if (!document.body.classList.contains('admin-editor-active')) return;
    if (window.self !== window.top) return;

    if (window.isInnerFrame) {
        document.addEventListener('click', function (e) {
            if (e.target.closest('a')) {
                e.preventDefault();
            }
        });
        return;
    }

    let editorDisabled = false;
    const MIN_WIDTH = 768;

    function checkScreenSize() {
        const width = window.innerWidth;
        if (width < MIN_WIDTH && !editorDisabled) {
            showMobileOverlay();
            editorDisabled = true;
        } else if (width >= MIN_WIDTH && editorDisabled) {
            hideMobileOverlay();
            editorDisabled = false;
        }
    }

    function showMobileOverlay() {
        const isGerman = window.location.pathname.includes('index.php') && !window.location.pathname.includes('index-en.php');
        const overlay = document.createElement('div');
        overlay.id = 'mobile-editor-overlay';
        overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:linear-gradient(135deg,#1a1a1a 0%,#2d2d2d 100%);z-index:999999;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:20px;';
        overlay.innerHTML = `
            <div style="text-align:center;max-width:400px;">
                <svg style="width:80px;height:80px;margin-bottom:24px;" viewBox="0 0 24 24" fill="none" stroke="#d225d7" stroke-width="1.5">
                    <rect x="3" y="3" width="18" height="18" rx="2"/>
                    <path d="M9 9h6M9 12h6M9 15h4"/>
                </svg>
                <h2 style="color:#fff;font-size:24px;margin:0 0 12px 0;font-weight:600;">${isGerman ? 'Editor nicht verfügbar' : 'Editor Not Available'}</h2>
                <p style="color:#999;font-size:14px;line-height:1.6;margin:0 0 32px 0;">${isGerman ? 'Der CMS-Editor benötigt eine Mindestbildschirmbreite von 768px. Bitte verwenden Sie ein größeres Gerät oder ändern Sie die Größe Ihres Browserfensters.' : 'The CMS editor requires a minimum screen width of 768px. Please use a larger device or resize your browser window.'}</p>
                <button id="preview-only-btn" style="background:#d225d7;color:#fff;border:none;padding:12px 32px;border-radius:6px;font-size:14px;font-weight:600;cursor:pointer;transition:all 0.2s;">
                    ${isGerman ? 'Nur Vorschau-Modus' : 'Preview Only Mode'}
                </button>
            </div>
        `;
        document.body.appendChild(overlay);
        document.getElementById('preview-only-btn').onclick = () => {
            window.location.href = window.location.pathname + '?preview=true';
        };
    }

    function hideMobileOverlay() {
        const overlay = document.getElementById('mobile-editor-overlay');
        if (overlay) overlay.remove();
    }

    checkScreenSize();
    window.addEventListener('resize', checkScreenSize);

    if (editorDisabled) return;

    const ACCENT_COLOR = '#d225d7';
    const PAGE_ID = window.editorPageId || window.location.pathname.split('/').pop().replace('.php', '') || 'index';

    let activeElement = null;
    let activeType = null;
    let activeTab = 'content';

    let isDirty = false;
    let originalState = {};
    let touchedKeys = new Set();
    let deletedStructuralKeys = new Set();

    const EDITOR_DEBUG = false;
    function debugLog() { if (EDITOR_DEBUG && console && console.log) console.log.apply(console, arguments); }

    const editorElements = document.querySelectorAll('#admin-image-upload, #admin-editor-toolbar, .admin-editor-toolbar');
    editorElements.forEach(el => el.remove());

    const originalBodyClasses = document.body.className.replace('admin-editor-active', '').trim();
    document.body.innerHTML = '';

    const contentWrapper = document.createElement('div');
    contentWrapper.id = 'admin-page-content-wrapper';
    contentWrapper.style.position = 'fixed';
    contentWrapper.style.top = '54px';
    contentWrapper.style.left = '280px';
    contentWrapper.style.right = '280px';
    contentWrapper.style.bottom = '0';
    contentWrapper.style.width = 'auto';
    contentWrapper.style.height = 'auto';

    contentWrapper.style.display = 'flex';
    contentWrapper.style.justifyContent = 'center';
    contentWrapper.style.alignItems = 'flex-start';
    contentWrapper.style.backgroundColor = '#181818';
    contentWrapper.style.paddingTop = '20px';
    contentWrapper.style.overflow = 'auto';

    const viewportFrame = document.createElement('iframe');
    viewportFrame.id = 'admin-viewport-frame';
    viewportFrame.style.border = 'none';
    viewportFrame.style.backgroundColor = '#fff';

    viewportFrame.style.width = '100%';
    viewportFrame.style.height = 'calc(100% - 40px)';
    viewportFrame.style.boxShadow = '0 0 20px rgba(0,0,0,0.5)';

    const url = new URL(window.location.href);
    url.searchParams.set('edit', 'true');
    url.searchParams.set('inner_frame', 'true');
    viewportFrame.src = url.toString();

    contentWrapper.appendChild(viewportFrame);
    document.body.appendChild(contentWrapper);

    let editorDoc = null;

    viewportFrame.onload = function () {
        debugLog('Editor Frame Loaded via SRC');

        const iframeDoc = viewportFrame.contentDocument || viewportFrame.contentWindow.document;
        window.editorDoc = iframeDoc;
        editorDoc = iframeDoc;

        initializeEditorTools();
    };

    function initializeEditorTools() {
        if (!editorDoc) return;
        const isGerman = PAGE_ID === 'home';
        let draggedStructureItem = null;

        const isMac = navigator.platform.toUpperCase().indexOf('MAC') >= 0;
        const searchShortcut = isMac ? '⌘ + K' : 'Ctrl + K';

        const L = {
            banner: isGerman ? 'Banner' : 'Banner',
            services: isGerman ? 'Leistungen' : 'Services',
            about: isGerman ? 'Über Uns' : 'About Us',
            projects: isGerman ? 'Projekte' : 'Projects',
            stats: isGerman ? 'Statistiken' : 'Stats',
            testimonial: isGerman ? 'Referenzen' : 'Testimonials',
            brand: isGerman ? 'Marken' : 'Brands',
            why_choose: isGerman ? 'Warum wir' : 'Why Choose',
            content_section: isGerman ? 'Inhalt' : 'Content',
            blog: isGerman ? 'Blog' : 'Blog',
            experience: isGerman ? 'Erfahrung' : 'Experience',
            stats: isGerman ? 'Statistiken' : 'Stats',
            items: isGerman ? 'Elemente' : 'Items',
            page_sections: isGerman ? 'Seitenbereiche' : 'Page Sections',

            lastEdited: isGerman ? 'Zuletzt bearbeitet' : 'Last edited',
            justNow: isGerman ? 'gerade eben' : 'just now',
            minute: isGerman ? 'Minute' : 'minute',
            minutes: isGerman ? 'Minuten' : 'minutes',
            hour: isGerman ? 'Stunde' : 'hour',
            hours: isGerman ? 'Stunden' : 'hours',
            day: isGerman ? 'Tag' : 'day',
            days: isGerman ? 'Tage' : 'days',
            ago: isGerman ? 'vor' : 'ago',
            copied: isGerman ? 'Seiten-URL in die Zwischenablage kopiert' : 'Page URL copied to clipboard',
            saveConfirm: isGerman ? 'Änderungen für die folgenden Blöcke speichern:\n\n' : 'Saving changes for the following blocks:\n\n',
            proceed: isGerman ? '\n\nFortfahren?' : '\n\nProceed?',
            publishConfirm: isGerman ? 'Sind Sie sicher, dass Sie diese Änderungen live VERÖFFENTLICHEN möchten?' : 'Are you sure you want to PUBLISH these changes live?',
            saving: isGerman ? 'Wird gespeichert...' : 'Saving...',
            saved: isGerman ? 'Gespeichert!' : 'Saved!',
            publishing: isGerman ? 'Wird veröffentlicht...' : 'Publishing...',
            published: isGerman ? 'Veröffentlicht!' : 'Published!',
            saveFailed: isGerman ? 'Speichern fehlgeschlagen: ' : 'Save Failed: ',
            publishFailed: isGerman ? 'Veröffentlichung fehlgeschlagen: ' : 'Publish Failed: ',
            error: isGerman ? 'Fehler: ' : 'Error: ',
            confirmTitle: isGerman ? 'Bestätigung erforderlich' : 'Confirmation Required',
            cancel: isGerman ? 'Abbrechen' : 'Cancel',
            confirm: isGerman ? 'Bestätigen' : 'Confirm',
            successTitle: isGerman ? 'Erfolg' : 'Success',
            errorTitle: isGerman ? 'Fehler' : 'Error',
            infoTitle: isGerman ? 'Information' : 'Information',
            warningTitle: isGerman ? 'Warnung' : 'Warning',
            widescreen: isGerman ? 'Widescreen (1600px+)' : 'Widescreen (1600px+)',
            desktop: isGerman ? 'Desktop (1440px)' : 'Desktop (1440px)',
            laptop: isGerman ? 'Laptop (1366px)' : 'Laptop (1366px)',
            tabletLand: isGerman ? 'Tablet Querformat (1024px)' : 'Tablet Landscape (1024px)',
            tabletPort: isGerman ? 'Tablet Hochformat (768px)' : 'Tablet Portrait (768px)',
            mobileLand: isGerman ? 'Mobile Querformat (600px)' : 'Mobile Landscape (600px)',
            about_features: isGerman ? 'Über Uns' : 'About',
            why_us: isGerman ? 'Warum Wir' : 'Why Us',
            service_x: isGerman ? 'Leistungen' : 'Services',
            project_x: isGerman ? 'Projekte' : 'Projects',
            testimonial_x: isGerman ? 'Referenzen' : 'Testimonials',
            mobilePort: isGerman ? 'Mobile Hochformat (375px)' : 'Mobile Portrait (375px)'
        };

        const _defaults = {
            structure: isGerman ? 'Struktur' : 'Structure',
            projectTitle: PAGE_ID || 'Project',
            editTime: L.lastEdited || (isGerman ? 'Zuletzt bearbeitet' : 'Last edited'),
            layouts: isGerman ? 'Vorlagen' : 'Layouts',
            saveDraft: isGerman ? 'Als Entwurf speichern' : 'Save Draft',
            publish: isGerman ? 'Veröffentlichen' : 'Publish',
            sections: isGerman ? 'Sektionen' : 'Sections',
            columns: isGerman ? 'Spalten' : 'Columns',
            quickstack: isGerman ? 'Quick Stack' : 'Quick Stack',
            grid: isGerman ? 'Grid' : 'Grid',
            container: isGerman ? 'Container' : 'Container',
            typography: isGerman ? 'Typografie' : 'Typography',
            paragraph: isGerman ? 'Absatz' : 'Paragraph',
            title: isGerman ? 'Titel' : 'Title',
            richtext: isGerman ? 'Richtext' : 'Rich Text',
            quote: isGerman ? 'Zitat' : 'Quote',
            textblock: isGerman ? 'Textblock' : 'Text Block',
            link: isGerman ? 'Link' : 'Link',
            media: isGerman ? 'Medien' : 'Media',
            image: isGerman ? 'Bild' : 'Image',
            video: 'Video',
            youtube: 'YouTube',
            audio: isGerman ? 'Audio' : 'Audio',
            forms: isGerman ? 'Formulare' : 'Forms',
            input: isGerman ? 'Eingabe' : 'Input',
            button: isGerman ? 'Button' : 'Button',
            checkbox: isGerman ? 'Checkbox' : 'Checkbox',
            content: isGerman ? 'Inhalt' : 'Content',
            advanced: isGerman ? 'Erweitert' : 'Advanced',
            selectToEdit: isGerman ? 'Zum Bearbeiten auswählen' : 'Select an element to edit',
            subtitle: isGerman ? 'Untertitel' : 'Subtitle',
            modified: isGerman ? 'Inhalt geändert' : 'Content modified',
            sections: isGerman ? 'Sektionen' : 'Sections',
            testimonial: isGerman ? 'Referenzen' : 'Testimonials',
            service: isGerman ? 'Leistungen' : 'Services',
            project: isGerman ? 'Projekte' : 'Projects',
            contact_href: isGerman ? 'Kontakt Link' : 'Contact Href',
            moveUp: isGerman ? 'Nach oben verschieben' : 'Move Up',
            moveDown: isGerman ? 'Nach unten verschieben' : 'Move Down'
        };

        const missingDefaults = {
            textContent: isGerman ? 'Inhalt' : 'Text Content',
            textColor: isGerman ? 'Textfarbe' : 'Text Color',
            bgColor: isGerman ? 'Hintergrundfarbe' : 'Bg Color',
            size: isGerman ? 'Größe' : 'Size',
            weight: isGerman ? 'Gewicht' : 'Weight',
            linkText: isGerman ? 'Link-Text' : 'Link Text',
            href: isGerman ? 'Link-Adresse' : 'Href',
            noElement: isGerman ? 'Kein Element ausgewählt' : 'No element selected',
            clickToReplace: isGerman ? 'Klicken um zu ersetzen' : 'Click to replace',
            uploading: isGerman ? 'Hochladen...' : 'Uploading...',
            layout: isGerman ? 'Layout' : 'Layout',
            customCss: isGerman ? 'Benutzerdefiniertes CSS' : 'Custom CSS',
            bgImage: isGerman ? 'Hintergrundbild' : 'Background Image',
            deleteSectionConfirm: isGerman ? 'Möchten Sie diesen Abschnitt wirklich löschen?' : 'Are you sure you want to delete this section?'
        };

        Object.keys(missingDefaults).forEach(k => { if (!L.hasOwnProperty(k) || !L[k]) L[k] = missingDefaults[k]; });

        Object.keys(_defaults).forEach(k => { if (!L.hasOwnProperty(k) || !L[k]) L[k] = _defaults[k]; });

        const heroIcons = {
            stack: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10.5L12 4l9 6.5V20a1 1 0 01-1 1h-5v-6H9v6H4a1 1 0 01-1-1V10.5z"/></svg>`,
            section: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5m.75-9l3-3 2.148 2.148A12.061 12.061 0 0116.5 7.605" /></svg>',
            cube: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" /></svg>',
            widescreen: '<i class="far fa-desktop" style="font-size: 16px;"></i>',
            desktop: '<i class="far fa-laptop" style="font-size: 16px;"></i>',
            laptop: '<i class="far fa-laptop-code" style="font-size: 16px;"></i>',

            tabletLand: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="transform: rotate(90deg);"><path d="M10.5 18a.75.75 0 0 0 0 1.5h3a.75.75 0 0 0 0-1.5h-3Z" /><path fill-rule="evenodd" d="M7.125 1.5A3.375 3.375 0 0 0 3.75 4.875v14.25A3.375 3.375 0 0 0 7.125 22.5h9.75a3.375 3.375 0 0 0 3.375-3.375V4.875A3.375 3.375 0 0 0 16.875 1.5h-9.75ZM6 4.875c0-.621.504-1.125 1.125-1.125h9.75c.621 0 1.125.504 1.125 1.125v14.25c0 .621-.504 1.125-1.125 1.125h-9.75A1.125 1.125 0 0 1 6 19.125V4.875Z" clip-rule="evenodd" /></svg>',
            tabletPort: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M10.5 18a.75.75 0 0 0 0 1.5h3a.75.75 0 0 0 0-1.5h-3Z" /><path fill-rule="evenodd" d="M7.125 1.5A3.375 3.375 0 0 0 3.75 4.875v14.25A3.375 3.375 0 0 0 7.125 22.5h9.75a3.375 3.375 0 0 0 3.375-3.375V4.875A3.375 3.375 0 0 0 16.875 1.5h-9.75ZM6 4.875c0-.621.504-1.125 1.125-1.125h9.75c.621 0 1.125.504 1.125 1.125v14.25c0 .621-.504 1.125-1.125 1.125h-9.75A1.125 1.125 0 0 1 6 19.125V4.875Z" clip-rule="evenodd" /></svg>',
            mobileLand: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="transform: rotate(90deg);"><path d="M10.5 18.75a.75.75 0 0 0 0 1.5h3a.75.75 0 0 0 0-1.5h-3Z" /><path fill-rule="evenodd" d="M8.625.75A3.375 3.375 0 0 0 5.25 4.125v15.75a3.375 3.375 0 0 0 3.375 3.375h6.75a3.375 3.375 0 0 0 3.375-3.375V4.125A3.375 3.375 0 0 0 15.375.75h-6.75ZM7.5 4.125C7.5 3.504 8.004 3 8.625 3H9.75v.375c0 .621.504 1.125 1.125 1.125h2.25c.621 0 1.125-.504 1.125-1.125V3h1.125c.621 0 1.125.504 1.125 1.125v15.75c0 .621-.504 1.125-1.125 1.125h-6.75A1.125 1.125 0 0 1 7.5 19.875V4.125Z" clip-rule="evenodd" /></svg>',
            mobilePort: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M10.5 18.75a.75.75 0 0 0 0 1.5h3a.75.75 0 0 0 0-1.5h-3Z" /><path fill-rule="evenodd" d="M8.625.75A3.375 3.375 0 0 0 5.25 4.125v15.75a3.375 3.375 0 0 0 3.375 3.375h6.75a3.375 3.375 0 0 0 3.375-3.375V4.125A3.375 3.375 0 0 0 15.375.75h-6.75ZM7.5 4.125C7.5 3.504 8.004 3 8.625 3H9.75v.375c0 .621.504 1.125 1.125 1.125h2.25c.621 0 1.125-.504 1.125-1.125V3h1.125c.621 0 1.125.504 1.125 1.125v15.75c0 .621-.504 1.125-1.125 1.125h-6.75A1.125 1.125 0 0 1 7.5 19.875V4.125Z" clip-rule="evenodd" /></svg>',
            stack: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12" /></svg>',

            eye: '<i class="far fa-eye" style="font-size: 18px;"></i>',
            share: '<i class="fas fa-share-alt" style="font-size: 18px;"></i>',
            save: '<i class="far fa-save" style="font-size: 18px;"></i>',
            text: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" width="14" height="14"><path d="M4 7h16M4 12h10" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>`,
            photo: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" width="14" height="14"><rect x="3" y="3" width="18" height="14" rx="2" stroke-width="1.5"/><path d="M3 17l4-4 4 4 5-6 5 6" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>`,
            link: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" width="14" height="14"><path d="M10 14a5 5 0 007.07 0l1.42-1.42a5 5 0 00-7.07-7.07l-1.42 1.42" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M14 10a5 5 0 00-7.07 0L5.51 11.42a5 5 0 007.07 7.07L14 17.07" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>`,
            container: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-2.25-1.313M21 7.5v2.25m0-2.25l-2.25 1.313M3 7.5l2.25-1.313M3 7.5l2.25 1.313M3 7.5v2.25m9 3l2.25-1.313M12 12.75l-2.25-1.313M12 12.75V15m0 6.75l2.25-1.313M12 21.75V19.5m0 2.25l-2.25-1.313m0-16.875L12 2.25l2.25 1.313M21 14.25v2.25l-2.25 1.313m-13.5 0L3 16.5v-2.25" /></svg>',
            chevronRight: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" width="12" height="12"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>',
            play: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 3v18l15-9L5 3z"/></svg>',
            trash: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" width="12" height="12"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>',
            drag: '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 24 24"><rect x="4" y="4" width="7" height="7" rx="1"/><rect x="13" y="4" width="7" height="7" rx="1"/><rect x="4" y="13" width="7" height="7" rx="1"/><rect x="13" y="13" width="7" height="7" rx="1"/></svg>',
            checkCircle: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
            exclamationCircle: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>',
            infoCircle: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" /></svg>',
            exclamationTriangle: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.008v.008H12v-.008z" /></svg>',
            xMark: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>',
            arrowUp: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="12" height="12"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" /></svg>',
            arrowDown: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="12" height="12"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>',
        };
        const editorHtml = `
        <!-- Structure Sidebar (Always Visible) -->
        <div class="admin-structure-sidebar" id="admin-structure-sidebar">
            <div class="structure-header">
                <h3 class="structure-title">${L.structure}</h3>
            </div>
            <div class="structure-content" id="structure-content">
                <!-- Tree will be built here -->
            </div>
        </div>

        <!-- Top Bar -->
        <div class="admin-topbar" id="admin-topbar">
            <!-- Left: Branding & Page Info -->
            <div class="admin-topbar-left">
                <div class="topbar-logo-icon">
                    ${heroIcons.stack}
                </div>
                <div class="topbar-info-text">
                    <div class="topbar-project-title">${L.projectTitle}</div>
                    <div class="topbar-edit-time" id="topbar-edit-time">${L.editTime}</div>
                </div>
            </div>

            <!-- Center: Viewport Controls -->
            <div class="admin-topbar-center">
                <div class="responsive-controls">
                    <button class="responsive-btn active" title="${L.widescreen}" id="btn-device-widescreen">${heroIcons.widescreen}</button>
                    <button class="responsive-btn" title="${L.desktop}" id="btn-device-desktop">${heroIcons.desktop}</button>
                    <button class="responsive-btn" title="${L.laptop}" id="btn-device-laptop">${heroIcons.laptop}</button>
                    <button class="responsive-btn" title="${L.tabletLand}" id="btn-device-tablet-land">${heroIcons.tabletLand}</button>
                    <button class="responsive-btn" title="${L.tabletPort}" id="btn-device-tablet-port">${heroIcons.tabletPort}</button>
                    <button class="responsive-btn" title="${L.mobileLand}" id="btn-device-mobile-land">${heroIcons.mobileLand}</button>
                    <button class="responsive-btn" title="${L.mobilePort}" id="btn-device-mobile-port">${heroIcons.mobilePort}</button>
                </div>
                </div>


                <button class="btn-icon-circle btn-preview" id="btn-preview" title="Preview" style="background: none !important;">${heroIcons.eye}</button>
                <button class="btn-icon-circle btn-share" title="Share" style="background: none !important;">${heroIcons.share}</button>
                <div class="topbar-divider"></div>
                
                <button class="btn-editor-action btn-save-draft" id="btn-save-draft">
                    ${heroIcons.save}
                    ${L.saveDraft}
                </button>

                <button class="btn-editor-action btn-publish" id="btn-publish">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 18px; height: 18px; margin-right: 6px;">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 0 1-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 0 0 6.16-12.12A14.98 14.98 0 0 0 9.631 8.41m5.96 5.96a14.926 14.926 0 0 1-5.841 2.58m-.119-8.54a6 6 0 0 0-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 0 0-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 0 1-2.448-2.448 14.9 14.9 0 0 1 .06-.312m-2.24 2.39a4.493 4.493 0 0 0-1.757 4.306 4.493 4.493 0 0 0 4.306-1.758M16.5 9a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z" />
                    </svg>
                    ${L.publish}
                </button>
            </div>
        </div>

        <!-- Editor Sidebar (LEFT - Component Library) -->
        <div class="admin-editor-sidebar" id="admin-sidebar">
            <div class="structure-header" style="display: flex; justify-content: space-between; align-items: center; padding-right: 15px;">
                <h3 class="structure-title" id="sidebar-title">${L.layouts}</h3>
            </div>
            
            <div class="sidebar-content" id="sidebar-content">
                
                

                <!-- Media Section -->
                <div class="component-category">
                    <h4 class="component-category-title">${L.media}</h4>
                    <div class="component-grid">

                        <button class="component-btn" data-component="why-us-video" title="Why Us Video">
                             <div class="component-icon">
                                 ${heroIcons.play}
                             </div>
                             <span class="component-label">Why Us Video</span>
                        </button>

                        <button class="component-btn" data-component="video" title="${L.video}">
                            <div class="component-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.91 11.672a.375.375 0 010 .656l-5.603 3.113a.375.375 0 01-.557-.328V8.887c0-.286.307-.466.557-.327l5.603 3.112z" />
                                </svg>
                            </div>
                            <span class="component-label">${L.video}</span>
                        </button>

                        <button class="component-btn" data-component="contact-href" title="${L.contact_href}">
                            <div class="component-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
                                </svg>
                            </div>
                            <span class="component-label">${L.contact_href}</span>
                        </button>
                    </div>
                </div>

                <!-- Items Section -->
                <div class="component-category">
                    <h4 class="component-category-title">${L.items}</h4>
                    <div class="component-grid">
                        <button class="component-btn" data-component="banner-circle" title="Banner Circle">
                            <div class="component-icon">
                                ${heroIcons.container}
                            </div>
                            <span class="component-label">Banner Circle</span>
                        </button>

                        <button class="component-btn" data-component="service" title="${L.service_x}">
                            <div class="component-icon">
                                ${heroIcons.container}
                            </div>
                            <span class="component-label">${L.service_x}</span>
                        </button>

                        <button class="component-btn" data-component="about-features" title="${L.about_features}">
                            <div class="component-icon">
                                ${heroIcons.container}
                            </div>
                            <span class="component-label">${L.about_features}</span>
                        </button>
                        
                        <button class="component-btn" data-component="project" title="${L.project_x}">
                            <div class="component-icon">
                                ${heroIcons.container}
                            </div>
                            <span class="component-label">${L.project_x}</span>
                        </button>

                        <button class="component-btn" data-component="why-us" title="${L.why_us}">
                            <div class="component-icon">
                                ${heroIcons.container}
                            </div>
                            <span class="component-label">${L.why_us}</span>
                        </button>

                        <button class="component-btn" data-component="testimonial" title="${L.testimonial_x}">
                            <div class="component-icon">
                                ${heroIcons.container}
                            </div>
                            <span class="component-label">${L.testimonial_x}</span>
                        </button>
                    </div>
                </div>

                <!-- Page Sections Category -->
                <div class="component-category">
                    <h4 class="component-category-title">${L.page_sections}</h4>
                    <div class="component-grid" id="sections-component-grid">
                        <button class="component-btn" data-component="section-banner" title="${L.banner}">
                            <div class="component-icon">${heroIcons.section}</div>
                            <span class="component-label">${L.banner}</span>
                        </button>
                        <button class="component-btn" data-component="section-experience" title="${L.experience}">
                            <div class="component-icon">${heroIcons.section}</div>
                            <span class="component-label">${L.experience}</span>
                        </button>
                        <button class="component-btn" data-component="section-services" title="${L.services}">
                            <div class="component-icon">${heroIcons.section}</div>
                            <span class="component-label">${L.services}</span>
                        </button>
                        <button class="component-btn" data-component="section-about" title="${L.about}">
                            <div class="component-icon">${heroIcons.section}</div>
                            <span class="component-label">${L.about}</span>
                        </button>
                        <button class="component-btn" data-component="section-projects" title="${L.projects}">
                            <div class="component-icon">${heroIcons.section}</div>
                            <span class="component-label">${L.projects}</span>
                        </button>
                        <button class="component-btn" data-component="section-stats" title="${L.stats}">
                            <div class="component-icon">${heroIcons.section}</div>
                            <span class="component-label">${L.stats}</span>
                        </button>
                        <button class="component-btn" data-component="section-brand" title="${L.brand}">
                            <div class="component-icon">${heroIcons.section}</div>
                            <span class="component-label">${L.brand}</span>
                        </button>
                        <button class="component-btn" data-component="section-why-choose" title="${L.why_choose}">
                            <div class="component-icon">${heroIcons.section}</div>
                            <span class="component-label">${L.why_choose}</span>
                        </button>
                        <button class="component-btn" data-component="section-testimonial" title="${L.testimonial}">
                            <div class="component-icon">${heroIcons.section}</div>
                            <span class="component-label">${L.testimonial}</span>
                        </button>
                        <button class="component-btn" data-component="section-blog" title="${L.blog}">
                            <div class="component-icon">${heroIcons.section}</div>
                            <span class="component-label">${L.blog}</span>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Properties Panel (Hidden by default, shown when element selected) -->
            <div class="properties-panel" id="properties-panel" style="display: none;">
                <div class="sidebar-header">
                    <div class="sidebar-header-top" style="display: flex; justify-content: space-between; align-items: center; padding-right: 15px;">
                        <h3 class="structure-title">${L.layouts}</h3>
                        <button class="sidebar-action-btn" id="btn-back-to-elements" title="Add Element" style="background:none; border:none; color:inherit; cursor:pointer;">
                            <i class="fas fa-plus" style="font-size: 14px;"></i>
                        </button>
                    </div>
                    <div class="sidebar-tabs">
                        <button class="sidebar-tab active" data-tab="content">${L.content}</button>
                        <button class="sidebar-tab" data-tab="advanced">${L.advanced}</button>
                    </div>
                </div>
                <div class="sidebar-content" id="properties-content">
                    <p style="text-align:center; color:#666; padding-top:40px;">${L.selectToEdit}</p>
                </div>
            </div>
        </div>
        
        <!-- Hidden Inputs -->
        <input type="file" id="admin-image-upload" accept="image/*">
    `;

        document.body.insertAdjacentHTML('beforeend', editorHtml);

        const sidebar = document.getElementById('admin-sidebar');
        const structureSidebar = document.getElementById('admin-structure-sidebar');
        const sidebarContent = document.getElementById('sidebar-content');
        const sidebarTitle = document.getElementById('sidebar-title');
        const propertiesPanel = document.getElementById('properties-panel');
        const propertiesContent = document.getElementById('properties-content');
        const propertiesTitle = document.getElementById('properties-title');
        const propertiesClose = document.getElementById('properties-close');
        const btnPreview = document.getElementById('btn-preview');
        const btnPublish = document.getElementById('btn-publish');
        const btnSaveDraft = document.getElementById('btn-save-draft');
        const fileInput = document.getElementById('admin-image-upload');
        const componentBtns = document.querySelectorAll('.component-btn');
        const tabButtons = document.querySelectorAll('.sidebar-tab');

        componentBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const component = btn.getAttribute('data-component');
                const repeatableTypes = ['testimonial', 'service', 'project', 'about-features', 'why-us', 'banner-circle', 'why-us-video', 'video', 'contact-href'];
                const typoTypes = ['title', 'subtitle', 'text', 'link', 'button'];
                const sectionTypes = ['section-banner', 'section-experience', 'section-services', 'section-about', 'section-projects', 'section-stats', 'section-brand', 'section-why-choose', 'section-testimonial', 'section-content', 'section-blog'];

                if (repeatableTypes.includes(component)) {
                    addComponent(component);
                } else if (typoTypes.includes(component)) {
                    insertTypography(component);
                } else if (sectionTypes.includes(component)) {
                    restoreSection(component.replace('section-', ''));
                } else {
                    showToast('Insertion logic for ' + component + ' coming soon!', 'info');
                }
            });
        });

        if (propertiesClose) {
            propertiesClose.addEventListener('click', closePropertiesPanel);
        }

        tabButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                tabButtons.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                activeTab = btn.getAttribute('data-tab');
                renderSidebarControls();
            });
        });

        buildStructureTree();

        function generateUniqueId(prefix) {
            return `${prefix}-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
        }

        async function addComponent(type) {
            if (!editorDoc) return;

            if (type === 'banner-circle') {
                console.log('🔵 BANNER CIRCLE RESTORATION STARTED');
                console.log('Attempting to restore banner-circle');
                const iframeDoc = document.getElementById('admin-viewport-frame').contentDocument;
                const existingCircle = iframeDoc.querySelector('.banner-one-item .curve-text');
                console.log('Existing circle found:', existingCircle);
                if (existingCircle && existingCircle.isConnected && iframeDoc.body.contains(existingCircle)) {
                    const existingVideo = existingCircle.querySelector('[data-key="banner_video"]');
                    if (!existingVideo) {
                        const videoHtml = `
                        <a href="https://www.youtube.com/watch?v=ipUuoMCEbDQ" class="popup-youtube" style="" data-editable-link="true" data-key="banner_video" data-group="Banner Circle" data-editable-style="true" data-style-key="banner_video_style">
                            <i class="fas fa-arrow-right"></i>
                        </a>`;
                        existingCircle.insertAdjacentHTML('beforeend', videoHtml);
                        const newVideo = existingCircle.querySelector('[data-key="banner_video"]');
                        markTouched(newVideo);
                        buildStructureTree();
                        console.log('✅ Banner Video restored successfully');
                        showToast(isGerman ? 'Banner Video wiederhergestellt' : 'Banner Video restored', 'success');
                        return;
                    }

                    console.log('ℹ️ Banner Circle already exists - no action needed');
                    showToast(isGerman ? 'Banner Circle existiert bereits' : 'Banner Circle already exists', 'warning');
                    return;
                }

                console.log('📦 Checking for Banner Circle backup in sessionStorage...');
                let circleHtml = null;
                try {
                    const backup = sessionStorage.getItem('bannerCircleBackup');
                    if (backup) {
                        circleHtml = backup;
                        sessionStorage.removeItem('bannerCircleBackup');
                        console.log('✅ Backup found and loaded from sessionStorage');
                    } else {
                        console.log('ℹ️ No backup found - will use default template');
                    }
                } catch (e) {
                    console.warn('⚠️ Could not restore Banner Circle state:', e);
                }
                console.log('Restoring Banner Circle. Backup found:', !!circleHtml);

                if (!circleHtml) {
                    circleHtml = `
                    <div class="col-lg-3 offset-lg-1 banner-one-item text-center" data-key="banner_circle_container">
                        <div class="curve-text">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 150 150" version="1.1" style="background-image: linear-gradient(90deg, #20C1F5, #49B9F2, #7675EC, #A04EE1, #D225D7, #F009D5);" data-editable-bg="true" data-key="curve_text_style" data-group="Circle">
                                <path id="textPath" d="M 0,75 a 75,75 0 1,1 0,1 z"></path>
                                <text>
                                    <textPath href="#textPath" data-editable="true" data-key="banner_cert_text" data-group="Circle">Certified Partner</textPath>
                                </text>
                            </svg>
                            <a href="https://www.youtube.com/watch?v=ipUuoMCEbDQ" class="popup-youtube" style="" data-editable-link="true" data-key="banner_video" data-group="Circle" data-editable-style="true" data-style-key="banner_video_style">
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>`;
                }

                const wrapperSelector = '#home .row.align-center';
                const wrapper = editorDoc.querySelector(wrapperSelector);
                console.log('Target wrapper found:', wrapper);
                if (!wrapper) {
                    console.error('❌ Wrapper not found:', wrapperSelector);
                    return;
                }

                const titleCol = wrapper.querySelector('.col-lg-8');
                if (titleCol) {
                    console.log('✅ Inserting banner-circle after title column');
                    titleCol.insertAdjacentHTML('afterend', circleHtml);
                } else {
                    console.log('⚠️ Title column not found - inserting at end of wrapper');
                    wrapper.insertAdjacentHTML('beforeend', circleHtml);
                }

                const newEl = wrapper.querySelector('.banner-one-item:last-child');
                console.log('New element inserted:', newEl);
                const newKey = newEl.getAttribute('data-key');
                console.log('Element data-key:', newKey);
                if (newKey && deletedStructuralKeys.has(newKey)) {
                    deletedStructuralKeys.delete(newKey);
                    console.log('Removed from deletedStructuralKeys:', newKey);
                }

                markTouched(newEl);
                buildStructureTree();
                showToast(isGerman ? 'Banner Circle wiederhergestellt' : 'Banner Circle restored', 'success');
                newEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }

            if (type === 'why-us-video') {
                const iframeDoc = document.getElementById('admin-viewport-frame').contentDocument;
                const existingVideo = iframeDoc.querySelector('.video-play-button');

                if (existingVideo && existingVideo.isConnected && iframeDoc.body.contains(existingVideo)) {
                    console.log('Restoration skipped: Why Us Video already exists.');
                    showToast(isGerman ? 'Video Play Button existiert bereits' : 'Why Us Video already exists', 'warning');
                    return;
                }

                const targetContainer = iframeDoc.querySelector('.choose-us-style-one-thumb');
                if (!targetContainer) {
                    showToast(isGerman ? 'Ziel-Container nicht gefunden' : 'Target container not found', 'error');
                    return;
                }

                let videoHtml = null;
                try {
                    const backup = sessionStorage.getItem('whyUsVideoBackup');
                    if (backup) {
                        videoHtml = backup;
                        sessionStorage.removeItem('whyUsVideoBackup');
                    }
                } catch (e) {
                    console.warn('Could not restore Why Us Video state:', e);
                }

                if (!videoHtml) {
                    videoHtml = `
                    <a href="https://www.youtube.com/watch?v=ipUuoMCEbDQ" class="popup-youtube video-play-button" data-editable-link="true" data-key="why_video_wrapper" data-link-key="why_video_href" data-group="Why Us Video">
                        <i class="fas fa-play" style=""></i>
                        <div class="effect"></div>
                    </a>`;
                }

                targetContainer.insertAdjacentHTML('beforeend', videoHtml);
                const newEl = targetContainer.querySelector('.video-play-button');
                const newKey = newEl.getAttribute('data-key');
                if (newKey && deletedStructuralKeys.has(newKey)) {
                    deletedStructuralKeys.delete(newKey);
                }

                markTouched(newEl);
                buildStructureTree();
                console.log('Restored Why Us Video:', newEl);
                showToast(isGerman ? 'Video wiederhergestellt' : 'Video restored', 'success');
                newEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }

            if (type === 'contact-href') {
                const iframeDoc = document.getElementById('admin-viewport-frame').contentDocument;
                const existingContact = iframeDoc.querySelector('.banner-one-item .btn-theme') || iframeDoc.querySelector('[data-key="contact_btn_link"]');

                if (existingContact && existingContact.isConnected && iframeDoc.body.contains(existingContact)) {
                    console.log('Restoration skipped: Contact button already exists.');
                    showToast(isGerman ? 'Kontakt-Button existiert bereits' : 'Contact button already exists', 'warning');
                    return;
                }

                const targetContainer = iframeDoc.querySelector('.contact-panel .col-lg-6');
                if (!targetContainer) {
                    showToast(isGerman ? 'Ziel-Container (Contact) nicht gefunden' : 'Target container (Contact) not found', 'error');
                    return;
                }

                const contactHtml = `
                <a class="btn-round-animation dark mt-40" href="#" data-bs-toggle="modal" data-bs-target="#contactFormModal" data-editable-link="true" data-key="contact_btn_link" data-group="Contact Href">
                    <span data-key="contact_btn_text">${isGerman ? 'E-Mail senden' : 'Send Email'}</span> <i class="fas fa-arrow-right"></i>
                </a>`;

                targetContainer.insertAdjacentHTML('beforeend', contactHtml);
                const newEl = targetContainer.querySelector('[data-key="contact_btn_link"]');

                markTouched(newEl);
                buildStructureTree();
                console.log('Restored Contact Href:', newEl);
                showToast(isGerman ? 'Kontakt wiederhergestellt' : 'Contact restored', 'success');
                newEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }

            if (type === 'video') {
                const iframeDoc = document.getElementById('admin-viewport-frame').contentDocument;
                const existingBtn = iframeDoc.querySelector('.about-style-one .arrow-btn');

                if (existingBtn && existingBtn.isConnected && iframeDoc.body.contains(existingBtn)) {
                    console.log('Restoration skipped: About Video button already exists.');
                    showToast(isGerman ? 'About-Button existiert bereits' : 'About button already exists', 'warning');
                    return;
                }

                const targetContainer = iframeDoc.querySelector('.about-style-one');
                if (!targetContainer) {
                    showToast(isGerman ? 'Ziel-Container (About Content) nicht gefunden' : 'Target container (About Content) not found', 'error');
                    return;
                }

                const aboutBtnHtml = `
                <a href="#about" class="arrow-btn" data-editable-link="true" data-key="about_btn" data-editable-style="true" data-group="About Video">
                    <i class="fas fa-arrow-right"></i>
                </a>`;

                targetContainer.insertAdjacentHTML('beforeend', aboutBtnHtml);
                const newEl = targetContainer.querySelector('.arrow-btn:last-child');

                markTouched(newEl);
                buildStructureTree();
                console.log('Restored About Video (Button):', newEl);
                showToast(isGerman ? 'About Button wiederhergestellt' : 'About Button restored', 'success');
                newEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }

            let wrapperSelector = '';
            let itemSelector = '';

            if (type === 'testimonial') {
                wrapperSelector = '.testimonial-style-one-carousel .swiper-wrapper';
                itemSelector = '.swiper-slide:not(.swiper-slide-duplicate)';
            } else if (type === 'service') {
                wrapperSelector = '.services-carousel .swiper-wrapper';
                itemSelector = '.swiper-slide:not(.swiper-slide-duplicate)';
            } else if (type === 'project') {
                wrapperSelector = '#projects-section .row.mt--50';
                itemSelector = '.col-lg-6.item-center';
            } else if (type === 'about-features') {
                wrapperSelector = '.modern-about-list';
                itemSelector = 'li';
            } else if (type === 'why-us') {
                wrapperSelector = '.choose-us-style-one .list-item';
                itemSelector = 'li';
            }

            if (!wrapperSelector) {
                console.warn('No wrapper selector defined for component type:', type);
                return;
            }

            const wrapper = editorDoc.querySelector(wrapperSelector);
            const items = wrapper ? wrapper.querySelectorAll(itemSelector) : null;

            if (!wrapper || !items) {
                showToast('Target container or template item not found for ' + type, 'error');
                return;
            }

            if (type === 'about-features') {
                let maxIndex = 0;
                if (items && items.length > 0) {
                    items.forEach(item => {
                        const key = item.getAttribute('data-key');
                        if (key) {
                            const match = key.match(/about_list_item_(\d+)/);
                            if (match) {
                                maxIndex = Math.max(maxIndex, parseInt(match[1]));
                            }
                        }
                    });
                }

                const newIndex = maxIndex + 1;
                const newKey = `about_list_item_${newIndex}`;
                const isGerman = document.documentElement.lang === 'de';

                const newLi = editorDoc.createElement('li');
                newLi.setAttribute('data-editable', 'true');
                newLi.setAttribute('data-key', newKey);
                newLi.setAttribute('data-editable-style', 'true');
                newLi.setAttribute('data-group', 'About Features');
                newLi.textContent = isGerman ? `Neues Feature ${newIndex}` : `New Feature ${newIndex}`;

                wrapper.appendChild(newLi);
                markTouched(newLi);
                buildStructureTree();
                showToast(isGerman ? 'Feature hinzugefügt' : 'Feature added', 'success');
                newLi.scrollIntoView({ behavior: 'smooth', block: 'center' });

                newLi.style.outline = '2px solid var(--color-primary)';
                setTimeout(() => {
                    newLi.style.outline = 'none';
                    newLi.style.transition = 'outline 0.5s ease';
                }, 2000);
                return;
            }

            if (items.length === 0) {
                showToast('Template item not found for ' + type, 'error');
                return;
            }

            let maxIndex = 0;
            const realItems = [];
            items.forEach(item => {
                if (item.classList.contains('swiper-slide-duplicate')) return;
                realItems.push(item);

                const elWithKey = item.querySelector('[data-key]');
                if (elWithKey) {
                    const key = elWithKey.getAttribute('data-key');
                    const parts = key.split('_');
                    const idxPart = parts.find(p => p !== '' && !isNaN(p));
                    if (idxPart) {
                        const idx = parseInt(idxPart);
                        if (idx > maxIndex && idx < 1000000000) {
                            maxIndex = idx;
                        }
                    }
                }
            });

            const newIndex = maxIndex + 1;
            const typeLabel = type.charAt(0).toUpperCase() + type.slice(1);
            let newGroupName = typeLabel + ' ' + newIndex;

            if (type === 'why-us') {
                newGroupName = 'Why Us';
            }

            const lastRealItem = realItems.length > 0 ? realItems[realItems.length - 1] : items[items.length - 1];
            const clone = lastRealItem.cloneNode(true);

            const elementsToUpdate = [clone, ...clone.querySelectorAll('[data-key], [data-link-key], [data-style-key], [data-group]')];
            elementsToUpdate.forEach(el => {
                const attrs = ['data-key', 'data-link-key', 'data-style-key'];
                attrs.forEach(attr => {
                    const oldKey = el.getAttribute(attr);
                    if (oldKey) {
                        const parts = oldKey.split('_');
                        const indexPos = parts.findIndex(p => p !== '' && !isNaN(p));
                        if (indexPos !== -1) {
                            parts[indexPos] = newIndex;
                            el.setAttribute(attr, parts.join('_'));
                        }
                    }
                });

                el.setAttribute('data-group', newGroupName);

                if (el.hasAttribute('data-editable') || (el.tagName === 'SPAN' && el.hasAttribute('data-key')) || (el.tagName === 'STRONG' && el.hasAttribute('data-key'))) {
                    const key = el.getAttribute('data-key') || '';
                    const tag = el.tagName.toLowerCase();

                    if (key.includes('name') || key.includes('title') || (type === 'why-us' && tag === 'span')) {
                        el.innerHTML = 'New ' + typeLabel;
                    } else if (key.includes('desc') || key.includes('text')) {
                        el.innerHTML = 'New content goes here...';
                    } else if (key.endsWith('_num') || key.includes('_num_') || (type === 'why-us' && tag === 'strong')) {
                        el.innerHTML = String(newIndex).padStart(2, '0');
                    }
                }

                markTouched(el);
            });

            if (type === 'why-us') {
                clone.setAttribute('data-editable', 'why-item');
            }

            if (lastRealItem.nextSibling) {
                wrapper.insertBefore(clone, lastRealItem.nextSibling);
            } else {
                wrapper.appendChild(clone);
            }

            const iframeWin = document.getElementById('admin-viewport-frame').contentWindow;
            if (iframeWin && iframeWin.Swiper) {
                const swiperEl = wrapper.closest('.swiper');
                if (swiperEl && swiperEl.swiper) {
                    swiperEl.swiper.update();
                }
            }

            buildStructureTree();
            showToast(type.charAt(0).toUpperCase() + type.slice(1) + ' added successfully', 'success');

            clone.scrollIntoView({ behavior: 'smooth', block: 'center' });

            clone.style.outline = '2px solid var(--color-primary)';
            setTimeout(() => {
                clone.style.outline = 'none';
                clone.style.transition = 'outline 0.5s ease';
            }, 2000);
        }

        function restoreSection(sectionType) {
            if (!editorDoc) return;

            const sectionMap = {
                'banner': '#banner-section, #home',
                'experience': '#experience-section, #experience',
                'services': '#services-section, #services',
                'about': '#about-section, #about',
                'projects': '#projects-section, #creative-project, #project, #projects',
                'stats': '#stats-section, #stats',
                'brand': '#brand-section, #brand, #brands',
                'why-choose': '#why-choose-section, #choose-us, #why-choose',
                'testimonial': '#testimonial-section, #testimonial, #testimonials',
                'content': '#content-section',
                'blog': '#blog-section, #blog'
            };

            const selectors = sectionMap[sectionType];
            if (!selectors) {
                showToast(isGerman ? 'Unbekannte Sektion' : 'Unknown section', 'error');
                return;
            }

            let existingSection = null;
            const selectorList = selectors.split(',').map(s => s.trim());
            for (const selector of selectorList) {
                existingSection = editorDoc.querySelector(selector);
                if (existingSection) break;
            }

            if (existingSection && existingSection.style.display !== 'none') {
                existingSection.scrollIntoView({ behavior: 'smooth', block: 'center' });
                showToast(isGerman ? 'Zur Sektion gesprungen' : 'Jumped to section', 'info');
                return;
            }

            const possibleKeys = [sectionType + 'SectionBackup'];
            const ids = selectors.split(',').map(s => s.trim().replace(/^#/, ''));
            ids.forEach(id => {
                possibleKeys.push(id.replace(/-section$/, '') + 'SectionBackup');
            });

            console.log('Restoration: Checking possible backup keys:', possibleKeys);

            let sectionHtml = null;
            let foundKey = null;

            try {
                for (const key of possibleKeys) {
                    const backup = sessionStorage.getItem(key);
                    if (backup) {
                        console.log('Restoration: Found backup with key:', key);
                        sectionHtml = backup;
                        foundKey = key;
                        break;
                    }
                }

                if (foundKey) {
                    sessionStorage.removeItem(foundKey);
                } else {
                    console.warn('Restoration: No backup found for keys:', possibleKeys);
                }
            } catch (e) {
                console.warn('Could not restore section from backup:', e);
            }

            const defaultTemplates = {
                'banner': `
            <div id="banner-section" class="page-section">
                <div id="home" class="banner-style-one" style="background-image: url(assets/img/shape/1.png);" data-editable-bg="true" data-key="banner_shape_bg" data-group="Background">
                <div class="editable-section-wrapper" data-editable="section" data-key="section_banner" data-group="Banner > Content">
                    <div class="container">
                        <div class="row align-center">
                            <div class="col-lg-8 banner-one-item">
                                <h4 style="" data-editable="true" data-key="banner_subtitle">Your partner for Nearshoring & BPO</h4>
                                <h2 style="" data-editable="true" data-key="banner_title">FASTER <strong>GROWING.</strong><br>OPTIMIZED <strong>WORKING.</strong></h2>
                            </div>
                                <div class="col-lg-3 offset-lg-1 banner-one-item text-center" data-key="banner_circle_container">
                                <div class="curve-text">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 150 150" version="1.1" style="background-image: linear-gradient(90deg, #20C1F5, #49B9F2, #7675EC, #A04EE1, #D225D7, #F009D5);" data-editable-bg="true" data-key="curve_text_style" data-group="Circle">
                                        <path id="textPath" d="M 0,75 a 75,75 0 1,1 0,1 z"></path>
                                        <text>
                                            <textPath href="#textPath" data-editable="true" data-key="banner_cert_text" data-group="Circle">Certified Partner</textPath>
                                        </text>
                                    </svg>
                                    <a href="https://www.youtube.com/watch?v=ipUuoMCEbDQ" class="popup-youtube" style="" data-editable-link="true" data-key="banner_video" data-group="Circle" data-editable-style="true" data-style-key="banner_video_style">
                                        <i class="fas fa-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            </div>`,
                'experience': `
            <div id="experience-section" class="page-section">
                <div class="banner-animation-zoom">
                    <div class="editable-section-wrapper" data-editable="section" data-key="section_experience">
                        <div class="animation-zoom-banner" id="js-hero" style="background-image: url(assets/img/banner/1.jpg);" data-editable-bg="true" data-key="banner_hero_bg">
                        </div>
                        <div class="container">
                            <div class="row">
                                <div class="col-lg-6 offset-lg-6">
                                    <div class="experience-box" style="">
                                        <div class="inner-content">
                                            <h2 style="" data-editable="true" data-key="exp_title"><strong>5+</strong> Years of Proven Results</h2>
                                            <p data-editable="true" data-key="exp_text">Over five years of successful BPO and nearshoring projects for companies across Europe.</p>
                                            <a class="btn-animation" href="#about" style="" data-editable-link="true" data-key="exp_btn" data-editable-style="true" data-style-key="exp_btn_style"><i class="fas fa-arrow-right"></i> <span>WHY ENTRIKS?</span></a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>`,
                'services': `
            <div id="services-section" class="page-section" data-key="section_services">
                <div id="services" class="creative-services-area overflow-hidden default-padding">
                    <div id="services-anchor"></div>
                    <div class="container">
                        <div class="row">
                            <div class="col-lg-8">
                                <div class="site-heading">
                                    <h4 id="services-heading" class="sub-title leistungen" style="" data-editable="true" data-key="services_subtitle">Our Services</h4>
                                    <h2 class="title" style="" data-editable="true" data-key="services_title">Nearshoring & BPO<br>Efficient Teams for Your Success</h2>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="container container-stage">
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="services-item-one-items">
                                <div class="services-nav">
                                    <div class="nav-items">
                                        <div class="services-button-prev"></div>
                                        <div class="services-button-next"></div>
                                    </div>
                                </div>
                                <div class="services-carousel swiper">
                                    <div class="swiper-wrapper">
                                        <div class="swiper-slide" data-group="Service 1" data-key="service_1_wrapper">
                                            <div class="cteative-service-item">
                                                <div class="editable-image-wrapper" data-key="service_1_icon" data-group="Service 1">
                                                    <img src="assets/img/icon/1.png" alt="Icon">
                                                </div>
                                                <h4><a href="#" style="" data-editable="true" data-key="service_1_title" data-group="Service 1">Nearshoring – Dedicated Teams</a></h4>
                                                <p style="" data-editable="true" data-key="service_1_desc" data-group="Service 1">We build dedicated teams that work reliably within your tools and according to your standards.</p>
                                                <span style="" data-editable="true" data-key="service_1_num" data-editable-style="true" data-style-key="service_1_num_style" data-group="Service 1">01</span>
                                            </div>
                                        </div>
                                        <div class="swiper-slide" data-group="Service 2" data-key="service_2_wrapper">
                                            <div class="cteative-service-item">
                                                <div class="editable-image-wrapper" data-key="service_2_icon" data-group="Service 2">
                                                    <img src="assets/img/icon/2.png" alt="Icon">
                                                </div>
                                                <h4><a href="#" style="" data-editable="true" data-key="service_2_title" data-group="Service 2">BPO – Business Process Outsourcing</a></h4>
                                                <p style="" data-editable="true" data-key="service_2_desc" data-group="Service 2">We take over complete business processes end-to-end and ensure that SLAs are met.</p>
                                                <span style="" data-editable="true" data-key="service_2_num" data-editable-style="true" data-style-key="service_2_num_style" data-group="Service 2">02</span>
                                            </div>
                                        </div>
                                        <div class="swiper-slide" data-group="Service 3" data-key="service_3_wrapper">
                                            <div class="cteative-service-item">
                                                <div class="editable-image-wrapper" data-key="service_3_icon" data-group="Service 3">
                                                    <img src="assets/img/icon/3.png" alt="Icon">
                                                </div>
                                                <h4><a href="#" style="" data-editable="true" data-key="service_3_title" data-group="Service 3">Support & Customer Service</a></h4>
                                                <p style="" data-editable="true" data-key="service_3_desc" data-group="Service 3">Our customer support teams handle inquiries quickly, professionally, and in a structured manner.</p>
                                                <span style="" data-editable="true" data-key="service_3_num" data-editable-style="true" data-style-key="service_3_num_style" data-group="Service 3">03</span>
                                            </div>
                                        </div>
                                        <div class="swiper-slide" data-group="Service 4" data-key="service_4_wrapper">
                                            <div class="cteative-service-item">
                                                <div class="editable-image-wrapper" data-key="service_4_icon" data-group="Service 4">
                                                    <img src="assets/img/icon/4.png" alt="Icon">
                                                </div>
                                                <h4><a href="#" style="" data-editable="true" data-key="service_4_title" data-group="Service 4">Back Office & Operations</a></h4>
                                                <p style="" data-editable="true" data-key="service_4_desc" data-group="Service 4">We take over recurring back office tasks, data maintenance, and operational workflows.</p>
                                                <span style="" data-editable="true" data-key="service_4_num" data-editable-style="true" data-style-key="service_4_num_style" data-group="Service 4">04</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            </div>`,
                'about': `
            <div id="about-section" class="page-section">
                <style>
                    .modern-about-header { margin-bottom: 40px; }
                    .modern-about-header .sub-title { color: var(--color-primary); text-transform: uppercase; font-weight: 800; font-size: 0.9rem; letter-spacing: 2px; margin-bottom: 10px; display: block; }
                    .modern-about-list li::after { border-color: currentColor !important; color: currentColor !important; opacity: 0.8; }
                </style>
                <div class="about-area default-padding-bottom relative">
                    <div class="editable-section-wrapper" data-editable="section" data-key="section_about">
                        <div class="blur-bg-theme"></div>
                        <div class="container">
                            <div class="row modern-about-header">
                                <div class="col-lg-8 offset-lg-2 text-center">
                                    <h2 class="title" style="" data-editable="true" data-key="about_main_title">Efficient Processes for Your Business Success</h2>
                                </div>
                            </div>
                            <div class="row align-center">
                                <div class="col-xl-7 col-lg-6">
                                    <div class="about-style-one-thumb animate" data-animate="fadeInUp">
                                        <div class="editable-image-wrapper" data-key="about_img_1">
                                            <img src="assets/img/about/1.jpg" alt="About Image">
                                        </div>
                                        <div class="fun-fact text-light animate" data-animate="fadeInDown" data-duration="1s">
                                            <div class="counter">
                                                <div class="timer" data-to="36" data-speed="2000" data-editable="true" data-key="about_stat_number" data-group="About Stats">36</div>
                                                <div class="operator" style="" data-editable="true" data-key="about_stat_operator" data-group="About Stats">K</div>
                                            </div>
                                            <span class="medium" style="" data-editable="true" data-key="about_stat_text" data-group="About Stats">Completed Projects</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-xl-5 col-lg-6 pl-80 pl-md-15 pl-xs-15">
                                    <div class="about-style-one">
                                        <p style="" data-editable="true" data-key="about_main_text">We handle operational tasks, support, and back office processes with clear structures.</p>
                                        <ul class="list-simple modern-about-list" data-group="About Features">
                                            <li style="" data-editable="true" data-key="about_list_item_1" data-editable-style="true" data-group="About Features">Transparent communication and clear KPIs</li>
                                            <li style="" data-editable="true" data-key="about_list_item_2" data-editable-style="true" data-group="About Features">Reliable, structured process management</li>
                                        </ul>
                                        <a href="#about" class="arrow-btn" style="" data-editable-link="true" data-key="about_btn" data-editable-style="true" data-group="About Video"><i class="fas fa-arrow-right"></i></a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>`,
                'projects': `
            <div id="projects-section" class="page-section">
                <div id="projects" class="portfolio-style-one-area default-padding bg-gray">
                    <div class="editable-section-wrapper" data-editable="section" data-key="section_projects">
                        <div class="container">
                            <div class="heading-left">
                                <div class="row">
                                    <div class="col-lg-6">
                                        <div class="content-left">
                                            <h4 class="sub-title projekte" style="" data-editable="true" data-key="projects_subtitle">Popular Projects</h4>
                                            <h2 class="title" style="" data-editable="true" data-key="projects_title">Featured Works</h2>
                                        </div>
                                    </div>
                                    <div class="col-lg-5 offset-lg-1">
                                        <p data-editable="true" data-key="projects_desc">We work with companies across various industries and deliver reliable results.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="container">
                            <div class="row mt--50 mt-md-3-0 mt-xs--30">
                                <div class="col-lg-6 item-center">
                                    <div class="portfolio-style-one animate" data-animate="fadeInDown">
                                        <div class="thumb-zoom" data-group="Project 1">
                                            <div class="editable-image-wrapper" data-key="project_1_img" data-group="Project 1">
                                                <img src="assets/img/projects/1.jpg" alt="Image Not Found">
                                            </div>
                                        </div>
                                        <div class="pf-item-info" data-group="Project 1">
                                            <div class="content-info">
                                                <span style="" data-editable="true" data-key="project_1_cat" data-group="Project 1">Marketing</span>
                                                <h2><a href="#" style="" data-editable="true" data-key="project_1_title" data-editable-link="true" data-link-key="project_1_link" data-group="Project 1">Photo Shooting and Editing</a></h2>
                                            </div>
                                            <div class="button">
                                                <a href="#" class="pf-btn" style="" data-editable-link="true" data-key="project_1_btn" data-link-key="project_1_link" data-editable-style="true" data-style-key="project_1_btn_style" data-group="Project 1"><i class="fas fa-arrow-right"></i></a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-6 item-center">
                                    <div class="portfolio-style-one animate" data-animate="fadeInUp">
                                        <div class="thumb-zoom" data-group="Project 2">
                                            <div class="editable-image-wrapper" data-key="project_2_img" data-group="Project 2">
                                                <img src="assets/img/projects/2.jpg" alt="Image Not Found">
                                            </div>
                                        </div>
                                        <div class="pf-item-info" data-group="Project 2">
                                            <div class="content-info">
                                                <span style="" data-editable="true" data-key="project_2_cat" data-group="Project 2">Creative</span>
                                                <h2><a href="#" style="" data-editable="true" data-key="project_2_title" data-editable-link="true" data-link-key="project_2_link" data-group="Project 2">Quality in Industrial Design</a></h2>
                                            </div>
                                            <div class="button">
                                                <a href="#" class="pf-btn" style="" data-editable-link="true" data-key="project_2_btn" data-link-key="project_2_link" data-editable-style="true" data-style-key="project_2_btn_style" data-group="Project 2"><i class="fas fa-arrow-right"></i></a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>`,
                'stats': `
            <div id="stats-section" class="page-section">
                <div class="fun-factor-circle-area default-padding-bottom bg-gray">
                    <div class="container">
                        <div class="row">
                            <div class="col-lg-12">
                                <div class="fun-fact-circle-lists">
                                    <div class="fun-fact animate" data-animate="fadeInDown">
                                        <div class="counter">
                                            <div class="timer" data-editable="true" data-key="stats_1_num" data-to="120" data-speed="3000">120</div>
                                            <div class="operator" style="" data-editable="true" data-key="stats_1_op">+</div>
                                        </div>
                                        <span class="medium" style="" data-editable="true" data-key="stats_1_text">Employees</span>
                                    </div>
                                    <div class="fun-fact animate" data-animate="fadeInUp" data-duration="0.5s">
                                        <div class="counter">
                                            <div class="timer" data-editable="true" data-key="stats_2_num" data-to="98" data-speed="3000">98</div>
                                            <div class="operator" style="" data-editable="true" data-key="stats_2_op">%</div>
                                        </div>
                                        <span class="medium" style="" data-editable="true" data-key="stats_2_text">Customer Satisfaction</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>`,
                'brand': `
            <div id="brand-section" class="page-section">
                <div class="brand-area relative overflow-hidden text-light">
                    <div class="editable-section-wrapper" data-editable="section" data-key="section_brand" data-group="Brand">
                        <div class="brand-style-one">
                            <div class="container-fill">
                                <div class="row">
                                    <div class="col-lg-12">
                                        <div class="brand-items custom-gradient-banner" data-group="Brand Items">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>`,
                'why-choose': `
            <div id="why-choose-section" class="page-section">
                <div class="choose-us-style-one-area default-padding bg-gray" id="about">
                    <div class="editable-section-wrapper" data-editable="section" data-key="section_why_choose">
                        <div class="container">
                            <div class="row align-center">
                                <div class="col-lg-6">
                                    <div class="choose-us-style-one-thumb">
                                        <div class="editable-image-wrapper" data-key="about_img_2" data-group="Why Us Image">
                                            <img src="assets/img/about/2.jpg" alt="Image Not Found">
                                        </div>
                                        <a href="https://www.youtube.com/watch?v=ipUuoMCEbDQ" class="popup-youtube video-play-button" data-editable-link="true" data-key="why_video_wrapper" data-link-key="why_video_href" data-group="Why Us Video">
                                            <i class="fas fa-play" style=""></i>
                                            <div class="effect"></div>
                                        </a>
                                    </div>
                                </div>
                                <div class="col-lg-5 offset-lg-1">
                                    <div class="choose-us-style-one d-flex-between">
                                        <div class="top-info">
                                            <h4 class="sub-title" style="" data-editable="true" data-key="why_subtitle">WHY ENTRIKS</h4>
                                            <h2 class="title" style="" data-editable="true" data-key="why_title">Efficient Nearshoring Teams for Sustainable Growth</h2>
                                        </div>
                                        <div class="bottom-info">
                                            <ul class="list-item" data-group="Why Us">
                                                <li style="" data-editable="why-item" data-key="why_list_1_row" data-group="Why Us">
                                                    <span style="pointer-events: none;" data-key="why_list_1">Scalable Teams</span>
                                                    <strong style="" data-key="why_list_1_num">01</strong>
                                                </li>
                                                <li style="" data-editable="why-item" data-key="why_list_2_row" data-group="Why Us">
                                                    <span style="pointer-events: none;" data-key="why_list_2">German Quality</span>
                                                    <strong style="" data-key="why_list_2_num">02</strong>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>`,
                'testimonial': `
            <div id="testimonial-section" class="page-section">
                <div class="testimonial-style-one-area default-padding-top">
                    <div class="editable-section-wrapper" data-editable="section" data-key="section_testimonial">
                        <div class="container">
                            <div class="heading-left">
                                <div class="row">
                                    <div class="col-lg-6">
                                        <div class="content-left">
                                            <h4 class="sub-title testimonial" style="" data-editable="true" data-key="testimonial_subtitle">Testimonials</h4>
                                            <h2 class="title" style="" data-editable="true" data-key="testimonial_title">What Our Clients Say</h2>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="container">
                            <div class="row">
                                <div class="testimonial-one-carousel-box">
                                    <div class="testimonial-style-one-carousel swiper">
                                        <div class="swiper-wrapper">
                                            <div class="swiper-slide" data-group="Testimonial 1" data-key="testimonial_1_wrapper">
                                                <div class="testimonial-style-one">
                                                    <div class="provider" data-group="Testimonial 1">
                                                        <div class="thumb" data-group="Testimonial 1">
                                                            <div class="editable-image-wrapper" data-key="testimonial_1_img" data-group="Testimonial 1">
                                                                <img src="assets/img/team/9.jpg" alt="Image Not Found">
                                                            </div>
                                                            <div class="quote" data-group="Testimonial 1">
                                                                <img src="assets/img/shape/quote-big.png" alt="Quote">
                                                            </div>
                                                        </div>
                                                        <div class="info" data-group="Testimonial 1">
                                                            <h4 style="color: white;" data-editable="true" data-key="testimonial_1_name" data-group="Testimonial 1">Julia Meissner</h4>
                                                            <span style="" data-editable="true" data-key="testimonial_1_role" data-group="Testimonial 1">Client</span>
                                                        </div>
                                                    </div>
                                                    <div class="content" data-group="Testimonial 1">
                                                        <div class="rating" style="" data-editable="true" data-type="html" data-key="testimonial_1_rating" data-group="Testimonial 1">
                                                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                                                        </div>
                                                        <p style="" data-editable="true" data-key="testimonial_1_text" data-group="Testimonial 1">"ENTRIKS has provided excellent support."</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="testimonial-one-swiper-nav">
                                        <div class="testimonial-one-pagination"></div>
                                        <div class="testimonial-one-button-prev"></div>
                                        <div class="testimonial-one-button-next"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>`,
                'content': `
            <div id="content-section" class="page-section" data-key="section_content">
                <div class="multi-section overflow-hidden bg-dark-secondary text-light">
                    <div class="thecontainer">
                        <div class="panel overflow-hidden" data-group="Awards">
                            <div class="bg-shape-top">
                                <div class="editable-image-wrapper" data-key="multi_sec_bg_shape" data-group="Awards > Awards Shape">
                                    <img src="assets/img/shape/bg-shape-1.png" alt="Image Not Found">
                                </div>
                            </div>
                            <div class="container overflow-hidden">
                                <div class="row align-center">
                                    <div class="col-lg-4">
                                        <div class="site-title">
                                            <h4 class="sub-title uitzeichnungen" data-editable="true" data-key="awards_subtitle" data-group="Awards">AWARDS & ACHIEVEMENTS</h4>
                                            <h2 class="title" data-editable="true" data-key="awards_title" data-group="Awards">Awards & Recognition</h2>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="panel overflow-hidden bg-gradient">
                            <div class="container overflow-hidden">
                                <div class="expertise-area text-center">
                                    <div class="row">
                                        <div class="col-lg-12">
                                            <div class="site-title">
                                                <h4 class="sub-title" style="" data-editable="true" data-key="expertise_subtitle" data-group="Expertise">OUR EXPERTISE</h4>
                                                <h2 class="title" style="" data-editable="true" data-key="expertise_title" data-group="Expertise">Your Partner for Efficient Process Solutions</h2>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>`,
                'blog': `
            <div id="blog-section" class="page-section">
                <div class="blog-area home-blog default-padding bottom-less">
                    <div class="editable-section-wrapper" data-editable="section" data-key="section_blog">
                        <div class="container">
                            <div class="row">
                                <div class="col-lg-8 offset-lg-2">
                                    <div class="site-heading text-center">
                                        <h4 class="sub-title none" style="" data-editable="true" data-key="blog_subtitle">News & Events</h4>
                                        <h2 class="title" style="" data-editable="true" data-key="blog_title">Latest Blog Posts</h2>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>`
            };

            if (!sectionHtml && defaultTemplates[sectionType]) {
                console.log('Restoration: No backup found. Using default template for:', sectionType);
                sectionHtml = defaultTemplates[sectionType];
            }

            if (existingSection) {
                existingSection.style.display = '';
                existingSection.style.visibility = 'visible';
                existingSection.style.opacity = '1';

                const sectionKey = existingSection.querySelector('[data-key^="section_"]');
                if (sectionKey) {
                    const key = sectionKey.getAttribute('data-key');
                    deletedStructuralKeys.delete(key);
                    touchedKeys.add(key);
                } else if (existingSection.id) {
                    const derivedKey = 'section_' + existingSection.id.replace(/-section$/, '').replace(/-/g, '_');
                    deletedStructuralKeys.delete(derivedKey);
                    touchedKeys.add(derivedKey);
                }

                existingSection.querySelectorAll('[data-key]').forEach(el => {
                    const k = el.getAttribute('data-key');
                    if (k) deletedStructuralKeys.delete(k);
                });

                hasUnsavedStructureChanges = true;
                updateSaveButtonState();
                buildStructureTree();
                const _snapshotExisting = Array.from(editorDoc.querySelectorAll('.page-section'))
                    .filter(s => s.id)
                    .map(s => ({ type: s.id.replace(/-section$/, '').replace(/-/g, '_'), id: s.id }));
                fetch('backend/save_structure.php', {
                    method: 'POST',
                    body: JSON.stringify({ page_id: PAGE_ID, structure: _snapshotExisting }),
                    headers: { 'Content-Type': 'application/json' }
                }).catch(() => { });
                showToast(isGerman ? 'Sektion wiederhergestellt' : 'Section restored', 'success');
                existingSection.scrollIntoView({ behavior: 'smooth', block: 'center' });
            } else if (sectionHtml) {
                const body = editorDoc.body;
                const tempDiv = editorDoc.createElement('div');
                tempDiv.innerHTML = sectionHtml;
                let restoredSection = tempDiv.firstElementChild;
                if (restoredSection) {
                    if (
                        sectionType === 'banner' &&
                        (restoredSection.id === 'home' || restoredSection.getAttribute('data-key') === 'banner_shape_bg')
                    ) {
                        const wrapper = editorDoc.createElement('div');
                        wrapper.id = 'banner-section';
                        wrapper.className = 'page-section';
                        wrapper.appendChild(restoredSection);
                        restoredSection = wrapper;
                        console.log('🔧 Banner backup was #home only — wrapped in #banner-section.page-section');
                    }

                    if (!restoredSection.classList.contains('page-section')) {
                        restoredSection.classList.add('page-section');
                    }
                    const viewport = editorDoc.querySelector('.viewport') || body;
                    let inserted = false;

                    if (sectionType === 'banner') {
                        console.log('Restoration: Prepending Banner to viewport');
                        if (viewport.firstElementChild) {
                            viewport.insertBefore(restoredSection, viewport.firstElementChild);
                        } else {
                            viewport.appendChild(restoredSection);
                        }
                        inserted = true;
                    } else {
                        const footer = editorDoc.querySelector('footer, #site-footer, .site-footer');
                        if (footer && footer.parentNode) {
                            console.log('Restoration: Inserting before footer');
                            footer.parentNode.insertBefore(restoredSection, footer);
                            inserted = true;
                        }
                    }

                    if (!inserted) {
                        console.log('Restoration: Appending to viewport');
                        viewport.appendChild(restoredSection);
                    }

                    if (sectionType === 'banner') {
                        console.log('Finalizing Banner restoration - ensuring "full" components');

                        const bgEl = restoredSection.querySelector('[data-key="banner_shape_bg"]');
                        if (!bgEl) {
                            console.warn('⚠️ Banner background (banner_shape_bg) still missing after wrap attempt — backup HTML may be incomplete.');
                        } else {
                            console.log('✅ Banner background element (banner_shape_bg) present:', bgEl);
                        }

                        const row = restoredSection.querySelector('.row.align-center');
                        if (row && !restoredSection.querySelector('[data-key="banner_circle_container"]')) {
                            console.log('Banner missing Circle. Attempting restoration...');
                            const backup = sessionStorage.getItem('bannerCircleBackup');
                            let circleHtml = backup;
                            if (!circleHtml) {
                                circleHtml = `
                                <div class="col-lg-3 offset-lg-1 banner-one-item text-center" data-key="banner_circle_container">
                                    <div class="curve-text">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 150 150" version="1.1" style="background-image: linear-gradient(90deg, #20C1F5, #49B9F2, #7675EC, #A04EE1, #D225D7, #F009D5);" data-editable-bg="true" data-key="curve_text_style" data-group="Banner > Circle">
                                            <path id="textPath" d="M 0,75 a 75,75 0 1,1 0,1 z"></path>
                                            <text>
                                                <textPath href="#textPath" data-editable="true" data-key="banner_cert_text" data-group="Banner > Circle">Certified Partner</textPath>
                                            </text>
                                        </svg>
                                        <a href="https://www.youtube.com/watch?v=ipUuoMCEbDQ" class="popup-youtube" style="" data-editable-link="true" data-key="banner_video" data-group="Banner > Circle" data-editable-style="true" data-style-key="banner_video_style">
                                            <i class="fas fa-arrow-right"></i>
                                        </a>
                                    </div>
                                </div>`;
                            }
                            const titleCol = row.querySelector('.col-lg-8');
                            if (titleCol) {
                                titleCol.insertAdjacentHTML('afterend', circleHtml);
                            } else {
                                row.insertAdjacentHTML('beforeend', circleHtml);
                            }
                        }
                    }

                    const innerSectionKey = restoredSection.querySelector('[data-key^="section_"]');
                    const sectionStructKey = innerSectionKey
                        ? innerSectionKey.getAttribute('data-key')
                        : (restoredSection.id ? 'section_' + restoredSection.id.replace(/-section$/, '').replace(/-/g, '_') : null);
                    if (sectionStructKey) {
                        deletedStructuralKeys.delete(sectionStructKey);
                        touchedKeys.add(sectionStructKey);
                    }

                    restoredSection.querySelectorAll('[data-key]').forEach(el => {
                        const k = el.getAttribute('data-key');
                        if (k) deletedStructuralKeys.delete(k);
                    });

                    hasUnsavedStructureChanges = true;
                    updateSaveButtonState();
                    buildStructureTree();
                    const _snapshotNew = Array.from(editorDoc.querySelectorAll('.page-section'))
                        .filter(s => s.id)
                        .map(s => ({ type: s.id.replace(/-section$/, '').replace(/-/g, '_'), id: s.id }));
                    fetch('backend/save_structure.php', {
                        method: 'POST',
                        body: JSON.stringify({ page_id: PAGE_ID, structure: _snapshotNew }),
                        headers: { 'Content-Type': 'application/json' }
                    }).catch(() => { });
                    showToast(isGerman ? 'Sektion wiederhergestellt' : 'Section restored', 'success');
                    restoredSection.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            } else {
                showToast(isGerman ? 'Sektion kann nicht wiederhergestellt werden' : 'Cannot restore section', 'error');
            }
        }

        function insertTypography(type) {
            if (!editorDoc) return;

            const uid = Date.now().toString(36);
            let useWrapper = false;

            switch (type) {
                case 'subtitle':
                    html = `<h4 class="sub-title" data-editable="true" data-key="subtitle_${uid}">${L.subtitle}</h4>`;
                    useWrapper = true;
                    break;
                case 'title':
                    html = `<h2 class="title" data-editable="true" data-key="title_${uid}">${L.title}</h2>`;
                    useWrapper = true;
                    break;
                case 'text':
                    html = `<p data-editable="true" data-key="text_${uid}">${L.textContent}</p>`;
                    break;
                case 'link':
                    html = `<a href="#" class="btn-animation" data-editable-link="true" data-key="link_${uid}"><i class="fas fa-arrow-right"></i> <span>${L.link}</span></a>`;
                    break;
                case 'button':
                    html = `<button class="btn btn-theme effect btn-md" data-editable-link="true" data-key="button_${uid}"><span>${L.button}</span></button>`;
                    break;
            }

            if (!html) return;

            let target = activeElement;
            let container = null;

            if (target) {
                if (target.getAttribute('data-editable') === 'section' || target.classList.contains('editable-section-wrapper')) {
                    container = target;
                    target = null;
                } else {
                    container = target.closest('.editable-section-wrapper') || target.parentElement;
                }
            }

            if (!container) {
                container = editorDoc.querySelector('.editable-section-wrapper');
            }

            if (!container) {
                showToast('Target container not found', 'error');
                return;
            }

            if (useWrapper) {
                let existingHeading = null;
                if (target) {
                    existingHeading = target.closest('.site-heading');
                    if (!existingHeading && target.classList.contains('site-heading')) existingHeading = target;
                }

                if (existingHeading) {
                    const temp = document.createElement('div');
                    temp.innerHTML = html.trim();
                    const newEl = temp.firstElementChild;

                    if (target && target.parentElement === existingHeading) {
                        existingHeading.insertBefore(newEl, target.nextElementSibling);
                    } else {
                        existingHeading.appendChild(newEl);
                    }

                    markTouched(newEl);
                    buildStructureTree();
                    showToast((L[type] || type) + ' added to heading', 'success');
                    newEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    if (typeof selectElement === 'function') selectElement(newEl, 'text');
                    return;
                } else {
                    html = `<div class="site-heading">${html}</div>`;
                }
            }

            const temp = document.createElement('div');
            temp.innerHTML = html.trim();
            const newEl = temp.firstElementChild || temp.firstChild;

            if (target && target.nextElementSibling) {
                container.insertBefore(newEl, target.nextElementSibling);
            } else if (target) {
                target.insertAdjacentElement('afterend', newEl);
            } else {
                let bestAppendTarget = container;

                if (container.classList.contains('editable-section-wrapper')) {
                    const existingContainer = container.querySelector('.container') || container.querySelector('.site-container');
                    if (existingContainer) {
                        bestAppendTarget = existingContainer;
                        const existingRow = existingContainer.querySelector('.row');
                        if (existingRow) {
                            const existingCol = existingRow.querySelector('[class*="col-"]');
                            if (existingCol) {
                                bestAppendTarget = existingCol;
                            } else {
                                bestAppendTarget = existingRow;
                            }
                        }
                    } else if (useWrapper || type === 'text') {
                        const gridHtml = `<div class="container"><div class="row"><div class="col-lg-8 site-content-wrapper"></div></div></div>`;
                        const gridTemp = document.createElement('div');
                        gridTemp.innerHTML = gridHtml.trim();
                        const gridEl = gridTemp.firstElementChild;
                        container.appendChild(gridEl);
                        bestAppendTarget = gridEl.querySelector('.col-lg-8');
                    }
                }

                bestAppendTarget.appendChild(newEl);
            }

            markTouched(newEl);
            buildStructureTree();
            showToast((L[type] || type) + ' added successfully', 'success');

            newEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
            newEl.style.outline = '2px solid var(--color-primary)';
            setTimeout(() => {
                newEl.style.outline = 'none';
                newEl.style.transition = 'outline 0.5s ease';
            }, 2000);

            if (typeof selectElement === 'function') {
                selectElement(newEl, type === 'link' ? 'link' : 'text');
            }
        }

        function moveElement(direction) {
            if (!activeElement) return;
            const parent = activeElement.parentElement;
            if (!parent) return;

            if (direction === 'up') {
                const prev = activeElement.previousElementSibling;
                if (prev) {
                    parent.insertBefore(activeElement, prev);
                    markTouched(activeElement);
                    markTouched(prev);
                    activeElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    if (typeof showToast === 'function') showToast(isGerman ? 'Nach oben verschoben' : 'Moved up');
                }
            } else {
                const next = activeElement.nextElementSibling;
                if (next) {
                    parent.insertBefore(next, activeElement);
                    markTouched(activeElement);
                    markTouched(next);
                    activeElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    if (typeof showToast === 'function') showToast(isGerman ? 'Nach unten verschoben' : 'Moved down');
                }
            }
        }

        const btnBackToElements = document.getElementById('btn-back-to-elements');
        if (btnBackToElements) {
            btnBackToElements.onclick = (e) => {
                e.preventDefault();
                e.stopPropagation();
                closePropertiesPanel();
            };
        }

        const viewportContainer = document.getElementById('admin-viewport-frame');
        const responsiveBtns = document.querySelectorAll('.responsive-btn');

        responsiveBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                responsiveBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                const id = btn.id;
                viewportContainer.style.transition = 'width 0.3s cubic-bezier(0.4, 0, 0.2, 1)';

                let width = '100%';
                let label = '100%';

                switch (id) {
                    case 'btn-device-widescreen':
                        width = '100%';
                        label = '1600px+';
                        break;
                    case 'btn-device-desktop':
                        width = '1440px';
                        label = '1440px';
                        break;
                    case 'btn-device-laptop':
                        width = '1366px';
                        label = '1366px';
                        break;
                    case 'btn-device-tablet-land':
                        width = '1024px';
                        label = '1024px';
                        break;
                    case 'btn-device-tablet-port':
                        width = '768px';
                        label = '768px';
                        break;
                    case 'btn-device-mobile-land':
                        width = '600px';
                        label = '600px';
                        break;
                    case 'btn-device-mobile-port':
                        width = '375px';
                        label = '375px';
                        break;
                    default:
                        width = '100%';
                }

                viewportContainer.style.width = width;
            });
        });

        const btnShare = document.querySelector('.btn-share');
        if (btnShare) {
            btnShare.addEventListener('click', () => {
                const dummyUrl = window.location.href.split('?')[0];
                navigator.clipboard.writeText(dummyUrl).then(() => {
                    showToast(L.copied, 'success');
                });
            });
        }

        editorDoc.addEventListener('click', function (e) {
            const link = e.target.closest('a');
            if (link) {
                e.preventDefault();
            }

            let target = null;
            let type = null;

            if (e.target.closest('.editable-image-wrapper')) {
                target = e.target.closest('.editable-image-wrapper');
                type = 'image';
            }
            else if (e.target.closest('[data-editable-link="true"]')) {
                target = e.target.closest('[data-editable-link="true"]');
                type = 'link';
            }
            else if (e.target.closest('[data-editable="true"]')) {
                const el = e.target.closest('[data-editable="true"]');
                target = el;
                type = el.getAttribute('data-type') || 'text';
            }
            else if (e.target.closest('[data-editable-bg="true"]')) {
                target = e.target.closest('[data-editable-bg="true"]');
                type = 'bg';
            }
            else if (e.target.closest('[data-editable-style="true"]')) {
                target = e.target.closest('[data-editable-style="true"]');
                type = 'style-only';
            }
            else if (e.target.closest('[data-editable-attr]')) {
                target = e.target.closest('[data-editable-attr]');
                type = 'attribute';
            }
            else if (e.target.closest('[data-editable]')) {
                const el = e.target.closest('[data-editable]');
                const val = el.getAttribute('data-editable');
                if (val !== 'false') {
                    target = el;
                    type = val === 'true' ? 'text' : val;
                }
            }
            else {
                const tag = e.target.tagName.toLowerCase();
                const textTags = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'span', 'strong', 'em', 'b', 'i', 'small', 'label', 'li', 'blockquote', 'svg', 'path', 'text', 'textPath'];
                if (textTags.includes(tag)) {
                    const editableParent = e.target.closest('[data-editable], [data-editable-link], [data-editable-bg], [data-editable-attr], .editable-image-wrapper');
                    if (editableParent) {
                        target = editableParent;
                    }
                }
            }

            if (target) {
                selectElement(target, type);
            } else {
                closePropertiesPanel();
            }
        });

        function openPropertiesPanel() {
            if (propertiesPanel) {
                propertiesPanel.style.display = 'flex';
                if (sidebar) sidebar.classList.add('properties-active');
            }
        }

        function closePropertiesPanel() {
            if (propertiesPanel) {
                propertiesPanel.style.display = 'none';
                if (sidebar) sidebar.classList.remove('properties-active');
            }
            if (activeElement) activeElement.classList.remove('admin-selected-element');
            activeElement = null;
            activeType = null;

            document.querySelectorAll('.structure-label').forEach(l => l.classList.remove('active'));
        }

        function selectElement(el, type) {
            if (!el) return;

            editorDoc.querySelectorAll('.admin-selected-element').forEach(node => {
                node.classList.remove('admin-selected-element');
                node.removeAttribute('data-type');
            });

            activeElement = el;

            if (!type) {
                const editableParent = el.closest ? el.closest('[data-editable]:not([data-editable="true"]):not([data-editable="section"])') : null;
                if (editableParent) {
                    el = editableParent;
                    type = el.getAttribute('data-editable');
                } else {
                    const tag = el.tagName.toLowerCase();
                    if (el.classList.contains('editable-image-wrapper') || el.hasAttribute('data-key') && /_img(_|$)/i.test(el.getAttribute('data-key'))) {
                        type = 'image';
                        if (!el.classList.contains('editable-image-wrapper')) {
                            const imgWrap = el.closest('.editable-image-wrapper');
                            if (imgWrap) el = imgWrap;
                        }
                    } else if (el.hasAttribute('data-editable-link')) {
                        type = 'link';
                    } else if (el.hasAttribute('data-editable-bg')) {
                        type = 'bg';
                    } else if (el.hasAttribute('data-editable-style')) {
                        type = 'style-only';
                    } else if (el.hasAttribute('data-editable') && !['true', 'section'].includes(el.getAttribute('data-editable'))) {
                        type = el.getAttribute('data-editable');
                    } else if (['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'span', 'strong', 'em', 'b', 'i', 'small', 'label', 'li', 'blockquote', 'svg', 'path', 'text', 'textPath'].includes(tag)) {
                        type = 'text';
                    } else {
                        type = el.getAttribute('data-type') || 'container';
                    }
                }
            }

            activeType = type;

            activeElement.classList.add('admin-selected-element');
            activeElement.setAttribute('data-type', type || 'container');
            activeTab = 'content';

            try {
                activeElement._pairedOperator = null;

                const key = activeElement.getAttribute && activeElement.getAttribute('data-key');
                if (key) {
                    const clean = key.replace(/_\d{10,}$/, '').replace(/_\d+$/, '');
                    const base = clean.replace(/_(num|nmb|number|count|stat_num|stat|value|nummer)$/i, '').replace(/_$/, '');

                    if (base) {
                        const section = activeElement.closest ? activeElement.closest('.page-section') : null;
                        const scope = section || activeElement.parentElement || editorDoc;
                        const candidates = scope.querySelectorAll ? scope.querySelectorAll('[data-key]') : [];
                        for (let i = 0; i < candidates.length; i++) {
                            const k = candidates[i].getAttribute('data-key') || '';
                            const kc = k.replace(/_\d{10,}$/, '').replace(/_\d+$/, '');
                            if (kc.indexOf(base) === 0 && /(_op($|_)|operator|_opt($|_))/i.test(kc)) {
                                activeElement._pairedOperator = candidates[i];
                                candidates[i]._pairedNumber = activeElement;
                                break;
                            }
                        }
                    }
                }
            } catch (err) {
            }

            const tabBtns = document.querySelectorAll('.properties-panel [data-tab]');
            tabBtns.forEach(b => b.classList.remove('active'));
            const contentBtn = document.querySelector('.properties-panel [data-tab="content"]');
            if (contentBtn) contentBtn.classList.add('active');

            openPropertiesPanel();
            renderSidebarControls();
            syncStructureTreeSelection(el);
        }

        function syncStructureTreeSelection(element) {
            document.querySelectorAll('.structure-label').forEach(l => l.classList.remove('active'));

            if (element && element._structureLabel) {
                const label = element._structureLabel;
                label.classList.add('active');
                let parentData = label.closest('li.structure-item');
                while (parentData) {
                    const parentUl = parentData.parentElement;
                    if (parentUl && parentUl.classList.contains('structure-children')) {
                        parentUl.style.display = 'block';
                        const parentLi = parentUl.parentElement;
                        if (parentLi && parentLi.classList.contains('structure-item')) {
                            parentLi.classList.add('expanded');
                            const toggle = parentLi.querySelector('.structure-toggle');
                            if (toggle) toggle.style.transform = 'rotate(90deg)';

                            parentData = parentLi;
                        } else {
                            break;
                        }
                    } else {
                        break;
                    }
                }

                setTimeout(() => {
                    label.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
                }, 100);
            }
        }

        function buildStructureTree() {
            const container = document.getElementById('structure-content');
            container.innerHTML = '';
            const tree = document.createElement('ul');
            tree.className = 'structure-tree';

            const addNode = (label, iconSvg, element, parentList, isContainer = false, groupItems = null) => {
                const li = document.createElement('li');
                li.className = 'structure-item';

                const div = document.createElement('div');
                div.className = 'structure-label';

                const toggleSpan = document.createElement('span');
                toggleSpan.className = 'structure-toggle';
                toggleSpan.innerHTML = heroIcons.chevronRight;
                toggleSpan.style.visibility = 'hidden';

                let prettyLabel = label || '';
                if (!isContainer) {
                    let tempLabel = (label || '').replace(/_?\s?\d{10,}/g, '');
                    tempLabel = tempLabel.replace(/_en$/i, '');

                    const indexMatch = tempLabel.match(/_(\d+)(?:_|$)/);
                    let indexSuffix = '';
                    if (indexMatch) {
                        indexSuffix = ' ' + indexMatch[1];
                        tempLabel = tempLabel.replace(/_(\d+)(?:_|$)/, '_').replace(/__+/g, '_').replace(/^_|_$/g, '');
                    }

                    try {
                        if (indexSuffix && element) {
                            const grp = (element.getAttribute && (element.getAttribute('data-group') || element.dataset && element.dataset.group)) || '';
                            if (grp && /Why Us Image/i.test(grp)) {
                                indexSuffix = '';
                            }
                        }
                    } catch (err) { }

                    const rawLabel = (label || '').toLowerCase();
                    if (/why_video_wrapper/i.test(rawLabel)) {
                        prettyLabel = 'Why Us Video';
                    } else if (/about_btn/i.test(rawLabel)) {
                        prettyLabel = 'Video';
                    } else if (/banner_video/i.test(rawLabel)) {
                        prettyLabel = 'Video';
                    } else if (/contact_link/i.test(rawLabel)) {
                        prettyLabel = 'Contact Href';
                    } else if (/(video|youtube|play|vid)/i.test(rawLabel)) {
                        prettyLabel = isGerman ? 'Video' : 'Video';
                    } else if (/(_href|_link)$/i.test(rawLabel)) {
                        prettyLabel = isGerman ? 'Href' : 'Href';
                    } else {
                        let parts = tempLabel.split('_');
                        let name = parts.pop();

                        const abbrevMap = {
                            'img': (isGerman ? 'Bild' : 'Image'),
                            'image': (isGerman ? 'Bild' : 'Image'),
                            'title': (isGerman ? 'Titel' : 'Title'),
                            'subtitle': (isGerman ? 'Untertitel' : 'Subtitle'),
                            'text': (isGerman ? 'Text' : 'Text'),
                            'desc': (isGerman ? 'Text' : 'Text'),
                            'txt': (isGerman ? 'Text' : 'Text'),
                            'name': (isGerman ? 'Name' : 'Name'),
                            'role': (isGerman ? 'Rolle' : 'Role'),
                            'rating': (isGerman ? 'Bewertung' : 'Rating'),
                            'link': (isGerman ? 'Link' : 'Link'),
                            'btn': (isGerman ? 'Button' : 'Button'),
                            'button': (isGerman ? 'Button' : 'Button'),
                            'nmb': (isGerman ? 'Nummer' : 'Number'),
                            'num': (isGerman ? 'Nummer' : 'Number'),
                            'number': (isGerman ? 'Nummer' : 'Number'),
                            'op': (isGerman ? 'Operator' : 'Operator'),
                            'cat': (isGerman ? 'Kategorie' : 'Category'),
                            'opt': (isGerman ? 'Operator' : 'Operator'),
                            'bg': (isGerman ? 'Hintergrund' : 'Background'),
                            'background': (isGerman ? 'Hintergrund' : 'Background'),
                            'operator': (isGerman ? 'Operator' : 'Operator')
                        };

                        const lower = (name || '').toLowerCase();
                        if (lower === 'row' && tempLabel.includes('why_list')) {
                            name = isGerman ? 'Item' : 'Item';
                        } else if (abbrevMap[lower]) {
                            name = abbrevMap[lower];
                        }

                        try {
                            const grpName = (element && (element.getAttribute('data-group') || element.dataset && element.dataset.group)) || '';
                            if (grpName === 'Why Us Image') name = 'Why Us Image';
                            if (grpName === 'Video') name = 'Video';
                        } catch (e) { }

                        name = (name || '').charAt(0).toUpperCase() + (name || '').slice(1);
                        prettyLabel = name + indexSuffix;
                    }

                    if (!prettyLabel.trim()) prettyLabel = "Element";
                } else {
                    prettyLabel = (prettyLabel || '').replace(/\s\d{10,}.*$/, '');
                    prettyLabel = prettyLabel.replace(/_en$/i, '');
                }

                let iconToUse = iconSvg;
                try {
                    const keyCheck = ((element && (element.getAttribute('data-key') || element.getAttribute('data-link-key'))) || label || '').toLowerCase();
                    const hrefCheck = (element && element.getAttribute && (element.getAttribute('href') || '')).toLowerCase();
                    if (/video|youtube|play|vid/.test(keyCheck) || /youtube\.com|vimeo\.com/.test(hrefCheck)) {
                        iconToUse = heroIcons.play;
                    }
                } catch (err) { }

                const contentSpan = document.createElement('span');
                contentSpan.className = 'structure-content';
                contentSpan.innerHTML = `<span class="structure-icon">${iconToUse}</span><span class="structure-name">${prettyLabel}</span>`;
                if (!isContainer && label) {
                    contentSpan.title = label;
                }

                div.appendChild(toggleSpan);
                div.appendChild(contentSpan);

                if (element) {
                    // Prevent opening brand section in editor
                    const key = element.getAttribute && (element.getAttribute('data-key') || element.getAttribute('data-link-key'));
                    if (key && key === 'section_brand') {
                        // Only scroll to it, do not open in editor
                        contentSpan.onclick = (e) => {
                            e.stopPropagation();
                            element.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        };
                    } else {
                        contentSpan.onclick = (e) => {
                            e.stopPropagation();
                            let type = 'container';
                            if (!isContainer) {
                                if (element.classList.contains('editable-image-wrapper')) type = 'image';
                                else if (element.hasAttribute('data-editable-link')) type = 'link';
                                else if (element.hasAttribute('data-editable-bg')) type = 'bg';
                                else if (element.hasAttribute('data-editable-attr')) type = 'attribute';
                                else if (element.hasAttribute('data-editable')) {
                                    type = element.getAttribute('data-editable');
                                    if (type === 'true') type = 'text';
                                }
                            }
                            selectElement(element, type);
                            const targetToScroll = element._scrollTarget || element;
                            targetToScroll.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        };
                    }
                    element._structureLabel = div;
                    li._linkedElement = element;

                    try {
                        const key = element.getAttribute && (element.getAttribute('data-key') || element.getAttribute('data-link-key'));
                        if (key && /_href$/i.test(key)) {
                            const prefix = key.replace(/_href$/i, '');
                            const candidates = [prefix + '_icon', prefix + '_style', prefix + '_effect', prefix + '_btn'];
                            for (let i = 0; i < candidates.length; i++) {
                                const candidateKey = candidates[i];
                                const found = editorDoc.querySelector(`[data-key="${candidateKey}"]`) || editorDoc.querySelector(`[data-style-key="${candidateKey}"]`);
                                if (found) {
                                    element._styleTarget = found;
                                    found._linkedElement = element;
                                    break;
                                }
                            }
                        }
                    } catch (err) { }
                }

                const deletableLabels = [
                    'Banner Circle', 'Circle', 'Service', 'Leistung', 'Referenz', 'Testimonial', 'Referenzen',
                    'Item', 'Eintrag', 'Video', 'Contact Href',

                    'Banner', 'Services', 'About', 'Über Uns',
                    'Project', 'Projects', 'Projekte', 'Brand', 'Marken',
                    'Why choose', 'Warum wir', 'Blog', 'Experience', 'Erfahrung'
                ];
                let isDeletable = deletableLabels.some(l => prettyLabel.includes(l));

                if (isDeletable && prettyLabel === 'Why Us Video' && !isContainer) {
                    isDeletable = false;
                }

                if (isDeletable && (element || groupItems)) {
                    const deleteBtn = document.createElement('span');
                    deleteBtn.className = 'structure-item-delete-btn';
                    deleteBtn.innerHTML = heroIcons.trash;
                    deleteBtn.title = isGerman ? 'Löschen' : 'Delete';
                    deleteBtn.onclick = async (e) => {
                        e.stopPropagation();

                        const isGroupDeletion = !element && groupItems && groupItems.length > 0;
                        const targetElements = isGroupDeletion ? groupItems : [element];

                        const isService = prettyLabel.includes('Service') || prettyLabel.includes('Leistung');
                        const isTestimonial = prettyLabel.includes('Referenz') || prettyLabel.includes('Testimonial') || prettyLabel.includes('Referenzen');

                        if ((isService || isTestimonial) && targetElements.length > 0) {
                            const slideWrapper = targetElements[0].closest('.swiper-slide');
                            if (slideWrapper && slideWrapper.parentElement) {
                                const totalItems = Array.from(slideWrapper.parentElement.children).filter(child => {
                                    return child.matches('.swiper-slide') && !child.matches('.swiper-slide-duplicate');
                                }).length;

                                if (totalItems <= 3) {
                                    const alertMsg = isGerman
                                        ? 'Mindestens 3 Elemente müssen erhalten bleiben.'
                                        : 'At least 3 items must remain.';
                                    showToast(alertMsg, 'warning');
                                    return;
                                }
                            }
                        }

                        const isProject = /^Project\s+\d+$/.test(prettyLabel);
                        if (isProject) {
                            const allProjectEls = editorDoc.querySelectorAll('[data-group^="Project "]');
                            const uniqueProjectGroups = new Set(
                                Array.from(allProjectEls)
                                    .map(el => el.getAttribute('data-group'))
                                    .filter(g => /^Project\s+\d+$/.test(g))
                            );
                            if (uniqueProjectGroups.size <= 2) {
                                const alertMsg = isGerman
                                    ? 'Mindestens 2 Projekte müssen erhalten bleiben.'
                                    : 'At least 2 projects must remain.';
                                showToast(alertMsg, 'warning');
                                return;
                            }
                        }

                        const msg = isGerman ? 'Element wirklich löschen?' : 'Really delete this element?';
                        if (await showConfirm(msg)) {
                            let isBannerCircle = prettyLabel.includes('Banner Circle') || prettyLabel === 'Circle';

                            if (isBannerCircle && groupItems && groupItems.length > 0) {
                                const circleContainer = groupItems[0].closest('.banner-one-item');
                                if (circleContainer) {
                                    sessionStorage.setItem('bannerCircleBackup', circleContainer.outerHTML);
                                }
                            }

                            let isWhyUsVideo = prettyLabel.includes('Video') && !isBannerCircle;
                            if (isWhyUsVideo) {
                                const videoBtn = element || (groupItems && groupItems[0]);
                                const videoWrapper = videoBtn ? videoBtn.closest('.video-play-button') : null;
                                if (videoWrapper) {
                                    sessionStorage.setItem('whyUsVideoBackup', videoWrapper.outerHTML);
                                    console.log('📹 Why Us Video backed up to sessionStorage');
                                }
                            }

                            const sectionLabels = {
                                'Experience': ['experience', 'Erfahrung'],
                                'Stats': ['stats', 'Statistiken'],
                                'Brand': ['brand', 'Marken'],
                                'Banner': ['banner-section', 'home'],
                                'Services': ['services', 'service'],
                                'About': ['about'],
                                'Projects': ['project', 'creative-project'],
                                'Why Choose': ['choose-us', 'why-choose'],
                                'Testimonial': ['testimonial', 'testimonials'],
                                'Blog': ['blog']
                            };

                            for (const [sectionName, idPatterns] of Object.entries(sectionLabels)) {
                                if (prettyLabel.includes(sectionName) || idPatterns.some(p => prettyLabel.toLowerCase().includes(p))) {
                                    let pageSection = null;
                                    const targetEl = element || (groupItems && groupItems[0]);

                                    if (targetEl) {
                                        const possibleSections = [];
                                        let current = targetEl;
                                        while (current && current !== editorDoc.body) {
                                            if (current.id && idPatterns.some(p => current.id.includes(p))) {
                                                possibleSections.push(current);
                                            }
                                            current = current.parentElement;
                                        }

                                        pageSection = possibleSections[possibleSections.length - 1];

                                        if (!pageSection) {
                                            pageSection = targetEl.closest('.page-section, section, [id], [class*="section"]');
                                        }
                                    }

                                    if (pageSection && pageSection.id) {
                                        const backupKey = pageSection.id.replace(/-section$/, '') + 'SectionBackup';
                                        sessionStorage.setItem(backupKey, pageSection.outerHTML);
                                        console.log(`📦 Section ${pageSection.id} (${sectionName}) backed up to sessionStorage`);
                                        break;
                                    }
                                }
                            }

                            isBannerCircle = prettyLabel.includes('Banner Circle') || prettyLabel === 'Circle';
                            const isSwiperItem = prettyLabel.includes('Service') || prettyLabel.includes('Leistung') ||
                                prettyLabel.includes('Referenz') || prettyLabel.includes('Testimonial') ||
                                prettyLabel.includes('Referenzen');

                            const pageSectionLabels = ['Experience', 'Erfahrung', 'Stats', 'Statistiken', 'Brand', 'Marken',
                                'Projects', 'Projekte', 'About', 'Über Uns',
                                'Blog', 'Why choose', 'Warum wir', 'Services', 'Service', 'Testimonial', 'Referenz', 'Referenzen', 'Banner'];

                            const isItemWithNumber = /\s+\d+$/.test(prettyLabel);
                            const isPageSection = pageSectionLabels.some(label => prettyLabel.includes(label)) &&
                                !isItemWithNumber &&
                                !prettyLabel.includes('Circle');

                            const actualTargets = [];

                            if (isBannerCircle && groupItems && groupItems.length > 0) {
                                console.log('🔵 Banner Circle deletion targeting container');
                                const container = groupItems[0].closest('[data-key="banner_circle_container"]') || groupItems[0].closest('.banner-one-item');
                                if (container) {
                                    actualTargets.push(container);
                                } else {
                                    actualTargets.push(...targetElements);
                                }
                            } else if (isPageSection && (element || (groupItems && groupItems.length > 0))) {
                                console.log('📦 Page section deletion - targeting main section container');
                                const targetEl = element || (groupItems && groupItems[0]);

                                let pageSection = null;

                                const possibleSections = [];
                                let current = targetEl;
                                while (current && current !== editorDoc.body) {
                                    if (current.id) {
                                        const cleanLabel = prettyLabel.toLowerCase();
                                        const idMatch = (
                                            (cleanLabel.includes('experience') || cleanLabel.includes('erfahrung')) && current.id.includes('experience') ||
                                            (cleanLabel.includes('stats') || cleanLabel.includes('statistiken')) && current.id.includes('stats') ||
                                            cleanLabel.includes('brand') && current.id.includes('brand') ||
                                            (cleanLabel.includes('service') || cleanLabel.includes('leistung')) && current.id.includes('service') ||
                                            (cleanLabel.includes('about') || cleanLabel.includes('uns')) && current.id.includes('about') ||
                                            (cleanLabel.includes('project') || cleanLabel.includes('projekt')) && current.id.includes('project') ||
                                            (cleanLabel.includes('testimonial') || cleanLabel.includes('referenz')) && current.id.includes('testimonial') ||
                                            cleanLabel.includes('blog') && current.id.includes('blog') ||
                                            (cleanLabel.includes('choose') || cleanLabel.includes('warum')) && current.id.includes('choose') ||
                                            cleanLabel.includes('banner') && (current.id.includes('home') || current.id.includes('banner'))
                                        );

                                        if (idMatch) {
                                            possibleSections.push(current);
                                        }
                                    }
                                    current = current.parentElement;
                                }

                                if (possibleSections.length > 0) {
                                    pageSection = possibleSections[possibleSections.length - 1];
                                }

                                if (!pageSection) {
                                    pageSection = targetEl.closest('.page-section, section[id], [class*="section"]');
                                }

                                if (pageSection && pageSection.id) {
                                    actualTargets.push(pageSection);
                                } else {
                                    actualTargets.push(...targetElements);
                                }
                            } else if (isSwiperItem && groupItems && groupItems.length > 0) {
                                console.log('🔧 Swiper item deletion targeting slide wrapper');
                                const slideWrapper = groupItems[0].closest('.swiper-slide');
                                if (slideWrapper) {
                                    actualTargets.push(slideWrapper);
                                } else {
                                    actualTargets.push(...targetElements);
                                }
                            } else if (prettyLabel.includes('Video') && (element || (groupItems && groupItems[0]))) {
                                const videoBtn = element || (groupItems && groupItems[0]);
                                const videoWrapper = videoBtn.closest('.video-play-button');
                                if (videoWrapper) {
                                    actualTargets.push(videoWrapper);
                                } else {
                                    actualTargets.push(...targetElements);
                                }
                            } else {
                                actualTargets.push(...targetElements);
                            }

                            actualTargets.forEach(el => {
                                if (el) {
                                    let structuralKey = el.getAttribute('data-key');
                                    const label = prettyLabel || 'unlabeled element';

                                    if (!structuralKey && el.id) {
                                        const inner = el.querySelector('[data-key^="section_"]');
                                        if (inner) {
                                            structuralKey = inner.getAttribute('data-key');
                                        } else if (el.id.endsWith('-section') || el.id === 'home' || el.id === 'banner-section') {
                                            const baseId = el.id.replace(/-section$/, '').replace(/-/g, '_');
                                            structuralKey = 'section_' + baseId;
                                        }
                                    }

                                    if (structuralKey) {
                                        deletedStructuralKeys.add(structuralKey);
                                        touchedKeys.add(structuralKey);
                                    }

                                    el.remove();
                                }
                            });

                            console.log('✅ Deletion event processed. Changes will persist upon clicking "Save" (Speichern).');

                            const iframeWin = document.getElementById('admin-viewport-frame').contentWindow;
                            if (iframeWin && iframeWin.Swiper) {
                                const firstEl = targetElements[0];
                                if (firstEl && !firstEl.isConnected) {
                                } else if (firstEl) {
                                    const swiperEl = firstEl.closest('.swiper');
                                    if (swiperEl && swiperEl.swiper) {
                                        swiperEl.swiper.update();
                                    }
                                }
                            }

                            buildStructureTree();
                            showToast(isGerman ? 'Element gelöscht' : 'Element deleted', 'info');
                        }
                    };
                    div.appendChild(deleteBtn);
                }

                li.appendChild(div);
                parentList.appendChild(li);
                return li;
            };

            const setupToggle = (li) => {
                const toggle = li.querySelector('.structure-toggle');
                const childrenUl = li.querySelector('.structure-children');

                if (toggle && childrenUl && childrenUl.children.length > 0) {
                    toggle.style.visibility = 'visible';

                    toggle.onclick = (e) => {
                        e.stopPropagation();
                        if (sidebar && sidebar.classList.contains('properties-active')) {
                            sidebar.classList.remove('properties-active');
                            if (activeElement) {
                            }
                        }
                        const isExpanded = li.classList.contains('expanded');

                        if (isExpanded) {
                            li.classList.remove('expanded');
                            childrenUl.style.display = 'none';
                            toggle.style.transform = 'rotate(0deg)';
                        } else {
                            li.classList.add('expanded');
                            childrenUl.style.display = 'block';
                            toggle.style.transform = 'rotate(90deg)';
                        }
                    };

                    childrenUl.style.display = 'none';
                    toggle.style.transform = 'rotate(0deg)';
                }
            };

            const urlParams = new URLSearchParams(window.location.search);
            const rawPageSections = editorDoc.querySelectorAll('.page-section');
            let pageSections = Array.from(rawPageSections);

            const homeFallback = editorDoc.getElementById('home') || editorDoc.getElementById('banner-section');
            if (homeFallback && !pageSections.some(s => s.contains(homeFallback))) {
                pageSections.push(homeFallback);
            }

            const uniqueSections = [];
            const seenIds = new Set();
            pageSections.forEach(section => {
                const id = section.id;
                if (id && !seenIds.has(id)) {
                    seenIds.add(id);
                    uniqueSections.push(section);
                } else if (!id) {
                    console.warn(`  Section skipped: No ID found on element. Classes: "${section.className}"`);
                } else if (seenIds.has(id)) {
                    console.warn(`  Section skipped: Duplicate ID found: "${id}"`);
                }
            });

            const groupEditables = (nodeList) => {
                const groups = {};
                const Singles = [];
                const processedKeys = new Set();
                const rawKeys = new Set();

                nodeList.forEach(el => {
                    if (el.closest('.swiper-slide-duplicate')) return;

                    let groupAttr = el.getAttribute('data-group') || el.dataset.group;
                    const rawKey = el.getAttribute('data-key') || el.getAttribute('data-link-key') || el.tagName.toLowerCase();

                    const key = (rawKey || '').replace(/_\d{10,}/g, '').replace(/_en$/i, '');

                    rawKeys.add(key);

                    if (processedKeys.has(key)) return;
                    processedKeys.add(key);

                    if (groupAttr === 'Banner Circle') {
                        groupAttr = 'Banner > Circle';
                    }

                    if (groupAttr) {
                        if (!groups[groupAttr]) {
                            groups[groupAttr] = [];
                        }
                        groups[groupAttr].push({ name: key, element: el });
                    } else {
                        const cleanKey = key.replace(/_\d{10,}/, '').replace(/_en$/i, '');
                        const matchEnd = cleanKey.match(/^(.+)_(\d+)$/);
                        const matchMiddle = cleanKey.match(/^(.+?)_(\d+)_(.+)$/);

                        if (matchMiddle) {
                            let prefix = matchMiddle[1];
                            const index = matchMiddle[2];

                            let groupNamePrefix = prefix;
                            const contextualLabels = {
                                'service': 'Service',
                                'services': 'Service',
                                'testimonial': 'Testimonial',
                                'testimonials': 'Testimonial',
                                'project': 'Project',
                                'projects': 'Project',
                                'brand': 'Brand',
                                'award': 'Award',
                                'stat': 'Stat',
                                'feature': 'Feature',
                                'item': 'Item'
                            };

                            let groupName = '';
                            const lowerPrefix = groupNamePrefix.toLowerCase();
                            if (contextualLabels[lowerPrefix]) {
                                groupName = contextualLabels[lowerPrefix] + ' ' + index;
                            } else {
                                groupNamePrefix = groupNamePrefix.charAt(0).toUpperCase() + groupNamePrefix.slice(1);
                                groupName = groupNamePrefix + (index ? ' ' + index : '');
                            }

                            if (!groups[groupName]) groups[groupName] = [];
                            groups[groupName].push({ name: key, element: el });
                        } else if (matchEnd) {
                            let prefix = matchEnd[1];
                            const index = matchEnd[2];

                            let groupNamePrefix = prefix.split('_')[0];
                            const contextualLabels = {
                                'service': 'Service',
                                'services': 'Service',
                                'testimonial': 'Testimonial',
                                'testimonials': 'Testimonial',
                                'project': 'Project',
                                'projects': 'Project',
                                'brand': 'Brand',
                                'award': 'Award',
                                'stat': 'Stat',
                                'feature': 'Feature',
                                'item': 'Item'
                            };

                            let groupName = '';
                            const lowerPrefix = groupNamePrefix.toLowerCase();
                            if (contextualLabels[lowerPrefix]) {
                                groupName = contextualLabels[lowerPrefix] + ' ' + index;
                            } else {
                                groupNamePrefix = groupNamePrefix.charAt(0).toUpperCase() + groupNamePrefix.slice(1);
                                groupName = groupNamePrefix + (index ? ' ' + index : '');
                            }

                            if (!groups[groupName]) {
                                groups[groupName] = [];
                            }
                            groups[groupName].push({ name: key, element: el });
                        } else {
                            const headerMatch = key.match(/^(.+?)_(subtitle|title|text|desc|btn_text|button_text|header)(_en)?$/i);
                            if (headerMatch) {
                                Singles.push({ name: key, element: el });
                            } else {
                                Singles.push({ name: key, element: el });
                            }
                        }
                    }
                });

                const filteredSingles = Singles.filter(item => {
                    const n = item.name || '';
                    if (/(?:_icon|_style|_btn)$/i.test(n)) {
                        const prefix = n.replace(/_(icon|style|btn)$/i, '');
                        if (rawKeys.has(prefix + '_href') || rawKeys.has(prefix + '_link')) {
                            if (/(video|youtube|play|vid)/i.test(prefix)) {
                                return true;
                            }
                            return false;
                        }
                    }
                    return true;
                });

                return { groups, Singles: filteredSingles };
            };

            const makeNodeDraggable = (li, targetEl, label) => {
                if (label.includes('Banner') || label.includes('Content') || label.includes('Inhalt')) return;

                li.setAttribute('draggable', 'true');

                li.addEventListener('dragstart', (e) => {
                    e.stopPropagation();
                    li.classList.add('dragging');
                    e.dataTransfer.effectAllowed = 'move';
                    window._draggingEl = targetEl;
                    window._draggingLi = li;
                });

                li.addEventListener('dragend', (e) => {
                    e.stopPropagation();
                    li.classList.remove('dragging');
                    document.querySelectorAll('.structure-item.drag-over').forEach(el => el.classList.remove('drag-over'));
                });

                li.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    e.stopPropagation();

                    if (!window._draggingLi || window._draggingLi === li) return;

                    if (window._draggingLi.parentElement !== li.parentElement) return;

                    li.classList.add('drag-over');
                });

                li.addEventListener('dragleave', (e) => {
                    li.classList.remove('drag-over');
                });

                li.addEventListener('drop', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    li.classList.remove('drag-over');

                    if (!window._draggingLi || !window._draggingEl || window._draggingLi === li) return;

                    const parentLi = li.parentElement;
                    const draggingLi = window._draggingLi;
                    const draggingEl = window._draggingEl;

                    if (draggingLi.parentElement !== parentLi) return;

                    const bounding = li.getBoundingClientRect();
                    const offset = bounding.y + (bounding.height / 2);

                    if (e.clientY - offset > 0) {
                        li.after(draggingLi);
                        if (targetEl && draggingEl) targetEl.after(draggingEl);
                    } else {
                        li.before(draggingLi);
                        if (targetEl && draggingEl) targetEl.before(draggingEl);
                    }

                    markTouched(draggingEl || targetEl);
                    buildStructureTree();
                    showToast(L.reordered || 'Reordered', 'success');

                    if (draggingEl && draggingEl.classList && draggingEl.classList.contains('page-section')) {
                        hasUnsavedStructureChanges = true;
                        updateSaveButtonState();
                        const sectionOrder = Array.from(editorDoc.querySelectorAll('.page-section'))
                            .filter(s => s.id)
                            .map(s => ({ type: s.id.replace(/-section$/, '').replace(/-/g, '_'), id: s.id }));
                        fetch('backend/save_structure.php', {
                            method: 'POST',
                            body: JSON.stringify({ page_id: PAGE_ID, structure: sectionOrder }),
                            headers: { 'Content-Type': 'application/json' }
                        }).catch(() => { });
                    }
                });
            };

            const renderNodes = (editables, parentUl, sectionElement) => {
                const { groups, groupLabels, Singles } = groupEditables(editables);

                Singles.forEach(item => {
                    let icon = heroIcons.text;
                    if (item.element.classList.contains('editable-image-wrapper')) icon = heroIcons.photo;
                    else if (item.element.hasAttribute('data-editable-link')) icon = heroIcons.link;
                    else if (item.element.hasAttribute('data-editable-bg')) icon = heroIcons.photo;
                    addNode(item.name, icon, item.element, parentUl, false);
                });

                const root = { children: {}, items: [] };

                Object.keys(groups).forEach(groupName => {
                    const parts = groupName.split('>');
                    let current = root;
                    let fullPath = '';

                    parts.forEach((partRaw, index) => {
                        const part = partRaw.trim();
                        fullPath = fullPath ? fullPath + ' > ' + part : part;

                        if (!current.children[part]) {
                            current.children[part] = {
                                children: {},
                                items: [],
                                name: part,
                                fullPath: fullPath
                            };
                        }
                        current = current.children[part];
                    });

                    current.items = groups[groupName];
                });

                const renderTree = (node, container) => {
                    Object.keys(node.children).forEach(key => {
                        const child = node.children[key];
                        const label = (groupLabels && groupLabels[child.fullPath]) ? groupLabels[child.fullPath] : child.name;

                        const collectSubtreeItems = (n) => {
                            let collected = [...n.items];
                            Object.values(n.children).forEach(c => {
                                collected = collected.concat(collectSubtreeItems(c));
                            });
                            return collected;
                        };
                        const subtreeItems = collectSubtreeItems(child);
                        const groupItems = subtreeItems.map(i => i.element);

                        const groupLi = addNode(label, heroIcons.container, null, container, true, groupItems);
                        const groupUl = document.createElement('ul');
                        groupUl.className = 'structure-children';
                        groupLi.appendChild(groupUl);
                        if (sectionElement) groupUl._sectionElement = sectionElement;
                        groupLi._groupItems = groupItems;

                        child.items.forEach(item => {
                            let icon = heroIcons.text;
                            if (item.element.classList.contains('editable-image-wrapper')) icon = heroIcons.photo;
                            else if (item.element.hasAttribute('data-editable-link')) icon = heroIcons.link;
                            else if (item.element.hasAttribute('data-editable-bg')) icon = heroIcons.photo;

                            addNode(item.name, icon, item.element, groupUl, false);
                            item.element._parentGroupUl = groupUl;
                        });

                        renderTree(child, groupUl);

                        setupToggle(groupLi);
                    });
                };

                renderTree(root, parentUl);
            };

            if (uniqueSections.length > 0) {
                const bannerIndex = uniqueSections.findIndex(s => s.id === 'banner-section' || s.id === 'home');
                if (bannerIndex !== -1) {
                    const bannerSect = uniqueSections.splice(bannerIndex, 1)[0];
                    uniqueSections.unshift(bannerSect);
                }

                uniqueSections.forEach(section => {
                    const sectionId = section.id || 'unknown-section';
                    const sectionName = sectionId.replace(/-/g, ' ').replace(/section/g, '').replace(/[0-9]{10,}/, '').trim();
                    const displayName = sectionName.charAt(0).toUpperCase() + sectionName.slice(1);

                    const editables = section.querySelectorAll('[data-editable], [data-editable-link], [data-editable-bg], [data-editable-style], .editable-image-wrapper');

                    let mainSectionElement = null;
                    const filteredEditables = [];

                    editables.forEach(el => {
                        const closestSection = el.closest('.page-section');
                        if (closestSection !== section) {
                            return;
                        }

                        const key = el.getAttribute('data-key');
                        if (key && key.startsWith('section_') && !mainSectionElement) {
                            mainSectionElement = el;
                        } else {
                            filteredEditables.push(el);
                        }
                    });

                    const elementForFolder = mainSectionElement || section;

                    const baseId = (section.id || '').replace(/-section$/i, '');
                    let preferredTarget = null;
                    if (baseId) {
                        try { preferredTarget = section.querySelector('#' + CSS.escape ? CSS.escape(baseId) : baseId); } catch (e) { preferredTarget = section.querySelector('#' + baseId); }
                    }
                    if (!preferredTarget) {
                        preferredTarget = section.querySelector('[id$="-heading"]');
                    }
                    if (preferredTarget) {
                        section._scrollTarget = preferredTarget;
                        if (mainSectionElement) mainSectionElement._scrollTarget = preferredTarget;
                    } else if (mainSectionElement) {
                        mainSectionElement._scrollTarget = section;
                    }

                    const sectionLi = addNode(displayName, heroIcons.section, elementForFolder, tree, true);
                    const childrenUl = document.createElement('ul');
                    childrenUl.className = 'structure-children';
                    sectionLi.appendChild(childrenUl);

                    renderNodes(filteredEditables, childrenUl, section);

                    setupToggle(sectionLi);
                    makeNodeDraggable(sectionLi, section, displayName);
                });
            } else {
                const mLi = addNode('Page Content', heroIcons.cube, null, tree, true);
                const mSub = document.createElement('ul');
                mSub.className = 'structure-children';
                mLi.appendChild(mSub);

                const allEditables = editorDoc.querySelectorAll('[data-editable], [data-editable-link], [data-editable-bg], [data-editable-style], .editable-image-wrapper');
                const mainEditables = Array.from(allEditables).filter(el => !el.closest('#site-header') && !el.closest('#site-footer'));

                renderNodes(mainEditables, mSub, null);

                setupToggle(mLi);
            }

            container.appendChild(tree);
        }

        function renderSidebarControls() {
            const targetContent = propertiesContent || sidebarContent;
            if (!targetContent) return;

            targetContent.innerHTML = '';
            if (!activeElement) {
                targetContent.innerHTML = `<p style="text-align:center; padding:20px; color:#888;">${L.noElement}</p>`;
                return;
            }

            try {
                if (activeTab === 'content') {
                    renderContentTab(targetContent);
                } else if (activeTab === 'advanced') {
                    renderAdvancedTab(targetContent);
                }

                if (targetContent.innerHTML.trim() === '') {
                    const titleStr = isGerman ? "Keine Eigenschaften" : "No Properties";
                    const tabStr = isGerman && activeTab === 'advanced' ? 'Erweiterte' :
                        isGerman && activeTab === 'content' ? 'Inhalts' :
                            activeTab;
                    const descStr = isGerman
                        ? `Es sind keine <strong>${tabStr}</strong>-Einstellungen für das ausgewählte <strong>${activeType}</strong>-Element verfügbar.`
                        : `There are no <strong>${activeTab}</strong> settings available for the selected <strong>${activeType}</strong> element.`;

                    const emptyStateHtml = `
                        <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; padding:40px 20px; text-align:center; background:var(--editor-bg); border:1px solid var(--editor-border); border-radius:12px; margin-top:20px;">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="width:36px; height:36px; margin-bottom:16px; color:var(--editor-border-light); stroke-width:1.5;">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                            </svg>
                            <h4 style="color:var(--editor-text-bright); font-size:13px; font-weight:600; margin:0 0 6px 0; letter-spacing:0.3px;">${titleStr}</h4>
                            <p style="color:var(--editor-text-dim); font-size:12px; margin:0; line-height:1.5;">${descStr}</p>
                        </div>
                    `;
                    targetContent.innerHTML = emptyStateHtml;
                }

            } catch (e) {
                console.error('Error rendering sidebar:', e);
                targetContent.innerHTML = `<p style="color:red; padding:10px;">Error rendering options: ${e.message}</p>`;
            }
        }

        function renderContentTab(container) {
            const key = activeElement.getAttribute('data-key');

            if (activeType === 'text') {
                createControl(container, 'textarea', L.textContent, activeElement.innerHTML.trim(), (val) => {
                    activeElement.innerHTML = val;
                    markTouched(activeElement);
                });

                const compStyles = window.getComputedStyle(activeElement);

                const colorRow = document.createElement('div');
                colorRow.className = 'sidebar-row';
                container.appendChild(colorRow);

                createControl(colorRow, 'color', L.textColor, rgbToHex(compStyles.color), (val) => {
                    if (activeElement.closest('svg')) {
                        activeElement.style.fill = val;
                        activeElement.style.color = val;
                    } else {
                        activeElement.style.color = val;
                    }
                    markTouched(activeElement);
                });

                if (!activeElement.closest('svg')) {
                    createControl(colorRow, 'color', L.bgColor, rgbToHex(compStyles.backgroundColor), (val) => {
                        activeElement.style.backgroundColor = val;
                        markTouched(activeElement);
                    });
                }

                const fontRow = document.createElement('div');
                fontRow.className = 'sidebar-row';
                container.appendChild(fontRow);

                createControl(fontRow, 'number-stepper', L.size, compStyles.fontSize, (val) => {
                    activeElement.style.fontSize = val;
                    markTouched(activeElement);
                });

                try {
                    const opEl = activeElement._pairedOperator;
                    if (opEl) {
                        createControl(container, 'text', (isGerman ? 'Operator' : 'Operator'), (opEl.textContent || opEl.innerText || '').trim(), (val) => {
                            opEl.textContent = val;
                            markTouched(opEl);
                        });
                    }
                } catch (err) { }

                const standardWeights = ['100', '200', '300', '400', '500', '600', '700', '800'];
                const currentWeight = compStyles.fontWeight || '400';
                if (!standardWeights.includes(currentWeight.toString())) {
                    standardWeights.push(currentWeight.toString());
                }

                createControl(fontRow, 'select', L.weight, currentWeight, (val) => {
                    activeElement.style.fontWeight = val;
                    markTouched(activeElement);
                }, standardWeights);
            }
            else if (activeType === 'why-item') {
                const span = activeElement.querySelector('span');
                const strong = activeElement.querySelector('strong');

                if (span) {
                    const cleanText = (span.textContent || span.innerText || '').trim();
                    createControl(container, 'text', (isGerman ? 'TEXT CONTENT' : 'TEXT CONTENT'), cleanText, (val) => {
                        span.innerText = val;
                        markTouched(span);
                        markTouched(activeElement);
                    });
                }

                if (strong) {
                    const cleanNum = (strong.textContent || strong.innerText || '').trim();
                    createControl(container, 'text', (isGerman ? 'NUMBER' : 'NUMBER'), cleanNum, (val) => {
                        strong.innerText = val;
                        markTouched(strong);
                        markTouched(activeElement);
                    });
                }

                const spanStyles = span ? window.getComputedStyle(span) : null;
                const liStyles = window.getComputedStyle(activeElement);
                const strongStyles = strong ? window.getComputedStyle(strong) : null;

                const colorRow = document.createElement('div');
                colorRow.className = 'sidebar-row';
                container.appendChild(colorRow);

                const currentColor = spanStyles ? spanStyles.color : liStyles.color;
                const currentBg = liStyles.backgroundColor;

                createControl(colorRow, 'color', L.textColor, rgbToHex(currentColor), (val) => {
                    if (span) {
                        span.style.setProperty('color', val, 'important');
                        markTouched(span);
                    }
                    if (strong) {
                        strong.style.setProperty('color', val, 'important');
                        markTouched(strong);
                    }
                    activeElement.style.setProperty('color', val, 'important');
                    markTouched(activeElement);
                });

                createControl(colorRow, 'color', L.bgColor, rgbToHex(currentBg), (val) => {
                    activeElement.style.setProperty('backgroundColor', val, 'important');
                    markTouched(activeElement);
                    if (strong) {
                        strong.style.setProperty('backgroundColor', val, 'important');
                        markTouched(strong);
                    }
                    if (span) {
                        span.style.setProperty('backgroundColor', val, 'important');
                        markTouched(span);
                    }
                });

                const fontRow = document.createElement('div');
                fontRow.className = 'sidebar-row';
                container.appendChild(fontRow);

                const fontStyles = spanStyles || liStyles;
                createControl(fontRow, 'number-stepper', L.size, fontStyles.fontSize, (val) => {
                    if (span) span.style.fontSize = val;
                    markTouched(activeElement);
                });

                createControl(fontRow, 'select', L.weight, fontStyles.fontWeight || '400', (val) => {
                    if (span) span.style.fontWeight = val;
                    markTouched(activeElement);
                }, ['100', '200', '300', '400', '500', '600', '700', '800']);
            }
            else if (activeType === 'link') {
                const key = activeElement.getAttribute('data-key') || '';
                if (!key.includes('banner_video')) {
                    createControl(container, 'text', L.linkText, activeElement.innerText, (val) => {
                        activeElement.innerText = val;
                        markTouched(activeElement);
                    });
                }
                createControl(container, 'text', L.href, activeElement.getAttribute('href'), (val) => {
                    activeElement.setAttribute('href', val);
                    markTouched(activeElement);
                });

                const compStyles = window.getComputedStyle(activeElement);

                const colorRow = document.createElement('div');
                colorRow.className = 'sidebar-row';
                container.appendChild(colorRow);

                createControl(colorRow, 'color', L.textColor, rgbToHex(compStyles.color), (val) => {
                    activeElement.style.color = val;
                    markTouched(activeElement);
                });

                createControl(colorRow, 'color', L.bgColor, rgbToHex(compStyles.backgroundColor), (val) => {
                    activeElement.style.backgroundColor = val;
                    markTouched(activeElement);
                });

                const fontRow = document.createElement('div');
                fontRow.className = 'sidebar-row';
                container.appendChild(fontRow);

                createControl(fontRow, 'number-stepper', L.size, compStyles.fontSize, (val) => {
                    activeElement.style.fontSize = val;
                    markTouched(activeElement);
                });

                const standardWeights = ['100', '200', '300', '400', '500', '600', '700', '800'];
                createControl(fontRow, 'select', L.weight, compStyles.fontWeight || '400', (val) => {
                    activeElement.style.fontWeight = val;
                    markTouched(activeElement);
                }, standardWeights);

                const styleTarget = activeElement._styleTarget || null;
                if (styleTarget) {
                    const stStyles = window.getComputedStyle(styleTarget);
                    const stRow = document.createElement('div');
                    stRow.className = 'sidebar-row';
                    container.appendChild(stRow);

                    createControl(stRow, 'color', (isGerman ? 'Icon Color' : 'Icon Color'), rgbToHex(stStyles.color), (val) => {
                        if (styleTarget.closest && styleTarget.closest('svg')) {
                            styleTarget.style.fill = val;
                            styleTarget.style.color = val;
                        } else {
                            styleTarget.style.color = val;
                        }
                        markTouched(styleTarget);
                    });

                    createControl(stRow, 'color', (isGerman ? 'BG Color' : 'BG Color'), rgbToHex(stStyles.backgroundColor), (val) => {
                        styleTarget.style.backgroundColor = val;
                        markTouched(styleTarget);
                    });

                    createControl(stRow, 'text', (isGerman ? 'Size' : 'Size'), stStyles.fontSize || '35px', (val) => {
                        styleTarget.style.fontSize = val;
                        markTouched(styleTarget);
                    });

                    createControl(stRow, 'select', (isGerman ? 'Weight' : 'Weight'), stStyles.fontWeight || '400', (val) => {
                        styleTarget.style.fontWeight = val;
                        markTouched(styleTarget);
                    }, ['100', '200', '300', '400', '500', '600', '700', '800']);
                }
            }
            else if (activeType === 'image') {
                const img = activeElement.querySelector('img');
                createImageControl(container, img ? img.src : '', (newUrl) => {
                    if (img) img.src = newUrl;
                    markTouched(activeElement);
                });
            }
            else if (activeType === 'bg') {
                const compStyles = window.getComputedStyle(activeElement);

                if (activeElement.getAttribute('data-key') === 'curve_text_style') {
                    let currentBg = activeElement.style.background;
                    if (!currentBg || currentBg === 'none') {
                        currentBg = compStyles.backgroundImage !== 'none' ? compStyles.backgroundImage : compStyles.background;
                    }

                    createControl(container, 'text', 'Background CSS', (currentBg && currentBg !== 'none') ? currentBg : '', (val) => {
                        activeElement.style.background = val;
                        markTouched(activeElement);
                    });
                } else {
                    let currentUrl = '';
                    const match = activeElement.style.backgroundImage.match(/url\(["']?(.+?)["']?\)/);
                    if (match) currentUrl = match[1];

                    createImageControl(container, currentUrl, (newUrl) => {
                        activeElement.style.backgroundImage = `url(${newUrl})`;
                        markTouched(activeElement);
                    }, L.bgImage);

                    createControl(container, 'color', L.bgColor, rgbToHex(compStyles.backgroundColor), (val) => {
                        activeElement.style.backgroundColor = val;
                        markTouched(activeElement);
                    });
                }
            }
            else if (activeType === 'style-only') {
                const wasSelected = activeElement.classList.contains('admin-selected-element');
                if (wasSelected) activeElement.classList.remove('admin-selected-element');

                const compStyles = window.getComputedStyle(activeElement);
                let bgColor = compStyles.backgroundColor;

                if (wasSelected) activeElement.classList.add('admin-selected-element');

                if (bgColor === 'rgba(0, 0, 0, 0)' || bgColor === 'transparent') {
                    let parent = activeElement.parentElement;
                    while (parent && parent !== editorDoc.body) {
                        const parentBg = window.getComputedStyle(parent).backgroundColor;
                        if (parentBg !== 'rgba(0, 0, 0, 0)' && parentBg !== 'transparent') {
                            bgColor = parentBg;
                            break;
                        }
                        parent = parent.parentElement;
                    }
                }

                createControl(container, 'color', (isGerman ? 'Icon Color' : 'Icon Color'), rgbToHex(compStyles.color), (val) => {
                    if (activeElement.closest && activeElement.closest('svg')) {
                        activeElement.style.fill = val;
                        activeElement.style.color = val;
                    } else {
                        activeElement.style.color = val;
                    }
                    markTouched(activeElement);
                });

                createControl(container, 'color', (isGerman ? 'Background Color' : 'Background Color'), rgbToHex(bgColor), (val) => {
                    activeElement.style.backgroundColor = val;
                    markTouched(activeElement);
                });
            }
            else if (activeType === 'container') {
                const isSection = activeElement.tagName.toLowerCase() === 'section' ||
                    activeElement.classList.contains('page-section') ||
                    (activeElement.getAttribute && activeElement.getAttribute('data-editable') === 'section');

                const compStyles = window.getComputedStyle(activeElement);

                let currentBg = activeElement.style.background || activeElement.style.backgroundImage || activeElement.style.backgroundColor;
                if (!currentBg || currentBg === 'none' || currentBg === 'initial' || currentBg.startsWith('rgba(0, 0, 0, 0)')) {
                    currentBg = compStyles.backgroundImage !== 'none' ? compStyles.backgroundImage : compStyles.backgroundColor;
                }

                if (currentBg && (currentBg.includes('gradient') || currentBg.includes('url'))) {
                    createControl(container, 'text', isGerman ? 'Hintergrund CSS' : 'Background CSS', currentBg, (val) => {
                        activeElement.style.background = val;
                        markTouched(activeElement);
                    });
                } else {
                    createControl(container, 'color', isGerman ? 'Hintergrundfarbe' : 'Background Color', rgbToHex(currentBg || compStyles.backgroundColor), (val) => {
                        activeElement.style.backgroundColor = val;
                        activeElement.style.backgroundImage = 'none';
                        markTouched(activeElement);
                    });
                }

                createControl(container, 'color', isGerman ? 'Textfarbe' : 'Text Color', rgbToHex(compStyles.color), (val) => {
                    if (isSection) {
                        activeElement.querySelectorAll('[data-editable="true"],[data-key]').forEach(el => {
                            if (!el.closest('svg')) {
                                el.style.color = val;
                                markTouched(el);
                            }
                        });
                    }
                    activeElement.style.color = val;
                    markTouched(activeElement);
                });

            }
            else if (activeType === 'attribute') {
                const attrName = activeElement.getAttribute('data-editable-attr');
                const label = attrName.replace('data-', '').toUpperCase();
                createControl(container, 'text', `Value(${label})`, activeElement.getAttribute(attrName) || '', (val) => {
                    activeElement.setAttribute(attrName, val);
                    markTouched(activeElement);
                });
            }
        }

        function populateSectionsList() {
            const sectionGrid = document.getElementById('sections-component-grid');
            if (!sectionGrid) return;
            sectionGrid.innerHTML = '';

            const knownSections = [
                { id: 'banner', label: 'Banner' },
                { id: 'experience', label: 'Experience' },
                { id: 'services', label: 'Services' },
                { id: 'about', label: 'About' },
                { id: 'projects', label: 'Projects' },
                { id: 'stats', label: 'Stats' },
                { id: 'brand', label: 'Brand' },
                { id: 'why-choose', label: 'Why Choose' },
                { id: 'testimonial', label: 'Testimonial' },
                { id: 'blog', label: 'Blog' }
            ];

            const isGerman = document.documentElement.lang === 'de';
            const localizedLabels = {
                'banner': isGerman ? 'Startseite' : 'Banner',
                'experience': isGerman ? 'Erfahrung' : 'Experience',
                'services': isGerman ? 'Leistungen' : 'Services',
                'about': isGerman ? 'Über Uns' : 'About',
                'projects': isGerman ? 'Projekte' : 'Projects',
                'stats': isGerman ? 'Statistiken' : 'Stats',
                'brand': isGerman ? 'Marken' : 'Brand',
                'why-choose': isGerman ? 'Warum Wir' : 'Why Choose',
                'testimonial': isGerman ? 'Referenzen' : 'Testimonial',
                'blog': isGerman ? 'Blog' : 'Blog'
            };

            knownSections.forEach(section => {
                const compType = section.id;
                if (compType === 'brand') return; // Do not allow editing brand section
                const label = localizedLabels[compType] || section.label;

                const btn = document.createElement('button');
                btn.className = 'component-btn';
                btn.setAttribute('data-component', compType);
                btn.title = label;
                btn.innerHTML = `<div class="component-icon">${heroIcons.section}</div><span class="component-label">${label}</span>`;

                btn.addEventListener('click', () => {
                    if (confirm(isGerman ? 'Möchten Sie diesen Abschnitt wiederherstellen?' : 'Do you want to restore this section?')) {
                        restoreSection(compType);
                    }
                });

                sectionGrid.appendChild(btn);
            });
        }

        function renderAdvancedTab(container) {
            const targetEl = activeElement;
            if (!targetEl) return;

            const compStyles = window.getComputedStyle(targetEl);

            createCollapsibleSection(container, L.layout, [
                { type: '4box', label: 'Margin', property: 'margin' },
                { type: '4box', label: 'Padding', property: 'padding' },
                { type: 'select', label: 'Width', value: 'Default', onChange: (v) => { targetEl.style.width = v; markTouched(targetEl); }, options: ['Default', 'Auto', '100%', '50%'] }
            ], true);

            createCollapsibleSection(container, L.customCss, [
                { type: 'textarea', label: 'CSS', value: targetEl.getAttribute('style') || '', onChange: (v) => { targetEl.setAttribute('style', v); markTouched(targetEl); } }
            ], true);
        }

        function createCollapsibleSection(container, title, controls, isOpen = false) {
            const section = document.createElement('div');
            section.className = 'advanced-section';

            const header = document.createElement('div');
            header.className = 'advanced-section-header';
            header.innerHTML = `<i class="fas fa-chevron-right"></i> <span>${title}</span>`;

            const content = document.createElement('div');
            content.className = 'advanced-section-content';
            content.style.display = 'none';

            header.onclick = () => {
                const isOpen = content.style.display === 'block';
                if (isOpen) {
                    content.style.display = 'none';
                    header.querySelector('i').classList.remove('fa-chevron-down');
                    header.querySelector('i').classList.add('fa-chevron-right');
                } else {
                    content.style.display = 'block';
                    header.querySelector('i').classList.remove('fa-chevron-right');
                    header.querySelector('i').classList.add('fa-chevron-down');
                }
            };

            controls.forEach(ctrl => {
                createControlInContainer(content, ctrl.type, ctrl.label, ctrl.value, ctrl.onChange, ctrl.options, ctrl.property);
            });

            section.appendChild(header);
            section.appendChild(content);

            if (container) container.appendChild(section);
        }

        function showCustomColorPicker(currentColor, onChange, triggerElement) {
            const rect = triggerElement.getBoundingClientRect();

            const picker = document.createElement('div');
            picker.style.cssText = 'position:fixed;background:rgba(26,26,26,0.95);backdrop-filter:blur(10px);border:2px solid #333;border-radius:8px;padding:0;width:180px;z-index:999999;box-shadow:0 8px 32px rgba(0,0,0,0.6);';
            picker.style.left = rect.left + 'px';
            picker.style.top = (rect.bottom + 8) + 'px';

            const header = document.createElement('div');
            header.style.cssText = 'display:flex;justify-content:center;align-items:center;padding:10px 12px;border-bottom:1px solid #333;';
            header.innerHTML = '<span style="font-size:11px;font-weight:600;color:#aaa;">Color Picker</span>';


            const content = document.createElement('div');
            content.style.cssText = 'padding:12px;';

            const canvasWrapper = document.createElement('div');
            canvasWrapper.style.cssText = 'position:relative;margin-bottom:10px;background:repeating-conic-gradient(#808080 0% 25%, transparent 0% 50%) 50% / 8px 8px;border-radius:4px;';

            const canvas = document.createElement('canvas');
            canvas.width = 156;
            canvas.height = 100;
            canvas.style.cssText = 'width:100%;border:1px solid #333;cursor:crosshair;border-radius:4px;display:block;';
            const ctx = canvas.getContext('2d');

            const cursor = document.createElement('div');
            cursor.style.cssText = 'position:absolute;width:12px;height:12px;border:2px solid #fff;border-radius:50%;pointer-events:none;box-shadow:0 0 4px rgba(0,0,0,0.5);display:none;';
            canvasWrapper.appendChild(cursor);

            let hue = 280;
            const drawPicker = () => {
                for (let y = 0; y < 100; y++) {
                    const grad = ctx.createLinearGradient(0, y, 156, y);
                    grad.addColorStop(0, '#fff');
                    grad.addColorStop(1, `hsl(${hue}, 100%, 50%)`);
                    ctx.fillStyle = grad;
                    ctx.fillRect(0, y, 156, 1);
                }
                const gradBlack = ctx.createLinearGradient(0, 0, 0, 100);
                gradBlack.addColorStop(0, 'rgba(0,0,0,0)');
                gradBlack.addColorStop(1, '#000');
                ctx.fillStyle = gradBlack;
                ctx.fillRect(0, 0, 156, 100);
            };
            drawPicker();

            const updateCursor = (e) => {
                const rect = canvas.getBoundingClientRect();
                let x = e.clientX - rect.left;
                let y = e.clientY - rect.top;
                if (x >= 0 && x <= rect.width && y >= 0 && y <= rect.height) {
                    cursor.style.display = 'block';
                    cursor.style.left = (x - 6) + 'px';
                    cursor.style.top = (y - 6) + 'px';
                    const cx = Math.min(Math.max(0, Math.floor((x / rect.width) * canvas.width)), canvas.width - 1);
                    const cy = Math.min(Math.max(0, Math.floor((y / rect.height) * canvas.height)), canvas.height - 1);
                    const imgData = ctx.getImageData(cx, cy, 1, 1).data;
                    const hex = '#' + [imgData[0], imgData[1], imgData[2]].map(v => v.toString(16).padStart(2, '0')).join('');
                    cursor.style.backgroundColor = hex;
                    colorInput.value = hex.replace('#', '');
                }
            };

            let isDragging = false;
            canvas.onmousemove = updateCursor;
            canvas.onmouseleave = () => cursor.style.display = 'none';
            canvas.onmousedown = (e) => { isDragging = true; updateCursor(e); };
            canvas.onmouseup = () => isDragging = false;

            canvasWrapper.appendChild(canvas);

            const hueBarWrapper = document.createElement('div');
            hueBarWrapper.style.cssText = 'position:relative;margin-bottom:10px;background:repeating-conic-gradient(#808080 0% 25%, transparent 0% 50%) 50% / 8px 8px;border-radius:4px;';

            const hueBar = document.createElement('canvas');
            hueBar.width = 156;
            hueBar.height = 10;
            hueBar.style.cssText = 'width:100%;height:10px;border:1px solid #333;cursor:pointer;border-radius:4px;display:block;';
            const hueCtx = hueBar.getContext('2d');
            const hueGrad = hueCtx.createLinearGradient(0, 0, 156, 0);
            for (let i = 0; i <= 360; i += 20)hueGrad.addColorStop(i / 360, `hsl(${i}, 100%, 50%)`);
            hueCtx.fillStyle = hueGrad;
            hueCtx.fillRect(0, 0, 156, 10);

            hueBarWrapper.appendChild(hueBar);

            hueBar.onclick = (e) => {
                hue = (e.offsetX / 156) * 360;
                drawPicker();
            };

            const tabs = document.createElement('div');
            tabs.style.cssText = 'display:grid;grid-template-columns:repeat(4,1fr);gap:4px;margin-bottom:8px;';
            let activeTab = 'Hex';
            const tabButtons = [];

            const colorInput = document.createElement('input');
            colorInput.value = currentColor.replace('#', '');
            colorInput.style.cssText = 'width:100%;padding:6px 4px;background:transparent;border:1px solid #333;color:#fff;font-size:10px;border-radius:4px;text-align:center;margin-bottom:10px;';

            colorInput.addEventListener('input', (e) => {
                let val = e.target.value.trim();
                if (val.startsWith('#')) val = val.substring(1);
                if (/^[0-9a-fA-F]{6}$/.test(val)) {
                    onChange('#' + val);
                } else if (/^rgb\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*\)$/.test(val)) {
                    const match = val.match(/rgb\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)/);
                    if (match) {
                        const hex = '#' + [match[1], match[2], match[3]].map(v => parseInt(v).toString(16).padStart(2, '0')).join('');
                        onChange(hex);
                    }
                }
            });

            ['Hex', 'RGB', 'HSL', 'HSB'].forEach((tab, i) => {
                const btn = document.createElement('button');
                btn.textContent = tab;
                btn.style.cssText = 'padding:6px 4px;background:' + (i === 0 ? '#0a0a0a' : 'transparent') + ';border:1px solid #333;color:#888;font-size:10px;cursor:pointer;border-radius:4px;transition:all 0.2s;';
                btn.onclick = () => {
                    activeTab = tab;
                    tabButtons.forEach((b, idx) => {
                        b.style.background = idx === i ? '#0a0a0a' : 'transparent';
                        b.style.color = idx === i ? '#d225d7' : '#888';
                    });
                    if (tab === 'HSL') colorInput.value = 'hsl(280, 100%, 50%)';
                    else if (tab === 'RGB') colorInput.value = 'rgb(210, 37, 215)';
                    else if (tab === 'HSB') colorInput.value = 'hsb(280, 100%, 84%)';
                    else colorInput.value = currentColor.replace('#', '');
                };
                btn.onmouseover = () => { btn.style.background = '#0a0a0a'; btn.style.color = '#d225d7'; };
                btn.onmouseout = () => { btn.style.background = activeTab === tab ? '#0a0a0a' : 'transparent'; btn.style.color = activeTab === tab ? '#d225d7' : '#888'; };
                tabs.appendChild(btn);
                tabButtons.push(btn);
            });

            const label = document.createElement('div');
            label.textContent = 'Theme Colors';
            label.style.cssText = 'font-size:10px;color:#888;margin-bottom:6px;';

            const presets = ['#20c1f5', '#49b9f2', '#7675ec', '#a04ee1', '#d225d7', '#f009d5'];
            const presetContainer = document.createElement('div');
            presetContainer.style.cssText = 'display:grid;grid-template-columns:repeat(6,1fr);gap:4px;';
            presets.forEach(color => {
                const btn = document.createElement('div');
                btn.style.cssText = 'width:100%;height:20px;background:' + color + ';border:1px solid #333;cursor:pointer;border-radius:4px;transition:border-color 0.2s;';
                btn.onmouseover = () => btn.style.borderColor = '#d225d7';
                btn.onmouseout = () => btn.style.borderColor = '#333';
                btn.onclick = () => {
                    colorInput.value = color.replace('#', '');
                    if (onChange) onChange(color);
                    setTimeout(() => picker.remove(), 100);
                };
                presetContainer.appendChild(btn);
            });

            canvas.onclick = (e) => {
                const x = (e.offsetX / canvas.offsetWidth) * 156;
                const y = (e.offsetY / canvas.offsetHeight) * 100;
                const imgData = ctx.getImageData(x, y, 1, 1).data;
                const hex = '#' + [imgData[0], imgData[1], imgData[2]].map(v => v.toString(16).padStart(2, '0')).join('');
                colorInput.value = hex.replace('#', '');
                onChange(hex);
                picker.remove();
            };

            content.appendChild(canvasWrapper);
            content.appendChild(hueBarWrapper);
            content.appendChild(tabs);
            content.appendChild(colorInput);
            content.appendChild(label);
            content.appendChild(presetContainer);
            picker.appendChild(header);
            picker.appendChild(content);
            document.body.appendChild(picker);

            setTimeout(() => {
                const clickOutside = (e) => {
                    if (!picker.contains(e.target)) {
                        picker.remove();
                        document.removeEventListener('click', clickOutside);
                    }
                };
                document.addEventListener('click', clickOutside);
            }, 100);
        }

        function createControlInContainer(container, type, label, value, onChange, options = [], property = null) {
            const group = document.createElement('div');
            group.className = 'sidebar-group';

            if (type === 'info') {
                group.innerHTML = `<p style="color: #888; font-size: 12px; font-style: italic; margin: 0;">${label}</p>`;
                container.appendChild(group);
                return;
            }

            const lbl = document.createElement('label');
            lbl.className = 'sidebar-label';
            lbl.innerText = label;
            group.appendChild(lbl);

            let input;
            if (type === '4box') {
                group.removeChild(lbl);

                const header = document.createElement('div');
                header.style.display = 'flex';
                header.style.justifyContent = 'space-between';
                header.style.alignItems = 'center';
                header.style.marginBottom = '6px';

                const customLbl = document.createElement('label');
                customLbl.className = 'sidebar-label';
                customLbl.style.marginBottom = '0';
                customLbl.innerText = label;
                header.appendChild(customLbl);

                const unitSelect = document.createElement('select');
                unitSelect.className = 'unit-select';
                ['px', '%', 'em', 'rem', 'vh', 'vw'].forEach(u => {
                    const opt = document.createElement('option');
                    opt.value = u;
                    opt.innerText = u;
                    unitSelect.appendChild(opt);
                });
                header.appendChild(unitSelect);
                group.appendChild(header);

                const fourBoxContainer = document.createElement('div');
                fourBoxContainer.className = 'four-box-container';

                const compStyles = window.getComputedStyle(activeElement);
                const sides = ['Top', 'Right', 'Bottom', 'Left'];
                const inputs = {};
                let isLinked = true;

                sides.forEach(side => {
                    const box = document.createElement('div');
                    box.className = 'four-box-item';

                    const sideInput = document.createElement('input');
                    sideInput.type = 'number';
                    sideInput.className = 'four-box-input';
                    sideInput.placeholder = '0';

                    const cssProp = property + side;
                    sideInput.value = parseInt(compStyles[cssProp]) || 0;
                    inputs[side] = sideInput;

                    sideInput.addEventListener('input', (e) => {
                        const val = e.target.value;
                        const unit = unitSelect.value;
                        if (isLinked) {
                            sides.forEach(s => {
                                if (inputs[s] !== sideInput) inputs[s].value = val;
                                activeElement.style[property + s] = val + unit;
                            });
                        } else {
                            activeElement.style[cssProp] = val + unit;
                        }
                        markTouched(activeElement);
                    });

                    const sideLabel = document.createElement('span');
                    sideLabel.className = 'four-box-label';
                    sideLabel.innerText = side;

                    box.appendChild(sideInput);
                    box.appendChild(sideLabel);
                    fourBoxContainer.appendChild(box);
                });
                // Place link/unlink button at the end, in the same row
                const linkBox = document.createElement('div');
                linkBox.className = 'four-box-item four-box-link active';
                linkBox.innerHTML = '<i class="fas fa-link"></i>';
                linkBox.title = 'Link Values';
                linkBox.onclick = () => {
                    isLinked = !isLinked;
                    linkBox.classList.toggle('active', isLinked);
                    linkBox.innerHTML = isLinked ? '<i class="fas fa-link"></i>' : '<i class="fas fa-unlink"></i>';
                };
                fourBoxContainer.appendChild(linkBox);

                group.appendChild(fourBoxContainer);
                container.appendChild(group);
                return;
            }
            else if (type === 'textarea') {
                input = document.createElement('textarea');
                input.className = 'sidebar-textarea';
                input.style.cssText = 'width:100%;min-height:120px;padding:12px;background:#1a1a1a;border:2px solid #333;border-radius:6px;color:#fff;font-size:14px;font-family:inherit;resize:vertical;transition:border-color 0.2s;';
                input.value = value || '';
                input.addEventListener('input', (e) => onChange(e.target.value));
                input.addEventListener('focus', () => input.style.borderColor = '#d225d7');
                input.addEventListener('blur', () => input.style.borderColor = '#333');
            }
            else if (type === 'select') {
                input = document.createElement('select');
                input.className = 'sidebar-select';
                input.style.cssText = 'width:100%;height:44px;padding:0 12px;background:#1a1a1a;border:2px solid #333;border-radius:6px;color:#fff;font-size:14px;font-family:inherit;cursor:pointer;transition:border-color 0.2s;';
                options.forEach(opt => {
                    const o = document.createElement('option');
                    o.value = opt;
                    o.innerText = opt;
                    if (opt == value) o.selected = true;
                    input.appendChild(o);
                });
                input.addEventListener('change', (e) => onChange(e.target.value));
                input.addEventListener('focus', () => input.style.borderColor = '#d225d7');
                input.addEventListener('blur', () => input.style.borderColor = '#333');
            }
            else {
                input = document.createElement('input');
                input.type = type;
                input.className = 'sidebar-input';
                if (type === 'color') {
                    if (!value || !value.startsWith('#')) value = '#000000';
                    const wrapper = document.createElement('div');
                    wrapper.style.cssText = 'position:relative;width:100%;';

                    const display = document.createElement('div');
                    display.style.cssText = 'width:100%;height:44px;background:' + value + ';border:2px solid #333;border-radius:6px;cursor:pointer;transition:border-color 0.2s;';
                    display.onmouseover = () => display.style.borderColor = '#d225d7';
                    display.onmouseout = () => display.style.borderColor = '#333';

                    display.onclick = () => {
                        showCustomColorPicker(value, (newColor) => {
                            display.style.background = newColor;
                            onChange(newColor);
                        }, display);
                    };

                    wrapper.appendChild(display);
                    group.appendChild(lbl);
                    group.appendChild(wrapper);
                    if (container) container.appendChild(group);
                    return;
                } else {
                    input.style.cssText = 'width:100%;height:44px;padding:0 12px;background:#1a1a1a;border:2px solid #333;border-radius:6px;color:#fff;font-size:14px;font-family:inherit;transition:border-color 0.2s;';
                    input.addEventListener('focus', () => input.style.borderColor = '#d225d7');
                    input.addEventListener('blur', () => input.style.borderColor = '#333');
                }
                input.value = value || '';
                input.addEventListener('input', (e) => onChange(e.target.value));
            }

            if (input) {
                group.appendChild(input);
            }
            container.appendChild(group);
        }

        function createControl(container, type, label, value, onChange, options = [], readonly = false) {
            const group = document.createElement('div');
            group.className = 'sidebar-group';

            const lbl = document.createElement('label');
            lbl.className = 'sidebar-label';
            lbl.innerText = label;
            group.appendChild(lbl);

            let input;
            if (type === 'number-stepper') {
                const wrapper = document.createElement('div');
                wrapper.style.cssText = 'display:flex;align-items:center;height:44px;background:#1a1a1a;border:2px solid #333;border-radius:6px;overflow:hidden;transition:border-color 0.2s;padding:0 14px;';
                const labelInside = document.createElement('span');
                labelInside.innerText = label;
                labelInside.style.cssText = 'padding:0;color:#888;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;display:none;';
                input = document.createElement('input');
                input.type = 'text';
                input.value = value;
                input.style.cssText = 'flex:1;padding:0;background:transparent;border:none;color:#fff;font-size:14px;outline:none;text-align:center;';
                input.addEventListener('input', (e) => onChange(e.target.value));
                input.addEventListener('focus', () => wrapper.style.borderColor = '#d225d7');
                input.addEventListener('blur', () => wrapper.style.borderColor = '#333');
                input.addEventListener('keydown', (e) => {
                    if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        const num = parseFloat(input.value) || 0;
                        input.value = (num + 1) + 'px';
                        onChange(input.value);
                    } else if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        const num = parseFloat(input.value) || 0;
                        input.value = Math.max(0, num - 1) + 'px';
                        onChange(input.value);
                    }
                });
                wrapper.appendChild(labelInside);
                wrapper.appendChild(input);
                group.appendChild(wrapper);
                if (container) container.appendChild(group);
                return;
            }
            else if (type === 'textarea') {
                input = document.createElement('textarea');
                input.className = 'sidebar-textarea';
                input.style.cssText = 'width:100%;min-height:120px;padding:12px;background:#1a1a1a;border:2px solid #333;border-radius:6px;color:#fff;font-size:14px;font-family:inherit;resize:vertical;transition:border-color 0.2s;';
                input.value = value;
                input.addEventListener('input', (e) => onChange(e.target.value));
                input.addEventListener('focus', () => input.style.borderColor = '#d225d7');
                input.addEventListener('blur', () => input.style.borderColor = '#333');
                if (readonly) input.readOnly = true;
            }
            else if (type === 'select') {
                input = document.createElement('select');
                input.className = 'sidebar-select';
                input.style.cssText = 'width:100%;height:44px;padding:0 12px;background:#1a1a1a;border:2px solid #333;border-radius:6px;color:#fff;font-size:14px;font-family:inherit;cursor:pointer;transition:border-color 0.2s;';
                options.forEach(opt => {
                    const o = document.createElement('option');
                    o.value = opt;
                    o.innerText = opt;
                    if (opt == value) o.selected = true;
                    input.appendChild(o);
                });
                input.addEventListener('change', (e) => onChange(e.target.value));
                input.addEventListener('focus', () => input.style.borderColor = '#d225d7');
                input.addEventListener('blur', () => input.style.borderColor = '#333');
                if (readonly) input.disabled = true;
            }
            else {
                input = document.createElement('input');
                input.type = type;
                input.className = 'sidebar-input';
                if (type === 'color') {
                    if (!value.startsWith('#')) value = '#000000';
                    const wrapper = document.createElement('div');
                    wrapper.style.cssText = 'position:relative;width:100%;';

                    const display = document.createElement('div');
                    display.style.cssText = 'width:100%;height:44px;background:' + value + ';border:2px solid #333;border-radius:6px;cursor:pointer;transition:border-color 0.2s;';
                    display.onmouseover = () => display.style.borderColor = '#d225d7';
                    display.onmouseout = () => display.style.borderColor = '#333';

                    display.onclick = () => {
                        showCustomColorPicker(value, (newColor) => {
                            display.style.background = newColor;
                            onChange(newColor);
                        }, display);
                    };

                    wrapper.appendChild(display);
                    group.appendChild(lbl);
                    group.appendChild(wrapper);
                    if (container) container.appendChild(group);
                    return;
                } else {
                    input.style.cssText = 'width:100%;height:44px;padding:0 12px;background:#1a1a1a;border:2px solid #333;border-radius:6px;color:#fff;font-size:14px;font-family:inherit;transition:border-color 0.2s;';
                    input.addEventListener('focus', () => input.style.borderColor = '#d225d7');
                    input.addEventListener('blur', () => input.style.borderColor = '#333');
                }
                input.value = value;
                input.addEventListener('input', (e) => onChange(e.target.value));
                if (readonly) input.readOnly = true;
            }

            group.appendChild(input);
            if (container) container.appendChild(group);
        }

        function createImageControl(container, currentUrl, onUpdate, label = 'Image') {
            const group = document.createElement('div');
            group.className = 'sidebar-group';

            const lbl = document.createElement('label');
            lbl.className = 'sidebar-label';
            lbl.innerText = label;
            group.appendChild(lbl);

            const imgContainer = document.createElement('div');
            imgContainer.className = 'sidebar-image-container';
            imgContainer.style.cssText = 'width:100%;height:180px;border:2px solid #333;border-radius:8px;cursor:pointer;display:flex;align-items:center;justify-content:center;overflow:hidden;background:#1a1a1a;transition:border-color 0.2s;';
            imgContainer.onmouseover = () => imgContainer.style.borderColor = '#d225d7';
            imgContainer.onmouseout = () => imgContainer.style.borderColor = '#333';

            const img = document.createElement('img');
            img.className = 'sidebar-image-preview';
            img.src = currentUrl || '';
            img.style.display = currentUrl ? 'block' : 'none';
            img.style.width = '100%';
            img.style.height = '100%';
            img.style.objectFit = 'cover';

            const placeholder = document.createElement('div');
            placeholder.style.cssText = 'display:flex;flex-direction:column;align-items:center;justify-content:center;color:#888;text-align:center;padding:20px;';
            placeholder.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:48px;height:48px;margin-bottom:8px;opacity:0.6;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" /></svg><span style="font-size:13px;">' + L.clickToReplace + '</span>';
            if (currentUrl) placeholder.style.display = 'none';

            imgContainer.appendChild(img);
            imgContainer.appendChild(placeholder);

            imgContainer.onclick = () => {
                const capturedOnUpdate = onUpdate;
                const capturedActiveElement = activeElement;

                fileInput.value = '';

                fileInput.onchange = (e) => {
                    if (!e.target.files[0]) return;
                    uploadImage(e.target.files[0], (url) => {
                        img.src = url;
                        img.style.display = 'block';
                        placeholder.style.display = 'none';
                        capturedOnUpdate(url);
                        markTouched(img);
                        if (capturedActiveElement) markTouched(capturedActiveElement);

                        fileInput.value = '';
                    });
                };
                fileInput.click();
            };

            group.appendChild(imgContainer);
            if (container) container.appendChild(group);
        }

        function uploadImage(file, callback) {
            const formData = new FormData();
            formData.append('image', file);

            const prevText = sidebarTitle.innerText;
            sidebarTitle.innerText = L.uploading;

            fetch('backend/ajax_upload_editable_image.php', {
                method: 'POST',
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        callback(data.url);
                    } else {
                        showToast('Upload Error: ' + data.message, 'error');
                    }
                })
                .catch(err => showToast('Network Error: ' + err.message, 'error'))
                .finally(() => sidebarTitle.innerText = isGerman ? 'Layouts' : 'Layouts');
        }

        function getPageContent() {
            const blocks = {};
            const getBlock = (key) => {
                if (!blocks[key]) {
                    blocks[key] = { content: '', type: 'text', style: '' };
                }
                return blocks[key];
            };

            editorDoc.querySelectorAll('[data-editable="true"], [data-editable="why-item"], [data-editable="why-item"] span, [data-editable="why-item"] strong').forEach(el => {
                const key = el.getAttribute('data-key');
                if (key) {
                    const block = getBlock(key);
                    block.is_deleted = false;
                    if (el.classList.contains('timer') && el.getAttribute('data-to')) {
                        block.content = el.getAttribute('data-to');
                    } else {
                        block.content = el.innerHTML.trim();
                    }
                    block.style = el.getAttribute('style') || '';
                    if (activeElement && activeElement.getAttribute('data-editable') === 'why-item') {
                        block.type = 'style-only';
                    } else {
                        block.type = 'html';
                    }

                    const linkKey = el.getAttribute('data-link-key');
                    if (linkKey) {
                        const linkBlock = getBlock(linkKey);
                        linkBlock.content = el.getAttribute('href') || '#';
                        linkBlock.type = 'link_href';
                    }
                }
            });

            editorDoc.querySelectorAll('.editable-image-wrapper').forEach(wrap => {
                const key = wrap.getAttribute('data-key');
                const img = wrap.querySelector('img');
                if (key && img) {
                    const block = getBlock(key);
                    block.content = img.getAttribute('src');
                    block.type = 'image';
                    block.style = wrap.getAttribute('style') || '';
                    if (img.getAttribute('alt')) {
                        block.alt = img.getAttribute('alt');
                    }
                }
            });

            editorDoc.querySelectorAll('[data-editable-bg="true"], [data-editable-color="true"]').forEach(el => {
                const key = el.getAttribute('data-key');
                const colorKey = el.getAttribute('data-color-key');

                if (key && el.getAttribute('data-editable-bg') === 'true') {
                    const block = getBlock(key);
                    const match = el.style.backgroundImage.match(/url\(["']?(.+?)["']?\)/);
                    if (match) {
                        block.content = match[1];
                        block.type = 'image';
                    }
                    block.style = el.getAttribute('style') || '';
                }

                if (colorKey) {
                    const cBlock = getBlock(colorKey);
                    cBlock.content = el.style.color || '';
                    cBlock.type = 'color';
                }
            });

            editorDoc.querySelectorAll('[data-editable-attr]').forEach(el => {
                const attr = el.getAttribute('data-editable-attr');
                const key = el.getAttribute('data-key');
                if (attr && key) {
                    const block = getBlock(key);
                    block.content = el.getAttribute(attr) || '';
                    block.type = 'attribute';
                }
            });

            editorDoc.querySelectorAll('[data-editable-link="true"]').forEach(el => {
                if (el.getAttribute('data-editable') === 'true') return;

                const key = el.getAttribute('data-key') || el.getAttribute('data-link-key');
                if (key) {
                    const block = getBlock(key);
                    block.content = el.getAttribute('href') || '#';
                    block.type = 'link_href';
                    block.style = el.getAttribute('style') || '';
                }
            });

            editorDoc.querySelectorAll('[data-editable="section"]').forEach(el => {
                const key = el.getAttribute('data-key');
                if (key) {
                    const block = getBlock(key);

                    block.type = 'container';
                    block.style = el.getAttribute('style') || '';

                    block.content = "SECTION_WRAPPER";
                }
            });

            editorDoc.querySelectorAll('[data-editable="section"], [data-key]').forEach(el => {
                const key = el.getAttribute('data-key');
                if (key) {
                    const block = getBlock(key);
                    block.is_deleted = false;
                    if (!block.type || block.type === 'text') {
                        block.type = 'style-only';
                    }
                    const currentStyle = el.getAttribute('style') || '';
                    block.style = currentStyle;
                }
            });

            deletedStructuralKeys.forEach(key => {
                const block = getBlock(key);
                block.content = '';
                block.type = 'style-only';
                block.is_deleted = true;
            });

            return blocks;
        }

        function getChangedBlocks() {
            const currentContent = getPageContent();
            const changed = {};
            let hasChanges = false;

            console.group('🔍 ANALYZING CHANGES FOR SAVE');
            console.log('Initial keys:', Object.keys(initialContentState).length);
            console.log('Current keys:', Object.keys(currentContent).length);
            console.log('Touched keys:', Array.from(touchedKeys));
            console.log('Deleted structural keys:', Array.from(deletedStructuralKeys));

            Object.keys(currentContent).forEach(key => {
                const current = currentContent[key];
                const initial = initialContentState[key];

                if (!initial) {
                    if (touchedKeys.has(key)) {
                        console.log(`✨[New Block] ${key} is marked touched.Including.`);
                        changed[key] = current;
                        hasChanges = true;
                    }
                    return;
                }

                const currentContentStr = (current.content || '').toString().trim();
                const initialContentStr = (initial.content || '').toString().trim();

                let currentStyle = (current.style || '').trim().replace(/;$/, '');
                let initialStyle = (initial.style || '').trim().replace(/;$/, '');

                if (key === 'banner_hero_bg') {
                    currentStyle = initialStyle;
                }

                const isDeletedDiff = (current.is_deleted || false) !== (initial.is_deleted || false);

                const isDiff =
                    currentContentStr !== initialContentStr ||
                    current.type !== initial.type ||
                    currentStyle !== initialStyle ||
                    isDeletedDiff ||
                    (current.alt || '') !== (initial.alt || '');

                if (isDiff) {
                    if (touchedKeys.has(key)) {
                        console.group(`📝 Changeable: ${key} `);
                        console.log('✅ Status: Touched');
                        console.log('🔄 Diff details:', {
                            content: currentContentStr !== initialContentStr ? { from: initialContentStr, to: currentContentStr } : 'no change',
                            style: currentStyle !== initialStyle ? { from: initialStyle, to: currentStyle } : 'no change',
                            type: current.type !== initial.type ? { from: initial.type, to: current.type } : 'no change',
                            deleted: isDeletedDiff ? { from: initial.is_deleted, to: current.is_deleted } : 'no change'
                        });
                        console.groupEnd();

                        changed[key] = current;
                        hasChanges = true;
                    } else {
                        console.groupCollapsed(`⚠️ Diff Skipped(Untouched): ${key} `);
                        console.log('❌ Status: Not Touched');
                        console.log('🔄 Diff details:', {
                            content: currentContentStr !== initialContentStr ? 'content diff' : 'same',
                            style: currentStyle !== initialStyle ? 'style diff' : 'same',
                            deleted: isDeletedDiff ? 'deletion diff' : 'same'
                        });
                        if (currentStyle !== initialStyle) {
                            console.log('🎨 Style Diff:', { initial: initialStyle, current: currentStyle });
                        }
                        console.groupEnd();
                    }
                }
            });

            console.log('Final payload size:', Object.keys(changed).length);
            console.groupEnd();

            return { changed, hasChanges, count: Object.keys(changed).length };
        }

        function updateInitialState() {
            initialContentState = JSON.parse(JSON.stringify(getPageContent()));
            touchedKeys.clear();
            debugLog('Initial state updated. Blocks tracked:', Object.keys(initialContentState).length);
        }

        function markTouched(element, markParent = false) {
            if (!element) return;
            const key = element.getAttribute('data-key') || element.getAttribute('data-link-key');
            if (key) {
                touchedKeys.add(key);
                deletedStructuralKeys.delete(key);
                debugLog('Marked touched:', key);
            } else {
                console.warn('⚠️ markTouched called on element with no data-key:', element);
            }
            if (markParent && element.parentElement) {
                const parent = element.parentElement.closest('[data-key]');
                if (parent) markTouched(parent, true);
            }
        }

        if (btnPreview) {
            btnPreview.addEventListener('click', async () => {
                const prevText = btnPreview.innerHTML;
                btnPreview.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Preparing...';

                const saveResult = await saveFullDraft();

                if (!saveResult.success) {
                    btnPreview.innerHTML = prevText;
                    showToast((isGerman ? 'Vorschau fehlgeschlagen: ' : 'Preview failed: ') + saveResult.message, 'error');
                    return;
                }

                btnPreview.innerHTML = prevText;

                const url = new URL(window.location.href);
                url.searchParams.set('preview', 'true');
                url.searchParams.delete('edit');
                window.open(url.toString(), '_blank');
            });
        }

        let hasUnsavedStructureChanges = false;

        function updateSaveButtonState() {
            if (!btnSaveDraft) return;
            if (hasUnsavedStructureChanges) {
                btnSaveDraft.classList.add('has-unsaved-changes');
            } else {
                btnSaveDraft.classList.remove('has-unsaved-changes');
            }
        }

        async function saveFullDraft() {
            const { changed, hasChanges } = getChangedBlocks();

            if (!hasChanges) {
                hasUnsavedStructureChanges = false;
                updateSaveButtonState();
                return { success: true };
            }

            try {
                const deletedKeysArray = Array.from(deletedStructuralKeys);

                console.group('🚀 INITIATING DATABASE UPLOAD');
                console.log('📦 Changed Blocks (Changeable):', changed);
                if (deletedKeysArray.length > 0) {
                    console.log('🗑️ Structural Deletions:', deletedKeysArray);
                }
                console.groupEnd();

                const contentResult = await fetch('backend/save_page_content.php', {
                    method: 'POST',
                    body: JSON.stringify({
                        page_id: PAGE_ID,
                        blocks: changed,
                        deleted_keys: deletedKeysArray
                    }),
                    headers: { 'Content-Type': 'application/json' }
                }).then(res => res.json());

                if (!contentResult.success) {
                    return { success: false, message: contentResult.message || 'Failed to save content' };
                }

                updateInitialState();
                deletedStructuralKeys.clear();
                hasUnsavedStructureChanges = false;
                updateSaveButtonState();
                return { success: true };
            } catch (err) {
                console.error('Save full draft error:', err);
                return { success: false, message: err.message };
            }
        }

        if (btnSaveDraft) {
            btnSaveDraft.addEventListener('click', async () => {
                const prevText = btnSaveDraft.innerText;
                btnSaveDraft.innerText = L.saving;

                try {
                    const result = await saveFullDraft();
                    if (result.success) {
                        btnSaveDraft.innerText = L.saved;
                        showToast(L.saved, 'success');
                        setTimeout(() => btnSaveDraft.innerText = prevText, 2000);
                    } else {
                        throw new Error(result.message);
                    }
                } catch (err) {
                    btnSaveDraft.innerText = prevText;
                    showToast(L.saveFailed + err.message, 'error');
                }
            });
        }

        btnPublish.addEventListener('click', async () => {
            const confirmed = await showConfirm(L.publishConfirm);
            if (!confirmed) return;

            const prevText = btnPublish.innerText;
            btnPublish.innerText = L.publishing;

            const { changed, hasChanges } = getChangedBlocks();

            let savePromise = Promise.resolve({ success: true });

            const deletedKeysForPublish = Array.from(deletedStructuralKeys);

            if (hasChanges || deletedKeysForPublish.length > 0) {
                savePromise = fetch('backend/save_page_content.php', {
                    method: 'POST',
                    body: JSON.stringify({
                        page_id: PAGE_ID,
                        blocks: changed,
                        deleted_keys: deletedKeysForPublish
                    }),
                    headers: { 'Content-Type': 'application/json' }
                }).then(res => res.json());
            }

            savePromise
                .then((saveResult) => {
                    if (!saveResult.success) {
                        throw new Error(isGerman ? 'Speichern vor Veröffentlichung fehlgeschlagen: ' : 'Pre-publish save failed: ' + saveResult.message);
                    }

                    if (hasChanges || deletedKeysForPublish.length > 0) {
                        updateInitialState();
                        deletedStructuralKeys.clear();
                        hasUnsavedStructureChanges = false;
                        updateSaveButtonState();
                    }

                    return fetch('backend/publish_content.php', {
                        method: 'POST',
                        body: JSON.stringify({ page_id: PAGE_ID }),
                        headers: { 'Content-Type': 'application/json' }
                    });
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        btnPublish.innerText = L.published;
                        showToast(L.published, 'success');
                        const now = new Date().toISOString();
                        localStorage.setItem(`lastEditTime_${PAGE_ID} `, now);
                        updateEditTimeDisplay();
                        setTimeout(() => btnPublish.innerText = L.publish, 2000);
                    } else {
                        showToast(L.publishFailed + data.message, 'error');
                        btnPublish.innerText = L.publish;
                    }
                })
                .catch(err => {
                    showToast(L.error + err.message, 'error');
                    btnPublish.innerText = L.publish;
                });
        });

        function getRelativeTime(timestamp) {
            if (!timestamp) return L.editTime;

            const now = new Date();
            const past = new Date(timestamp);
            const diffMs = now - past;
            const diffSec = Math.floor(diffMs / 1000);
            const diffMin = Math.floor(diffSec / 60);
            const diffHour = Math.floor(diffMin / 60);
            const diffDay = Math.floor(diffHour / 24);

            if (diffSec < 60) return L.justNow;

            let val, unit;
            if (diffMin < 60) {
                val = diffMin;
                unit = val === 1 ? L.minute : L.minutes;
            } else if (diffHour < 24) {
                val = diffHour;
                unit = val === 1 ? L.hour : L.hours;
            } else if (diffDay < 7) {
                val = diffDay;
                unit = val === 1 ? L.day : L.days;
            } else {
                return past.toLocaleDateString(isGerman ? 'de-DE' : 'en-US', { day: '2-digit', month: '2-digit', year: 'numeric' });
            }

            return isGerman ? `${L.ago} ${val} ${unit} ` : `${val} ${unit} ${L.ago} `;
        }

        function updateEditTimeDisplay() {
            const timeEl = document.getElementById('topbar-edit-time');
            if (timeEl) {
                const storageKey = `lastEditTime_${PAGE_ID} `;
                const lastEdit = localStorage.getItem(storageKey);
                if (lastEdit) {
                    timeEl.innerText = `${L.lastEdited} ${getRelativeTime(lastEdit)} `;
                } else {
                    timeEl.innerText = L.editTime;
                }
            }
        }
        updateEditTimeDisplay();
        setInterval(updateEditTimeDisplay, 60000);

        function rgbToHex(rgb) {
            if (!rgb) return '#000000';
            if (rgb.startsWith('#')) return rgb;
            if (rgb === 'transparent' || rgb === 'rgba(0, 0, 0, 0)') return '#000000';

            const rgbMatch = rgb.match(/(\d+)/g);
            if (!rgbMatch || rgbMatch.length < 3) return '#000000';

            if (rgbMatch.length === 4 && parseInt(rgbMatch[3]) === 0) return '#000000';

            const r = parseInt(rgbMatch[0]);
            const g = parseInt(rgbMatch[1]);
            const b = parseInt(rgbMatch[2]);

            return "#" + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
        }

        function findEditableChildren(parent, list, addNodeFn) {
            const editables = parent.querySelectorAll('[data-editable], [data-editable-link], [data-editable-bg], .editable-image-wrapper');
            editables.forEach(el => {
                const name = el.getAttribute('data-key') || el.tagName.toLowerCase();
                let icon = 'fas fa-font';
                if (el.classList.contains('editable-image-wrapper')) icon = 'fas fa-image';
                else if (el.hasAttribute('data-editable-link')) icon = 'fas fa-link';
                else if (el.hasAttribute('data-editable-bg')) icon = 'fas fa-palette';

                addNodeFn(name, icon, el, list, false);
            });
        }

        function showToast(message, type = 'info') {
            try {
                if (isGerman && typeof message === 'string') {
                    const trailing = message.match(/[.!?]\s*$/);
                    const punct = trailing ? trailing[0] : '';
                    const key = message.replace(/[.!?]\s*$/, '');

                    const direct = {
                        'Section deleted': 'Abschnitt gelöscht',
                        'Group deleted': 'Gruppe gelöscht',
                        'Saved': L.saved || 'Gespeichert!',
                        'Save Failed': L.saveFailed ? L.saveFailed.replace(/: ?$/, '') : 'Speichern fehlgeschlagen',
                        'Section deleted.': 'Abschnitt gelöscht',
                        'Section deleted': 'Abschnitt gelöscht'
                    };

                    const loweredKey = key.trim();
                    if (direct.hasOwnProperty(loweredKey)) {
                        message = direct[loweredKey] + (punct ? punct.trim() : '');
                    } else {
                        const m = key.match(/^(.*?) component added successfully$/i);
                        if (m) {
                            const comp = m[1];
                            const compKey = comp.toLowerCase();
                            const compLabel = (L[compKey] || comp);
                            message = compLabel + ' erfolgreich hinzugefügt' + (punct ? punct.trim() : '!');
                        }
                    }
                }
            } catch (err) {
            }
            let container = document.querySelector('.editor-toast-container');
            if (!container) {
                container = document.createElement('div');
                container.className = 'editor-toast-container';
                document.body.appendChild(container);
            }

            const toast = document.createElement('div');
            toast.className = `editor - toast ${type} `;

            const titles = {
                success: L.successTitle,
                error: L.errorTitle,
                info: L.infoTitle,
                warning: L.warningTitle
            };

            const icons = {
                success: heroIcons.checkCircle,
                error: heroIcons.exclamationCircle,
                info: heroIcons.infoCircle,
                warning: heroIcons.exclamationTriangle
            };

            toast.innerHTML = `
                <div class="editor-toast-icon">${icons[type] || icons.info}</div>
                <div class="editor-toast-content">
                    <div class="editor-toast-title">${titles[type] || titles.info}</div>
                    <div class="editor-toast-message">${message}</div>
                </div>
                <button class="editor-toast-close" style="background: transparent; color: white; border: none; cursor: pointer;">
                    ${heroIcons.xMark}
                </button>
                    `;

            container.appendChild(toast);

            const activeToasts = Array.from(container.querySelectorAll('.editor-toast:not(.hide)'));
            if (activeToasts.length > 3) {
                const toRemove = activeToasts.length - 3;
                for (let i = 0; i < toRemove; i++) {
                    const t = activeToasts[i];
                    t.classList.remove('active');
                    t.classList.add('hide');
                    setTimeout(() => t.remove(), 400);
                }
            }

            setTimeout(() => toast.classList.add('active'), 10);

            const closeBtn = toast.querySelector('.editor-toast-close');
            closeBtn.onclick = () => {
                toast.classList.remove('active');
                toast.classList.add('hide');
                setTimeout(() => toast.remove(), 400);
            };

            setTimeout(() => {
                if (toast.parentElement) {
                    toast.classList.remove('active');
                    toast.classList.add('hide');
                    setTimeout(() => toast.remove(), 400);
                }
            }, 4500);
        }

        async function showConfirm(message, title = L.confirmTitle) {
            return new Promise((resolve) => {
                const overlay = document.createElement('div');
                overlay.className = 'editor-modal-overlay';

                overlay.innerHTML = `
                    <div class="editor-modal">
                        <div class="editor-modal-title">${title}</div>
                        <div class="editor-modal-message">${message}</div>
                        <div class="editor-modal-actions">
                            <button class="editor-modal-btn editor-modal-btn-cancel">${L.cancel}</button>
                            <button class="editor-modal-btn editor-modal-btn-confirm">${L.confirm}</button>
                        </div>
                    </div>
                `;

                document.body.appendChild(overlay);

                const btnCancel = overlay.querySelector('.editor-modal-btn-cancel');
                const btnConfirm = overlay.querySelector('.editor-modal-btn-confirm');

                btnCancel.onclick = () => {
                    cleanup();
                    resolve(false);
                };

                btnConfirm.onclick = () => {
                    cleanup();
                    resolve(true);
                };

                const cleanup = () => {
                    overlay.style.animation = 'modal-fade-in 0.3s reverse forwards';
                    overlay.querySelector('.editor-modal').style.animation = 'modal-zoom-in 0.3s reverse forwards';
                    setTimeout(() => overlay.remove(), 300);
                };
            });
        }
        updateInitialState();
    }
});