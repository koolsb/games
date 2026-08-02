<x-layouts.fullscreen :title="'Game '.$room->code.' — Qwixx'" game="qwixx">
    {{--
        One screen for the whole multiplayer game: taking a seat, waiting in
        the lobby, playing, and the final scores. Which one you see is a
        function of the room's status and whether this device holds a seat,
        so nobody navigates mid-game and a shared link always lands somewhere
        sensible.
    --}}
    <div
        x-data="qwixxGame(@js($layout->toClientArray()), 'multi', @js($room->toClientArray()))"
        class="flex h-full flex-col"
        x-cloak
    >
        <div class="flex items-center justify-between gap-2 px-4 py-2">
            <flux:button href="{{ route('qwixx.picker') }}" variant="ghost" size="sm" icon="arrow-left">
                Sheets
            </flux:button>

            <div class="flex min-w-0 items-center gap-2">
                <span class="hidden text-sm font-bold text-zinc-500 sm:inline dark:text-zinc-400">
                    {{ $layout->name }}
                </span>
                <button
                    type="button"
                    class="rounded-lg bg-zinc-200/70 px-2 py-0.5 font-mono text-sm font-black tracking-[0.3em] text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200"
                    x-on:click="copyCode()"
                    title="Copy the link to this game"
                >{{ $room->code }}</button>
                <span class="text-xs font-semibold text-emerald-600 dark:text-emerald-400" x-show="copied" x-cloak>
                    Link copied
                </span>
            </div>

            <div class="flex items-center gap-1">
                <flux:button size="sm" variant="ghost" x-on:click="$flux.dark = ! $flux.dark" title="Light / dark mode">
                    <flux:icon.sun class="size-4" x-show="! $flux.dark" x-cloak />
                    <flux:icon.moon class="size-4" x-show="$flux.dark" x-cloak />
                </flux:button>
                <flux:button
                    size="sm"
                    variant="ghost"
                    x-show="wlSupported"
                    x-on:click="wlToggle()"
                    x-bind:title="wlEnabled ? 'Screen stays awake — tap to allow sleep' : 'Screen may sleep — tap to keep awake'"
                >
                    <flux:icon.bolt class="size-4" x-show="wlEnabled" />
                    <flux:icon.bolt-slash class="size-4" x-show="!wlEnabled" x-cloak />
                </flux:button>
                <flux:button
                    size="sm"
                    variant="ghost"
                    x-show="fsSupported"
                    x-cloak
                    x-on:click="fsToggle()"
                    x-bind:title="fsActive ? 'Exit full screen' : 'Full screen'"
                >
                    <flux:icon.arrows-pointing-out class="size-4" x-show="!fsActive" />
                    <flux:icon.arrows-pointing-in class="size-4" x-show="fsActive" x-cloak />
                </flux:button>
                <flux:modal.trigger name="reset-confirm">
                    <flux:button size="sm" variant="ghost" icon="arrow-path" x-show="isHost && !inLobby">
                        New game
                    </flux:button>
                </flux:modal.trigger>
            </div>
        </div>

        {{-- The room expired or was never there. Rooms are throwaway by
             design, so say so plainly rather than spinning forever. --}}
        <template x-if="gone">
            <div class="flex min-h-0 flex-1 items-center justify-center p-6">
                <div class="max-w-sm space-y-4 text-center">
                    <flux:heading size="lg">This game has finished</flux:heading>
                    <flux:text>
                        Games are cleared once everyone has stopped playing. Start a fresh one from the sheets page.
                    </flux:text>
                    <flux:button href="{{ route('qwixx.picker') }}" variant="primary">Back to sheets</flux:button>
                </div>
            </div>
        </template>

        {{-- No seat on this device: join, if the game has not started. --}}
        <template x-if="!gone && !seated">
            <div class="flex min-h-0 flex-1 items-center justify-center p-6">
                <div class="w-full max-w-sm space-y-5">
                    <div class="space-y-1 text-center">
                        <flux:heading size="lg">Join game {{ $room->code }}</flux:heading>
                        <flux:text>
                            <span x-text="roster.length"></span> already in ·
                            {{ $layout->name }} sheet
                        </flux:text>
                    </div>

                    <template x-if="inLobby">
                        <form class="space-y-3" x-on:submit.prevent="join()">
                            <flux:input
                                x-model="joinName"
                                label="Your name"
                                placeholder="Name on the scoreboard"
                                maxlength="14"
                                autofocus
                            />
                            <p class="text-sm font-medium text-red-600 dark:text-red-400" x-show="joinError" x-cloak x-text="joinError"></p>
                            <flux:button type="submit" variant="primary" class="w-full" x-bind:disabled="busy">
                                Join game
                            </flux:button>
                        </form>
                    </template>

                    <template x-if="!inLobby">
                        <div class="space-y-3 text-center">
                            <flux:callout icon="lock-closed" variant="warning">
                                <flux:callout.heading>Already started</flux:callout.heading>
                                <flux:callout.text>
                                    This game is under way, so no new sheets can be dealt. Ask the host to start
                                    another one when this round finishes.
                                </flux:callout.text>
                            </flux:callout>
                            <flux:button href="{{ route('qwixx.picker') }}" variant="ghost">Back to sheets</flux:button>
                        </div>
                    </template>
                </div>
            </div>
        </template>

        {{-- Seated, waiting for the host to deal. --}}
        <template x-if="!gone && seated && inLobby">
            <div class="flex min-h-0 flex-1 items-center justify-center overflow-y-auto p-6">
                <div class="w-full max-w-md space-y-6 text-center">
                    <div class="space-y-2">
                        <flux:text class="text-sm font-semibold uppercase tracking-wide">Game code</flux:text>
                        <button
                            type="button"
                            class="w-full rounded-2xl bg-white py-4 font-mono text-5xl font-black tracking-[0.35em] text-zinc-800 shadow-sm ring-1 ring-zinc-200 dark:bg-zinc-900 dark:text-zinc-100 dark:ring-zinc-800"
                            x-on:click="copyCode()"
                        >{{ $room->code }}</button>
                        <flux:text class="text-sm">
                            Everyone opens <span class="font-semibold">games.kools.us/qwixx</span>, taps
                            <span class="font-semibold">Join a game</span> and types this code. Tap the code to copy
                            a link instead.
                        </flux:text>
                    </div>

                    <div class="space-y-2">
                        <flux:text class="text-sm font-semibold uppercase tracking-wide">
                            Players (<span x-text="roster.length"></span>)
                        </flux:text>
                        <div class="flex flex-wrap justify-center gap-2">
                            <template x-for="player in roster" x-bind:key="player.id">
                                <span
                                    class="flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-semibold"
                                    x-bind:class="player.id === playerId
                                        ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900'
                                        : 'bg-zinc-200/70 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200'"
                                >
                                    <span x-text="player.name"></span>
                                    <flux:icon.star variant="solid" class="size-3.5 opacity-70" x-show="player.isHost" />
                                </span>
                            </template>
                        </div>
                    </div>

                    <template x-if="isHost">
                        <flux:button variant="primary" class="w-full" x-on:click="startGame()" x-bind:disabled="busy">
                            Start game
                        </flux:button>
                    </template>

                    <template x-if="!isHost">
                        <flux:text class="text-sm">Waiting for the host to start…</flux:text>
                    </template>
                </div>
            </div>
        </template>

        {{-- Playing. One sheet, sized like a solo game, plus the standings. --}}
        <template x-if="!gone && seated && !inLobby">
            <div class="flex min-h-0 flex-1 flex-col">
                <div class="border-y border-zinc-200 bg-white/60 dark:border-zinc-800 dark:bg-zinc-900/60">
                    <x-qwixx.standings />
                </div>

                <div class="flex min-h-0 flex-1 items-center justify-center p-3">
                    <x-qwixx.scoresheet :layout="$layout" player-index="me" mode="multi" />
                </div>
            </div>
        </template>

        <flux:modal name="reset-confirm" class="min-w-[20rem]">
            <div class="space-y-4">
                <flux:heading size="lg">Start a new game?</flux:heading>
                <flux:text>
                    This clears every sheet in the game, for all
                    <span x-text="roster.length"></span> players. There is no undo.
                </flux:text>
                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancel</flux:button>
                    </flux:modal.close>
                    <flux:modal.close>
                        <flux:button variant="danger" x-on:click="resetGame()">New game</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        </flux:modal>
    </div>
</x-layouts.fullscreen>
