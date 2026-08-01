{{-- The root menu: one card per game in config/games.php. --}}
<x-layouts.app :title="config('app.name')">
    <div class="space-y-8">
        <div class="space-y-2">
            <flux:heading size="xl" class="font-black">Pick a game</flux:heading>
            <flux:text>
                Table-top helpers that replace the paper. Everything runs in the browser — no accounts,
                nothing to install.
            </flux:text>
        </div>

        <div class="grid gap-6 sm:grid-cols-2">
            {{-- Qwixx: the classic sheet's four rows as the preview. --}}
            <a
                href="{{ route('qwixx.picker') }}"
                class="group flex flex-col gap-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-zinc-200 transition hover:shadow-md hover:ring-zinc-300 dark:bg-zinc-900 dark:ring-zinc-800 dark:hover:ring-zinc-700"
            >
                <div class="space-y-1">
                    <flux:heading size="lg" class="font-black">
                        <x-site.wordmark game="qwixx" />
                    </flux:heading>
                    <flux:text class="text-sm">{{ config('games.games.qwixx.summary') }}</flux:text>
                </div>

                <div class="space-y-1">
                    @foreach ($qwixxLayout->rows as $row)
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

                <span class="mt-auto flex items-center gap-1 pt-1 text-sm font-semibold text-accent-content">
                    Open scoresheets
                    <flux:icon.arrow-right class="size-4 transition group-hover:translate-x-0.5" />
                </span>
            </a>

            {{-- Phase 10: a miniature of the printed card. --}}
            <a
                href="{{ route('phase10.generator') }}"
                class="group flex flex-col gap-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-zinc-200 transition hover:shadow-md hover:ring-zinc-300 dark:bg-zinc-900 dark:ring-zinc-800 dark:hover:ring-zinc-700"
            >
                <div class="space-y-1">
                    <flux:heading size="lg" class="font-black">
                        <x-site.wordmark game="phase10" />
                    </flux:heading>
                    <flux:text class="text-sm">{{ config('games.games.phase10.summary') }}</flux:text>
                </div>

                <div class="phase10-card-bg overflow-hidden rounded-lg text-white shadow-sm ring-1 ring-black/10">
                    <ol class="space-y-1 px-4 py-3 text-[11px] leading-snug font-semibold">
                        @foreach (['2 sets of 3', '1 run of 8', '1 set of 4 + 1 run of 4'] as $i => $label)
                            <li class="flex items-baseline gap-2">
                                <span class="w-3 shrink-0 text-right font-bold text-white/60 tabular-nums">{{ $i + 1 }}.</span>
                                <span>{{ $label }}</span>
                            </li>
                        @endforeach
                        <li class="flex items-baseline gap-2 text-white/50">
                            <span class="w-3 shrink-0 text-right tabular-nums">4.</span>
                            <span>…</span>
                        </li>
                    </ol>
                    <div class="phase10-stripe h-2 w-full"></div>
                </div>

                <span class="mt-auto flex items-center gap-1 pt-1 text-sm font-semibold text-accent-content">
                    Open generator
                    <flux:icon.arrow-right class="size-4 transition group-hover:translate-x-0.5" />
                </span>
            </a>
        </div>
    </div>
</x-layouts.app>
