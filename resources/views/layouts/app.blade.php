<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'DartScore')</title>
    @vite('resources/css/app.css')
</head>
<body class="bg-dark-bg flex flex-col min-h-screen text-light-text">

    <header class="bg-dark-bg text-light-white py-6 border-b-1 border-light-green">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-2xl font-bold text-light-green">DartScore</h1>
            <nav>
                <a href="/" class="px-3 hover:text-light-green transition duration-300">Strona główna</a>
                <a href="/login" class="px-3 hover:text-light-green transition duration-300">Zaloguj się</a>
            </nav>
        </div>
    </header>

    <main class="container mx-auto py-8 flex-grow flex">
        @yield('content')
    </main>

    <footer class="bg-dark-bg text-light-white py-4 mt-12 text-center border-t border-gray-700">
        &copy; {{ date('Y') }} SunnyVale
    </footer>

</body>
</html>
