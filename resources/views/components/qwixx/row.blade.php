{{-- One color row: 11 number cells, the lock cell, and the external-close toggle. --}}
@props(['row', 'playerIndex'])

@php($p = $playerIndex)
@php($r = $row->index)

<div class="flex items-center">
    <div class="flex items-stretch">
        @foreach ($row->cells as $pos => $cell)
            <div class="qx-seg qx-seg-{{ $cell->color->value }} {{ $loop->first ? 'rounded-l-lg' : '' }}">
                <button
                    type="button"
                    class="qx-cell qx-{{ $cell->color->value }}"
                    x-bind:class="cellClass({{ $p }}, {{ $r }}, {{ $pos }})"
                    x-on:click="tap({{ $p }}, {{ $r }}, {{ $pos }})"
                >{{ $cell->number }}</button>
            </div>
        @endforeach

        {{-- Lock cell: earned with the final number, or struck when closed externally. --}}
        <div class="qx-seg qx-seg-{{ $row->lockColor->value }} rounded-r-lg">
            <div
                class="qx-cell qx-{{ $row->lockColor->value }} rounded-full!"
                x-bind:class="{
                    'qx-crossed': rowLockedSelf({{ $p }}, {{ $r }}),
                    'qx-skipped': rowClosedExternally({{ $p }}, {{ $r }}),
                    'qx-muted': !rowClosed({{ $p }}, {{ $r }}),
                }"
            >
                <flux:icon.lock-closed variant="solid" class="size-[55%]" />
            </div>
        </div>
    </div>

    {{-- "Someone else locked this row" toggle, for players off this device. --}}
    <button
        type="button"
        class="ml-[calc(var(--qx-cell)*0.2)] flex h-[calc(var(--qx-cell)*0.7)] w-[calc(var(--qx-cell)*0.7)] items-center justify-center rounded-full border border-zinc-300 text-zinc-400 transition dark:border-zinc-700 dark:text-zinc-600"
        x-bind:class="rowMarkedClosed({{ $p }}, {{ $r }}) && 'border-zinc-700! bg-zinc-700! text-white! dark:border-zinc-300! dark:bg-zinc-300! dark:text-zinc-900!'"
        x-bind:disabled="rowLockedSelf({{ $p }}, {{ $r }}) || (rowClosed({{ $p }}, {{ $r }}) && !rowMarkedClosed({{ $p }}, {{ $r }}))"
        x-on:click="toggleClose({{ $p }}, {{ $r }})"
        title="Row locked by another player"
    >
        <flux:icon.no-symbol class="size-[60%]" />
    </button>
</div>
