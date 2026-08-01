{{--
    The shared site header. On a game page it wears that game's colors and
    always carries an "All games" control back to the root menu; on the menu
    itself it is just the hub's own bar.
--}}
@props(['game' => null])

@php($config = $game ? config("games.games.$game") : null)

<header class="{{ $config['header'] ?? 'bg-zinc-900' }} text-white">
    <div class="mx-auto flex max-w-5xl items-center justify-between gap-3 px-6 py-4">
        <div class="flex min-w-0 items-center gap-2">
            @if ($config)
                <flux:button
                    href="{{ route('home') }}"
                    variant="ghost"
                    size="sm"
                    icon="squares-2x2"
                    class="text-white!"
                    title="Back to all games"
                >
                    <span class="sr-only sm:not-sr-only">All games</span>
                </flux:button>
                <span class="text-white/30" aria-hidden="true">/</span>
            @endif

            <a href="{{ $config ? route($config['route']) : route('home') }}" class="flex min-w-0 items-center gap-3">
                <span class="truncate text-2xl font-black tracking-tight">
                    <x-site.wordmark :game="$game" />
                </span>
                <span class="hidden text-sm font-medium text-white/70 sm:inline">
                    {{ $config['tagline'] ?? 'kools.us' }}
                </span>
            </a>
        </div>

        <div class="flex shrink-0 items-center gap-1">
            <flux:button
                variant="ghost"
                size="sm"
                class="text-white!"
                x-data
                x-on:click="$flux.dark = ! $flux.dark"
                title="Light / dark mode"
            >
                <flux:icon.sun class="size-4" x-show="! $flux.dark" x-cloak />
                <flux:icon.moon class="size-4" x-show="$flux.dark" x-cloak />
            </flux:button>

            @if ($config)
                <flux:button
                    href="{{ $config['rules_url'] }}"
                    target="_blank"
                    variant="ghost"
                    size="sm"
                    class="text-white!"
                >
                    Rules
                </flux:button>
            @endif
        </div>
    </div>

    <div class="{{ $config['stripe'] ?? 'games-stripe' }} h-1.5 w-full"></div>
</header>
