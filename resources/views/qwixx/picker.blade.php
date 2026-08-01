<x-layouts.app title="Qwixx Scoresheets" game="qwixx">
    <div class="space-y-8">
        <div class="space-y-2">
            <flux:heading size="xl" class="font-black">Pick a scoresheet</flux:heading>
            <flux:text>
                Tap numbers to cross them out — left to right, just like the pad. Scores tally themselves,
                and the sheet survives a refresh.
            </flux:text>
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

                    <div class="mt-auto flex gap-2">
                        <flux:button href="{{ route('qwixx.game', [$layout->id, 'solo']) }}" variant="primary" class="flex-1">
                            Solo
                        </flux:button>
                        <flux:button href="{{ route('qwixx.game', [$layout->id, 'duo']) }}" variant="filled" class="flex-1">
                            2 players
                        </flux:button>
                    </div>
                </div>
            @endforeach
        </div>

        <flux:text class="text-sm">
            In 2-player mode the iPad lies flat between you — the top sheet is upside down on purpose.
            Locking a row locks it for the other player too. The small circled button at a row's end
            marks a row locked by someone playing on paper.
        </flux:text>
    </div>
</x-layouts.app>
