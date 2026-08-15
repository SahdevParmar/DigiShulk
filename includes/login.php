<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - DigiShulk</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="style2.css">
</head>
<body>
    <main class="landing-page">
        <div class="page" style="max-width: 420px;">
            <div class="logo" style="margin: 0 auto var(--space-6); width: 140px; height: 42px;"></div>

            <h1 style="font-size: var(--text-2xl); font-weight: 700; text-align: center; margin-bottom: var(--space-1);">Welcome Back</h1>
            <p style="text-align: center; color: var(--color-text-muted); margin-bottom: var(--space-6);">Sign in to your DigiShulk account</p>

            <form action="auth.php" method="POST" novalidate>
                <div class="form-field">
                    <label class="form-label" for="username">Username</label>
                    <input
                        type="text"
                        name="username"
                        id="username"
                        class="form-input"
                        placeholder="Enter your username"
                        required
                        autocomplete="username"
                        autofocus>
                </div>

                <div class="form-field">
                    <label class="form-label" for="password">Password</label>
                    <input
                        type="password"
                        name="password"
                        id="password"
                        class="form-input"
                        placeholder="Enter your password"
                        required
                        autocomplete="current-password">
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top: var(--space-2);">
                    <i class="fa-solid fa-sign-in" aria-hidden="true"></i>
                    Sign In
                </button>
            </form>

            <p style="margin-top: var(--space-6); text-align: center; font-size: var(--text-sm); color: var(--color-text-subtle);">
                DigiShulk &mdash; RMC Digital Tax Collection System
            </p>
        </div>
    </main>
</body>
</html>