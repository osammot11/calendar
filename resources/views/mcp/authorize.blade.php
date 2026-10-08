<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Collega {{ $client->name }} | Calendario</title>
    <link rel="icon" href="/favicon.ico">
    <style>
        :root { color-scheme: light; font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #f4f6f7; color: #172026; }
        * { box-sizing: border-box; }
        body { min-height: 100vh; margin: 0; display: grid; place-items: center; padding: 24px; }
        main { width: min(100%, 460px); background: #fff; border: 1px solid #dce2e4; border-radius: 8px; box-shadow: 0 12px 32px #17202612; padding: 32px; }
        .brand { display: flex; align-items: center; gap: 12px; margin-bottom: 30px; font-size: 15px; font-weight: 650; }
        .brand-mark { width: 36px; height: 36px; display: grid; place-items: center; border-radius: 8px; background: #0d7777; color: #fff; font-size: 21px; }
        h1 { font-size: 24px; line-height: 1.2; margin: 0 0 12px; font-weight: 650; }
        p { margin: 0; line-height: 1.5; color: #526169; }
        .identity { margin-top: 28px; padding: 16px; border-radius: 8px; background: #eef5f4; border: 1px solid #d8e8e5; }
        .identity span { display: block; color: #526169; font-size: 13px; margin-bottom: 4px; }
        .identity strong { font-size: 15px; overflow-wrap: anywhere; }
        .scope { margin: 20px 0 28px; display: flex; gap: 10px; align-items: flex-start; font-size: 14px; line-height: 1.45; }
        .scope-mark { flex: 0 0 20px; height: 20px; border-radius: 50%; display: grid; place-items: center; background: #d5eeeb; color: #075f5f; font-weight: 700; }
        .actions { display: flex; justify-content: flex-end; gap: 10px; }
        form { margin: 0; }
        button { min-height: 42px; padding: 0 18px; border-radius: 8px; border: 1px solid #0d7777; font: inherit; font-size: 14px; font-weight: 650; cursor: pointer; }
        button:focus-visible { outline: 3px solid #ffbd73; outline-offset: 2px; }
        .cancel { background: #fff; color: #0d7777; }
        .approve { background: #0d7777; color: #fff; }
        button:hover { filter: brightness(.94); }
        @media (max-width: 480px) { main { padding: 24px; } .actions { flex-direction: column-reverse; } .actions form, button { width: 100%; } }
    </style>
</head>
<body>
    <main>
        <div class="brand"><span class="brand-mark" aria-hidden="true">C</span> Calendario</div>
        <h1>Collega {{ $client->name }}</h1>
        <p>Questa app potrà leggere il tuo calendario e creare, modificare o completare task quando lo richiedi in chat.</p>

        <div class="identity">
            <span>Account collegato</span>
            <strong>{{ $user->email }}</strong>
        </div>

        <div class="scope"><span class="scope-mark" aria-hidden="true">✓</span><span>Accesso al calendario personale e alle task.</span></div>

        <div class="actions">
            <form method="POST" action="{{ route('passport.authorizations.deny') }}">
                @csrf
                @method('DELETE')
                <input type="hidden" name="state" value="">
                <input type="hidden" name="client_id" value="{{ $client->id }}">
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <button class="cancel" type="submit">Annulla</button>
            </form>
            <form method="POST" action="{{ route('passport.authorizations.approve') }}">
                @csrf
                <input type="hidden" name="state" value="">
                <input type="hidden" name="client_id" value="{{ $client->id }}">
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <button class="approve" type="submit">Autorizza</button>
            </form>
        </div>
    </main>
</body>
</html>
