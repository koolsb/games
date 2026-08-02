{{--
    Live standings for a multiplayer game: every player's running total, best
    first, straight out of the same engine that draws the sheet. Qwixx sheets
    lie face up on a real table, so nothing here is hidden information.
--}}
<div class="flex items-center gap-2 overflow-x-auto px-3 py-1.5">
    <flux:button
        size="sm"
        variant="ghost"
        x-on:click="showStandings = ! showStandings"
        x-bind:title="showStandings ? 'Hide scores' : 'Show scores'"
    >
        <flux:icon.trophy class="size-4" />
    </flux:button>

    <div class="flex min-w-0 items-center gap-1.5" x-show="showStandings" x-cloak>
        <template x-for="player in standings" x-bind:key="player.index">
            <span
                class="flex shrink-0 items-center gap-1.5 rounded-full py-0.5 pl-2.5 pr-1 text-xs font-semibold"
                x-bind:class="player.index === me
                    ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900'
                    : 'bg-zinc-200/70 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300'"
            >
                <span
                    class="size-1.5 shrink-0 rounded-full"
                    x-bind:class="playerStale(roster[player.index]) ? 'bg-amber-500' : 'bg-emerald-500'"
                    x-bind:title="playerStale(roster[player.index]) ? 'Not syncing right now' : 'Connected'"
                ></span>
                <span class="max-w-24 truncate" x-text="player.name"></span>
                <span
                    class="rounded-full bg-white/90 px-1.5 font-black text-zinc-800 dark:bg-zinc-900/60 dark:text-zinc-100"
                    x-text="player.total"
                ></span>
            </span>
        </template>
    </div>

    {{-- Sync health. Silent while everything is fine — this only appears
         when the table needs to know a device has stopped talking. --}}
    <span
        class="ml-auto flex shrink-0 items-center gap-1 text-xs font-semibold text-amber-600 dark:text-amber-400"
        x-show="syncState !== 'live'"
        x-cloak
    >
        <flux:icon.signal-slash class="size-4" />
        <span x-text="syncState === 'offline' ? 'Offline — still playing' : 'Reconnecting…'"></span>
    </span>
</div>
