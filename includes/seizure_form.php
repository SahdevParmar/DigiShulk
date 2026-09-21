<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'inspector') {
    header('Location: logout.php');
    exit();
}

require_once 'db_connect.php';
require_once 'helpers/csrf.php';

/* =========================================================
   FORM DATA REPOPULATION
   On server-side validation error, we stash everything in
   the session, then read it back here so the user doesn't
   lose their work.
   ========================================================= */
$form = $_SESSION['seizure_form_data'] ?? [];
unset($_SESSION['seizure_form_data']);

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['log_seizure'])) {

    csrf_require_or_die();

    // --- Session header fields ---
    $t_leader   = trim($_POST['team_leader_name']   ?? '');
    $zone       = trim($_POST['zone']               ?? '');
    $t_no       = trim($_POST['team_number']        ?? '');
    $s_date     = trim($_POST['seizure_date']       ?? '');
    $op_loc     = trim($_POST['operation_location'] ?? '');
    $s_notes    = trim($_POST['session_notes']      ?? '');

    // --- Item arrays ---
    $godown_nos = isset($_POST['godown_register_no'])  ? (array) $_POST['godown_register_no']  : [];
    $items      = isset($_POST['item_details'])        ? (array) $_POST['item_details']        : [];
    $qtys       = isset($_POST['quantity_seized'])     ? (array) $_POST['quantity_seized']     : [];
    $owners     = isset($_POST['owner_merchant_name']) ? (array) $_POST['owner_merchant_name'] : [];
    $locations  = isset($_POST['seizure_location'])    ? (array) $_POST['seizure_location']    : [];
    $cats       = isset($_POST['item_category'])       ? (array) $_POST['item_category']       : [];
    $values     = isset($_POST['estimated_value'])     ? (array) $_POST['estimated_value']     : [];
    $conditions = isset($_POST['condition_status'])    ? (array) $_POST['condition_status']    : [];

    // --- Validation ---
    $errors = [];
    if ($t_leader === '') { $errors[] = 'Team leader name is required.'; }
    if ($zone === '')     { $errors[] = 'Zone is required.'; }
    if ($t_no === '')     { $errors[] = 'Team number is required.'; }
    if ($s_date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $s_date)) {
        $errors[] = 'A valid seizure date is required.';
    }
    if (strlen($op_loc)  > 255) { $op_loc  = substr($op_loc, 0, 255); }
    if (strlen($s_notes) > 2000) { $s_notes = substr($s_notes, 0, 2000); }

    $hasItem = false;
    foreach ($items as $i => $detail) {
        if (trim((string) $detail) !== '') { $hasItem = true; break; }
    }
    if (!$hasItem) {
        $errors[] = 'Add at least one seized item with details.';
    }

    if (!empty($errors)) {
        // Repopulate on next render
        $_SESSION['seizure_form_data'] = $_POST;
        $msg = "<div class='sz-alert sz-alert-danger'>
            <i class='fa-solid fa-triangle-exclamation' aria-hidden='true'></i>
            <div>
                <strong>Please fix the following:</strong>
                <ul style='margin:6px 0 0; padding-left:18px;'>";
        foreach ($errors as $e) {
            $msg .= '<li>' . htmlspecialchars($e) . '</li>';
        }
        $msg .= "</ul></div></div>";

        // Re-sync the local $form so the inputs render with values
        $form = $_POST;

    } else {

        $conn->begin_transaction();
        try {
            // Session header
            $stmt = $conn->prepare(
                "INSERT INTO seizure_sessions
                    (inspector_id, team_leader_name, zone, team_number,
                     seizure_date, operation_location, session_notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param(
                'issssss',
                $_SESSION['user_id'], $t_leader, $zone, $t_no,
                $s_date,
                ($op_loc  === '' ? null : $op_loc),
                ($s_notes === '' ? null : $s_notes)
            );
            $stmt->execute();
            $session_id = (int) $conn->insert_id;

            // Items
            $item_stmt = $conn->prepare(
                "INSERT INTO seizure_items
                    (session_id, godown_register_no, item_details,
                     item_category, quantity, estimated_value,
                     condition_status, owner_merchant_name, seizure_location)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            $saved_count = 0;
            foreach ($items as $i => $item_detail) {
                $item_detail = trim((string) $item_detail);
                if ($item_detail === '') { continue; }

                $g_no   = isset($godown_nos[$i]) ? trim((string) $godown_nos[$i]) : '';
                $qty    = isset($qtys[$i])       ? (int) $qtys[$i]                : 0;
                $owner  = isset($owners[$i])     ? trim((string) $owners[$i])     : '';
                $loc    = isset($locations[$i])  ? trim((string) $locations[$i])  : '';
                $cat    = isset($cats[$i])       ? trim((string) $cats[$i])       : '';
                $val    = isset($values[$i]) && $values[$i] !== '' ? (float) $values[$i] : null;
                $cond   = isset($conditions[$i]) ? trim((string) $conditions[$i]) : 'good';

                if ($qty < 1) { $qty = 1; }
                if (strlen($item_detail) > 255) { $item_detail = substr($item_detail, 0, 255); }
                if (strlen($g_no)   > 100) { $g_no   = substr($g_no, 0, 100); }
                if (strlen($owner)  > 150) { $owner  = substr($owner, 0, 150); }
                if (strlen($loc)    > 255) { $loc    = substr($loc, 0, 255); }
                if (strlen($cat)    > 50)  { $cat    = substr($cat, 0, 50); }
                if (strlen($cond)   > 20)  { $cond   = substr($cond, 0, 20); }

                $item_stmt->bind_param(
                    'issisdsss',
                    $session_id,
                    $g_no,
                    $item_detail,
                    $cat,
                    $qty,
                    $val,
                    $cond,
                    $owner,
                    $loc
                );
                $item_stmt->execute();
                $saved_count++;
            }

            $conn->commit();

            $msg = "<div class='sz-alert sz-alert-success'>
                <i class='fa-solid fa-circle-check' aria-hidden='true'></i>
                <div>
                    <strong>" . __('success_msg') . "</strong>
                    <p style='margin:4px 0 0;'>"
                    . (int) $saved_count . " " . __('items_saved') . "
                    saved to session #{$session_id}.</p>
                </div>
            </div>";

        } catch (Throwable $e) {
            $conn->rollback();
            error_log('DigiShulk seizure_form: ' . $e->getMessage());

            // Also preserve data on DB error
            $_SESSION['seizure_form_data'] = $_POST;
            $form = $_POST;

            $msg = "<div class='sz-alert sz-alert-danger'>
                <i class='fa-solid fa-triangle-exclamation' aria-hidden='true'></i>
                <div>
                    <strong>Could not save the seizure report.</strong>
                    <p style='margin:4px 0 0;'>Please try again. If the problem persists, contact your administrator.</p>
                </div>
            </div>";
        }
    }
}

/* Normalise $form for template use */
$f_leader   = htmlspecialchars($form['team_leader_name']   ?? '');
$f_zone     = htmlspecialchars($form['zone']               ?? '');
$f_tno      = htmlspecialchars($form['team_number']        ?? '');
$f_date     = htmlspecialchars($form['seizure_date']       ?? date('Y-m-d'));
$f_oploc    = htmlspecialchars($form['operation_location'] ?? '');
$f_notes    = htmlspecialchars($form['session_notes']      ?? '');

/* Build a JSON payload of existing items for JS restore */
$existing_items = [];
if (!empty($form['item_details']) && is_array($form['item_details'])) {
    foreach ($form['item_details'] as $i => $detail) {
        if (trim((string) $detail) === '' && empty($form['godown_register_no'][$i])) continue;
        $existing_items[] = [
            'godown_register_no'  => $form['godown_register_no'][$i]  ?? '',
            'item_details'        => $detail,
            'item_category'       => $form['item_category'][$i]       ?? '',
            'quantity_seized'     => $form['quantity_seized'][$i]     ?? '',
            'estimated_value'     => $form['estimated_value'][$i]     ?? '',
            'condition_status'    => $form['condition_status'][$i]    ?? 'good',
            'owner_merchant_name' => $form['owner_merchant_name'][$i] ?? '',
            'seizure_location'    => $form['seizure_location'][$i]    ?? '',
        ];
    }
}
$existing_items_json = json_encode($existing_items, JSON_UNESCAPED_UNICODE);

$zones = ['Central', 'East', 'West', 'North', 'South', 'Other'];
$categories = ['Stall / Structure', 'Goods / Merchandise', 'Equipment', 'Vehicle', 'Signage', 'Other'];
$conditions = ['good' => 'Good', 'damaged' => 'Damaged', 'partial' => 'Partial'];

include 'header.php';
?>

<style>
/* ================================================================
   DigiShulk — Seizure Report wizard
   Scope: .sz-page. Self-contained. No dependency on style2.css
   beyond theme CSS variables.
   ================================================================ */

.sz-page {
    --sz-ease: cubic-bezier(0.16, 1, 0.3, 1);
    --sz-accent: #3b82f6;
    --sz-accent-2: #6366f1;
    --sz-accent-soft: rgba(59, 130, 246, 0.14);
    --sz-success: #22c55e;
    --sz-danger: #ef4444;
    --sz-warn: #eab308;
}

.sz-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 320px;
    gap: 22px;
    align-items: start;
    max-width: 1180px;
    margin: 0 auto;
}
@media (max-width: 900px) { .sz-grid { grid-template-columns: 1fr; } }

/* ---------- Animations ---------- */
@keyframes szSlideR { from { opacity: 0; transform: translateX(26px); } to { opacity: 1; transform: translateX(0); } }
@keyframes szSlideL { from { opacity: 0; transform: translateX(-26px); } to { opacity: 1; transform: translateX(0); } }
@keyframes szRise   { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: translateY(0); } }
@keyframes szPop {
    0% { opacity: 0; transform: scale(0.85); }
    60% { opacity: 1; transform: scale(1.05); }
    100% { opacity: 1; transform: scale(1); }
}
@keyframes szBounceIn {
    0% { opacity: 0; transform: scale(0.4); }
    60% { opacity: 1; transform: scale(1.15); }
    100% { opacity: 1; transform: scale(1); }
}
@keyframes szShimmer {
    0% { background-position: -200% 0; }
    100% { background-position: 200% 0; }
}
@keyframes szConfetti {
    0% { opacity: 1; transform: translate(0,0) rotate(0deg); }
    100% { opacity: 0; transform: translate(var(--dx), var(--dy)) rotate(var(--rot)); }
}
@keyframes szFlashVal {
    0% { color: var(--sz-accent); transform: scale(1.05); }
    100% { color: inherit; transform: scale(1); }
}

