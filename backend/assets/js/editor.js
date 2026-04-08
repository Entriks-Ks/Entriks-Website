document.addEventListener('DOMContentLoaded', () => {
    const editor = document.getElementById('contentEditor');
    const dropZone = document.getElementById('dropZone');

    if (editor) {
        initEditor(editor, dropZone);
    }
});

function initEditor(editor, dropZone) {
    editor.addEventListener('dragover', (e) => {
        e.preventDefault();
    });

    editor.addEventListener('drop', (e) => {
        e.preventDefault();
        if (window.draggedType) {
            if (dropZone && dropZone.parentElement) {
                dropZone.remove();
            }

            const type = window.draggedType;
            if (type === 'paragraph') addParagraphBlock(editor, '');
            else if (type === 'heading') addHeadingBlock(editor, 'heading1', '');
            else if (type.startsWith('heading')) addHeadingBlock(editor, type, '');
            else if (type === 'quote') addQuoteBlock(editor, '');
            else if (type === 'list') addListBlock(editor, 'list', []);
            else if (type === 'orderedlist') addListBlock(editor, 'orderedlist', []);
            else if (type === 'divider') addDividerBlock(editor);
            else if (type === 'image') addImageBlock(editor, '', '', '', '');

            updateContent();
            window.draggedType = null;
        }
    });
}

window.draggedType = null;

function initPalette() {
    const blockPalette = document.querySelectorAll('.block-item');
    blockPalette.forEach(item => {
        item.addEventListener('dragstart', (e) => {
            window.draggedType = e.target.getAttribute('data-type');
        });

        item.addEventListener('click', (e) => {
            const type = e.currentTarget.getAttribute('data-type');
            const editor = document.getElementById('contentEditor');
            const dropZone = document.getElementById('dropZone');

            if (dropZone) dropZone.remove();

            if (type === 'paragraph') addParagraphBlock(editor, '');
            else if (type === 'heading') addHeadingBlock(editor, 'heading1', '');
            else if (type.startsWith('heading')) addHeadingBlock(editor, type, '');
            else if (type === 'quote') addQuoteBlock(editor, '');
            else if (type === 'list') addListBlock(editor, 'list', []);
            else if (type === 'orderedlist') addListBlock(editor, 'orderedlist', []);
            else if (type === 'divider') addDividerBlock(editor);
            else if (type === 'image') addImageBlock(editor, '', '', '', '');

            updateContent();
        });
    });
}

