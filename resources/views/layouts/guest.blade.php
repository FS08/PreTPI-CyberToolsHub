<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Cyber Tools Hub') }}</title>

        <!-- Favicon -->
        <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
            <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gradient-to-b from-indigo-50 to-white dark:from-gray-900 dark:to-gray-800">
            <div>
                <a href="/">
                    {{-- Smaller logo with rounded corners --}}
                    <img src="{{ asset('cth_logo.png') }}"
                         alt="Cyber Tools Hub"
                         class="w-38 h-auto rounded-lg shadow-sm border-4 border-white">
                </a>
            </div>

            <div class="w-full sm:max-w-md mt-6 rounded-xl bg-white border p-6 dark:bg-gray-800 dark:border-gray-700 space-y-3">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
