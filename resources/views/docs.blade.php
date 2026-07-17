<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <link rel="stylesheet" href="{{ asset('vendor/laravel-api/docs.css') }}">
    @if ($driver === 'swagger')
        <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
    @endif
</head>
<body>
<div class="docs-shell">
    <header class="docs-header">
        <h1>{{ $title }}</h1>
        <p>OpenAPI reference powered by yoosuf/laravel-api</p>
    </header>

    <main class="docs-main">
        @if ($driver === 'redoc')
            <redoc spec-url="{{ $specUrl }}"></redoc>
        @else
            <div id="swagger-ui"></div>
        @endif
    </main>
</div>

@if ($driver === 'redoc')
    <script src="https://cdn.jsdelivr.net/npm/redoc@2/bundles/redoc.standalone.js"></script>
@else
    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
    <script>
        window.onload = function () {
            window.SwaggerUIBundle({
                url: @json($specUrl),
                dom_id: '#swagger-ui',
                deepLinking: true,
                displayRequestDuration: true,
            });
        };
    </script>
@endif
</body>
</html>
