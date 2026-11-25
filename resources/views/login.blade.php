<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="/favicon-smart-calendar.png">
    <title>Accesso Planner</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Newsreader:ital,opsz,wght@0,6..72,400;0,6..72,500;1,6..72,400;1,6..72,500&display=swap">
    @vite(['resources/css/app.css'])
</head>
<body class="auth-page">
    <main class="auth-card surface">
        <div class="auth-brand">
            <span class="brand-mark">C</span>
            <div>
                <p class="eyebrow">Calendario intelligente</p>
                <h1>Accedi al tuo planner</h1>
            </div>
        </div>

        <form method="post" action="{{ route('login.store') }}" class="stack">
            @csrf
            <label class="field">
                <span>Email</span>
                <input name="email" type="email" value="{{ old('email', 'admin@example.com') }}" required autofocus>
            </label>
            <label class="field">
                <span>Password</span>
                <input name="password" type="password" value="password" required>
            </label>
            <label class="check-row">
                <input type="checkbox" name="remember" value="1">
                <span>Ricordami</span>
            </label>

            @if ($errors->any())
                <div class="alert">{{ $errors->first() }}</div>
            @endif

            <button class="button filled" type="submit">Entra</button>
        </form>
    </main>
</body>
</html>
