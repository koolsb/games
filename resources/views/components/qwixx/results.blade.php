{{--
    Final standings, shared by 2-player and multiplayer games: every player's
    score, best first, winner called out. Ties share the top spot — Qwixx has
    no tie-break, so two people can both have won.
--}}
<div class="space-y-[calc(var(--qx-cell)*0.12)]">
    <div class="text-[calc(var(--qx-cell)*0.26)] font-bold opacity-80">
        <template x-if="standings.filter((p) => p.winner).length > 1">
            <span>
                <span x-text="standings.filter((p) => p.winner).map((p) => p.name).join(' & ')"></span> tie for the win
            </span>
        </template>
        <template x-if="standings.filter((p) => p.winner).length === 1">
            <span><span x-text="standings[0].name"></span> wins</span>
        </template>
    </div>

    <div class="flex flex-wrap items-center gap-[calc(var(--qx-cell)*0.16)]">
        <template x-for="player in standings" x-bind:key="player.index">
            <span
                class="flex items-center gap-[calc(var(--qx-cell)*0.16)] rounded-lg px-[calc(var(--qx-cell)*0.24)] py-[calc(var(--qx-cell)*0.06)] text-[calc(var(--qx-cell)*0.26)] font-bold"
                x-bind:class="player.winner ? 'bg-white/20 dark:bg-zinc-900/10' : 'opacity-70'"
            >
                <span class="opacity-60" x-text="player.rank + '.'"></span>
                <span class="max-w-32 truncate" x-text="player.name"></span>
                <span class="text-[calc(var(--qx-cell)*0.32)] font-black" x-text="player.total"></span>
            </span>
        </template>
    </div>
</div>
