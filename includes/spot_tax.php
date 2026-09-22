<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'inspector') {
    header('Location: logout.php');
    exit();
}

$form_errors = isset($_SESSION['form_errors']) ? $_SESSION['form_errors'] : [];
$form_data   = isset($_SESSION['form_data'])   ? $_SESSION['form_data']   : [];
unset($_SESSION['form_errors'], $_SESSION['form_data']);

include 'db_connect.php';
require_once 'helpers/rates.php';
require_once 'helpers/csrf.php';

$ratesMap = get_all_rates($conn);

// Common zones — edit to match your municipality's zones.
$zones = ['Central', 'East', 'West', 'North', 'South', 'Other'];

include 'header.php';
?>

<style>
/* ================================================================
   DigiShulk — New Spot Tax wizard
   Scope: .spot-page. Self-contained; no dependency on style2.css
   beyond theme CSS variables.
   ================================================================ */

.spot-page {
    --spot-ease: cubic-bezier(0.16, 1, 0.3, 1);
    --spot-accent: #3b82f6;
    --spot-accent-2: #6366f1;
    --spot-accent-soft: rgba(59, 130, 246, 0.14);
    --spot-success: #22c55e;
    --spot-danger: #ef4444;
}

/* ---------- Layout ---------- */
.spot-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 320px;
    gap: 22px;
    align-items: start;
    max-width: 1180px;
    margin: 0 auto;
}
@media (max-width: 900px) {
    .spot-grid { grid-template-columns: 1fr; }
}

/* ---------- Animations ---------- */
@keyframes spotSlideR {
    from { opacity: 0; transform: translateX(26px); }
    to   { opacity: 1; transform: translateX(0); }
}
@keyframes spotSlideL {
    from { opacity: 0; transform: translateX(-26px); }
    to   { opacity: 1; transform: translateX(0); }
}
@keyframes spotPop {
    0%   { opacity: 0; transform: scale(0.85); }
    65%  { opacity: 1; transform: scale(1.05); }
    100% { opacity: 1; transform: scale(1); }
}
@keyframes spotRise {
    from { opacity: 0; transform: translateY(12px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes spotPulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.5); }
    70%      { box-shadow: 0 0 0 12px rgba(59, 130, 246, 0); }
}
@keyframes spotShimmer {
    0%   { background-position: -200% 0; }
    100% { background-position:  200% 0; }
}
@keyframes spotConfetti {
    0%   { opacity: 1; transform: translate(0,0) rotate(0deg); }
    100% { opacity: 0; transform: translate(var(--dx), var(--dy)) rotate(var(--rot)); }
}
@keyframes spotBounceIn {
    0%   { opacity: 0; transform: scale(0.4); }
    60%  { opacity: 1; transform: scale(1.12); }
    100% { opacity: 1; transform: scale(1); }
}

.spot-slide-r { animation: spotSlideR 0.4s var(--spot-ease) both; }
.spot-slide-l { animation: spotSlideL 0.4s var(--spot-ease) both; }
.spot-rise    { opacity: 0; animation: spotRise 0.5s var(--spot-ease) forwards; animation-delay: var(--d, 0ms); }
.spot-pop     { animation: spotPop 0.35s var(--spot-ease) both; }

@media (prefers-reduced-motion: reduce) {
    .spot-slide-r, .spot-slide-l, .spot-rise, .spot-pop, .step-transition {
        animation: none !important;
        opacity: 1 !important;
        transform: none !important;
    }
}

/* ---------- Progress bar ---------- */
.spot-progress {
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
.spot-progress-fill {
    height: 100%;
    width: 33.33%;
    background: linear-gradient(90deg, var(--spot-accent), var(--spot-accent-2));
    border-radius: 999px;
    transition: width 0.5s var(--spot-ease);
    position: relative;
}
.spot-progress-fill::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(110deg, transparent 30%, rgba(255,255,255,0.35) 45%, transparent 60%);
    background-size: 200% 100%;
    animation: spotShimmer 2.4s linear infinite;
}
@media (prefers-reduced-motion: reduce) {
    .spot-progress-fill::after { animation: none; }
}

.spot-progress-label {
    display: flex;
    justify-content: space-between;
    font-size: 0.72rem;
    color: var(--color-text-muted);
    font-weight: 600;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    margin: -14px 0 20px;
}

/* ---------- Step indicators ---------- */
.spot-steps {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 28px;
    position: relative;
}
.spot-steps::before {
    content: '';
    position: absolute;
    left: 20px;
    right: 20px;
    top: 22px;
    height: 2px;
    background: var(--color-border);
    z-index: 0;
}
.spot-steps::after {
    content: '';
    position: absolute;
    left: 20px;
    top: 22px;
    height: 2px;
    background: linear-gradient(90deg, var(--spot-accent), var(--spot-accent-2));
    z-index: 0;
    transition: width 0.5s var(--spot-ease);
    width: calc((var(--step, 1) - 1) / 2 * (100% - 40px));
}

.spot-step {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    position: relative;
    z-index: 1;
    text-align: center;
}
.spot-step-circle {
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
    transition: all 0.35s var(--spot-ease);
    position: relative;
}
.spot-step.active .spot-step-circle {
    background: linear-gradient(135deg, var(--spot-accent), var(--spot-accent-2));
    color: #fff;
    border-color: transparent;
    transform: scale(1.08);
    box-shadow: 0 10px 24px -10px rgba(59, 130, 246, 0.7);
}
.spot-step.done .spot-step-circle {
    background: var(--spot-success);
    color: #fff;
    border-color: transparent;
}
.spot-step.done .spot-step-circle::after {
    content: '\f00c'; /* fa-check */
    font-family: 'Font Awesome 6 Free';
    font-weight: 900;
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: spotBounceIn 0.4s var(--spot-ease) both;
}
.spot-step.done .spot-step-circle span { display: none; }

.spot-step-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--color-text-muted);
    transition: color 0.3s var(--spot-ease);
}
.spot-step.active .spot-step-label { color: var(--color-text); }
.spot-step.done .spot-step-label { color: var(--spot-success); }

/* ---------- Step content ---------- */
.spot-form-body { position: relative; }
.spot-step-panel { display: none; }
.spot-step-panel.active { display: block; }

.spot-step-head {
    text-align: center;
    margin-bottom: 24px;
}
.spot-step-title {
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
.spot-step-subtitle {
    color: var(--color-text-muted);
    font-size: 0.9rem;
    margin: 0;
}

/* ---------- Fields ---------- */
.spot-field { margin-bottom: 18px; }
.spot-label {
    display: block;
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--color-text);
    margin-bottom: 8px;
}
.spot-label .optional {
    color: var(--color-text-muted);
    font-weight: 400;
    font-size: 0.72rem;
    margin-left: 6px;
}
.spot-input,
.spot-select,
.spot-textarea {
    width: 100%;
    padding: 13px 15px;
    background: var(--color-surface-muted);
    border: 1px solid var(--color-border);
    border-radius: 12px;
    color: var(--color-text);
    font-family: inherit;
    font-size: 0.95rem;
    transition: border-color 0.2s var(--spot-ease), box-shadow 0.2s var(--spot-ease), background 0.2s var(--spot-ease);
    box-sizing: border-box;
}
.spot-input:focus,
.spot-select:focus,
.spot-textarea:focus {
    outline: none;
    border-color: var(--spot-accent);
    background: var(--color-surface);
    box-shadow: 0 0 0 4px var(--spot-accent-soft);
}
.spot-input.error,
.spot-select.error,
.spot-textarea.error {
    border-color: var(--spot-danger);
    background: rgba(239, 68, 68, 0.06);
}
.spot-help {
    font-size: 0.78rem;
    color: var(--color-text-muted);
    margin-top: 6px;
}
.spot-error {
    font-size: 0.78rem;
    color: #fca5a5;
    margin-top: 6px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.spot-textarea { resize: vertical; min-height: 80px; line-height: 1.5; }

/* ---------- Field row ---------- */
.spot-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}
@media (max-width: 520px) {
    .spot-row { grid-template-columns: 1fr; }
}

