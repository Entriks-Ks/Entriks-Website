(function () {
  const DEFAULT_PREFS = { functional: true, statistics: false, marketing: false };
  const TEXT = {
    de: {
      title: "Zustimmung verwalten",
      intro: "Um Ihnen ein optimales Erlebnis zu bieten, verwenden wir Technologien wie Cookies, um Geräteinformationen zu speichern bzw. darauf zuzugreifen. Wenn Sie diesen Technologien zustimmen, können wir Daten wie das Surfverhalten oder eindeutige IDs auf dieser Website verarbeiten.",
      functional: "Funktional",
      functionalNote: "Immer aktiv",
      functionalDesc: "Die technische Speicherung oder der Zugriff ist unbedingt erforderlich, um die Nutzung der Website zu ermöglichen.",
      statistics: "Statistiken",
      statisticsDesc: "Die technische Speicherung oder der Zugriff, der ausschließlich zu anonymen statistischen Zwecken verwendet wird.",
      marketing: "Marketing",
      marketingDesc: "Die technische Speicherung oder der Zugriff ist erforderlich, um Nutzerprofile zu erstellen, um Werbung zu versenden oder den Nutzer auf einer Website oder über mehrere Websites hinweg zu ähnlichen Marketingzwecken zu verfolgen.",
      accept: "Akzeptieren",
      decline: "Ablehnen",
      save: "Abspeichern",
      customize: "Anpassen",
    },
    en: {
      title: "Manage consent",
      intro: "To provide you with the best experience, we use technologies like cookies to store or access device information. If you consent to these technologies, we may process data such as browsing behavior or unique IDs on this site.",
      functional: "Functional",
      functionalNote: "Always active",
      functionalDesc: "Technical storage or access is strictly necessary to enable the use of the website.",
      statistics: "Statistics",
      statisticsDesc: "Technical storage or access that is used exclusively for anonymous statistical purposes.",
      marketing: "Marketing",
      marketingDesc: "Technical storage or access is required to create user profiles, to send advertising, or to track the user across websites for similar marketing purposes.",
      accept: "Accept",
      decline: "Decline",
      save: "Save",
      customize: "Customize",
    }
  };

  const lang = (document.documentElement.lang && document.documentElement.lang.startsWith('en')) ? 'en' : 'de';
  const t = TEXT[lang] || TEXT.de;

  function getPrefs() {
    try { const s = localStorage.getItem('cookiePrefs'); return s ? JSON.parse(s) : null } catch (e) { return null }
  }
  function runScripts(prefs) {
    if (!prefs) return;

    // Update Google Consent Mode based on user preferences
    window.dataLayer = window.dataLayer || [];
    function gtag() { dataLayer.push(arguments); }

    gtag('consent', 'update', {
      'analytics_storage': prefs.statistics ? 'granted' : 'denied',
      'ad_storage': prefs.marketing ? 'granted' : 'denied',
      'ad_user_data': prefs.marketing ? 'granted' : 'denied',
      'ad_personalization': prefs.marketing ? 'granted' : 'denied'
    });

    // If you add other scripts (Facebook Pixel, etc.), put them here
    if (prefs.marketing) {
      // e.g., fbq('track', 'PageView');
    }
  }

  function loadScript(src) {
    if (document.querySelector(`script[src="${src}"]`)) return;
    const s = document.createElement('script');
    s.src = src;
    s.async = true;
    document.head.appendChild(s);
  }

  function savePrefs(next) {
    const final = Object.assign({}, DEFAULT_PREFS, next);
    localStorage.setItem('cookiePrefs', JSON.stringify(final));
    document.dispatchEvent(new CustomEvent('cookieConsentSaved', { detail: final }));
    runScripts(final);
  }

  function createFloating() {
    if (document.querySelector('.cookie-compact')) return;
    const wrap = document.createElement('div'); wrap.className = 'cookie-compact';
    const btn = document.createElement('button'); btn.className = 'cookie-toggle'; btn.title = 'cookie settings';
    btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a10 10 0 1 0 10 10 4 4 0 0 1-5-5 4 4 0 0 1-5-5"></path><path d="M8.5 8.5v.01"></path><path d="M16 15.5v.01"></path><path d="M12 12v.01"></path><path d="M11 17v.01"></path><path d="M7 14v.01"></path></svg>';
    btn.addEventListener('click', () => { openModal(false) });
    wrap.appendChild(btn);
    document.body.appendChild(wrap);

    const saved = getPrefs();
    if (saved) runScripts(saved);
  }

  let modalOverlay, prefsState = Object.assign({}, DEFAULT_PREFS), expanded = {};

  function buildModal() {
    if (modalOverlay) return;
    modalOverlay = document.createElement('div'); modalOverlay.className = 'cookie-modal-overlay';
    modalOverlay.addEventListener('click', (e) => { if (e.target === modalOverlay) closeModal(); });
    const aside = document.createElement('aside'); aside.className = 'cookie-panel';
    const inner = document.createElement('div'); inner.className = 'cookie-panel-inner';
    aside.appendChild(inner); modalOverlay.appendChild(aside); document.body.appendChild(modalOverlay);
  }

  function renderInitial() {
    const inner = modalOverlay.querySelector('.cookie-panel-inner');
    inner.innerHTML = `
      <div class="cookie-panel-top">
        <h3>${t.title}</h3>
        <button class="cookie-close">✕</button>
      </div>
      <div class="cookie-panel-head"><p>${t.intro}</p></div>
      <div class="cookie-panel-actions center">
        <button class="btn btn-accept" id="cookie-accept-all">${t.accept}</button>
        <button class="btn btn-decline" id="cookie-decline-all">${t.decline}</button>
        <button class="btn btn-save" id="cookie-customize">${t.customize}</button>
      </div>
    `;
    inner.querySelector('.cookie-close').addEventListener('click', closeModal);
    inner.querySelector('#cookie-accept-all').addEventListener('click', () => { savePrefs({ functional: true, statistics: true, marketing: true }); closeModal(); });
    inner.querySelector('#cookie-decline-all').addEventListener('click', () => { savePrefs({ functional: true, statistics: false, marketing: false }); closeModal(); });
    inner.querySelector('#cookie-customize').addEventListener('click', renderCustomize);
  }

  function renderCustomize() {
    const inner = modalOverlay.querySelector('.cookie-panel-inner');
    inner.innerHTML = `
      <div class="cookie-panel-top">
        <h3>${t.title}</h3>
        <button class="cookie-close">✕</button>
      </div>
      <div class="cookie-panel-head"><p>${t.intro}</p></div>
      <div class="cookie-options"></div>
      <div class="cookie-panel-actions">
        <button class="btn btn-decline" id="cookie-decline-all-custom">${t.decline}</button>
        <button class="btn btn-save" id="cookie-save-prefs">${t.save}</button>
      </div>
    `;

    inner.querySelector('.cookie-close').addEventListener('click', closeModal);
    inner.querySelector('#cookie-decline-all-custom').addEventListener('click', () => { closeModal(); });
    inner.querySelector('#cookie-save-prefs').addEventListener('click', () => { savePrefs(prefsState); closeModal(); });

    const optsContainer = inner.querySelector('.cookie-options');
    ['functional', 'statistics', 'marketing'].forEach(key => {
      const row = document.createElement('div');
      row.className = `cookie-row ${expanded[key] ? 'expanded' : ''}`;

      const rightContent = key === 'functional'
        ? `<span class="functional-status">${t.functionalNote}</span>`
        : `<label class="switch"><input type="checkbox" ${prefsState[key] ? 'checked' : ''} data-key="${key}"><span class="slider"></span></label>`;

      row.innerHTML = `
        <div class="cookie-row-head">
          <div class="cookie-row-head-left"><strong>${t[key]}</strong></div>
          <div class="cookie-row-head-right">
            ${rightContent}
            <div class="cookie-toggle-chev">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
            </div>
          </div>
        </div>
        ${expanded[key] ? `<div class="cookie-details">${t[`${key}Desc`]}</div>` : ''}
      `;

      row.querySelector('.cookie-row-head').addEventListener('click', (e) => {
        if (e.target.closest('.switch') || e.target.closest('.functional-status')) return;
        expanded[key] = !expanded[key];
        renderCustomize();
      });

      if (key !== 'functional') {
        const input = row.querySelector('input');
        input.addEventListener('change', (e) => {
          prefsState[key] = e.target.checked;
        });
      }

      optsContainer.appendChild(row);
    });
  }

  function openModal(showCustomize) {
    prefsState = Object.assign({}, DEFAULT_PREFS, getPrefs() || {});
    if (!modalOverlay) buildModal();
    document.body.style.overflow = 'hidden';
    modalOverlay.style.display = 'flex';
    if (showCustomize) renderCustomize();
    else renderInitial();
  }

  function closeModal() {
    if (modalOverlay) modalOverlay.style.display = 'none';
    document.body.style.overflow = '';
  }

  window.addEventListener('load', () => {
    if (typeof window.cookieConsentEnabled !== 'undefined' && window.cookieConsentEnabled === false) {
      localStorage.removeItem('cookiePrefs');
      return;
    }

    setTimeout(() => {
      createFloating();
      if (!getPrefs()) openModal(false);
    }, 1500);
  });

})();