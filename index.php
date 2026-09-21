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

    <title>DigiShulk — RMC Digital Tax Collection</title>

    <meta name="description" content="DigiShulk is the digital spot tax and seizure reporting platform for Rajkot Municipal Corporation. UPI and cash collection, instant receipts, real-time field reporting.">
    <meta property="og:title" content="DigiShulk — RMC Digital Tax Collection">
    <meta property="og:description" content="Spot tax collection, seizure reporting, and field operations for Rajkot Municipal Corporation.">
    <meta property="og:type" content="website">

    <!-- Favicon: use the supplied logo mark -->
    <link rel="icon" type="image/png" href="includes/css/layout/logo.png">
    <link rel="apple-touch-icon" href="includes/css/layout/logo.png">

    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
    /* ================================================================
       DigiShulk — Landing page
       Design: municipal-grade. Restrained. Human.
       Palette pulled from the actual logo:
         navy   #14285a
         blue   #1e50a2
         green  #3ba55c
         gold   #f0a020 (used sparingly)
       ================================================================ */

    *, *::before, *::after {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }

    :root {
        --navy:    #14285a;
        --blue:    #1e50a2;
        --blue-lt: #2d69c4;
        --green:   #3ba55c;
        --gold:    #f0a020;
        --ink:     #0b1424;
        --ink-2:   #334155;
        --ink-3:   #64748b;
        --ink-4:   #94a3b8;
        --paper:   #ffffff;
        --paper-2: #f7f8fa;
        --paper-3: #eef1f6;
        --line:    #e2e6ec;
        --line-2:  #cfd6e0;

        --radius:  10px;
        --radius-lg: 14px;
        --ease: cubic-bezier(0.4, 0, 0.2, 1);

        --serif: Georgia, 'Times New Roman', serif;
        --sans: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Inter',
                Roboto, 'Helvetica Neue', Arial, sans-serif;
    }

    html { -webkit-text-size-adjust: 100%; }

    body {
        font-family: var(--sans);
        background: var(--paper);
        color: var(--ink);
        line-height: 1.55;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
        min-height: 100vh;
        display: flex;
        flex-direction: column;
    }

    a { color: inherit; text-decoration: none; }

    /* ---------------- Top strip ---------------- */
    .gov-strip {
        background: var(--navy);
        color: rgba(255, 255, 255, 0.75);
        font-size: 0.75rem;
        letter-spacing: 0.02em;
        padding: 8px 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    }
    .gov-strip-inner {
        max-width: 1120px;
        margin: 0 auto;
        padding: 0 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        flex-wrap: wrap;
    }
    .gov-strip-left,
    .gov-strip-right {
        display: flex;
        align-items: center;
        gap: 18px;
        flex-wrap: wrap;
    }
    .gov-strip-item {
        display: inline-flex;
        align-items: center;
        gap: 7px;
    }
    .gov-strip-item i {
        color: var(--green);
        font-size: 0.7rem;
    }

    /* ---------------- Header ---------------- */
    .site-header {
        background: var(--paper);
        border-bottom: 1px solid var(--line);
        padding: 20px 0;
    }
    .site-header-inner {
        max-width: 1120px;
        margin: 0 auto;
        padding: 0 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
    }
    .brand {
        display: flex;
        align-items: center;
        gap: 14px;
        text-decoration: none;
    }
    .brand-mark {
        width: 48px;
        height: 48px;
        object-fit: contain;
        flex-shrink: 0;
    }
    .brand-text {
        display: flex;
        flex-direction: column;
        line-height: 1.1;
    }
    .brand-name {
        font-family: var(--serif);
        font-size: 1.35rem;
        font-weight: 700;
        color: var(--navy);
        letter-spacing: -0.01em;
    }
    .brand-sub {
        font-size: 0.72rem;
        color: var(--ink-3);
        letter-spacing: 0.06em;
        text-transform: uppercase;
        margin-top: 2px;
        font-weight: 500;
    }

    .header-nav {
        display: flex;
        align-items: center;
        gap: 26px;
        font-size: 0.88rem;
        color: var(--ink-2);
        font-weight: 500;
    }
    .header-nav a {
        position: relative;
        padding: 6px 0;
        transition: color 0.2s var(--ease);
    }
    .header-nav a::after {
        content: '';
        position: absolute;
        left: 0;
        right: 0;
        bottom: 0;
        height: 2px;
        background: var(--blue);
        transform: scaleX(0);
        transform-origin: left;
        transition: transform 0.25s var(--ease);
    }
    .header-nav a:hover {
        color: var(--navy);
    }
    .header-nav a:hover::after {
        transform: scaleX(1);
    }

    /* ---------------- Hero ---------------- */
    .hero {
        flex: 1;
        display: flex;
        align-items: center;
        padding: 72px 0 88px;
        background: var(--paper);
        position: relative;
        overflow: hidden;
    }
    /* A single, quiet diagonal line art — evokes the shape in the logo */
    .hero::before {
        content: '';
        position: absolute;
        top: 0;
        right: 0;
        width: 55%;
        height: 100%;
        background:
            linear-gradient(135deg,
                transparent 0%,
                transparent 42%,
                rgba(30, 80, 162, 0.035) 42%,
                rgba(30, 80, 162, 0.035) 42.4%,
                transparent 42.4%,
                transparent 58%,
                rgba(59, 165, 92, 0.045) 58%,
                rgba(59, 165, 92, 0.045) 58.4%,
                transparent 58.4%);
        pointer-events: none;
    }
    .hero-inner {
        max-width: 1120px;
        margin: 0 auto;
        padding: 0 24px;
        width: 100%;
        display: grid;
        grid-template-columns: 1.15fr 1fr;
        gap: 64px;
        align-items: center;
        position: relative;
    }

    @media (max-width: 900px) {
        .hero-inner {
            grid-template-columns: 1fr;
            gap: 48px;
        }
    }

    .hero-copy {
        animation: enter 0.7s var(--ease) both;
    }
    @keyframes enter {
        from { opacity: 0; transform: translateY(14px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    .hero-kicker {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 0.72rem;
        font-weight: 600;
        letter-spacing: 0.09em;
        text-transform: uppercase;
        color: var(--blue);
        padding: 6px 12px;
        background: rgba(30, 80, 162, 0.07);
        border-radius: 4px;
        margin-bottom: 22px;
    }
    .hero-kicker::before {
        content: '';
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: var(--green);
    }

    .hero-title {
        font-family: var(--serif);
        font-size: clamp(2.4rem, 4.4vw, 3.6rem);
        font-weight: 700;
        line-height: 1.08;
        letter-spacing: -0.02em;
        color: var(--navy);
        margin-bottom: 22px;
    }
    .hero-title em {
        font-style: normal;
        color: var(--green);
    }

    .hero-lede {
        font-size: 1.05rem;
        color: var(--ink-2);
        line-height: 1.65;
        max-width: 52ch;
        margin-bottom: 34px;
    }

    .hero-actions {
        display: flex;
        align-items: center;
        gap: 16px;
        flex-wrap: wrap;
        margin-bottom: 28px;
    }

    .btn {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 13px 24px;
        border-radius: 8px;
        font-size: 0.94rem;
        font-weight: 600;
        letter-spacing: -0.005em;
        cursor: pointer;
        border: 1px solid transparent;
        transition: background 0.2s var(--ease),
                    border-color 0.2s var(--ease),
                    color 0.2s var(--ease),
                    box-shadow 0.2s var(--ease);
        font-family: inherit;
    }
    .btn-primary {
        background: var(--navy);
        color: #fff;
        border-color: var(--navy);
        box-shadow: 0 1px 2px rgba(20, 40, 90, 0.15);
    }
    .btn-primary:hover {
        background: var(--blue);
        border-color: var(--blue);
        box-shadow: 0 6px 18px -6px rgba(20, 40, 90, 0.45);
    }
    .btn-primary i {
        transition: transform 0.2s var(--ease);
        font-size: 0.85rem;
    }
    .btn-primary:hover i {
        transform: translateX(2px);
    }

    .btn-ghost {
        background: transparent;
        color: var(--ink-2);
        border-color: var(--line-2);
    }
    .btn-ghost:hover {
        color: var(--navy);
        border-color: var(--navy);
        background: var(--paper-2);
    }

    .hero-footnote {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 0.8rem;
        color: var(--ink-3);
    }
    .hero-footnote i {
        color: var(--green);
        font-size: 0.85rem;
    }

    /* ---------------- Trust badges ---------------- */
    .trust-row {
        display: flex;
        align-items: center;
        gap: 28px;
        padding: 20px 0 0;
        margin-top: 28px;
        border-top: 1px solid var(--line);
        flex-wrap: wrap;
    }
    .trust-item {
        display: inline-flex;
        align-items: center;
        gap: 9px;
        font-size: 0.82rem;
        color: var(--ink-2);
        font-weight: 500;
    }
    .trust-item i {
        width: 16px;
        text-align: center;
        color: var(--blue);
        font-size: 0.9rem;
    }

    /* ---------------- Right-side illustration ---------------- */
    .hero-visual {
        position: relative;
        animation: enter 0.7s var(--ease) 0.15s both;
    }
    /* Card that holds the logo — feels like a "product seal" */
    .seal-card {
        background: var(--paper);
        border: 1px solid var(--line);
        border-radius: var(--radius-lg);
        padding: 36px 32px 28px;
        box-shadow:
            0 1px 2px rgba(11, 20, 36, 0.04),
            0 12px 40px -18px rgba(20, 40, 90, 0.18);
        position: relative;
    }
    .seal-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 32px;
        right: 32px;
        height: 3px;
        background: linear-gradient(90deg,
            var(--navy) 0%, var(--navy) 40%,
            var(--green) 40%, var(--green) 100%);
        border-radius: 0 0 3px 3px;
    }
    .seal-logo {
        width: 100%;
        max-width: 340px;
        height: auto;
        display: block;
        margin: 6px auto 22px;
    }
    .seal-divider {
        height: 1px;
        background: var(--line);
        margin: 22px 0;
    }
    .seal-meta {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px 24px;
    }
    .seal-meta-item {
        display: flex;
        flex-direction: column;
        gap: 3px;
    }
    .seal-meta-label {
        font-size: 0.68rem;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: var(--ink-4);
        font-weight: 600;
    }
    .seal-meta-value {
        font-size: 0.9rem;
        color: var(--ink);
        font-weight: 600;
        letter-spacing: -0.005em;
    }
    .seal-meta-value.mono {
        font-family: ui-monospace, 'SF Mono', Menlo, Consolas, monospace;
        font-size: 0.82rem;
        color: var(--ink-2);
    }

    @media (max-width: 900px) {
        .hero-visual { max-width: 460px; margin: 0 auto; }
        .seal-meta { grid-template-columns: 1fr 1fr; }
    }

    /* ---------------- Capabilities section ---------------- */
    .capabilities {
        background: var(--paper-2);
        padding: 72px 0;
        border-top: 1px solid var(--line);
    }
    .cap-inner {
        max-width: 1120px;
        margin: 0 auto;
        padding: 0 24px;
    }
    .cap-head {
        max-width: 620px;
        margin-bottom: 44px;
    }
    .cap-head h2 {
        font-family: var(--serif);
        font-size: 1.85rem;
        font-weight: 700;
        color: var(--navy);
        letter-spacing: -0.015em;
        line-height: 1.2;
        margin-bottom: 12px;
    }
    .cap-head p {
        color: var(--ink-3);
        font-size: 1rem;
        line-height: 1.6;
    }

    .cap-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
    }
    @media (max-width: 900px) {
        .cap-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 520px) {
        .cap-grid { grid-template-columns: 1fr; }
    }

    .cap-card {
        background: var(--paper);
        border: 1px solid var(--line);
        border-radius: var(--radius);
        padding: 26px 22px;
        transition: border-color 0.2s var(--ease),
                    transform 0.2s var(--ease),
                    box-shadow 0.2s var(--ease);
    }
    .cap-card:hover {
        border-color: var(--line-2);
        transform: translateY(-2px);
        box-shadow: 0 12px 28px -18px rgba(20, 40, 90, 0.22);
    }
    .cap-icon {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(30, 80, 162, 0.08);
        color: var(--blue);
        font-size: 0.95rem;
        margin-bottom: 18px;
    }
    .cap-icon.green { background: rgba(59, 165, 92, 0.10); color: var(--green); }
    .cap-icon.gold  { background: rgba(240, 160, 32, 0.11); color: #b6771a; }
    .cap-icon.navy  { background: rgba(20, 40, 90, 0.08);  color: var(--navy); }

    .cap-title {
        font-size: 0.98rem;
        font-weight: 700;
        color: var(--ink);
        margin-bottom: 8px;
        letter-spacing: -0.005em;
    }
    .cap-desc {
        font-size: 0.86rem;
        color: var(--ink-3);
        line-height: 1.55;
    }

    /* ---------------- CTA band ---------------- */
    .cta-band {
        background: var(--navy);
        color: #fff;
        padding: 56px 0;
    }
    .cta-band-inner {
        max-width: 1120px;
        margin: 0 auto;
        padding: 0 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 32px;
        flex-wrap: wrap;
    }
    .cta-band-text h2 {
        font-family: var(--serif);
        font-size: 1.6rem;
        font-weight: 700;
        letter-spacing: -0.015em;
        margin-bottom: 6px;
        line-height: 1.25;
    }
    .cta-band-text p {
        color: rgba(255, 255, 255, 0.7);
        font-size: 0.95rem;
        max-width: 52ch;
        line-height: 1.55;
    }
    .cta-band .btn-primary {
        background: #fff;
        color: var(--navy);
        border-color: #fff;
        padding: 14px 26px;
    }
    .cta-band .btn-primary:hover {
        background: var(--paper-2);
        border-color: var(--paper-2);
        box-shadow: 0 10px 26px -10px rgba(0, 0, 0, 0.5);
    }

    /* ---------------- Footer ---------------- */
    .site-footer {
        background: var(--paper);
        border-top: 1px solid var(--line);
        padding: 44px 0 28px;
        font-size: 0.83rem;
        color: var(--ink-3);
    }
    .footer-inner {
        max-width: 1120px;
        margin: 0 auto;
        padding: 0 24px;
        display: grid;
        grid-template-columns: 1.4fr 1fr 1fr;
        gap: 40px;
        margin-bottom: 32px;
    }
    @media (max-width: 720px) {
        .footer-inner { grid-template-columns: 1fr; gap: 28px; }
    }
    .footer-brand {
        display: flex;
        align-items: flex-start;
        gap: 14px;
    }
    .footer-brand img {
        width: 40px;
        height: 40px;
        object-fit: contain;
        flex-shrink: 0;
        margin-top: 2px;
    }
    .footer-brand-text p {
        color: var(--ink-3);
        line-height: 1.6;
        margin-top: 6px;
        max-width: 36ch;
    }
    .footer-brand-name {
        font-family: var(--serif);
        font-size: 1rem;
        font-weight: 700;
        color: var(--navy);
    }
    .footer-col h4 {
        font-size: 0.72rem;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: var(--ink-4);
        margin-bottom: 14px;
        font-weight: 700;
    }
    .footer-col ul {
        list-style: none;
        display: flex;
        flex-direction: column;
        gap: 9px;
    }
    .footer-col li {
        color: var(--ink-2);
        font-size: 0.86rem;
        display: flex;
        align-items: flex-start;
        gap: 9px;
    }
    .footer-col li i {
        color: var(--ink-4);
        font-size: 0.75rem;
        margin-top: 5px;
        width: 12px;
        text-align: center;
        flex-shrink: 0;
    }
    .footer-col a {
        transition: color 0.15s var(--ease);
    }
    .footer-col a:hover {
        color: var(--blue);
    }

    .footer-bottom {
        max-width: 1120px;
        margin: 0 auto;
        padding: 20px 24px 0;
        border-top: 1px solid var(--line);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
        font-size: 0.78rem;
        color: var(--ink-4);
    }
    .footer-version {
        font-family: ui-monospace, 'SF Mono', Menlo, Consolas, monospace;
        font-size: 0.72rem;
        padding: 3px 8px;
        background: var(--paper-2);
        border-radius: 4px;
        color: var(--ink-3);
        letter-spacing: 0.02em;
    }

    /* ---------------- Reduced motion ---------------- */
    @media (prefers-reduced-motion: reduce) {
        *, *::before, *::after {
            animation-duration: 0.01ms !important;
            transition-duration: 0.01ms !important;
        }
    }

    /* ---------------- Mobile ---------------- */
    @media (max-width: 640px) {
        .gov-strip-inner { justify-content: center; text-align: center; }
        .gov-strip { font-size: 0.7rem; }

        .site-header { padding: 16px 0; }
        .brand-mark { width: 40px; height: 40px; }
        .brand-name { font-size: 1.15rem; }
        .brand-sub { font-size: 0.66rem; }
        .header-nav { display: none; }

        .hero { padding: 44px 0 56px; }
        .hero-title { margin-bottom: 18px; }
        .hero-lede { font-size: 0.98rem; margin-bottom: 26px; }
        .hero-actions { gap: 10px; }
        .btn { padding: 12px 20px; font-size: 0.9rem; }
        .hero-actions .btn { flex: 1; justify-content: center; }

        .trust-row {
            gap: 18px;
            padding-top: 18px;
            margin-top: 22px;
        }
        .trust-item { font-size: 0.78rem; }

        .seal-card { padding: 26px 22px 22px; }
        .seal-logo { max-width: 260px; margin-bottom: 16px; }

        .capabilities { padding: 52px 0; }
        .cap-head { margin-bottom: 30px; }
        .cap-head h2 { font-size: 1.5rem; }

        .cta-band { padding: 40px 0; }
        .cta-band-text h2 { font-size: 1.3rem; }
        .cta-band-inner { gap: 22px; }
        .cta-band .btn-primary { width: 100%; justify-content: center; }

        .site-footer { padding: 36px 0 22px; }
        .footer-bottom { flex-direction: column; text-align: center; gap: 10px; }
    }
    </style>
</head>
<body>

<!-- ============ Government strip ============ -->
<div class="gov-strip">
    <div class="gov-strip-inner">
        <div class="gov-strip-left">
            <span class="gov-strip-item">
                <i class="fa-solid fa-landmark" aria-hidden="true"></i>
                An initiative of Rajkot Municipal Corporation
            </span>
        </div>
        <div class="gov-strip-right">
            <span class="gov-strip-item">
                <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                Official portal
            </span>
            <span class="gov-strip-item">
                <i class="fa-solid fa-headset" aria-hidden="true"></i>
                Support: 0281–222–0000
            </span>
        </div>
    </div>
</div>

<!-- ============ Header ============ -->
<header class="site-header">
    <div class="site-header-inner">
        <a href="index.php" class="brand" aria-label="DigiShulk home">
            <img src="includes/css/layout/logo.png"
                 alt="DigiShulk"
                 class="brand-mark">
            <span class="brand-text">
                <span class="brand-name">DigiShulk</span>
                <span class="brand-sub">RMC Tax Collection</span>
            </span>
        </a>

        <nav class="header-nav" aria-label="Primary">
            <a href="#capabilities">Capabilities</a>
            <a href="#about">About</a>
            <a href="includes/login.php">Sign in</a>
        </nav>
    </div>
</header>

<!-- ============ Hero ============ -->
<main class="hero">
    <div class="hero-inner">

        <div class="hero-copy">
            <div class="hero-kicker">Field-ready since 2024</div>

            <h1 class="hero-title">
                Collect smarter.<br>
                Report cleaner.<br>
                Serve <em>faster</em>.
            </h1>

            <p class="hero-lede">
                DigiShulk replaces paper slips and month-end reconciliation with
                a single field app — spot tax collection, seizure logging, and
                instant receipts, all synced back to RMC's central records in
                real time.
            </p>

            <div class="hero-actions">
                <a href="includes/login.php" class="btn btn-primary">
                    <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>
                    Sign in to your account
                </a>
                <a href="#capabilities" class="btn btn-ghost">
                    Learn more
                </a>
            </div>

            <div class="hero-footnote">
                <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                <span>Authorised RMC personnel only. Single sign-on enforced.</span>
            </div>

            <div class="trust-row" aria-label="Platform guarantees">
                <span class="trust-item">
                    <i class="fa-solid fa-lock" aria-hidden="true"></i>
                    Secure
                </span>
                <span class="trust-item">
                    <i class="fa-solid fa-building-columns" aria-hidden="true"></i>
                    Trusted
                </span>
                <span class="trust-item">
                    <i class="fa-solid fa-mobile-screen" aria-hidden="true"></i>
                    Simple
                </span>
            </div>
        </div>

        <div class="hero-visual">
            <div class="seal-card">
                <img src="includes/css/layout/logo.png"
                     alt="DigiShulk — RMC Digital Tax Collection"
                     class="seal-logo">

                <div class="seal-divider"></div>

                <div class="seal-meta">
                    <div class="seal-meta-item">
                        <span class="seal-meta-label">Deployment</span>
                        <span class="seal-meta-value">Rajkot, Gujarat</span>
                    </div>
                    <div class="seal-meta-item">
                        <span class="seal-meta-label">System status</span>
                        <span class="seal-meta-value" style="color: var(--green);">
                            <i class="fa-solid fa-circle" style="font-size: 0.5rem; vertical-align: middle; margin-right: 5px;" aria-hidden="true"></i>
                            Operational
                        </span>
                    </div>
                    <div class="seal-meta-item">
                        <span class="seal-meta-label">Payments via</span>
                        <span class="seal-meta-value">UPI &amp; Cash</span>
                    </div>
                    <div class="seal-meta-item">
                        <span class="seal-meta-label">Build</span>
                        <span class="seal-meta-value mono">v1.0.0</span>
                    </div>
                </div>
            </div>
        </div>

    </div>
</main>

<!-- ============ Capabilities ============ -->
<section class="capabilities" id="capabilities">
    <div class="cap-inner">

        <div class="cap-head">
            <h2>Built for the field, not the desk.</h2>
            <p>
                Every feature in DigiShulk was designed around what inspectors
                actually need on the ground — one-handed use, unreliable
                networks, and shopkeepers who want a receipt in hand.
            </p>
        </div>

        <div class="cap-grid">

            <div class="cap-card">
                <div class="cap-icon">
                    <i class="fa-solid fa-receipt" aria-hidden="true"></i>
                </div>
                <h3 class="cap-title">Spot tax collection</h3>
                <p class="cap-desc">
                    Inspector selects a stall type, enters size, and the fee is
                    calculated instantly from the official rates table.
                </p>
            </div>

            <div class="cap-card">
                <div class="cap-icon green">
                    <i class="fa-solid fa-qrcode" aria-hidden="true"></i>
                </div>
                <h3 class="cap-title">UPI &amp; cash payments</h3>
                <p class="cap-desc">
                    A QR code appears the moment UPI is chosen. Cash collections
                    are confirmed with a single tap.
                </p>
            </div>

            <div class="cap-card">
                <div class="cap-icon gold">
                    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                </div>
                <h3 class="cap-title">Seizure reporting</h3>
                <p class="cap-desc">
                    Structured item logging with owner, location, godown register
                    number, and estimated value — all in one form.
                </p>
            </div>

            <div class="cap-card">
                <div class="cap-icon navy">
                    <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
                </div>
                <h3 class="cap-title">Supervisor oversight</h3>
                <p class="cap-desc">
                    Zone-wise dashboards, undercharge detection, and instant CSV
                    or PDF exports for review meetings.
                </p>
            </div>

        </div>
    </div>
</section>

<!-- ============ CTA band ============ -->
<section class="cta-band">
    <div class="cta-band-inner">
        <div class="cta-band-text">
            <h2>Ready when you are.</h2>
            <p>
                Sign in with your RMC credentials to start collecting. If you
                don't have an account yet, contact your zone supervisor.
            </p>
        </div>
        <a href="includes/login.php" class="btn btn-primary">
            <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>
            Sign in
        </a>
    </div>
</section>

<!-- ============ Footer ============ -->
<footer class="site-footer" id="about">
    <div class="footer-inner">

        <div class="footer-brand">
            <img src="includes/css/layout/logo.png" alt="DigiShulk">
            <div class="footer-brand-text">
                <div class="footer-brand-name">DigiShulk</div>
                <p>
                    A digital platform for RMC to streamline spot tax collection,
                    seizure reporting, and field operations.
                </p>
            </div>
        </div>

        <div class="footer-col">
            <h4>Contact</h4>
            <ul>
                <li>
                    <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                    <span>Rajkot Municipal Corporation,<br>Dhebarbhai Road, Rajkot 360001</span>
                </li>
                <li>
                    <i class="fa-solid fa-phone" aria-hidden="true"></i>
                    <span>0281–222–0000</span>
                </li>
                <li>
                    <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                    <span><a href="mailto:support@rmc.gov.in">support@rmc.gov.in</a></span>
                </li>
            </ul>
        </div>

        <div class="footer-col">
            <h4>Support hours</h4>
            <ul>
                <li>
                    <i class="fa-solid fa-clock" aria-hidden="true"></i>
                    <span>Mon–Fri, 10:00 – 18:00 IST</span>
                </li>
                <li>
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                    <span>For account issues, contact your zone supervisor first.</span>
                </li>
            </ul>
        </div>

    </div>

    <div class="footer-bottom">
        <span>
            &copy; <?= date('Y') ?> Rajkot Municipal Corporation. All rights reserved.
        </span>
        <span class="footer-version">DigiShulk v1.0.0</span>
    </div>
</footer>

</body>
</html>