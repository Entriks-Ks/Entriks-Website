function getCurrentPageStructure() {
    if (!editorDoc) return [];
    
    const structure = [];
    const sections = editorDoc.querySelectorAll('.page-section');
    
    sections.forEach(section => {
        const sectionId = section.id || '';
        if (!sectionId) return;
        
        let sectionType = '';
        const sectionKeyEl = section.querySelector('[data-key^="section_"]');
        
        if (sectionKeyEl) {
            const key = sectionKeyEl.getAttribute('data-key');
            sectionType = key.replace(/^section_/, '').replace(/_\d{10,}$/, '').replace(/_en$/, '');
        } else if (sectionId) {
            const match = sectionId.match(/section[-_]?(.+?)(?:[-_]\d+)?$/i);
            if (match) {
                sectionType = match[1].replace(/-/g, '_');
            }
        }
        
        structure.push({
            type: sectionType || 'unknown',
            id: sectionId
        });
    });
    
    return structure;
}

function savePageStructure(pageId) {
    const structure = getCurrentPageStructure();
    
    return fetch('backend/save_structure.php', {
        method: 'POST',
        body: JSON.stringify({
            page_id: pageId,
            structure: structure
        }),
        headers: { 'Content-Type': 'application/json' }
    }).then(res => res.json());
}