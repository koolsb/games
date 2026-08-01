<x-layouts.fullscreen :title="$layout->name.' — Qwixx'" game="qwixx">
    <div
        x-data="qwixxGame(@js($layout->toClientArray()), @js($mode))"
        class="h-full"
    >
        @if ($mode === 'solo')
            <div class="flex h-full flex-col">
                <div class="flex items-center justify-between px-4 py-2">
                    <flux:button href="{{ route('qwixx.picker') }}" variant="ghost" size="sm" icon="arrow-left">
                        Sheets
                    </flux:button>

                    <span class="text-sm font-bold text-zinc-500 dark:text-zinc-400">{{ $layout->name }}</span>

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
                            <flux:button size="sm" variant="ghost" icon="arrow-path">Reset</flux:button>
                        </flux:modal.trigger>
                    </div>
                </div>

                <div class="flex min-h-0 flex-1 items-center justify-center p-3">
                    <x-qwixx.scoresheet :layout="$layout" :player-index="0" />
                </div>
            </div>
        @else
            {{-- Duo: two sheets facing away from each other, iPad flat on the table. --}}
            <div class="grid h-full grid-rows-[1fr_auto_1fr]">
                <div class="flex min-h-0 rotate-180 items-center justify-center p-2">
                    <x-qwixx.scoresheet :layout="$layout" :player-index="1" compact />
                </div>

                <div class="flex items-center justify-center gap-2 border-y border-zinc-200 bg-white/60 px-4 py-1.5 dark:border-zinc-800 dark:bg-zinc-900/60">
                    <flux:button href="{{ route('qwixx.picker') }}" variant="ghost" size="sm" icon="arrow-left"></flux:button>
                    <span class="text-xs font-bold text-zinc-400">{{ $layout->name }} · 2 players</span>
                    <flux:button size="sm" variant="ghost" x-on:click="$flux.dark = ! $flux.dark" title="Light / dark mode">
                        <flux:icon.sun class="size-4" x-show="! $flux.dark" x-cloak />
                        <flux:icon.moon class="size-4" x-show="$flux.dark" x-cloak />
                    </flux:button>
                    <flux:button size="sm" variant="ghost" x-show="wlSupported" x-on:click="wlToggle()">
                        <flux:icon.bolt class="size-4" x-show="wlEnabled" />
                        <flux:icon.bolt-slash class="size-4" x-show="!wlEnabled" x-cloak />
                    </flux:button>
                    <flux:button size="sm" variant="ghost" x-show="fsSupported" x-cloak x-on:click="fsToggle()">
                        <flux:icon.arrows-pointing-out class="size-4" x-show="!fsActive" />
                        <flux:icon.arrows-pointing-in class="size-4" x-show="fsActive" x-cloak />
                    </flux:button>
                    <flux:modal.trigger name="reset-confirm">
                        <flux:button size="sm" variant="ghost" icon="arrow-path"></flux:button>
                    </flux:modal.trigger>
                </div>

                <div class="flex min-h-0 items-center justify-center p-2">
                    <x-qwixx.scoresheet :layout="$layout" :player-index="0" compact />
                </div>
            </div>
        @endif

        <flux:modal name="reset-confirm" class="min-w-[20rem]">
            <div class="space-y-4">
                <flux:heading size="lg">Start a new game?</flux:heading>
                <flux:text>
                    This clears every mark on {{ $mode === 'duo' ? 'both sheets' : 'this sheet' }}. There is no undo.
                </flux:text>
                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancel</flux:button>
                    </flux:modal.close>
                    <flux:modal.close>
                        <flux:button variant="danger" x-on:click="resetGame()">Reset</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        </flux:modal>
    </div>
</x-layouts.fullscreen>