.sz-slide-r { animation: szSlideR 0.4s var(--sz-ease) both; }
.sz-slide-l { animation: szSlideL 0.4s var(--sz-ease) both; }
.sz-rise    { opacity: 0; animation: szRise 0.5s var(--sz-ease) forwards; animation-delay: var(--d, 0ms); }
.sz-pop     { animation: szPop 0.35s var(--sz-ease) both; }

@media (prefers-reduced-motion: reduce) {
    .sz-slide-r, .sz-slide-l, .sz-rise, .sz-pop, .sz-item-row {
        animation: none !important;
        opacity: 1 !important;
        transform: none !important;
    }
}

/* ---------- Alerts ---------- */
.sz-alert {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    padding: 14px 18px;
    border-radius: 14px;
    margin-bottom: 20px;
    border: 1px solid transparent;
    animation: szRise 0.4s var(--sz-ease) both;
    font-size: 0.9rem;
}
.sz-alert i:first-child {
    font-size: 1.15rem;
    margin-top: 2px;
    flex-shrink: 0;
}
.sz-alert-success {
    background: rgba(34, 197, 94, 0.10);
    border-color: rgba(34, 197, 94, 0.30);
    color: #86efac;
}
.sz-alert-danger {
    background: rgba(239, 68, 68, 0.10);
    border-color: rgba(239, 68, 68, 0.30);
    color: #fca5a5;
}
.sz-alert ul { margin: 6px 0 0; padding-left: 18px; }

/* ---------- Progress bar ---------- */
.sz-progress {
    position: sticky;
    top: 8px;
    z-index: 5;
    height: 6px;
    background: var(--color-surface-muted);
    border-radius: 999px;
    overflow: hidden;
    margin-bottom: 22px;
    box-shadow: inset 0 1px 2px rgba(0,0,0,0.25);
}
.sz-progress-fill {
    height: 100%;
    width: 33.33%;
    background: linear-gradient(90deg, var(--sz-accent), var(--sz-accent-2));
    border-radius: 999px;
    transition: width 0.5s var(--sz-ease);
    position: relative;
}
.sz-progress-fill::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(110deg, transparent 30%, rgba(255,255,255,0.35) 45%, transparent 60%);
    background-size: 200% 100%;
    animation: szShimmer 2.4s linear infinite;
}
.sz-progress-label {
    display: flex;
    justify-content: space-between;
    font-size: 0.72rem;
    color: var(--color-text-muted);
    font-weight: 600;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    margin: -14px 0 20px;
}

/* ---------- Step indicator ---------- */
.sz-steps {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 28px;
    position: relative;
}
.sz-steps::before {
    content: '';
    position: absolute;
    left: 20px; right: 20px; top: 22px;
    height: 2px;
    background: var(--color-border);
    z-index: 0;
}
.sz-steps::after {
    content: '';
    position: absolute;
    left: 20px; top: 22px;
    height: 2px;
    background: linear-gradient(90deg, var(--sz-accent), var(--sz-accent-2));
    z-index: 0;
    transition: width 0.5s var(--sz-ease);
    width: calc((var(--step, 1) - 1) / 2 * (100% - 40px));
}
.sz-step {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    position: relative;
    z-index: 1;
    text-align: center;
}
.sz-step-circle {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.95rem;
    background: var(--color-surface);
    color: var(--color-text-muted);
    border: 2px solid var(--color-border);
    transition: all 0.35s var(--sz-ease);
    position: relative;
}
.sz-step.active .sz-step-circle {
    background: linear-gradient(135deg, var(--sz-accent), var(--sz-accent-2));
    color: #fff;
    border-color: transparent;
    transform: scale(1.08);
    box-shadow: 0 10px 24px -10px rgba(59, 130, 246, 0.7);
}
.sz-step.done .sz-step-circle {
    background: var(--sz-success);
    color: #fff;
    border-color: transparent;
}
.sz-step.done .sz-step-circle::after {
    content: '\f00c';
    font-family: 'Font Awesome 6 Free';
    font-weight: 900;
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: szBounceIn 0.4s var(--sz-ease) both;
}
.sz-step.done .sz-step-circle span { display: none; }
.sz-step-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--color-text-muted);
    transition: color 0.3s var(--sz-ease);
}
.sz-step.active .sz-step-label { color: var(--color-text); }
.sz-step.done .sz-step-label   { color: var(--sz-success); }

