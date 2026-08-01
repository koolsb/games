{{--
    The shared page shell: the menu and every game landing page renders
    through here. `game` selects the branding — omit it for the root menu.
--}}
@props(['title' => null, 'game' => null])

@php($theme = $game ? config("games.games.$game.theme") : null)

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full {{ $theme }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="theme-color" content="#18181b">
        <link rel="manifest" href="/site.webmanifest">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png">

        <title>{{ $title ?? config('app.name', 'Games') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @fluxAppearance
        @livewireStyles
    </head>
    <body class="min-h-full bg-zinc-50 text-zinc-800 antialiased dark:bg-zinc-950 dark:text-zinc-200">
        <x-site.header :game="$game" />

        <main class="mx-auto max-w-5xl px-6 py-8">
            {{ $slot }}
        </main>

        @livewireScripts
        @fluxScripts
    </body>
</html>
