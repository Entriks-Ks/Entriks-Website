let aiMessageHistory = [];
let selectedAIModel = 'gemini-2.5-flash';

function escapeHTML(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

const starSVG = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z" /></svg>`;
const userSVG = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M7.5 6a4.5 4.5 0 1 1 9 0 4.5 4.5 0 0 1-9 0ZM3.751 20.105a8.25 8.25 0 0 1 16.498 0 .75.75 0 0 1-.437.695A18.683 18.683 0 0 1 12 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 0 1-.437-.695Z" clip-rule="evenodd" /></svg>`;
const refreshSVG = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>`;
const closeSVG = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>`;
const insertSVG = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6">
  <path fill-rule="evenodd" d="M5.625 1.5H9a3.75 3.75 0 0 1 3.75 3.75v1.875c0 1.036.84 1.875 1.875 1.875H16.5a3.75 3.75 0 0 1 3.75 3.75v7.875c0 1.035-.84 1.875-1.875 1.875H5.625a1.875 1.875 0 0 1-1.875-1.875V3.375c0-1.036.84-1.875 1.875-1.875ZM12.75 12a.75.75 0 0 0-1.5 0v2.25H9a.75.75 0 0 0 0 1.5h2.25V18a.75.75 0 0 0 1.5 0v-2.25H15a.75.75 0 0 0 0-1.5h-2.25V12Z" clip-rule="evenodd" />
  <path d="M14.25 5.25a5.23 5.23 0 0 0-1.279-3.434 9.768 9.768 0 0 1 6.963 6.963A5.23 5.23 0 0 0 16.5 7.5h-1.875a.375.375 0 0 1-.375-.375V5.25Z" />
</svg>`;
const copySVG = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6">
  <path fill-rule="evenodd" d="M7.502 6h7.128A3.375 3.375 0 0 1 18 9.375v9.375a3 3 0 0 0 3-3V6.108c0-1.505-1.125-2.811-2.664-2.94a48.972 48.972 0 0 0-.673-.05A3 3 0 0 0 15 1.5h-1.5a3 3 0 0 0-2.663 1.618c-.225.015-.45.032-.673.05C8.662 3.295 7.554 4.542 7.502 6ZM13.5 3A1.5 1.5 0 0 0 12 4.5h4.5A1.5 1.5 0 0 0 15 3h-1.5Z" clip-rule="evenodd" />
  <path fill-rule="evenodd" d="M3 9.375C3 8.339 3.84 7.5 4.875 7.5h9.75c1.036 0 1.875.84 1.875 1.875v11.25c0 1.035-.84 1.875-1.875 1.875h-9.75A1.875 1.875 0 0 1 3 20.625V9.375ZM6 12a.75.75 0 0 1 .75-.75h.008a.75.75 0 0 1 .75.75v.008a.75.75 0 0 1-.75.75H6.75a.75.75 0 0 1-.75-.75V12Zm2.25 0a.75.75 0 0 1 .75-.75h3.75a.75.75 0 0 1 0 1.5H9a.75.75 0 0 1-.75-.75ZM6 15a.75.75 0 0 1 .75-.75h.008a.75.75 0 0 1 .75.75v.008a.75.75 0 0 1-.75.75H6.75a.75.75 0 0 1-.75-.75V15Zm2.25 0a.75.75 0 0 1 .75-.75h3.75a.75.75 0 0 1 0 1.5H9a.75.75 0 0 1-.75-.75ZM6 18a.75.75 0 0 1 .75-.75h.008a.75.75 0 0 1 .75.75v.008a.75.75 0 0 1-.75.75H6.75a.75.75 0 0 1-.75-.75V18Zm2.25 0a.75.75 0 0 1 .75-.75h3.75a.75.75 0 0 1 0 1.5H9a.75.75 0 0 1-.75-.75Z" clip-rule="evenodd" />
</svg>`;
const checkmarkSVG = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm13.36-1.814a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z" clip-rule="evenodd" /></svg>`;
const downSVG = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>`;

document.addEventListener('DOMContentLoaded', () => {
    injectAIHTML();
    initSmartRecommendations();
});

function initSmartRecommendations() {
    document.addEventListener('click', function (e) {
        const reco = e.target.closest('.ai-reco');
        if (!reco) return;

        const type = reco.getAttribute('data-type');
        const content = reco.innerText.trim();

        if (type === 'title') {
            const titleInput = document.getElementById('title');
            if (titleInput) {
                titleInput.value = content;
            }

            const aiInput = document.getElementById('aiInput');
            if (aiInput) {
                const draftPrompt = window.entraLanguage === 'de'
                    ? `Schreibe einen Blogartikel über: ${content}`
                    : `Write a blog post about: ${content}`;
                aiInput.value = draftPrompt;
                aiInput.style.height = 'auto';
                aiInput.style.height = (aiInput.scrollHeight) + 'px';
                aiInput.focus();
            }

            const successMsg = window.entraLanguage === 'de' ? 'Titel erfolgreich angewendet!' : 'Title applied successfully!';
            showToast(successMsg, 'success');
        }
        else if (type === 'idea') {
            const titleInput = document.getElementById('title');
            if (titleInput) {
                titleInput.value = content;
            }

            const aiInput = document.getElementById('aiInput');
            if (aiInput) {
                const draftPrompt = window.entraLanguage === 'de' ? `Schreibe einen vollständigen Blogartikel über: ${content}` : `Write a complete blog post about: ${content}`;
                aiInput.value = draftPrompt;
                aiInput.style.height = 'auto';
                aiInput.style.height = (aiInput.scrollHeight) + 'px';
                aiInput.focus();

                const successMsg = window.entraLanguage === 'de' ? 'Idee ausgewählt! Bearbeiten und senden.' : 'Idea selected! Edit and send.';
                showToast(successMsg, 'success');
            }
        }
        else if (type === 'content') {
            const editor = document.getElementById('contentEditor');
            if (editor && typeof processNodes === 'function') {
                const sanitizedHTML = typeof DOMPurify !== 'undefined' ? DOMPurify.sanitize(reco.innerHTML) : reco.innerHTML;
                const temp = document.createElement('div');
                temp.innerHTML = sanitizedHTML;
                processNodes(Array.from(temp.childNodes));
                const insertMsg = window.entraLanguage === 'de' ? 'Inhalt in den Editor eingefügt.' : 'Content inserted into editor.';
                showToast(insertMsg, 'success');
            } else if (editor) {
                const sanitizedHTML = typeof DOMPurify !== 'undefined' ? DOMPurify.sanitize(reco.innerHTML) : reco.innerHTML;
                editor.insertAdjacentHTML('beforeend', sanitizedHTML);
                const insertMsg = window.entraLanguage === 'de' ? 'Inhalt in den Editor eingefügt.' : 'Content inserted into editor.';
                showToast(insertMsg, 'success');
            }
        }
    });
}

function injectAIHTML() {
    if (document.getElementById('aiFloatingBtn')) return;

    const isEditPage = window.location.pathname.includes('edit.php');
    const isCreatePage = window.location.pathname.includes('create.php');

    let promptsHtml = '';

    if (isEditPage) {
        const isGerman = window.entraLanguage === 'de';
        promptsHtml = `
            <div class="ai-prompt-card" onclick="quickPrompt('${isGerman ? 'Gib eine kurze professionelle Bewertung meines Inhalts ab. Hebe Stärken, Schwächen, Klarheit und Lesefluss in 4-6 Aufzählungspunkten hervor. Schreibe den Inhalt NICHT ohne Aufforderung neu.' : 'Provide a short professional review of my content. Highlight strengths, weaknesses, clarity issues, and flow problems in 4–6 bullet points. Do NOT rewrite the content unless asked.'}')">
                <div class="ai-prompt-card-text">${isGerman ? 'Überprüfung' : 'Review'}</div>
            </div>
            <div class="ai-prompt-card" onclick="quickPrompt('${isGerman ? 'Optimiere den Inhalt für SEO. Erstelle: 1) Eine SEO-Zusammenfassung (2-3 Sätze), 2) Eine Meta-Beschreibung (155-160 Zeichen), 3) 3-5 Punkte für SEO-Verbesserungen, 4) Verbesserte Überschriften. Schreibe NICHT den gesamten Artikel neu.' : 'Optimize the content for SEO. Provide: 1) A 2–3 sentence SEO summary, 2) One 155–160 character meta description, 3) 3–5 bullet points for SEO improvements, 4) Improved section headings. Do NOT rewrite the entire article.'}')">
                <div class="ai-prompt-card-text">${isGerman ? 'SEO Optimieren' : 'Optimize SEO'}</div>
            </div>
            <div class="ai-prompt-card" onclick="quickPrompt('${isGerman ? 'Überarbeite meinen Text mit verbesserter Grammatik, Klarheit und Tonfall. Behalte die ursprüngliche Bedeutung bei. Gib NUR die verbesserte Version aus – ohne Erklärungen.' : 'Rewrite my text with improved grammar, clarity, and tone. Keep the original meaning intact. Output ONLY the improved version — no explanations.'}')">
                <div class="ai-prompt-card-text">${isGerman ? 'Grammatik & Ton' : 'Grammar & Tone'}</div>
            </div>
            <div class="ai-prompt-card" onclick="quickPrompt('${isGerman ? 'Generiere 5-8 verbesserte Titeloptionen basierend auf meinem aktuellen Titel. Mache sie klarer, ansprechender und SEO-freundlicher. Halte die Vorschläge kurz.' : 'Generate 5–8 improved title options based on my existing title. Make them clearer, more engaging, and more SEO‑friendly. Keep suggestions short.'}')">
                <div class="ai-prompt-card-text">${isGerman ? 'Titel Verbessern' : 'Improve Title'}</div>
            </div>
        `;
    } else if (isCreatePage) {
        const isGerman = window.entraLanguage === 'de';
        promptsHtml = `
            <div class="ai-prompt-card" onclick="quickPrompt('${isGerman ? 'Generiere 8-12 kreative Themenideen basierend auf meiner Nische oder meinen Keywords. Halte jede Idee kurz, klar und aussagekräftig. Schreibe KEINE vollständigen Artikel.' : 'Generate 8–12 creative blog post topic ideas based on my niche or keywords. Keep each idea short, clear, and high‑impact. DO NOT write full articles.'}')">
                <div class="ai-prompt-card-text">${isGerman ? 'Themen Brainstormen' : 'Brainstorm Topics'}</div>
            </div>
            <div class="ai-prompt-card" onclick="quickPrompt('${isGerman ? 'Erstelle eine strukturierte Gliederung mit H2 und optionalen H3 Abschnitten. Halte sie klar und logisch. Schreibe NICHT den Artikel – nur die Gliederung.' : 'Create a structured blog post outline with H2 and optional H3 sections. Keep it clear, logical, and easy to follow. Do NOT write the article — only the outline.'}')">
                <div class="ai-prompt-card-text">${isGerman ? 'Detaillierte Gliederung' : 'Detailed Outline'}</div>
            </div>
            <div class="ai-prompt-card" onclick="quickPrompt('${isGerman ? 'Schreibe eine fesselnde Einleitung (4-6 Sätze). Wecke das Interesse, erstelle Kontext und führe in das Hauptthema ein. Schreibe KEINE weiteren Abschnitte.' : 'Write a compelling blog post introduction (4–6 sentences). Hook the reader, set context, and introduce the main topic. Do NOT continue into full sections.'}')">
                <div class="ai-prompt-card-text">${isGerman ? 'Einleitung Verfassen' : 'Draft Introduction'}</div>
            </div>
            <div class="ai-prompt-card" onclick="quickPrompt('${isGerman ? 'Generiere 6-10 aufmerksamkeitsstarke Titel. Mische SEO-optimierte Optionen, kreative Hooks und kurze, prägnante Alternativen. Halte die Titel kurz.' : 'Generate 6–10 attention‑grabbing blog post titles. Mix SEO‑optimized options, creative hooks, and short punchy alternatives. Keep titles concise.'}')">
                <div class="ai-prompt-card-text">${isGerman ? 'Titelideen' : 'Title Ideas'}</div>
            </div>
        `;
    } else {
        promptsHtml = `
            <div class="ai-prompt-card" onclick="quickPrompt('Give me a summary of my blog performance and content strategy.')">
                <div class="ai-prompt-card-text">Strategy Summary</div>
            </div>
            <div class="ai-prompt-card" onclick="quickPrompt('What are some trending blog post ideas for my niche?')">
                <div class="ai-prompt-card-text">Niche Trends</div>
            </div>
        `;
    }

    const html = `
    <!-- Floating Button -->
    <button class="ai-floating-btn" id="aiFloatingBtn" onclick="toggleAIChat()">
        ${starSVG}
    </button>

    <!-- Chat Panel -->
    <div class="ai-chat-panel" id="aiChatPanel">
        <div class="ai-chat-header">
            <div class="ai-header-avatar">${starSVG}</div>
            <div class="ai-header-info">
                <p class="ai-chat-title">ENTRIKS AI</p>
            </div>
            <select id="aiModelSelect" class="ai-model-select" onchange="updateModel(this.value)">
                <option value="gemini-2.0-flash" selected>Model 1</option>
                <option value="gemini-2.0-flash-lite">Model 2</option>
                <option value="gemini-2.0-pro">Model 3</option>
                <option value="gemini-1.5-pro">Model 4</option>
            </select>
        </div>
        <div class="ai-chat-body">
            <div class="ai-chat-history" id="aiChatHistory">
                <div class="ai-message ai-message-bot">
                    <div class="ai-msg-avatar ai-bot-avatar">${starSVG}</div>
                    <div class="ai-message-bubble">
                        <p>${window.entraLanguage === 'de' ? `Hallo ${window.adminName || 'da'}! Wie kann ich dir bei deinem Blog helfen?` : `Hi ${window.adminName || 'there'}! How can I help you with your blog post today?`}</p>
                    </div>
                </div>
            </div>
            <div class="ai-prompts" id="aiPrompts">
                <div class="ai-prompts-grid">
                    ${promptsHtml}
                </div>
            </div>
        </div>
        <button class="ai-scroll-bottom" id="aiScrollBottom" onclick="scrollToBottom()">
            ${downSVG}
        </button>
        <div class="ai-input-area">
            <div class="ai-input-wrapper">
                <textarea id="aiInput" placeholder="${window.entraLanguage === 'de' ? 'Schreibe deine Nachricht...' : 'Type your message...'}" rows="1" onkeydown="if(event.key==='Enter' && !event.shiftKey){ event.preventDefault(); sendMessage(); }" oninput="this.style.height = 'auto'; this.style.height = (this.scrollHeight) + 'px'; scrollToBottom();"></textarea>
                <button class="ai-send-btn" id="aiSendBtn" onclick="sendMessage()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"></path></svg>
                </button>
            </div>
        </div>
    </div>`;

    document.body.insertAdjacentHTML('beforeend', html);

    const historyDiv = document.getElementById('aiChatHistory');
    const scrollBtn = document.getElementById('aiScrollBottom');
    if (historyDiv && scrollBtn) {
        historyDiv.onscroll = function () {
            const threshold = 150;
            const diff = historyDiv.scrollHeight - historyDiv.scrollTop - historyDiv.clientHeight;
            if (diff > threshold) {
                scrollBtn.classList.add('visible');
            } else {
                scrollBtn.classList.remove('visible');
            }
        };
    }
}

window.toggleAIChat = function () {
    const panel = document.getElementById('aiChatPanel');
    const btn = document.getElementById('aiFloatingBtn');
    panel.classList.toggle('open');
    if (panel.classList.contains('open')) {
        btn.innerHTML = closeSVG;
        document.getElementById('aiInput').focus();
        scrollToBottom();
    } else {
        btn.innerHTML = starSVG;
    }
}

window.scrollToBottom = function () {
    const historyDiv = document.getElementById('aiChatHistory');
    const scrollBtn = document.getElementById('aiScrollBottom');
    if (historyDiv) {
        historyDiv.scrollTop = historyDiv.scrollHeight;
        if (scrollBtn) scrollBtn.classList.remove('visible');
    }
}

window.updateModel = function (model) {
    selectedAIModel = model;
}

window.quickPrompt = function (text) {
    const input = document.getElementById('aiInput');
    input.value = text;
    input.style.height = 'auto';
    input.style.height = (input.scrollHeight) + 'px';
    input.focus();
    scrollToBottom();
}

async function typewriterEffect(element, html) {
    try {
        const temp = document.createElement('div');
        temp.innerHTML = html;
        const nodes = Array.from(temp.childNodes);
        element.innerHTML = '';

        if (html.length > 2000) {
            element.innerHTML = html;
            const historyDiv = document.getElementById('aiChatHistory');
            if (historyDiv) historyDiv.scrollTop = historyDiv.scrollHeight;
            return;
        }

        const baseDelay = 15;
        const getRandomDelay = () => baseDelay + Math.random() * 15;

        async function typeNode(node, parent) {
            if (node.nodeType === Node.TEXT_NODE) {
                const text = node.textContent;
                const textSpan = document.createTextNode('');
                parent.appendChild(textSpan);

                for (let i = 0; i < text.length; i++) {
                    textSpan.textContent += text[i];
                    await new Promise(r => setTimeout(r, getRandomDelay()));

                    if (i % 5 === 0) {
                        const historyDiv = document.getElementById('aiChatHistory');
                        if (historyDiv) historyDiv.scrollTop = historyDiv.scrollHeight;
                    }
                }
            } else if (node.nodeType === Node.ELEMENT_NODE) {
                const clone = document.createElement(node.tagName);
                for (let attr of node.attributes) {
                    clone.setAttribute(attr.name, attr.value);
                }
                parent.appendChild(clone);
                for (let child of node.childNodes) {
                    await typeNode(child, clone);
                }

                await new Promise(r => setTimeout(r, 30));
            }
        }

        for (const node of nodes) {
            await typeNode(node, element);
        }

        const historyDiv = document.getElementById('aiChatHistory');
        if (historyDiv) historyDiv.scrollTop = historyDiv.scrollHeight;
    } catch (error) {
        console.error('Typewriter error:', error);
        element.innerHTML = html;
    }
}

window.sendMessage = async function () {
    const userLang = window.entraLanguage || 'en';
    const input = document.getElementById('aiInput');
    const sendBtn = document.getElementById('aiSendBtn');
    const historyDiv = document.getElementById('aiChatHistory');
    const message = input.value.trim();

    if (!message || sendBtn.disabled) return;

    const titleVal = (document.getElementById('title')?.value || document.querySelector('input[name="title"]')?.value || '').trim();
    const contentVal = (document.getElementById('contentEditor')?.innerText || '').trim();
    const isTitlePrompt = (message.includes('6-10') || message.includes('6\u201310')) &&
        (message.toLowerCase().includes('titles') || message.toLowerCase().includes('titel'));

    if (isTitlePrompt && !titleVal && (!contentVal || contentVal.length < 10)) {
        historyDiv.insertAdjacentHTML('beforeend', `
            <div class="ai-message ai-message-user">
                <div class="ai-message-bubble"><p>${escapeHTML(message)}</p></div>
                <div class="ai-msg-avatar ai-user-avatar">${userSVG}</div>
            </div>
        `);
        input.value = '';
        input.style.height = 'auto';

        const botQuestion = userLang === 'de'
            ? 'Um welches Thema oder Fachgebiet soll es in diesen Blogpost-Titeln gehen?'
            : 'What topic or subject should these blog post titles be about?';

        aiMessageHistory.push({ role: 'user', content: message });
        aiMessageHistory.push({ role: 'model', content: `<p>${botQuestion}</p>` });

        setTimeout(() => {
            historyDiv.insertAdjacentHTML('beforeend', `
                <div class="ai-message ai-message-bot">
                    <div class="ai-msg-avatar ai-bot-avatar">${starSVG}</div>
                    <div class="ai-message-bubble"><p>${botQuestion}</p></div>
                </div>
            `);
            historyDiv.scrollTop = historyDiv.scrollHeight;
        }, 300);
        return;
    }

    sendBtn.disabled = true;
    historyDiv.insertAdjacentHTML('beforeend', `
        <div class="ai-message ai-message-user">
            <div class="ai-message-bubble"><p>${escapeHTML(message)}</p></div>
            <div class="ai-msg-avatar ai-user-avatar">${userSVG}</div>
        </div>
    `);

    input.value = '';
    input.style.height = 'auto';
    historyDiv.scrollTop = historyDiv.scrollHeight;

    const prompts = document.getElementById('aiPrompts');
    if (prompts) prompts.style.display = 'none';

    const loadingId = 'ai-loading-' + Date.now();
    historyDiv.insertAdjacentHTML('beforeend', `
        <div class="ai-message ai-message-bot" id="${loadingId}">
            <div class="ai-msg-avatar ai-bot-avatar">${starSVG}</div>
            <div class="ai-message-bubble">
                <div class="ai-typing-indicator"><span></span><span></span><span></span></div>
            </div>
        </div>
    `);
    historyDiv.scrollTop = historyDiv.scrollHeight;

    try {
        const response = await fetch('../api/ai_chat.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                csrf_token: window.csrfToken,
                instruction: message,
                current_title: titleVal,
                current_content: contentVal,
                model: selectedAIModel,
                language: userLang,
                history: aiMessageHistory.slice(-6)
            })
        });

        const data = await response.json();
        document.getElementById(loadingId)?.remove();

        if (data.success) {
            const sanitizedHTML = typeof DOMPurify !== 'undefined'
                ? DOMPurify.sanitize(data.html, {
                    ALLOWED_TAGS: ['p', 'h2', 'h3', 'ul', 'ol', 'li', 'strong', 'em', 'b', 'i', 'blockquote', 'span', 'div'],
                    ALLOWED_ATTR: ['class', 'data-type']
                })
                : data.html;

            aiMessageHistory.push({ role: 'user', content: message });
            aiMessageHistory.push({ role: 'model', content: sanitizedHTML });

            if (aiMessageHistory.length > 20) {
                aiMessageHistory = aiMessageHistory.slice(-20);
            }

            const resultId = 'ai-res-' + Date.now();
            const previewId = resultId + '-preview';

            historyDiv.insertAdjacentHTML('beforeend', `
                <div class="ai-message ai-message-bot">
                    <div class="ai-msg-avatar ai-bot-avatar">${starSVG}</div>
                    <div class="ai-message-bubble">
                        <div class="ai-content-preview" id="${previewId}"></div>
                        <div id="${resultId}" style="display:none;">${sanitizedHTML}</div>
                    </div>
                </div>
                <div class="ai-message-actions" style="display:none; margin-top:8px; margin-left:38px;" id="${resultId}-actions">
                    <button class="ai-btn-small" onclick="insertAIContent('${resultId}', this)">${insertSVG} <span>${window.entraLanguage === 'de' ? 'Einfügen' : 'Insert'}</span></button>
                    <button class="ai-btn-small" onclick="copyToClipboard('${resultId}', this)">${copySVG} <span>${window.entraLanguage === 'de' ? 'Kopieren' : 'Copy'}</span></button>
                </div>
            `);

            const previewEl = document.getElementById(previewId);
            await typewriterEffect(previewEl, sanitizedHTML);

            const mode = data.mode || 'conversation';

            if (mode === 'content_creation' || mode === 'grammar/tone' || mode === 'grammar' || mode === 'seo' || mode === 'review') {
                document.getElementById(resultId + '-actions').style.display = 'flex';
            } else if (mode === 'brainstorm' || mode === 'title_list') {
                const regenLabel = window.entraLanguage === 'de' ? 'Andere Vorschläge' : 'Regenerate Ideas';
                const regenPrompt = mode === 'title_list'
                    ? (window.entraLanguage === 'de' ? 'Generiere 6-10 neue, andere Titelvorschläge.' : 'Generate 6-10 new, different title ideas.')
                    : (window.entraLanguage === 'de' ? 'Generiere diese Ideen mit mehr Abwechslung neu.' : 'Regenerate those ideas with more variety.');
                historyDiv.insertAdjacentHTML('beforeend', `
                    <div class="ai-message-actions" style="display:flex; margin-top:8px; margin-left:38px;">
                        <button class="ai-btn-small ai-btn-regen" onclick="quickPrompt('${regenPrompt}')">
                            ${refreshSVG}
                            <span>${regenLabel}</span>
                        </button>
                    </div>
                `);
            }

            historyDiv.scrollTop = historyDiv.scrollHeight;
        } else {
            const errorPrefix = window.entraLanguage === 'de' ? 'Entschuldigung, es ist ein Fehler aufgetreten: ' : 'Sorry, I encountered an error: ';
            historyDiv.insertAdjacentHTML('beforeend', `
                <div class="ai-message ai-message-bot">
                    <div class="ai-msg-avatar ai-bot-avatar">${starSVG}</div>
                    <div class="ai-message-bubble">
                        <p>${errorPrefix}${data.error || 'Unknown error'}</p>
                    </div>
                </div>
            `);
        }
    } catch (err) {
        document.getElementById(loadingId)?.remove();
        console.error(err);
        const connError = window.entraLanguage === 'de' ? 'Verbindungsfehler. Bitte versuchen Sie es erneut.' : 'Connection error. Please try again.';
        historyDiv.insertAdjacentHTML('beforeend', `
            <div class="ai-message ai-message-bot">
                <div class="ai-msg-avatar ai-bot-avatar">${starSVG}</div>
                <div class="ai-message-bubble">
                    <p>${connError}</p>
                </div>
            </div>
        `);
    } finally {
        sendBtn.disabled = false;
        historyDiv.scrollTop = historyDiv.scrollHeight;
    }
}

window.insertAIContent = async function (id, btn) {
    if (btn && btn.disabled) return;
    const sourceEl = document.getElementById(id);
    if (!sourceEl) return;

    let html = sourceEl.innerHTML;

    const titleInput = document.getElementById('title');
    if (titleInput) {
        const temp = document.createElement('div');
        temp.innerHTML = html;

        const firstChild = Array.from(temp.childNodes).find(node =>
            (node.nodeType === Node.ELEMENT_NODE && node.textContent.trim() !== '') ||
            (node.nodeType === Node.TEXT_NODE && node.textContent.trim() !== '')
        );

        if (firstChild && firstChild.nodeType === Node.ELEMENT_NODE && /^H[1-6]$/i.test(firstChild.nodeName)) {
            const extractedTitle = firstChild.textContent.trim();
            if (extractedTitle) {
                titleInput.value = extractedTitle;
                titleInput.focus();

                firstChild.remove();
                html = temp.innerHTML;
            }
        }
    }

    const sanitized = typeof DOMPurify !== 'undefined'
        ? DOMPurify.sanitize(html, {
            ALLOWED_TAGS: ['p', 'h2', 'h3', 'ul', 'ol', 'li', 'strong', 'em', 'b', 'i', 'blockquote', 'span', 'div'],
            ALLOWED_ATTR: ['class', 'data-type']
        })
        : html;

    const editor = document.getElementById('contentEditor');
    if (editor && typeof processNodes === 'function') {
        const temp = document.createElement('div');
        temp.innerHTML = sanitized;
        processNodes(Array.from(temp.childNodes));

        if (btn) {
            btn.disabled = true;
            const span = btn.querySelector('span');
            if (span) span.textContent = window.entraLanguage === 'de' ? 'Eingefügt' : 'Inserted';
            const oldSvg = btn.querySelector('svg');
            if (oldSvg) oldSvg.outerHTML = checkmarkSVG;

            setTimeout(() => {
                btn.disabled = false;
                if (span) span.textContent = window.entraLanguage === 'de' ? 'Einfügen' : 'Insert';
                const currentSvg = btn.querySelector('svg');
                if (currentSvg && typeof insertSVG !== 'undefined') currentSvg.outerHTML = insertSVG;
            }, 2000);
        }
        const successMsg = window.entraLanguage === 'de' ? 'Inhalt erfolgreich eingefügt' : 'Content inserted successfully';
        showToast(successMsg, 'success');
    }
}

window.copyToClipboard = function (id, btn) {
    if (btn && btn.disabled) return;
    const html = document.getElementById(id).innerHTML;
    const temp = document.createElement('div');
    temp.innerHTML = html;
    const text = temp.innerText || temp.textContent;
    navigator.clipboard.writeText(text).then(() => {
        if (btn) {
            const span = btn.querySelector('span');
            const originalText = span ? span.textContent : '';
            if (span) span.textContent = window.entraLanguage === 'de' ? 'Kopiert' : 'Copied';
            btn.classList.add('copied');
            setTimeout(() => {
                if (span) span.textContent = originalText;
                btn.classList.remove('copied');
            }, 2000);
        }
        showToast('Copied to clipboard!', 'success');
    }).catch(err => {
        console.error('Copy failed:', err);
        showToast('Failed to copy', 'error');
    });
}

function showToast(msg, type = 'success') {
    if (window.showNotification) {
        window.showNotification({ title: 'ENTRIKS AI', message: msg, type_class: type });
    } else {
        alert(msg);
    }
}