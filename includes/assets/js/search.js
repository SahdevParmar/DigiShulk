const overlay = document.getElementById("searchOverlay");
const input = document.getElementById("spotlight");
const results = document.getElementById("searchResults");
const openBtn = document.getElementById("openSearch");
const closeBtn = document.getElementById("closeSearch");
const quickActionsTemplate = results.innerHTML;

function openSearch() {
    overlay.style.display = "flex";
    input.focus();
}

function closeSearch() {
    overlay.style.display = "none";
    input.value = "";
    results.innerHTML = quickActionsTemplate;
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

    if (e.key === "Escape") {
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

            if (data.length === 0) {
                results.innerHTML = "<div class='search-empty'>No results found</div>";
                return;
            }

            data.forEach(item => {

                const div = document.createElement("div");
                div.className = "search-item";
                div.style.cursor = "pointer";

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

                div.onclick = function () {

                    console.log("Clicked:", item);

                    if (item.type === "shop") {

                        window.location.href = "spot_tax.php?shop=" + item.shop_id;

                    } else {

                        window.location.href = item.url;

                    }

                };

                results.appendChild(div);

            });

        })
        .catch(err => {
            console.error(err);
        });

});
