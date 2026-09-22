const overlay = document.getElementById("searchOverlay");
const input = document.getElementById("spotlight");
const results = document.getElementById("searchResults");
const openBtn = document.getElementById("openSearch");
const closeBtn = document.getElementById("closeSearch");
const quickActionsTemplate = results.innerHTML;

let lastFocusedElement = null;
let selectedIndex = -1;
let currentItems = [];
let searchAbortController = null;
let recentSearches = [];

// Load recent searches from localStorage
function loadRecentSearches() {
    try {
        const stored = localStorage.getItem('digishulk_recent_searches');
        recentSearches = stored ? JSON.parse(stored) : [];
    } catch (e) {
        recentSearches = [];
    }
}

function saveRecentSearch(query) {
    if (!query || query.length < 2) return;
    recentSearches = recentSearches.filter(s => s !== query);
    recentSearches.unshift(query);
    recentSearches = recentSearches.slice(0, 5);
    try {
        localStorage.setItem('digishulk_recent_searches', JSON.stringify(recentSearches));
    } catch (e) {}
}

function openSearch() {
    lastFocusedElement = document.activeElement;
    overlay.hidden = false;
    requestAnimationFrame(() => {
        input.focus();
    });
    document.body.style.overflow = 'hidden';
    selectedIndex = -1;
    
    // Hide desktop search input if visible
    const spotlightDesktop = document.getElementById('spotlightDesktop');
    if (spotlightDesktop) {
        spotlightDesktop.style.display = 'none';
    }
    
    loadRecentSearches();
    renderQuickActions();
    trapFocus(overlay);
}

function closeSearch() {
    overlay.hidden = true;
    input.value = "";
    results.innerHTML = quickActionsTemplate;
    document.body.style.overflow = '';
    selectedIndex = -1;
    if (searchAbortController) {
        searchAbortController.abort();
        searchAbortController = null;
    }
    if (lastFocusedElement) {
        lastFocusedElement.focus();
    }
}

function trapFocus(element) {
    const focusableElements = element.querySelectorAll(
        'a[href], button, input, select, textarea, [tabindex]:not([tabindex="-1"])'
    );
    const firstElement = focusableElements[0];
    const lastElement = focusableElements[focusableElements.length - 1];

    element.addEventListener('keydown', function handleTab(e) {
        if (e.key !== 'Tab') return;

        if (e.shiftKey) {
            if (document.activeElement === firstElement) {
                e.preventDefault();
                lastElement.focus();
            }
        } else {
            if (document.activeElement === lastElement) {
                e.preventDefault();
                firstElement.focus();
            }
        }
    });
}

function renderQuickActions() {
    results.innerHTML = quickActionsTemplate;

    // Add recent searches section
    if (recentSearches.length > 0) {
        const recentSection = document.createElement('div');
        recentSection.innerHTML = `
            <div class="search-section-title">
                <i class="fa-solid fa-history" aria-hidden="true"></i> Recent Searches
            </div>
        `;
        recentSearches.forEach(query => {
            const item = document.createElement('a');
            item.className = 'search-item';
            item.href = '#';
            item.dataset.query = query;
            item.innerHTML = `
                <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                <div>
                    <strong>${escapeHtml(query)}</strong>
                    <small>Recent search</small>
                </div>
            `;
            item.addEventListener('click', (e) => {
                e.preventDefault();
                input.value = query;
                input.dispatchEvent(new Event('input'));
            });
            recentSection.appendChild(item);
        });
        results.appendChild(recentSection);
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function renderResults(data) {
    currentItems = [];
    results.innerHTML = "";
    results.setAttribute('role', 'listbox');

    if (!data.groups || data.groups.length === 0) {
        renderEmptyState();
        return;
    }

    data.groups.forEach((group, groupIndex) => {
        // Group header
        const groupHeader = document.createElement('div');
        groupHeader.className = 'search-section-title';
        groupHeader.innerHTML = `<i class="${group.icon}" aria-hidden="true"></i> ${group.label}`;
        results.appendChild(groupHeader);

        // Group items
        group.items.forEach((item, itemIndex) => {
            const div = document.createElement('div');
            div.className = 'search-item';
            div.setAttribute('role', 'option');
            div.setAttribute('tabindex', '0');
            div.dataset.url = item.url;
            div.dataset.group = groupIndex;
            div.dataset.index = itemIndex;

            const badgeHtml = item.badge ? `<span class="badge ${item.badge.class} badge-sm" style="margin-left: auto; font-size: var(--text-xs);">${item.badge.label}</span>` : '';

            div.innerHTML = `
                <i class="${item.icon}" aria-hidden="true"></i>
                <div style="flex: 1; min-width: 0;">
                    <div style="display: flex; align-items: center; gap: var(--space-2);">
                        <strong style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${escapeHtml(item.title)}</strong>
                        ${badgeHtml}
                    </div>
                    <small style="color: var(--color-text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${escapeHtml(item.subtitle)}</small>
                </div>
            `;

            const navigate = () => {
                saveRecentSearch(input.value.trim());
                window.location.href = item.url;
            };

            div.addEventListener('click', navigate);
            div.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    navigate();
                }
            });

            currentItems.push(div);
            results.appendChild(div);
        });
    });

    // Add suggestions if available
    if (data.suggestions && data.suggestions.length > 0) {
        const suggSection = document.createElement('div');
        suggSection.className = 'search-section-title';
        suggSection.innerHTML = `<i class="fa-solid fa-lightbulb" aria-hidden="true"></i> Suggestions`;
        results.appendChild(suggSection);

        data.suggestions.forEach(suggestion => {
            const item = document.createElement('a');
            item.className = 'search-item';
            item.href = '#';
            item.innerHTML = `
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <div><strong>${escapeHtml(suggestion)}</strong></div>
            `;
            item.addEventListener('click', (e) => {
                e.preventDefault();
                input.value = suggestion;
                input.dispatchEvent(new Event('input'));
            });
            results.appendChild(item);
        });
    }

    updateSelection();
}

