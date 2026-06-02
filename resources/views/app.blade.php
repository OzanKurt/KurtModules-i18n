<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ $bootstrap['csrf'] }}">
    <title>{{ $bootstrap['strings']['title'] ?? 'Translations' }}</title>
    <link rel="stylesheet" href="{{ asset('vendor/i18n/app.css') }}">
</head>
<body class="h-full bg-slate-50 text-slate-900 antialiased">
    <div id="i18n-app" class="min-h-full"></div>
    <script type="application/json" id="i18n-bootstrap">@json($bootstrap)</script>
    <script src="{{ asset('vendor/i18n/app.js') }}" defer></script>
</body>
</html>