/* ---------- Phone wrapper with country prefix ---------- */
.spot-phone-wrap {
    display: flex;
    align-items: stretch;
    background: var(--color-surface-muted);
    border: 1px solid var(--color-border);
    border-radius: 12px;
    overflow: hidden;
    transition: border-color 0.2s var(--spot-ease), box-shadow 0.2s var(--spot-ease);
}
.spot-phone-wrap:focus-within {
    border-color: var(--spot-accent);
    box-shadow: 0 0 0 4px var(--spot-accent-soft);
}
.spot-phone-prefix {
    display: flex;
    align-items: center;
    padding: 0 12px;
    background: rgba(255,255,255,0.03);
    border-right: 1px solid var(--color-border);
    color: var(--color-text-muted);
    font-weight: 600;
    font-size: 0.9rem;
    flex-shrink: 0;
}
.spot-phone-wrap input {
    flex: 1;
    border: none;
    background: transparent;
    padding: 13px 15px;
    color: var(--color-text);
    font-family: inherit;
    font-size: 0.95rem;
    letter-spacing: 0.03em;
}
.spot-phone-wrap input:focus { outline: none; }

/* ---------- GPS button ---------- */
.spot-gps-row {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 8px;
}
.spot-gps-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 14px;
    border-radius: 10px;
    background: var(--spot-accent-soft);
    color: #93c5fd;
    border: 1px solid rgba(59, 130, 246, 0.35);
    font-weight: 600;
    font-size: 0.82rem;
    cursor: pointer;
    font-family: inherit;
    transition: all 0.2s var(--spot-ease);
}
.spot-gps-btn:hover {
    background: rgba(59, 130, 246, 0.24);
    transform: translateY(-1px);
}
.spot-gps-btn:disabled {
    opacity: 0.6;
    cursor: wait;
}
.spot-gps-status {
    font-size: 0.78rem;
    color: var(--color-text-muted);
}
.spot-gps-status.ok { color: var(--spot-success); }
.spot-gps-status.err { color: #fca5a5; }

/* ---------- Size presets ---------- */
.spot-presets {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 10px;
}
.spot-preset {
    padding: 8px 14px;
    border-radius: 999px;
    background: var(--color-surface-muted);
    border: 1px solid var(--color-border);
    color: var(--color-text-muted);
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
    transition: all 0.2s var(--spot-ease);
}
.spot-preset:hover {
    border-color: var(--spot-accent);
    color: var(--color-text);
    transform: translateY(-1px);
}
.spot-preset.active {
    background: var(--spot-accent-soft);
    border-color: var(--spot-accent);
    color: #93c5fd;
}

/* ---------- Payment mode cards ---------- */
.spot-pay-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}
.spot-pay-card {
    padding: 20px;
    border-radius: 16px;
    background: var(--color-surface-muted);
    border: 2px solid var(--color-border);
    cursor: pointer;
    text-align: center;
    transition: all 0.25s var(--spot-ease);
    position: relative;
    overflow: hidden;
}
.spot-pay-card:hover {
    border-color: rgba(59, 130, 246, 0.5);
    transform: translateY(-2px);
}
.spot-pay-card.active {
    border-color: var(--spot-accent);
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.10), rgba(99, 102, 241, 0.08));
    box-shadow: 0 12px 30px -16px rgba(59, 130, 246, 0.55);
}
.spot-pay-card.active::after {
    content: '\f00c';
    font-family: 'Font Awesome 6 Free';
    font-weight: 900;
    position: absolute;
    top: 10px;
    right: 10px;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: var(--spot-success);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    animation: spotBounceIn 0.35s var(--spot-ease) both;
}
.spot-pay-icon {
    font-size: 1.6rem;
    margin-bottom: 8px;
    color: #93c5fd;
    transition: transform 0.25s var(--spot-ease);
}
.spot-pay-card:hover .spot-pay-icon { transform: scale(1.15); }
.spot-pay-label {
    font-weight: 700;
    font-size: 0.95rem;
    color: var(--color-text);
}
.spot-pay-desc {
    font-size: 0.72rem;
    color: var(--color-text-muted);
    margin-top: 4px;
}
.spot-pay-card.active .spot-pay-icon { color: #60a5fa; }
.spot-pay-card input { display: none; }

/* ---------- Amount suggestion chip ---------- */
.spot-suggest {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 10px;
    padding: 12px 14px;
    border-radius: 12px;
    background: linear-gradient(135deg, rgba(34, 197, 94, 0.10), rgba(59, 130, 246, 0.08));
    border: 1px solid rgba(34, 197, 94, 0.30);
    animation: spotRise 0.4s var(--spot-ease) both;
}
.spot-suggest-icon {
    width: 32px;
    height: 32px;
    border-radius: 9px;
    background: rgba(34, 197, 94, 0.16);
    color: #4ade80;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.spot-suggest-text {
    flex: 1;
    font-size: 0.82rem;
    color: var(--color-text);
    line-height: 1.4;
}
.spot-suggest-text strong { color: #4ade80; }
.spot-suggest-apply {
    padding: 6px 12px;
    border-radius: 8px;
    background: var(--spot-success);
    color: #fff;
    border: none;
    font-weight: 700;
    font-size: 0.78rem;
    cursor: pointer;
    font-family: inherit;
    transition: transform 0.2s var(--spot-ease);
    white-space: nowrap;
}
.spot-suggest-apply:hover { transform: translateY(-1px); }

/* ---------- Amount input with +/- steppers ---------- */
.spot-amount-wrap {
    display: flex;
    align-items: stretch;
    background: var(--color-surface-muted);
    border: 1px solid var(--color-border);
    border-radius: 12px;
    overflow: hidden;
    transition: border-color 0.2s var(--spot-ease), box-shadow 0.2s var(--spot-ease);
}
.spot-amount-wrap:focus-within {
    border-color: var(--spot-accent);
    box-shadow: 0 0 0 4px var(--spot-accent-soft);
}
.spot-amount-prefix {
    display: flex;
    align-items: center;
    padding: 0 14px;
    background: rgba(255,255,255,0.03);
    border-right: 1px solid var(--color-border);
    color: var(--color-text-muted);
    font-weight: 700;
    font-size: 1rem;
    flex-shrink: 0;
}
.spot-amount-wrap input {
    flex: 1;
    border: none;
    background: transparent;
    padding: 13px 15px;
    color: var(--color-text);
    font-family: inherit;
    font-size: 1.05rem;
    font-weight: 600;
    letter-spacing: -0.01em;
}
.spot-amount-wrap input:focus { outline: none; }
.spot-amount-step {
    width: 42px;
    border: none;
    background: rgba(255,255,255,0.03);
    border-left: 1px solid var(--color-border);
    color: var(--color-text-muted);
    font-size: 1.1rem;
    cursor: pointer;
    transition: background 0.15s var(--spot-ease);
}
.spot-amount-step:hover { background: rgba(255,255,255,0.07); color: var(--color-text); }

/* ---------- Summary card ---------- */
.spot-summary {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 18px;
    padding: 22px;
    position: sticky;
    top: 20px;
    overflow: hidden;
}
.spot-summary::before {
    content: '';
    position: absolute;
    inset: -40% -40% auto auto;
    width: 200px;
    height: 200px;
    background: radial-gradient(circle, rgba(59, 130, 246, 0.18), transparent 65%);
    pointer-events: none;
    filter: blur(6px);
}
.spot-summary-head {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 18px;
    position: relative;
}
.spot-summary-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: var(--spot-accent-soft);
    color: #93c5fd;
    display: flex;
    align-items: center;
    justify-content: center;
}
.spot-summary-title {
    font-size: 0.95rem;
    font-weight: 700;
    margin: 0;
    color: var(--color-text);
}
.spot-summary-sub {
    font-size: 0.72rem;
    color: var(--color-text-muted);
    margin: 0;
}
.spot-summary-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
    position: relative;
}
.spot-summary-item {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    font-size: 0.84rem;
    padding-bottom: 12px;
    border-bottom: 1px dashed var(--color-border);
}
.spot-summary-item:last-child { border-bottom: none; padding-bottom: 0; }
.spot-summary-key {
    color: var(--color-text-muted);
    flex-shrink: 0;
}
.spot-summary-val {
    color: var(--color-text);
    font-weight: 600;
    text-align: right;
    word-break: break-word;
    transition: color 0.3s var(--spot-ease);
}
.spot-summary-val.empty { color: var(--color-text-muted); font-weight: 400; font-style: italic; }
.spot-summary-val.flash { animation: spotPop 0.5s var(--spot-ease); color: var(--spot-accent); }