function addParagraphBlock(editor, text, alignment = '') {
    const block = document.createElement('div');
    block.className = 'editor-block';
    block.setAttribute('data-type', 'paragraph');

    const placeholder = typeof EDITOR_LANG !== 'undefined' ? EDITOR_LANG.placeholder_paragraph : 'Start writing...';

    block.innerHTML = `
        <div class="block-controls">
            <button type="button" class="block-btn drag-handle" title="Drag to reorder" draggable="false" style="cursor:grab;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6"><path fill-rule="evenodd" d="M3 6a3 3 0 0 1 3-3h2.25a3 3 0 0 1 3 3v2.25a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V6Zm9.75 0a3 3 0 0 1 3-3H18a3 3 0 0 1 3 3v2.25a3 3 0 0 1-3 3h-2.25a3 3 0 0 1-3-3V6ZM3 15.75a3 3 0 0 1 3-3h2.25a3 3 0 0 1 3 3V18a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3v-2.25Zm9.75 0a3 3 0 0 1 3-3H18a3 3 0 0 1 3 3V18a3 3 0 0 1-3 3h-2.25a3 3 0 0 1-3-3v-2.25Z" clip-rule="evenodd" /></svg>
            </button>
            <button type="button" class="block-btn delete" onclick="deleteBlock(this)">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        <div class="formatting-toolbar">
            <button type="button" class="format-btn" onmousedown="event.preventDefault();" onclick="formatText('bold', this)"><b>B</b></button>
            <button type="button" class="format-btn" onmousedown="event.preventDefault();" onclick="formatText('italic', this)"><i>I</i></button>
            <button type="button" class="format-btn" onmousedown="event.preventDefault();" onclick="formatText('underline', this)"><u>U</u></button>
            <button type="button" class="format-btn" onmousedown="event.preventDefault();" onclick="formatText('strikeThrough', this)"><s>S</s></button>
            <button type="button" class="format-btn" onmousedown="event.preventDefault();" onclick="formatText('createLink', this)"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:14px;height:14px;"><path d="M12.232 4.232a2.5 2.5 0 013.536 3.536l-1.225 1.224a.75.75 0 001.061 1.06l1.224-1.224a4 4 0 00-5.656-5.656l-3 3a4 4 0 00.225 5.865.75.75 0 00.977-1.138 2.5 2.5 0 01-.142-3.667l3-3z" /><path d="M11.603 7.963a.75.75 0 00-.977 1.138 2.5 2.5 0 01.142 3.667l-3 3a2.5 2.5 0 01-3.536-3.536l1.225-1.224a.75.75 0 00-1.061-1.06l-1.224 1.224a4 4 0 105.656 5.656l3-3a4 4 0 00-.225-5.865z" /></svg></button>
            <span class="format-divider"></span>
            <button type="button" class="format-btn" onmousedown="event.preventDefault();" onclick="formatText('justifyLeft', this)" title="Align Left"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M3 6.75A.75.75 0 0 1 3.75 6h16.5a.75.75 0 0 1 0 1.5H3.75A.75.75 0 0 1 3 6.75ZM3 12a.75.75 0 0 1 .75-.75h16.5a.75.75 0 0 1 0 1.5H3.75A.75.75 0 0 1 3 12Zm0 5.25a.75.75 0 0 1 .75-.75H12a.75.75 0 0 1 0 1.5H3.75a.75.75 0 0 1-.75-.75Z" /></svg></button>
            <button type="button" class="format-btn" onmousedown="event.preventDefault();" onclick="formatText('justifyCenter', this)" title="Align Center"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M3 6.75A.75.75 0 0 1 3.75 6h16.5a.75.75 0 0 1 0 1.5H3.75A.75.75 0 0 1 3 6.75ZM6 12a.75.75 0 0 1 .75-.75h10.5a.75.75 0 0 1 0 1.5H6.75A.75.75 0 0 1 6 12Zm3 5.25a.75.75 0 0 1 .75-.75h6.5a.75.75 0 0 1 0 1.5H9.75a.75.75 0 0 1-.75-.75Z" /></svg></button>
            <button type="button" class="format-btn" onmousedown="event.preventDefault();" onclick="formatText('justifyRight', this)" title="Align Right"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M3 6.75A.75.75 0 0 1 3.75 6h16.5a.75.75 0 0 1 0 1.5H3.75A.75.75 0 0 1 3 6.75ZM3 12a.75.75 0 0 1 .75-.75h16.5a.75.75 0 0 1 0 1.5H3.75A.75.75 0 0 1 3 12Zm0 5.25a.75.75 0 0 1 .75-.75H12a.75.75 0 0 1 0 1.5H3.75a.75.75 0 0 1-.75-.75Z" /></svg></button>
             <button type="button" class="format-btn" onmousedown="event.preventDefault();" onclick="formatText('justifyFull', this)" title="Justify"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:14px;height:14px;"><path fill-rule="evenodd" d="M2.75 5.75a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H3.5a.75.75 0 01-.75-.75zm0 8.5a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H3.5a.75.75 0 01-.75-.75zM2.75 10a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H3.5A.75.75 0 012.75 10z" clip-rule="evenodd" /></svg></button>
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
        <div class="rich-text-content" contenteditable="true" data-placeholder="${placeholder}" oninput="updateContent()" onkeydown="handleParagraphKeydown(event, this)"></div>
    `;

    const richTextDiv = block.querySelector('.rich-text-content');
    richTextDiv.innerHTML = text;
    if (alignment) richTextDiv.style.textAlign = alignment;

    editor.appendChild(block);
    makeBlockDraggable(block);
}

