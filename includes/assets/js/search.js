const overlay = document.getElementById("searchOverlay");
const input = document.getElementById("spotlight");
const results = document.getElementById("searchResults");
const openBtn = document.getElementById("openSearch");
const closeBtn = document.getElementById("closeSearch");
const quickActionsTemplate = results.innerHTML;
let lastFocusedElement = null;

function openSearch() {
    lastFocusedElement = document.activeElement;
    overlay.hidden = false;
    // Force reflow for animation
    requestAnimationFrame(() => {
        input.focus();
    });
    document.body.style.overflow = 'hidden';
    trapFocus(overlay);
}

function closeSearch() {
    overlay.hidden = true;
    input.value = "";
    results.innerHTML = quickActionsTemplate;
    document.body.style.overflow = '';
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
});

overlay.addEventListener("click", (e) => {
    if (e.target === overlay) {
        closeSearch();
    }
});

input.addEventListener("input", function () {
    const q = this.value.trim();

    if (q.length < 2) {
        results.innerHTML = quickActionsTemplate;
        return;
    }

    fetch("api/search.php?q=" + encodeURIComponent(q))
        .then(async (res) => {
            const text = await res.text();
            console.log("API Response:", text);
            return JSON.parse(text);
        })
        .then(data => {
            results.innerHTML = "";
            results.setAttribute('role', 'listbox');

            if (data.length === 0) {
                results.innerHTML = "<div class='search-empty' role='option'>No results found</div>";
                return;
            }

            data.forEach((item, index) => {
                const div = document.createElement("div");
                div.className = "search-item";
                div.setAttribute('role', 'option');
                div.setAttribute('tabindex', '0');

                const icon = document.createElement("i");
                icon.className = item.icon || "fa-solid fa-circle";
                icon.setAttribute("aria-hidden", "true");

                const text = document.createElement("div");
                const title = document.createElement("strong");
                title.textContent = item.title || "";
                const subtitle = document.createElement("small");
                subtitle.textContent = item.subtitle || "";

                text.append(title, document.createElement("br"), subtitle);
                div.append(icon, text);

                const navigate = () => {
                    if (item.type === "shop") {
                        window.location.href = "spot_tax.php?shop=" + item.shop_id;
                    } else {
                        window.location.href = item.url;
                    }
                };

                div.addEventListener('click', navigate);
                div.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        navigate();
                    }
                });

                results.appendChild(div);
            });
        })
        .catch(err => {
            console.error(err);
        });
});