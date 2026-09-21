<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: logout.php");
    exit();
}

include 'header.php';

$is_admin = ($_SESSION['role'] ?? '') === 'admin';

// Sanitise the view parameter — never trust $_GET.
$view = $_GET['view'] ?? 'tax';
if (!in_array($view, ['tax', 'seizures'], true)) {
    $view = 'tax';
}

/**
 * Shared pagination renderer.
 * Preserves every other query parameter and only rewrites `page`.
 */
function hist_render_pagination($page, $total_pages)
{
    if ($total_pages <= 1) {
        return '';
    }

    $q = $_GET;
    $out = '<nav class="hist-pagination" aria-label="Pagination">';

    if ($page > 1) {
        $q['page'] = $page - 1;
        $out .= '<a href="?' . htmlspecialchars(http_build_query($q)) . '" class="hist-page-link" aria-label="Previous page"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></a>';
    }

    $start = max(1, $page - 2);
    $end   = min($total_pages, $page + 2);

    if ($start > 1) {
        $q['page'] = 1;
        $out .= '<a href="?' . htmlspecialchars(http_build_query($q)) . '" class="hist-page-link">1</a>';
        if ($start > 2) {
            $out .= '<span class="hist-page-ellipsis" aria-hidden="true">…</span>';
        }
    }

    for ($i = $start; $i <= $end; $i++) {
        $q['page'] = $i;
        $active = ($i === $page) ? ' active' : '';
        $aria   = ($i === $page) ? ' aria-current="page"' : '';
        $out .= '<a href="?' . htmlspecialchars(http_build_query($q)) . '" class="hist-page-link' . $active . '"' . $aria . '>' . $i . '</a>';
    }

    if ($end < $total_pages) {
        if ($end < $total_pages - 1) {
            $out .= '<span class="hist-page-ellipsis" aria-hidden="true">…</span>';
        }
        $q['page'] = $total_pages;
        $out .= '<a href="?' . htmlspecialchars(http_build_query($q)) . '" class="hist-page-link">' . $total_pages . '</a>';
    }

    if ($page < $total_pages) {
        $q['page'] = $page + 1;
        $out .= '<a href="?' . htmlspecialchars(http_build_query($q)) . '" class="hist-page-link" aria-label="Next page"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a>';
    }

    $out .= '</nav>';
    return $out;
}
?>

<style>
/* ================================================================
   History page — self-contained styles (scope: .history-page)
   Does not depend on style2.css beyond theme variables.
   ================================================================ */

.history-page {
    --hist-ease: cubic-bezier(0.16, 1, 0.3, 1);
    --hist-accent: #3b82f6;
    --hist-accent-soft: rgba(59, 130, 246, 0.15);
}

