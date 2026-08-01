{{-- Four -5 penalty boxes. --}}
@props(['playerIndex'])

@php($p = $playerIndex)

<div class="flex items-center gap-[calc(var(--qx-cell)*0.14)]">
    <span class="mr-1 text-[calc(var(--qx-cell)*0.28)] font-bold text-zinc-500 dark:text-zinc-400">✕ = −5</span>

    @for ($i = 0; $i < 4; $i++)
        <button
            type="button"
            class="qx-cell h-[calc(var(--qx-cell)*0.72)]! w-[calc(var(--qx-cell)*0.72)]! border-2 border-zinc-300 bg-white dark:border-zinc-500"
            x-bind:class="{ 'qx-crossed': penaltyCount({{ $p }}) > {{ $i }} }"
            x-on:click="togglePenalty({{ $p }}, {{ $i }})"
        ></button>
    @endfor
</div>
