document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('globalSearchInput');
    const searchResults = document.getElementById('globalSearchResults');
    let debounceTimer;

    if (!searchInput || !searchResults) return;

    // Keyboard Shortcut (Ctrl+K / Cmd+K)
    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            searchInput.focus();
        }
    });

    // Close on click outside
    document.addEventListener('click', (e) => {
        if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
            searchResults.style.display = 'none';
        }
    });

    // Input Handler
    searchInput.addEventListener('input', (e) => {
        const query = e.target.value.trim();

        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            if (query.length < 2) {
                searchResults.style.display = 'none';
                return;
            }
            performSearch(query);
        }, 300);
    });

    // Focus Handler
    searchInput.addEventListener('focus', () => {
        if (searchInput.value.trim().length >= 2) {
            searchResults.style.display = 'block';
        }
    });


    const isInBlogSubdir = window.location.pathname.includes('/blog/');
    const apiPath = isInBlogSubdir ? '../api/search.php' : 'api/search.php';

    function performSearch(query) {
        fetch(`${apiPath}?q=${encodeURIComponent(query)}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    renderResults(data.results);
                }
            })
            .catch(err => console.error('Search error:', err));
    }

    function renderResults(groupedResults) {
        searchResults.innerHTML = '';
        const categories = Object.keys(groupedResults);

        if (categories.length === 0) {
            const noResultsText = (window.searchTranslations && window.searchTranslations.noResults) || 'No results found.';
            searchResults.innerHTML = '<div style="padding:12px;text-align:center;color:#6b7280;">' + escapeHtml(noResultsText) + '</div>';
            searchResults.style.display = 'block';
            return;
        }

        categories.forEach(cat => {
            const groupDiv = document.createElement('div');
            groupDiv.className = 'search-group';
            groupDiv.style.padding = '8px 0';

            const groupTitle = document.createElement('div');
            groupTitle.style.padding = '4px 12px';
            groupTitle.style.fontSize = '11px';
            groupTitle.style.fontWeight = '600';
            groupTitle.style.color = '#6b7280';
            groupTitle.style.textTransform = 'uppercase';
            groupTitle.style.letterSpacing = '0.5px';
            groupTitle.textContent = cat;
            groupDiv.appendChild(groupTitle);

            groupedResults[cat].forEach(item => {
                const link = document.createElement('a');
                // Adjust URL if we are in /blog/ and the URL is relative (doesn't start with http or /)
                let itemUrl = item.url;
                if (isInBlogSubdir && !itemUrl.startsWith('http') && !itemUrl.startsWith('/')) {
                    itemUrl = '../' + itemUrl;
                }

                link.href = itemUrl;
                link.className = 'search-result-item';
                link.style.display = 'block';
                link.style.padding = '8px 12px';
                link.style.textDecoration = 'none';
                link.style.color = '#fff';
                link.style.fontSize = '14px';
                link.style.transition = 'background 0.2s';
                link.style.borderLeft = '2px solid transparent';

                link.innerHTML = `
                    <div style="font-weight:500;">${escapeHtml(item.title)}</div>
                    ${item.subtitle ? `<div style="font-size:12px;color:#9ca3af;">${escapeHtml(item.subtitle)}</div>` : ''}
                `;

                link.addEventListener('mouseenter', () => {
                    link.style.background = '#262626';
                    link.style.borderLeftColor = '#7675ec';
                });
                link.addEventListener('mouseleave', () => {
                    link.style.background = 'transparent';
                    link.style.borderLeftColor = 'transparent';
                });

                groupDiv.appendChild(link);
            });

            searchResults.appendChild(groupDiv);
        });

        searchResults.style.display = 'block';
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