/* ---------- Animations ---------- */
@keyframes histRise {
    from { opacity: 0; transform: translateY(12px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes histPop {
    0%   { opacity: 0; transform: scale(0.94); }
    60%  { opacity: 1; transform: scale(1.02); }
    100% { opacity: 1; transform: scale(1); }
}
@keyframes histFloat {
    0%, 100% { transform: translateY(0); }
    50%      { transform: translateY(-6px); }
}
@keyframes histPulse {
    0%, 100% { opacity: 1; }
    50%      { opacity: 0.55; }
}

.hist-rise { opacity: 0; animation: histRise 0.5s var(--hist-ease) forwards; animation-delay: var(--d, 0ms); }
.hist-row  { opacity: 0; animation: histRise 0.4s var(--hist-ease) forwards; animation-delay: var(--d, 0ms); }

@media (prefers-reduced-motion: reduce) {
    .hist-rise, .hist-row { animation: none !important; opacity: 1 !important; transform: none !important; }
}

/* ---------- Page heading ---------- */
.history-heading {
    font-size: var(--text-3xl);
    font-weight: 700;
    color: var(--color-text);
    margin: 0;
    letter-spacing: -0.02em;
    display: flex;
    align-items: center;
    gap: var(--space-3);
}
.history-heading i { color: var(--hist-accent); }

/* ---------- Tabs ---------- */
.history-tabs {
    display: flex;
    gap: 6px;
    padding: 6px;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 16px;
    margin-bottom: 22px;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
}
.history-tabs::-webkit-scrollbar { display: none; }

.history-tab {
    flex: 1;
    min-width: 150px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 20px;
    border-radius: 12px;
    color: var(--color-text-muted);
    text-decoration: none;
    font-weight: 600;
    font-size: 0.9rem;
    transition: all 0.3s var(--hist-ease);
    position: relative;
    white-space: nowrap;
    overflow: hidden;
}
.history-tab::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(110deg, transparent 40%, rgba(255,255,255,0.10) 50%, transparent 60%);
    background-size: 200% 100%;
    background-position: -200% 0;
    transition: background-position 0.9s var(--hist-ease);
    pointer-events: none;
}
.history-tab:hover {
    color: var(--color-text);
    background: var(--color-surface-muted);
}
.history-tab:hover::before {
    background-position: 200% 0;
}
.history-tab.active {
    background: linear-gradient(135deg, var(--hist-accent), #6366f1);
    color: #fff;
    box-shadow: 0 10px 24px -10px rgba(59, 130, 246, 0.65);
}
.history-tab.active::before { display: none; }
.history-tab i { transition: transform 0.3s var(--hist-ease); }
.history-tab:hover i { transform: scale(1.15) rotate(-4deg); }

/* ---------- Summary strip ---------- */
.history-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}
.history-stat {
    padding: 18px;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 16px;
    transition:
        transform 0.25s var(--hist-ease),
        box-shadow 0.25s var(--hist-ease),
        border-color 0.25s var(--hist-ease);
}
.history-stat:hover {
    transform: translateY(-3px);
    box-shadow: 0 16px 34px -18px rgba(0, 0, 0, 0.55);
    border-color: rgba(59, 130, 246, 0.4);
}
.history-stat-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 10px;
    margin-bottom: 10px;
    background: var(--hist-accent-soft);
    color: #93c5fd;
    font-size: 0.95rem;
    transition: transform 0.3s var(--hist-ease);
}
.history-stat:hover .history-stat-icon { transform: scale(1.1) rotate(-4deg); }
.history-stat-label {
    font-size: 0.72rem;
    color: var(--color-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    font-weight: 600;
    margin-bottom: 4px;
}
.history-stat-value {
    font-size: 1.35rem;
    font-weight: 700;
    color: var(--color-text);
    letter-spacing: -0.02em;
    font-variant-numeric: tabular-nums;
}

/* ---------- Filter bar ---------- */
.history-filters {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 12px;
    padding: 16px;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 16px;
    margin-bottom: 18px;
}
.history-filters .form-field {
    flex: 1;
    min-width: 140px;
    margin-bottom: 0 !important;
}
.history-filters .btn {
    margin-top: 0 !important;
    height: auto;
}

.history-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-left: auto;
}
.history-export-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.85rem;
    text-decoration: none;
    transition: transform 0.2s var(--hist-ease),
                box-shadow 0.2s var(--hist-ease),
                background 0.2s var(--hist-ease);
    border: 1px solid transparent;
    white-space: nowrap;
}
.history-export-btn:hover { transform: translateY(-2px); }
.history-export-btn i { transition: transform 0.25s var(--hist-ease); }
.history-export-btn:hover i { transform: scale(1.15); }
.history-export-btn-csv {
    background: rgba(34, 197, 94, 0.12);
    color: #86efac;
    border-color: rgba(34, 197, 94, 0.30);
}
.history-export-btn-csv:hover {
    background: rgba(34, 197, 94, 0.2);
    box-shadow: 0 10px 24px -14px rgba(34, 197, 94, 0.65);
}
.history-export-btn-pdf {
    background: rgba(239, 68, 68, 0.12);
    color: #fca5a5;
    border-color: rgba(239, 68, 68, 0.30);
}
.history-export-btn-pdf:hover {
    background: rgba(239, 68, 68, 0.2);
    box-shadow: 0 10px 24px -14px rgba(239, 68, 68, 0.65);
}

/* ---------- Table rows ---------- */
.hist-table {
    border-collapse: separate;
    border-spacing: 0;
}
.hist-table tbody tr {
    transition: background 0.2s var(--hist-ease), transform 0.2s var(--hist-ease);
}
.hist-table tbody tr:hover {
    background: var(--color-surface-muted);
    transform: translateX(2px);
}

/* ---------- Seizure session card ---------- */
.seizure-session {
    position: relative;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 16px;
    margin-bottom: 16px;
    overflow: hidden;
    transition:
        transform 0.25s var(--hist-ease),
        box-shadow 0.25s var(--hist-ease),
        border-color 0.25s var(--hist-ease);
}
.seizure-session::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 3px;
    background: linear-gradient(180deg, #3b82f6, #a855f7);
    opacity: 0;
    transition: opacity 0.3s var(--hist-ease);
}
.seizure-session:hover {
    transform: translateY(-2px);
    box-shadow: 0 18px 42px -22px rgba(0, 0, 0, 0.55);
    border-color: rgba(59, 130, 246, 0.35);
}
.seizure-session:hover::before { opacity: 1; }