function addHeadingBlock(editor, type, text, alignment = '') {
    let headingLevel = 1;
    if (type && /^heading[1-6]$/.test(type)) {
        headingLevel = parseInt(type.replace('heading', ''), 10);
    }
    const block = document.createElement('div');
    block.className = 'editor-block';
    block.setAttribute('data-type', 'heading');
    block.setAttribute('data-level', headingLevel);

    const placeholder = typeof EDITOR_LANG !== 'undefined' ? EDITOR_LANG.placeholder_heading : 'Heading';

    block.innerHTML = `
        <div class="block-controls">
            <button type="button" class="block-btn drag-handle" title="Drag to reorder" draggable="false" style="cursor:grab;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6"><path fill-rule="evenodd" d="M3 6a3 3 0 0 1 3-3h2.25a3 3 0 0 1 3 3v2.25a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V6Zm9.75 0a3 3 0 0 1 3-3H18a3 3 0 0 1 3 3v2.25a3 3 0 0 1-3 3h-2.25a3 3 0 0 1-3-3V6ZM3 15.75a3 3 0 0 1 3-3h2.25a3 3 0 0 1 3 3V18a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3v-2.25Zm9.75 0a3 3 0 0 1 3-3H18a3 3 0 0 1 3 3V18a3 3 0 0 1-3 3h-2.25a3 3 0 0 1-3-3v-2.25Z" clip-rule="evenodd" /></svg>
            </button>
            <button type="button" class="block-btn delete" onclick="deleteBlock(this)">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
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
            <span class="format-divider"></span>
            <button type="button" class="format-btn" onmousedown="event.preventDefault();" onclick="formatText('justifyLeft', this)" title="Align Left"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M3 6.75A.75.75 0 0 1 3.75 6h16.5a.75.75 0 0 1 0 1.5H3.75A.75.75 0 0 1 3 6.75ZM3 12a.75.75 0 0 1 .75-.75h16.5a.75.75 0 0 1 0 1.5H3.75A.75.75 0 0 1 3 12Zm0 5.25a.75.75 0 0 1 .75-.75H12a.75.75 0 0 1 0 1.5H3.75a.75.75 0 0 1-.75-.75Z" /></svg></button>
            <button type="button" class="format-btn" onmousedown="event.preventDefault();" onclick="formatText('justifyCenter', this)" title="Align Center"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M3 6.75A.75.75 0 0 1 3.75 6h16.5a.75.75 0 0 1 0 1.5H3.75A.75.75 0 0 1 3 6.75ZM6 12a.75.75 0 0 1 .75-.75h10.5a.75.75 0 0 1 0 1.5H6.75A.75.75 0 0 1 6 12Zm3 5.25a.75.75 0 0 1 .75-.75h6.5a.75.75 0 0 1 0 1.5H9.75a.75.75 0 0 1-.75-.75Z" /></svg></button>
            <button type="button" class="format-btn" onmousedown="event.preventDefault();" onclick="formatText('justifyRight', this)" title="Align Right"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M3 6.75A.75.75 0 0 1 3.75 6h16.5a.75.75 0 0 1 0 1.5H3.75A.75.75 0 0 1 3 6.75ZM3 12a.75.75 0 0 1 .75-.75h16.5a.75.75 0 0 1 0 1.5H3.75A.75.75 0 0 1 3 12Zm12 5.25a.75.75 0 0 1 .75-.75h6.5a.75.75 0 0 1 0 1.5H15.75a.75.75 0 0 1-.75-.75Z" /></svg></button>
        </div>
        <div class="heading-input-wrapper">
            <h${headingLevel}><input type="text" placeholder="${placeholder} ${headingLevel}" oninput="updateContent()"></h${headingLevel}>
        </div>
    `;

    block.querySelector('.heading-level-select').value = headingLevel;
    const input = block.querySelector('input');
    input.value = text;
    if (alignment) {
        input.style.textAlign = alignment;
    }

    editor.appendChild(block);
    makeBlockDraggable(block);
}


