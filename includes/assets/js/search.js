/* ================================================================
   DigiShulk — Global search overlay
   Fully self-contained. Safe to load on any page.
   ================================================================ */
(function () {
  "use strict";

  /* ---------- DOM refs ---------- */
  var overlay = document.getElementById("searchOverlay");
  var input = document.getElementById("spotlight");
  var results = document.getElementById("searchResults");
  var closeBtn = document.getElementById("closeSearch");

  // Support BOTH the legacy id and the new class-based selector.
  // Multiple buttons may exist (desktop topbar + mobile topbar).
  var openBtns = document.querySelectorAll("#openSearch, .js-open-search");

  // If the page has no search UI, exit silently.
  if (!overlay || !input || !results) {
    return;
  }

  /* ---------- State ---------- */
  var quickActionsTemplate = results.innerHTML;
  var lastFocusedElement = null;
  var selectedIndex = -1;
  var currentItems = [];
  var searchAbortController = null;
  var recentSearches = [];
  var isOpen = false;

  /* ---------- Recent searches (localStorage) ---------- */
  function loadRecentSearches() {
    try {
      var stored = localStorage.getItem("digishulk_recent_searches");
      recentSearches = stored ? JSON.parse(stored) : [];
      if (!Array.isArray(recentSearches)) recentSearches = [];
    } catch (e) {
      recentSearches = [];
    }
  }

  function saveRecentSearch(query) {
    query = (query || "").trim();
    if (query.length < 2) return;
    recentSearches = recentSearches.filter(function (s) {
      return s !== query;
    });
    recentSearches.unshift(query);
    recentSearches = recentSearches.slice(0, 5);
    try {
      localStorage.setItem(
        "digishulk_recent_searches",
        JSON.stringify(recentSearches),
      );
    } catch (e) {
      /* quota or blocked */
    }
  }

  /* ---------- HTML escape ---------- */
  function escapeHtml(text) {
    var div = document.createElement("div");
    div.textContent = text == null ? "" : String(text);
    return div.innerHTML;
  }

  /* ---------- Open / close ---------- */
  function openSearch() {
    if (isOpen) return;
    isOpen = true;
    lastFocusedElement = document.activeElement;

    overlay.hidden = false;
    document.body.style.overflow = "hidden";

    requestAnimationFrame(function () {
      input.focus();
    });

    selectedIndex = -1;
    loadRecentSearches();
    renderQuickActions();
  }

  function closeSearch() {
    if (!isOpen) return;
    isOpen = false;

    overlay.hidden = true;
    input.value = "";
    results.innerHTML = quickActionsTemplate;
    document.body.style.overflow = "";
    selectedIndex = -1;

    if (searchAbortController) {
      try {
        searchAbortController.abort();
      } catch (e) {}
      searchAbortController = null;
    }

    if (lastFocusedElement && typeof lastFocusedElement.focus === "function") {
      lastFocusedElement.focus();
    }
  }

  /* ---------- Quick actions + recent ---------- */
  function renderQuickActions() {
    results.innerHTML = quickActionsTemplate;

    if (recentSearches.length > 0) {
      var section = document.createElement("div");
      section.innerHTML =
        '<div class="search-section-title">' +
        '<i class="fa-solid fa-history" aria-hidden="true"></i> Recent Searches' +
        "</div>";

      recentSearches.forEach(function (query) {
        var item = document.createElement("a");
        item.className = "search-item";
        item.href = "#";
        item.dataset.query = query;
        item.innerHTML =
          '<i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>' +
          "<div>" +
          "<strong>" +
          escapeHtml(query) +
          "</strong>" +
          "<small>Recent search</small>" +
          "</div>";
        item.addEventListener("click", function (e) {
          e.preventDefault();
          input.value = query;
          input.dispatchEvent(new Event("input"));
        });
        section.appendChild(item);
      });

      results.appendChild(section);
    }
  }

  /* ---------- Loading ---------- */
  function renderLoadingState() {
    results.innerHTML =
      '<div class="search-empty" role="status" aria-live="polite" ' +
      'style="padding: var(--space-8) var(--space-4); text-align: center;">' +
      '<div class="skeleton" style="width: 32px; height: 32px; border-radius: 50%; ' +
      'margin: 0 auto var(--space-3);"></div>' +
      '<p class="empty-state-title" style="font-size: var(--text-base); margin: 0;">Searching...</p>' +
      "</div>";
  }

  /* ---------- Empty ---------- */
  function renderEmptyState() {
    results.innerHTML =
      '<div class="search-empty" style="padding: var(--space-8) var(--space-4); text-align: center;">' +
      '<div class="empty-state-icon" style="margin: 0 auto var(--space-3); ' +
      'width: 48px; height: 48px; font-size: 1.5rem;">' +
      '<i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>' +
      "</div>" +
      '<p class="empty-state-title" style="font-size: var(--text-base); margin: 0 0 var(--space-1);">No results found</p>' +
      '<p class="empty-state-message" style="font-size: var(--text-sm); margin: 0 0 var(--space-4);">' +
      "Try searching for a shop name, receipt number, or inspector" +
      "</p>" +
      '<div style="display: flex; flex-wrap: wrap; gap: var(--space-2); justify-content: center;">' +
      '<a href="spot_tax.php" class="btn btn-primary btn-sm">New Spot Tax</a>' +
      '<a href="seizure_form.php" class="btn btn-secondary btn-sm">New Seizure</a>' +
      '<a href="history.php" class="btn btn-ghost btn-sm">Browse History</a>' +
      "</div>" +
      "</div>";
  }

  /* ---------- Render results ---------- */
  function renderResults(data) {
    currentItems = [];
    results.innerHTML = "";
    results.setAttribute("role", "listbox");

    if (!data || !data.groups || data.groups.length === 0) {
      renderEmptyState();
      return;
    }

    data.groups.forEach(function (group) {
      var header = document.createElement("div");
      header.className = "search-section-title";
      header.innerHTML =
        '<i class="' +
        (group.icon || "fa-solid fa-circle") +
        '" aria-hidden="true"></i> ' +
        escapeHtml(group.label || "");
      results.appendChild(header);

      (group.items || []).forEach(function (item) {
        var div = document.createElement("div");
        div.className = "search-item";
        div.setAttribute("role", "option");
        div.setAttribute("tabindex", "0");
        div.dataset.url = item.url || "#";

        var badgeHtml = "";
        if (item.badge && item.badge.class && item.badge.label) {
          badgeHtml =
            '<span class="badge ' +
            escapeHtml(item.badge.class) +
            '" style="margin-left:auto;font-size:var(--text-xs);">' +
            escapeHtml(item.badge.label) +
            "</span>";
        }

        div.innerHTML =
          '<i class="' +
          escapeHtml(item.icon || "fa-solid fa-circle") +
          '" aria-hidden="true"></i>' +
          '<div style="flex:1;min-width:0;">' +
          '<div style="display:flex;align-items:center;gap:var(--space-2);">' +
          '<strong style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' +
          escapeHtml(item.title || "") +
          "</strong>" +
          badgeHtml +
          "</div>" +
          '<small style="color:var(--color-text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' +
          escapeHtml(item.subtitle || "") +
          "</small>" +
          "</div>";

        function navigate() {
          saveRecentSearch(input.value.trim());
          window.location.href = item.url || "#";
        }

        div.addEventListener("click", navigate);
        div.addEventListener("keydown", function (e) {
          if (e.key === "Enter" || e.key === " ") {
            e.preventDefault();
            navigate();
          }
        });

        currentItems.push(div);
        results.appendChild(div);
      });
    });

    if (data.suggestions && data.suggestions.length > 0) {
      var suggTitle = document.createElement("div");
      suggTitle.className = "search-section-title";
      suggTitle.innerHTML =
        '<i class="fa-solid fa-lightbulb" aria-hidden="true"></i> Suggestions';
      results.appendChild(suggTitle);

      data.suggestions.forEach(function (suggestion) {
        var item = document.createElement("a");
        item.className = "search-item";
        item.href = "#";
        item.innerHTML =
          '<i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>' +
          "<div><strong>" +
          escapeHtml(suggestion) +
          "</strong></div>";
        item.addEventListener("click", function (e) {
          e.preventDefault();
          input.value = suggestion;
          input.dispatchEvent(new Event("input"));
        });
        results.appendChild(item);
      });
    }

    updateSelection();
  }

  /* ---------- Keyboard navigation ---------- */
  function updateSelection() {
    currentItems.forEach(function (item, index) {
      if (index === selectedIndex) {
        item.classList.add("selected");
        item.setAttribute("aria-selected", "true");
        try {
          item.scrollIntoView({ block: "nearest" });
        } catch (e) {}
      } else {
        item.classList.remove("selected");
        item.setAttribute("aria-selected", "false");
      }
    });
  }

  function handleKeyNavigation(e) {
    if (!currentItems.length) return;

    if (e.key === "ArrowDown") {
      e.preventDefault();
      selectedIndex = Math.min(selectedIndex + 1, currentItems.length - 1);
      updateSelection();
    } else if (e.key === "ArrowUp") {
      e.preventDefault();
      selectedIndex = Math.max(selectedIndex - 1, 0);
      updateSelection();
    } else if (e.key === "Enter" && selectedIndex >= 0) {
      e.preventDefault();
      currentItems[selectedIndex].click();
    }
  }

  /* ---------- Wire open buttons ---------- */
  openBtns.forEach(function (btn) {
    btn.addEventListener("click", function (e) {
      e.preventDefault();
      openSearch();
    });
  });

  /* ---------- Wire close ---------- */
  if (closeBtn) {
    closeBtn.addEventListener("click", function (e) {
      e.preventDefault();
      closeSearch();
    });
  }

  /* ---------- Global keyboard ---------- */
  document.addEventListener("keydown", function (e) {
    // Open with Ctrl/Cmd + K
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === "k") {
      e.preventDefault();
      if (isOpen) {
        closeSearch();
      } else {
        openSearch();
      }
      return;
    }

    if (!isOpen) return;

    if (e.key === "Escape") {
      e.preventDefault();
      closeSearch();
      return;
    }

    handleKeyNavigation(e);
  });

  /* ---------- Click outside modal closes ---------- */
  overlay.addEventListener("click", function (e) {
    if (e.target === overlay) {
      closeSearch();
    }
  });

  /* ---------- Live search ---------- */
  var debounceTimer = null;

  input.addEventListener("input", function () {
    var q = this.value.trim();
    selectedIndex = -1;

    if (q.length < 1) {
      if (searchAbortController) {
        try {
          searchAbortController.abort();
        } catch (e) {}
        searchAbortController = null;
      }
      renderQuickActions();
      return;
    }

    // Debounce fast typing
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(function () {
      doSearch(q);
    }, 180);
  });

  function doSearch(q) {
    renderLoadingState();

    if (searchAbortController) {
      try {
        searchAbortController.abort();
      } catch (e) {}
    }
    searchAbortController = new AbortController();

    fetch("api/search.php?q=" + encodeURIComponent(q), {
      signal: searchAbortController.signal,
      credentials: "same-origin",
    })
      .then(function (res) {
        if (!res.ok) {
          throw new Error("HTTP " + res.status);
        }
        return res.json();
      })
      .then(function (data) {
        renderResults(data);
      })
      .catch(function (err) {
        if (err && err.name === "AbortError") return;
        console.error("[DigiShulk search]", err);
        renderEmptyState();
      });
  }
})();