function renderEmptyState() {
    results.innerHTML = `
        <div class="search-empty" role="option" style="padding: var(--space-8) var(--space-4); text-align: center;">
            <div class="empty-state-icon" style="margin: 0 auto var(--space-3); width: 48px; height: 48px; font-size: 1.5rem;">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            </div>
            <p class="empty-state-title" style="font-size: var(--text-base); margin: 0 0 var(--space-1);">No results found</p>
            <p class="empty-state-message" style="font-size: var(--text-sm); margin: 0 0 var(--space-4);">Try searching for a shop name, receipt number, or inspector</p>
            <div style="display: flex; flex-wrap: wrap; gap: var(--space-2); justify-content: center;">
                <a href="spot_tax.php" class="btn btn-primary btn-sm">New Spot Tax</a>
                <a href="seizure_form.php" class="btn btn-secondary btn-sm">New Seizure</a>
                <a href="history.php" class="btn btn-ghost btn-sm">Browse History</a>
            </div>
        </div>
    `;
}

function renderLoadingState() {
    results.innerHTML = `
        <div class="search-empty" role="status" aria-live="polite" style="padding: var(--space-8) var(--space-4); text-align: center;">
            <div class="skeleton" style="width: 32px; height: 32px; border-radius: 50%; margin: 0 auto var(--space-3);"></div>
            <p class="empty-state-title" style="font-size: var(--text-base); margin: 0;">Searching...</p>
        </div>
    `;
}

function updateSelection() {
    currentItems.forEach((item, index) => {
        if (index === selectedIndex) {
            item.classList.add('selected');
            item.setAttribute('aria-selected', 'true');
            item.scrollIntoView({ block: 'nearest' });
        } else {
            item.classList.remove('selected');
            item.setAttribute('aria-selected', 'false');
        }
    });
}

function handleKeyNavigation(e) {
    if (!currentItems.length) return;

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        selectedIndex = Math.min(selectedIndex + 1, currentItems.length - 1);
        updateSelection();
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        selectedIndex = Math.max(selectedIndex - 1, 0);
        updateSelection();
    } else if (e.key === 'Enter' && selectedIndex >= 0) {
        e.preventDefault();
        currentItems[selectedIndex].click();
    } else if (e.key === 'Escape') {
        closeSearch();
    }
}

if (openBtn) {
    openBtn.addEventListener("click", openSearch);
}
if (closeBtn) {
    closeBtn.addEventListener("click", closeSearch);
}

document.addEventListener("keydown", (e) => {
    if (e.ctrlKey && e.key.toLowerCase() === "k") {
        e.preventDefault();
        openSearch();
    }
    if (e.key === "Escape" && !overlay.hidden) {
        closeSearch();
    }
    if (!overlay.hidden) {
        handleKeyNavigation(e);
    }
});

overlay.addEventListener("click", (e) => {
    if (e.target === overlay) {
        closeSearch();
    }
});

input.addEventListener("input", function () {
    const q = this.value.trim();
    selectedIndex = -1;

    if (q.length < 1) {
        renderQuickActions();
        return;
    }

    renderLoadingState();

    if (searchAbortController) {
        searchAbortController.abort();
    }
    searchAbortController = new AbortController();

    fetch("api/search.php?q=" + encodeURIComponent(q), { signal: searchAbortController.signal })
        .then(async (res) => {
            const text = await res.text();
            return JSON.parse(text);
        })
        .then(data => {
            renderResults(data);
        })
        .catch(err => {
            if (err.name !== 'AbortError') {
                console.error(err);
                renderEmptyState();
            }
        });
});