function addListBlock(editor, type, items, alignment = '', listStyle = '', color = '') {
    const listTag = type === 'orderedlist' ? 'ol' : 'ul';
    const block = document.createElement('div');
    block.className = 'editor-block';
    block.setAttribute('data-type', type);
    if (listStyle) block.dataset.listStyle = listStyle;
    if (color) block.dataset.textColor = color;

    const placeholder = typeof EDITOR_LANG !== 'undefined' ? EDITOR_LANG.placeholder_list_item : 'List item';

    let listHTML = '';
    const renderItems = items.length > 0 ? items : [
        { content: '', padding: '' },
        { content: '', padding: '' },
        { content: '', padding: '' }
    ];

    renderItems.forEach((item, i) => {
        const content = typeof item === 'string' ? item : (item.content || '');
        const padding = typeof item === 'string' ? '' : (item.padding || '');
        const paddingStyle = padding ? ` style="padding-left: ${padding}"` : '';

        listHTML += `
            <li class="editor-list-item"${paddingStyle}>
                <div class="li-content-wrapper">
                    <div class="rich-text-content" contenteditable="true" data-placeholder="${placeholder} ${i + 1}" oninput="updateContent()" onkeydown="handleListKeydown(event, this)">${content}</div>
                    <button type="button" class="remove-list-item" title="Remove item">×</button>
                </div>
            </li>`;
    });

    const listStyleOptions = type === 'orderedlist'
        ? `<option value="decimal" ${listStyle === 'decimal' ? 'selected' : ''}>1. Decimal</option>
           <option value="lower-alpha" ${listStyle === 'lower-alpha' ? 'selected' : ''}>a. Lower Alpha</option>
           <option value="upper-alpha" ${listStyle === 'upper-alpha' ? 'selected' : ''}>A. Upper Alpha</option>
           <option value="lower-roman" ${listStyle === 'lower-roman' ? 'selected' : ''}>i. Lower Roman</option>
           <option value="upper-roman" ${listStyle === 'upper-roman' ? 'selected' : ''}>I. Upper Roman</option>`
        : `<option value="disc" ${listStyle === 'disc' ? 'selected' : ''}>● Disc</option>
           <option value="circle" ${listStyle === 'circle' ? 'selected' : ''}>○ Circle</option>
           <option value="square" ${listStyle === 'square' ? 'selected' : ''}>■ Square</option>
           <option value="'◆'" ${listStyle === "'◆'" ? 'selected' : ''}>◆ Diamond</option>
           <option value="'✓'" ${listStyle === "'✓'" ? 'selected' : ''}>✓ Checkmark</option>
           <option value="'➤'" ${listStyle === "'➤'" ? 'selected' : ''}>➤ Arrow</option>
           <option value="'★'" ${listStyle === "'★'" ? 'selected' : ''}>★ Star</option>
           <option value="none" ${listStyle === 'none' ? 'selected' : ''}>— None</option>`;

    block.innerHTML = `
        <div class="block-controls">
            <button type="button" class="block-btn drag-handle" title="Drag to reorder" draggable="false" style="cursor:grab;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6"><path fill-rule="evenodd" d="M3 6a3 3 0 0 1 3-3h2.25a3 3 0 0 1 3 3v2.25a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V6Zm9.75 0a3 3 0 0 1 3-3H18a3 3 0 0 1 3 3v2.25a3 3 0 0 1-3 3h-2.25a3 3 0 0 1-3-3V6ZM3 15.75a3 3 0 0 1 3-3h2.25a3 3 0 0 1 3 3V18a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3v-2.25Zm9.75 0a3 3 0 0 1 3-3H18a3 3 0 0 1 3 3V18a3 3 0 0 1-3 3h-2.25a3 3 0 0 1-3-3v-2.25Z" clip-rule="evenodd" /></svg>
            </button>
            <button type="button" class="block-btn delete" onclick="deleteBlock(this)">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        <div class="formatting-toolbar">
             <button type="button" class="format-btn" onmousedown="event.preventDefault();" onclick="formatText('bold', this)"><b>B</b></button>
            <button type="button" class="format-btn" onmousedown="event.preventDefault();" onclick="formatText('italic', this)"><i>I</i></button>
            <button type="button" class="format-btn" onmousedown="event.preventDefault();" onclick="formatText('underline', this)"><u>U</u></button>
            <button type="button" class="format-btn" onmousedown="event.preventDefault();" onclick="formatText('strikeThrough', this)"><s>S</s></button>
            <span class="format-divider"></span>
            <select class="list-style-select" onchange="changeListStyle(this)" style="padding:6px 8px; border-radius:6px; background:#1a1a1a; border:1px solid #333; color:#fff; cursor:pointer;">
                ${listStyleOptions}
            </select>
            <div class="color-palette">
                <!-- Color swatches -->
                <div class="color-swatch" style="background-color: #20c1f5" onclick="applyTextColor('#20c1f5', this)" title="#20c1f5"></div>
                <div class="color-swatch" style="background-color: #49b9f2" onclick="applyTextColor('#49b9f2', this)" title="#49b9f2"></div>
                <div class="color-swatch" style="background-color: #7675ec" onclick="applyTextColor('#7675ec', this)" title="#7675ec"></div>
                <div class="color-swatch" style="background-color: #a04ee1" onclick="applyTextColor('#a04ee1', this)" title="#a04ee1"></div>
                <div class="color-swatch" style="background-color: #d225d7" onclick="applyTextColor('#d225d7', this)" title="#d225d7"></div>
                <div class="color-swatch" style="background-color: #f009d5" onclick="applyTextColor('#f009d5', this)" title="#f009d5"></div>
                <div class="color-swatch" style="background-color: #ffffff" onclick="applyTextColor('#ffffff', this)" title="#ffffff"></div>
            </div>
        </div>
        <${listTag} class="editor-list"${color ? ` style="color:${color}"` : ''}>
            ${listHTML}
        </${listTag}>
        <div class="list-footer"><button type="button" class="add-list-item">+ ${typeof EDITOR_LANG !== 'undefined' ? EDITOR_LANG.action_add_item : 'Add Item'}</button></div>
    `;

    editor.appendChild(block);
    makeBlockDraggable(block);

    block.querySelectorAll('.remove-list-item').forEach(btn => {
        btn.addEventListener('click', function () {
            const li = this.closest('li');
            if (li.parentElement.children.length === 1) {
                block.remove();
            } else {
                li.remove();
            }
            updateContent();
        });
    });

    block.querySelector('.add-list-item').addEventListener('click', () => {
        const list = block.querySelector('.editor-list');
        const newItem = document.createElement('li');
        newItem.className = 'editor-list-item';
        newItem.innerHTML = `
            <div class="li-content-wrapper">
                <div class="rich-text-content" contenteditable="true" data-placeholder="${placeholder}" oninput="updateContent()" onkeydown="handleListKeydown(event, this)"></div>
                <button type="button" class="remove-list-item" title="Remove item">×</button>
            </div>
        `;
        list.appendChild(newItem);
        newItem.querySelector('.remove-list-item').addEventListener('click', function () {
            newItem.remove();
            if (list.children.length === 0) block.remove();
            updateContent();
        });
        newItem.querySelector('.rich-text-content').focus();
        updateContent();
    });
}

