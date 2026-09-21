<?php
// index.php — DigiShulk landing page
// Redirect logged-in users straight to their dashboard.
session_start();
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: includes/admin_dashboard.php');
        exit();
    }
    if ($_SESSION['role'] === 'inspector') {
        header('Location: includes/dashboard.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#3b82f6">

    <title>DigiShulk — RMC Digital Tax Collection System</title>

    <!-- SEO / sharing -->
    <meta name="description" content="DigiShulk is the digital spot tax and seizure reporting platform for Rajkot Municipal Corporation. Built for inspectors — UPI and cash collection, instant receipts, real-time field reporting.">
    <meta property="og:title" content="DigiShulk — RMC Digital Tax Collection System">
    <meta property="og:description" content="Digital spot tax collection, seizure reporting, and field operations for Rajkot Municipal Corporation.">
    <meta property="og:type" content="website">
    <meta name="twitter:card" content="summary_large_image">

    <!-- Favicon (data-URI SVG — no external file needed) -->
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Cdefs%3E%3ClinearGradient id='g' x1='0' y1='0' x2='1' y2='1'%3E%3Cstop offset='0' stop-color='%233b82f6'/%3E%3Cstop offset='1' stop-color='%23a855f7'/%3E%3C/linearGradient%3E%3C/defs%3E%3Crect width='64' height='64' rx='14' fill='url(%23g)'/%3E%3Ctext x='50%25' y='54%25' font-family='-apple-system,sans-serif' font-size='34' font-weight='800' fill='%23fff' text-anchor='middle' dominant-baseline='middle'%3E%E2%82%B9%3C/text%3E%3C/svg%3E">

    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
    /* ================================================================
       DigiShulk — Landing page
       Fully self-contained styles. Zero external dependency beyond
       Font Awesome. Does not rely on style2.css.
       ================================================================ */

    *, *::before, *::after {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }

    :root {
        --bg-base: #f8fafc;
        --text-primary: #0f172a;
        --text-secondary: #475569;
        --text-muted: #94a3b8;
        --primary: #3b82f6;
        --primary-dark: #2563eb;
        --accent: #a855f7;
        --success: #22c55e;
        --warning: #eab308;
        --danger: #ef4444;
        --border: rgba(15, 23, 42, 0.08);
        --radius-sm: 10px;
        --radius-md: 14px;
        --radius-lg: 22px;
        --ease: cubic-bezier(0.16, 1, 0.3, 1);
    }

    html, body {
        height: 100%;
    }

    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Inter',
                     Roboto, 'Helvetica Neue', Arial, sans-serif;
        background: var(--bg-base);
        color: var(--text-primary);
        min-height: 100vh;
        overflow-x: hidden;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
        text-rendering: optimizeLegibility;
        line-height: 1.5;
    }

    /* ---------------- Animations ---------------- */
    @keyframes rise {
        from { opacity: 0; transform: translateY(18px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    @keyframes fadeIn {
        from { opacity: 0; }
        to   { opacity: 1; }
    }
    @keyframes orbFloat {
        0%, 100% { transform: translate(0, 0) scale(1); }
        33%      { transform: translate(40px, -50px) scale(1.05); }
        66%      { transform: translate(-30px, 30px) scale(0.94); }
    }
    @keyframes logoFloat {
        0%, 100% { transform: translateY(0); }
        50%      { transform: translateY(-6px); }
    }
    @keyframes logoGlow {
        0%, 100% { opacity: 0.35; transform: scale(1); }
        50%      { opacity: 0.55; transform: scale(1.08); }
    }
    @keyframes pulseDot {
        0%, 100% { box-shadow: 0 0 0 4px rgba(34, 197, 94, 0.22); }
        50%      { box-shadow: 0 0 0 9px rgba(34, 197, 94, 0); }
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

    /* ---------------- Background orbs ---------------- */
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
        opacity: 0.42;
        animation: orbFloat 22s ease-in-out infinite;
        will-change: transform;
    }
    .bg-orb-1 {
        width: 520px;
        height: 520px;
        background: radial-gradient(circle, #3b82f6, transparent 70%);
        top: -180px;
        left: -140px;
        animation-delay: 0s;
    }
    .bg-orb-2 {
        width: 460px;
        height: 460px;
        background: radial-gradient(circle, #a855f7, transparent 70%);
        top: 25%;
        right: -160px;
        animation-delay: -8s;
    }
    .bg-orb-3 {
        width: 400px;
        height: 400px;
        background: radial-gradient(circle, #22c55e, transparent 70%);
        bottom: -150px;
        left: 32%;
        animation-delay: -16s;
    }

    /* ---------------- Grid overlay ---------------- */
    .bg-grid {
        position: fixed;
        inset: 0;
        z-index: 0;
        background-image:
            linear-gradient(rgba(15, 23, 42, 0.045) 1px, transparent 1px),
            linear-gradient(90deg, rgba(15, 23, 42, 0.045) 1px, transparent 1px);
        background-size: 48px 48px;
        -webkit-mask-image: radial-gradient(ellipse 80% 60% at 50% 50%, #000 30%, transparent 75%);
        mask-image: radial-gradient(ellipse 80% 60% at 50% 50%, #000 30%, transparent 75%);
        pointer-events: none;
        opacity: 0.9;
    }

    /* ---------------- Floating particles ---------------- */
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
        background: rgba(59, 130, 246, 0.5);
        animation: floatUp linear infinite;
        will-change: transform;
    }
    .particle.gold { background: rgba(234, 179, 8, 0.5); }
    .particle.purple { background: rgba(168, 85, 247, 0.5); }
    .particle.green { background: rgba(34, 197, 94, 0.5); }

    /* ---------------- Page shell ---------------- */
    .landing-page {
        position: relative;
        z-index: 1;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 64px 24px;
    }
    .hero {
        max-width: 900px;
        width: 100%;
        text-align: center;
        position: relative;
    }

    /* ---------------- Logo mark ---------------- */
    .hero-logo-wrap {
        display: flex;
        justify-content: center;
        margin-bottom: 28px;
        animation: rise 0.9s var(--ease) 0.05s both;
    }
    .hero-logo {
        width: 96px;
        height: 96px;
        border-radius: 26px;
        background: linear-gradient(135deg, #3b82f6 0%, #6366f1 55%, #a855f7 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.9rem;
        color: #fff;
        position: relative;
        box-shadow:
            0 24px 48px -12px rgba(59, 130, 246, 0.55),
            0 0 0 1px rgba(255, 255, 255, 0.35) inset,
            0 0 0 1px rgba(59, 130, 246, 0.15);
        animation: logoFloat 5s ease-in-out infinite;
    }
    .hero-logo::before {
        content: '';
        position: absolute;
        inset: -12px;
        border-radius: 34px;
        background: inherit;
        filter: blur(22px);
        z-index: -1;
        animation: logoGlow 4s ease-in-out infinite;
    }
    .hero-logo i {
        filter: drop-shadow(0 2px 6px rgba(0, 0, 0, 0.15));
    }

    /* ---------------- Kicker pill ---------------- */
    .kicker {
        display: inline-flex;
        align-items: center;
        gap: 9px;
        padding: 7px 16px 7px 12px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.75);
        border: 1px solid rgba(59, 130, 246, 0.22);
        color: #1d4ed8;
        font-size: 0.76rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        margin-bottom: 22px;
        position: relative;
        overflow: hidden;
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        box-shadow: 0 6px 18px -8px rgba(59, 130, 246, 0.35);
        animation: rise 0.9s var(--ease) 0.15s both;
    }
    .kicker::after {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(110deg,
            transparent 40%,
            rgba(59, 130, 246, 0.10) 50%,
            transparent 60%);
        background-size: 200% 100%;
        animation: shimmer 3.2s linear infinite;
        pointer-events: none;
    }
    .kicker-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: var(--success);
        flex-shrink: 0;
        animation: pulseDot 2.2s ease-in-out infinite;
    }

    /* ---------------- Hero title ---------------- */
    .hero-title {
        font-size: clamp(3rem, 9vw, 6rem);
        font-weight: 800;
        line-height: 0.98;
        letter-spacing: -0.045em;
        margin-bottom: 24px;
        background: linear-gradient(120deg,
            #0f172a 0%,
            #1e293b 30%,
            #3b82f6 60%,
            #a855f7 90%,
            #0f172a 100%);
        background-size: 250% auto;
        -webkit-background-clip: text;
        background-clip: text;
        -webkit-text-fill-color: transparent;
        animation:
            rise 0.9s var(--ease) 0.25s both,
            gradientShift 9s ease-in-out infinite;
        padding-bottom: 4px;
    }

    /* ---------------- Description ---------------- */
    .hero-desc {
        font-size: clamp(1rem, 2vw, 1.13rem);
        color: var(--text-secondary);
        line-height: 1.65;
        max-width: 54ch;
        margin: 0 auto 40px;
        font-weight: 400;
        animation: rise 0.9s var(--ease) 0.35s both;
    }
    .hero-desc strong {
        color: var(--text-primary);
        font-weight: 600;
    }

    /* ---------------- Feature grid ---------------- */
    .features {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(185px, 1fr));
        gap: 12px;
        max-width: 820px;
        margin: 0 auto 42px;
        animation: rise 0.9s var(--ease) 0.45s both;
    }
    .feature-card {
        display: flex;
        align-items: center;
        gap: 13px;
        padding: 14px 18px;
        background: rgba(255, 255, 255, 0.7);
        border: 1px solid var(--border);
        border-radius: var(--radius-md);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        text-align: left;
        transition:
            transform 0.3s var(--ease),
            box-shadow 0.3s var(--ease),
            border-color 0.3s var(--ease),
            background 0.3s var(--ease);
        cursor: default;
        position: relative;
        overflow: hidden;
    }
    .feature-card::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 3px;
        background: linear-gradient(180deg, var(--primary), var(--accent));
        transform: scaleY(0);
        transform-origin: center;
        transition: transform 0.35s var(--ease);
    }
    .feature-card:hover {
        transform: translateY(-4px);
        border-color: rgba(59, 130, 246, 0.30);
        background: rgba(255, 255, 255, 0.95);
        box-shadow: 0 20px 40px -18px rgba(59, 130, 246, 0.4);
    }
    .feature-card:hover::before {
        transform: scaleY(1);
    }
    .feature-icon {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.98rem;
        flex-shrink: 0;
        transition: transform 0.35s var(--ease);
    }
    .feature-card:hover .feature-icon {
        transform: scale(1.12) rotate(-6deg);
    }
    .feature-icon.blue   { background: rgba(59, 130, 246, 0.13); color: #2563eb; }
    .feature-icon.amber  { background: rgba(234, 179, 8, 0.15);  color: #b45309; }
    .feature-icon.green  { background: rgba(34, 197, 94, 0.13);  color: #16a34a; }
    .feature-icon.red    { background: rgba(239, 68, 68, 0.13);  color: #dc2626; }
    .feature-label {
        font-size: 0.86rem;
        font-weight: 600;
        color: var(--text-primary);
        line-height: 1.3;
        letter-spacing: -0.005em;
    }

    /* ---------------- CTA ---------------- */
    .cta-wrap {
        animation: rise 0.9s var(--ease) 0.55s both;
        margin-bottom: 46px;
    }
    .cta {
        display: inline-flex;
        align-items: center;
        gap: 12px;
        padding: 18px 40px;
        border-radius: 16px;
        background: linear-gradient(135deg, #3b82f6 0%, #6366f1 55%, #3b82f6 100%);
        background-size: 200% auto;
        color: #fff;
        font-size: 1.05rem;
        font-weight: 700;
        text-decoration: none;
        letter-spacing: -0.01em;
        position: relative;
        overflow: hidden;
        box-shadow:
            0 22px 44px -14px rgba(59, 130, 246, 0.65),
            0 0 0 1px rgba(255, 255, 255, 0.14) inset;
        transition:
            transform 0.28s var(--ease),
            box-shadow 0.28s var(--ease),
            background-position 0.6s var(--ease);
    }
    .cta:hover {
        transform: translateY(-3px);
        background-position: 100% center;
        box-shadow:
            0 32px 58px -14px rgba(59, 130, 246, 0.8),
            0 0 0 1px rgba(255, 255, 255, 0.18) inset;
    }
    .cta:active {
        transform: translateY(-1px);
        transition-duration: 0.1s;
    }
    .cta::before {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(110deg,
            transparent 35%,
            rgba(255, 255, 255, 0.30) 50%,
            transparent 65%);
        background-size: 200% 100%;
        animation: shimmer 3.4s linear infinite;
        pointer-events: none;
    }
    .cta .cta-icon {
        display: inline-flex;
        transition: transform 0.3s var(--ease);
    }
    .cta:hover .cta-icon {
        transform: translateX(4px);
    }
    .cta .cta-arrow {
        display: inline-flex;
        font-size: 0.85rem;
        opacity: 0.85;
        transition: transform 0.3s var(--ease);
    }
    .cta:hover .cta-arrow {
        transform: translateX(5px);
        opacity: 1;
    }

    /* ---------------- Footer ---------------- */
    .hero-footer {
        display: flex;
        flex-direction: column;
        gap: 12px;
        align-items: center;
        color: var(--text-muted);
        font-size: 0.78rem;
        line-height: 1.6;
        animation: rise 0.9s var(--ease) 0.65s both;
    }
    .hero-footer-sep {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        font-size: 0.7rem;
    }
    .hero-footer-sep::before,
    .hero-footer-sep::after {
        content: '';
        height: 1px;
        width: 36px;
        background: linear-gradient(90deg, transparent, rgba(148, 163, 184, 0.6), transparent);
    }
    .hero-footer-credits {
        display: inline-flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 6px 14px;
        color: var(--text-secondary);
        font-weight: 500;
    }
    .hero-footer-credits span {
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .hero-footer-credits i {
        color: var(--text-muted);
        font-size: 0.7rem;
        opacity: 0.7;
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
        .hero-logo,
        .hero-logo::before {
            animation: none !important;
        }
        .hero-logo::before { opacity: 0.4; }
    }

    /* ---------------- Mobile ---------------- */
    @media (max-width: 640px) {
        .landing-page {
            padding: 44px 20px 56px;
        }
        .hero-logo {
            width: 78px;
            height: 78px;
            font-size: 2.3rem;
            border-radius: 22px;
        }
        .kicker {
            font-size: 0.68rem;
            letter-spacing: 0.06em;
            padding: 6px 13px 6px 10px;
        }
        .hero-title {
            margin-bottom: 18px;
        }
        .hero-desc {
            margin-bottom: 32px;
            font-size: 0.98rem;
        }
        .features {
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 34px;
        }
        .feature-card {
            padding: 12px 13px;
            gap: 10px;
        }
        .feature-icon {
            width: 34px;
            height: 34px;
            font-size: 0.85rem;
            border-radius: 10px;
        }
        .feature-label {
            font-size: 0.78rem;
        }
        .cta {
            padding: 16px 30px;
            font-size: 0.96rem;
            border-radius: 14px;
        }
        .cta-wrap {
            margin-bottom: 38px;
        }
        .hero-footer {
            font-size: 0.72rem;
        }
    }

    @media (max-width: 400px) {
        .features {
            grid-template-columns: 1fr;
        }
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

<!-- ============ Landing content ============ -->
<main class="landing-page">
    <div class="hero">

        <!-- Logo mark -->
        <div class="hero-logo-wrap">
            <div class="hero-logo" aria-hidden="true">
                <i class="fa-solid fa-indian-rupee-sign"></i>
            </div>
        </div>

        <!-- Kicker -->
        <div class="kicker" role="presentation">
            <span class="kicker-dot" aria-hidden="true"></span>
            RMC Digital Initiative
        </div>

        <!-- Title -->
        <h1 class="hero-title">DigiShulk</h1>

        <!-- Description -->
        <p class="hero-desc">
            A modern digital platform for <strong>Rajkot Municipal Corporation</strong>
            to streamline spot tax collection, seizure reporting, and field operations —
            built for inspectors, by inspectors.
        </p>

        <!-- Features -->
        <div class="features" role="list">
            <div class="feature-card" role="listitem">
                <div class="feature-icon blue">
                    <i class="fa-solid fa-receipt" aria-hidden="true"></i>
                </div>
                <span class="feature-label">Spot Tax Collection</span>
            </div>

            <div class="feature-card" role="listitem">
                <div class="feature-icon amber">
                    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                </div>
                <span class="feature-label">Seizure Reporting</span>
            </div>

            <div class="feature-card" role="listitem">
                <div class="feature-icon green">
                    <i class="fa-solid fa-qrcode" aria-hidden="true"></i>
                </div>
                <span class="feature-label">UPI &amp; Cash Payments</span>
            </div>

            <div class="feature-card" role="listitem">
                <div class="feature-icon red">
                    <i class="fa-solid fa-file-pdf" aria-hidden="true"></i>
                </div>
                <span class="feature-label">Instant Receipts</span>
            </div>
        </div>

        <!-- CTA -->
        <div class="cta-wrap">
            <a href="includes/login.php" class="cta">
                <span class="cta-icon">
                    <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>
                </span>
                <span>Sign In to Dashboard</span>
                <span class="cta-arrow">
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </span>
            </a>
        </div>

        <!-- Footer -->
        <footer class="hero-footer">
            <span class="hero-footer-sep">Authorized Personnel Only</span>
            <div class="hero-footer-credits">
                <span><i class="fa-solid fa-code" aria-hidden="true"></i> Sahdev Parmar</span>
                <span><i class="fa-solid fa-code" aria-hidden="true"></i> Shubham Yadav</span>
                <span><i class="fa-solid fa-code" aria-hidden="true"></i> Yashraj Solanki</span>
            </div>
        </footer>

    </div>
</main>

<script>
/* ================================================================
   Floating particles — subtle background life.
   Skips generation entirely for users who prefer reduced motion.
   ================================================================ */
(function () {
    'use strict';

    var reduced = window.matchMedia &&
                  window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduced) return;

    var host = document.getElementById('particles');
    if (!host) return;

    var palette = ['', 'gold', 'purple', 'green'];
    var COUNT = 18;

    for (var i = 0; i < COUNT; i++) {
        var p = document.createElement('span');
        p.className = 'particle ' + palette[Math.floor(Math.random() * palette.length)];

        // Random horizontal position, random size, random duration/delay
        p.style.left      = (Math.random() * 100) + '%';
        p.style.width     = (2 + Math.random() * 4) + 'px';
        p.style.height    = p.style.width;
        p.style.opacity   = (0.35 + Math.random() * 0.5).toFixed(2);
        p.style.animationDuration = (16 + Math.random() * 20) + 's';
        p.style.animationDelay    = (-Math.random() * 30) + 's';

        host.appendChild(p);
    }
})();

/* ================================================================
   Subtle parallax on background orbs — follows the mouse.
   Very light (no continuous rAF); only reacts to mousemove with
   a debounce-ish threshold.
   ================================================================ */
(function () {
    'use strict';

    var reduced = window.matchMedia &&
                  window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduced) return;
    if (window.innerWidth < 768) return; // skip on mobile

    var orbs = document.querySelectorAll('.bg-orb');
    if (!orbs.length) return;

    var mx = 0, my = 0;
    var ticking = false;

    window.addEventListener('mousemove', function (e) {
        mx = (e.clientX / window.innerWidth  - 0.5) * 2;
        my = (e.clientY / window.innerHeight - 0.5) * 2;

        if (!ticking) {
            ticking = true;
            requestAnimationFrame(function () {
                orbs.forEach(function (orb, idx) {
                    var strength = 10 + idx * 6;
                    orb.style.setProperty('--px', (mx * strength) + 'px');
                    orb.style.setProperty('--py', (my * strength) + 'px');
                    orb.style.transform =
                        'translate(var(--px, 0), var(--py, 0))';
                });
                ticking = false;
            });
        }
    }, { passive: true });
})();
</script>

</body>
</html>