/* ---------- Form body ---------- */
.sz-form-body { position: relative; }
.sz-panel { display: none; }
.sz-panel.active { display: block; }

.sz-panel-head {
    text-align: center;
    margin-bottom: 24px;
}
.sz-panel-title {
    font-size: 1.35rem;
    font-weight: 700;
    margin: 0 0 6px;
    color: var(--color-text);
    letter-spacing: -0.015em;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}
.sz-panel-sub {
    color: var(--color-text-muted);
    font-size: 0.9rem;
    margin: 0;
}

/* ---------- Fields ---------- */
.sz-field { margin-bottom: 18px; }
.sz-label {
    display: block;
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--color-text);
    margin-bottom: 8px;
}
.sz-label .optional {
    color: var(--color-text-muted);
    font-weight: 400;
    font-size: 0.72rem;
    margin-left: 6px;
}
.sz-input,
.sz-select,
.sz-textarea {
    width: 100%;
    padding: 13px 15px;
    background: var(--color-surface-muted);
    border: 1px solid var(--color-border);
    border-radius: 12px;
    color: var(--color-text);
    font-family: inherit;
    font-size: 0.95rem;
    transition: border-color 0.2s var(--sz-ease), box-shadow 0.2s var(--sz-ease), background 0.2s var(--sz-ease);
    box-sizing: border-box;
}
.sz-input:focus,
.sz-select:focus,
.sz-textarea:focus {
    outline: none;
    border-color: var(--sz-accent);
    background: var(--color-surface);
    box-shadow: 0 0 0 4px var(--sz-accent-soft);
}
.sz-input.error,
.sz-select.error,
.sz-textarea.error {
    border-color: var(--sz-danger);
    background: rgba(239, 68, 68, 0.06);
}
.sz-help { font-size: 0.78rem; color: var(--color-text-muted); margin-top: 6px; }
.sz-error {
    font-size: 0.78rem;
    color: #fca5a5;
    margin-top: 6px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.sz-textarea { resize: vertical; min-height: 80px; line-height: 1.5; }

.sz-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}
@media (max-width: 520px) { .sz-row { grid-template-columns: 1fr; } }

/* ---------- Items ---------- */
.sz-items-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 14px;
}
.sz-items-count {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 12px;
    border-radius: 999px;
    background: var(--sz-accent-soft);
    color: #93c5fd;
    font-weight: 700;
    font-size: 0.8rem;
}
.sz-add-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    border-radius: 10px;
    background: linear-gradient(135deg, var(--sz-accent), var(--sz-accent-2));
    color: #fff;
    font-weight: 700;
    font-size: 0.85rem;
    border: none;
    cursor: pointer;
    font-family: inherit;
    box-shadow: 0 10px 24px -12px rgba(59, 130, 246, 0.7);
    transition: transform 0.2s var(--sz-ease), box-shadow 0.2s var(--sz-ease);
}
.sz-add-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 16px 30px -12px rgba(59, 130, 246, 0.85);
}
.sz-add-btn i { transition: transform 0.25s var(--sz-ease); }
.sz-add-btn:hover i { transform: rotate(90deg) scale(1.1); }

/* Quick-add category chips */
.sz-quick {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 16px;
}
.sz-quick-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 13px;
    border-radius: 999px;
    background: var(--color-surface-muted);
    border: 1px solid var(--color-border);
    color: var(--color-text-muted);
    font-weight: 600;
    font-size: 0.78rem;
    cursor: pointer;
    transition: all 0.2s var(--sz-ease);
    font-family: inherit;
}
.sz-quick-chip:hover {
    border-color: var(--sz-accent);
    color: var(--color-text);
    transform: translateY(-1px);
}

/* Item row */
.sz-item-row {
    background: var(--color-surface-muted);
    border: 1px solid var(--color-border);
    border-radius: 14px;
    padding: 16px 18px;
    margin-bottom: 12px;
    position: relative;
    animation: szRise 0.35s var(--sz-ease) both;
    transition: border-color 0.2s var(--sz-ease);
}
.sz-item-row:hover { border-color: rgba(59, 130, 246, 0.35); }

.sz-item-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 14px;
}
.sz-item-num {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--color-text);
}
.sz-item-num-badge {
    width: 26px;
    height: 26px;
    border-radius: 8px;
    background: var(--sz-accent-soft);
    color: #93c5fd;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
}
.sz-item-actions {
    display: flex;
    gap: 6px;
}
.sz-icon-btn {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: 1px solid var(--color-border);
    background: var(--color-surface);
    color: var(--color-text-muted);
    cursor: pointer;
    font-family: inherit;
    transition: all 0.2s var(--sz-ease);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
}
.sz-icon-btn:hover {
    color: var(--color-text);
    border-color: var(--sz-accent);
    transform: translateY(-1px);
}
.sz-icon-btn.danger:hover {
    color: #fca5a5;
    border-color: var(--sz-danger);
    background: rgba(239, 68, 68, 0.08);
}

/* ---------- Buttons ---------- */
.sz-actions {
    display: flex;
    gap: 12px;
    margin-top: 24px;
    flex-wrap: wrap;
}
.sz-btn {
    flex: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    padding: 14px 20px;
    border-radius: 12px;
    font-weight: 700;
    font-size: 0.95rem;
    font-family: inherit;
    cursor: pointer;
    transition: all 0.2s var(--sz-ease);
    border: 1px solid transparent;
    position: relative;
    overflow: hidden;
    min-width: 140px;
}
.sz-btn:disabled { opacity: 0.55; cursor: not-allowed; }
.sz-btn i { transition: transform 0.25s var(--sz-ease); }
.sz-btn:hover:not(:disabled) i { transform: scale(1.15) rotate(-4deg); }

.sz-btn-primary {
    background: linear-gradient(135deg, var(--sz-accent), var(--sz-accent-2));
    color: #fff;
    box-shadow: 0 12px 28px -12px rgba(59, 130, 246, 0.75);
}
.sz-btn-primary:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 18px 34px -12px rgba(59, 130, 246, 0.85);
}
.sz-btn-primary::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(110deg, transparent 35%, rgba(255,255,255,0.28) 50%, transparent 65%);
    background-size: 200% 100%;
    animation: szShimmer 3.6s linear infinite;
    pointer-events: none;
}
.sz-btn-secondary {
    background: var(--color-surface-muted);
    color: var(--color-text);
    border-color: var(--color-border);
}
.sz-btn-secondary:hover {
    background: var(--color-surface);
    border-color: var(--sz-accent);
    transform: translateY(-1px);
}

/* ---------- Review card ---------- */
.sz-review {
    border-radius: 14px;
    background: var(--color-surface-muted);
    padding: 18px;
}
.sz-review-section { margin-bottom: 16px; }
.sz-review-section:last-child { margin-bottom: 0; }
.sz-review-title {
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--color-text-muted);
    margin: 0 0 10px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.sz-review-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}