function addQuoteBlock(editor, text, alignment = '') {
    const block = document.createElement('div');
    block.className = 'editor-block';
    block.setAttribute('data-type', 'quote');

    const placeholder = typeof EDITOR_LANG !== 'undefined' ? EDITOR_LANG.placeholder_quote : 'Quote...';

    block.innerHTML = `
        <div class="block-controls">
            <button type="button" class="block-btn drag-handle" title="Drag to reorder" draggable="false" style="cursor:grab;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6"><path fill-rule="evenodd" d="M3 6a3 3 0 0 1 3-3h2.25a3 3 0 0 1 3 3v2.25a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V6Zm9.75 0a3 3 0 0 1 3-3H18a3 3 0 0 1 3 3v2.25a3 3 0 0 1-3 3h-2.25a3 3 0 0 1-3-3V6ZM3 15.75a3 3 0 0 1 3-3h2.25a3 3 0 0 1 3 3V18a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3v-2.25Zm9.75 0a3 3 0 0 1 3-3H18a3 3 0 0 1 3 3V18a3 3 0 0 1-3 3h-2.25a3 3 0 0 1-3-3v-2.25Z" clip-rule="evenodd" /></svg>
            </button>
            <button type="button" class="block-btn delete" onclick="deleteBlock(this)">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        <div class="formatting-toolbar">
            <button type="button" class="format-btn" onmousedown="event.preventDefault();" onclick="formatText('bold', this)"><b>B</b></button>
            <button type="button" class="format-btn" onmousedown="event.preventDefault();" onclick="formatText('italic', this)"><i>I</i></button>
             <button type="button" class="format-btn" onmousedown="event.preventDefault();" onclick="formatText('createLink', this)"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:14px;height:14px;"><path d="M12.232 4.232a2.5 2.5 0 013.536 3.536l-1.225 1.224a.75.75 0 001.061 1.06l1.224-1.224a4 4 0 00-5.656-5.656l-3 3a4 4 0 00.225 5.865.75.75 0 00.977-1.138 2.5 2.5 0 01-.142-3.667l3-3z" /><path d="M11.603 7.963a.75.75 0 00-.977 1.138 2.5 2.5 0 01.142 3.667l-3 3a2.5 2.5 0 01-3.536-3.536l1.225-1.224a.75.75 0 00-1.061-1.06l-1.224 1.224a4 4 0 105.656 5.656l3-3a4 4 0 00-.225-5.865z" /></svg></button>
            <span class="format-divider"></span>
            <button type="button" class="format-btn" onmousedown="event.preventDefault();" onclick="formatText('justifyLeft', this)" title="Align Left"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M3 6.75A.75.75 0 0 1 3.75 6h16.5a.75.75 0 0 1 0 1.5H3.75A.75.75 0 0 1 3 6.75ZM3 12a.75.75 0 0 1 .75-.75h16.5a.75.75 0 0 1 0 1.5H3.75A.75.75 0 0 1 3 12Zm0 5.25a.75.75 0 0 1 .75-.75H12a.75.75 0 0 1 0 1.5H3.75a.75.75 0 0 1-.75-.75Z" /></svg></button>
            <button type="button" class="format-btn" onmousedown="event.preventDefault();" onclick="formatText('justifyCenter', this)" title="Align Center"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M3 6.75A.75.75 0 0 1 3.75 6h16.5a.75.75 0 0 1 0 1.5H3.75A.75.75 0 0 1 3 6.75ZM6 12a.75.75 0 0 1 .75-.75h10.5a.75.75 0 0 1 0 1.5H6.75A.75.75 0 0 1 6 12Zm3 5.25a.75.75 0 0 1 .75-.75h6.5a.75.75 0 0 1 0 1.5H9.75a.75.75 0 0 1-.75-.75Z" /></svg></button>
            <button type="button" class="format-btn" onmousedown="event.preventDefault();" onclick="formatText('justifyRight', this)" title="Align Right"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M3 6.75A.75.75 0 0 1 3.75 6h16.5a.75.75 0 0 1 0 1.5H3.75A.75.75 0 0 1 3 6.75ZM3 12a.75.75 0 0 1 .75-.75h16.5a.75.75 0 0 1 0 1.5H3.75A.75.75 0 0 1 3 12Zm0 5.25a.75.75 0 0 1 .75-.75H12a.75.75 0 0 1 0 1.5H3.75a.75.75 0 0 1-.75-.75Z" /></svg></button>
        </div>
        <blockquote>
            <div class="rich-text-content" contenteditable="true" data-placeholder="${placeholder}" oninput="updateContent()" style="min-height: 1em; outline: none; color: #fff;"></div>
        </blockquote>
    `;

    block.querySelector('.rich-text-content').innerHTML = text;
    if (alignment) block.querySelector('.rich-text-content').style.textAlign = alignment;

    editor.appendChild(block);
    makeBlockDraggable(block);
}

