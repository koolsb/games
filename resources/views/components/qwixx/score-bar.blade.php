{{-- Live totals strip: four color scores, minus penalties, equals total. --}}
@props(['playerIndex'])

@php($p = $playerIndex)

<div class="flex flex-wrap items-center gap-[calc(var(--qx-cell)*0.12)] text-[calc(var(--qx-cell)*0.34)] font-black">
    <span class="mr-1 text-[calc(var(--qx-cell)*0.26)] font-bold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">totals</span>

    @foreach (['red', 'yellow', 'green', 'blue'] as $color)
        <span
            class="flex h-[calc(var(--qx-cell)*0.85)] min-w-[calc(var(--qx-cell)*1.15)] items-center justify-center rounded-lg border-2 bg-white px-1 dark:bg-zinc-800"
            style="border-color: var(--color-qwixx-{{ $color }}); color: var(--color-qwixx-{{ $color }})"
            x-text="score({{ $p }}, '{{ $color }}')"
        ></span>
        <span class="text-zinc-400">{{ $loop->last ? '−' : '+' }}</span>
    @endforeach

    <span
        class="flex h-[calc(var(--qx-cell)*0.85)] min-w-[calc(var(--qx-cell)*1.15)] items-center justify-center rounded-lg border-2 border-zinc-400 bg-white px-1 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300"
        x-text="penaltyPoints({{ $p }})"
    ></span>
    <span class="text-zinc-400">=</span>
    <span
        class="flex h-[calc(var(--qx-cell)*0.95)] min-w-[calc(var(--qx-cell)*1.7)] items-center justify-center rounded-lg border-2 border-zinc-700 bg-white px-1 text-zinc-800 dark:border-zinc-300 dark:bg-zinc-800 dark:text-zinc-100"
        x-text="totalFor({{ $p }})"
    ></span>
</div>