@media (max-width: 520px) { .sz-review-grid { grid-template-columns: 1fr; } }
.sz-review-item {
    padding: 10px 12px;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 10px;
    font-size: 0.85rem;
}
.sz-review-item-label {
    font-size: 0.7rem;
    color: var(--color-text-muted);
    margin-bottom: 3px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    font-weight: 600;
}
.sz-review-item-val {
    color: var(--color-text);
    font-weight: 600;
    word-break: break-word;
}
.sz-review-items-list {
    display: grid;
    gap: 10px;
    max-height: 320px;
    overflow-y: auto;
    padding-right: 4px;
}
.sz-review-item-card {
    padding: 12px 14px;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 12px;
    font-size: 0.85rem;
    position: relative;
}
.sz-review-item-card-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
    gap: 8px;
}
.sz-review-item-card-name {
    font-weight: 700;
    color: var(--color-text);
}
.sz-review-item-card-qty {
    font-size: 0.72rem;
    padding: 2px 9px;
    border-radius: 999px;
    background: var(--sz-accent-soft);
    color: #93c5fd;
    font-weight: 700;
}
.sz-review-item-card-meta {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 6px 12px;
    font-size: 0.78rem;
    color: var(--color-text-muted);
}
.sz-review-item-card-meta strong { color: var(--color-text); font-weight: 500; }
.sz-review-empty {
    padding: 24px;
    text-align: center;
    color: var(--color-text-muted);
    font-size: 0.85rem;
}

/* ---------- Summary sidebar ---------- */
.sz-summary {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 18px;
    padding: 22px;
    position: sticky;
    top: 20px;
    overflow: hidden;
}
.sz-summary::before {
    content: '';
    position: absolute;
    inset: -40% -40% auto auto;
    width: 200px;
    height: 200px;
    background: radial-gradient(circle, rgba(59, 130, 246, 0.18), transparent 65%);
    pointer-events: none;
    filter: blur(6px);
}
.sz-summary-head {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 18px;
    position: relative;
}
.sz-summary-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: var(--sz-accent-soft);
    color: #93c5fd;
    display: flex;
    align-items: center;
    justify-content: center;
}
.sz-summary-title {
    font-size: 0.95rem;
    font-weight: 700;
    margin: 0;
    color: var(--color-text);
}
.sz-summary-sub {
    font-size: 0.72rem;
    color: var(--color-text-muted);
    margin: 0;
}
.sz-summary-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
    position: relative;
}
.sz-summary-item {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    font-size: 0.84rem;
    padding-bottom: 12px;
    border-bottom: 1px dashed var(--color-border);
}
.sz-summary-item:last-child { border-bottom: none; padding-bottom: 0; }
.sz-summary-key { color: var(--color-text-muted); flex-shrink: 0; }
.sz-summary-val {
    color: var(--color-text);
    font-weight: 600;
    text-align: right;
    word-break: break-word;
    transition: color 0.3s var(--sz-ease);
}
.sz-summary-val.empty { color: var(--color-text-muted); font-weight: 400; font-style: italic; }
.sz-summary-val.flash { animation: szFlashVal 0.5s var(--sz-ease); }

.sz-summary-total {
    margin-top: 18px;
    padding-top: 16px;
    border-top: 1px solid var(--color-border);
    display: grid;
    grid-template-columns: 1fr auto;
    align-items: baseline;
    gap: 8px;
    position: relative;
}
.sz-summary-total-label {
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--color-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.sz-summary-total-amount {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--sz-accent);
    letter-spacing: -0.02em;
    font-variant-numeric: tabular-nums;
}

/* ---------- Confetti ---------- */
.sz-confetti-piece {
    position: fixed;
    width: 8px;
    height: 8px;
    pointer-events: none;
    z-index: 9999;
    border-radius: 2px;
    animation: szConfetti 1.1s ease-out forwards;
}

/* ---------- Responsive ---------- */
@media (max-width: 900px) {
    .sz-summary { position: static; }
}
</style>