function addDividerBlock(editor) {
    const block = document.createElement('div');
    block.className = 'editor-block';
    block.setAttribute('data-type', 'divider');
    block.innerHTML = `
        <div class="block-controls">
            <button type="button" class="block-btn drag-handle" title="Drag to reorder" draggable="false" style="cursor:grab;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6"><path fill-rule="evenodd" d="M3 6a3 3 0 0 1 3-3h2.25a3 3 0 0 1 3 3v2.25a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V6Zm9.75 0a3 3 0 0 1 3-3H18a3 3 0 0 1 3 3v2.25a3 3 0 0 1-3 3h-2.25a3 3 0 0 1-3-3V6ZM3 15.75a3 3 0 0 1 3-3h2.25a3 3 0 0 1 3 3V18a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3v-2.25Zm9.75 0a3 3 0 0 1 3-3H18a3 3 0 0 1 3 3V18a3 3 0 0 1-3 3h-2.25a3 3 0 0 1-3-3v-2.25Z" clip-rule="evenodd" /></svg>
            </button>
             <button type="button" class="block-btn delete" onclick="deleteBlock(this)">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        <hr style="border: none; border-top: 2px solid rgba(118, 117, 236, 0.3); margin: 16px 0;">
    `;
    editor.appendChild(block);
    makeBlockDraggable(block);
}

function addImageBlock(editor, src, alt, width, height, alignment = '', caption = '') {
    const uniqueId = 'img_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    const block = document.createElement('div');
    block.className = 'editor-block';
    block.setAttribute('data-type', 'image');
    block.setAttribute('data-id', uniqueId);

    block.innerHTML = `
        <div class="block-controls">
            <button type="button" class="block-btn drag-handle" title="Drag to reorder" draggable="false" style="cursor:grab;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6"><path fill-rule="evenodd" d="M3 6a3 3 0 0 1 3-3h2.25a3 3 0 0 1 3 3v2.25a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V6Zm9.75 0a3 3 0 0 1 3-3H18a3 3 0 0 1 3 3v2.25a3 3 0 0 1-3 3h-2.25a3 3 0 0 1-3-3V6ZM3 15.75a3 3 0 0 1 3-3h2.25a3 3 0 0 1 3 3V18a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3v-2.25Zm9.75 0a3 3 0 0 1 3-3H18a3 3 0 0 1 3 3V18a3 3 0 0 1-3 3h-2.25a3 3 0 0 1-3-3v-2.25Z" clip-rule="evenodd" /></svg>
            </button>
            <button type="button" class="block-btn delete" onclick="deleteBlock(this)">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        <div class="image-block modern-image-block">
            <div class="file-upload-card">
                 <div class="file-upload-header">
                    <h3 style="margin:0 0 4px 0;font-size:1.1rem;font-weight:600;">Image Upload</h3>
                    <div style="color:#888;font-size:13px;margin-bottom:12px;">Max size 5MB</div>
                </div>
                 <div class="img-content upload-content">
                    <div class="dropzone" id="dropzone_${uniqueId}" onclick="document.getElementById('fileInput_${uniqueId}').click()">
                        <input type="file" accept="image/*" onchange="handleImageUpload(this, '${uniqueId}')" style="display:none;" id="fileInput_${uniqueId}">
                        <div class="dropzone-inner">
                            <div class="dropzone-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#7675ec"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" /></svg></div>
                            <div class="dropzone-text">Drag & Drop files</div>
                            <div class="dropzone-formats">JPG, PNG, GIF, WEBP</div>
                            <button type="button" class="select-file-btn">Select File</button>
                        </div>
                    </div>
                     <div style="text-align:left; color:#888; margin-top: 10px; font-size:15px;">Or upload via URL</div>
                    <div class="url-upload-row">
                        <input type="url" placeholder="https://example.com/image.jpg" class="url-upload-input">
                        <button type="button" class="url-upload-btn">Upload</button>
                    </div>
                 </div>
            </div>
        </div>
    `;

    editor.appendChild(block);
    makeBlockDraggable(block);
}

