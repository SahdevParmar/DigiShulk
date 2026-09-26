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
    <meta name="theme-color" content="#14285a">

    <title>DigiShulk — RMC Digital Tax Collection System</title>

    <meta name="description" content="DigiShulk is the digital spot tax and seizure reporting platform for Rajkot Municipal Corporation. UPI and cash collection, instant receipts, real-time field reporting.">
    <meta property="og:title" content="DigiShulk — RMC Digital Tax Collection System">
    <meta property="og:description" content="Digital spot tax collection, seizure reporting, and field operations for Rajkot Municipal Corporation.">
    <meta property="og:type" content="website">

    <!-- Favicon: use the supplied logo -->
    <link rel="icon" type="image/png" href="includes/css/layout/logo.png">
    <link rel="apple-touch-icon" href="includes/css/layout/logo.png">

    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
    /* ================================================================
       DigiShulk — Landing page (animated)
       Self-contained. Does not load style2.css.
       Palette driven by the DigiShulk logo (navy + green + gold).
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

        --ink:      #0f172a;
        --ink-2:    #475569;
        --ink-3:    #94a3b8;

        --bg:       #f8fafc;
        --paper:    #ffffff;
        --border:   rgba(15, 23, 42, 0.08);

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
        50%      { transform: translateY(-8px); }
    }
    @keyframes logoGlow {
        0%, 100% { opacity: 0.35; transform: scale(1); }
        50%      { opacity: 0.55; transform: scale(1.06); }
    }
    @keyframes pulseDot {
        0%, 100% { box-shadow: 0 0 0 4px rgba(59, 165, 92, 0.22); }
        50%      { box-shadow: 0 0 0 9px rgba(59, 165, 92, 0); }
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
        opacity: 0.38;
        animation: orbFloat 22s ease-in-out infinite;
        will-change: transform;
    }
    .bg-orb-1 {
        width: 520px;
        height: 520px;
        background: radial-gradient(circle, var(--blue) 0%, transparent 70%);
        top: -180px;
        left: -140px;
        animation-delay: 0s;
    }
    .bg-orb-2 {
        width: 460px;
        height: 460px;
        background: radial-gradient(circle, var(--green) 0%, transparent 70%);
        top: 25%;
        right: -160px;
        animation-delay: -8s;
    }
    .bg-orb-3 {
        width: 400px;
        height: 400px;
        background: radial-gradient(circle, var(--gold) 0%, transparent 70%);
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
        background: rgba(30, 80, 162, 0.5);
        animation: floatUp linear infinite;
        will-change: transform;
    }
    .particle.gold   { background: rgba(240, 160, 32, 0.5); }
    .particle.green  { background: rgba(59, 165, 92, 0.5); }

    /* ---------------- Brand bar (top) ---------------- */
    .brand-bar {
        position: relative;
        z-index: 2;
        padding: 22px 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        animation: rise 0.8s var(--ease) both;
    }
    .brand-bar-inner {
        display: inline-flex;
        align-items: center;
        gap: 14px;
        padding: 10px 20px 10px 12px;
        background: rgba(255, 255, 255, 0.7);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        border: 1px solid var(--border);
        border-radius: 999px;
        box-shadow: 0 8px 24px -12px rgba(20, 40, 90, 0.25);
    }
    .brand-bar img {
        height: 40px;
        width: auto;
        display: block;
    }
    .brand-bar-text {
        display: flex;
        flex-direction: column;
        line-height: 1.1;
        border-left: 1px solid var(--border);
        padding-left: 14px;
    }
    .brand-bar-name {
        font-size: 0.9rem;
        font-weight: 700;
        color: var(--navy);
        letter-spacing: -0.005em;
    }
    .brand-bar-sub {
        font-size: 0.68rem;
        color: var(--ink-3);
        letter-spacing: 0.06em;
        text-transform: uppercase;
        margin-top: 2px;
        font-weight: 600;
    }

    /* ---------------- Page shell ---------------- */
    .landing-page {
        position: relative;
        z-index: 1;
        min-height: calc(100vh - 90px);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 40px 24px 72px;
    }
    .hero {
        max-width: 900px;
        width: 100%;
        text-align: center;
        position: relative;
    }

    /* ---------------- Hero logo ---------------- */
    .hero-logo-wrap {
        display: flex;
        justify-content: center;
        margin-bottom: 28px;
        animation: rise 0.9s var(--ease) 0.05s both;
        position: relative;
    }
    .hero-logo {
        position: relative;
        display: inline-block;
        animation: logoFloat 5s ease-in-out infinite;
    }
    .hero-logo img {
        height: 120px;
        width: auto;
        display: block;
        filter: drop-shadow(0 20px 40px rgba(20, 40, 90, 0.18))
                drop-shadow(0 4px 12px rgba(20, 40, 90, 0.10));
    }
    /* Glow blob behind the logo */
    .hero-logo::before {
        content: '';
        position: absolute;
        inset: -20px -40px;
        background: radial-gradient(ellipse at center,
            rgba(59, 165, 92, 0.30) 0%,
            rgba(30, 80, 162, 0.20) 40%,
            transparent 70%);
        filter: blur(28px);
        z-index: -1;
        animation: logoGlow 4s ease-in-out infinite;
    }

    /* ---------------- Kicker pill ---------------- */
    .kicker {
        display: inline-flex;
        align-items: center;
        gap: 9px;
        padding: 7px 16px 7px 12px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.75);
        border: 1px solid rgba(59, 165, 92, 0.28);
        color: #256d3f;
        font-size: 0.76rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        margin-bottom: 22px;
        position: relative;
        overflow: hidden;
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        box-shadow: 0 6px 18px -8px rgba(59, 165, 92, 0.35);
        animation: rise 0.9s var(--ease) 0.15s both;
    }
    .kicker::after {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(110deg,
            transparent 40%,
            rgba(59, 165, 92, 0.12) 50%,
            transparent 60%);
        background-size: 200% 100%;
        animation: shimmer 3.2s linear infinite;
        pointer-events: none;
    }
    .kicker-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: var(--green);
        flex-shrink: 0;
        animation: pulseDot 2.2s ease-in-out infinite;
    }

    /* ---------------- Hero title ---------------- */
    .hero-title {
        font-size: clamp(3rem, 9vw, 5.4rem);
        font-weight: 800;
       
        letter-spacing: -0.045em;
        margin-bottom: 24px;
        background: linear-gradient(120deg,
            #14285a 0%,
            #1e50a2 30%,
            #3ba55c 60%,
            #f0a020 85%,
            #14285a 100%);
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
        color: var(--ink-2);
        line-height: 1.65;
        max-width: 54ch;
        margin: 0 auto 40px;
        font-weight: 400;
        animation: rise 0.9s var(--ease) 0.35s both;
    }
    .hero-desc strong {
        color: var(--ink);
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
        border-radius: var(--radius);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        text-align: left;
        transition:
            transform 0.3s var(--ease),
            box-shadow 0.3s var(--ease),
            border-color 0.3s var(--ease),
            background 0.3s var(--ease);
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
        background: linear-gradient(180deg, var(--blue), var(--green));
        transform: scaleY(0);
        transform-origin: center;
        transition: transform 0.35s var(--ease);
    }
    .feature-card:hover {
        transform: translateY(-4px);
        border-color: rgba(30, 80, 162, 0.30);
        background: rgba(255, 255, 255, 0.95);
        box-shadow: 0 20px 40px -18px rgba(20, 40, 90, 0.35);
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
    .feature-icon.blue   { background: rgba(30, 80, 162, 0.13);  color: var(--blue); }
    .feature-icon.green  { background: rgba(59, 165, 92, 0.13);  color: #16a34a; }
    .feature-icon.gold   { background: rgba(240, 160, 32, 0.15); color: #b45309; }
    .feature-icon.red    { background: rgba(239, 68, 68, 0.13);  color: #dc2626; }
    .feature-label {
        font-size: 0.86rem;
        font-weight: 600;
        color: var(--ink);
        line-height: 1.3;
        letter-spacing: -0.005em;
    }

    /* ---------------- CTA ---------------- */
    .cta-wrap {
        animation: rise 0.9s var(--ease) 0.55s both;
        margin-bottom: 40px;
    }
    .cta {
        display: inline-flex;
        align-items: center;
        gap: 12px;
        padding: 18px 40px;
        border-radius: 16px;
        background: linear-gradient(135deg,
            var(--navy) 0%,
            var(--blue) 55%,
            var(--navy) 100%);
        background-size: 200% auto;
        color: #fff;
        font-size: 1.05rem;
        font-weight: 700;
        text-decoration: none;
        letter-spacing: -0.01em;
        position: relative;
        overflow: hidden;
        box-shadow:
            0 22px 44px -14px rgba(20, 40, 90, 0.55),
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
            0 32px 58px -14px rgba(20, 40, 90, 0.7),
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
            rgba(255, 255, 255, 0.28) 50%,
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
        transform: translateX(2px);
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

    /* ---------------- Trust row ---------------- */
    .trust-row {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        align-items: center;
        gap: 10px 22px;
        margin-bottom: 40px;
        padding: 14px 22px;
        background: rgba(255, 255, 255, 0.55);
        border: 1px solid var(--border);
        border-radius: 999px;
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        animation: rise 0.9s var(--ease) 0.62s both;
        max-width: fit-content;
        margin-left: auto;
        margin-right: auto;
    }
    .trust-item {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--ink-2);
        letter-spacing: 0.01em;
    }
    .trust-item i {
        color: var(--blue);
        font-size: 0.9rem;
    }
    .trust-item.green i { color: var(--green); }
    .trust-item.gold  i { color: var(--gold); }
    .trust-sep {
        width: 1px;
        height: 14px;
        background: var(--border);
    }

    /* ---------------- Footer ---------------- */
    .hero-footer {
        display: flex;
        flex-direction: column;
        gap: 12px;
        align-items: center;
        color: var(--ink-3);
        font-size: 0.78rem;
        line-height: 1.6;
        animation: rise 0.9s var(--ease) 0.7s both;
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
        color: var(--ink-2);
        font-weight: 500;
    }
    .hero-footer-credits span {
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .hero-footer-credits i {
        color: var(--ink-3);
        font-size: 0.7rem;
        opacity: 0.75;
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
        .brand-bar { padding: 16px 20px; }
        .brand-bar-inner { padding: 8px 16px 8px 10px; gap: 10px; }
        .brand-bar img { height: 34px; }
        .brand-bar-name { font-size: 0.82rem; }
        .brand-bar-sub { font-size: 0.62rem; }

        .landing-page { padding: 24px 20px 56px; }

        .hero-logo img { height: 88px; }
        .hero-logo::before { inset: -14px -28px; }

        .kicker {
            font-size: 0.68rem;
            letter-spacing: 0.06em;
            padding: 6px 13px 6px 10px;
        }
        .hero-title { margin-bottom: 18px; }
        .hero-desc { margin-bottom: 32px; font-size: 0.98rem; }

        .features {
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 34px;
        }
        .feature-card { padding: 12px 13px; gap: 10px; }
        .feature-icon { width: 34px; height: 34px; font-size: 0.85rem; border-radius: 10px; }
        .feature-label { font-size: 0.78rem; }

        .cta { padding: 16px 30px; font-size: 0.96rem; border-radius: 14px; }
        .cta-wrap { margin-bottom: 32px; }

        .trust-row {
            gap: 8px 14px;
            padding: 12px 16px;
            font-size: 0.74rem;
        }
        .trust-sep { display: none; }

        .hero-footer { font-size: 0.72rem; }
    }

    @media (max-width: 400px) {
        .features { grid-template-columns: 1fr; }
        .brand-bar-text { display: none; }
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

<!-- ============ Small brand bar ============ -->
<div class="brand-bar">
    <div class="brand-bar-inner">
        <img src="includes/css/layout/logo.png" alt="DigiShulk">
        <div class="brand-bar-text">
            <span class="brand-bar-name">DigiShulk</span>
            <span class="brand-bar-sub">RMC Tax Collection</span>
        </div>
    </div>
</div>

<!-- ============ Landing content ============ -->
<main class="landing-page">
    <div class="hero">

        <!-- Hero logo -->
        <div class="hero-logo-wrap">
            <div class="hero-logo">
                <img src="includes/css/layout/logo.png"
                     alt="DigiShulk — RMC Digital Tax Collection">
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
                <div class="feature-icon gold">
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

        <!-- Trust row -->
        <div class="trust-row" aria-label="Platform guarantees">
            <span class="trust-item">
                <i class="fa-solid fa-lock" aria-hidden="true"></i>
                Secure
            </span>
            <span class="trust-sep" aria-hidden="true"></span>
            <span class="trust-item green">
                <i class="fa-solid fa-building-columns" aria-hidden="true"></i>
                Trusted
            </span>
            <span class="trust-sep" aria-hidden="true"></span>
            <span class="trust-item gold">
                <i class="fa-solid fa-mobile-screen" aria-hidden="true"></i>
                Simple
            </span>
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
   Skips generation entirely for reduced-motion users.
   ================================================================ */
(function () {
    'use strict';

    var reduced = window.matchMedia &&
                  window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduced) return;

    var host = document.getElementById('particles');
    if (!host) return;

    var palette = ['', 'gold', 'green'];
    var COUNT = 18;

    for (var i = 0; i < COUNT; i++) {
        var p = document.createElement('span');
        p.className = 'particle ' + palette[Math.floor(Math.random() * palette.length)];

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
   Subtle parallax on background orbs — desktop only.
   ================================================================ */
(function () {
    'use strict';

    var reduced = window.matchMedia &&
                  window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduced) return;
    if (window.innerWidth < 768) return;

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
                    orb.style.transform =
                        'translate(' + (mx * strength) + 'px, ' + (my * strength) + 'px)';
                });
                ticking = false;
            });
        }
    }, { passive: true });
})();
</script>

</body>
</html>