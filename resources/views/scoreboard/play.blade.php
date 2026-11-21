{{--
    The scoreboard play screen — chromeless, like the Qwixx room and the
    Phase 10 scoring screen, because it sits on a table all evening.

    One Alpine component (resources/js/scoreboard/board.js) runs both roles:
    the scorekeeper, whose game lives in this browser's localStorage and is
    pushed to the server when the signal allows, and a follower, who polls
    the server's copy. Which one this device is comes from localStorage, not
    the server, so the scorekeeper can reopen their game with no signal.

    Passed in: $code (null for this device's current game), $board (the
    server's snapshot, if it has one).
--}}
<x-layouts.fullscreen :title="$code ? 'Game '.$code.' — Scoreboard' : 'Scoreboard'" game="scoreboard">
    <div
        class="flex h-full min-h-0 flex-col"
        x-data="scoreboardGame(@js($code), @js($board))"
        x-cloak
    >
        {{-- Toolbar --}}
        <div class="flex shrink-0 items-center gap-2 px-3 py-2 sm:px-4">
            <flux:button :href="route('scoreboard.index')" variant="ghost" size="sm" icon="arrow-left" class="shrink-0">
                <span class="sr-only sm:not-sr-only">Scoreboard</span>
            </flux:button>

            <div class="min-w-0 flex-1">
                <div class="truncate leading-tight font-black text-zinc-900 dark:text-zinc-50" x-text="title"></div>
                <div class="truncate text-xs text-zinc-500 dark:text-zinc-400" x-show="game">
                    <span x-text="`Round ${game?.round}`"></span>
                    <span x-show="!isHost"> · Following along</span>
                </div>
            </div>

            <span
                class="flex shrink-0 items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold"
                x-show="mode === 'host' || mode === 'viewer'"
                :class="statusTone === 'ok'
                    ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300'
                    : 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300'"
                :title="syncState === 'error' ? syncMessage : ''"
            >
                <span class="size-1.5 rounded-full bg-current" :class="(syncState === 'syncing' && isHost) && 'animate-pulse'"></span>
                <span class="hidden sm:inline" x-text="statusLabel"></span>
            </span>

            <flux:modal.trigger name="edit-game" x-show="isHost">
                <flux:button variant="ghost" size="sm" icon="users" title="Players and settings" class="shrink-0" />
            </flux:modal.trigger>

            <flux:modal.trigger name="share-game" x-show="mode === 'host' || mode === 'viewer'">
                <button
                    type="button"
                    class="flex shrink-0 items-center gap-1.5 rounded-lg bg-zinc-900/5 px-2.5 py-1 font-mono text-sm font-black tracking-[0.25em] text-zinc-700 transition hover:bg-zinc-900/10 dark:bg-white/10 dark:text-zinc-200 dark:hover:bg-white/20"
                    title="Share this game"
                >
                    <span x-text="code || '····'"></span>
                    <flux:icon.share class="size-3.5 shrink-0 tracking-normal text-zinc-400" />
                </button>
            </flux:modal.trigger>
        </div>

        {{-- A follower whose game the server no longer has, and nothing cached. --}}
        <div class="flex flex-1 items-center justify-center p-6" x-show="mode === 'viewer' && !game && viewerState === 'missing'">
            <div class="max-w-sm space-y-3 text-center">
                <flux:heading size="lg">No game with that code</flux:heading>
                <flux:text>It may have finished a while ago, or the code was mistyped.</flux:text>
                <flux:button :href="route('scoreboard.index')" variant="primary">Back to Scoreboard</flux:button>
            </div>
        </div>

        <div class="flex flex-1 items-center justify-center p-6" x-show="mode === 'viewer' && !game && viewerState !== 'missing'">
            <flux:text x-text="viewerState === 'offline' ? 'Waiting for a connection…' : 'Loading…'"></flux:text>
        </div>

        <template x-if="game">
            <div class="flex min-h-0 flex-1 flex-col">
                {{-- Phone and portrait-tablet tabs --}}
                <div class="flex shrink-0 px-3 pb-2 lg:hidden">
                    <div class="flex rounded-lg bg-zinc-900/5 p-0.5 text-sm font-semibold dark:bg-white/10">
                        <button
                            type="button"
                            x-on:click="pane = 'scores'"
                            :class="pane === 'scores' ? 'bg-white shadow-sm dark:bg-zinc-700' : 'text-zinc-500'"
                            class="rounded-md px-3 py-1"
                        >Scores</button>
                        <button
                            type="button"
                            x-on:click="pane = 'history'"
                            :class="pane === 'history' ? 'bg-white shadow-sm dark:bg-zinc-700' : 'text-zinc-500'"
                            class="rounded-md px-3 py-1"
                        >History</button>
                    </div>
                </div>

                <div class="grid min-h-0 flex-1 gap-3 p-3 pt-0 lg:grid-cols-5 lg:gap-4 lg:p-4 lg:pt-0">
                    {{-- Scores --}}
                    <div class="min-h-0 flex-col gap-3 lg:col-span-3" :class="(wide || pane === 'scores') ? 'flex' : 'hidden'">
                        <div class="flex shrink-0 flex-wrap items-center gap-2" x-show="isHost">
                            <div class="flex items-center rounded-lg bg-zinc-900/5 p-0.5 dark:bg-white/10">
                                <flux:button variant="ghost" size="sm" icon="chevron-left" x-on:click="previousRound()" x-bind:disabled="!canStepBack" aria-label="Previous round" />
                                <span class="px-1 text-sm font-semibold whitespace-nowrap tabular-nums" x-text="`Round ${game.round}`"></span>
                                <flux:button variant="ghost" size="sm" icon="chevron-right" x-on:click="nextRound()" aria-label="Next round" />
                            </div>

                            <flux:button variant="primary" size="sm" icon="table-cells" x-on:click="openRound()">
                                <span x-text="`Score round ${game.round}`"></span>
                            </flux:button>

                            <flux:button size="sm" icon="arrow-uturn-left" x-on:click="undo()" x-bind:disabled="!undoLabel" class="max-w-full min-w-0">
                                <span class="truncate" x-text="undoLabel || 'Undo'"></span>
                            </flux:button>
                        </div>

                        <div class="min-h-0 flex-1 overflow-y-auto">
                            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                <template x-for="player in standings" :key="player.id">
                                    <div
                                        class="flex flex-col rounded-xl border bg-white p-3 shadow-xs transition dark:bg-zinc-900"
                                        :class="player.leading
                                            ? 'border-emerald-300 bg-emerald-50/60 dark:border-emerald-800 dark:bg-emerald-950/30'
                                            : 'border-zinc-200 dark:border-zinc-700'"
                                    >
                                        <button
                                            type="button"
                                            class="flex items-start justify-between gap-3 text-left disabled:cursor-default"
                                            x-on:click="openAdd(player.id)"
                                            :disabled="!isHost"
                                        >
                                            <div class="min-w-0">
                                                <div class="flex items-center gap-1.5">
                                                    <span x-show="player.leading" class="shrink-0">
                                                        <flux:icon.trophy class="size-4 text-emerald-600 dark:text-emerald-400" />
                                                    </span>
                                                    <span class="truncate font-semibold text-zinc-800 dark:text-zinc-100" x-text="player.name"></span>
                                                </div>
                                                <div class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400" x-text="game.entries.length ? ordinal(player.rank) : ' '"></div>
                                            </div>
                                            <div class="shrink-0 text-right">
                                                <div class="text-4xl leading-none font-black text-zinc-900 tabular-nums dark:text-zinc-50" x-text="player.total"></div>
                                                <div class="text-[10px] tracking-wide text-zinc-400 uppercase">points</div>
                                            </div>
                                        </button>

                                        <div class="mt-3 grid grid-cols-3 gap-2" x-show="isHost">
                                            <flux:button size="sm" x-on:click="quick(player.id, -1)" aria-label="Minus one">−1</flux:button>
                                            <flux:button size="sm" x-on:click="quick(player.id, 1)" aria-label="Plus one">+1</flux:button>
                                            <flux:button size="sm" variant="primary" icon="plus" x-on:click="openAdd(player.id)">Add</flux:button>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    {{-- History --}}
                    <div class="min-h-0 lg:col-span-2" :class="(wide || pane === 'history') ? 'block' : 'hidden'">
                        <div class="flex h-full min-h-0 flex-col rounded-2xl bg-white shadow-sm ring-1 ring-zinc-200 dark:bg-zinc-900 dark:ring-zinc-800">
                            <div class="flex shrink-0 items-baseline justify-between gap-2 border-b border-zinc-100 px-4 py-3 dark:border-zinc-800">
                                <flux:heading class="font-black">History</flux:heading>
                                <span class="text-xs font-semibold tracking-wide text-zinc-400 uppercase" x-show="isHost">Tap a score to fix it</span>
                            </div>

                            <div class="min-h-0 flex-1 overflow-auto">
                                <table class="w-full border-separate border-spacing-0 text-sm tabular-nums">
                                    <thead>
                                        <tr>
                                            <th class="sticky top-0 left-0 z-20 w-12 bg-white px-3 py-2 text-left text-xs font-semibold text-zinc-400 dark:bg-zinc-900">Rd</th>
                                            <template x-for="p in game.players" :key="p.id">
                                                <th class="sticky top-0 z-10 max-w-28 min-w-16 truncate bg-white px-2 py-2 text-right font-semibold text-zinc-700 dark:bg-zinc-900 dark:text-zinc-200" x-text="p.name"></th>
                                            </template>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="row in grid" :key="row.round">
                                            <tr>
                                                <td class="sticky left-0 border-t border-zinc-100 bg-white px-3 py-1 text-xs font-bold text-zinc-400 dark:border-zinc-800 dark:bg-zinc-900" x-text="row.round"></td>
                                                <template x-for="p in game.players" :key="p.id">
                                                    <td class="border-t border-zinc-100 px-1 py-0.5 text-right dark:border-zinc-800">
                                                        <button
                                                            type="button"
                                                            class="w-full rounded-md px-1.5 py-1 text-right enabled:hover:bg-zinc-900/5 disabled:cursor-default dark:enabled:hover:bg-white/10"
                                                            :class="row.cells[p.id] === null ? 'text-zinc-300 dark:text-zinc-600' : 'text-zinc-800 dark:text-zinc-100'"
                                                            :disabled="!isHost"
                                                            x-on:click="openCell(p.id, row.round)"
                                                            x-text="row.cells[p.id] === null ? '–' : row.cells[p.id]"
                                                        ></button>
                                                    </td>
                                                </template>
                                            </tr>
                                        </template>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td class="sticky bottom-0 left-0 z-20 border-t-2 border-zinc-200 bg-zinc-50 px-3 py-2 text-xs font-bold text-zinc-500 uppercase dark:border-zinc-700 dark:bg-zinc-800">Total</td>
                                            <template x-for="p in game.players" :key="p.id">
                                                <td class="sticky bottom-0 z-10 border-t-2 border-zinc-200 bg-zinc-50 px-2.5 py-2 text-right font-black text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-50" x-text="totals[p.id]"></td>
                                            </template>
                                        </tr>
                                    </tfoot>
                                </table>

                                <flux:text class="px-4 py-6 text-center text-sm" x-show="!grid.length">
                                    Nothing scored yet. Each round shows up here as points come in.
                                </flux:text>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        {{-- Add points to one player --}}
        <flux:modal name="add-points" class="w-full max-w-sm">
            <form class="space-y-4" x-on:submit.prevent="saveAdd()">
                <div class="space-y-1">
                    <flux:heading size="lg" x-text="playerName(sheet.playerId)"></flux:heading>
                    <div class="text-sm text-zinc-500 tabular-nums">
                        <span x-text="totals[sheet.playerId] ?? 0"></span>
                        <span x-show="sheet.delta !== 0">
                            → <span class="font-bold text-zinc-900 dark:text-zinc-50" x-text="(totals[sheet.playerId] ?? 0) + sheet.delta"></span>
                        </span>
                    </div>
                </div>

                <div class="flex items-stretch gap-2">
                    <button
                        type="button"
                        class="w-14 shrink-0 rounded-lg text-2xl font-black transition"
                        :class="sheet.negative
                            ? 'bg-red-100 text-red-700 dark:bg-red-950/60 dark:text-red-300'
                            : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300'"
                        x-on:click="flipSign()"
                        x-text="sheet.negative ? '−' : '+'"
                        aria-label="Switch between adding and subtracting"
                    ></button>
                    <input
                        id="add-points-amount"
                        type="text"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        autocomplete="off"
                        placeholder="0"
                        x-model="amount"
                        class="h-14 min-w-0 flex-1 rounded-lg border border-zinc-200 bg-white px-3 text-right text-3xl font-black tabular-nums shadow-xs outline-none focus:border-accent focus:ring-2 focus:ring-accent/20 dark:border-white/10 dark:bg-white/10"
                        aria-label="Points"
                    />
                </div>

                <div class="grid grid-cols-6 gap-1.5">
                    @foreach ([-10, -5, -1, 1, 5, 10] as $step)
                        <flux:button size="sm" x-on:click="nudge({{ $step }})" class="px-0! tabular-nums">
                            {{ $step > 0 ? '+'.$step : '−'.abs($step) }}
                        </flux:button>
                    @endforeach
                </div>

                <flux:button type="submit" variant="primary" class="w-full" x-bind:disabled="sheet.delta === 0">
                    <span x-text="sheet.delta === 0 ? 'Add points' : `${sheet.delta > 0 ? 'Add' : 'Take'} ${Math.abs(sheet.delta)}`"></span>
                </flux:button>
            </form>
        </flux:modal>

        {{-- Score a whole round at once --}}
        <flux:modal name="score-round" class="w-full max-w-md">
            <form class="space-y-4" x-on:submit.prevent="saveRound()">
                <div class="space-y-1">
                    <flux:heading size="lg" x-text="`Score round ${game?.round}`"></flux:heading>
                    <flux:text>Enter what everyone scored this round. Blank counts as 0.</flux:text>
                </div>

                <div class="max-h-[55dvh] space-y-2 overflow-y-auto">
                    <template x-for="p in (game?.players ?? [])" :key="p.id">
                        <label class="flex items-center gap-2">
                            <span class="min-w-0 flex-1 truncate font-semibold" x-text="p.name"></span>
                            <span class="w-12 shrink-0 text-right text-xs text-zinc-400 tabular-nums" x-text="totals[p.id]"></span>
                            <button
                                type="button"
                                class="h-10 w-10 shrink-0 rounded-lg bg-zinc-900/5 text-lg font-black dark:bg-white/10"
                                x-on:click="flipRoundSign(p.id)"
                                aria-label="Make negative or positive"
                            >±</button>
                            <input
                                type="text"
                                inputmode="numeric"
                                autocomplete="off"
                                placeholder="0"
                                x-model="roundScores[p.id]"
                                class="h-10 w-24 shrink-0 rounded-lg border border-zinc-200 bg-white px-3 text-right text-lg font-bold tabular-nums shadow-xs outline-none focus:border-accent focus:ring-2 focus:ring-accent/20 dark:border-white/10 dark:bg-white/10"
                            />
                        </label>
                    </template>
                </div>

                <flux:button type="submit" variant="primary" class="w-full">
                    <span x-text="`Save round ${game?.round}`"></span>
                </flux:button>
            </form>
        </flux:modal>

        {{-- Fix one cell of the history --}}
        <flux:modal name="edit-cell" class="w-full max-w-sm">
            <div class="space-y-4">
                <div class="space-y-1">
                    <flux:heading size="lg" x-text="`${playerName(cell.playerId)} · round ${cell.round}`"></flux:heading>
                    <flux:text>Change or remove what was entered. Setting an entry to 0 removes it.</flux:text>
                </div>

                <ul class="space-y-2">
                    <template x-for="entry in cellEntries" :key="entry.id">
                        <li class="flex items-center gap-2">
                            <span class="flex-1 text-xs text-zinc-500" x-text="entry.kind === 'round' ? 'Round score' : 'Added'"></span>
                            <input
                                type="text"
                                inputmode="numeric"
                                autocomplete="off"
                                :value="entry.points"
                                x-on:change="editEntry(entry.id, $event.target.value)"
                                class="h-10 w-24 rounded-lg border border-zinc-200 bg-white px-3 text-right font-bold tabular-nums shadow-xs outline-none focus:border-accent focus:ring-2 focus:ring-accent/20 dark:border-white/10 dark:bg-white/10"
                            />
                            <flux:button variant="ghost" size="sm" icon="trash" x-on:click="deleteEntry(entry.id)" aria-label="Remove entry" />
                        </li>
                    </template>
                </ul>

                <form class="flex items-center gap-2 border-t border-zinc-100 pt-4 dark:border-zinc-800" x-on:submit.prevent="addToCell()">
                    <span class="flex-1 text-sm font-semibold">Add to this round</span>
                    <input
                        type="text"
                        inputmode="numeric"
                        autocomplete="off"
                        placeholder="0"
                        x-model="cell.amount"
                        class="h-10 w-24 rounded-lg border border-zinc-200 bg-white px-3 text-right font-bold tabular-nums shadow-xs outline-none focus:border-accent focus:ring-2 focus:ring-accent/20 dark:border-white/10 dark:bg-white/10"
                    />
                    <flux:button type="submit" size="sm" variant="primary">Add</flux:button>
                </form>
            </div>
        </flux:modal>

        {{-- Players and settings --}}
        <flux:modal name="edit-game" class="w-full max-w-md">
            <div class="space-y-5">
                <flux:heading size="lg">Players and settings</flux:heading>

                <div class="space-y-1.5">
                    <flux:label>Game</flux:label>
                    <input
                        type="text"
                        placeholder="Optional — e.g. Rummy"
                        maxlength="{{ config('scoreboard.title_max') }}"
                        :value="game?.title ?? ''"
                        x-on:change="setTitle($event.target.value)"
                        class="h-10 w-full rounded-lg border border-zinc-200 bg-white px-3 text-base shadow-xs outline-none focus:border-accent focus:ring-2 focus:ring-accent/20 sm:text-sm dark:border-white/10 dark:bg-white/10"
                    />
                </div>

                <flux:switch x-model="lowestWinsSetting" label="Lowest score wins" />

                <div class="space-y-2">
                    <flux:label>Players</flux:label>
                    <ol class="space-y-2">
                        <template x-for="(p, index) in (game?.players ?? [])" :key="p.id">
                            <li class="flex items-center gap-2">
                                <input
                                    type="text"
                                    maxlength="{{ config('scoreboard.name_max') }}"
                                    :value="p.name"
                                    x-on:change="renamePlayer(p.id, $event.target.value)"
                                    class="h-10 min-w-0 flex-1 rounded-lg border border-zinc-200 bg-white px-3 text-base shadow-xs outline-none focus:border-accent focus:ring-2 focus:ring-accent/20 sm:text-sm dark:border-white/10 dark:bg-white/10"
                                />
                                <div class="flex shrink-0">
                                    <flux:button variant="ghost" size="sm" icon="chevron-up" x-on:click="movePlayer(p.id, -1)" x-bind:disabled="index === 0" aria-label="Move up" />
                                    <flux:button variant="ghost" size="sm" icon="chevron-down" x-on:click="movePlayer(p.id, 1)" x-bind:disabled="index === game.players.length - 1" aria-label="Move down" />
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        icon="x-mark"
                                        x-on:click="removePlayer(p.id)"
                                        x-bind:disabled="!canRemovePlayer(p.id)"
                                        x-bind:title="canRemovePlayer(p.id) ? 'Remove' : 'Has points, so can’t be removed'"
                                        aria-label="Remove player"
                                    />
                                </div>
                            </li>
                        </template>
                    </ol>

                    <form class="flex gap-2" x-on:submit.prevent="addPlayer()" x-show="(game?.players.length ?? 0) < {{ config('scoreboard.max_players') }}">
                        <input
                            type="text"
                            placeholder="New player"
                            maxlength="{{ config('scoreboard.name_max') }}"
                            x-model="newPlayer"
                            class="h-10 min-w-0 flex-1 rounded-lg border border-zinc-200 bg-white px-3 text-base shadow-xs outline-none focus:border-accent focus:ring-2 focus:ring-accent/20 sm:text-sm dark:border-white/10 dark:bg-white/10"
                        />
                        <flux:button type="submit" size="sm" icon="plus">Add</flux:button>
                    </form>
                    <flux:text class="text-xs">A player added now starts on 0.</flux:text>
                </div>

                <div class="border-t border-zinc-100 pt-4 dark:border-zinc-800">
                    <flux:modal.trigger name="new-game">
                        <flux:button class="w-full" icon="arrow-path">New game, same players</flux:button>
                    </flux:modal.trigger>
                </div>
            </div>
        </flux:modal>

        <flux:modal name="new-game" class="w-full max-w-sm">
            <div class="space-y-4">
                <div class="space-y-1">
                    <flux:heading size="lg">Start a new game?</flux:heading>
                    <flux:text>
                        Same players, everyone back to 0, on a new code. This game stays under “Your games” on the
                        Scoreboard page.
                    </flux:text>
                </div>
                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancel</flux:button>
                    </flux:modal.close>
                    <flux:button variant="primary" x-on:click="rematch()">New game</flux:button>
                </div>
            </div>
        </flux:modal>

        {{--
            Sharing — the same sheet Qwixx rooms and Phase 10 scoring use.
            `navigator.clipboard` is refused outside a secure context, so the
            link is on screen as selectable text and a refusal says so.
        --}}
        <flux:modal name="share-game" class="min-w-[22rem]">
            <div class="space-y-4">
                <div class="space-y-1">
                    <flux:heading size="lg">Follow this game</flux:heading>
                    <flux:text x-show="isHost">
                        Anyone with the code sees the scores as they're entered — nothing to install, no name to type.
                        Only this device can enter scores.
                    </flux:text>
                    <flux:text x-show="!isHost">Send this to anyone else who wants to follow along.</flux:text>
                </div>

                <template x-if="code">
                    <div class="space-y-4">
                        <div class="rounded-xl bg-zinc-100 py-5 text-center dark:bg-zinc-800">
                            <div class="font-mono text-4xl font-black tracking-[0.35em] text-zinc-900 dark:text-zinc-50" x-text="code"></div>
                            <div class="mt-1 text-xs font-medium text-zinc-500">
                                Enter it at {{ parse_url(config('app.url'), PHP_URL_HOST) }}/scoreboard
                            </div>
                        </div>

                        <flux:input readonly x-bind:value="shareUrl" x-on:focus="$el.select()" aria-label="Link to this game" />

                        <flux:button variant="primary" class="w-full" icon="clipboard" x-on:click="copyLink()">
                            <span x-text="copied ? 'Copied!' : 'Copy link'">Copy link</span>
                        </flux:button>

                        <flux:text class="text-center text-sm" x-show="copyFailed">
                            This browser wouldn't let the page copy — tap the link above to select it.
                        </flux:text>
                    </div>
                </template>

                <template x-if="!code">
                    <flux:callout icon="signal-slash" variant="warning">
                        <flux:callout.text>
                            This game hasn't reached the server yet, so it has no code. Keep scoring — it's saved on
                            this device, and the code appears here as soon as there's a connection.
                        </flux:callout.text>
                    </flux:callout>
                </template>
            </div>
        </flux:modal>
    </div>
</x-layouts.fullscreen>
