<x-layouts.app title="Qwixx Scoresheets" game="qwixx">
    <div class="space-y-8" x-data="qwixxLauncher">
        <div class="space-y-2">
            <flux:heading size="xl" class="font-black">Pick a scoresheet</flux:heading>
            <flux:text>
                Tap numbers to cross them out — left to right, just like the pad. Scores tally themselves,
                and the sheet survives a refresh.
            </flux:text>
        </div>

        {{-- Joining needs no layout — the host already picked one. --}}
        <div class="flex flex-wrap items-center gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-zinc-200 dark:bg-zinc-900 dark:ring-zinc-800">
            <flux:icon.users class="size-5 text-zinc-400" />
            <flux:text class="flex-1 text-sm">
                Playing on separate devices? Someone hosts a game from a sheet below and reads out the
                four-letter code — everyone else joins it here.
            </flux:text>
            <flux:modal.trigger name="join-game">
                <flux:button variant="primary" size="sm">Join a game</flux:button>
            </flux:modal.trigger>
        </div>

        {{-- Resume an in-progress game found in this device's storage. --}}
        <div x-data="qwixxResume" x-show="hasMarks" x-cloak>
            <flux:callout icon="play" variant="secondary">
                <flux:callout.heading>Game in progress</flux:callout.heading>
                <flux:callout.text>
                    You have an unfinished <span class="font-semibold" x-text="game?.layout"></span>
                    <span x-text="game?.mode === 'duo' ? '2-player' : 'solo'"></span> game on this device.
                </flux:callout.text>
                <x-slot name="actions">
                    <flux:button size="sm" variant="primary" x-bind:href="game && `/qwixx/play/${game.layout}/${game.mode}`">
                        Resume
                    </flux:button>
                    <flux:button size="sm" variant="ghost" x-on:click="discard()">Discard</flux:button>
                </x-slot>
            </flux:callout>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            @foreach ($layouts as $layout)
                <div class="flex flex-col gap-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-zinc-200 dark:bg-zinc-900 dark:ring-zinc-800">
                    <div class="space-y-1">
                        <flux:heading size="lg">{{ $layout->name }}</flux:heading>
                        <flux:text class="text-sm">{{ $layout->description }}</flux:text>
                    </div>

                    {{-- Mini preview of the four rows. --}}
                    <div class="space-y-1">
                        @foreach ($layout->rows as $row)
                            <div class="flex">
                                @foreach ($row->cells as $cell)
                                    <span
                                        class="flex h-5 flex-1 items-center justify-center text-[9px] font-black text-white/90 first:rounded-l-sm"
                                        style="background: var(--color-qwixx-{{ $cell->color->value }})"
                                    >{{ $cell->number }}</span>
                                @endforeach
                                <span
                                    class="flex h-5 w-6 items-center justify-center rounded-r-sm"
                                    style="background: var(--color-qwixx-{{ $row->lockColor->value }})"
                                >
                                    <flux:icon.lock-closed variant="solid" class="size-3 text-white/90" />
                                </span>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-auto space-y-2">
                        <div class="flex gap-2">
                            <flux:button href="{{ route('qwixx.game', [$layout->id, 'solo']) }}" variant="primary" class="flex-1">
                                Solo
                            </flux:button>
                            <flux:button href="{{ route('qwixx.game', [$layout->id, 'duo']) }}" variant="filled" class="flex-1">
                                2 players
                            </flux:button>
                        </div>
                        {{-- Plain interpolation, not @js(): a component tag's
                             attributes are compiled without directives. Layout
                             ids are config-file slugs, so quoting is enough. --}}
                        <flux:modal.trigger name="host-game">
                            <flux:button
                                variant="filled"
                                icon="users"
                                class="w-full"
                                x-on:click="hostLayout = '{{ $layout->id }}'"
                            >
                                Multiplayer
                            </flux:button>
                        </flux:modal.trigger>
                    </div>
                </div>
            @endforeach
        </div>

        <flux:text class="text-sm">
            In 2-player mode the iPad lies flat between you — the top sheet is upside down on purpose.
            In multiplayer everyone plays on their own device and locked rows travel between them.
            Either way, the small circled button at a row's end marks a row locked by someone playing on paper.
        </flux:text>

        <flux:modal name="host-game" class="min-w-[22rem]">
            <form class="space-y-4" x-on:submit.prevent="host()">
                <div class="space-y-1">
                    <flux:heading size="lg">Host a game</flux:heading>
                    <flux:text>
                        You'll get a four-letter code to read out. Everyone joins on their own device and
                        plays the sheet you picked.
                    </flux:text>
                </div>

                <flux:input x-model="name" label="Your name" placeholder="Name on the scoreboard" maxlength="14" />
                <p class="text-sm font-medium text-red-600 dark:text-red-400" x-show="error" x-cloak x-text="error"></p>

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost" type="button">Cancel</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary" x-bind:disabled="busy">Create game</flux:button>
                </div>
            </form>
        </flux:modal>

        <flux:modal name="join-game" class="min-w-[22rem]">
            <form class="space-y-4" x-on:submit.prevent="join()">
                <div class="space-y-1">
                    <flux:heading size="lg">Join a game</flux:heading>
                    <flux:text>Type the four-letter code the host read out.</flux:text>
                </div>

                <flux:input
                    x-model="code"
                    label="Game code"
                    placeholder="ABCD"
                    maxlength="4"
                    autocapitalize="characters"
                    autocomplete="off"
                    class="font-mono text-lg uppercase tracking-[0.3em]"
                />
                <flux:input x-model="name" label="Your name" placeholder="Name on the scoreboard" maxlength="14" />
                <p class="text-sm font-medium text-red-600 dark:text-red-400" x-show="error" x-cloak x-text="error"></p>

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost" type="button">Cancel</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary" x-bind:disabled="busy">Join</flux:button>
                </div>
            </form>
        </flux:modal>
    </div>
</x-layouts.app>
