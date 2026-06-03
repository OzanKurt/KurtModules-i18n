<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ $bootstrap['csrf'] }}">
    <title>i18n Studio · {{ $bootstrap['strings']['title'] ?? 'Translations' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@500;600;700&family=Sora:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('vendor/i18n/app.css') }}">
</head>
<body>
    <div class="bg"></div>
    <div class="bg-grid"></div>
    <div class="bg-noise"></div>

    <div class="shell">
        <header class="topbar">
            <div class="brand">
                <span class="mark" aria-hidden="true"></span>
                <span class="name">i18n&nbsp;<b>Studio</b></span>
            </div>
            <div id="crumbs" class="crumbs"></div>
            <span class="spacer"></span>
            <div id="topactions"></div>
        </header>
        <main id="view"></main>
    </div>

    <script type="application/json" id="i18n-bootstrap">@json($bootstrap)</script>
    <script src="{{ asset('vendor/i18n/app.js') }}" defer></script>
</body>
</html>
