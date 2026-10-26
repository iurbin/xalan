<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-page" data-page="login">
    <main class="auth-card">
        <a class="brand auth-brand" href="{{ route('login') }}">
            <span class="brand-mark">F</span>
            <span>Force<span class="brand-light">Ops</span><small>FIELD SERVICE</small></span>
        </a>
        <p class="eyebrow mt-5 mb-2">MAINTENANCE, IN CONTROL</p>
        <h1 id="auth-heading">Welcome back</h1>
        <p class="auth-copy" id="auth-copy">Sign in to manage your field operations.</p>

        <form id="auth-form" class="mt-4">
            <div class="mb-3 d-none" id="tenant-name-field">
                <label class="form-label" for="tenant_name">Company name</label>
                <input class="form-control" id="tenant_name" name="tenant_name" autocomplete="organization">
            </div>
            <div class="mb-3 d-none" id="name-field">
                <label class="form-label" for="name">Your name</label>
                <input class="form-control" id="name" name="name" autocomplete="name">
            </div>
            <div class="mb-3">
                <label class="form-label" for="email">Email address</label>
                <input class="form-control" type="email" id="email" name="email" autocomplete="email" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="password">Password</label>
                <input class="form-control" type="password" id="password" name="password" autocomplete="current-password" required>
            </div>
            <div class="mb-3 d-none" id="password-confirmation-field">
                <label class="form-label" for="password_confirmation">Confirm password</label>
                <input class="form-control" type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password">
            </div>
            <div class="form-error d-none" id="auth-error" role="alert"></div>
            <button class="btn btn-primary w-100 mt-2" type="submit" id="auth-submit">Sign in</button>
        </form>
        <p class="auth-switch">
            <span id="auth-switch-copy">New to ForceOps?</span>
            <button type="button" class="link-button" id="auth-mode-toggle">Create an account</button>
        </p>
    </main>
</body>
</html>