.spot-summary-total {
    margin-top: 18px;
    padding-top: 16px;
    border-top: 1px solid var(--color-border);
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    position: relative;
}
.spot-summary-total-label {
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--color-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.spot-summary-total-amount {
    font-size: 1.6rem;
    font-weight: 800;
    color: var(--spot-success);
    letter-spacing: -0.02em;
    font-variant-numeric: tabular-nums;
    transition: transform 0.3s var(--spot-ease);
}
.spot-summary-total-amount.flash { animation: spotPop 0.5s var(--spot-ease); }

/* ---------- Mobile summary toggle ---------- */
.spot-summary-mobile-toggle {
    display: none;
    position: sticky;
    bottom: 12px;
    z-index: 8;
    margin-top: 16px;
    padding: 14px 18px;
    border-radius: 14px;
    background: linear-gradient(135deg, var(--spot-accent), var(--spot-accent-2));
    color: #fff;
    border: none;
    font-family: inherit;
    font-weight: 700;
    font-size: 0.9rem;
    cursor: pointer;
    box-shadow: 0 16px 34px -14px rgba(59, 130, 246, 0.7);
    width: 100%;
    display: none;
    align-items: center;
    justify-content: space-between;
}
.spot-summary-mobile-toggle .label { display: flex; align-items: center; gap: 8px; }
.spot-summary-mobile-toggle .amount { font-size: 1rem; }
@media (max-width: 900px) {
    .spot-summary { display: none; position: static; margin-top: 16px; }
    .spot-summary.open { display: block; animation: spotRise 0.35s var(--spot-ease) both; }
    .spot-summary-mobile-toggle { display: flex; }
}

/* ---------- Actions ---------- */
.spot-actions {
    display: flex;
    gap: 12px;
    margin-top: 24px;
}
.spot-btn {
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
    transition: all 0.2s var(--spot-ease);
    border: 1px solid transparent;
    position: relative;
    overflow: hidden;
}
.spot-btn:disabled { opacity: 0.55; cursor: not-allowed; }
.spot-btn i { transition: transform 0.25s var(--spot-ease); }
.spot-btn:hover:not(:disabled) i { transform: scale(1.15) rotate(-4deg); }

.spot-btn-primary {
    background: linear-gradient(135deg, var(--spot-accent), var(--spot-accent-2));
    color: #fff;
    box-shadow: 0 12px 28px -12px rgba(59, 130, 246, 0.75);
}
.spot-btn-primary:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 18px 34px -12px rgba(59, 130, 246, 0.85);
}
.spot-btn-primary::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(110deg, transparent 35%, rgba(255,255,255,0.28) 50%, transparent 65%);
    background-size: 200% 100%;
    animation: spotShimmer 3.6s linear infinite;
    pointer-events: none;
}
.spot-btn-secondary {
    background: var(--color-surface-muted);
    color: var(--color-text);
    border-color: var(--color-border);
}
.spot-btn-secondary:hover {
    background: var(--color-surface);
    border-color: var(--spot-accent);
    transform: translateY(-1px);
}
.spot-btn-ghost {
    background: transparent;
    color: var(--color-text-muted);
    flex: 0 0 auto;
    padding: 14px 16px;
    font-weight: 500;
    font-size: 0.85rem;
}
.spot-btn-ghost:hover { color: var(--color-text); }

/* ---------- Draft restored banner ---------- */
.spot-draft-banner {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    margin-bottom: 16px;
    border-radius: 12px;
    background: rgba(234, 179, 8, 0.10);
    border: 1px solid rgba(234, 179, 8, 0.28);
    color: #fcd34d;
    font-size: 0.82rem;
    animation: spotRise 0.4s var(--spot-ease) both;
}
.spot-draft-banner button {
    margin-left: auto;
    background: transparent;
    border: 1px solid rgba(234, 179, 8, 0.4);
    color: #fcd34d;
    padding: 4px 10px;
    border-radius: 8px;
    font-size: 0.72rem;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
}

/* ---------- Confetti ---------- */
.spot-confetti-piece {
    position: fixed;
    width: 8px;
    height: 8px;
    pointer-events: none;
    z-index: 9999;
    border-radius: 2px;
    animation: spotConfetti 1.1s ease-out forwards;
}

/* ---------- Floating help ---------- */
.spot-keyhint {
    display: none;
    align-items: center;
    gap: 8px;
    font-size: 0.72rem;
    color: var(--color-text-muted);
    margin-top: 12px;
    justify-content: center;
}
.spot-keyhint kbd {
    background: var(--color-surface-muted);
    border: 1px solid var(--color-border);
    border-radius: 5px;
    padding: 1px 6px;
    font-family: ui-monospace, monospace;
    font-size: 0.7rem;
}
@media (min-width: 900px) {
    .spot-keyhint { display: flex; }
}
</style>

