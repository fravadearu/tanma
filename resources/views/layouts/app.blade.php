<!DOCTYPE html>
<html data-theme="" lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body data-theme="" class="font-sans antialiased">
    <div data-theme="" class="min-h-screen">
        @include('layouts.navigation')

        <!-- Page Heading -->
        @isset($header)
            <header data-theme="" class="border-b-4 border-accent rounded-md">
                <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                    <h1 class="text-2xl font-bold text-base-content">
                        {{ $header }}
                    </h1>
                </div>
            </header>
        @endisset

        <!-- Page Content -->
        <main data-theme="" class="border-accent rounded-md">
            {{ $slot }}
        </main>
    </div>
    <script>
        const themeSelector = document.getElementById('theme-selector');

        // Load theme on page load
        document.documentElement.setAttribute('data-theme', localStorage.getItem('theme') ||
            'default'); // 'default' is the fallback

        themeSelector.addEventListener('change', function() {
            const selectedTheme = this.value;
            document.documentElement.setAttribute('data-theme', selectedTheme);
            localStorage.setItem('theme', selectedTheme); // Store in local storage
        });
    </script>
</body>

</html>
