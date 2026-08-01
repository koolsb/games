{{--
    Chromeless shell for screens that own the whole viewport (the Qwixx
    scoresheet on a tablet). No site header — those pages carry their own
    slim toolbar with a link back to the game's landing page.
--}}
@props(['title' => null, 'game' => null])

@php($theme = $game ? config("games.games.$game.theme") : null)

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full {{ $theme }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">
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
    <body class="h-dvh overflow-hidden bg-zinc-100 text-zinc-800 antialiased dark:bg-zinc-950 dark:text-zinc-200">
        {{ $slot }}

        @livewireScripts
        @fluxScripts
    </body>
</html>
