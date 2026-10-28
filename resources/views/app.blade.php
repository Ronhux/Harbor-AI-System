<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>HarborAI</title>
    <link rel="icon" type="image/png" href="/logo.png">
    <link rel="apple-touch-icon" href="/logo.png">
    @vite(['resources/js/app.tsx'])
    <script src="/theme-init.js"></script>
</head>
<body>
    <div id="app"></div>
</body>
</html>