<div class="page spot-page">
    <div class="spot-grid">

        <!-- ====================== FORM COLUMN ====================== -->
        <div class="spot-form-col spot-rise" style="--d: 0ms;">

            <!-- Progress bar -->
            <div class="spot-progress">
                <div class="spot-progress-fill" id="progressFill"></div>
            </div>
            <div class="spot-progress-label">
                <span id="progressText">Step 1 of 3</span>
                <span id="progressPercent">33%</span>
            </div>

            <!-- Draft banner (shown by JS if restored) -->
            <div class="spot-draft-banner" id="draftBanner" style="display:none;">
                <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                <span>Draft restored from your last session</span>
                <button type="button" id="discardDraft">Discard</button>
            </div>

            <!-- Step indicators -->
            <div class="spot-steps" id="spotSteps" style="--step: 1;">
                <div class="spot-step active" data-step="1">
                    <div class="spot-step-circle"><span>1</span></div>
                    <div class="spot-step-label">Shop</div>
                </div>
                <div class="spot-step" data-step="2">
                    <div class="spot-step-circle"><span>2</span></div>
                    <div class="spot-step-label">Tax</div>
                </div>
                <div class="spot-step" data-step="3">
                    <div class="spot-step-circle"><span>3</span></div>
                    <div class="spot-step-label">Confirm</div>
                </div>
            </div>

            <!-- Form -->
            <form action="payment.php" method="POST" novalidate id="spotTaxForm" autocomplete="off">
                <?= csrf_field() ?>
                <input type="hidden" name="suggested_amount" id="suggested_amount" value="">
                <input type="hidden" name="rate_per_sqft" id="rate_per_sqft" value="">
                <input type="hidden" name="shop_id" id="shop_id" value="<?= htmlspecialchars(isset($form_data['shop_id']) ? $form_data['shop_id'] : '') ?>">

                <div class="card">
                    <div class="card-body spot-form-body">

                        <!-- ==================== STEP 1 ==================== -->
                        <div class="spot-step-panel active" data-step="1">
                            <div class="spot-step-head">
                                <h2 class="spot-step-title">
                                    <i class="fa-solid fa-store" style="color: var(--spot-accent);" aria-hidden="true"></i>
                                    Shop Details
                                </h2>
                                <p class="spot-step-subtitle">Who are you collecting from?</p>
                            </div>

                            <div class="spot-field">
                                <label class="spot-label" for="shop_name">Shop Name <span style="color: var(--spot-danger);">*</span></label>
                                <input type="text" id="shop_name" name="shop_name"
                                       class="spot-input <?= isset($form_errors['shop_name']) ? 'error' : '' ?>"
                                       placeholder="e.g. Sharma Kirana"
                                       maxlength="150"
                                       required
                                       value="<?= htmlspecialchars(isset($form_data['shop_name']) ? $form_data['shop_name'] : '') ?>">
                                <?php if (isset($form_errors['shop_name'])): ?>
                                    <p class="spot-error"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i> <?= htmlspecialchars($form_errors['shop_name']) ?></p>
                                <?php endif; ?>
                            </div>

                            <div class="spot-row">
                                <div class="spot-field">
                                    <label class="spot-label" for="phone">Phone <span style="color: var(--spot-danger);">*</span></label>
                                    <div class="spot-phone-wrap">
                                        <span class="spot-phone-prefix">+91</span>
                                        <input type="tel" id="phone" name="phone"
                                               class="<?= isset($form_errors['phone']) ? 'error' : '' ?>"
                                               placeholder="98765 43210"
                                               required pattern="[0-9]{10}"
                                               inputmode="numeric"
                                               maxlength="11"
                                               value="<?= htmlspecialchars(isset($form_data['phone']) ? $form_data['phone'] : '') ?>">
                                    </div>
                                    <?php if (isset($form_errors['phone'])): ?>
                                        <p class="spot-error"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i> <?= htmlspecialchars($form_errors['phone']) ?></p>
                                    <?php endif; ?>
                                </div>

                                <div class="spot-field">
                                    <label class="spot-label" for="owner_name">Owner name <span class="optional">optional</span></label>
                                    <input type="text" id="owner_name" name="owner_name"
                                           class="spot-input"
                                           placeholder="e.g. Rajesh Sharma"
                                           maxlength="150"
                                           value="<?= htmlspecialchars(isset($form_data['owner_name']) ? $form_data['owner_name'] : '') ?>">
                                </div>
                            </div>

                            <div class="spot-field">
                                <label class="spot-label" for="shop_address">Address <span style="color: var(--spot-danger);">*</span></label>
                                <input type="text" id="shop_address" name="shop_address"
                                       class="spot-input <?= isset($form_errors['shop_address']) ? 'error' : '' ?>"
                                       placeholder="Street, landmark, area"
                                       required
                                       maxlength="255"
                                       value="<?= htmlspecialchars(isset($form_data['shop_address']) ? $form_data['shop_address'] : '') ?>">
                                <?php if (isset($form_errors['shop_address'])): ?>
                                    <p class="spot-error"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i> <?= htmlspecialchars($form_errors['shop_address']) ?></p>
                                <?php endif; ?>
                            </div>

                            <div class="spot-row">
                                <div class="spot-field">
                                    <label class="spot-label" for="zone">Zone <span class="optional">optional</span></label>
                                    <select id="zone" name="zone" class="spot-select">
                                        <option value="">— Select zone —</option>
                                        <?php foreach ($zones as $z): ?>
                                            <option value="<?= htmlspecialchars($z) ?>"
                                                <?= (isset($form_data['zone']) && $form_data['zone'] === $z) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($z) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="spot-field">
                                    <label class="spot-label">GPS location <span class="optional">optional</span></label>
                                    <div class="spot-gps-row">
                                        <button type="button" class="spot-gps-btn" id="gpsBtn">
                                            <i class="fa-solid fa-location-crosshairs" aria-hidden="true"></i>
                                            Capture
                                        </button>
                                        <span class="spot-gps-status" id="gpsStatus">Not captured</span>
                                    </div>
                                    <input type="hidden" name="latitude"  id="latitude"  value="">
                                    <input type="hidden" name="longitude" id="longitude" value="">
                                </div>
                            </div>

                            <div class="spot-actions">
                                <button type="button" class="spot-btn spot-btn-primary" data-next="2">
                                    <span>Continue</span>
                                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                                </button>
                            </div>
                            <div class="spot-keyhint">
                                <kbd>Enter</kbd> to continue
                            </div>
                        </div>

                        <!-- ==================== STEP 2 ==================== -->
                        <div class="spot-step-panel" data-step="2">
                            <div class="spot-step-head">
                                <h2 class="spot-step-title">
                                    <i class="fa-solid fa-receipt" style="color: var(--spot-accent);" aria-hidden="true"></i>
                                    Tax Details
                                </h2>
                                <p class="spot-step-subtitle">Stall type, size, and amount</p>
                            </div>

                            <div class="spot-field">
                                <label class="spot-label" for="stall_type">Stall type <span style="color: var(--spot-danger);">*</span></label>
                                <select id="stall_type" name="stall_type" class="spot-select" required>
                                    <option value="">— Select stall type —</option>
                                    <option value="Rekdi"   <?= (isset($form_data['stall_type']) && $form_data['stall_type'] === 'Rekdi')   ? 'selected' : '' ?>>Rekdi</option>
                                    <option value="Mandap"  <?= (isset($form_data['stall_type']) && $form_data['stall_type'] === 'Mandap')  ? 'selected' : '' ?>>Mandap</option>
                                    <option value="Chhajli" <?= (isset($form_data['stall_type']) && $form_data['stall_type'] === 'Chhajli') ? 'selected' : '' ?>>Chhajli</option>
                                    <option value="Other"   <?= (isset($form_data['stall_type']) && $form_data['stall_type'] === 'Other')   ? 'selected' : '' ?>>Other (type manually)</option>
                                </select>
                            </div>

                            <div class="spot-field" id="stall_type_other_wrapper" style="display: <?= (isset($form_data['stall_type']) && $form_data['stall_type'] === 'Other') ? 'block' : 'none' ?>;">
                                <label class="spot-label" for="stall_type_other">Specify stall type <span style="color: var(--spot-danger);">*</span></label>
                                <input type="text" id="stall_type_other" name="stall_type_other"
                                       class="spot-input"
                                       placeholder="e.g. Fruit cart, Vegetable stand"
                                       maxlength="50"
                                       value="<?= htmlspecialchars(isset($form_data['stall_type_other']) ? $form_data['stall_type_other'] : '') ?>">
                            </div>

                            <div class="spot-field">
                                <label class="spot-label" for="size">Size (sq ft) <span style="color: var(--spot-danger);">*</span></label>
                                <input type="number" id="size" name="size" class="spot-input"
                                       placeholder="Enter area in square feet"
                                       required min="0.01" step="0.01"
                                       max="10000"
                                       inputmode="decimal"
                                       value="<?= htmlspecialchars(isset($form_data['size']) ? $form_data['size'] : '') ?>">
                                <div class="spot-presets" id="sizePresets">
                                    <button type="button" class="spot-preset" data-size="25">25 sqft</button>
                                    <button type="button" class="spot-preset" data-size="50">50 sqft</button>
                                    <button type="button" class="spot-preset" data-size="100">100 sqft</button>
                                    <button type="button" class="spot-preset" data-size="144">144 sqft</button>
                                </div>
                            </div>

                            <div class="spot-field">
                                <label class="spot-label" for="amount">Amount to charge <span style="color: var(--spot-danger);">*</span></label>
                                <div class="spot-amount-wrap">
                                    <span class="spot-amount-prefix">₹</span>
                                    <input type="number" id="amount" name="amount"
                                           placeholder="0.00"
                                           required min="1" step="0.01"
                                           inputmode="decimal"
                                           value="<?= htmlspecialchars(isset($form_data['amount']) ? $form_data['amount'] : '') ?>">
                                    <button type="button" class="spot-amount-step" data-delta="-10" aria-label="Decrease by 10">−</button>
                                    <button type="button" class="spot-amount-step" data-delta="10"  aria-label="Increase by 10">+</button>
                                </div>
                                <p class="spot-help" id="amount-help">Enter final amount in rupees</p>

                                <div class="spot-suggest" id="suggestChip" style="display:none;">
                                    <div class="spot-suggest-icon">
                                        <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
                                    </div>
                                    <div class="spot-suggest-text" id="suggestText">
                                        Suggested amount
                                    </div>
                                    <button type="button" class="spot-suggest-apply" id="suggestApply">
                                        Use this
                                    </button>
                                </div>
                            </div>

                            <div class="spot-field">
                                <label class="spot-label">Payment mode <span style="color: var(--spot-danger);">*</span></label>
                                <div class="spot-pay-grid">
                                    <label class="spot-pay-card <?= (isset($form_data['payment_mode']) && $form_data['payment_mode'] === 'cash') ? 'active' : '' ?>" data-mode="cash">
                                        <input type="radio" name="payment_mode" value="cash"
                                               <?= (isset($form_data['payment_mode']) && $form_data['payment_mode'] === 'cash') ? 'checked' : '' ?>>
                                        <div class="spot-pay-icon"><i class="fa-solid fa-money-bill-wave" aria-hidden="true"></i></div>
                                        <div class="spot-pay-label">Cash</div>
                                        <div class="spot-pay-desc">Collect in hand</div>
                                    </label>
                                    <label class="spot-pay-card <?= (isset($form_data['payment_mode']) && $form_data['payment_mode'] === 'upi') ? 'active' : '' ?>" data-mode="upi">
                                        <input type="radio" name="payment_mode" value="upi"
                                               <?= (isset($form_data['payment_mode']) && $form_data['payment_mode'] === 'upi') ? 'checked' : '' ?>>
                                        <div class="spot-pay-icon"><i class="fa-solid fa-qrcode" aria-hidden="true"></i></div>
                                        <div class="spot-pay-label">UPI</div>
                                        <div class="spot-pay-desc">Scan &amp; pay</div>
                                    </label>
                                </div>
                            </div>

                            <div class="spot-field">
                                <label class="spot-label" for="notes">Notes <span class="optional">optional</span></label>
                                <textarea id="notes" name="notes" class="spot-textarea"
                                          placeholder="Any observations about this shop or collection"
                                          maxlength="500"></textarea>
                            </div>

                            <div class="spot-actions">
                                <button type="button" class="spot-btn spot-btn-secondary" data-back="1">
                                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                                    <span>Back</span>
                                </button>
                                <button type="button" class="spot-btn spot-btn-primary" data-next="3">
                                    <span>Review</span>
                                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                                </button>
                            </div>
                            <div class="spot-keyhint">
                                <kbd>Enter</kbd> to review
                            </div>
                        </div>

                        <!-- ==================== STEP 3 ==================== -->
                        <div class="spot-step-panel" data-step="3">
                            <div class="spot-step-head">
                                <h2 class="spot-step-title">
                                    <i class="fa-solid fa-circle-check" style="color: var(--spot-success);" aria-hidden="true"></i>
                                    Ready to Confirm
                                </h2>
                                <p class="spot-step-subtitle">Review everything before submitting</p>
                            </div>

                            <div id="reviewCard" style="border-radius: 14px; background: var(--color-surface-muted); padding: 18px;">
                                <!-- Filled by JS -->
                            </div>

                            <div class="spot-actions">
                                <button type="button" class="spot-btn spot-btn-secondary" data-back="2">
                                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                                    <span>Edit</span>
                                </button>
                                <button type="submit" class="spot-btn spot-btn-primary" id="submitBtn">
                                    <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                                    <span>Generate Collection</span>
                                </button>
                            </div>
                        </div>

                    </div>
                </div>
            </form>

            <!-- Mobile summary toggle -->
            <button type="button" class="spot-summary-mobile-toggle" id="summaryToggle">
                <span class="label">
                    <i class="fa-solid fa-list-check" aria-hidden="true"></i>
                    Summary
                </span>
                <span class="amount" id="mobileTotal">₹0.00</span>
            </button>

        </div>

        <!-- ====================== SUMMARY COLUMN ====================== -->
        <aside class="spot-summary spot-rise" id="summaryCard" style="--d: 120ms;">
            <div class="spot-summary-head">
                <div class="spot-summary-icon">
                    <i class="fa-solid fa-receipt" aria-hidden="true"></i>
                </div>
                <div>
                    <p class="spot-summary-title">Collection Summary</p>
                    <p class="spot-summary-sub">Live preview as you type</p>
                </div>
            </div>

            <div class="spot-summary-list">
                <div class="spot-summary-item">
                    <span class="spot-summary-key">Shop</span>
                    <span class="spot-summary-val empty" id="sumShop">—</span>
                </div>
                <div class="spot-summary-item">
                    <span class="spot-summary-key">Phone</span>
                    <span class="spot-summary-val empty" id="sumPhone">—</span>
                </div>
                <div class="spot-summary-item">
                    <span class="spot-summary-key">Owner</span>
                    <span class="spot-summary-val empty" id="sumOwner">—</span>
                </div>
                <div class="spot-summary-item">
                    <span class="spot-summary-key">Zone</span>
                    <span class="spot-summary-val empty" id="sumZone">—</span>
                </div>
                <div class="spot-summary-item">
                    <span class="spot-summary-key">Stall</span>
                    <span class="spot-summary-val empty" id="sumStall">—</span>
                </div>
                <div class="spot-summary-item">
                    <span class="spot-summary-key">Size</span>
                    <span class="spot-summary-val empty" id="sumSize">—</span>
                </div>
                <div class="spot-summary-item">
                    <span class="spot-summary-key">Payment</span>
                    <span class="spot-summary-val empty" id="sumPayment">—</span>
                </div>
            </div>

            <div class="spot-summary-total">
                <span class="spot-summary-total-label">Total</span>
                <span class="spot-summary-total-amount" id="sumTotal">₹0.00</span>
            </div>
        </aside>

    </div>