function deleteBlock(btn) {
    const block = btn.closest('.editor-block');
    block.remove();
    updateContent();

    const editor = document.getElementById('contentEditor');
    if (editor && editor.children.length === 0) {
        const dz = document.createElement('div');
        dz.className = 'drop-zone';
        dz.id = 'dropZone';
        dz.innerHTML = `<p>${typeof EDITOR_LANG !== 'undefined' ? EDITOR_LANG.editor_drag_hint : 'Drag blocks here'}</p>`;
        editor.appendChild(dz);
        initEditor(editor, dz);
    }
}

function updateContent() {
    const editor = document.getElementById('contentEditor');
    if (!editor) return;

    const blocks = editor.querySelectorAll('.editor-block');
    let html = '';

    blocks.forEach(block => {
        const type = block.getAttribute('data-type');

        if (type === 'paragraph') {
            const richText = block.querySelector('.rich-text-content');
            if (richText) {
                const content = richText.innerHTML;
                if (content.trim() && content.trim() !== '<br>') {
                    const align = richText.style.textAlign ? ` style="text-align:${richText.style.textAlign}"` : '';
                    html += `<p${align}>${content}</p>\n\n`;
                }
            }
        } else if (type === 'heading') {
            const level = block.getAttribute('data-level') || '1';
            const input = block.querySelector('input');
            if (input && input.value.trim()) {
                const align = input.style.textAlign ? ` style="text-align:${input.style.textAlign}"` : '';
                html += `<h${level}${align}>${input.value}</h${level}>\n\n`;
            }
        } else if (type === 'quote') {
            const richText = block.querySelector('.rich-text-content');
            if (richText) {
                const content = richText.innerHTML;
                if (content.trim() && content.trim() !== '<br>') {
                    const align = richText.style.textAlign ? ` style="text-align:${richText.style.textAlign}"` : '';
                    html += `<blockquote${align}><p>${content}</p></blockquote>\n\n`;
                }
            }
        } else if (type === 'list' || type === 'orderedlist') {
            const listTag = type === 'orderedlist' ? 'ol' : 'ul';
            const style = block.dataset.listStyle || (type === 'orderedlist' ? 'decimal' : 'disc');
            const color = block.dataset.textColor || '';

            let itemsHtml = '';
            block.querySelectorAll('.editor-list-item').forEach(li => {
                const richText = li.querySelector('.rich-text-content');
                if (richText) {
                    itemsHtml += `<li><span>${richText.innerHTML}</span></li>\n`;
                }
            });

            if (itemsHtml) {
                html += `<${listTag} style="list-style-type:${style};${color ? 'color:' + color : ''}">\n${itemsHtml}</${listTag}>\n\n`;
            }
        } else if (type === 'divider') {
            html += `<hr style="border: none; border-top: 2px solid rgba(118, 117, 236, 0.3); margin: 32px 0;">\n\n`;
        }
    });

    const input = document.getElementById('contentInput');
    if (input) input.value = html;
}

function processNodes(nodes) {
    const editor = document.getElementById('contentEditor');
    const dz = document.getElementById('dropZone');
    if (dz) dz.remove();

    let currentParagraphContent = '';

    function flushParagraph() {
        if (currentParagraphContent.trim()) {
            addParagraphBlock(editor, currentParagraphContent.trim());
            currentParagraphContent = '';
        }
    }

    nodes.forEach(node => {
        if (node.nodeType === Node.TEXT_NODE) {
            const text = node.textContent.trim();
            if (text) {
                currentParagraphContent += (currentParagraphContent ? '<br><br>' : '') + text;
            }
        } else if (node.nodeType === Node.ELEMENT_NODE) {
            const tag = node.tagName.toLowerCase();
            if (tag === 'p' || tag === 'div' || tag === 'span') {
                const content = node.innerHTML.trim();
                if (content) {
                    currentParagraphContent += (currentParagraphContent ? '<br><br>' : '') + content;
                }
            } else {
                flushParagraph();

                if (/^h[1-6]$/.test(tag)) {
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
                    if (content) {
                        currentParagraphContent += (currentParagraphContent ? '<br><br>' : '') + content;
                    }
                }
            }
        }
    });

    flushParagraph();
    updateContent();
}

