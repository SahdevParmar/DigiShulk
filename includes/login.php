<?php
session_start();

// If already logged in, skip the form.
if (isset($_SESSION['role'])) {
    header('Location: ' . ($_SESSION['role'] === 'admin' ? 'admin_dashboard.php' : 'dashboard.php'));
    exit();
}

require_once 'helpers/csrf.php';

// Show a generic error banner if we were redirected back from auth.php
$show_error = isset($_GET['error']) && $_GET['error'] === '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#14285a">

    <title>Sign In — DigiShulk</title>
    <meta name="description" content="Sign in to DigiShulk — RMC Digital Tax Collection System.">
    <meta name="robots" content="noindex, nofollow">

    <link rel="icon" type="image/png" href="../includes/css/layout/logo.png">
    <link rel="apple-touch-icon" href="../includes/css/layout/logo.png">

    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
    /* ================================================================
       DigiShulk — Sign In
       Self-contained. Matches the landing page aesthetic.
       ================================================================ */

    *, *::before, *::after {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }

    :root {
        --navy:   #14285a;
        --blue:   #1e50a2;
        --blue-2: #3b82f6;
        --green:  #3ba55c;
        --gold:   #f0a020;

        --ink:    #0f172a;
        --ink-2:  #475569;
        --ink-3:  #94a3b8;
        --ink-4:  #cbd5e1;

        --bg:     #f8fafc;
        --paper:  #ffffff;
        --border: rgba(15, 23, 42, 0.08);
        --border-2: rgba(15, 23, 42, 0.14);

        --danger: #dc2626;
        --danger-bg: rgba(220, 38, 38, 0.08);
        --danger-bd: rgba(220, 38, 38, 0.28);

        --radius:   14px;
        --radius-lg:22px;
        --ease: cubic-bezier(0.16, 1, 0.3, 1);
    }

    html, body { height: 100%; }

    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Inter',
                     Roboto, 'Helvetica Neue', Arial, sans-serif;
        background: var(--bg);
        color: var(--ink);
        min-height: 100vh;
        overflow-x: hidden;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
        line-height: 1.5;
    }

    /* ---------------- Animations ---------------- */
    @keyframes rise {
        from { opacity: 0; transform: translateY(18px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    @keyframes orbFloat {
        0%, 100% { transform: translate(0, 0) scale(1); }
        33%      { transform: translate(40px, -50px) scale(1.05); }
        66%      { transform: translate(-30px, 30px) scale(0.94); }
    }
    @keyframes logoFloat {
        0%, 100% { transform: translateY(0); }
        50%      { transform: translateY(-5px); }
    }
    @keyframes logoGlow {
        0%, 100% { opacity: 0.32; transform: scale(1); }
        50%      { opacity: 0.5; transform: scale(1.05); }
    }
    @keyframes shimmer {
        0%   { background-position: -200% 0; }
        100% { background-position:  200% 0; }
    }
    @keyframes gradientShift {
        0%, 100% { background-position: 0% center; }
        50%      { background-position: 100% center; }
    }
    @keyframes floatUp {
        0%   { transform: translateY(100vh) scale(0.6); opacity: 0; }
        10%  { opacity: 1; }
        90%  { opacity: 1; }
        100% { transform: translateY(-20vh) scale(1); opacity: 0; }
    }
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        20%      { transform: translateX(-7px); }
        40%      { transform: translateX(6px); }
        60%      { transform: translateX(-4px); }
        80%      { transform: translateX(3px); }
    }
    @keyframes spin {
        from { transform: rotate(0deg); }
        to   { transform: rotate(360deg); }
    }

    /* ---------------- Animated background ---------------- */
    .bg-orbs {
        position: fixed;
        inset: 0;
        z-index: 0;
        overflow: hidden;
        pointer-events: none;
    }
    .bg-orb {
        position: absolute;
        border-radius: 50%;
        filter: blur(90px);
        opacity: 0.35;
        animation: orbFloat 22s ease-in-out infinite;
        will-change: transform;
    }
    .bg-orb-1 {
        width: 520px; height: 520px;
        background: radial-gradient(circle, var(--blue) 0%, transparent 70%);
        top: -180px; left: -140px;
    }
    .bg-orb-2 {
        width: 460px; height: 460px;
        background: radial-gradient(circle, var(--green) 0%, transparent 70%);
        top: 25%; right: -160px;
        animation-delay: -8s;
    }
    .bg-orb-3 {
        width: 400px; height: 400px;
        background: radial-gradient(circle, var(--gold) 0%, transparent 70%);
        bottom: -150px; left: 32%;
        animation-delay: -16s;
    }

    .bg-grid {
        position: fixed;
        inset: 0;
        z-index: 0;
        background-image:
            linear-gradient(rgba(15, 23, 42, 0.045) 1px, transparent 1px),
            linear-gradient(90deg, rgba(15, 23, 42, 0.045) 1px, transparent 1px);
        background-size: 48px 48px;
        -webkit-mask-image: radial-gradient(ellipse 70% 60% at 50% 45%, #000 30%, transparent 75%);
        mask-image: radial-gradient(ellipse 70% 60% at 50% 45%, #000 30%, transparent 75%);
        pointer-events: none;
        opacity: 0.9;
    }

    .particles {
        position: fixed;
        inset: 0;
        z-index: 0;
        pointer-events: none;
        overflow: hidden;
    }
    .particle {
        position: absolute;
        width: 4px;
        height: 4px;
        border-radius: 50%;
        background: rgba(30, 80, 162, 0.5);
        animation: floatUp linear infinite;
        will-change: transform;
    }
    .particle.gold  { background: rgba(240, 160, 32, 0.5); }
    .particle.green { background: rgba(59, 165, 92, 0.5); }

    /* ---------------- Layout ---------------- */
    .login-shell {
        position: relative;
        z-index: 1;
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 32px 20px 40px;
    }

    /* ---------------- Brand bar ---------------- */
    .brand-bar {
        display: flex;
        justify-content: center;
        margin-bottom: 24px;
        animation: rise 0.7s var(--ease) both;
    }
    .brand-bar-inner {
        display: inline-flex;
        align-items: center;
        gap: 12px;
        padding: 8px 16px 8px 10px;
        background: rgba(255, 255, 255, 0.72);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        border: 1px solid var(--border);
        border-radius: 999px;
        box-shadow: 0 8px 24px -12px rgba(20, 40, 90, 0.22);
    }
    .brand-bar img {
        height: 34px;
        width: auto;
        display: block;
    }
    .brand-bar-text {
        display: flex;
        flex-direction: column;
        line-height: 1.1;
        border-left: 1px solid var(--border);
        padding-left: 12px;
    }
    .brand-bar-name {
        font-size: 0.85rem;
        font-weight: 700;
        color: var(--navy);
        letter-spacing: -0.005em;
    }
    .brand-bar-sub {
        font-size: 0.64rem;
        color: var(--ink-3);
        letter-spacing: 0.06em;
        text-transform: uppercase;
        margin-top: 2px;
        font-weight: 600;
    }

    /* ---------------- Login card ---------------- */
    .login-card {
        width: 100%;
        max-width: 440px;
        background: var(--paper);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 34px 32px 30px;
        box-shadow:
            0 1px 2px rgba(15, 23, 42, 0.04),
            0 24px 60px -22px rgba(20, 40, 90, 0.28);
        position: relative;
        animation: rise 0.8s var(--ease) 0.1s both;
        overflow: hidden;
    }
    /* Top accent stripe */
    .login-card::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 3px;
        background: linear-gradient(90deg,
            var(--navy) 0%, var(--navy) 30%,
            var(--green) 30%, var(--green) 65%,
            var(--gold) 65%, var(--gold) 100%);
    }

    /* ---------------- Logo ---------------- */
    .login-logo {
        display: flex;
        justify-content: center;
        margin: 6px 0 22px;
        position: relative;
        animation: logoFloat 5s ease-in-out infinite;
    }
    .login-logo img {
        height: 78px;
        width: auto;
        display: block;
        filter: drop-shadow(0 14px 28px rgba(20, 40, 90, 0.15));
    }
    .login-logo::before {
        content: '';
        position: absolute;
        inset: -12px -30px;
        background: radial-gradient(ellipse at center,
            rgba(59, 165, 92, 0.24) 0%,
            rgba(30, 80, 162, 0.16) 45%,
            transparent 72%);
        filter: blur(22px);
        z-index: -1;
        animation: logoGlow 4s ease-in-out infinite;
    }

    /* ---------------- Heading ---------------- */
    .login-heading {
        text-align: center;
        margin-bottom: 26px;
    }
    .login-heading h1 {
        font-size: 1.75rem;
        font-weight: 800;
        letter-spacing: -0.025em;
        line-height: 1.1;
        margin-bottom: 6px;
        background: linear-gradient(120deg,
            var(--navy) 0%,
            var(--blue) 45%,
            var(--green) 100%);
        background-size: 200% auto;
        -webkit-background-clip: text;
        background-clip: text;
        -webkit-text-fill-color: transparent;
        animation: gradientShift 8s ease-in-out infinite;
    }
    .login-heading p {
        color: var(--ink-2);
        font-size: 0.9rem;
    }

    /* ---------------- Error banner ---------------- */
    .login-error {
        display: flex;
        align-items: flex-start;
        gap: 11px;
        padding: 12px 14px;
        margin-bottom: 20px;
        background: var(--danger-bg);
        border: 1px solid var(--danger-bd);
        border-radius: 10px;
        color: #991b1b;
        font-size: 0.86rem;
        line-height: 1.5;
        animation: rise 0.4s var(--ease) both, shake 0.5s var(--ease) 0.05s;
    }
    .login-error i {
        color: var(--danger);
        font-size: 1rem;
        margin-top: 2px;
        flex-shrink: 0;
    }
    .login-error strong {
        display: block;
        font-weight: 700;
        margin-bottom: 1px;
    }
    .login-error span {
        color: #7f1d1d;
    }

    /* ---------------- Form ---------------- */
    .form-group {
        margin-bottom: 16px;
    }
    .form-label {
        display: block;
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--ink);
        margin-bottom: 7px;
        letter-spacing: 0.005em;
    }

    .input-wrap {
        position: relative;
        display: flex;
        align-items: center;
        background: var(--bg);
        border: 1px solid var(--border-2);
        border-radius: 11px;
        transition: border-color 0.2s var(--ease),
                    box-shadow 0.2s var(--ease),
                    background 0.2s var(--ease);
    }
    .input-wrap:focus-within {
        border-color: var(--blue);
        background: var(--paper);
        box-shadow: 0 0 0 4px rgba(30, 80, 162, 0.12);
    }

    .input-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 44px;
        flex-shrink: 0;
        color: var(--ink-3);
        font-size: 0.92rem;
        transition: color 0.2s var(--ease);
    }
    .input-wrap:focus-within .input-icon {
        color: var(--blue);
    }

    .input-field {
        flex: 1;
        min-width: 0;
        padding: 13px 14px 13px 0;
        background: transparent;
        border: none;
        outline: none;
        font-family: inherit;
        font-size: 0.95rem;
        color: var(--ink);
        letter-spacing: 0.005em;
    }
    .input-field::placeholder {
        color: var(--ink-3);
    }
    /* Remove default Chrome autofill yellow */
    .input-field:-webkit-autofill,
    .input-field:-webkit-autofill:hover,
    .input-field:-webkit-autofill:focus {
        -webkit-box-shadow: 0 0 0 30px var(--bg) inset;
        -webkit-text-fill-color: var(--ink);
        transition: background-color 5000s ease-in-out 0s;
    }

    /* Password reveal toggle */
    .pw-toggle {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 100%;
        background: transparent;
        border: none;
        color: var(--ink-3);
        cursor: pointer;
        font-size: 0.9rem;
        transition: color 0.2s var(--ease);
        padding: 0;
        flex-shrink: 0;
    }
    .pw-toggle:hover {
        color: var(--blue);
    }
    .pw-toggle:focus-visible {
        outline: 2px solid var(--blue);
        outline-offset: -4px;
        border-radius: 8px;
    }

    /* Caps Lock warning */
    .caps-warn {
        display: none;
        align-items: center;
        gap: 6px;
        margin-top: 6px;
        font-size: 0.75rem;
        color: #b45309;
        font-weight: 600;
    }
    .caps-warn.visible {
        display: flex;
        animation: rise 0.25s var(--ease) both;
    }

    /* ---------------- Submit button ---------------- */
    .btn-submit {
        width: 100%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        padding: 14px 24px;
        margin-top: 8px;
        border: none;
        border-radius: 11px;
        background: linear-gradient(135deg,
            var(--navy) 0%,
            var(--blue) 55%,
            var(--navy) 100%);
        background-size: 200% auto;
        color: #fff;
        font-family: inherit;
        font-size: 0.98rem;
        font-weight: 700;
        letter-spacing: -0.005em;
        cursor: pointer;
        position: relative;
        overflow: hidden;
        box-shadow:
            0 14px 28px -12px rgba(20, 40, 90, 0.55),
            0 0 0 1px rgba(255, 255, 255, 0.12) inset;
        transition:
            transform 0.25s var(--ease),
            box-shadow 0.25s var(--ease),
            background-position 0.5s var(--ease);
    }
    .btn-submit:hover:not(:disabled) {
        transform: translateY(-2px);
        background-position: 100% center;
        box-shadow:
            0 20px 36px -12px rgba(20, 40, 90, 0.7),
            0 0 0 1px rgba(255, 255, 255, 0.16) inset;
    }
    .btn-submit:active:not(:disabled) {
        transform: translateY(0);
        transition-duration: 0.1s;
    }
    .btn-submit:disabled {
        cursor: wait;
        opacity: 0.85;
    }
    .btn-submit::before {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(110deg,
            transparent 35%,
            rgba(255, 255, 255, 0.28) 50%,
            transparent 65%);
        background-size: 200% 100%;
        animation: shimmer 3.4s linear infinite;
        pointer-events: none;
    }
    .btn-submit .btn-icon,
    .btn-submit .btn-arrow {
        display: inline-flex;
        transition: transform 0.28s var(--ease);
    }
    .btn-submit:hover:not(:disabled) .btn-arrow {
        transform: translateX(4px);
    }
    .btn-submit .spinner {
        display: none;
        font-size: 0.95rem;
        animation: spin 0.9s linear infinite;
    }
    .btn-submit.loading .btn-icon,
    .btn-submit.loading .btn-arrow,
    .btn-submit.loading .btn-label {
        display: none;
    }
    .btn-submit.loading .spinner {
        display: inline-flex;
    }

    /* ---------------- Footer ---------------- */
    .login-footer {
        text-align: center;
        margin-top: 22px;
        padding-top: 18px;
        border-top: 1px solid var(--border);
        font-size: 0.78rem;
        color: var(--ink-3);
        line-height: 1.6;
    }
    .login-footer .brand-line {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        font-weight: 600;
        color: var(--ink-2);
        margin-bottom: 4px;
    }
    .login-footer .brand-line i {
        color: var(--green);
        font-size: 0.7rem;
    }

    /* ---------------- Bottom meta (outside card) ---------------- */
    .page-meta {
        margin-top: 24px;
        text-align: center;
        font-size: 0.76rem;
        color: var(--ink-3);
        line-height: 1.7;
        animation: rise 0.8s var(--ease) 0.35s both;
    }
    .page-meta a {
        color: var(--blue);
        font-weight: 600;
        text-decoration: none;
        transition: color 0.15s var(--ease);
    }
    .page-meta a:hover {
        color: var(--navy);
        text-decoration: underline;
    }

    /* ---------------- Reduced motion ---------------- */
    @media (prefers-reduced-motion: reduce) {
        *, *::before, *::after {
            animation-duration: 0.01ms !important;
            animation-iteration-count: 1 !important;
            transition-duration: 0.01ms !important;
        }
        .bg-orb,
        .particle,
        .login-logo,
        .login-logo::before,
        .btn-submit::before {
            animation: none !important;
        }
        .login-logo::before { opacity: 0.35; }
    }

    /* ---------------- Mobile ---------------- */
    @media (max-width: 520px) {
        .login-shell { padding: 24px 16px 32px; }

        .brand-bar { margin-bottom: 20px; }
        .brand-bar-inner { padding: 7px 14px 7px 9px; gap: 10px; }
        .brand-bar img { height: 30px; }
        .brand-bar-name { font-size: 0.8rem; }
        .brand-bar-sub { font-size: 0.6rem; }

        .login-card {
            padding: 26px 22px 24px;
            border-radius: 18px;
        }
        .login-logo img { height: 66px; }
        .login-heading h1 { font-size: 1.5rem; }
        .login-heading p { font-size: 0.85rem; }
        .login-heading { margin-bottom: 22px; }

        .input-field { padding: 12px 12px 12px 0; font-size: 1rem; }
        .input-icon { width: 40px; }

        .btn-submit { padding: 13px 20px; font-size: 0.94rem; }

        .page-meta { font-size: 0.72rem; }
    }

    @media (max-width: 400px) {
        .brand-bar-text { display: none; }
        .brand-bar-inner { padding: 6px 10px; }
    }
    </style>
</head>
<body>

<!-- ============ Animated background ============ -->
<div class="bg-orbs" aria-hidden="true">
    <div class="bg-orb bg-orb-1"></div>
    <div class="bg-orb bg-orb-2"></div>
    <div class="bg-orb bg-orb-3"></div>
</div>
<div class="bg-grid" aria-hidden="true"></div>
<div class="particles" id="particles" aria-hidden="true"></div>

<!-- ============ Content ============ -->
<main class="login-shell">

    <!-- Brand bar -->
    <div class="brand-bar">
        <div class="brand-bar-inner">
            <img src="css/layout/logo.png" alt="DigiShulk">
            <div class="brand-bar-text">
                <span class="brand-bar-name">DigiShulk</span>
                <span class="brand-bar-sub">RMC Tax Collection</span>
            </div>
        </div>
    </div>

    <!-- Login card -->
    <section class="login-card">

        <div class="login-logo">
            <img src="css/layout/logo.png" alt="DigiShulk — RMC Digital Tax Collection">
        </div>

        <div class="login-heading">
            <h1>Welcome back</h1>
            <p>Sign in to your DigiShulk account</p>
        </div>

        <?php if ($show_error): ?>
        <div class="login-error" role="alert">
            <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
            <div>
                <strong>Couldn't sign in</strong>
                <span>The username or password is incorrect. Please try again.</span>
            </div>
        </div>
        <?php endif; ?>

        <form action="auth.php" method="POST" id="loginForm" novalidate>

            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label" for="username">Username</label>
                <div class="input-wrap">
                    <span class="input-icon" aria-hidden="true">
                        <i class="fa-solid fa-user"></i>
                    </span>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        class="input-field"
                        placeholder="Enter your username"
                        required
                        autocomplete="username"
                        autocapitalize="none"
                        autocorrect="off"
                        spellcheck="false"
                        autofocus
                        enterkeyhint="next">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <div class="input-wrap">
                    <span class="input-icon" aria-hidden="true">
                        <i class="fa-solid fa-lock"></i>
                    </span>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="input-field"
                        placeholder="Enter your password"
                        required
                        autocomplete="current-password"
                        enterkeyhint="go">
                    <button type="button"
                            class="pw-toggle"
                            id="pwToggle"
                            aria-label="Show password"
                            aria-pressed="false"
                            tabindex="-1">
                        <i class="fa-solid fa-eye" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="caps-warn" id="capsWarn" role="status">
                    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                    Caps Lock is on
                </div>
            </div>

            <button type="submit" class="btn-submit" id="submitBtn">
                <span class="btn-icon">
                    <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>
                </span>
                <span class="btn-label">Sign in</span>
                <span class="btn-arrow">
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </span>
                <span class="spinner" aria-hidden="true">
                    <i class="fa-solid fa-circle-notch"></i>
                </span>
            </button>

        </form>

        <div class="login-footer">
            <div class="brand-line">
                <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                Authorised RMC personnel only
            </div>
            <span>Contact your zone supervisor if you don't have an account.</span>
        </div>

    </section>

    <p class="page-meta">
        DigiShulk &mdash; RMC Digital Tax Collection System<br>
        <a href="../index.php">← Back to home</a>
    </p>

</main>

<script>
/* ================================================================
   DigiShulk — Login runtime
   ================================================================ */
(function () {
    'use strict';

    /* ---------- 1. Floating particles ---------- */
    (function () {
        var reduced = window.matchMedia &&
                      window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (reduced) return;

        var host = document.getElementById('particles');
        if (!host) return;

        var palette = ['', 'gold', 'green'];
        for (var i = 0; i < 16; i++) {
            var p = document.createElement('span');
            p.className = 'particle ' + palette[Math.floor(Math.random() * palette.length)];
            p.style.left = (Math.random() * 100) + '%';
            p.style.width = (2 + Math.random() * 4) + 'px';
            p.style.height = p.style.width;
            p.style.opacity = (0.3 + Math.random() * 0.45).toFixed(2);
            p.style.animationDuration = (16 + Math.random() * 20) + 's';
            p.style.animationDelay = (-Math.random() * 30) + 's';
            host.appendChild(p);
        }
    })();

    /* ---------- 2. Orb parallax (desktop only) ---------- */
    (function () {
        var reduced = window.matchMedia &&
                      window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (reduced || window.innerWidth < 768) return;

        var orbs = document.querySelectorAll('.bg-orb');
        if (!orbs.length) return;

        var mx = 0, my = 0, ticking = false;

        window.addEventListener('mousemove', function (e) {
            mx = (e.clientX / window.innerWidth  - 0.5) * 2;
            my = (e.clientY / window.innerHeight - 0.5) * 2;

            if (!ticking) {
                ticking = true;
                requestAnimationFrame(function () {
                    orbs.forEach(function (orb, idx) {
                        var s = 8 + idx * 5;
                        orb.style.transform =
                            'translate(' + (mx * s) + 'px, ' + (my * s) + 'px)';
                    });
                    ticking = false;
                });
            }
        }, { passive: true });
    })();

    /* ---------- 3. Password reveal toggle ---------- */
    (function () {
        var toggle = document.getElementById('pwToggle');
        var pw = document.getElementById('password');
        if (!toggle || !pw) return;

        toggle.addEventListener('click', function () {
            var showing = pw.type === 'text';
            pw.type = showing ? 'password' : 'text';
            toggle.setAttribute('aria-pressed', showing ? 'false' : 'true');
            toggle.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
            toggle.innerHTML = showing
                ? '<i class="fa-solid fa-eye" aria-hidden="true"></i>'
                : '<i class="fa-solid fa-eye-slash" aria-hidden="true"></i>';
            pw.focus();
        });
    })();

    /* ---------- 4. Caps Lock detection on password ---------- */
    (function () {
        var pw = document.getElementById('password');
        var warn = document.getElementById('capsWarn');
        if (!pw || !warn) return;

        function check(e) {
            if (typeof e.getModifierState !== 'function') return;
            var on = e.getModifierState('CapsLock');
            warn.classList.toggle('visible', !!on);
        }

        pw.addEventListener('keydown', check);
        pw.addEventListener('keyup', check);
        pw.addEventListener('blur', function () { warn.classList.remove('visible'); });
    })();

    /* ---------- 5. Submit — loading state + double-click guard ---------- */
    (function () {
        var form = document.getElementById('loginForm');
        var btn = document.getElementById('submitBtn');
        if (!form || !btn) return;

        var submitted = false;

        form.addEventListener('submit', function (e) {
            // Basic client-side required-field guard (server still validates)
            var u = document.getElementById('username');
            var p = document.getElementById('password');
            if (!u.value.trim() || !p.value) {
                e.preventDefault();
                (u.value.trim() ? p : u).focus();
                return;
            }

            if (submitted) {
                e.preventDefault();
                return;
            }
            submitted = true;

            // Show spinner state — don't disable the input fields so the browser
            // can still submit their values.
            btn.classList.add('loading');
            btn.setAttribute('aria-busy', 'true');

            // Fail-safe: if something goes wrong on the server and we never
            // navigate away, re-enable after 8s so the user isn't stuck.
            setTimeout(function () {
                if (submitted) {
                    submitted = false;
                    btn.classList.remove('loading');
                    btn.removeAttribute('aria-busy');
                }
            }, 8000);
        });

        // If the user hits "back" from the dashboard, don't leave the button
        // stuck in loading state.
        window.addEventListener('pageshow', function (evt) {
            if (evt.persisted) {
                submitted = false;
                btn.classList.remove('loading');
                btn.removeAttribute('aria-busy');
            }
        });
    })();

    /* ---------- 6. Auto-clear the ?error=1 from the URL ---------- */
    (function () {
        if (window.history && window.history.replaceState) {
            var url = new URL(window.location.href);
            if (url.searchParams.has('error')) {
                url.searchParams.delete('error');
                window.history.replaceState({}, '', url.pathname + url.search);
            }
        }
    })();

})();
</script>

</body>
</html>