</div>

<script>
/* ================================================================
   DigiShulk — Spot Tax wizard
   ================================================================ */
(function () {
    'use strict';

    // Server-injected data
    var RATES = <?= json_encode($ratesMap, JSON_UNESCAPED_UNICODE) ?>;
    var DRAFT_KEY = 'digishulk_spot_draft_v1';
    var DRAFT_MAX_AGE_MS = 6 * 60 * 60 * 1000; // 6 hours

    // DOM refs
    var form           = document.getElementById('spotTaxForm');
    var steps          = document.querySelectorAll('.spot-step-panel');
    var stepDots       = document.querySelectorAll('.spot-step');
    var progressFill   = document.getElementById('progressFill');
    var progressText   = document.getElementById('progressText');
    var progressPct    = document.getElementById('progressPercent');
    var stepsBar       = document.getElementById('spotSteps');
    var suggestChip    = document.getElementById('suggestChip');
    var suggestText    = document.getElementById('suggestText');
    var suggestApply   = document.getElementById('suggestApply');
    var amountHelp     = document.getElementById('amount-help');
    var summaryCard    = document.getElementById('summaryCard');
    var summaryToggle  = document.getElementById('summaryToggle');
    var mobileTotal    = document.getElementById('mobileTotal');
    var draftBanner    = document.getElementById('draftBanner');
    var discardDraft   = document.getElementById('discardDraft');
    var gpsBtn         = document.getElementById('gpsBtn');
    var gpsStatus      = document.getElementById('gpsStatus');
    var latInput       = document.getElementById('latitude');
    var lngInput       = document.getElementById('longitude');

    var currentStep = 1;
    var userEditedAmount = false;
    var lastSuggestedValue = null;
    var restoredFromDraft = false;

    /* ============================================================
       1. Step navigation
       ============================================================ */
    function goToStep(target, direction) {
        if (target === currentStep) return;
        if (target < 1 || target > 3) return;

        var currentPanel = document.querySelector('.spot-step-panel[data-step="' + currentStep + '"]');
        var nextPanel    = document.querySelector('.spot-step-panel[data-step="' + target + '"]');

        // Animate out
        currentPanel.classList.remove('spot-slide-r', 'spot-slide-l');
        currentPanel.classList.add(direction === 'forward' ? 'spot-slide-l' : 'spot-slide-r');
        setTimeout(function () {
            currentPanel.classList.remove('active', 'spot-slide-l', 'spot-slide-r');
            nextPanel.classList.add('active');
            nextPanel.classList.remove('spot-slide-l', 'spot-slide-r');
            void nextPanel.offsetWidth; // force reflow
            nextPanel.classList.add(direction === 'forward' ? 'spot-slide-r' : 'spot-slide-l');
        }, 200);

        currentStep = target;

        // Update step dots
        stepDots.forEach(function (dot) {
            var n = parseInt(dot.getAttribute('data-step'), 10);
            dot.classList.toggle('active', n === currentStep);
            dot.classList.toggle('done', n < currentStep);
        });

        // Progress bar
        var pct = Math.round((currentStep / 3) * 100);
        if (progressFill) progressFill.style.width = pct + '%';
        if (progressPct)  progressPct.textContent = pct + '%';
        if (progressText) progressText.textContent = 'Step ' + currentStep + ' of 3';
        if (stepsBar)     stepsBar.style.setProperty('--step', currentStep);

        // Autofocus first input on the new step (except on step 3)
        if (target !== 3) {
            var firstInput = nextPanel.querySelector('input:not([type=hidden]):not([type=radio]), select, textarea');
            if (firstInput) {
                setTimeout(function () { firstInput.focus(); }, 260);
            }
        }

        // Scroll to top of wizard
        var top = form.getBoundingClientRect().top + window.scrollY - 20;
        window.scrollTo({ top: top, behavior: 'smooth' });

        if (target === 3) renderReview();
    }

    /* ============================================================
       2. Validation
       ============================================================ */
    function clearStepErrors(stepEl) {
        stepEl.querySelectorAll('.spot-error.dynamic').forEach(function (el) { el.remove(); });
        stepEl.querySelectorAll('.error').forEach(function (el) { el.classList.remove('error'); });
    }

    function showError(field, message) {
        field.classList.add('error');
        var wrap = field.closest('.spot-field') || field.parentNode;
        var p = document.createElement('p');
        p.className = 'spot-error dynamic';
        p.innerHTML = '<i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i> ' + message;
        wrap.appendChild(p);
    }

    function validateStep(step) {
        var panel = document.querySelector('.spot-step-panel[data-step="' + step + '"]');
        clearStepErrors(panel);
        var valid = true;

        if (step === 1) {
            var shopName = document.getElementById('shop_name');
            var phone    = document.getElementById('phone');
            var address  = document.getElementById('shop_address');

            if (!shopName.value.trim()) { showError(shopName, 'Shop name is required'); valid = false; }
            if (!/^[0-9]{10}$/.test(phone.value.replace(/\D/g, ''))) {
                showError(phone, 'Enter a valid 10-digit mobile number'); valid = false;
            }
            if (!address.value.trim()) { showError(address, 'Address is required'); valid = false; }
        }

        if (step === 2) {
            var stallType = document.getElementById('stall_type');
            var stallOther = document.getElementById('stall_type_other');
            var size      = document.getElementById('size');
            var amount    = document.getElementById('amount');
            var mode      = form.querySelector('input[name="payment_mode"]:checked');

            if (!stallType.value) { showError(stallType, 'Select a stall type'); valid = false; }
            if (stallType.value === 'Other' && !stallOther.value.trim()) {
                showError(stallOther, 'Please specify the stall type'); valid = false;
            }
            if (!size.value || parseFloat(size.value) <= 0) { showError(size, 'Enter a valid size'); valid = false; }
            if (!amount.value || parseFloat(amount.value) < 1) { showError(amount, 'Amount must be at least ₹1'); valid = false; }
            if (!mode) {
                valid = false;
                var payGrid = panel.querySelector('.spot-pay-grid');
                if (payGrid && !payGrid.querySelector('.spot-error.dynamic')) {
                    var p = document.createElement('p');
                    p.className = 'spot-error dynamic';
                    p.innerHTML = '<i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i> Choose a payment mode';
                    payGrid.parentNode.appendChild(p);
                }
            }
        }

        if (!valid) {
            var firstErr = panel.querySelector('.error');
            if (firstErr) firstErr.focus();
        }
        return valid;
    }

    /* ============================================================
       3. Wire up next/back buttons
       ============================================================ */
    document.querySelectorAll('[data-next]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var next = parseInt(btn.getAttribute('data-next'), 10);
            if (validateStep(currentStep)) {
                goToStep(next, 'forward');
                saveDraft();
            }
        });
    });
    document.querySelectorAll('[data-back]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var back = parseInt(btn.getAttribute('data-back'), 10);
            goToStep(back, 'back');
        });
    });

    /* ============================================================
       4. Enter key advances to next step
       ============================================================ */
    form.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && currentStep < 3) {
            var target = e.target;
            if (target.tagName === 'TEXTAREA') return;
            e.preventDefault();
            var nextBtn = document.querySelector('.spot-step-panel[data-step="' + currentStep + '"] [data-next]');
            if (nextBtn) nextBtn.click();
        }
    });

    /* ============================================================
       5. Phone auto-format
       ============================================================ */
    var phone = document.getElementById('phone');
    if (phone) {
        phone.addEventListener('input', function () {
            var digits = phone.value.replace(/\D/g, '').slice(0, 10);
            if (digits.length > 5) {
                phone.value = digits.slice(0, 5) + ' ' + digits.slice(5);
            } else {
                phone.value = digits;
            }
        });
    }

    /* ============================================================
       6. Size presets
       ============================================================ */
    var sizeInput = document.getElementById('size');
    document.querySelectorAll('.spot-preset').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var size = btn.getAttribute('data-size');
            if (sizeInput) {
                sizeInput.value = size;
                sizeInput.dispatchEvent(new Event('input'));
            }
            document.querySelectorAll('.spot-preset').forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
        });
    });
    if (sizeInput) {
        sizeInput.addEventListener('input', function () {
            var v = sizeInput.value;
            document.querySelectorAll('.spot-preset').forEach(function (b) {
                b.classList.toggle('active', b.getAttribute('data-size') === v);
            });
        });
    }

    /* ============================================================
       7. Amount steppers
       ============================================================ */
    document.querySelectorAll('.spot-amount-step').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var delta = parseFloat(btn.getAttribute('data-delta'));
            var amountInput = document.getElementById('amount');
            var cur = parseFloat(amountInput.value) || 0;
            var next = Math.max(1, cur + delta);
            amountInput.value = next.toFixed(2);
            userEditedAmount = true;
            amountInput.dispatchEvent(new Event('input'));
        });
    });

    /* ============================================================
       8. Suggested fee
       ============================================================ */
    function recomputeSuggestedFee() {
        var stallType  = document.getElementById('stall_type').value;
        var stallOther = document.getElementById('stall_type_other').value;
        var size       = parseFloat(document.getElementById('size').value || 0);
        var amountEl   = document.getElementById('amount');
        var sugHidden  = document.getElementById('suggested_amount');
        var rateHidden = document.getElementById('rate_per_sqft');

        var effectiveType = (stallType === 'Other') ? stallOther : stallType;
        var rate = RATES[effectiveType];

        if (rate === undefined || size <= 0) {
            sugHidden.value = '';
            rateHidden.value = '';
            suggestChip.style.display = 'none';
            if (!userEditedAmount && amountEl.value === '') {
                amountHelp.textContent = 'Enter final amount in rupees';
            }
            return;
        }

        var suggested = Math.round(size * rate * 100) / 100;
        sugHidden.value = suggested.toFixed(2);
        rateHidden.value = rate.toFixed(2);
        lastSuggestedValue = suggested;

        // Show suggestion chip if the current value differs
        var current = parseFloat(amountEl.value || 0);
        if (!userEditedAmount) {
            amountEl.value = suggested.toFixed(2);
            suggestChip.style.display = 'none';
            amountHelp.textContent = 'Auto-calculated from ' + size + ' sqft × ₹' + rate.toFixed(2);
        } else {
            if (Math.abs(current - suggested) > 0.01) {
                suggestText.innerHTML = 'Suggested: <strong>₹' + suggested.toFixed(2) + '</strong> · '
                    + size + ' sqft × ₹' + rate.toFixed(2);
                suggestChip.style.display = 'flex';
                amountHelp.textContent = 'You can override the suggested amount';
            } else {
                suggestChip.style.display = 'none';
                amountHelp.textContent = 'Matches the suggested amount';
            }
        }
        updateSummary();
    }

    if (suggestApply) {
        suggestApply.addEventListener('click', function () {
            if (lastSuggestedValue !== null) {
                document.getElementById('amount').value = lastSuggestedValue.toFixed(2);
                userEditedAmount = false;
                suggestChip.style.display = 'none';
                updateSummary();
            }
        });
    }

    /* ============================================================
       9. Live summary
       ============================================================ */
    function flash(el) {
        if (!el) return;
        el.classList.remove('flash');
        void el.offsetWidth;
        el.classList.add('flash');
    }

    function setVal(id, value) {
        var el = document.getElementById(id);
        if (!el) return;
        var v = (value === undefined || value === null || value === '') ? '—' : value;
        if (el.textContent !== v) {
            el.textContent = v;
            el.classList.toggle('empty', v === '—');
            flash(el);
        }
    }

    function updateSummary() {
        setVal('sumShop',    document.getElementById('shop_name').value.trim());
        var ph = document.getElementById('phone').value.replace(/\D/g, '');
        setVal('sumPhone',   ph ? '+91 ' + ph.replace(/(\d{5})(\d{0,5})/, '$1 $2').trim() : '');
        setVal('sumOwner',   document.getElementById('owner_name').value.trim());
        setVal('sumZone',    document.getElementById('zone').value);
        var st = document.getElementById('stall_type').value;
        var stOther = document.getElementById('stall_type_other').value;
        setVal('sumStall',   (st === 'Other' ? stOther : st) || '');
        var sz = document.getElementById('size').value;
        setVal('sumSize',    sz ? sz + ' sqft' : '');

        var mode = form.querySelector('input[name="payment_mode"]:checked');
        setVal('sumPayment', mode ? mode.value.toUpperCase() : '');

        var amt = parseFloat(document.getElementById('amount').value || 0);
        var total = document.getElementById('sumTotal');
        var totalStr = '₹' + amt.toFixed(2);
        if (total.textContent !== totalStr) {
            total.textContent = totalStr;
            flash(total);
        }
        if (mobileTotal) mobileTotal.textContent = totalStr;
    }

    /* ============================================================
       10. Review card (step 3)
       ============================================================ */
    function renderReview() {
        var card = document.getElementById('reviewCard');
        if (!card) return;

        var mode = form.querySelector('input[name="payment_mode"]:checked');
        var modeLabel = mode ? mode.value.toUpperCase() : '—';
        var amt = parseFloat(document.getElementById('amount').value || 0);
        var st = document.getElementById('stall_type').value;
        var stOther = document.getElementById('stall_type_other').value;
        var stallFinal = (st === 'Other' ? stOther : st) || '—';

        var rows = [
            ['Shop name',  document.getElementById('shop_name').value || '—'],
            ['Phone',      '+91 ' + document.getElementById('phone').value || '—'],
            ['Owner',      document.getElementById('owner_name').value || '—'],
            ['Address',    document.getElementById('shop_address').value || '—'],
            ['Zone',       document.getElementById('zone').value || '—'],
            ['Stall type', stallFinal],
            ['Size',       (document.getElementById('size').value || '—') + ' sqft'],
            ['Payment',    modeLabel],
        ];

        var html = '<div style="display: grid; gap: 10px;">';
        rows.forEach(function (r) {
            html += '<div style="display: flex; justify-content: space-between; gap: 14px; font-size: 0.85rem; padding-bottom: 8px; border-bottom: 1px dashed var(--color-border);">'
                 +  '<span style="color: var(--color-text-muted);">' + r[0] + '</span>'
                 +  '<span style="font-weight: 600; text-align: right; color: var(--color-text);">'
                 +  escapeHtml(r[1]) + '</span></div>';
        });
        html += '<div style="display: flex; justify-content: space-between; align-items: baseline; padding-top: 10px; margin-top: 4px;">'
             +  '<span style="font-size: 0.75rem; font-weight: 700; color: var(--color-text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Amount to collect</span>'
             +  '<span style="font-size: 1.75rem; font-weight: 800; color: var(--spot-success); letter-spacing: -0.02em;">₹'
             +  amt.toFixed(2) + '</span></div>';
        html += '</div>';

        card.innerHTML = html;
    }

    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    /* ============================================================
       11. Payment mode cards
       ============================================================ */
    document.querySelectorAll('.spot-pay-card').forEach(function (card) {
        card.addEventListener('click', function () {
            document.querySelectorAll('.spot-pay-card').forEach(function (c) { c.classList.remove('active'); });
            card.classList.add('active');
            card.querySelector('input').checked = true;
            updateSummary();
            saveDraft();
        });
    });

    /* ============================================================
       12. Toggle "Other" stall type
       ============================================================ */
    var stallTypeSelect = document.getElementById('stall_type');
    var otherWrapper    = document.getElementById('stall_type_other_wrapper');
    var otherInput      = document.getElementById('stall_type_other');

    function toggleOtherType() {
        if (stallTypeSelect.value === 'Other') {
            otherWrapper.style.display = 'block';
            otherInput.required = true;
        } else {
            otherWrapper.style.display = 'none';
            otherInput.required = false;
            otherInput.value = '';
        }
        recomputeSuggestedFee();
    }
    if (stallTypeSelect) stallTypeSelect.addEventListener('change', toggleOtherType);

    /* ============================================================
       13. Wire up field change listeners
       ============================================================ */
    var amtEl = document.getElementById('amount');
    if (amtEl) {
        amtEl.addEventListener('input', function () {
            userEditedAmount = true;
            recomputeSuggestedFee();
        });
    }
    if (otherInput) {
        otherInput.addEventListener('input', function () {
            userEditedAmount = false;
            recomputeSuggestedFee();
        });
    }
    if (sizeInput) {
        sizeInput.addEventListener('input', function () {
            // Keep user-edited amount, but recompute suggestion
            recomputeSuggestedFee();
        });
    }
    ['shop_name', 'phone', 'owner_name', 'shop_address', 'zone', 'stall_type',
     'size', 'payment_mode', 'notes'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', function () { updateSummary(); saveDraft(); });
            el.addEventListener('change', function () { updateSummary(); saveDraft(); });
        }
    });

    /* ============================================================
       14. GPS capture
       ============================================================ */
    if (gpsBtn) {
        gpsBtn.addEventListener('click', function () {
            if (!navigator.geolocation) {
                gpsStatus.textContent = 'Not supported on this device';
                gpsStatus.className = 'spot-gps-status err';
                return;
            }
            gpsBtn.disabled = true;
            gpsStatus.textContent = 'Locating…';
            gpsStatus.className = 'spot-gps-status';
            navigator.geolocation.getCurrentPosition(
                function (pos) {
                    latInput.value = pos.coords.latitude.toFixed(7);
                    lngInput.value = pos.coords.longitude.toFixed(7);
                    gpsStatus.textContent = 'Captured (' + latInput.value + ', ' + lngInput.value + ')';
                    gpsStatus.className = 'spot-gps-status ok';
                    gpsBtn.disabled = false;
                    saveDraft();
                },
                function (err) {
                    gpsStatus.textContent = 'Location denied or unavailable';
                    gpsStatus.className = 'spot-gps-status err';
                    gpsBtn.disabled = false;
                },
                { enableHighAccuracy: true, timeout: 8000, maximumAge: 30000 }
            );
        });
    }

    /* ============================================================
       15. Draft save/restore
       ============================================================ */
    function getFormState() {
        return {
            shop_name:      document.getElementById('shop_name').value,
            phone:          document.getElementById('phone').value,
            owner_name:     document.getElementById('owner_name').value,
            shop_address:   document.getElementById('shop_address').value,
            zone:           document.getElementById('zone').value,
            latitude:       latInput.value,
            longitude:      lngInput.value,
            stall_type:     document.getElementById('stall_type').value,
            stall_type_other: document.getElementById('stall_type_other').value,
            size:           document.getElementById('size').value,
            amount:         document.getElementById('amount').value,
            payment_mode:   (form.querySelector('input[name="payment_mode"]:checked') || {}).value || '',
            notes:          document.getElementById('notes').value,
            ts:             Date.now()
        };
    }

    function saveDraft() {
        try {
            localStorage.setItem(DRAFT_KEY, JSON.stringify(getFormState()));
        } catch (e) { /* localStorage may be blocked */ }
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

    function applyDraft(d) {
        if (d.shop_name)       document.getElementById('shop_name').value    = d.shop_name;
        if (d.phone)           document.getElementById('phone').value        = d.phone;
        if (d.owner_name)      document.getElementById('owner_name').value   = d.owner_name;
        if (d.shop_address)    document.getElementById('shop_address').value = d.shop_address;
        if (d.zone)            document.getElementById('zone').value         = d.zone;
        if (d.latitude)        latInput.value                                = d.latitude;
        if (d.longitude)       lngInput.value                                = d.longitude;
        if (d.stall_type)      document.getElementById('stall_type').value   = d.stall_type;
        if (d.stall_type_other) document.getElementById('stall_type_other').value = d.stall_type_other;
        if (d.size)            document.getElementById('size').value         = d.size;
        if (d.amount)          { document.getElementById('amount').value = d.amount; userEditedAmount = true; }
        if (d.notes)           document.getElementById('notes').value        = d.notes;
        if (d.payment_mode) {
            var radio = form.querySelector('input[name="payment_mode"][value="' + d.payment_mode + '"]');
            if (radio) {
                radio.checked = true;
                document.querySelectorAll('.spot-pay-card').forEach(function (c) {
                    c.classList.toggle('active', c.getAttribute('data-mode') === d.payment_mode);
                });
            }
        }
        if (d.latitude && d.longitude) {
            gpsStatus.textContent = 'Captured (' + d.latitude + ', ' + d.longitude + ')';
            gpsStatus.className = 'spot-gps-status ok';
        }
        if (d.stall_type === 'Other') {
            otherWrapper.style.display = 'block';
            otherInput.required = true;
        }
        restoredFromDraft = true;
        if (draftBanner) draftBanner.style.display = 'flex';
    }

    function clearDraft() {
        try { localStorage.removeItem(DRAFT_KEY); } catch (e) {}
    }

    if (discardDraft) {
        discardDraft.addEventListener('click', function () {
            clearDraft();
            form.reset();
            document.querySelectorAll('.spot-pay-card').forEach(function (c) { c.classList.remove('active'); });
            draftBanner.style.display = 'none';
            userEditedAmount = false;
            recomputeSuggestedFee();
            updateSummary();
        });
    }

    // Try to restore on load (only if there were no server-side errors)
    var hasServerErrors = <?= !empty($form_errors) ? 'true' : 'false' ?>;
    if (!hasServerErrors) {
        var draft = loadDraft();
        if (draft) applyDraft(draft);
    }

    /* ============================================================
       16. Mobile summary toggle
       ============================================================ */
    if (summaryToggle && summaryCard) {
        summaryToggle.addEventListener('click', function () {
            summaryCard.classList.toggle('open');
        });
    }

    /* ============================================================
       17. Confetti on submit
       ============================================================ */
    form.addEventListener('submit', function (e) {
        if (!validateStep(1) || !validateStep(2)) {
            e.preventDefault();
            // Figure out which step to return to
            if (!validateStep(1)) goToStep(1, 'back');
            else                  goToStep(2, 'back');
            return;
        }

        clearDraft();

        // Confetti — fun, brief, non-blocking
        if (!window.matchMedia || !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            var colors = ['#3b82f6', '#22c55e', '#a855f7', '#f59e0b', '#ef4444'];
            var submitBtn = document.getElementById('submitBtn');
            var rect = submitBtn.getBoundingClientRect();
            var cx = rect.left + rect.width / 2;
            var cy = rect.top + rect.height / 2;
            for (var i = 0; i < 22; i++) {
                (function () {
                    var piece = document.createElement('span');
                    piece.className = 'spot-confetti-piece';
                    var angle = Math.random() * Math.PI * 2;
                    var dist = 60 + Math.random() * 120;
                    piece.style.background = colors[Math.floor(Math.random() * colors.length)];
                    piece.style.left = cx + 'px';
                    piece.style.top  = cy + 'px';
                    piece.style.setProperty('--dx', Math.cos(angle) * dist + 'px');
                    piece.style.setProperty('--dy', Math.sin(angle) * dist + 100 + 'px');
                    piece.style.setProperty('--rot', (Math.random() * 720 - 360) + 'deg');
                    document.body.appendChild(piece);
                    setTimeout(function () { piece.remove(); }, 1200);
                })();
            }
            // Delay the actual submit so confetti is visible
            e.preventDefault();
            submitBtn.disabled = true;
            submitBtn.querySelector('span').textContent = 'Creating…';
            setTimeout(function () { form.submit(); }, 500);
        }
    });

    /* ============================================================
       18. Init
       ============================================================ */
    recomputeSuggestedFee();
    updateSummary();

})();
</script>
<script src="assets/js/spot_tax.js"></script>
<?php include 'footer.php'; ?>