function formatText(command, btn) {
    document.execCommand(command, false, null);
    if (btn) btn.classList.toggle('active', document.queryCommandState(command));
    updateContent();
}

function changeHeadingLevel(select) {
    const block = select.closest('.editor-block');
    const level = select.value;
    block.setAttribute('data-level', level);

    const wrapper = block.querySelector('.heading-input-wrapper');
    const input = block.querySelector('input');
    if (wrapper && input) {
        const newH = document.createElement(`h${level}`);
        newH.appendChild(input);
        wrapper.innerHTML = '';
        wrapper.appendChild(newH);
    }
    updateContent();
}

function changeListStyle(select) {
    const block = select.closest('.editor-block');
    block.dataset.listStyle = select.value;
    const list = block.querySelector('.editor-list');
    if (list) list.style.listStyleType = select.value;
    updateContent();
}

function handleListKeydown(e, el) {
    if (e.key === 'Enter') {
        e.preventDefault();
        const block = el.closest('.editor-block');
        const list = block.querySelector('.editor-list');
        const parentLi = el.closest('li');

        const newItem = document.createElement('li');
        newItem.className = 'editor-list-item';
        newItem.innerHTML = `
            <div class="li-content-wrapper">
                <div class="rich-text-content" contenteditable="true" oninput="updateContent()" onkeydown="handleListKeydown(event, this)"></div>
                <button type="button" class="remove-list-item" title="Remove item">×</button>
            </div>
        `;

        if (parentLi.nextSibling) {
            list.insertBefore(newItem, parentLi.nextSibling);
        } else {
            list.appendChild(newItem);
        }

        newItem.querySelector('.rich-text-content').focus();

        newItem.querySelector('.remove-list-item').addEventListener('click', function () {
            newItem.remove();
            if (list.children.length === 0) block.remove();
            updateContent();
        });
    }
}

function handleParagraphKeydown(e, el) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        document.execCommand('insertLineBreak');
    }
}

function makeBlockDraggable(block) {
    const handle = block.querySelector('.drag-handle');
    if (!handle) return;

    block.setAttribute('draggable', 'true');

    block.addEventListener('dragstart', (e) => {
        block.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
    });

    block.addEventListener('dragend', () => {
        block.classList.remove('dragging');
        updateContent();
    });

    block.addEventListener('dragover', (e) => {
        e.preventDefault();
        const editor = document.getElementById('contentEditor');
        const dragging = document.querySelector('.dragging');
        if (dragging && dragging !== block && editor.contains(dragging)) {
            const bounding = block.getBoundingClientRect();
            const offset = bounding.y + (bounding.height / 2);
            if (e.clientY - offset > 0) {
                block.after(dragging);
            } else {
                block.before(dragging);
            }
        }
    });
}

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

    row.innerHTML = `
        <img class="uploaded-file-thumb" src="" alt="Preview" style="width:40px; height:40px; border-radius:4px; margin-right:10px; display:none;">
        <span class="uploaded-file-name" style="flex:1;">${fileName}</span>
        <button class="uploaded-file-delete" onclick="this.parentElement.remove(); const b=this.closest('.editor-block'); if(b){ delete b.dataset.attachedFile; delete b.dataset.attachedFileName; delete b.dataset.attachedUrl; if(typeof updateContent === 'function') updateContent(); }" style="background:none; border:none; color:#ff6b6b; cursor:pointer; padding:4px;">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 16px; height: 16px;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    `;

    try { if (fileList) fileList.innerHTML = ''; } catch (e) { }
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
                        const errorMsg = typeof EDITOR_LANG !== 'undefined' ? EDITOR_LANG.error_upload_failed : 'Upload failed';
                        alert(errorMsg + ": " + (data.error || 'Unknown error'));
                        row.remove();
                    }
                })
                .catch(err => {
                    console.error('Upload error', err);
                    const errorMsg = typeof EDITOR_LANG !== 'undefined' ? EDITOR_LANG.error_upload_failed : 'Upload failed';
                    alert(errorMsg + ".");
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
    }
});