.seizure-head {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 18px 22px;
    border-bottom: 1px solid var(--color-border);
    background: linear-gradient(180deg, rgba(59, 130, 246, 0.04), transparent);
}
.seizure-title {
    font-size: 1.05rem;
    font-weight: 700;
    margin: 0 0 6px;
    color: var(--color-text);
    letter-spacing: -0.01em;
}
.seizure-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    font-size: 0.8rem;
    color: var(--color-text-muted);
}
.seizure-meta span {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.seizure-meta i { opacity: 0.65; }
.seizure-item-count {
    padding: 5px 12px;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 700;
    background: var(--hist-accent-soft);
    color: #93c5fd;
    letter-spacing: 0.03em;
}

/* ---------- Empty state ---------- */
.history-empty {
    padding: 60px 24px;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
}
.history-empty-icon {
    width: 68px;
    height: 68px;
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.7rem;
    background: var(--hist-accent-soft);
    color: #93c5fd;
    animation: histFloat 3.4s ease-in-out infinite;
}
.history-empty-title {
    font-weight: 700;
    font-size: 1.1rem;
    color: var(--color-text);
    margin: 0;
}
.history-empty-msg {
    font-size: 0.9rem;
    color: var(--color-text-muted);
    margin: 0;
    max-width: 380px;
}

/* ---------- Pagination ---------- */
.hist-pagination {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 6px;
    margin-top: 26px;
}
.hist-page-link {
    min-width: 40px;
    height: 40px;
    padding: 0 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    color: var(--color-text);
    text-decoration: none;
    font-weight: 600;
    font-size: 0.9rem;
    transition: all 0.2s var(--hist-ease);
}
.hist-page-link:hover {
    transform: translateY(-1px);
    border-color: var(--hist-accent);
    background: var(--hist-accent-soft);
    color: #93c5fd;
}
.hist-page-link.active {
    background: linear-gradient(135deg, var(--hist-accent), #6366f1);
    border-color: transparent;
    color: #fff;
    box-shadow: 0 8px 20px -10px rgba(59, 130, 246, 0.65);
    cursor: default;
}
.hist-page-link.active:hover { transform: none; }
.hist-page-ellipsis {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 28px;
    height: 40px;
    color: var(--color-text-muted);
    user-select: none;
}

/* ---------- Responsive ---------- */
@media (max-width: 640px) {
    .history-heading { font-size: var(--text-2xl); }
    .history-tab { min-width: 120px; padding: 10px 14px; font-size: 0.85rem; }
    .history-filters { padding: 14px; }
    .history-actions { margin-left: 0; width: 100%; }
    .history-export-btn { flex: 1; justify-content: center; }
}
</style>

<div class="page history-page">

    <!-- Page heading -->
    <div class="hist-rise" style="--d: 0ms; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: var(--space-4); margin-bottom: var(--space-6);">
        <div>
            <h1 class="history-heading">
                <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                History
            </h1>
            <p class="subtitle" style="margin-top: var(--space-1);">
                View, filter, and export your records
            </p>
        </div>
    </div>

    <!-- Tabs -->
    <nav class="history-tabs hist-rise" style="--d: 60ms;" role="tablist" aria-label="History views">
        <a href="history.php?view=tax"
           class="history-tab <?= $view === 'tax' ? 'active' : '' ?>"
           role="tab"
           aria-selected="<?= $view === 'tax' ? 'true' : 'false' ?>">
            <i class="fa-solid fa-receipt" aria-hidden="true"></i>
            <span><?php echo __('spot_tax_tab'); ?></span>
        </a>
        <a href="history.php?view=seizures"
           class="history-tab <?= $view === 'seizures' ? 'active' : '' ?>"
           role="tab"
           aria-selected="<?= $view === 'seizures' ? 'true' : 'false' ?>">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            <span><?php echo __('seizures_tab'); ?></span>
        </a>
    </nav>

    <!-- Active view -->
    <div class="hist-rise" style="--d: 120ms;">
        <?php if ($view === 'tax'): ?>
            <?php include 'history_tax_section.php'; ?>
        <?php else: ?>
            <?php include 'history_seizures_section.php'; ?>
        <?php endif; ?>
    </div>

</div>

<script>
/* ============================================================
   Animated number counter for summary stat values
   ============================================================ */
(function () {
    var reduced = window.matchMedia &&
                  window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function easeOutCubic(t) {
        return 1 - Math.pow(1 - t, 3);
    }

    /* Integer counters */
    document.querySelectorAll('.hist-counter').forEach(function (el) {
        var target = parseInt(el.getAttribute('data-target'), 10) || 0;
        if (reduced || target === 0) { el.textContent = target; return; }

        var start = null;
        function tick(ts) {
            if (start === null) start = ts;
            var p = Math.min((ts - start) / 900, 1);
            el.textContent = Math.floor(target * easeOutCubic(p));
            if (p < 1) requestAnimationFrame(tick);
            else el.textContent = target;
        }
        requestAnimationFrame(tick);
    });

    /* Currency counters */
    document.querySelectorAll('.hist-counter-currency').forEach(function (el) {
        var target = parseFloat(el.getAttribute('data-target')) || 0;
        if (reduced) {
            el.textContent = '₹' + target.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            return;
        }

        var start = null;
        function tick(ts) {
            if (start === null) start = ts;
            var p = Math.min((ts - start) / 1100, 1);
            var v = target * easeOutCubic(p);
            el.textContent = '₹' + v.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            if (p < 1) requestAnimationFrame(tick);
            else el.textContent = '₹' + target.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        requestAnimationFrame(tick);
    });
})();

/* ============================================================
   Auto-submit filter when date inputs change (nice UX)
   ============================================================ */
(function () {
    var form = document.querySelector('.history-filters');
    if (!form) return;
    form.querySelectorAll('input[type="date"]').forEach(function (inp) {
        inp.addEventListener('change', function () {
            if (inp.value) form.submit();
        });
    });
})();
</script>

<?php include 'footer.php'; ?>