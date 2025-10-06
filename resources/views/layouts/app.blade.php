<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'DartScore')</title>
    @vite('resources/css/app.css')
    @vite('resources/js/app.js')
</head>
<body class="bg-dark-bg flex flex-col min-h-screen text-light-text">

    @include('components.notifications')

    @include('layouts.header')

    <main class="container mx-auto flex-grow">
        @yield('content')
    </main>

    @include('layouts.footer')

    @yield('scripts')
</body>
</html>