<div class="page sz-page">
    <div class="sz-grid">

        <!-- ==================== FORM ==================== -->
        <div class="sz-main sz-rise" style="--d: 0ms;">

            <div class="sz-progress">
                <div class="sz-progress-fill" id="szProgressFill"></div>
            </div>
            <div class="sz-progress-label">
                <span id="szProgressText">Step 1 of 3</span>
                <span id="szProgressPercent">33%</span>
            </div>

            <?= $msg ?>

            <div class="sz-steps" id="szSteps" style="--step: 1;">
                <div class="sz-step active" data-step="1">
                    <div class="sz-step-circle"><span>1</span></div>
                    <div class="sz-step-label">Session</div>
                </div>
                <div class="sz-step" data-step="2">
                    <div class="sz-step-circle"><span>2</span></div>
                    <div class="sz-step-label">Items</div>
                </div>
                <div class="sz-step" data-step="3">
                    <div class="sz-step-circle"><span>3</span></div>
                    <div class="sz-step-label">Review</div>
                </div>
            </div>

            <form method="POST" action="" id="seizureForm" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="log_seizure" value="1">

                <div class="card">
                    <div class="card-body sz-form-body">

                        <!-- ============ STEP 1: SESSION ============ -->
                        <div class="sz-panel active" data-step="1">
                            <div class="sz-panel-head">
                                <h2 class="sz-panel-title">
                                    <i class="fa-solid fa-clipboard-list" style="color: var(--sz-accent);" aria-hidden="true"></i>
                                    Session Details
                                </h2>
                                <p class="sz-panel-sub">Operation info — who, when, where</p>
                            </div>

                            <div class="sz-row">
                                <div class="sz-field">
                                    <label class="sz-label" for="team_leader_name">Team leader <span style="color: var(--sz-danger);">*</span></label>
                                    <input type="text" id="team_leader_name" name="team_leader_name"
                                           class="sz-input <?= !empty($msg) && $f_leader === '' ? 'error' : '' ?>"
                                           maxlength="150" required
                                           value="<?= $f_leader ?>"
                                           placeholder="e.g. Ramesh Patel">
                                </div>

                                <div class="sz-field">
                                    <label class="sz-label" for="team_number">Team number <span style="color: var(--sz-danger);">*</span></label>
                                    <input type="text" id="team_number" name="team_number"
                                           class="sz-input <?= !empty($msg) && $f_tno === '' ? 'error' : '' ?>"
                                           maxlength="50" required
                                           value="<?= $f_tno ?>"
                                           placeholder="e.g. Team 12">
                                </div>
                            </div>

                            <div class="sz-row">
                                <div class="sz-field">
                                    <label class="sz-label" for="zone">Zone <span style="color: var(--sz-danger);">*</span></label>
                                    <select id="zone" name="zone" class="sz-select <?= !empty($msg) && $f_zone === '' ? 'error' : '' ?>" required>
                                        <option value="">— Select zone —</option>
                                        <?php foreach ($zones as $z): ?>
                                            <option value="<?= htmlspecialchars($z) ?>" <?= $f_zone === $z ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($z) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="sz-field">
                                    <label class="sz-label" for="seizure_date">Date <span style="color: var(--sz-danger);">*</span></label>
                                    <input type="date" id="seizure_date" name="seizure_date"
                                           class="sz-input"
                                           max="<?= date('Y-m-d') ?>"
                                           required
                                           value="<?= $f_date ?>">
                                </div>
                            </div>

                            <div class="sz-field">
                                <label class="sz-label" for="operation_location">Operation location <span class="optional">optional</span></label>
                                <input type="text" id="operation_location" name="operation_location"
                                       class="sz-input"
                                       maxlength="255"
                                       value="<?= $f_oploc ?>"
                                       placeholder="Area, road, or landmark of the raid">
                            </div>

                            <div class="sz-field">
                                <label class="sz-label" for="session_notes">Session notes <span class="optional">optional</span></label>
                                <textarea id="session_notes" name="session_notes"
                                          class="sz-textarea"
                                          maxlength="2000"
                                          placeholder="Any additional context about this operation"
                                          rows="3"><?= $f_notes ?></textarea>
                            </div>

                            <div class="sz-actions">
                                <button type="button" class="sz-btn sz-btn-primary" data-next="2">
                                    <span>Continue to Items</span>
                                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>

                        <!-- ============ STEP 2: ITEMS ============ -->
                        <div class="sz-panel" data-step="2">
                            <div class="sz-panel-head">
                                <h2 class="sz-panel-title">
                                    <i class="fa-solid fa-boxes-stacked" style="color: var(--sz-accent);" aria-hidden="true"></i>
                                    Seized Items
                                </h2>
                                <p class="sz-panel-sub">Add each item seized in this session</p>
                            </div>

                            <div class="sz-items-head">
                                <span class="sz-items-count" id="szItemsCount">
                                    <i class="fa-solid fa-cube" aria-hidden="true"></i>
                                    <span id="szItemsCountText">0 items</span>
                                </span>
                                <button type="button" class="sz-add-btn" id="szAddItem">
                                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                                    Add item
                                </button>
                            </div>

                            <div class="sz-quick">
                                <button type="button" class="sz-quick-chip" data-quick="Rekdi">
                                    <i class="fa-solid fa-store" aria-hidden="true"></i> Rekdi
                                </button>
                                <button type="button" class="sz-quick-chip" data-quick="Cabin">
                                    <i class="fa-solid fa-house" aria-hidden="true"></i> Cabin
                                </button>
                                <button type="button" class="sz-quick-chip" data-quick="Fruit cart">
                                    <i class="fa-solid fa-apple-whole" aria-hidden="true"></i> Fruit cart
                                </button>
                                <button type="button" class="sz-quick-chip" data-quick="Vegetable stand">
                                    <i class="fa-solid fa-carrot" aria-hidden="true"></i> Vegetable stand
                                </button>
                                <button type="button" class="sz-quick-chip" data-quick="Signage">
                                    <i class="fa-solid fa-sign-hanging" aria-hidden="true"></i> Signage
                                </button>
                            </div>

                            <div id="szItemsContainer"></div>

                            <div class="sz-actions">
                                <button type="button" class="sz-btn sz-btn-secondary" data-back="1">
                                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                                    <span>Back</span>
                                </button>
                                <button type="button" class="sz-btn sz-btn-primary" data-next="3">
                                    <span>Review</span>
                                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>

                        <!-- ============ STEP 3: REVIEW ============ -->
                        <div class="sz-panel" data-step="3">
                            <div class="sz-panel-head">
                                <h2 class="sz-panel-title">
                                    <i class="fa-solid fa-circle-check" style="color: var(--sz-success);" aria-hidden="true"></i>
                                    Review &amp; Submit
                                </h2>
                                <p class="sz-panel-sub">Check everything before logging the seizure</p>
                            </div>

                            <div class="sz-review" id="szReviewCard"></div>

                            <div class="sz-actions">
                                <button type="button" class="sz-btn sz-btn-secondary" data-back="2">
                                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                                    <span>Edit items</span>
                                </button>
                                <button type="submit" class="sz-btn sz-btn-primary" id="szSubmitBtn">
                                    <i class="fa-solid fa-save" aria-hidden="true"></i>
                                    <span><?php echo __('submit'); ?></span>
                                </button>
                            </div>
                        </div>

                    </div>
                </div>
            </form>

        </div>

        <!-- ==================== SUMMARY ==================== -->
        <aside class="sz-summary sz-rise" style="--d: 120ms;">
            <div class="sz-summary-head">
                <div class="sz-summary-icon"><i class="fa-solid fa-chart-simple" aria-hidden="true"></i></div>
                <div>
                    <p class="sz-summary-title">Session Summary</p>
                    <p class="sz-summary-sub">Live preview</p>
                </div>
            </div>

            <div class="sz-summary-list">
                <div class="sz-summary-item">
                    <span class="sz-summary-key">Team leader</span>
                    <span class="sz-summary-val empty" id="szSumLeader">—</span>
                </div>
                <div class="sz-summary-item">
                    <span class="sz-summary-key">Zone</span>
                    <span class="sz-summary-val empty" id="szSumZone">—</span>
                </div>
                <div class="sz-summary-item">
                    <span class="sz-summary-key">Team #</span>
                    <span class="sz-summary-val empty" id="szSumTeam">—</span>
                </div>
                <div class="sz-summary-item">
                    <span class="sz-summary-key">Date</span>
                    <span class="sz-summary-val empty" id="szSumDate">—</span>
                </div>
            </div>

            <div class="sz-summary-total">
                <span class="sz-summary-total-label">Items seized</span>
                <span class="sz-summary-total-amount" id="szSumItems">0</span>
            </div>

            <div class="sz-summary-total" style="margin-top: 8px; padding-top: 8px; border-top: none;">
                <span class="sz-summary-total-label">Total value</span>
                <span class="sz-summary-total-amount" id="szSumValue" style="color: var(--sz-success); font-size: 1.15rem;">₹0</span>
            </div>
        </aside>

    </div>
</div>

<!-- ============ ITEM TEMPLATE ============ -->
<template id="szItemTemplate">
    <div class="sz-item-row">
        <div class="sz-item-head">
            <span class="sz-item-num">
                <span class="sz-item-num-badge">#</span>
                <span>Item</span>
            </span>
            <div class="sz-item-actions">
                <button type="button" class="sz-icon-btn sz-duplicate" aria-label="Duplicate item" title="Duplicate">
                    <i class="fa-solid fa-copy" aria-hidden="true"></i>
                </button>
                <button type="button" class="sz-icon-btn danger sz-remove" aria-label="Remove item" title="Remove">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
        </div>

        <div class="sz-row">
            <div class="sz-field" style="margin-bottom: 0;">
                <label class="sz-label">Category</label>
                <select name="item_category[]" class="sz-select">
                    <option value="">— Category —</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sz-field" style="margin-bottom: 0;">
                <label class="sz-label">Condition</label>
                <select name="condition_status[]" class="sz-select">
                    <?php foreach ($conditions as $k => $v): ?>
                        <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="sz-field" style="margin-top: 12px;">
            <label class="sz-label">Item details <span style="color: var(--sz-danger);">*</span></label>
            <input type="text" name="item_details[]" class="sz-input"
                   maxlength="255" required
                   placeholder="e.g. Wooden Rekdi with red canopy">
        </div>

        <div class="sz-row">
            <div class="sz-field" style="margin-bottom: 0;">
                <label class="sz-label">Quantity <span style="color: var(--sz-danger);">*</span></label>
                <input type="number" name="quantity_seized[]" class="sz-input"
                       min="1" step="1" required
                       placeholder="0"
                       inputmode="numeric">
            </div>

            <div class="sz-field" style="margin-bottom: 0;">
                <label class="sz-label">Estimated value (₹) <span class="optional">optional</span></label>
                <input type="number" name="estimated_value[]" class="sz-input"
                       min="0" step="0.01"
                       placeholder="0.00"
                       inputmode="decimal">
            </div>
        </div>

        <div class="sz-row" style="margin-top: 12px;">
            <div class="sz-field" style="margin-bottom: 0;">
                <label class="sz-label">Owner / merchant <span class="optional">optional</span></label>
                <input type="text" name="owner_merchant_name[]" class="sz-input"
                       maxlength="150"
                       placeholder="Full name">
            </div>

            <div class="sz-field" style="margin-bottom: 0;">
                <label class="sz-label">Godown register no. <span class="optional">optional</span></label>
                <input type="text" name="godown_register_no[]" class="sz-input"
                       maxlength="100"
                       placeholder="Register number">
            </div>
        </div>

        <div class="sz-field" style="margin-top: 12px; margin-bottom: 0;">
            <label class="sz-label">Seizure location <span class="optional">optional</span></label>
            <input type="text" name="seizure_location[]" class="sz-input"
                   maxlength="255"
                   placeholder="Where exactly the item was seized">
        </div>
    </div>
