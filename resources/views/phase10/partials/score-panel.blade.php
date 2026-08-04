{{--
    Shared between the desktop split-screen pane and the mobile full-screen
    overlay so the two never drift out of sync. $room and $readOnly are
    passed in by whichever @include calls this.
--}}
<div class="flex h-full min-h-0 flex-col gap-3 overflow-y-auto rounded-2xl bg-white p-4 shadow-sm ring-1 ring-zinc-200 dark:bg-zinc-900 dark:ring-zinc-800">
    @if ($room->status === 'tiebreak')
        @php($tied = collect($room->players)->whereIn('id', $room->tiedPlayerIds))
        <flux:callout variant="warning" icon="exclamation-triangle">
            <flux:callout.heading>Tiebreaker</flux:callout.heading>
            <flux:callout.text>
                {{ $tied->pluck('name')->join(' and ') }} tied at {{ $tied->first()?->totalScore() }} points after
                completing the final phase together. Play the final phase again at the table — whoever goes out
                first wins.
            </flux:callout.text>
        </flux:callout>

        @unless ($readOnly)
            <div class="flex flex-wrap gap-2">
                @foreach ($tied as $player)
                    <flux:button variant="primary" wire:click="resolveTiebreak('{{ $player->id }}')">
                        {{ $player->name }} won the replay
                    </flux:button>
                @endforeach
            </div>
        @endunless
    @elseif ($room->status === 'ended')
        @php($winner = collect($room->players)->firstWhere('id', $room->winnerIds[0] ?? null))
        <flux:callout variant="success" icon="trophy">
            <flux:callout.heading>{{ $winner?->name }} wins!</flux:callout.heading>
            <flux:callout.text>
                Finished all {{ $room->phaseCount() }} phases with {{ $winner?->totalScore() }} points.
            </flux:callout.text>
        </flux:callout>
    @endif

    <div class="space-y-2">
        @foreach ($room->players as $player)
            <div class="rounded-xl border border-zinc-200 p-3 dark:border-zinc-700">
                <div class="flex items-center justify-between gap-2">
                    <div class="min-w-0">
                        <div class="truncate font-semibold text-zinc-800 dark:text-zinc-100">{{ $player->name }}</div>
                        <div class="text-xs text-zinc-500 dark:text-zinc-400">
                            Phase {{ $player->currentPhase($room->phaseCount()) }} of {{ $room->phaseCount() }}
                            @if (in_array($player->id, $room->winnerIds, true))
                                &middot; <span class="font-semibold text-emerald-600 dark:text-emerald-400">Winner</span>
                            @endif
                        </div>
                    </div>
                    <div class="shrink-0 text-right">
                        <div class="text-lg font-black text-zinc-900 tabular-nums dark:text-zinc-50">{{ $player->totalScore() }}</div>
                        <div class="text-[10px] tracking-wide text-zinc-400 uppercase">points</div>
                    </div>
                </div>

                @if (! $readOnly && $room->status === 'active')
                    <div class="mt-2 flex items-center gap-3">
                        <flux:input
                            type="text"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            placeholder="0"
                            size="sm"
                            class="w-20"
                            wire:model.live="pending.{{ $player->id }}.score"
                            aria-label="{{ $player->name }}'s score this round"
                        />
                        <label class="flex items-center gap-1.5 text-sm text-zinc-600 dark:text-zinc-300">
                            <input
                                type="checkbox"
                                class="size-4 rounded border-zinc-300 text-zinc-800 focus:ring-zinc-800 dark:border-zinc-600"
                                wire:model.live="pending.{{ $player->id }}.completed"
                            >
                            Completed phase
                        </label>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    @if (! $readOnly && $room->status === 'active')
        <flux:button
            variant="primary"
            wire:click="finalizeRound"
            wire:loading.attr="disabled"
            :disabled="! $this->pendingReady($room)"
        >
            Finalize round
        </flux:button>
    @endif

    @unless ($readOnly)
        <flux:button variant="ghost" size="sm" wire:click="undoLastRound" wire:loading.attr="disabled">
            Undo last round
        </flux:button>
    @endunless
</div>
