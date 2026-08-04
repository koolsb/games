{{--
    The scoreboard, and — for the host — the round entry form.

    Entry is built around what actually happens at the table: one player
    goes out, everyone else counts the cards left in their hand. So the
    score starts at 0 and is nudged in fives (Phase 10 card values are all
    multiples of five), and "made the phase" is a single big target rather
    than a checkbox, because this gets tapped on a tablet being passed
    around.

    Passed in by the caller: $room, $rounds, $readOnly, $leaderId.
--}}
@php
    $phaseCount = $room->phaseCount();
    $entering = ! $readOnly && $room->status === 'active';
@endphp

<div class="flex h-full min-h-0 flex-col rounded-2xl bg-white shadow-sm ring-1 ring-zinc-200 dark:bg-zinc-900 dark:ring-zinc-800">
    <div class="flex shrink-0 items-baseline justify-between gap-2 border-b border-zinc-100 px-4 py-3 dark:border-zinc-800">
        <flux:heading class="font-black">Scores</flux:heading>
        <span class="text-xs font-semibold tracking-wide text-zinc-400 uppercase">
            @if ($room->status !== 'active')
                Final
            @elseif ($rounds === 0)
                Round 1
            @else
                Round {{ $rounds + 1 }}
            @endif
        </span>
    </div>

    <div class="min-h-0 flex-1 space-y-3 overflow-y-auto p-3">
        @if ($room->status === 'tiebreak')
            @php
                $tied = collect($room->players)->whereIn('id', $room->tiedPlayerIds);
            @endphp
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
            @php
                $winner = collect($room->players)->firstWhere('id', $room->winnerIds[0] ?? null);
            @endphp
            <flux:callout variant="success" icon="trophy">
                <flux:callout.heading>{{ $winner?->name }} wins!</flux:callout.heading>
                <flux:callout.text>
                    All {{ $phaseCount }} phases in {{ $rounds }} {{ \Illuminate\Support\Str::plural('round', $rounds) }},
                    on {{ $winner?->totalScore() }} points.
                </flux:callout.text>
            </flux:callout>
        @endif

        @foreach ($room->players as $player)
            @php
                $done = $player->phasesCompleted();
                $pendingCompleted = $entering && ($pending[$player->id]['completed'] ?? false);
                $onPhase = min($done + 1, $phaseCount);
            @endphp

            <div
                wire:key="player-{{ $player->id }}"
                @class([
                    'rounded-xl border p-3 transition',
                    'border-emerald-300 bg-emerald-50/60 dark:border-emerald-800 dark:bg-emerald-950/30' => in_array($player->id, $room->winnerIds, true),
                    'border-zinc-200 dark:border-zinc-700' => ! in_array($player->id, $room->winnerIds, true),
                ])
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-1.5">
                            @if (in_array($player->id, $room->winnerIds, true))
                                <flux:icon.trophy class="size-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                            @elseif ($player->id === $leaderId && $room->status === 'active')
                                <flux:icon.chevron-double-up class="size-4 shrink-0 text-zinc-400" title="Ahead" />
                            @endif
                            <span class="truncate font-semibold text-zinc-800 dark:text-zinc-100">{{ $player->name }}</span>
                        </div>
                        <div class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">
                            @if ($player->finished($phaseCount))
                                All phases done
                            @else
                                On phase {{ $onPhase }}
                            @endif
                        </div>
                    </div>

                    <div class="shrink-0 text-right">
                        <div class="text-2xl leading-none font-black text-zinc-900 tabular-nums dark:text-zinc-50">{{ $player->totalScore() }}</div>
                        <div class="text-[10px] tracking-wide text-zinc-400 uppercase">points</div>
                    </div>
                </div>

                {{-- One badge per phase, crossed off as it is cleared. --}}
                <div class="mt-2.5 flex flex-wrap gap-1" role="img" aria-label="{{ $done }} of {{ $phaseCount }} phases completed">
                    @for ($n = 1; $n <= $phaseCount; $n++)
                        @php
                            $isPending = $pendingCompleted && $n === $done + 1;
                        @endphp
                        <span @class([
                            'p10-pip',
                            'p10-pip-crossed bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300' => $n <= $done,
                            'p10-pip-crossed p10-pip-pending bg-emerald-50 text-emerald-600 ring-1 ring-emerald-400 dark:bg-emerald-950/50 dark:text-emerald-400' => $isPending,
                            'bg-white text-zinc-700 ring-2 ring-zinc-400 dark:bg-zinc-800 dark:text-zinc-200 dark:ring-zinc-500' => $n === $done + 1 && ! $isPending,
                            'bg-zinc-100 text-zinc-400 dark:bg-zinc-800/60 dark:text-zinc-600' => $n > $done + 1,
                        ])>{{ $n }}</span>
                    @endfor
                </div>

                @if ($entering)
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            wire:click="toggleCompleted('{{ $player->id }}')"
                            aria-pressed="{{ $pendingCompleted ? 'true' : 'false' }}"
                            @class([
                                'flex flex-1 items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold transition',
                                'bg-emerald-600 text-white' => $pendingCompleted,
                                'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700' => ! $pendingCompleted,
                            ])
                        >
                            @if ($pendingCompleted)
                                <flux:icon.check class="size-4" />
                                Made phase {{ $onPhase }}
                            @else
                                Made phase {{ $onPhase }}?
                            @endif
                        </button>

                        <div class="flex shrink-0 items-center gap-1">
                            <button
                                type="button"
                                wire:click="bumpScore('{{ $player->id }}', -5)"
                                aria-label="Lower {{ $player->name }}'s score by 5"
                                class="flex size-9 items-center justify-center rounded-lg bg-zinc-100 text-lg font-black text-zinc-600 transition hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700"
                            >&minus;</button>

                            <input
                                type="text"
                                inputmode="numeric"
                                pattern="[0-9]*"
                                wire:model.blur="pending.{{ $player->id }}.score"
                                x-on:focus="$el.select()"
                                aria-label="{{ $player->name }}'s cards left this round"
                                class="w-16 rounded-lg border-0 bg-zinc-100 py-2 text-center text-lg font-black text-zinc-900 tabular-nums focus:ring-2 focus:ring-accent dark:bg-zinc-800 dark:text-zinc-50"
                            >

                            <button
                                type="button"
                                wire:click="bumpScore('{{ $player->id }}', 5)"
                                aria-label="Raise {{ $player->name }}'s score by 5"
                                class="flex size-9 items-center justify-center rounded-lg bg-zinc-100 text-lg font-black text-zinc-600 transition hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700"
                            >+</button>
                        </div>
                    </div>
                @endif
            </div>
        @endforeach

        {{-- The round log, for when someone disputes a total. --}}
        @if ($rounds > 0)
            <details class="rounded-xl border border-zinc-200 dark:border-zinc-700">
                <summary class="cursor-pointer px-3 py-2 text-sm font-semibold text-zinc-600 select-none dark:text-zinc-300">
                    Round history
                </summary>
                <div class="overflow-x-auto border-t border-zinc-100 dark:border-zinc-800">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-xs text-zinc-400">
                                <th class="px-3 py-1.5 text-left font-medium">Rd</th>
                                @foreach ($room->players as $player)
                                    <th class="px-3 py-1.5 text-right font-medium">{{ \Illuminate\Support\Str::limit($player->name, 8, '') }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @for ($r = 0; $r < $rounds; $r++)
                                <tr>
                                    <td class="px-3 py-1.5 text-zinc-400 tabular-nums">{{ $r + 1 }}</td>
                                    @foreach ($room->players as $player)
                                        <td class="px-3 py-1.5 text-right tabular-nums">
                                            <span class="font-semibold text-zinc-700 dark:text-zinc-200">{{ $player->log[$r]['score'] }}</span>
                                            @if ($player->log[$r]['completed'])
                                                <span class="ml-0.5 text-emerald-600 dark:text-emerald-400" title="Made the phase">&check;</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endfor
                        </tbody>
                    </table>
                </div>
            </details>
        @endif
    </div>

    @unless ($readOnly)
        <div class="shrink-0 space-y-2 border-t border-zinc-100 p-3 dark:border-zinc-800">
            @if ($room->status === 'active')
                <flux:button
                    variant="primary"
                    class="w-full"
                    wire:click="finalizeRound"
                    wire:loading.attr="disabled"
                >
                    Save round {{ $rounds + 1 }}
                </flux:button>
            @else
                <flux:button variant="primary" class="w-full" icon="arrow-path" wire:click="rematch" wire:loading.attr="disabled">
                    Play again, same table
                </flux:button>
            @endif

            @if ($rounds > 0)
                <flux:button variant="ghost" size="sm" class="w-full" wire:click="undoLastRound" wire:loading.attr="disabled">
                    Undo round {{ $rounds }}
                </flux:button>
            @endif
        </div>
    @endunless
</div>
