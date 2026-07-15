const overlay = document.getElementById("searchOverlay");
const input = document.getElementById("spotlight");
const results = document.getElementById("searchResults");
const openBtn = document.getElementById("openSearch");

function openSearch() {
    overlay.style.display = "flex";
    input.focus();
}

function closeSearch() {
    overlay.style.display = "none";
    input.value = "";
    results.innerHTML = "";
}

openBtn.addEventListener("click", openSearch);

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

        results.innerHTML = "<div class='search-empty'>Type at least 2 letters...</div>";

        return;

    }

    fetch("api/search.php?q=" + encodeURIComponent(q))
        .then(async (res) => {
            const text = await res.text();
            console.log("API Response:", text);
            return JSON.parse(text);
        })
        .then(data => {

            if (data.length === 0) {

                results.innerHTML = "<div class='search-empty'>No results found</div>";

                return;

            }

            results.innerHTML = "";

            data.forEach(item => {

                results.innerHTML += `
                    <a href="${item.url}" class="search-item">
                        <span style="font-size:22px">${item.icon}</span>
                        <div>
                            <strong>${item.title}</strong><br>
                            <small>${item.subtitle}</small>
                        </div>
                    </a>
                `;

            });

        });

});