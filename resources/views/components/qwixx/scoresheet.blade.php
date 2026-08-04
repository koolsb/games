{{--
    A full player scoresheet: 4 rows, points legend, penalties, totals.

    `playerIndex` is interpolated straight into the Alpine expressions below,
    so it takes a literal index (0, 1) for a local game or the name of a
    reactive property ("me") for a multiplayer one, where this device's seat
    depends on the order people joined.
--}}
@props(['layout', 'playerIndex' => 0, 'compact' => false, 'mode' => 'solo'])

@php($p = $playerIndex)

<div
    {{ $attributes->class([
        'qx-sheet relative flex flex-col gap-[calc(var(--qx-cell)*0.16)] rounded-2xl bg-white p-[calc(var(--qx-cell)*0.3)] shadow-lg ring-1 ring-zinc-200 dark:bg-zinc-900 dark:ring-zinc-800',
        'qx-compact' => $compact,
    ]) }}
>
    <div class="flex items-end justify-between gap-2 px-1">
        <span class="text-[calc(var(--qx-cell)*0.32)] font-black tracking-tight">
            QWI<span class="text-qwixx-red">X</span><span class="text-qwixx-blue">X</span>
        </span>

        {{-- Whose sheet this is. Editable in a 2-player game, where both
             sheets share a device; in a room the name came from joining. --}}
        @if ($mode === 'duo')
            <input
                type="text"
                maxlength="14"
                class="min-w-0 flex-1 truncate rounded-md bg-transparent px-1 text-center text-[calc(var(--qx-cell)*0.26)] font-bold text-zinc-500 hover:bg-zinc-100 focus:bg-zinc-100 focus:outline-none dark:text-zinc-400 dark:hover:bg-zinc-800 dark:focus:bg-zinc-800"
                aria-label="Player name"
                x-bind:value="playerName({{ $p }})"
                x-on:change="setName({{ $p }}, $event.target.value)"
            />
        @elseif ($mode === 'multi')
            <span
                class="min-w-0 flex-1 truncate text-center text-[calc(var(--qx-cell)*0.26)] font-bold text-zinc-500 dark:text-zinc-400"
                x-text="playerName({{ $p }})"
            ></span>
        @endif

        <span class="text-[calc(var(--qx-cell)*0.22)] font-semibold text-zinc-400">At least 5 ✕'s to lock</span>
    </div>

    @foreach ($layout->rows as $row)
        <x-qwixx.row :row="$row" :player-index="$p" />
    @endforeach

    <div class="flex flex-wrap items-center justify-between gap-x-6 gap-y-[calc(var(--qx-cell)*0.16)] pt-[calc(var(--qx-cell)*0.08)]">
        {{-- Points legend, as printed on the pad. --}}
        <div class="flex items-stretch overflow-hidden rounded-md border border-zinc-300 text-center dark:border-zinc-600">
            @foreach ([1, 3, 6, 10, 15, 21, 28, 36, 45, 55, 66, 78] as $points)
                <div class="flex flex-col border-r border-zinc-300 px-[calc(var(--qx-cell)*0.09)] py-0.5 last:border-r-0 dark:border-zinc-600">
                    <span class="text-[calc(var(--qx-cell)*0.17)] font-bold text-zinc-400">{{ $loop->iteration }}✕</span>
                    <span class="text-[calc(var(--qx-cell)*0.2)] font-black text-zinc-600 dark:text-zinc-300">{{ $points }}</span>
                </div>
            @endforeach
        </div>

        <x-qwixx.penalties :player-index="$p" />
    </div>

    <div class="flex items-center justify-between gap-4">
        <x-qwixx.score-bar :player-index="$p" />
    </div>

    {{--
        Game over banner: rendered inside the sheet so it rotates with it in
        duo mode. It lies across the middle rows, so it can be put away —
        otherwise the cells you need to reach to correct a mistake are the
        ones underneath it.
    --}}
    <div
        x-show="gameOver && ! bannerDismissed"
        x-cloak
        class="pointer-events-none absolute inset-x-0 top-1/2 z-10 -translate-y-1/2 px-[calc(var(--qx-cell)*0.6)]"
    >
        <div class="pointer-events-auto flex items-center justify-between gap-4 rounded-xl bg-zinc-900/95 px-5 py-3 text-white shadow-2xl ring-1 ring-white/20 dark:bg-zinc-100/95 dark:text-zinc-900">
            <div>
                <div class="text-[calc(var(--qx-cell)*0.34)] font-black">Game over!</div>

                @if ($mode === 'solo')
                    <div class="text-[calc(var(--qx-cell)*0.24)] font-medium opacity-80">
                        <span x-text="gameOverReason"></span>
                        Final score: <span class="font-black" x-text="totalFor({{ $p }})"></span>
                    </div>
                @else
                    <x-qwixx.results />
                @endif
            </div>

            <div class="flex shrink-0 items-center gap-2">
                {{-- A game can end on a mistap — the fourth penalty, or a
                     lock nobody meant to take. The banner covers the cells
                     you would tap to undo it, so it offers the undo itself. --}}
                <flux:button size="sm" variant="filled" x-show="undo" x-cloak x-on:click="undoLast()">
                    Undo last <span x-text="undo?.label"></span>
                </flux:button>

                {{-- Put the banner away to get at the sheet underneath. --}}
                <button
                    type="button"
                    class="flex size-7 shrink-0 items-center justify-center rounded-full text-white/70 hover:bg-white/10 hover:text-white dark:text-zinc-500 dark:hover:bg-zinc-900/10 dark:hover:text-zinc-900"
                    aria-label="Hide this and show the sheet"
                    title="Hide this and show the sheet"
                    x-on:click="bannerDismissed = true"
                >
                    <flux:icon.x-mark class="size-4" />
                </button>

                {{-- In a room only the host deals the next game, so everyone's
                     sheets clear at the same moment. The condition is baked into
                     the x-show expression rather than wrapped around the tag:
                     Blade directives can't live inside a component tag's
                     attribute list. --}}
                <flux:modal.trigger name="reset-confirm">
                    <flux:button size="sm" variant="primary" x-show="{{ $mode === 'multi' ? 'isHost' : 'true' }}" x-cloak>
                        New game
                    </flux:button>
                </flux:modal.trigger>
            </div>
        </div>
    </div>

    {{-- Hiding the banner is not one-way: this brings the result back. It
         disappears by itself the moment the game is no longer over. --}}
    <button
        type="button"
        x-show="gameOver && bannerDismissed"
        x-cloak
        x-on:click="bannerDismissed = false"
        class="absolute right-[calc(var(--qx-cell)*0.3)] top-[calc(var(--qx-cell)*0.3)] z-10 rounded-full bg-zinc-900/90 px-[calc(var(--qx-cell)*0.3)] py-[calc(var(--qx-cell)*0.08)] text-[calc(var(--qx-cell)*0.22)] font-bold text-white shadow-lg dark:bg-zinc-100/90 dark:text-zinc-900"
    >
        Game over — show result
    </button>
</div>