</template>

<script>
/* ================================================================
   DigiShulk — Seizure Report wizard
   ================================================================ */
(function () {
    'use strict';

    var EXISTING_ITEMS = <?= $existing_items_json ?: '[]' ?>;
    var DRAFT_KEY = 'digishulk_seizure_draft_v1';
    var DRAFT_MAX_AGE_MS = 6 * 60 * 60 * 1000; // 6 hours

    var form            = document.getElementById('seizureForm');
    var panels          = document.querySelectorAll('.sz-panel');
    var stepDots        = document.querySelectorAll('.sz-step');
    var progressFill    = document.getElementById('szProgressFill');
    var progressText    = document.getElementById('szProgressText');
    var progressPct     = document.getElementById('szProgressPercent');
    var stepsBar        = document.getElementById('szSteps');
    var container       = document.getElementById('szItemsContainer');
    var template        = document.getElementById('szItemTemplate');
    var addBtn          = document.getElementById('szAddItem');
    var countBadge      = document.getElementById('szItemsCountText');
    var reviewCard      = document.getElementById('szReviewCard');
    var submitBtn       = document.getElementById('szSubmitBtn');

    var currentStep = 1;
    var restoredFromDraft = false;

    /* ============================================================
       1. Step navigation
       ============================================================ */
    function goToStep(target, direction) {
        if (target === currentStep || target < 1 || target > 3) return;

        var currentPanel = document.querySelector('.sz-panel[data-step="' + currentStep + '"]');
        var nextPanel    = document.querySelector('.sz-panel[data-step="' + target + '"]');

        currentPanel.classList.remove('sz-slide-r', 'sz-slide-l');
        currentPanel.classList.add(direction === 'forward' ? 'sz-slide-l' : 'sz-slide-r');

        setTimeout(function () {
            currentPanel.classList.remove('active', 'sz-slide-l', 'sz-slide-r');
            nextPanel.classList.add('active');
            nextPanel.classList.remove('sz-slide-l', 'sz-slide-r');
            void nextPanel.offsetWidth;
            nextPanel.classList.add(direction === 'forward' ? 'sz-slide-r' : 'sz-slide-l');
        }, 200);

        currentStep = target;

        stepDots.forEach(function (dot) {
            var n = parseInt(dot.getAttribute('data-step'), 10);
            dot.classList.toggle('active', n === currentStep);
            dot.classList.toggle('done', n < currentStep);
        });

        var pct = Math.round((currentStep / 3) * 100);
        if (progressFill) progressFill.style.width = pct + '%';
        if (progressPct)  progressPct.textContent = pct + '%';
        if (progressText) progressText.textContent = 'Step ' + currentStep + ' of 3';
        if (stepsBar)     stepsBar.style.setProperty('--step', currentStep);

        if (target !== 3) {
            var firstInput = nextPanel.querySelector('input:not([type=hidden]), select, textarea');
            if (firstInput) setTimeout(function () { firstInput.focus(); }, 260);
        }

        var top = form.getBoundingClientRect().top + window.scrollY - 20;
        window.scrollTo({ top: top, behavior: 'smooth' });

        if (target === 3) renderReview();
        saveDraft();
    }

    /* ============================================================
       2. Validation
       ============================================================ */
    function clearErrors(panel) {
        panel.querySelectorAll('.sz-error.dynamic').forEach(function (el) { el.remove(); });
        panel.querySelectorAll('.error').forEach(function (el) { el.classList.remove('error'); });
    }

    function showError(field, message) {
        field.classList.add('error');
        var wrap = field.closest('.sz-field') || field.parentNode;
        var p = document.createElement('p');
        p.className = 'sz-error dynamic';
        p.innerHTML = '<i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i> ' + message;
        wrap.appendChild(p);
    }

    function validateStep(step) {
        var panel = document.querySelector('.sz-panel[data-step="' + step + '"]');
        clearErrors(panel);
        var valid = true;

        if (step === 1) {
            var leader = document.getElementById('team_leader_name');
            var zone   = document.getElementById('zone');
            var team   = document.getElementById('team_number');
            var date   = document.getElementById('seizure_date');

            if (!leader.value.trim()) { showError(leader, 'Team leader is required'); valid = false; }
            if (!zone.value)          { showError(zone,   'Select a zone'); valid = false; }
            if (!team.value.trim())   { showError(team,   'Team number is required'); valid = false; }
            if (!date.value)          { showError(date,   'Date is required'); valid = false; }
        }

        if (step === 2) {
            var rows = container.querySelectorAll('.sz-item-row');
            if (rows.length === 0) {
                valid = false;
                showSectionError(panel, 'Add at least one item');
            } else {
                var anyFilled = false;
                rows.forEach(function (row) {
                    var detail = row.querySelector('input[name="item_details[]"]');
                    var qty    = row.querySelector('input[name="quantity_seized[]"]');
                    if (detail.value.trim()) {
                        anyFilled = true;
                        if (!qty.value || parseInt(qty.value, 10) < 1) {
                            showError(qty, 'Quantity must be at least 1');
                            valid = false;
                        }
                    }
                });
                if (!anyFilled) {
                    valid = false;
                    showSectionError(panel, 'Fill in the details of at least one item');
                }
            }
        }

        if (!valid) {
            var firstErr = panel.querySelector('.error');
            if (firstErr) firstErr.focus();
        }
        return valid;
    }

    function showSectionError(panel, message) {
        var head = panel.querySelector('.sz-panel-head');
        if (head) {
            var p = document.createElement('p');
            p.className = 'sz-error dynamic';
            p.style.justifyContent = 'center';
            p.innerHTML = '<i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i> ' + message;
            head.appendChild(p);
        }
    }

    /* ============================================================
       3. Next/back wiring
       ============================================================ */
    document.querySelectorAll('[data-next]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var next = parseInt(btn.getAttribute('data-next'), 10);
            if (validateStep(currentStep)) goToStep(next, 'forward');
        });
    });
    document.querySelectorAll('[data-back]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var back = parseInt(btn.getAttribute('data-back'), 10);
            goToStep(back, 'back');
        });
    });

    /* ============================================================
       4. Enter key advances (except in textareas)
       ============================================================ */
    form.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && currentStep < 3) {
            if (e.target.tagName === 'TEXTAREA') return;
            e.preventDefault();
            var nextBtn = document.querySelector('.sz-panel[data-step="' + currentStep + '"] [data-next]');
            if (nextBtn) nextBtn.click();
        }
    });

    /* ============================================================
       5. Items — add, remove, duplicate, re-index
       ============================================================ */
    function reindexItems() {
        var rows = container.querySelectorAll('.sz-item-row');
        rows.forEach(function (row, i) {
            row.querySelector('.sz-item-num-badge').textContent = '#' + (i + 1);
            row.querySelector('.sz-item-num span:last-child').textContent = 'Item ' + (i + 1);
        });
        var n = rows.length;
        if (countBadge) countBadge.textContent = n + ' item' + (n === 1 ? '' : 's');
        updateSummary();
    }

    function addItemRow(prefill) {
        prefill = prefill || {};
        var node = template.content.cloneNode(true);
        var row  = node.querySelector('.sz-item-row');

        if (prefill.item_details)        row.querySelector('input[name="item_details[]"]').value = prefill.item_details;
        if (prefill.godown_register_no)  row.querySelector('input[name="godown_register_no[]"]').value = prefill.godown_register_no;
        if (prefill.quantity_seized)     row.querySelector('input[name="quantity_seized[]"]').value = prefill.quantity_seized;
        if (prefill.estimated_value)     row.querySelector('input[name="estimated_value[]"]').value = prefill.estimated_value;
        if (prefill.owner_merchant_name) row.querySelector('input[name="owner_merchant_name[]"]').value = prefill.owner_merchant_name;
        if (prefill.seizure_location)    row.querySelector('input[name="seizure_location[]"]').value = prefill.seizure_location;
        if (prefill.item_category) {
            var cs = row.querySelector('select[name="item_category[]"]');
            if (cs) cs.value = prefill.item_category;
        }
        if (prefill.condition_status) {
            var cond = row.querySelector('select[name="condition_status[]"]');
            if (cond) cond.value = prefill.condition_status;
        }

        // Wire remove
        row.querySelector('.sz-remove').addEventListener('click', function () {
            var rows = container.querySelectorAll('.sz-item-row').length;
            if (rows === 1) {
                if (!confirm('This is the only item. Remove it anyway?')) return;
            }
            row.remove();
            reindexItems();
            saveDraft();
        });

        // Wire duplicate
        row.querySelector('.sz-duplicate').addEventListener('click', function () {
            var data = readRow(row);
            addItemRow(data);
            var newRow = container.lastElementChild;
            if (newRow) {
                var input = newRow.querySelector('input[name="item_details[]"]');
                if (input) input.focus();
                // Scroll new row into view
                newRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            saveDraft();
        });

        // Track changes for draft + summary
        row.querySelectorAll('input, select').forEach(function (el) {
            el.addEventListener('input', function () { updateSummary(); saveDraft(); });
            el.addEventListener('change', function () { updateSummary(); saveDraft(); });
        });

        container.appendChild(node);
        reindexItems();
    }

    function readRow(row) {
        return {
            item_details:        (row.querySelector('input[name="item_details[]"]')        || {}).value || '',
            godown_register_no:  (row.querySelector('input[name="godown_register_no[]"]')  || {}).value || '',
            quantity_seized:     (row.querySelector('input[name="quantity_seized[]"]')     || {}).value || '',
            estimated_value:     (row.querySelector('input[name="estimated_value[]"]')     || {}).value || '',
            owner_merchant_name: (row.querySelector('input[name="owner_merchant_name[]"]') || {}).value || '',
            seizure_location:    (row.querySelector('input[name="seizure_location[]"]')    || {}).value || '',
            item_category:       (row.querySelector('select[name="item_category[]"]')      || {}).value || '',
            condition_status:    (row.querySelector('select[name="condition_status[]"]')   || {}).value || 'good'
        };
    }

    if (addBtn) {
        addBtn.addEventListener('click', function () {
            addItemRow();
            var newRow = container.lastElementChild;
            if (newRow) {
                var input = newRow.querySelector('input[name="item_details[]"]');
                if (input) input.focus();
                newRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    }

    // Quick-add chips pre-fill item_details
    document.querySelectorAll('.sz-quick-chip').forEach(function (chip) {
        chip.addEventListener('click', function () {
            var value = chip.getAttribute('data-quick');
            addItemRow({ item_details: value, quantity_seized: '1' });
            var newRow = container.lastElementChild;
            if (newRow) newRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    });

    /* ============================================================
       6. Live summary (right sidebar)
       ============================================================ */
    function setSum(id, val) {
        var el = document.getElementById(id);
        if (!el) return;
        var v = (val === undefined || val === null || val === '') ? '—' : String(val);
        if (el.textContent !== v) {
            el.textContent = v;
            el.classList.toggle('empty', v === '—');
            el.classList.remove('flash');
            void el.offsetWidth;
            el.classList.add('flash');
        }
    }

    function updateSummary() {
        setSum('szSumLeader', document.getElementById('team_leader_name').value.trim());
        setSum('szSumZone',   document.getElementById('zone').value);
        setSum('szSumTeam',   document.getElementById('team_number').value.trim());

        var d = document.getElementById('seizure_date').value;
        if (d) {
            var dt = new Date(d + 'T00:00:00');
            setSum('szSumDate', dt.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }));
        } else {
            setSum('szSumDate', '');
        }

        var rows = container.querySelectorAll('.sz-item-row');
        var totalQty   = 0;
        var totalValue = 0;
        rows.forEach(function (row) {
            var detail = (row.querySelector('input[name="item_details[]"]') || {}).value || '';
            var qty    = parseInt((row.querySelector('input[name="quantity_seized[]"]') || {}).value, 10) || 0;
            var val    = parseFloat((row.querySelector('input[name="estimated_value[]"]') || {}).value) || 0;
            if (detail.trim()) {
                totalQty += qty;
                totalValue += val;
            }
        });

        var sumItems = document.getElementById('szSumItems');
        var sumValue = document.getElementById('szSumValue');
        if (sumItems) sumItems.textContent = totalQty;
        if (sumValue) {
            sumValue.textContent = '₹' + totalValue.toLocaleString('en-IN', {
                maximumFractionDigits: 2,
                minimumFractionDigits: 0
            });
        }
    }

    /* ============================================================
       7. Review step
       ============================================================ */
    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function renderReview() {
        if (!reviewCard) return;

        var leader = document.getElementById('team_leader_name').value.trim() || '—';
        var zone   = document.getElementById('zone').value || '—';
        var team   = document.getElementById('team_number').value.trim() || '—';
        var date   = document.getElementById('seizure_date').value || '—';
        var opLoc  = document.getElementById('operation_location').value.trim();
        var notes  = document.getElementById('session_notes').value.trim();

        var sessionHtml =
            '<div class="sz-review-section">' +
                '<div class="sz-review-title"><i class="fa-solid fa-clipboard-list" aria-hidden="true"></i> Session</div>' +
                '<div class="sz-review-grid">' +
                    '<div class="sz-review-item"><div class="sz-review-item-label">Team leader</div><div class="sz-review-item-val">' + escapeHtml(leader) + '</div></div>' +
                    '<div class="sz-review-item"><div class="sz-review-item-label">Zone</div><div class="sz-review-item-val">' + escapeHtml(zone) + '</div></div>' +
                    '<div class="sz-review-item"><div class="sz-review-item-label">Team</div><div class="sz-review-item-val">' + escapeHtml(team) + '</div></div>' +
                    '<div class="sz-review-item"><div class="sz-review-item-label">Date</div><div class="sz-review-item-val">' + escapeHtml(date) + '</div></div>' +
                '</div>' +
                (opLoc || notes ? '<div style="margin-top: 10px; font-size:0.85rem; color: var(--color-text-muted);">' +
                    (opLoc ? '<div><strong style="color:var(--color-text);">Location:</strong> ' + escapeHtml(opLoc) + '</div>' : '') +
                    (notes ? '<div style="margin-top:4px;"><strong style="color:var(--color-text);">Notes:</strong> ' + escapeHtml(notes) + '</div>' : '') +
                '</div>' : '') +
            '</div>';

        var rows = container.querySelectorAll('.sz-item-row');
        var itemCount = 0;
        var totalQty = 0;
        var totalValue = 0;
        var itemsHtml = '';

        rows.forEach(function (row) {
            var data = readRow(row);
            if (!data.item_details.trim()) return;
            itemCount++;
            var qty = parseInt(data.quantity_seized, 10) || 0;
            var val = parseFloat(data.estimated_value) || 0;
            totalQty   += qty;
            totalValue += val;

            itemsHtml +=
                '<div class="sz-review-item-card">' +
                    '<div class="sz-review-item-card-head">' +
                        '<span class="sz-review-item-card-name">' + escapeHtml(data.item_details) + '</span>' +
                        '<span class="sz-review-item-card-qty">' + qty + ' unit' + (qty === 1 ? '' : 's') + '</span>' +
                    '</div>' +
                    '<div class="sz-review-item-card-meta">' +
                        (data.item_category   ? '<div>Category: <strong>' + escapeHtml(data.item_category) + '</strong></div>' : '') +
                        (data.condition_status ? '<div>Condition: <strong>' + escapeHtml(data.condition_status) + '</strong></div>' : '') +
                        (data.owner_merchant_name ? '<div>Owner: <strong>' + escapeHtml(data.owner_merchant_name) + '</strong></div>' : '') +
                        (data.godown_register_no ? '<div>Godown: <strong>' + escapeHtml(data.godown_register_no) + '</strong></div>' : '') +
                        (data.seizure_location ? '<div>Location: <strong>' + escapeHtml(data.seizure_location) + '</strong></div>' : '') +
                        (val > 0 ? '<div>Value: <strong>₹' + val.toLocaleString('en-IN') + '</strong></div>' : '') +
                    '</div>' +
                '</div>';
        });

        var itemsSection =
            '<div class="sz-review-section">' +
                '<div class="sz-review-title">' +
                    '<i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i> ' +
                    itemCount + ' item' + (itemCount === 1 ? '' : 's') +
                    ' · ' + totalQty + ' unit' + (totalQty === 1 ? '' : 's') +
                    (totalValue > 0 ? ' · ₹' + totalValue.toLocaleString('en-IN') + ' est.' : '') +
                '</div>' +
                (itemsHtml
                    ? '<div class="sz-review-items-list">' + itemsHtml + '</div>'
                    : '<div class="sz-review-empty"><i class="fa-solid fa-inbox" style="font-size:1.6rem; opacity:0.5;" aria-hidden="true"></i><p style="margin:8px 0 0;">No items added</p></div>') +
            '</div>';

        reviewCard.innerHTML = sessionHtml + itemsSection;
    }

    /* ============================================================
       8. Draft save/restore
       ============================================================ */
    function getSessionFields() {
        return {
            team_leader_name:   document.getElementById('team_leader_name').value,
            zone:               document.getElementById('zone').value,
            team_number:        document.getElementById('team_number').value,
            seizure_date:       document.getElementById('seizure_date').value,
            operation_location: document.getElementById('operation_location').value,
            session_notes:      document.getElementById('session_notes').value
        };
    }

    function getItems() {
        var rows = container.querySelectorAll('.sz-item-row');
        var out = [];
        rows.forEach(function (row) { out.push(readRow(row)); });
        return out;
    }

    function saveDraft() {
        try {
            var state = { session: getSessionFields(), items: getItems(), ts: Date.now() };
            localStorage.setItem(DRAFT_KEY, JSON.stringify(state));
        } catch (e) {}
    }

    function loadDraft() {
        try {
            var raw = localStorage.getItem(DRAFT_KEY);
            if (!raw) return null;
            var d = JSON.parse(raw);
            if (!d || !d.ts || (Date.now() - d.ts) > DRAFT_MAX_AGE_MS) return null;
            return d;
        } catch (e) { return null; }
    }

    function clearDraft() {
        try { localStorage.removeItem(DRAFT_KEY); } catch (e) {}
    }

    function applyDraft(d) {
        if (d.session) {
            if (d.session.team_leader_name)   document.getElementById('team_leader_name').value   = d.session.team_leader_name;
            if (d.session.zone)               document.getElementById('zone').value               = d.session.zone;
            if (d.session.team_number)        document.getElementById('team_number').value        = d.session.team_number;
            if (d.session.seizure_date)       document.getElementById('seizure_date').value       = d.session.seizure_date;
            if (d.session.operation_location) document.getElementById('operation_location').value = d.session.operation_location;
            if (d.session.session_notes)      document.getElementById('session_notes').value      = d.session.session_notes;
        }
        if (Array.isArray(d.items) && d.items.length) {
            // Wipe default empty row before restoring
            container.innerHTML = '';
            d.items.forEach(function (item) { addItemRow(item); });
        }
        restoredFromDraft = true;
    }

    /* ============================================================
       9. Initial items — from server-side errors, then draft
       ============================================================ */
    function bootstrapItems() {
        container.innerHTML = '';

        if (Array.isArray(EXISTING_ITEMS) && EXISTING_ITEMS.length > 0) {
            EXISTING_ITEMS.forEach(function (item) { addItemRow(item); });
            return;
        }

        var draft = loadDraft();
        if (draft) {
            applyDraft(draft);
            // Don't show banner unless we actually restored something
            if (!container.querySelector('.sz-item-row')) {
                addItemRow();
            }
            return;
        }

        // Default: one empty row
        addItemRow();
    }

    /* ============================================================
       10. Wire up session-field listeners
       ============================================================ */
    ['team_leader_name', 'zone', 'team_number', 'seizure_date',
     'operation_location', 'session_notes'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', function () { updateSummary(); saveDraft(); });
            el.addEventListener('change', function () { updateSummary(); saveDraft(); });
        }
    });

    /* ============================================================
       11. Submit — validate both steps, confetti, submit
       ============================================================ */
    form.addEventListener('submit', function (e) {
        // Re-validate step 1 and step 2 in case user jumped back/forward
        var ok1 = validateStep(1);
        var ok2 = validateStep(2);

        if (!ok1 || !ok2) {
            e.preventDefault();
            goToStep(ok1 ? 2 : 1, 'back');
            return;
        }

        // Confetti + delay
        var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (!reduced && submitBtn) {
            var colors = ['#3b82f6', '#22c55e', '#a855f7', '#f59e0b', '#ef4444'];
            var rect = submitBtn.getBoundingClientRect();
            var cx = rect.left + rect.width / 2;
            var cy = rect.top + rect.height / 2;
            for (var i = 0; i < 24; i++) {
                (function () {
                    var piece = document.createElement('span');
                    piece.className = 'sz-confetti-piece';
                    var angle = Math.random() * Math.PI * 2;
                    var dist  = 60 + Math.random() * 130;
                    piece.style.background = colors[Math.floor(Math.random() * colors.length)];
                    piece.style.left = cx + 'px';
                    piece.style.top  = cy + 'px';
                    piece.style.setProperty('--dx',  Math.cos(angle) * dist + 'px');
                    piece.style.setProperty('--dy',  Math.sin(angle) * dist + 100 + 'px');
                    piece.style.setProperty('--rot', (Math.random() * 720 - 360) + 'deg');
                    document.body.appendChild(piece);
                    setTimeout(function () { piece.remove(); }, 1200);
                })();
            }
            e.preventDefault();
            submitBtn.disabled = true;
            var label = submitBtn.querySelector('span');
            if (label) label.textContent = 'Saving…';
            setTimeout(function () {
                clearDraft();
                form.submit();
            }, 500);
        } else {
            clearDraft();
        }
    });

    /* ============================================================
       12. Init
       ============================================================ */
    bootstrapItems();
    updateSummary();

})();
</script>

<?php include 'footer.php'; ?>