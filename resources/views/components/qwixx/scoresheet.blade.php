{{-- A full player scoresheet: 4 rows, points legend, penalties, totals. --}}
@props(['layout', 'playerIndex' => 0, 'compact' => false])

@php($p = $playerIndex)

<div
    {{ $attributes->class([
        'qx-sheet relative flex flex-col gap-[calc(var(--qx-cell)*0.16)] rounded-2xl bg-white p-[calc(var(--qx-cell)*0.3)] shadow-lg ring-1 ring-zinc-200 dark:bg-zinc-900 dark:ring-zinc-800',
        'qx-compact' => $compact,
    ]) }}
>
    <div class="flex items-end justify-between px-1">
        <span class="text-[calc(var(--qx-cell)*0.32)] font-black tracking-tight">
            QWI<span class="text-qwixx-red">X</span><span class="text-qwixx-blue">X</span>
        </span>
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

    {{-- Game over banner: rendered inside the sheet so it rotates with it in duo mode. --}}
    <div
        x-show="gameOver"
        x-cloak
        class="pointer-events-none absolute inset-x-0 top-1/2 z-10 -translate-y-1/2 px-[calc(var(--qx-cell)*0.6)]"
    >
        <div class="pointer-events-auto flex items-center justify-between gap-4 rounded-xl bg-zinc-900/95 px-5 py-3 text-white shadow-2xl ring-1 ring-white/20 dark:bg-zinc-100/95 dark:text-zinc-900">
            <div>
                <div class="text-[calc(var(--qx-cell)*0.34)] font-black">Game over!</div>
                <div class="text-[calc(var(--qx-cell)*0.24)] font-medium opacity-80">
                    <span x-text="gameOverReason"></span>
                    Final score: <span class="font-black" x-text="totalFor({{ $p }})"></span>
                </div>
            </div>
            <flux:modal.trigger name="reset-confirm">
                <flux:button size="sm" variant="primary">New game</flux:button>
            </flux:modal.trigger>
        </div>
    </div>
</div>
