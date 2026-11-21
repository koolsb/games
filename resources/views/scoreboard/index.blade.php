{{--
    Scoreboard landing: start a game, follow someone else's, or pick up a
    game this device is already keeping score for. Everything here is
    Alpine over localStorage — starting a game needs no connection at all;
    the play screen registers it with the server when it can.
--}}
<x-layouts.app title="Scoreboard" game="scoreboard">
    <div class="space-y-8">
        <div class="space-y-2">
            <flux:heading size="xl" class="font-black">Keep score for any game</flux:heading>
            <flux:text>
                Name the players, tap to add points, and watch the totals. Everyone else can follow along with a
                four-letter code. Scores are saved on this device as you go, so a refresh or a dropped signal
                loses nothing.
            </flux:text>
        </div>

        <div class="grid gap-6 lg:grid-cols-5" x-data="scoreboardSetup" x-cloak>
            {{-- New game --}}
            <form
                class="space-y-5 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-zinc-200 lg:col-span-3 dark:bg-zinc-900 dark:ring-zinc-800"
                x-on:submit.prevent="start()"
            >
                <flux:heading size="lg" class="font-black">New game</flux:heading>

                <flux:input
                    label="What are you playing?"
                    placeholder="Optional — e.g. Rummy"
                    x-model="title"
                    maxlength="{{ config('scoreboard.title_max') }}"
                />

                <div class="space-y-2">
                    <div class="flex items-baseline justify-between">
                        <flux:label>Players</flux:label>
                        <span class="text-xs text-zinc-400" x-text="`${names.length} of ${maxPlayers}`"></span>
                    </div>

                    <ol class="space-y-2">
                        <template x-for="(name, index) in names" :key="index">
                            <li class="flex items-center gap-2">
                                <span class="w-5 shrink-0 text-right text-sm font-bold text-zinc-400 tabular-nums" x-text="index + 1"></span>
                                <input
                                    type="text"
                                    data-name-input
                                    x-model="names[index]"
                                    :placeholder="`Player ${index + 1}`"
                                    maxlength="{{ config('scoreboard.name_max') }}"
                                    autocomplete="off"
                                    class="h-10 min-w-0 flex-1 rounded-lg border border-zinc-200 bg-white px-3 text-base shadow-xs outline-none focus:border-accent focus:ring-2 focus:ring-accent/20 sm:text-sm dark:border-white/10 dark:bg-white/10"
                                />
                                <div class="flex shrink-0">
                                    <flux:button variant="ghost" size="sm" icon="chevron-up" x-on:click="moveName(index, -1)" x-bind:disabled="index === 0" aria-label="Move up" />
                                    <flux:button variant="ghost" size="sm" icon="chevron-down" x-on:click="moveName(index, 1)" x-bind:disabled="index === names.length - 1" aria-label="Move down" />
                                    <flux:button variant="ghost" size="sm" icon="x-mark" x-on:click="removeName(index)" x-bind:disabled="names.length === 1" aria-label="Remove player" />
                                </div>
                            </li>
                        </template>
                    </ol>

                    <flux:button size="sm" icon="plus" x-on:click="addName()" x-bind:disabled="names.length >= maxPlayers">
                        Add player
                    </flux:button>
                </div>

                <flux:switch x-model="lowestWins" label="Lowest score wins" description="For games like golf or Hearts, where fewer points is better." />

                <flux:button type="submit" variant="primary" class="w-full" icon="play">Start keeping score</flux:button>
            </form>

            <div class="space-y-6 lg:col-span-2">
                {{-- Follow --}}
                <div
                    class="space-y-3 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-zinc-200 dark:bg-zinc-900 dark:ring-zinc-800"
                    x-data="scoreboardFollow"
                >
                    <div class="flex items-center gap-2">
                        <flux:icon.eye class="size-5 text-zinc-400" />
                        <flux:heading class="font-black">Follow a game</flux:heading>
                    </div>
                    <flux:text class="text-sm">Someone else keeping score? Type their code to see the totals live.</flux:text>

                    <form class="flex items-start gap-2" x-on:submit.prevent="follow()">
                        <flux:input
                            x-model="code"
                            x-on:input="error = null"
                            placeholder="ABCD"
                            maxlength="6"
                            autocapitalize="characters"
                            autocomplete="off"
                            aria-label="Game code"
                            class="w-32 text-center font-mono tracking-[0.3em] uppercase"
                        />
                        <flux:button type="submit" variant="primary" x-bind:disabled="busy">Follow</flux:button>
                    </form>

                    <p class="text-sm font-medium text-red-600 dark:text-red-400" x-show="error" x-text="error"></p>
                </div>

                {{-- Resume --}}
                <div
                    class="space-y-3 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-zinc-200 dark:bg-zinc-900 dark:ring-zinc-800"
                    x-show="records.length"
                >
                    <flux:heading class="font-black">Your games</flux:heading>

                    <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        <template x-for="record in records" :key="record.id">
                            <li class="flex items-center gap-3 py-2.5">
                                <button type="button" class="min-w-0 flex-1 text-left" x-on:click="open(record)">
                                    <div class="flex items-center gap-2">
                                        <span class="truncate font-semibold text-zinc-800 dark:text-zinc-100" x-text="record.title || 'Untitled game'"></span>
                                        <span
                                            class="shrink-0 rounded bg-zinc-900/5 px-1.5 font-mono text-xs font-black tracking-widest text-zinc-500 dark:bg-white/10"
                                            x-show="record.code"
                                            x-text="record.code"
                                        ></span>
                                    </div>
                                    <div class="truncate text-xs text-zinc-500" x-text="`${record.players} · round ${record.round} · ${ago(record.savedAt)}`"></div>
                                </button>
                                <flux:button size="sm" variant="ghost" icon="trash" x-on:click="forget(record)" aria-label="Remove from this device" />
                                <flux:button size="sm" x-on:click="open(record)">Open</flux:button>
                            </li>
                        </template>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
